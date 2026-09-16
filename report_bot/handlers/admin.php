<?php
/** پنلِ مدیریت — کاملاً داخلِ خودِ ربات (با دستورِ /admin یا دکمه‌ی «پنل مدیریت») */

const EMOJI_SLOTS = ['btn_report', 'btn_support', 'btn_approve', 'btn_reject', 'start_prefix', 'admin_prefix'];

function requireAdminCq(array $cq): bool {
    if (!reportIsAdmin($cq['from']['id'])) {
        tgAnswerCallback($cq['id'], 'اجازه‌ی این کار را ندارید.', true);
        return false;
    }
    return true;
}

function handleAdminMenu(array $cq): void {
    if (!requireAdminCq($cq)) return;
    tgSendMessage((int)$cq['message']['chat']['id'], '⚙️ پنل مدیریت ربات گزارشات', [], ['reply_markup' => kbAdminMenu()]);
    tgAnswerCallback($cq['id']);
}

function handleAdminCommand(array $msg): void {
    $uid = (int)$msg['from']['id'];
    if (!reportIsAdmin($uid)) return;
    stateClear($uid);
    tgSendMessage((int)$msg['chat']['id'], '⚙️ پنل مدیریت ربات گزارشات', [], ['reply_markup' => kbAdminMenu()]);
}

// ---------------------------------------------------------------- تگ‌ها ---

function kbTagsList(): array {
    $rows = [];
    foreach (tagGetAll() as $t) {
        $rows[] = [
            ['text' => $t['text'], 'callback_data' => 'noop'],
            ['text' => '🗑 حذف', 'callback_data' => 'tagdel:' . $t['id']],
        ];
    }
    $rows[] = [['text' => '➕ افزودن تگ', 'callback_data' => 'tagadd']];
    $rows[] = [['text' => '🔙 بازگشت', 'callback_data' => 'adm:menu']];
    return ['inline_keyboard' => $rows];
}

function handleAdminTags(array $cq): void {
    if (!requireAdminCq($cq)) return;
    tgSendMessage((int)$cq['message']['chat']['id'],
        '🏷 تگ‌های فعلی — این‌ها زیرِ هر گزارش در گروه/تاپیک به‌صورتِ دکمه نشان داده می‌شوند و هنگامِ تایید، همراهِ پست به کانال می‌روند:',
        [], ['reply_markup' => kbTagsList()]);
    tgAnswerCallback($cq['id']);
}

function handleAdminTagAdd(array $cq): void {
    if (!requireAdminCq($cq)) return;
    stateSet((int)$cq['from']['id'], 'admin_await_tag_text');
    tgSendMessage((int)$cq['message']['chat']['id'],
        '✏️ متنِ تگِ جدید را بفرستید (ایموجیِ پریمیوم هم می‌توانید داخلش بگذارید).',
        [], ['reply_markup' => kbCancel()]);
    tgAnswerCallback($cq['id']);
}

