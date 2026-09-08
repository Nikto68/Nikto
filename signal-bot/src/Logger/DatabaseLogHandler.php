<?php

declare(strict_types=1);

namespace App\Logger;

use App\Database\Database;
use Closure;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

/**
 * Mirrors ERROR-and-above records into the `logs` table so the admin
 * panel's "📋 Logs" screen and System Health's "Errors Today" counter
 * (spec #23/#24) are cheap queries instead of tailing files over SSH.
 * storage/logs/*.log stays the full, authoritative record.
 *
 * Takes a lazy resolver rather than a Database instance directly: Database
 * itself needs a logger (for connection-error logging), so wiring this
 * eagerly at LoggerFactory construction time would be circular. The
 * resolver is only invoked the first time a record is actually written.
 */
final class DatabaseLogHandler extends AbstractProcessingHandler
{
    private ?Database $database = null;

    // If the `logs` INSERT below itself fails (e.g. the DB is
    // unreachable), Database::run() logs that failure through this same
    // channel, which re-enters write() on this same handler instance —
    // without this guard that is unbounded recursion, not just a dropped
    // log line.
    private bool $writing = false;

    public function __construct(
        private readonly Closure $databaseResolver,
    ) {
        parent::__construct(Level::Error);
    }

    protected function write(LogRecord $record): void
    {
        if ($this->writing) {
            return;
        }

        $this->writing = true;

        try {
            $db = $this->database ??= ($this->databaseResolver)();

            $db->insert('logs', [
                'level' => $record->level->getName(),
                'component' => $record->channel,
                'message' => $record->message,
                'context' => json_encode([...$record->context, ...$record->extra], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
            ]);
        } catch (Throwable) {
            // Never let the logging path itself take the app down — the
            // full record is already safely on disk via the file handler.
        } finally {
            $this->writing = false;
        }
    }
}
