<?php

declare(strict_types=1);

use App\Support\Env;

return [
    'env' => Env::string('APP_ENV', 'production'),
    'timezone' => Env::string('APP_TIMEZONE', 'UTC'),

    // LIVE sends real signals to channels. DRY_RUN runs the full pipeline
    // (scanner, strategy, signal engine) but never dispatches to Telegram.
    // This is the env-level fallback; bot_settings.mode in the database
    // takes precedence once the app has booted once.
    'mode' => Env::string('APP_MODE', 'DRY_RUN'),

    'log_level' => Env::string('LOG_LEVEL', 'debug'),

    'storage_path' => dirname(__DIR__) . '/storage',

    // Fallback defaults for scanner/signal settings. These seed the
    // `bot_settings` table on first boot; after that, the database wins
    // and is editable from inside the admin panel.
    'defaults' => [
        'scan_symbol_limit' => Env::int('SCAN_SYMBOL_LIMIT', 200),
        'scan_interval_seconds' => Env::int('SCAN_INTERVAL', 60),
        'min_volume' => Env::float('MIN_VOLUME', 100000.0),
        'min_liquidity' => Env::float('MIN_LIQUIDITY', 50000.0),
        'min_signal_score' => Env::int('MIN_SIGNAL_SCORE', 75),
        'signal_cooldown_seconds' => Env::int('SIGNAL_COOLDOWN', 1800),
    ],
];