function handleAdminTagText(array $msg): void {
    $uid = (int)$msg['from']['id'];
    $text = $msg['text'] ?? '';
    if (trim($text) === '') {
        tgSendMessage((int)$msg['chat']['id'], '⚠️ متن خالی است؛ دوباره بفرستید.', [], ['reply_markup' => kbCancel()]);
        return;
    }

    $stmt = reportDb()->prepare('INSERT INTO tags (text, entities, sort_order, created_at) VALUES (:t, :e, :o, :c)');
    $stmt->bindValue(':t', $text, SQLITE3_TEXT);
    $stmt->bindValue(':e', json_encode($msg['entities'] ?? [], JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
    $stmt->bindValue(':o', time(), SQLITE3_INTEGER);
    $stmt->bindValue(':c', time(), SQLITE3_INTEGER);
    $stmt->execute();

    stateClear($uid);
    tgSendMessage((int)$msg['chat']['id'], '✅ تگ اضافه شد.', [], ['reply_markup' => kbTagsList()]);
}

function handleAdminTagDelete(array $cq, int $tagId): void {
    if (!requireAdminCq($cq)) return;
    $stmt = reportDb()->prepare('DELETE FROM tags WHERE id = :id');
    $stmt->bindValue(':id', $tagId, SQLITE3_INTEGER);
    $stmt->execute();
    tgAnswerCallback($cq['id'], 'حذف شد.');
    tgEditReplyMarkup((int)$cq['message']['chat']['id'], $cq['message']['message_id'], kbTagsList());
}

// ----------------------------------------------------- ایموجیِ پریمیوم ---

function kbEmojiList(): array {
    $rows = [];
    foreach (EMOJI_SLOTS as $slot) {
        $cur = emojiGet($slot);
        $rows[] = [['text' => $cur['char'] . ' ' . emojiSlotLabel($slot), 'callback_data' => 'noop']];
        $rows[] = [
            ['text' => '✏️ تغییر', 'callback_data' => 'emoset:' . $slot],
            ['text' => '↩️ پیش‌فرض', 'callback_data' => 'emoclr:' . $slot],
        ];
    }
    $rows[] = [['text' => '🔙 بازگشت', 'callback_data' => 'adm:menu']];
    return ['inline_keyboard' => $rows];
}

function handleAdminEmoji(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $note = "⭐ ایموجی‌های پریمیوم\n\n" .
        "برای هرکدام «تغییر» را بزنید و یک پیامِ حاویِ همان ایموجیِ پریمیوم بفرستید (خودتان باید تلگرامِ پریمیوم داشته باشید تا بتوانید چنین پیامی بفرستید).\n\n" .
        "⚠️ توجه: تلگرام روی متنِ دکمه‌ها هیچ‌جور فرمت/گرافیکِ ویژه‌ای اجازه نمی‌دهد — روی دکمه‌ها همیشه همان کاراکترِ سادهٔ جایگزین دیده می‌شود؛ نسخهٔ متحرکِ پریمیوم فقط داخلِ متنِ پیام‌ها (مثلاً پیامِ خوش‌آمد) نمایش داده می‌شود.";
    tgSendMessage((int)$cq['message']['chat']['id'], $note, [], ['reply_markup' => kbEmojiList()]);
    tgAnswerCallback($cq['id']);
}

function handleAdminEmojiSet(array $cq, string $slot): void {
    if (!requireAdminCq($cq)) return;
    if (!in_array($slot, EMOJI_SLOTS, true)) { tgAnswerCallback($cq['id'], 'نامعتبر.', true); return; }
    stateSet((int)$cq['from']['id'], 'admin_await_emoji', ['slot' => $slot]);
    tgSendMessage((int)$cq['message']['chat']['id'], '⭐ یک پیامِ حاویِ ایموجیِ پریمیوم بفرستید:', [], ['reply_markup' => kbCancel()]);
    tgAnswerCallback($cq['id']);
}

function handleAdminEmojiClear(array $cq, string $slot): void {
    if (!requireAdminCq($cq)) return;
    emojiClear($slot);
    tgAnswerCallback($cq['id'], 'به پیش‌فرض برگشت.');
    tgEditReplyMarkup((int)$cq['message']['chat']['id'], $cq['message']['message_id'], kbEmojiList());
}

function handleAdminEmojiMessage(array $msg, string $slot): void {
    $uid = (int)$msg['from']['id'];
    if (!in_array($slot, EMOJI_SLOTS, true)) { stateClear($uid); return; }

    $found = extractFirstCustomEmoji($msg);
    if (!$found) {
        tgSendMessage((int)$msg['chat']['id'], '⚠️ ایموجیِ پریمیومی در پیام پیدا نشد؛ دوباره تلاش کنید یا انصراف بدهید.', [], ['reply_markup' => kbCancel()]);
        return;
    }

    emojiSet($slot, $found['custom_emoji_id'], $found['placeholder']);
    stateClear($uid);
    tgSendMessage((int)$msg['chat']['id'], '✅ ایموجی برای «' . emojiSlotLabel($slot) . '» ذخیره شد.', [], ['reply_markup' => kbEmojiList()]);
}

// --------------------------------------------------- عکسِ پیامِ خوش‌آمد ---

function handleAdminStartPhoto(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $cur = settingGet('start_photo_file_id');
    $rows = [];
    if ($cur) $rows[] = [['text' => '🗑 حذفِ عکسِ فعلی', 'callback_data' => 'startphotodel']];
    $rows[] = [['text' => '🔙 بازگشت', 'callback_data' => 'adm:menu']];

    stateSet((int)$cq['from']['id'], 'admin_await_start_photo');
    tgSendMessage((int)$cq['message']['chat']['id'],
        '🖼 یک عکس بفرستید تا زیرِ پیامِ خوش‌آمدگویی نمایش داده شود.' . ($cur ? "\n\nهم‌اکنون یک عکس تنظیم شده است." : ''),
        [], ['reply_markup' => ['inline_keyboard' => $rows]]);
    tgAnswerCallback($cq['id']);
}

function handleAdminStartPhotoMessage(array $msg): void {
    $uid = (int)$msg['from']['id'];
    if (empty($msg['photo'])) {
        tgSendMessage((int)$msg['chat']['id'], '⚠️ لطفاً یک عکس بفرستید.', [], ['reply_markup' => kbCancel()]);
        return;
    }
    $fileId = end($msg['photo'])['file_id'];
    settingSet('start_photo_file_id', $fileId);
    stateClear($uid);
    tgSendMessage((int)$msg['chat']['id'], '✅ عکسِ پیامِ خوش‌آمد ذخیره شد.', [], ['reply_markup' => kbAdminMenu()]);
}

function handleAdminStartPhotoDelete(array $cq): void {
    if (!requireAdminCq($cq)) return;
    settingDel('start_photo_file_id');
    tgAnswerCallback($cq['id'], 'حذف شد.');
    tgSendMessage((int)$cq['message']['chat']['id'], '✅ عکسِ پیامِ خوش‌آمد حذف شد.', [], ['reply_markup' => kbAdminMenu()]);
}

// ---------------------------------------------------- متنِ پیامِ خوش‌آمد ---

function handleAdminStartText(array $cq): void {
    if (!requireAdminCq($cq)) return;
    stateSet((int)$cq['from']['id'], 'admin_await_start_text');
    tgSendMessage((int)$cq['message']['chat']['id'],
        '✏️ متنِ جدیدِ پیامِ خوش‌آمد را بفرستید (فرمت‌دهی و ایموجیِ پریمیوم هم حفظ می‌شود).',
        [], ['reply_markup' => kbCancel()]);
    tgAnswerCallback($cq['id']);
}

function handleAdminStartTextMessage(array $msg): void {
    $uid = (int)$msg['from']['id'];
    $text = $msg['text'] ?? '';
    if (trim($text) === '') {
        tgSendMessage((int)$msg['chat']['id'], '⚠️ متن خالی است.', [], ['reply_markup' => kbCancel()]);
        return;
    }
    settingSet('start_text', $text);
    settingSet('start_text_entities', json_encode($msg['entities'] ?? [], JSON_UNESCAPED_UNICODE));
    stateClear($uid);
    tgSendMessage((int)$msg['chat']['id'], '✅ متنِ پیامِ خوش‌آمد ذخیره شد.', [], ['reply_markup' => kbAdminMenu()]);
}

// -------------------------------------------------- گروه/تاپیکِ گزارش ---

function handleAdminGroup(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $gid = settingGet('report_group_id');
    $tid = settingGet('report_topic_id');
    $status = $gid
        ? "\n\nهم‌اکنون تنظیم شده:\nگروه: <code>$gid</code>" . ($tid ? "\nتاپیک: <code>$tid</code>" : "\n(بدونِ تاپیکِ مشخص)")
        : '';

    tgSendMessage((int)$cq['message']['chat']['id'],
        "👥 برای تنظیمِ گروه/تاپیکِ بررسیِ گزارش‌ها:\n" .
        "۱) ربات را به گروهِ موردنظر اضافه و ادمین کنید.\n" .
        "۲) اگر گروه تاپیک (موضوع) دارد، داخلِ همان تاپیک — وگرنه در خودِ گروه — دستورِ زیر را بفرستید:\n" .
        "<code>/setreporttopic</code>" . $status,
        [], ['parse_mode' => 'HTML', 'reply_markup' => kbBackToAdmin()]);
    tgAnswerCallback($cq['id']);
}

function handleSetReportTopicCommand(array $msg): void {
    $uid = isset($msg['from']) ? (int)$msg['from']['id'] : 0;
    if (!reportIsAdmin($uid)) return;

    $chat = $msg['chat'];
    if (!in_array($chat['type'], ['group', 'supergroup'], true)) {
        tgSendMessage($chat['id'], '⚠️ این دستور را باید داخلِ گروه بفرستید.');
        return;
    }

    settingSet('report_group_id', $chat['id']);
    $opts = [];
    if (isset($msg['message_thread_id'])) {
        settingSet('report_topic_id', $msg['message_thread_id']);
        $opts['message_thread_id'] = $msg['message_thread_id'];
    } else {
        settingDel('report_topic_id');
    }
    tgSendMessage($chat['id'], '✅ این گروه/تاپیک به‌عنوانِ مقصدِ گزارش‌ها تنظیم شد.', [], $opts);
}

// -------------------------------------------------------- کانالِ مقصد ---

function handleAdminChannel(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $cid = settingGet('report_channel_id');
    $status = $cid ? "\n\nهم‌اکنون تنظیم شده: <code>$cid</code>" : '';

    stateSet((int)$cq['from']['id'], 'admin_await_channel');
    tgSendMessage((int)$cq['message']['chat']['id'],
        "📢 برای تنظیمِ کانالِ مقصد:\n" .
        "۱) ربات را در کانالِ موردنظر ادمین کنید (با اجازه‌ی ارسالِ پیام).\n" .
        "۲) یک پیام از همان کانال را همین‌جا فوروارد کنید — یا آیدیِ عددی/یوزرنیمِ کانال را بفرستید.$status",
        [], ['parse_mode' => 'HTML', 'reply_markup' => kbCancel()]);
    tgAnswerCallback($cq['id']);
}

function handleAdminChannelMessage(array $msg): void {
    $uid = (int)$msg['from']['id'];
    $chatId = (int)$msg['chat']['id'];

    $target = null;
    if (isset($msg['forward_from_chat']) && $msg['forward_from_chat']['type'] === 'channel') {
        $target = $msg['forward_from_chat']['id'];
    } elseif (!empty($msg['text'])) {
        $t = trim($msg['text']);
        $target = ctype_digit(ltrim($t, '-')) ? (int)$t : $t;
    }

    if (!$target) {
        tgSendMessage($chatId, '⚠️ پیامِ فورواردشده از کانال، یا آیدی/یوزرنیمِ کانال را بفرستید.', [], ['reply_markup' => kbCancel()]);
        return;
    }

    $chat = tgGetChat($target);
    if (empty($chat['ok']) || $chat['result']['type'] !== 'channel') {
        tgSendMessage($chatId, '⚠️ این کانال پیدا نشد یا ربات در آن ادمین نیست.', [], ['reply_markup' => kbCancel()]);
        return;
    }

    settingSet('report_channel_id', $chat['result']['id']);
    stateClear($uid);
    tgSendMessage($chatId, '✅ کانالِ مقصد تنظیم شد: ' . ($chat['result']['title'] ?? $chat['result']['id']), [], ['reply_markup' => kbAdminMenu()]);
}

// ------------------------------------------------------------- وضعیت ---

function handleAdminStatus(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $gid = settingGet('report_group_id', '—');
    $tid = settingGet('report_topic_id', '—');
    $cid = settingGet('report_channel_id', '—');
    $tags = count(tagGetAll());
    $emojiCount = 0;
    foreach (EMOJI_SLOTS as $s) if (emojiGet($s)['id']) $emojiCount++;
    $photo = settingGet('start_photo_file_id') ? 'دارد' : 'ندارد';

    $text = "ℹ️ وضعیتِ تنظیمات\n\n" .
        "گروهِ گزارش: $gid\n" .
        "تاپیکِ گزارش: $tid\n" .
        "کانالِ مقصد: $cid\n" .
        "تعدادِ تگ‌ها: $tags\n" .
        "ایموجیِ پریمیومِ تنظیم‌شده: $emojiCount از " . count(EMOJI_SLOTS) . "\n" .
        "عکسِ پیامِ خوش‌آمد: $photo";

    tgSendMessage((int)$cq['message']['chat']['id'], $text, [], ['reply_markup' => kbBackToAdmin()]);
    tgAnswerCallback($cq['id']);
}
