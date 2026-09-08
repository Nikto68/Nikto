<?php

declare(strict_types=1);

/**
 * Telegram webhook entrypoint. This is the ONLY thing driven by an HTTP
 * request in the whole system — the market scanner, indicator/strategy
 * pipeline and signal dispatch all run inside worker/market_worker.php as a
 * long-lived daemon, never here and never via cron.
 */

use App\Bot\Bot;
use App\Core\Application;
use App\Core\Config;

if (PHP_SAPI === 'cli') {
    fwrite(STDERR, "webhook.php must be served over HTTP, not run from the CLI.\n");
    exit(1);
}

/** @var Application $app */
$app = require dirname(__DIR__) . '/bootstrap.php';

/** @var Config $config */
$config = $app->get(Config::class);
$logger = $app->get(App\Logger\LoggerFactory::class)->channel('telegram');

$expectedSecret = (string) $config->get('telegram.webhook_secret');
$providedSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

if ($expectedSecret === '' || !hash_equals($expectedSecret, (string) $providedSecret)) {
    http_response_code(403);
    exit;
}

$body = file_get_contents('php://input');
if ($body === false || $body === '') {
    http_response_code(400);
    exit;
}

$update = json_decode($body, true);
if (!is_array($update)) {
    http_response_code(400);
    exit;
}

// Ack immediately with 200 so Telegram doesn't retry/queue this update
// again; failures inside handle() are caught and logged there, not here.
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

try {
    /** @var Bot $bot */
    $bot = $app->get(Bot::class);
    $bot->handle($update);
} catch (Throwable $e) {
    $logger->error('Webhook processing failed', ['exception' => $e->getMessage()]);
}
