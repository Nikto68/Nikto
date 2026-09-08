<?php

declare(strict_types=1);

namespace App\Database;

use App\Core\Config;
use App\Database\Exceptions\DatabaseException;
use PDO;
use PDOException;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Thin PDO wrapper: prepared statements only (no raw string interpolation
 * anywhere -> no SQL injection surface), lazy connection, and a one-shot
 * reconnect for "MySQL server has gone away" which a long-running worker
 * will eventually hit on an idle connection.
 */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= $this->connect();
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function select(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public function statement(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): string
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn (string $c): string => "`$c`", $columns)),
            implode(', ', $placeholders),
        );

        $this->run($sql, $data);

        return $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
    public function update(string $table, array $data, array $where): int
    {
        $set = implode(', ', array_map(static fn (string $c): string => "`$c` = :set_$c", array_keys($data)));
        $condition = implode(' AND ', array_map(static fn (string $c): string => "`$c` = :where_$c", array_keys($where)));

        $params = [];
        foreach ($data as $key => $value) {
            $params["set_$key"] = $value;
        }
        foreach ($where as $key => $value) {
            $params["where_$key"] = $value;
        }

        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, $set, $condition);

        return $this->run($sql, $params)->rowCount();
    }

    /**
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $result = $callback($this);
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<int|string, mixed> $params
     */
    private function run(string $sql, array $params, bool $isRetry = false): PDOStatement
    {
        try {
            $statement = $this->pdo()->prepare($sql);
            $statement->execute($params);

            return $statement;
        } catch (PDOException $e) {
            if (!$isRetry && $this->isConnectionLost($e)) {
                $this->logger->warning('Database connection lost, reconnecting once', ['sql' => $sql]);
                $this->pdo = null;

                return $this->run($sql, $params, true);
            }

            $this->logger->error('Database query failed', [
                'sql' => $sql,
                'exception' => $e->getMessage(),
            ]);

            throw new DatabaseException('Database query failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function isConnectionLost(PDOException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'server has gone away')
            || str_contains($message, 'Lost connection')
            || str_contains($message, 'Connection refused')
            || (int) $e->getCode() === 2006;
    }

    private function connect(): PDO
    {
        $host = (string) $this->config->get('database.host');
        $port = (int) $this->config->get('database.port');
        $database = (string) $this->config->get('database.database');
        $charset = (string) $this->config->get('database.charset', 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        try {
            return new PDO(
                $dsn,
                (string) $this->config->get('database.username'),
                (string) $this->config->get('database.password'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => false,
                ],
            );
        } catch (PDOException $e) {
            $this->logger->error('Database connection failed', ['host' => $host, 'database' => $database]);

            throw new DatabaseException('Could not connect to the database.', 0, $e);
        }
    }
}
