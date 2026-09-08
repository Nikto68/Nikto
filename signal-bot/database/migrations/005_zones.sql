-- Price-action structures detected by the Strategy layer: S/R zones
-- (multi-method composite), order blocks, and fair value gaps.

CREATE TABLE IF NOT EXISTS zones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    symbol_id BIGINT UNSIGNED NOT NULL,
    timeframe VARCHAR(4) NOT NULL,
    type ENUM('support', 'resistance') NOT NULL,
    high DECIMAL(24, 12) NOT NULL,
    low DECIMAL(24, 12) NOT NULL,
    strength DECIMAL(6, 2) NOT NULL DEFAULT 0,
    touches INT UNSIGNED NOT NULL DEFAULT 0,
    volume DECIMAL(28, 8) NULL,
    methods JSON NOT NULL DEFAULT (JSON_ARRAY()) COMMENT 'which detectors contributed: swing_high_low, volume, rejection, clustering, htf_levels, liquidity, prev_high_low',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    invalidated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_zones_lookup (symbol_id, timeframe, is_active),
    CONSTRAINT fk_zones_symbol FOREIGN KEY (symbol_id) REFERENCES symbols (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_blocks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    symbol_id BIGINT UNSIGNED NOT NULL,
    timeframe VARCHAR(4) NOT NULL,
    type ENUM('bullish', 'bearish') NOT NULL,
    high DECIMAL(24, 12) NOT NULL,
    low DECIMAL(24, 12) NOT NULL,
    origin_candle_open_time BIGINT UNSIGNED NOT NULL,
    strength DECIMAL(6, 2) NOT NULL DEFAULT 0,
    volume DECIMAL(28, 8) NULL,
    mitigated TINYINT(1) NOT NULL DEFAULT 0,
    mitigated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_order_blocks_lookup (symbol_id, timeframe, mitigated),
    CONSTRAINT fk_order_blocks_symbol FOREIGN KEY (symbol_id) REFERENCES symbols (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fvgs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    symbol_id BIGINT UNSIGNED NOT NULL,
    timeframe VARCHAR(4) NOT NULL,
    type ENUM('bullish', 'bearish') NOT NULL,
    high DECIMAL(24, 12) NOT NULL,
    low DECIMAL(24, 12) NOT NULL,
    midpoint DECIMAL(24, 12) NOT NULL,
    size DECIMAL(24, 12) NOT NULL,
    origin_candle_open_time BIGINT UNSIGNED NOT NULL,
    filled TINYINT(1) NOT NULL DEFAULT 0,
    partially_filled TINYINT(1) NOT NULL DEFAULT 0,
    filled_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fvgs_lookup (symbol_id, timeframe, filled),
    CONSTRAINT fk_fvgs_symbol FOREIGN KEY (symbol_id) REFERENCES symbols (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
