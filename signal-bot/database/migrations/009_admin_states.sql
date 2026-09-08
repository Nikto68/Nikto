-- Lightweight conversation state (FSM) for multi-step admin flows (add
-- channel, edit a text_formats entry, edit a signal template, ...). Each
-- webhook request is a fresh PHP process, so this is what lets a bot
-- "remember" it is waiting for the next message from a given admin.

CREATE TABLE IF NOT EXISTS admin_states (
    telegram_user_id BIGINT NOT NULL PRIMARY KEY,
    state VARCHAR(64) NOT NULL,
    context JSON NOT NULL DEFAULT (JSON_OBJECT()),
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
