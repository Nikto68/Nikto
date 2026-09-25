<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/handlers/start.php';
require_once __DIR__ . '/handlers/account.php';
require_once __DIR__ . '/handlers/guide.php';
require_once __DIR__ . '/handlers/report.php';
require_once __DIR__ . '/handlers/support.php';
require_once __DIR__ . '/handlers/admin.php';

function reportWebhookSecretOk(): bool {
    if (REPORT_WEBHOOK_SECRET === '') {
        reportAdminAlertOnce('webhook_secret_missing',
            '🔴 REPORT_WEBHOOK_SECRET تنظیم نشده — تا وقتی تنظیم نشود هیچ آپدیتی از تلگرام پذیرفته نمی‌شود.', 3600);
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
    if (isset($cq['from'])) userTouch($cq['from']);
    $data = $cq['data'] ?? '';

    static $exact = null;
    if ($exact === null) {
        $exact = [
            'noop'           => fn($cq) => tgAnswerCallback($cq['id']),
            'rep:new'        => 'handleReportNew',
            'rc:send'        => 'handleReportConfirmSend',
            'sup:new'        => 'handleSupportNew',
            'acc:view'       => 'handleAccountView',
            'gd:view'        => 'handleGuideView',
            'nav:back'       => 'handleNavBack',
            'adm:menu'       => 'handleAdminMenu',
            'adm:tags'       => 'handleAdminTags',
            'tagadd'         => 'handleAdminTagAdd',
            'adm:colors'     => 'handleAdminColors',
            'adm:btnlabels'  => 'handleAdminButtonLabels',
            'adm:texts'      => 'handleAdminTexts',
            'adm:startphoto' => 'handleAdminStartPhoto',
            'startphotodel'  => 'handleAdminStartPhotoDelete',
            'adm:guidephoto' => 'handleAdminGuidePhoto',
            'guidephotodel'  => 'handleAdminGuidePhotoDelete',
            'adm:starttext'  => 'handleAdminStartText',
            'adm:group'      => 'handleAdminGroup',
            'adm:supportgroup' => 'handleAdminSupportGroup',
            'adm:channel'    => 'handleAdminChannel',
            'adm:status'     => 'handleAdminStatus',
        ];
    }
    if (isset($exact[$data])) { $exact[$data]($cq); return; }

    if (preg_match('/^tagdel:(\d+)$/', $data, $m))       { handleAdminTagDelete($cq, (int)$m[1]); return; }
    if (preg_match('/^stset:([a-z_]+):([a-z]+)$/', $data, $m)) { handleAdminColorSet($cq, $m[1], $m[2]); return; }
    if (preg_match('/^txted:([a-z_]+)$/', $data, $m))    { handleAdminTextEdit($cq, $m[1]); return; }
    if (preg_match('/^txtclr:([a-z_]+)$/', $data, $m))   { handleAdminTextClear($cq, $m[1]); return; }
    if (preg_match('/^btled:([a-z_]+)$/', $data, $m))    { handleAdminButtonLabelEdit($cq, $m[1]); return; }
    if (preg_match('/^btlclr:([a-z_]+)$/', $data, $m))   { handleAdminButtonLabelClear($cq, $m[1]); return; }
    if (preg_match('/^tg:(\d+):(\d+)$/', $data, $m))     { handleTagPick($cq, (int)$m[1], (int)$m[2]); return; }
    if (preg_match('/^ap:(\d+)$/', $data, $m))           { handleDecision($cq, 'approve', (int)$m[1]); return; }
    if (preg_match('/^rj:(\d+)$/', $data, $m))           { handleDecision($cq, 'reject', (int)$m[1]); return; }
    if (preg_match('/^ge:(\d+)$/', $data, $m))           { handleGroupEditStart($cq, (int)$m[1]); return; }

    tgAnswerCallback($cq['id']);
}

function reportDispatchMessage(array $msg): void {
    if (isset($msg['from'])) userTouch($msg['from']);

    $chat = $msg['chat'];
    $text = $msg['text'] ?? '';

    if ($chat['type'] !== 'private') {
        if (strncmp($text, '/setreporttopic', 15) === 0) { handleSetReportTopicCommand($msg); return; }
        if (strncmp($text, '/setsupporttopic', 16) === 0) { handleSetSupportTopicCommand($msg); return; }
        if (isset($msg['from']) && reportIsAdmin($msg['from']['id'])) {
            $ast = stateGet((int)$msg['from']['id']);
            if ($ast['state'] === 'admin_await_group_edit' && handleGroupEditMessage($msg, $ast)) return;
            if (isset($msg['reply_to_message'])) {
                if (!handleAdminReportReply($msg)) handleAdminSupportGroupReply($msg);
            }
        }
        return;
    }

    if (!isset($msg['from'])) return;
    $uid = (int)$msg['from']['id'];

    if (preg_match('~^/start(@\w+)?(\s|$)~', $text)) { handleStartCommand($msg); return; }
    if (preg_match('~^/admin(@\w+)?$~', $text) && reportIsAdmin($uid)) { handleAdminCommand($msg); return; }

    $st = stateGet($uid);
    if ($st['state']) {
        switch ($st['state']) {
            case 'awaiting_report':         handleReportMedia($msg, $st); return;
            case 'report_confirm':          handleReportMedia($msg, $st); return;
            case 'awaiting_support':        handleSupportMessage($msg, $st); return;
            case 'admin_await_tag_text':    handleAdminTagText($msg, $st); return;
            case 'admin_await_start_photo': handleAdminStartPhotoMessage($msg, $st); return;
            case 'admin_await_start_text':  handleAdminStartTextMessage($msg, $st); return;
            case 'admin_await_guide_photo': handleAdminGuidePhotoMessage($msg, $st); return;
            case 'admin_await_channel':     handleAdminChannelMessage($msg, $st); return;
            case 'admin_await_text':        handleAdminTextEditMessage($msg, $st['data']['key'] ?? '', $st); return;
            case 'admin_await_btn_label':   handleAdminButtonLabelMessage($msg, $st['data']['slot'] ?? '', $st); return;
        }
    }

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
