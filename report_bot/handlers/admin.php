<?php

const BUTTON_STYLE_SLOTS = ['btn_report', 'btn_support', 'btn_account', 'btn_guide', 'btn_approve', 'btn_reject', 'admin_prefix'];
const BUTTON_LABEL_SLOTS = ['btn_report', 'btn_support', 'btn_account', 'btn_guide', 'btn_back', 'btn_approve', 'btn_reject', 'admin_prefix'];
const BUTTON_LABEL_DEFAULTS = [
    'btn_report'   => 'ارسال گزارش',
    'btn_support'  => 'ارتباط با پشتیبانی',
    'btn_account'  => 'حساب کاربری',
    'btn_guide'    => 'راهنما',
    'btn_back'     => 'بازگشت',
    'btn_approve'  => 'تایید',
    'btn_reject'   => 'رد',
    'admin_prefix' => 'پنل مدیریت',
];

function requireAdminCq(array $cq): bool {
    if (!reportIsAdmin($cq['from']['id'])) {
        tgAnswerCallback($cq['id'], 'اجازه‌ی این کار را ندارید.', true);
        return false;
    }
    return true;
}

function handleAdminMenu(array $cq): void {
    if (!requireAdminCq($cq)) return;
    stateClear((int)$cq['from']['id']);
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

function kbTagsList(): array {
    $rows = [];
    foreach (tagGetAll() as $t) {
        $rows[] = [
            ['text' => $t['text'], 'callback_data' => 'noop'],
            ['text' => '🗑 حذف', 'callback_data' => 'tagdel:' . $t['id']],
        ];
    }
    $rows[] = [['text' => '➕ افزودن تگ', 'callback_data' => 'tagadd']];
    $rows[] = [emojiBtn('btn_back', 'بازگشت', 'adm:menu')];
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

function handleAdminStartPhoto(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $uid = (int)$cq['from']['id'];
    $a = screenAnchorFromCq($cq);
    $cur = settingGet('start_photo_file_id');

    $rows = [];
    if ($cur) $rows[] = [['text' => '🗑 حذف عکس فعلی', 'callback_data' => 'startphotodel']];
    $rows[] = [emojiBtn('btn_back', 'بازگشت', 'adm:menu')];

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
    stateClear((int)$cq['from']['id']);
    tgAnswerCallback($cq['id'], 'حذف شد.');
    $a = screenAnchorFromCq($cq);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '✅ عکس پیام خوش‌آمد حذف شد.', [], kbAdminMenu());
}

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

function handleAdminGuidePhoto(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $uid = (int)$cq['from']['id'];
    $a = screenAnchorFromCq($cq);
    $cur = settingGet('guide_photo_file_id');

    $rows = [];
    if ($cur) $rows[] = [['text' => '🗑 حذف عکس فعلی', 'callback_data' => 'guidephotodel']];
    $rows[] = [emojiBtn('btn_back', 'بازگشت', 'adm:menu')];

    stateSet($uid, 'admin_await_guide_photo', ['prompt' => $a]);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '📖 یک عکس بفرستید.', [], ['inline_keyboard' => $rows]);
    tgAnswerCallback($cq['id']);
}

function handleAdminGuidePhotoMessage(array $msg, array $st = []): void {
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
    settingSet('guide_photo_file_id', $fileId);
    stateClear($uid);
    screenRender($anchorChat, $anchorMsg, $anchorPhoto, '✅ ذخیره شد.', [], kbAdminMenu());
}

function handleAdminGuidePhotoDelete(array $cq): void {
    if (!requireAdminCq($cq)) return;
    settingDel('guide_photo_file_id');
    stateClear((int)$cq['from']['id']);
    tgAnswerCallback($cq['id'], 'حذف شد.');
    $a = screenAnchorFromCq($cq);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '✅ عکسِ راهنما حذف شد.', [], kbAdminMenu());
}

function kbTextsList(): array {
    $rows = [];
    $rows[] = [
        ['text' => '👋 پیام خوش‌آمد', 'callback_data' => 'noop'],
        ['text' => '✏️ تغییر', 'callback_data' => 'adm:starttext'],
    ];
    foreach (REPORT_TEXT_KEYS as $key) {
        $rows[] = [['text' => REPORT_TEXT_LABELS[$key] ?? $key, 'callback_data' => 'noop']];
        $rows[] = [
            ['text' => '✏️ تغییر', 'callback_data' => 'txted:' . $key],
            ['text' => '↩️ پیش‌فرض', 'callback_data' => 'txtclr:' . $key],
        ];
    }
    $rows[] = [emojiBtn('btn_back', 'بازگشت', 'adm:menu')];
    return ['inline_keyboard' => $rows];
}

