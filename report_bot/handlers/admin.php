<?php

const EMOJI_SLOTS = ['btn_report', 'btn_support', 'btn_account', 'btn_back', 'btn_approve', 'btn_reject', 'start_prefix', 'admin_prefix'];

function requireAdminCq(array $cq): bool {
    if (!reportIsAdmin($cq['from']['id'])) {
        tgAnswerCallback($cq['id'], 'اجازه‌ی این کار را ندارید.', true);
        return false;
    }
    return true;
}

function handleAdminMenu(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $a = screenAnchorFromCq($cq);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '⚙️ پنل مدیریت ربات گزارشات', [], kbAdminMenu());
    tgAnswerCallback($cq['id']);
}

function handleAdminCommand(array $msg): void {
    $uid = (int)$msg['from']['id'];
    if (!reportIsAdmin($uid)) return;
    stateClear($uid);
    tgSendMessage((int)$msg['chat']['id'], '⚙️ پنل مدیریت ربات گزارشات', [], ['reply_markup' => kbAdminMenu()]);
}

// ---- تگ‌ها ----

function kbTagsList(): array {
    $rows = [];
    foreach (tagGetAll() as $t) {
        $rows[] = [
            ['text' => $t['text'], 'callback_data' => 'noop'],
            ['text' => '🗑 حذف', 'callback_data' => 'tagdel:' . $t['id']],
        ];
    }
    $rows[] = [['text' => '➕ افزودن تگ', 'callback_data' => 'tagadd']];
    $rows[] = [['text' => trim(emojiButtonChar('btn_back') . ' بازگشت'), 'callback_data' => 'adm:menu']];
    return ['inline_keyboard' => $rows];
}

function handleAdminTags(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $a = screenAnchorFromCq($cq);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '🏷 تگ‌ها', [], kbTagsList());
    tgAnswerCallback($cq['id']);
}

function handleAdminTagAdd(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $uid = (int)$cq['from']['id'];
    $a = screenAnchorFromCq($cq);
    stateSet($uid, 'admin_await_tag_text', ['prompt' => $a]);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '✏️ متن تگ جدید را بفرستید.', [], kbBack());
    tgAnswerCallback($cq['id']);
}

function handleAdminTagText(array $msg, array $st = []): void {
    $uid = (int)$msg['from']['id'];
    $prompt = $st['data']['prompt'] ?? null;
    $anchorChat = $prompt['chat_id'] ?? (int)$msg['chat']['id'];
    $anchorMsg = $prompt['message_id'] ?? null;
    $anchorPhoto = $prompt['has_photo'] ?? false;

    $text = $msg['text'] ?? '';
    if (trim($text) === '') {
        screenRender($anchorChat, $anchorMsg, $anchorPhoto, '⚠️ متن خالی است؛ دوباره بفرستید.', [], kbBack());
        return;
    }

    $stmt = reportDb()->prepare('INSERT INTO tags (text, entities, sort_order, created_at) VALUES (:t, :e, :o, :c)');
    $stmt->bindValue(':t', $text, SQLITE3_TEXT);
    $stmt->bindValue(':e', json_encode($msg['entities'] ?? [], JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
    $stmt->bindValue(':o', time(), SQLITE3_INTEGER);
    $stmt->bindValue(':c', time(), SQLITE3_INTEGER);
    $stmt->execute();

    stateClear($uid);
    screenRender($anchorChat, $anchorMsg, $anchorPhoto, '✅ تگ اضافه شد.', [], kbTagsList());
}

function handleAdminTagDelete(array $cq, int $tagId): void {
    if (!requireAdminCq($cq)) return;
    $stmt = reportDb()->prepare('DELETE FROM tags WHERE id = :id');
    $stmt->bindValue(':id', $tagId, SQLITE3_INTEGER);
    $stmt->execute();
    tgAnswerCallback($cq['id'], 'حذف شد.');
    tgEditReplyMarkup((int)$cq['message']['chat']['id'], $cq['message']['message_id'], kbTagsList());
}

// ---- ایموجی پریمیوم ----

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
    $rows[] = [['text' => trim(emojiButtonChar('btn_back') . ' بازگشت'), 'callback_data' => 'adm:menu']];
    return ['inline_keyboard' => $rows];
}

function handleAdminEmoji(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $a = screenAnchorFromCq($cq);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '⭐ ایموجی‌های پریمیوم', [], kbEmojiList());
    tgAnswerCallback($cq['id']);
}

