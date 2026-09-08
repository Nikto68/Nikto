-- Core: admins, bot users, global settings, and the generic text/entity store.

CREATE TABLE IF NOT EXISTS admins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    telegram_user_id BIGINT NOT NULL,
    username VARCHAR(64) NULL,
    role ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin',
    added_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admins_telegram_user_id (telegram_user_id),
    CONSTRAINT fk_admins_added_by FOREIGN KEY (added_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    telegram_user_id BIGINT NOT NULL,
    username VARCHAR(64) NULL,
    first_name VARCHAR(128) NULL,
    language_code VARCHAR(16) NULL,
    is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_telegram_user_id (telegram_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Database-driven runtime configuration (scanner thresholds, mode, cooldowns, ...).
-- Seeded from .env defaults on first boot; editable afterwards from the admin panel.
-- value_type tells the SettingsManager how to cast setting_value back to PHP.
CREATE TABLE IF NOT EXISTS bot_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(128) NOT NULL,
    setting_value TEXT NULL,
    value_type ENUM('string', 'int', 'float', 'bool', 'json') NOT NULL DEFAULT 'string',
    updated_by_admin_id BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bot_settings_key (setting_key),
    CONSTRAINT fk_bot_settings_admin FOREIGN KEY (updated_by_admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every user-facing string in the bot (welcome text, signal wording, button
-- labels, error messages, ...) lives here instead of being hard-coded in
-- PHP. `entities` stores the raw Telegram MessageEntity[] JSON so premium
-- custom emoji / bold / spoilers survive round-tripping through the editor
-- (text is never reduced to Markdown, which would drop them).
CREATE TABLE IF NOT EXISTS text_formats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    text_key VARCHAR(128) NOT NULL,
    text_value TEXT NOT NULL,
    entities JSON NOT NULL,
    parse_mode VARCHAR(16) NULL,
    reply_to_message_id BIGINT NULL,
    quote_text TEXT NULL,
    quote_entities JSON NULL,
    quote_position INT UNSIGNED NULL,
    updated_by_admin_id BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_text_formats_key (text_key),
    CONSTRAINT fk_text_formats_admin FOREIGN KEY (updated_by_admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