function handleAdminTexts(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $a = screenAnchorFromCq($cq);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '✏️ متن‌های ربات', [], kbTextsList());
    tgAnswerCallback($cq['id']);
}

function handleAdminTextEdit(array $cq, string $key): void {
    if (!requireAdminCq($cq)) return;
    if (!in_array($key, REPORT_TEXT_KEYS, true)) { tgAnswerCallback($cq['id'], 'نامعتبر.', true); return; }
    $uid = (int)$cq['from']['id'];
    $a = screenAnchorFromCq($cq);
    stateSet($uid, 'admin_await_text', ['key' => $key, 'prompt' => $a]);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '✏️ متن جدید را بفرستید.', [], kbBack());
    tgAnswerCallback($cq['id']);
}

function handleAdminTextEditMessage(array $msg, string $key, array $st = []): void {
    $uid = (int)$msg['from']['id'];
    $prompt = $st['data']['prompt'] ?? null;
    $anchorChat = $prompt['chat_id'] ?? (int)$msg['chat']['id'];
    $anchorMsg = $prompt['message_id'] ?? null;
    $anchorPhoto = $prompt['has_photo'] ?? false;

    if (!in_array($key, REPORT_TEXT_KEYS, true)) { stateClear($uid); return; }

    $text = $msg['text'] ?? '';
    if (trim($text) === '') {
        screenRender($anchorChat, $anchorMsg, $anchorPhoto, '⚠️ متن خالی است.', [], kbBack());
        return;
    }
    textSet($key, $text, $msg['entities'] ?? []);
    stateClear($uid);
    screenRender($anchorChat, $anchorMsg, $anchorPhoto, '✅ ذخیره شد.', [], kbTextsList());
}

function handleAdminTextClear(array $cq, string $key): void {
    if (!requireAdminCq($cq)) return;
    if (!in_array($key, REPORT_TEXT_KEYS, true)) { tgAnswerCallback($cq['id'], 'نامعتبر.', true); return; }
    textClear($key);
    tgAnswerCallback($cq['id'], 'به پیش‌فرض برگشت.');
    $a = screenAnchorFromCq($cq);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '✏️ متن‌های ربات', [], kbTextsList());
}

function kbColorsList(): array {
    $rows = [];
    foreach (BUTTON_STYLE_SLOTS as $slot) {
        $cur = styleGet($slot);
        $curLabel = REPORT_STYLE_CHOICES[$cur ?? ''] ?? 'پیش‌فرضِ کد';
        $rows[] = [['text' => emojiSlotLabel($slot) . ' — ' . $curLabel, 'callback_data' => 'noop']];
        $row = [];
        foreach (REPORT_STYLE_CHOICES as $val => $label) {
            $row[] = ['text' => $label, 'callback_data' => 'stset:' . $slot . ':' . ($val === '' ? 'none' : $val)];
        }
        $rows[] = $row;
    }
    $rows[] = [emojiBtn('btn_back', 'بازگشت', 'adm:menu')];
    return ['inline_keyboard' => $rows];
}

function handleAdminColors(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $a = screenAnchorFromCq($cq);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '🎨 رنگ دکمه‌ها', [], kbColorsList());
    tgAnswerCallback($cq['id']);
}

function handleAdminColorSet(array $cq, string $slot, string $val): void {
    if (!requireAdminCq($cq)) return;
    $val = $val === 'none' ? '' : $val;
    if (!in_array($slot, BUTTON_STYLE_SLOTS, true) || !array_key_exists($val, REPORT_STYLE_CHOICES)) {
        tgAnswerCallback($cq['id'], 'نامعتبر.', true);
        return;
    }
    styleSet($slot, $val);
    tgAnswerCallback($cq['id'], 'ذخیره شد.');
    $a = screenAnchorFromCq($cq);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '🎨 رنگ دکمه‌ها', [], kbColorsList());
}