function handleAdminEmojiSet(array $cq, string $slot): void {
    if (!requireAdminCq($cq)) return;
    if (!in_array($slot, EMOJI_SLOTS, true)) { tgAnswerCallback($cq['id'], 'نامعتبر.', true); return; }
    $uid = (int)$cq['from']['id'];
    $a = screenAnchorFromCq($cq);
    stateSet($uid, 'admin_await_emoji', ['slot' => $slot, 'prompt' => $a]);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '⭐ یک پیام حاوی ایموجی پریمیوم بفرستید:', [], kbBack());
    tgAnswerCallback($cq['id']);
}

function handleAdminEmojiClear(array $cq, string $slot): void {
    if (!requireAdminCq($cq)) return;
    emojiClear($slot);
    tgAnswerCallback($cq['id'], 'به پیش‌فرض برگشت.');
    tgEditReplyMarkup((int)$cq['message']['chat']['id'], $cq['message']['message_id'], kbEmojiList());
}

function handleAdminEmojiMessage(array $msg, string $slot, array $st = []): void {
    $uid = (int)$msg['from']['id'];
    $prompt = $st['data']['prompt'] ?? null;
    $anchorChat = $prompt['chat_id'] ?? (int)$msg['chat']['id'];
    $anchorMsg = $prompt['message_id'] ?? null;
    $anchorPhoto = $prompt['has_photo'] ?? false;

    if (!in_array($slot, EMOJI_SLOTS, true)) { stateClear($uid); return; }

    $found = extractFirstCustomEmoji($msg);
    if (!$found) {
        screenRender($anchorChat, $anchorMsg, $anchorPhoto, '⚠️ ایموجی پریمیومی پیدا نشد؛ دوباره تلاش کنید.', [], kbBack());
        return;
    }

    emojiSet($slot, $found['custom_emoji_id'], $found['placeholder']);
    stateClear($uid);
    screenRender($anchorChat, $anchorMsg, $anchorPhoto, '✅ ذخیره شد.', [], kbEmojiList());
}

// ---- عکس پیام خوش‌آمد ----

function handleAdminStartPhoto(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $uid = (int)$cq['from']['id'];
    $a = screenAnchorFromCq($cq);
    $cur = settingGet('start_photo_file_id');

    $rows = [];
    if ($cur) $rows[] = [['text' => '🗑 حذف عکس فعلی', 'callback_data' => 'startphotodel']];
    $rows[] = [['text' => trim(emojiButtonChar('btn_back') . ' بازگشت'), 'callback_data' => 'adm:menu']];

    stateSet($uid, 'admin_await_start_photo', ['prompt' => $a]);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '🖼 یک عکس بفرستید.', [], ['inline_keyboard' => $rows]);
    tgAnswerCallback($cq['id']);
}

function handleAdminStartPhotoMessage(array $msg, array $st = []): void {
    $uid = (int)$msg['from']['id'];
    $prompt = $st['data']['prompt'] ?? null;
    $anchorChat = $prompt['chat_id'] ?? (int)$msg['chat']['id'];
    $anchorMsg = $prompt['message_id'] ?? null;
    $anchorPhoto = $prompt['has_photo'] ?? false;

    if (empty($msg['photo'])) {
        screenRender($anchorChat, $anchorMsg, $anchorPhoto, '⚠️ لطفاً یک عکس بفرستید.', [], kbBack());
        return;
    }
    $fileId = end($msg['photo'])['file_id'];
    settingSet('start_photo_file_id', $fileId);
    stateClear($uid);
    screenRender($anchorChat, $anchorMsg, $anchorPhoto, '✅ ذخیره شد.', [], kbAdminMenu());
}

function handleAdminStartPhotoDelete(array $cq): void {
    if (!requireAdminCq($cq)) return;
    settingDel('start_photo_file_id');
    tgAnswerCallback($cq['id'], 'حذف شد.');
    $a = screenAnchorFromCq($cq);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '✅ عکس پیام خوش‌آمد حذف شد.', [], kbAdminMenu());
}

// ---- متن پیام خوش‌آمد ----

function handleAdminStartText(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $uid = (int)$cq['from']['id'];
    $a = screenAnchorFromCq($cq);
    stateSet($uid, 'admin_await_start_text', ['prompt' => $a]);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '✏️ متن جدید را بفرستید.', [], kbBack());
    tgAnswerCallback($cq['id']);
}

function handleAdminStartTextMessage(array $msg, array $st = []): void {
    $uid = (int)$msg['from']['id'];
    $prompt = $st['data']['prompt'] ?? null;
    $anchorChat = $prompt['chat_id'] ?? (int)$msg['chat']['id'];
    $anchorMsg = $prompt['message_id'] ?? null;
    $anchorPhoto = $prompt['has_photo'] ?? false;

    $text = $msg['text'] ?? '';
    if (trim($text) === '') {
        screenRender($anchorChat, $anchorMsg, $anchorPhoto, '⚠️ متن خالی است.', [], kbBack());
        return;
    }
    settingSet('start_text', $text);
    settingSet('start_text_entities', json_encode($msg['entities'] ?? [], JSON_UNESCAPED_UNICODE));
    stateClear($uid);
    screenRender($anchorChat, $anchorMsg, $anchorPhoto, '✅ ذخیره شد.', [], kbAdminMenu());
}

