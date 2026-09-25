<?php

require_once __DIR__ . '/../bootstrap.php';

$isCli = PHP_SAPI === 'cli';
$url = $isCli ? ($argv[1] ?? null) : ($_GET['url'] ?? null);

function fail(string $msg, bool $isCli): void {
    if ($isCli) { fwrite(STDERR, "$msg\n"); exit(1); }
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(400);
    exit($msg . "\n");
}

if (REPORT_WEBHOOK_SECRET === '') {
    fail('REPORT_WEBHOOK_SECRET در config.local.php تنظیم نشده — اول آن را بگذارید.', $isCli);
}

if (!$isCli) {
    $key = $_GET['key'] ?? '';
    if (!hash_equals(REPORT_WEBHOOK_SECRET, (string)$key)) {
        fail('دسترسی غیرمجاز — پارامترِ key باید برابرِ REPORT_WEBHOOK_SECRET باشد.', $isCli);
    }
}

if (!$url) {
    $usage = $isCli
        ? 'استفاده: php set_webhook.php <URL کاملِ webhook.php>'
        : 'استفاده: ...set_webhook.php?url=<URL کاملِ webhook.php>&key=<REPORT_WEBHOOK_SECRET>';
    fail($usage, $isCli);
}

$res = tgCall('setWebhook', [
    'url' => $url,
    'secret_token' => REPORT_WEBHOOK_SECRET,
    'allowed_updates' => ['message', 'callback_query'],
]);

if (!$isCli) header('Content-Type: application/json; charset=utf-8');
echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
