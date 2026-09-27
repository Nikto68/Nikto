<?php
declare(strict_types=1);

use EmojiBot\Bot\Handler;
use EmojiBot\TelegramError;
use EmojiBot\Worker;

// Long polling mode (no webhook / no HTTPS needed for the bot itself; the mini app still needs HTTPS).
// Usage: php bin/setup.php --polling && php bin/poll.php
require dirname(__DIR__) . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
$app = emojibot();
$app->tg()->call('deleteWebhook');
$handler = new Handler($app);
$worker = new Worker($app);
$offset = 0;
echo "Polling as @", $app->botUsername(), "...\n";
while (true) {
    try {
        $updates = $app->tg()->call('getUpdates', [
            'offset' => $offset,
            'timeout' => 25,
            'allowed_updates' => ['message', 'callback_query', 'pre_checkout_query', 'my_chat_member'],
        ], 40);
    } catch (TelegramError $e) {
        fwrite(STDERR, 'getUpdates: ' . $e->getMessage() . "\n");
        sleep(3);
        continue;
    }
    foreach ($updates as $u) {
        $offset = $u['update_id'] + 1;
        try {
            $handler->handle($u);
        } catch (Throwable $e) {
            fwrite(STDERR, 'update ' . $u['update_id'] . ': ' . $e->getMessage() . "\n");
        }
    }
    $worker->tick(6);
}
