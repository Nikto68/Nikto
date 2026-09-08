<?php

declare(strict_types=1);

use App\Support\Env;

return [
    'bot_token' => Env::string('TELEGRAM_BOT_TOKEN', ''),
    'webhook_secret' => Env::string('TELEGRAM_WEBHOOK_SECRET', ''),
    'api_base' => rtrim(Env::string('TELEGRAM_API_BASE', 'https://api.telegram.org') ?? '', '/'),

    // Numeric Telegram user IDs allowed into the admin panel. This is the
    // env-level bootstrap list (needed before the DB has any admins);
    // the `admins` table is authoritative once the schema is migrated.
    'admin_ids' => array_map('intval', Env::list('TELEGRAM_ADMIN_IDS')),
];
