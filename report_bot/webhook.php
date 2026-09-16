<?php
/**
 * Webhook مادرِ ربات گزارشات — این آدرس را با tools/set_webhook.php ثبت کنید.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/handlers/start.php';
require_once __DIR__ . '/handlers/report.php';
require_once __DIR__ . '/handlers/support.php';
require_once __DIR__ . '/handlers/admin.php';

/**
 * Fail-closed: بدونِ REPORT_WEBHOOK_SECRETِ تنظیم‌شده، یا بدونِ هدرِ درست،
 * هیچ آپدیتی پذیرفته نمی‌شود — همان الگویی که بقیه‌ی ربات‌های این مجموعه
 * استفاده می‌کنند، تا کسی نتواند با ساختنِ یک JSON دلخواه (uid برابرِ مدیر)
 * مستقیم به مسیرهای مدیریتی برسد.
 */
function reportWebhookSecretOk(): bool {
    if (REPORT_WEBHOOK_SECRET === '') {
        reportAdminAlertOnce('webhook_secret_missing',
            '🔴 REPORT_WEBHOOK_SECRET تنظیم نشده — تا وقتی تنظیم نشود هیچ آپدیتی از تلگرام پذیرفته نمی‌شود. ' .
            'در config.local.php مقدارش را بگذارید و یک‌بار tools/set_webhook.php را اجرا کنید.', 3600);
        return false;
    }
    $got = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    return is_string($got) && $got !== '' && hash_equals(REPORT_WEBHOOK_SECRET, $got);
}

function reportDispatch(array $update): void {
    if (isset($update['callback_query'])) { reportDispatchCallback($update['callback_query']); return; }
    if (isset($update['message']))        { reportDispatchMessage($update['message']); return; }
}

function reportDispatchCallback(array $cq): void {
    $data = $cq['data'] ?? '';

    static $exact = null;
    if ($exact === null) {
        $exact = [
            'noop'         => fn($cq) => tgAnswerCallback($cq['id']),
            'rep:new'      => 'handleReportNew',
            'rep:cancel'   => 'handleReportCancel',
            'sup:new'      => 'handleSupportNew',
            'adm:menu'     => 'handleAdminMenu',
            'adm:tags'     => 'handleAdminTags',
            'tagadd'       => 'handleAdminTagAdd',
            'adm:emoji'    => 'handleAdminEmoji',
            'adm:startphoto' => 'handleAdminStartPhoto',
            'startphotodel'  => 'handleAdminStartPhotoDelete',
            'adm:starttext'  => 'handleAdminStartText',
            'adm:group'      => 'handleAdminGroup',
            'adm:channel'    => 'handleAdminChannel',
            'adm:status'     => 'handleAdminStatus',
        ];
    }
    if (isset($exact[$data])) { $exact[$data]($cq); return; }

    if (preg_match('/^tagdel:(\d+)$/', $data, $m))          { handleAdminTagDelete($cq, (int)$m[1]); return; }
    if (preg_match('/^emoset:([a-z_]+)$/', $data, $m))      { handleAdminEmojiSet($cq, $m[1]); return; }
    if (preg_match('/^emoclr:([a-z_]+)$/', $data, $m))      { handleAdminEmojiClear($cq, $m[1]); return; }
    if (preg_match('/^tg:(\d+):(\d+)$/', $data, $m))        { handleTagPick($cq, (int)$m[1], (int)$m[2]); return; }
    if (preg_match('/^ap:(\d+)$/', $data, $m))              { handleDecision($cq, 'approve', (int)$m[1]); return; }
    if (preg_match('/^rj:(\d+)$/', $data, $m))              { handleDecision($cq, 'reject', (int)$m[1]); return; }

    tgAnswerCallback($cq['id']);
}

function reportDispatchMessage(array $msg): void {
    $chat = $msg['chat'];
    $text = $msg['text'] ?? '';

    // دستورهایی که باید داخلِ گروه/تاپیک هم کار کنند
    if ($chat['type'] !== 'private') {
        if (strncmp($text, '/setreporttopic', 15) === 0) handleSetReportTopicCommand($msg);
        return;
    }

    if (!isset($msg['from'])) return;
    $uid = (int)$msg['from']['id'];

    if ($text === '/start') { handleStartCommand($msg); return; }
    if ($text === '/admin' && reportIsAdmin($uid)) { handleAdminCommand($msg); return; }

    $st = stateGet($uid);
    if ($st['state']) {
        switch ($st['state']) {
            case 'awaiting_report':       handleReportMedia($msg); return;
            case 'awaiting_support':      handleSupportMessage($msg); return;
            case 'admin_await_tag_text':  handleAdminTagText($msg); return;
            case 'admin_await_emoji':     handleAdminEmojiMessage($msg, $st['data']['slot'] ?? ''); return;
            case 'admin_await_start_photo': handleAdminStartPhotoMessage($msg); return;
            case 'admin_await_start_text':  handleAdminStartTextMessage($msg); return;
            case 'admin_await_channel':     handleAdminChannelMessage($msg); return;
        }
    }

    // ریپلایِ مدیر روی یکی از کپی‌های پشتیبانی
    if (reportIsAdmin($uid) && handleAdminSupportReply($msg)) return;

    handleStartCommand($msg);
}

if (!reportWebhookSecretOk()) {
    http_response_code(403);
    exit('forbidden');
}

$raw = file_get_contents('php://input');
$update = json_decode($raw, true);

if (is_array($update)) {
    try {
        reportDispatch($update);
    } catch (Throwable $e) {
        reportLog('Exception: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
}

http_response_code(200);
echo 'ok';
