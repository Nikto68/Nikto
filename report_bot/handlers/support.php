<?php
/** جریانِ «ارتباط با پشتیبانی»: پیامِ کاربر برای همه‌ی مدیرها کپی می‌شود؛
 *  مدیر با ریپلای‌کردن روی همان کپی، مستقیم به کاربر پاسخ می‌دهد. */

function handleSupportNew(array $cq): void {
    $uid = (int)$cq['from']['id'];
    stateSet($uid, 'awaiting_support');
    tgSendMessage((int)$cq['message']['chat']['id'],
        '💬 پیامِ خود را برای پشتیبانی بفرستید (متن، عکس، فایل یا هر چیزِ دیگر).',
        [], ['reply_markup' => kbCancel()]);
    tgAnswerCallback($cq['id']);
}

function handleSupportMessage(array $msg): void {
    $uid = (int)$msg['from']['id'];
    $chatId = (int)$msg['chat']['id'];
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
    if ($anySent) {
        tgSendMessage($chatId, '✅ پیامِ شما برای پشتیبانی ارسال شد؛ به‌زودی پاسخ داده می‌شود.');
    } else {
        tgSendMessage($chatId, '⚠️ در حال حاضر ارسالِ پیام به پشتیبانی ممکن نیست؛ بعداً دوباره تلاش کنید.');
    }
}

/** اگر پیام، ریپلایِ یک مدیر روی یکی از کپی‌های پشتیبانی باشد، به کاربر اصلی می‌رساند. */
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
