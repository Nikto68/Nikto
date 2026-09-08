-- Operational tables: scan history (System Health), DB-mirrored error log
-- (for the in-bot "📋 Logs" viewer — full detail always stays in
-- storage/logs/*.log), and the queue backing the worker's job dispatch.

CREATE TABLE IF NOT EXISTS scanner_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exchange_id BIGINT UNSIGNED NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    symbols_scanned INT UNSIGNED NOT NULL DEFAULT 0,
    signals_generated INT UNSIGNED NOT NULL DEFAULT 0,
    errors_count INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('running', 'completed', 'failed') NOT NULL DEFAULT 'running',
    notes TEXT NULL,
    KEY idx_scanner_runs_started (started_at),
    CONSTRAINT fk_scanner_runs_exchange FOREIGN KEY (exchange_id) REFERENCES exchanges (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Populated by App\Logger\DatabaseLogHandler for level >= ERROR only, so
-- "Errors Today" in System Health and the Logs admin screen are cheap
-- queries. This is a mirror for the admin UI, not the source of truth —
-- storage/logs/*.log holds every level and full stack traces.
CREATE TABLE IF NOT EXISTS logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level VARCHAR(16) NOT NULL,
    component VARCHAR(64) NOT NULL,
    message TEXT NOT NULL,
    context JSON NOT NULL DEFAULT (JSON_OBJECT()),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_logs_component_created (component, created_at),
    KEY idx_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Database-backed queue (spec #29). Signal dispatch and every outbound
-- Telegram call go through here so a transient Telegram API failure never
-- blocks the scanner loop; queue_worker.php drains this with retry/backoff.
CREATE TABLE IF NOT EXISTS jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue VARCHAR(64) NOT NULL DEFAULT 'default',
    job_type VARCHAR(128) NOT NULL COMMENT 'FQCN implementing App\\Queue\\JobInterface',
    payload JSON NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'job is not picked up before this time (backoff delay)',
    reserved_at DATETIME NULL,
    status ENUM('pending', 'reserved', 'done', 'failed') NOT NULL DEFAULT 'pending',
    last_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_jobs_pickup (queue, status, available_at),
    KEY idx_jobs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