function kbButtonLabelsList(): array {
    $rows = [];
    foreach (BUTTON_LABEL_SLOTS as $slot) {
        $cur = buttonLabelGet($slot) ?? BUTTON_LABEL_DEFAULTS[$slot];
        $rows[] = [['text' => emojiSlotLabel($slot) . ' — ' . $cur, 'callback_data' => 'noop']];
        $rows[] = [
            ['text' => '✏️ تغییر', 'callback_data' => 'btled:' . $slot],
            ['text' => '↩️ پیش‌فرض', 'callback_data' => 'btlclr:' . $slot],
        ];
    }
    $rows[] = [emojiBtn('btn_back', 'بازگشت', 'adm:menu')];
    return ['inline_keyboard' => $rows];
}

function handleAdminButtonLabels(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $a = screenAnchorFromCq($cq);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '📝 متن دکمه‌ها', [], kbButtonLabelsList());
    tgAnswerCallback($cq['id']);
}

function handleAdminButtonLabelEdit(array $cq, string $slot): void {
    if (!requireAdminCq($cq)) return;
    if (!in_array($slot, BUTTON_LABEL_SLOTS, true)) { tgAnswerCallback($cq['id'], 'نامعتبر.', true); return; }
    $uid = (int)$cq['from']['id'];
    $a = screenAnchorFromCq($cq);
    stateSet($uid, 'admin_await_btn_label', ['slot' => $slot, 'prompt' => $a]);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '✏️ متنِ جدیدِ دکمه را بفرستید.', [], kbBack());
    tgAnswerCallback($cq['id']);
}

function handleAdminButtonLabelMessage(array $msg, string $slot, array $st = []): void {
    $uid = (int)$msg['from']['id'];
    $prompt = $st['data']['prompt'] ?? null;
    $anchorChat = $prompt['chat_id'] ?? (int)$msg['chat']['id'];
    $anchorMsg = $prompt['message_id'] ?? null;
    $anchorPhoto = $prompt['has_photo'] ?? false;

    if (!in_array($slot, BUTTON_LABEL_SLOTS, true)) { stateClear($uid); return; }

    $text = trim($msg['text'] ?? '');
    if ($text === '') {
        screenRender($anchorChat, $anchorMsg, $anchorPhoto, '⚠️ متن خالی است.', [], kbBack());
        return;
    }
    buttonLabelSet($slot, $text);
    stateClear($uid);
    screenRender($anchorChat, $anchorMsg, $anchorPhoto, '✅ ذخیره شد.', [], kbButtonLabelsList());
}

function handleAdminButtonLabelClear(array $cq, string $slot): void {
    if (!requireAdminCq($cq)) return;
    if (!in_array($slot, BUTTON_LABEL_SLOTS, true)) { tgAnswerCallback($cq['id'], 'نامعتبر.', true); return; }
    buttonLabelClear($slot);
    tgAnswerCallback($cq['id'], 'به پیش‌فرض برگشت.');
    $a = screenAnchorFromCq($cq);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '📝 متن دکمه‌ها', [], kbButtonLabelsList());
}

function handleAdminGroup(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $gid = settingGet('report_group_id');
    $tid = settingGet('report_topic_id');
    $status = $gid ? "\n\nگروه: $gid" . ($tid ? " · تاپیک: $tid" : '') : '';

    $a = screenAnchorFromCq($cq);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'],
        "👥 /setreporttopic$status",
        [], kbBackToAdmin());
    tgAnswerCallback($cq['id']);
}

function handleSetReportTopicCommand(array $msg): void {
    $uid = isset($msg['from']) ? (int)$msg['from']['id'] : 0;
    if (!reportIsAdmin($uid)) return;

    $chat = $msg['chat'];
    if (!in_array($chat['type'], ['group', 'supergroup'], true)) {
        tgSendMessage($chat['id'], '⚠️ فقط داخل گروه.');
        return;
    }

    settingSet('report_group_id', $chat['id']);
    $opts = [];
    $thread = msgThreadId($msg);
    if ($thread !== null) {
        settingSet('report_topic_id', $thread);
        $opts['message_thread_id'] = $thread;
    } else {
        settingDel('report_topic_id');
    }
    tgSendMessage($chat['id'], '✅ این گروه/تاپیک به‌عنوان مقصد گزارش‌ها تنظیم شد.', [], $opts);
}

