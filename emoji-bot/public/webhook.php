<?php
declare(strict_types=1);

use EmojiBot\Bot\Handler;

require dirname(__DIR__) . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}
$app = emojibot();
if (!hash_equals($app->webhookSecret(), (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? ''))) {
    http_response_code(403);
    exit;
}
$update = json_decode((string) file_get_contents('php://input', false, null, 0, 1048576), true);
http_response_code(200);
if (!is_array($update)) {
    exit;
}
try {
    (new Handler($app))->handle($update);
} catch (Throwable $e) {
    // Always answer 200 so Telegram does not retry the same update forever.
    $app->log('webhook', 'error', ['err' => $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()]);
}
