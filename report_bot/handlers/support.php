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

    $name = trim($msg['from']['first_name'] ?? 'کاربر');
    $who = isset($msg['from']['username']) ? '@' . $msg['from']['username'] : "شناسه: $uid";
    $header = "📩 پیامِ پشتیبانیِ جدید\n👤 $name ($who)\n\nبرای پاسخ، روی همین پیام ریپلای کنید.";

    $db = reportDb();
    $anySent = false;
    foreach (reportAdminIds() as $aid) {
        $res = tgCopyMessage($aid, $chatId, $msg['message_id'], ['caption' => null]);
        if (empty($res['ok'])) continue;
        $anySent = true;
        tgSendMessage($aid, $header, [], ['reply_to_message_id' => $res['result']['message_id']]);

        $stmt = $db->prepare('INSERT OR REPLACE INTO support_threads (admin_chat_id, admin_message_id, user_id, created_at) VALUES (:a, :m, :u, :t)');
        $stmt->bindValue(':a', $aid, SQLITE3_INTEGER);
        $stmt->bindValue(':m', $res['result']['message_id'], SQLITE3_INTEGER);
        $stmt->bindValue(':u', $uid, SQLITE3_INTEGER);
        $stmt->bindValue(':t', time(), SQLITE3_INTEGER);
        $stmt->execute();
    }

    stateClear($uid);
    $resultText = $anySent
        ? '✅ پیام شما برای پشتیبانی ارسال شد.'
        : '⚠️ در حال حاضر ارسال به پشتیبانی ممکن نیست؛ بعداً دوباره تلاش کنید.';
    screenRender($anchorChat, $anchorMsg, $anchorPhoto, $resultText, [], kbStart(reportIsAdmin($uid)));
}

function handleAdminSupportReply(array $msg): bool {
    $aid = (int)$msg['from']['id'];
    if (!reportIsAdmin($aid)) return false;
    $reply = $msg['reply_to_message'] ?? null;
    if (!$reply) return false;

    $stmt = reportDb()->prepare('SELECT user_id FROM support_threads WHERE admin_chat_id = :a AND admin_message_id = :m');
    $stmt->bindValue(':a', $aid, SQLITE3_INTEGER);
    $stmt->bindValue(':m', $reply['message_id'], SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return false;

    $userId = (int)$row['user_id'];
    tgSendMessage($userId, '👨‍💻 پاسخ پشتیبانی:');
    tgCopyMessage($userId, (int)$msg['chat']['id'], $msg['message_id']);
    return true;
}