function handleAdminSupportGroup(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $gid = settingGet('support_group_id');
    $tid = settingGet('support_topic_id');
    $status = $gid ? "\n\nگروه: $gid" . ($tid ? " · تاپیک: $tid" : '') : '';

    $a = screenAnchorFromCq($cq);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'],
        "📨 /setsupporttopic$status",
        [], kbBackToAdmin());
    tgAnswerCallback($cq['id']);
}

function handleSetSupportTopicCommand(array $msg): void {
    $uid = isset($msg['from']) ? (int)$msg['from']['id'] : 0;
    if (!reportIsAdmin($uid)) return;

    $chat = $msg['chat'];
    if (!in_array($chat['type'], ['group', 'supergroup'], true)) {
        tgSendMessage($chat['id'], '⚠️ فقط داخل گروه.');
        return;
    }

    settingSet('support_group_id', $chat['id']);
    $opts = [];
    $thread = msgThreadId($msg);
    if ($thread !== null) {
        settingSet('support_topic_id', $thread);
        $opts['message_thread_id'] = $thread;
    } else {
        settingDel('support_topic_id');
    }
    tgSendMessage($chat['id'], '✅ این گروه/تاپیک به‌عنوان مقصد پشتیبانی تنظیم شد.', [], $opts);
}

function handleAdminChannel(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $uid = (int)$cq['from']['id'];
    $cid = settingGet('report_channel_id');
    $status = $cid ? "\n\nهم‌اکنون: $cid" : '';

    $a = screenAnchorFromCq($cq);
    stateSet($uid, 'admin_await_channel', ['prompt' => $a]);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'],
        "📢 فوروارد از کانال یا آیدی/یوزرنیم:$status",
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
    $origin = $msg['forward_origin'] ?? null;
    if ($origin && ($origin['type'] ?? '') === 'channel' && isset($origin['chat']['id'])) {
        $target = $origin['chat']['id'];
    } elseif (!empty($msg['text'])) {
        $t = trim($msg['text']);
        $target = ctype_digit(ltrim($t, '-')) ? (int)$t : $t;
    }

    if (!$target) {
        screenRender($anchorChat, $anchorMsg, $anchorPhoto, '⚠️ فوروارد از کانال یا آیدی/یوزرنیم.', [], kbBack());
        return;
    }

    $chat = tgGetChat($target);
    if (empty($chat['ok']) || ($chat['result']['type'] ?? '') !== 'channel') {
        screenRender($anchorChat, $anchorMsg, $anchorPhoto, '⚠️ کانال پیدا نشد.', [], kbBack());
        return;
    }

    $member = tgGetChatMember($chat['result']['id'], reportBotId());
    $m = $member['result'] ?? [];
    if (($m['status'] ?? '') !== 'administrator' || empty($m['can_post_messages'])) {
        screenRender($anchorChat, $anchorMsg, $anchorPhoto, '⚠️ ربات در این کانال ادمین نیست یا اجازه‌ی ارسال پست ندارد.', [], kbBack());
        return;
    }

    settingSet('report_channel_id', $chat['result']['id']);
    stateClear($uid);
    screenRender($anchorChat, $anchorMsg, $anchorPhoto, '✅ ذخیره شد: ' . ($chat['result']['title'] ?? $chat['result']['id']), [], kbAdminMenu());
}

function handleAdminStatus(array $cq): void {
    if (!requireAdminCq($cq)) return;
    $gid = settingGet('report_group_id', '—');
    $tid = settingGet('report_topic_id', '—');
    $sgid = settingGet('support_group_id', '—');
    $stid = settingGet('support_topic_id', '—');
    $cid = settingGet('report_channel_id', '—');
    $tags = count(tagGetAll());
    $photo = settingGet('start_photo_file_id') ? 'دارد' : 'ندارد';

    $text = "ℹ️ وضعیت\n\n" .
        "گروه گزارش: $gid · تاپیک: $tid\n" .
        "گروه پشتیبانی: $sgid · تاپیک: $stid\n" .
        "کانال: $cid\n" .
        "تگ‌ها: $tags\n" .
        "عکس خوش‌آمد: $photo";

    $a = screenAnchorFromCq($cq);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], $text, [], kbBackToAdmin());
    tgAnswerCallback($cq['id']);
}
