-- Telegram channels the bot posts signals to, each with its own filtering
-- rules and its own signal template (spec #16/#17: Channel A > score 80,
-- Channel B everything, Channel C LONG-only, ...).

CREATE TABLE IF NOT EXISTS signal_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(128) NOT NULL,
    template_text TEXT NOT NULL COMMENT 'raw text with {placeholder} tokens, see SignalFormatter',
    entities JSON NOT NULL DEFAULT (JSON_ARRAY()) COMMENT 'MessageEntity[] anchored to template_text, offsets recomputed per render',
    parse_mode VARCHAR(16) NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    reply_to_message_id BIGINT NULL,
    quote_text TEXT NULL,
    quote_entities JSON NULL,
    quote_position INT UNSIGNED NULL,
    created_by_admin_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_signal_templates_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS channels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chat_id BIGINT NOT NULL COMMENT 'Telegram chat id, negative for channels/supergroups',
    username VARCHAR(64) NULL,
    title VARCHAR(255) NULL,
    type ENUM('channel', 'group', 'supergroup') NOT NULL DEFAULT 'channel',
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    language VARCHAR(8) NOT NULL DEFAULT 'fa',
    parse_mode VARCHAR(16) NOT NULL DEFAULT 'HTML',
    signal_template_id BIGINT UNSIGNED NULL COMMENT 'falls back to the is_default template when NULL',
    minimum_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    direction_filter ENUM('ANY', 'LONG', 'SHORT') NOT NULL DEFAULT 'ANY',
    bot_is_admin TINYINT(1) NOT NULL DEFAULT 0,
    bot_can_post TINYINT(1) NOT NULL DEFAULT 0,
    permissions_checked_at DATETIME NULL,
    added_by_admin_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_channels_chat_id (chat_id),
    KEY idx_channels_enabled (enabled),
    CONSTRAINT fk_channels_template FOREIGN KEY (signal_template_id) REFERENCES signal_templates (id) ON DELETE SET NULL,
    CONSTRAINT fk_channels_admin FOREIGN KEY (added_by_admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Free-form per-channel overrides beyond the typed columns above.
CREATE TABLE IF NOT EXISTS channel_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(128) NOT NULL,
    setting_value TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_channel_settings (channel_id, setting_key),
    CONSTRAINT fk_channel_settings_channel FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- channel_strategies (channel_id, strategy_id) is created in
-- 004_indicators_strategies.sql, once the `strategies` table it
-- references exists.
