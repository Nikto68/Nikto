<?php

declare(strict_types=1);

namespace App\Database;

use App\Database\Exceptions\DatabaseException;
use Psr\Log\LoggerInterface;

/**
 * Applies database/migrations/*.sql in filename order, tracking what ran in
 * schema_migrations so re-running `bin/migrate.php` is a no-op once the
 * schema is current. Deliberately simple (no up/down pairs, no rollback):
 * this is an additive signal/scanner schema, not a rolling-release app.
 */
final class Migration
{
    public function __construct(
        private readonly Database $database,
        private readonly LoggerInterface $logger,
        private readonly string $migrationsPath,
    ) {
    }

    /**
     * @return string[] migrations that were applied during this call
     */
    public function run(): array
    {
        $this->ensureMigrationsTable();

        $applied = array_column(
            $this->database->select('SELECT migration FROM schema_migrations'),
            'migration',
        );

        $files = glob(rtrim($this->migrationsPath, '/') . '/*.sql') ?: [];
        sort($files);

        $newlyApplied = [];

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }

            $this->applyFile($file, $name);
            $newlyApplied[] = $name;
        }

        return $newlyApplied;
    }

    private function applyFile(string $file, string $name): void
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new DatabaseException("Could not read migration file [$name].");
        }

        $pdo = $this->database->pdo();

        foreach ($this->splitStatements($sql) as $statement) {
            try {
                $pdo->exec($statement);
            } catch (\PDOException $e) {
                $this->logger->error('Migration failed', ['migration' => $name]);

                throw new DatabaseException("Migration [$name] failed: " . $e->getMessage(), 0, $e);
            }
        }

        $this->database->insert('schema_migrations', ['migration' => $name]);
        $this->logger->info('Migration applied', ['migration' => $name]);
    }

    /**
     * @return string[]
     */
    private function splitStatements(string $sql): array
    {
        $sql = preg_replace('/^--.*$/m', '', $sql) ?? $sql;
        $statements = array_map('trim', explode(";\n", $sql . "\n"));

        return array_values(array_filter($statements, static fn (string $s): bool => $s !== ''));
    }

    private function ensureMigrationsTable(): void
    {
        $this->database->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (' .
            'migration VARCHAR(191) NOT NULL PRIMARY KEY, ' .
            'applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }
}
