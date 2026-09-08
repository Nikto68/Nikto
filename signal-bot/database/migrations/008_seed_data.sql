-- Idempotent seed data: exchange rows, default runtime settings mirroring
-- the .env defaults, default bot texts, and a default signal template.
-- Admins are NOT seeded here (that depends on the deployment's
-- TELEGRAM_ADMIN_IDS and is synced at boot by AdminRepository::syncFromConfig()).

INSERT IGNORE INTO exchanges (code, name, enabled) VALUES
    ('binance', 'Binance', 1),
    ('mexc', 'MEXC', 1),
    ('wallex', 'Wallex', 1);

INSERT IGNORE INTO bot_settings (setting_key, setting_value, value_type) VALUES
    ('mode', 'DRY_RUN', 'string'),
    ('scan_symbol_limit', '200', 'int'),
    ('scan_interval_seconds', '60', 'int'),
    ('min_volume', '100000', 'float'),
    ('min_liquidity', '50000', 'float'),
    ('min_signal_score', '75', 'int'),
    ('signal_cooldown_seconds', '1800', 'int'),
    ('universe_quote_assets', '["USDT","USDC"]', 'json'),
    ('universe_selection', 'top_volume', 'string');

INSERT IGNORE INTO text_formats (text_key, text_value, entities) VALUES
    ('welcome', N'به ربات سیگنال خوش آمدید.', JSON_ARRAY()),
    ('error_permission', N'شما اجازه دسترسی به این بخش را ندارید.', JSON_ARRAY()),
    ('channel_added', N'کانال با موفقیت اضافه شد.', JSON_ARRAY()),
    ('channel_removed', N'کانال حذف شد.', JSON_ARRAY()),
    ('scanner_status', N'وضعیت اسکنر بازار.', JSON_ARRAY()),
    ('signal_long', N'سیگنال خرید (LONG) جدید.', JSON_ARRAY()),
    ('signal_short', N'سیگنال فروش (SHORT) جدید.', JSON_ARRAY());

INSERT IGNORE INTO signal_templates (id, name, template_text, entities, parse_mode, is_default) VALUES
    (1, 'Default', CONCAT(
        '{direction} {symbol}\n',
        '\n',
        'Exchange: {exchange}\n',
        'Entry: {entry}\n',
        'Stop Loss: {stop_loss}\n',
        '\n',
        'TP1: {tp1}\n',
        'TP2: {tp2}\n',
        'TP3: {tp3}\n',
        '\n',
        'Risk/Reward: {risk_reward}\n',
        'Score: {score}/100\n',
        'Timeframe: {timeframe}\n',
        '\n',
        '{reasons}'
    ), JSON_ARRAY(), 'HTML', 1);