// ---- گروه/تاپیک گزارش ----

function handleAdminGroup(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $gid = settingGet('report_group_id');
    $tid = settingGet('report_topic_id');
    $status = $gid ? "\n\nگروه: $gid" . ($tid ? " · تاپیک: $tid" : '') : '';

    $a = screenAnchorFromCq($cq);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'],
        "👥 داخل گروه/تاپیکِ موردنظر دستور زیر را بفرستید:\n/setreporttopic$status",
        [], kbBackToAdmin());
    tgAnswerCallback($cq['id']);
}

function handleSetReportTopicCommand(array $msg): void {
    $uid = isset($msg['from']) ? (int)$msg['from']['id'] : 0;
    if (!reportIsAdmin($uid)) return;

    $chat = $msg['chat'];
    if (!in_array($chat['type'], ['group', 'supergroup'], true)) {
        tgSendMessage($chat['id'], '⚠️ این دستور را باید داخل گروه بفرستید.');
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
    tgSendMessage($chat['id'], '✅ این گروه/تاپیک به‌عنوان مقصد گزارش‌ها تنظیم شد.', [], $opts);
}

// ---- کانال مقصد ----

function handleAdminChannel(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $uid = (int)$cq['from']['id'];
    $cid = settingGet('report_channel_id');
    $status = $cid ? "\n\nهم‌اکنون: $cid" : '';

    $a = screenAnchorFromCq($cq);
    stateSet($uid, 'admin_await_channel', ['prompt' => $a]);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'],
        "📢 یک پیام از کانالِ مقصد فوروارد کنید، یا آیدی/یوزرنیمِ آن را بفرستید.$status",
        [], kbBack());
    tgAnswerCallback($cq['id']);
}

function handleAdminChannelMessage(array $msg, array $st = []): void {
    $uid = (int)$msg['from']['id'];
    $prompt = $st['data']['prompt'] ?? null;
    $anchorChat = $prompt['chat_id'] ?? (int)$msg['chat']['id'];
    $anchorMsg = $prompt['message_id'] ?? null;
    $anchorPhoto = $prompt['has_photo'] ?? false;

    $target = null;
    if (isset($msg['forward_from_chat']) && $msg['forward_from_chat']['type'] === 'channel') {
        $target = $msg['forward_from_chat']['id'];
    } elseif (!empty($msg['text'])) {
        $t = trim($msg['text']);
        $target = ctype_digit(ltrim($t, '-')) ? (int)$t : $t;
    }

    if (!$target) {
        screenRender($anchorChat, $anchorMsg, $anchorPhoto, '⚠️ پیام فورواردشده از کانال یا آیدی/یوزرنیم را بفرستید.', [], kbBack());
        return;
    }

    $chat = tgGetChat($target);
    if (empty($chat['ok']) || $chat['result']['type'] !== 'channel') {
        screenRender($anchorChat, $anchorMsg, $anchorPhoto, '⚠️ این کانال پیدا نشد یا ربات در آن ادمین نیست.', [], kbBack());
        return;
    }

    settingSet('report_channel_id', $chat['result']['id']);
    stateClear($uid);
    screenRender($anchorChat, $anchorMsg, $anchorPhoto, '✅ ذخیره شد: ' . ($chat['result']['title'] ?? $chat['result']['id']), [], kbAdminMenu());
}

// ---- وضعیت ----

function handleAdminStatus(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $gid = settingGet('report_group_id', '—');
    $tid = settingGet('report_topic_id', '—');
    $cid = settingGet('report_channel_id', '—');
    $tags = count(tagGetAll());
    $emojiCount = 0;
    foreach (EMOJI_SLOTS as $s) if (emojiGet($s)['id']) $emojiCount++;
    $photo = settingGet('start_photo_file_id') ? 'دارد' : 'ندارد';

    $text = "ℹ️ وضعیت\n\n" .
        "گروه: $gid · تاپیک: $tid\n" .
        "کانال: $cid\n" .
        "تگ‌ها: $tags\n" .
        "ایموجی پریمیوم: $emojiCount از " . count(EMOJI_SLOTS) . "\n" .
        "عکس خوش‌آمد: $photo";

    $a = screenAnchorFromCq($cq);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], $text, [], kbBackToAdmin());
    tgAnswerCallback($cq['id']);
}
