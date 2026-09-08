-- Indicator plugin registry + cached outputs, and the strategy registry
-- whose scoring rules/confluences are configured here (never hard-coded —
-- see spec #36, filled in once real strategy rules are provided).

CREATE TABLE IF NOT EXISTS indicators (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL COMMENT 'EMA, RSI, MACD, ... matches IndicatorInterface::name()',
    name VARCHAR(64) NOT NULL,
    class_name VARCHAR(255) NOT NULL COMMENT 'FQCN implementing App\\Indicators\\IndicatorInterface',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    default_params JSON NOT NULL DEFAULT (JSON_OBJECT()),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_indicators_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache of computed indicator output per candle so the engine only computes
-- the newest candles incrementally instead of recalculating full history
-- (spec #41 performance requirement).
CREATE TABLE IF NOT EXISTS indicator_values (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    symbol_id BIGINT UNSIGNED NOT NULL,
    timeframe VARCHAR(4) NOT NULL,
    indicator_id BIGINT UNSIGNED NOT NULL,
    candle_open_time BIGINT UNSIGNED NOT NULL,
    value JSON NOT NULL,
    computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_indicator_values (symbol_id, timeframe, indicator_id, candle_open_time),
    KEY idx_indicator_values_lookup (symbol_id, timeframe, indicator_id, candle_open_time DESC),
    CONSTRAINT fk_indicator_values_symbol FOREIGN KEY (symbol_id) REFERENCES symbols (id) ON DELETE CASCADE,
    CONSTRAINT fk_indicator_values_indicator FOREIGN KEY (indicator_id) REFERENCES indicators (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS strategies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL COMMENT 'matches StrategyInterface::code(), e.g. strategy_a',
    name VARCHAR(128) NOT NULL,
    description TEXT NULL,
    class_name VARCHAR(255) NULL COMMENT 'FQCN implementing App\\Strategy\\StrategyInterface, if code-based',
    is_active TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'inactive until real rules are supplied and reviewed',
    htf_timeframe VARCHAR(4) NULL,
    confirmation_timeframe VARCHAR(4) NULL,
    entry_timeframe VARCHAR(4) NULL,
    min_score TINYINT UNSIGNED NOT NULL DEFAULT 75,
    -- STRATEGY CONFIGURATION (spec #36): required confluences, per-factor
    -- score weights, support/resistance/OB/FVG/volume/indicator conditions.
    -- Structure is intentionally open (JSON) — ConfluenceEngine reads it,
    -- nothing in the engine hard-codes weights or thresholds.
    config JSON NOT NULL DEFAULT (JSON_OBJECT()),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_strategies_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS strategy_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    strategy_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(128) NOT NULL,
    setting_value TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_strategy_settings (strategy_id, setting_key),
    CONSTRAINT fk_strategy_settings_strategy FOREIGN KEY (strategy_id) REFERENCES strategies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which strategies are allowed to publish into a given channel (spec #16).
-- Empty for a channel = all active strategies.
CREATE TABLE IF NOT EXISTS channel_strategies (
    channel_id BIGINT UNSIGNED NOT NULL,
    strategy_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (channel_id, strategy_id),
    CONSTRAINT fk_channel_strategies_channel FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE,
    CONSTRAINT fk_channel_strategies_strategy FOREIGN KEY (strategy_id) REFERENCES strategies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
