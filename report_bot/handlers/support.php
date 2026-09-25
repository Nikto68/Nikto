<?php

function handleSupportNew(array $cq): void {
    $uid = (int)$cq['from']['id'];
    $a = screenAnchorFromCq($cq);
    stateSet($uid, 'awaiting_support');
    $t = botText('support_prompt');
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], $t['text'], $t['entities'], kbBack());
    tgAnswerCallback($cq['id']);
}

function handleSupportMessage(array $msg, array $st = []): void {
    $uid = (int)$msg['from']['id'];
    $chatId = (int)$msg['chat']['id'];

    $groupId = settingGet('support_group_id');
    if (!$groupId) {
        $t = botText('support_disabled');
        tgSendMessage($chatId, $t['text'], $t['entities'], ['reply_markup' => kbStart(reportIsAdmin($uid))]);
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

    $hdr = tgSendMessage((int)$groupId, $header, [], $copyOpts);
    $res = tgCopyMessage((int)$groupId, $chatId, (int)$msg['message_id'], $copyOpts);

    stateClear($uid);
    if (empty($res['ok'])) {
        $t = botText('support_send_failed');
        tgSendMessage($chatId, $t['text'], $t['entities'], ['reply_markup' => kbStart(reportIsAdmin($uid))]);
        reportAdminAlertOnce('support_copy_fail', '⚠️ ارسالِ پیامِ پشتیبانی به گروه ناموفق بود: ' . ($res['description'] ?? ''));
        return;
    }

    supportThreadCreate((int)$groupId, (int)$res['result']['message_id'], $uid);
    if (!empty($hdr['ok'])) supportThreadCreate((int)$groupId, (int)$hdr['result']['message_id'], $uid);

    $t = botText('support_sent');
    tgSendMessage($chatId, $t['text'], $t['entities'], ['reply_markup' => kbStart(reportIsAdmin($uid))]);
}

function handleAdminSupportGroupReply(array $msg): bool {
    $aid = (int)$msg['from']['id'];
    if (!reportIsAdmin($aid)) return false;
    $reply = $msg['reply_to_message'] ?? null;
    if (!$reply) return false;

    $thread = supportThreadFindByGroupMessage((int)$msg['chat']['id'], (int)$reply['message_id']);
    if (!$thread) return false;

    $userId = (int)$thread['user_id'];
    $t = botText('support_reply_prefix');
    tgSendMessage($userId, $t['text'], $t['entities']);
    $res = tgCopyMessage($userId, (int)$msg['chat']['id'], (int)$msg['message_id']);
    if (empty($res['ok'])) tgSendMessage((int)$msg['chat']['id'], '⚠️ به کاربر نرسید.', [], replyOptsFor($msg));
    return true;
}
