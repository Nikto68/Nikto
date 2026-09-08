-- Exchanges, the dynamic symbol universe, live market snapshots, and candles.

CREATE TABLE IF NOT EXISTS exchanges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL COMMENT 'binance, mexc, wallex, ... — matches config/exchanges.php key',
    name VARCHAR(64) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('online', 'degraded', 'offline') NOT NULL DEFAULT 'online',
    last_error TEXT NULL,
    last_checked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_exchanges_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Free-form runtime overrides per exchange (rate limits, ws toggles, ...),
-- editable from the admin panel without redeploying config/exchanges.php.
CREATE TABLE IF NOT EXISTS exchange_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exchange_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(128) NOT NULL,
    setting_value TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_exchange_settings (exchange_id, setting_key),
    CONSTRAINT fk_exchange_settings_exchange FOREIGN KEY (exchange_id) REFERENCES exchanges (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The tradeable universe as reported by each exchange. Rebuilt/refreshed by
-- the scanner; NOT a fixed list of 200 rows — `is_in_universe` + `rank`
-- (in market_data) are what the scanner actually filters on.
CREATE TABLE IF NOT EXISTS symbols (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exchange_id BIGINT UNSIGNED NOT NULL,
    symbol VARCHAR(32) NOT NULL COMMENT 'exchange-native symbol, e.g. BTCUSDT',
    base_asset VARCHAR(16) NOT NULL,
    quote_asset VARCHAR(16) NOT NULL,
    market_type ENUM('spot', 'futures') NOT NULL DEFAULT 'spot',
    status ENUM('trading', 'break', 'delisted') NOT NULL DEFAULT 'trading',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    price_precision TINYINT UNSIGNED NULL,
    qty_precision TINYINT UNSIGNED NULL,
    min_notional DECIMAL(24, 8) NULL,
    last_seen_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_symbols_exchange_symbol (exchange_id, symbol),
    KEY idx_symbols_quote_active (quote_asset, is_active),
    CONSTRAINT fk_symbols_exchange FOREIGN KEY (exchange_id) REFERENCES exchanges (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Latest ticker/liquidity snapshot per symbol — one row per symbol, upserted
-- on every scan. This (not a history table) is what the universe filter
-- (volume/liquidity/spread ranking -> Top N) reads.
CREATE TABLE IF NOT EXISTS market_data (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    symbol_id BIGINT UNSIGNED NOT NULL,
    price DECIMAL(24, 12) NOT NULL DEFAULT 0,
    volume_24h DECIMAL(28, 8) NOT NULL DEFAULT 0,
    quote_volume_24h DECIMAL(28, 8) NOT NULL DEFAULT 0,
    price_change_percent_24h DECIMAL(10, 4) NOT NULL DEFAULT 0,
    high_24h DECIMAL(24, 12) NOT NULL DEFAULT 0,
    low_24h DECIMAL(24, 12) NOT NULL DEFAULT 0,
    spread_percent DECIMAL(10, 6) NOT NULL DEFAULT 0,
    trades_count_24h BIGINT UNSIGNED NULL,
    volatility DECIMAL(10, 6) NULL COMMENT 'e.g. ATR% of price, filled in by VolumeAnalyzer',
    liquidity_score DECIMAL(10, 4) NULL,
    volume_rank INT UNSIGNED NULL COMMENT 'rank within its exchange for the current scan',
    is_in_universe TINYINT(1) NOT NULL DEFAULT 0,
    captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_market_data_symbol (symbol_id),
    KEY idx_market_data_universe (is_in_universe, volume_rank),
    CONSTRAINT fk_market_data_symbol FOREIGN KEY (symbol_id) REFERENCES symbols (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OHLCV history per symbol/timeframe. open_time/close_time are epoch
-- milliseconds (matches every exchange kline API directly, no conversion).
CREATE TABLE IF NOT EXISTS candles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    symbol_id BIGINT UNSIGNED NOT NULL,
    timeframe VARCHAR(4) NOT NULL COMMENT '1m, 5m, 15m, 1h, 4h, 1d, ... not hard-coded elsewhere',
    open_time BIGINT UNSIGNED NOT NULL,
    close_time BIGINT UNSIGNED NOT NULL,
    open DECIMAL(24, 12) NOT NULL,
    high DECIMAL(24, 12) NOT NULL,
    low DECIMAL(24, 12) NOT NULL,
    close DECIMAL(24, 12) NOT NULL,
    volume DECIMAL(28, 8) NOT NULL,
    quote_volume DECIMAL(28, 8) NULL,
    trades_count INT UNSIGNED NULL,
    is_closed TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_candles_symbol_tf_time (symbol_id, timeframe, open_time),
    KEY idx_candles_lookup (symbol_id, timeframe, open_time DESC),
    CONSTRAINT fk_candles_symbol FOREIGN KEY (symbol_id) REFERENCES symbols (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
