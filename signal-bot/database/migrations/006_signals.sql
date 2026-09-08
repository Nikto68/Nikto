-- The standard Signal object (spec #13), its lifecycle history, and its
-- per-channel Telegram delivery tracking (needed to edit/pin the right
-- message later without re-sending).

CREATE TABLE IF NOT EXISTS signals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    exchange_id BIGINT UNSIGNED NOT NULL,
    symbol_id BIGINT UNSIGNED NOT NULL,
    strategy_id BIGINT UNSIGNED NULL,
    direction ENUM('LONG', 'SHORT') NOT NULL,
    timeframe VARCHAR(4) NOT NULL,
    entry DECIMAL(24, 12) NOT NULL,
    stop_loss DECIMAL(24, 12) NOT NULL,
    take_profit_1 DECIMAL(24, 12) NULL,
    take_profit_2 DECIMAL(24, 12) NULL,
    take_profit_3 DECIMAL(24, 12) NULL,
    risk_reward DECIMAL(8, 3) NULL,
    score TINYINT UNSIGNED NOT NULL,
    confidence VARCHAR(16) NULL,
    reasons JSON NOT NULL DEFAULT (JSON_ARRAY()),
    zones JSON NOT NULL DEFAULT (JSON_ARRAY()) COMMENT 'zone/order-block/FVG ids and snapshots that fed this signal',
    indicators JSON NOT NULL DEFAULT (JSON_OBJECT()) COMMENT 'indicator values at signal time, for later audit',
    fingerprint VARCHAR(191) NOT NULL COMMENT 'exchange+symbol+direction+strategy+zone+timeframe, see SignalCooldown',
    mode ENUM('LIVE', 'DRY_RUN') NOT NULL DEFAULT 'DRY_RUN',
    status ENUM('new', 'active', 'closed', 'cancelled', 'invalidated') NOT NULL DEFAULT 'new',
    close_reason VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    UNIQUE KEY uq_signals_uuid (uuid),
    KEY idx_signals_fingerprint_status (fingerprint, status),
    KEY idx_signals_symbol_created (symbol_id, created_at),
    KEY idx_signals_status (status),
    CONSTRAINT fk_signals_exchange FOREIGN KEY (exchange_id) REFERENCES exchanges (id) ON DELETE CASCADE,
    CONSTRAINT fk_signals_symbol FOREIGN KEY (symbol_id) REFERENCES symbols (id) ON DELETE CASCADE,
    CONSTRAINT fk_signals_strategy FOREIGN KEY (strategy_id) REFERENCES strategies (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS signal_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    signal_id BIGINT UNSIGNED NOT NULL,
    event_type ENUM(
        'created', 'sent', 'edited', 'tp1_hit', 'tp2_hit', 'tp3_hit',
        'sl_hit', 'closed', 'cancelled', 'invalidated'
    ) NOT NULL,
    payload JSON NOT NULL DEFAULT (JSON_OBJECT()),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_signal_events_signal (signal_id, created_at),
    CONSTRAINT fk_signal_events_signal FOREIGN KEY (signal_id) REFERENCES signals (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS signal_channel_deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    signal_id BIGINT UNSIGNED NOT NULL,
    channel_id BIGINT UNSIGNED NOT NULL,
    telegram_message_id BIGINT NULL,
    status ENUM('pending', 'sent', 'edited', 'failed') NOT NULL DEFAULT 'pending',
    error TEXT NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_signal_channel (signal_id, channel_id),
    CONSTRAINT fk_deliveries_signal FOREIGN KEY (signal_id) REFERENCES signals (id) ON DELETE CASCADE,
    CONSTRAINT fk_deliveries_channel FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
