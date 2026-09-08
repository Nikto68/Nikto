#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * One-time (or re-run-after-URL-change) setup: registers public/webhook.php
 * with Telegram using TELEGRAM_WEBHOOK_SECRET from .env.
 *
 * Usage: php bin/set-webhook.php https://bot.example.com/webhook.php
 */

use App\Core\Application;
use App\Core\Config;
use App\Telegram\TelegramSender;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$url = $argv[1] ?? null;
if ($url === null || !filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://')) {
    fwrite(STDERR, "Usage: php bin/set-webhook.php https://your-domain.example.com/webhook.php\n");
    exit(1);
}

/** @var Application $app */
$app = require dirname(__DIR__) . '/bootstrap.php';

$secret = (string) $app->get(Config::class)->get('telegram.webhook_secret');
if ($secret === '') {
    fwrite(STDERR, "TELEGRAM_WEBHOOK_SECRET is not set in .env — set it before registering the webhook.\n");
    exit(1);
}

$sender = $app->get(TelegramSender::class);
$ok = $sender->setWebhook($url, $secret);

fwrite(STDOUT, $ok ? "Webhook registered: {$url}\n" : "Telegram rejected the webhook registration.\n");
exit($ok ? 0 : 1);
