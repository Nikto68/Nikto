<?php
declare(strict_types=1);

use EmojiBot\Worker;

// Web cron for hosts without CLI cron: call every minute with ?key=... (shown by setup).
require dirname(__DIR__) . '/bootstrap.php';

$app = emojibot();
if (!hash_equals($app->urlKey('cron'), (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('forbidden');
}
ignore_user_abort(true);
header('Content-Type: application/json');
echo json_encode((new Worker($app))->tick(50));
