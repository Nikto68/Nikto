<?php
/**
 * ثبتِ webhook — یک‌بار، بعد از هر تغییرِ آدرس یا REPORT_WEBHOOK_SECRET.
 * اجرا: php tools/set_webhook.php https://yourdomain.com/report_bot/webhook.php
 */

require_once __DIR__ . '/../bootstrap.php';

$url = $argv[1] ?? null;
if (!$url) {
    fwrite(STDERR, "استفاده: php set_webhook.php <URL کاملِ webhook.php>\n");
    exit(1);
}

if (REPORT_WEBHOOK_SECRET === '') {
    fwrite(STDERR, "REPORT_WEBHOOK_SECRET در config.local.php تنظیم نشده — اول آن را بگذارید.\n");
    exit(1);
}

$res = tgCall('setWebhook', [
    'url' => $url,
    'secret_token' => REPORT_WEBHOOK_SECRET,
    'allowed_updates' => ['message', 'callback_query'],
]);

echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
