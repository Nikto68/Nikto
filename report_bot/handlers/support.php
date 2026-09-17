<?php

function handleSupportNew(array $cq): void {
    $uid = (int)$cq['from']['id'];
    $a = screenAnchorFromCq($cq);
    stateSet($uid, 'awaiting_support', ['prompt' => $a]);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '💬 پیام خود را برای پشتیبانی بفرستید.', [], kbBack());
    tgAnswerCallback($cq['id']);
}

function handleSupportMessage(array $msg, array $st = []): void {
    $uid = (int)$msg['from']['id'];
    $chatId = (int)$msg['chat']['id'];
    $prompt = $st['data']['prompt'] ?? null;
    $anchorChat = $prompt['chat_id'] ?? $chatId;
    $anchorMsg = $prompt['message_id'] ?? null;
    $anchorPhoto = $prompt['has_photo'] ?? false;

    $groupId = settingGet('support_group_id');
    if (!$groupId) {
        screenRender($anchorChat, $anchorMsg, $anchorPhoto, '⛔️ پشتیبانی موقتاً در دسترس نیست.', [], kbStart(reportIsAdmin($uid)));
        reportAdminAlertOnce('no_support_group', '⚠️ گروه/تاپیکِ پشتیبانی تنظیم نشده.');
        stateClear($uid);
        return;
    }

    $name = trim($msg['from']['first_name'] ?? 'کاربر');
    $who = isset($msg['from']['username']) ? '@' . $msg['from']['username'] : "شناسه: $uid";
    $header = "📩 پیامِ پشتیبانیِ جدید\n👤 $name ($who)";

    $topicId = settingGet('support_topic_id');
    $copyOpts = [];
    if ($topicId) $copyOpts['message_thread_id'] = (int)$topicId;

    tgSendMessage((int)$groupId, $header, [], $copyOpts);
    $res = tgCopyMessage((int)$groupId, $chatId, $msg['message_id'], $copyOpts);

    stateClear($uid);
    if (empty($res['ok'])) {
        screenRender($anchorChat, $anchorMsg, $anchorPhoto, '⚠️ ارسالِ پیام ناموفق بود؛ دوباره تلاش کنید.', [], kbStart(reportIsAdmin($uid)));
        reportAdminAlertOnce('support_copy_fail', '⚠️ ارسالِ پیامِ پشتیبانی به گروه ناموفق بود: ' . ($res['description'] ?? ''));
        return;
    }

    supportThreadCreate((int)$groupId, (int)$res['result']['message_id'], $uid);
    screenRender($anchorChat, $anchorMsg, $anchorPhoto, '✅ پیام شما برای پشتیبانی ارسال شد.', [], kbStart(reportIsAdmin($uid)));
}

function handleAdminSupportGroupReply(array $msg): bool {
    $aid = (int)$msg['from']['id'];
    if (!reportIsAdmin($aid)) return false;
    $reply = $msg['reply_to_message'] ?? null;
    if (!$reply) return false;

    $thread = supportThreadFindByGroupMessage((int)$msg['chat']['id'], (int)$reply['message_id']);
    if (!$thread) return false;

    $userId = (int)$thread['user_id'];
    tgSendMessage($userId, '👨‍💻 پاسخ پشتیبانی:');
    tgCopyMessage($userId, (int)$msg['chat']['id'], $msg['message_id']);
    return true;
}
