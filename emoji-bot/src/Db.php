<?php
declare(strict_types=1);

namespace EmojiBot;

use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Thin PDO wrapper that works with SQLite (default) and MySQL/MariaDB.
 */
final class Db
{
    private const SCHEMA_VERSION = 1;

    private function __construct(public readonly PDO $pdo, public readonly string $driver)
    {
    }

    public static function connect(array $cfg): self
    {
        $driver = $cfg['driver'] ?? 'sqlite';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if ($driver === 'mysql') {
            $m = $cfg['mysql'] ?? [];
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $m['host'] ?? 'localhost',
                (int) ($m['port'] ?? 3306),
                $m['name'] ?? '',
            );
            $pdo = new PDO($dsn, $m['user'] ?? '', $m['pass'] ?? '', $options);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00'");
        } elseif ($driver === 'sqlite') {
            $path = $cfg['sqlite_path'] ?? (EMOJIBOT_ROOT . '/storage/database.sqlite');
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $pdo = new PDO('sqlite:' . $path, null, null, $options);
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA busy_timeout = 10000');
            $pdo->exec('PRAGMA synchronous = NORMAL');
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            throw new RuntimeException("Unsupported db driver: $driver");
        }
        $db = new self($pdo, $driver);
        $db->migrate();
        return $db;
    }

    public function q(string $sql, array $params = []): PDOStatement
    {
        $st = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $key = is_int($k) ? $k + 1 : (str_starts_with($k, ':') ? $k : ':' . $k);
            $type = match (true) {
                is_int($v) => PDO::PARAM_INT,
                is_bool($v) => PDO::PARAM_INT,
                $v === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $st->bindValue($key, is_bool($v) ? (int) $v : $v, $type);
        }
        $st->execute();
        return $st;
    }

    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->q($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->q($sql, $params)->fetchAll();
    }

    public function val(string $sql, array $params = []): mixed
    {
        $v = $this->q($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** Executes a statement and returns the number of affected rows. */
    public function exec(string $sql, array $params = []): int
    {
        return $this->q($sql, $params)->rowCount();
    }

    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', array_map(fn ($c) => ':' . $c, $cols)),
        );
        $this->q($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    /** INSERT ... or ignore when the unique key already exists. Returns true if a row was inserted. */
    public function insertIgnore(string $table, array $data): bool
    {
        $cols = array_keys($data);
        $verb = $this->driver === 'mysql' ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
        $sql = sprintf(
            '%s INTO %s (%s) VALUES (%s)',
            $verb,
            $table,
            implode(', ', $cols),
            implode(', ', array_map(fn ($c) => ':' . $c, $cols)),
        );
        return $this->q($sql, $data)->rowCount() > 0;
    }

    /**
     * Insert or update by primary/unique key.
     * @param string[] $keys  conflict columns
     * @param array<string,string>|null $updates column => SQL expression; defaults to "new value" for every non-key column
     */
    public function upsert(string $table, array $data, array $keys, ?array $updates = null): void
    {
        $cols = array_keys($data);
        $placeholders = implode(', ', array_map(fn ($c) => ':' . $c, $cols));
        if ($updates === null) {
            $updates = [];
            foreach ($cols as $c) {
                if (!in_array($c, $keys, true)) {
                    $updates[$c] = $this->newValue($c);
                }
            }
        }
        $set = implode(', ', array_map(fn ($c, $e) => "$c = $e", array_keys($updates), $updates));
        if ($this->driver === 'mysql') {
            $sql = "INSERT INTO $table (" . implode(', ', $cols) . ") VALUES ($placeholders)"
                . ($set !== '' ? " ON DUPLICATE KEY UPDATE $set" : '');
        } else {
            $sql = "INSERT INTO $table (" . implode(', ', $cols) . ") VALUES ($placeholders)"
                . ' ON CONFLICT(' . implode(', ', $keys) . ')'
                . ($set !== '' ? " DO UPDATE SET $set" : ' DO NOTHING');
        }
        $this->q($sql, $data);
    }

    /** SQL expression that refers to the value being inserted, for use inside upsert updates. */
    public function newValue(string $column): string
    {
        return $this->driver === 'mysql' ? "VALUES($column)" : "excluded.$column";
    }

    /** Runs $fn in a transaction. Nested calls join the outer transaction. */
    public function tx(callable $fn): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $fn($this);
        }
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function migrate(): void
    {
        $exists = $this->driver === 'mysql'
            ? $this->val("SHOW TABLES LIKE 'meta'")
            : $this->val("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'meta'");
        $version = $exists ? (int) $this->val("SELECT v FROM meta WHERE k = 'schema'") : 0;
        if ($version >= self::SCHEMA_VERSION) {
            return;
        }

        $my = $this->driver === 'mysql';
        $pk = $my ? 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $text = $my ? 'MEDIUMTEXT' : 'TEXT';

        $tables = [
            "CREATE TABLE IF NOT EXISTS meta (k VARCHAR(64) PRIMARY KEY, v VARCHAR(255) NOT NULL)$tail",
            "CREATE TABLE IF NOT EXISTS users (
                id BIGINT PRIMARY KEY,
                first_name VARCHAR(128) NOT NULL DEFAULT '',
                last_name VARCHAR(128) NOT NULL DEFAULT '',
                username VARCHAR(64) NOT NULL DEFAULT '',
                lang VARCHAR(16) NOT NULL DEFAULT '',
                is_premium SMALLINT NOT NULL DEFAULT 0,
                coins INT NOT NULL DEFAULT 0,
                banned SMALLINT NOT NULL DEFAULT 0,
                blocked SMALLINT NOT NULL DEFAULT 0,
                state VARCHAR(64) NULL,
                state_data $text NULL,
                referrer_id BIGINT NULL,
                last_gift_at INT NOT NULL DEFAULT 0,
                join_checked_at INT NOT NULL DEFAULT 0,
                packs_count INT NOT NULL DEFAULT 0,
                emojis_count INT NOT NULL DEFAULT 0,
                created_at INT NOT NULL,
                last_seen INT NOT NULL
            )$tail",
            "CREATE TABLE IF NOT EXISTS packs (
                id $pk,
                user_id BIGINT NOT NULL,
                name VARCHAR(64) NOT NULL UNIQUE,
                title VARCHAR(128) NOT NULL,
                emoji_count INT NOT NULL DEFAULT 0,
                cover $text NULL,
                created_at INT NOT NULL,
                updated_at INT NOT NULL,
                deleted SMALLINT NOT NULL DEFAULT 0
            )$tail",
            "CREATE TABLE IF NOT EXISTS pack_items (
                id $pk,
                pack_id BIGINT NOT NULL,
                template_id VARCHAR(32) NOT NULL,
                params $text NOT NULL,
                format VARCHAR(8) NOT NULL,
                created_at INT NOT NULL
            )$tail",
            "CREATE TABLE IF NOT EXISTS templates (
                id $pk,
                title VARCHAR(64) NOT NULL DEFAULT '',
                category VARCHAR(64) NOT NULL DEFAULT '',
                emoji VARCHAR(32) NOT NULL DEFAULT '✨',
                config $text NOT NULL,
                sort INT NOT NULL DEFAULT 0,
                enabled SMALLINT NOT NULL DEFAULT 0,
                version INT NOT NULL DEFAULT 1,
                created_at INT NOT NULL,
                updated_at INT NOT NULL
            )$tail",
            "CREATE TABLE IF NOT EXISTS jobs (
                id $pk,
                user_id BIGINT NOT NULL,
                type VARCHAR(16) NOT NULL,
                payload $text NOT NULL,
                status VARCHAR(16) NOT NULL,
                progress INT NOT NULL DEFAULT 0,
                total INT NOT NULL DEFAULT 0,
                cost INT NOT NULL DEFAULT 0,
                result $text NULL,
                error VARCHAR(255) NULL,
                created_at INT NOT NULL,
                started_at INT NULL,
                finished_at INT NULL
            )$tail",
            "CREATE TABLE IF NOT EXISTS payments (
                id $pk,
                user_id BIGINT NOT NULL,
                stars INT NOT NULL,
                coins INT NOT NULL,
                charge_id VARCHAR(191) NOT NULL UNIQUE,
                payload VARCHAR(128) NOT NULL,
                refunded SMALLINT NOT NULL DEFAULT 0,
                created_at INT NOT NULL
            )$tail",
            "CREATE TABLE IF NOT EXISTS coin_log (
                id $pk,
                user_id BIGINT NOT NULL,
                delta INT NOT NULL,
                balance INT NOT NULL,
                reason VARCHAR(32) NOT NULL,
                ref VARCHAR(64) NOT NULL DEFAULT '',
                created_at INT NOT NULL
            )$tail",
            "CREATE TABLE IF NOT EXISTS settings (k VARCHAR(64) PRIMARY KEY, v $text NOT NULL)$tail",
            "CREATE TABLE IF NOT EXISTS rate_limits (k VARCHAR(128) PRIMARY KEY, win BIGINT NOT NULL, hits INT NOT NULL)$tail",
            "CREATE TABLE IF NOT EXISTS broadcasts (
                id $pk,
                from_chat_id BIGINT NOT NULL,
                message_id BIGINT NOT NULL,
                status VARCHAR(16) NOT NULL,
                cursor_id BIGINT NOT NULL DEFAULT 0,
                sent INT NOT NULL DEFAULT 0,
                failed INT NOT NULL DEFAULT 0,
                total INT NOT NULL DEFAULT 0,
                created_by BIGINT NOT NULL,
                created_at INT NOT NULL,
                finished_at INT NULL
            )$tail",
            "CREATE TABLE IF NOT EXISTS updates_seen (update_id BIGINT PRIMARY KEY, at INT NOT NULL)$tail",
        ];
        $indexes = [
            ['users', 'idx_users_created', 'created_at'],
            ['packs', 'idx_packs_user', 'user_id'],
            ['pack_items', 'idx_items_pack', 'pack_id'],
            ['jobs', 'idx_jobs_status', 'status'],
            ['jobs', 'idx_jobs_user', 'user_id'],
            ['coin_log', 'idx_coinlog_user', 'user_id'],
            ['payments', 'idx_payments_user', 'user_id'],
        ];

        foreach ($tables as $sql) {
            $this->pdo->exec($sql);
        }
        foreach ($indexes as [$table, $name, $col]) {
            if ($my) {
                $has = $this->val("SHOW INDEX FROM $table WHERE Key_name = ?", [$name]);
                if (!$has) {
                    $this->pdo->exec("CREATE INDEX $name ON $table ($col)");
                }
            } else {
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS $name ON $table ($col)");
            }
        }
        $this->upsert('meta', ['k' => 'schema', 'v' => (string) self::SCHEMA_VERSION], ['k']);
    }
}
