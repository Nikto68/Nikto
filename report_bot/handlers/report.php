<?php
/** جریانِ «ارسالِ گزارش»: دریافتِ عکس/ویدیو → گروه/تاپیک → تایید/رد → کانال */

function handleReportNew(array $cq): void {
    $uid = (int)$cq['from']['id'];
    stateSet($uid, 'awaiting_report');
    tgSendMessage((int)$cq['message']['chat']['id'],
        '📎 لطفاً عکس یا ویدیوی گزارش خود را ارسال کنید (می‌توانید برایش توضیح/کپشن هم بنویسید).',
        [], ['reply_markup' => kbCancel()]);
    tgAnswerCallback($cq['id']);
}

function handleReportCancel(array $cq): void {
    $uid = (int)$cq['from']['id'];
    $st = stateGet($uid);
    stateClear($uid);
    tgAnswerCallback($cq['id'], 'لغو شد.');

    $chatId = (int)$cq['message']['chat']['id'];
    if (reportIsAdmin($uid) && $st['state'] && strncmp($st['state'], 'admin_', 6) === 0) {
        tgSendMessage($chatId, '⚙️ پنل مدیریت ربات گزارشات', [], ['reply_markup' => kbAdminMenu()]);
    } else {
        handleStartCommand(['chat' => $cq['message']['chat'], 'from' => $cq['from']]);
    }
}

function extractReportMedia(array $msg): ?array {
    if (!empty($msg['photo']) && is_array($msg['photo'])) {
        $largest = end($msg['photo']);
        return ['type' => 'photo', 'file_id' => $largest['file_id']];
    }
    if (isset($msg['video'])) return ['type' => 'video', 'file_id' => $msg['video']['file_id']];
    if (isset($msg['animation'])) return ['type' => 'animation', 'file_id' => $msg['animation']['file_id']];
    if (isset($msg['document'])) {
        $mime = $msg['document']['mime_type'] ?? '';
        if (strncmp($mime, 'image/', 6) === 0 || strncmp($mime, 'video/', 6) === 0) {
            return ['type' => 'document', 'file_id' => $msg['document']['file_id']];
        }
    }
    return null;
}

function handleReportMedia(array $msg): void {
    $uid = (int)$msg['from']['id'];
    $chatId = (int)$msg['chat']['id'];
    $media = extractReportMedia($msg);

    if (!$media) {
        tgSendMessage($chatId, '⚠️ لطفاً فقط عکس یا ویدیو ارسال کنید.', [], ['reply_markup' => kbCancel()]);
        return;
    }

    $groupId = settingGet('report_group_id');
    if (!$groupId) {
        tgSendMessage($chatId, '⛔️ در حال حاضر ارسالِ گزارش غیرفعال است؛ لطفاً بعداً دوباره تلاش کنید.');
        reportAdminAlertOnce('no_report_group', '⚠️ گروه/تاپیکِ گزارش‌ها هنوز تنظیم نشده — از «پنل مدیریت ← گروه/تاپیک گزارش» تنظیم کنید.');
        stateClear($uid);
        return;
    }

    $caption = $msg['caption'] ?? '';
    $captionEntities = $msg['caption_entities'] ?? [];

    $db = reportDb();
    $stmt = $db->prepare('INSERT INTO submissions
        (user_id, username, first_name, src_chat_id, src_message_id, media_type, orig_caption, orig_caption_entities, status, created_at)
        VALUES (:uid, :un, :fn, :cid, :mid, :mt, :cap, :cape, "pending", :t)');
    $stmt->bindValue(':uid', $uid, SQLITE3_INTEGER);
    $stmt->bindValue(':un', $msg['from']['username'] ?? null, SQLITE3_TEXT);
    $stmt->bindValue(':fn', $msg['from']['first_name'] ?? null, SQLITE3_TEXT);
    $stmt->bindValue(':cid', $chatId, SQLITE3_INTEGER);
    $stmt->bindValue(':mid', $msg['message_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':mt', $media['type'], SQLITE3_TEXT);
    $stmt->bindValue(':cap', $caption, SQLITE3_TEXT);
    $stmt->bindValue(':cape', json_encode($captionEntities, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
    $stmt->bindValue(':t', time(), SQLITE3_INTEGER);
    $stmt->execute();
    $subId = (int)$db->lastInsertRowID();

    $sub = submissionGet($subId);
    $capBuilt = buildGroupCaption($sub);
    $kb = kbReview($subId, tagGetAll(), null, false);

    $topicId = settingGet('report_topic_id');
    $copyOpts = [
        'caption' => $capBuilt['text'] !== '' ? $capBuilt['text'] : null,
        'caption_entities' => $capBuilt['entities'] ?: null,
        'reply_markup' => $kb,
    ];
    if ($topicId) $copyOpts['message_thread_id'] = (int)$topicId;

    $res = tgCopyMessage((int)$groupId, $chatId, $msg['message_id'], $copyOpts);

    if (empty($res['ok'])) {
        tgSendMessage($chatId, '⚠️ در ثبتِ گزارش مشکلی پیش آمد؛ لطفاً دوباره تلاش کنید.');
        reportAdminAlertOnce('group_copy_fail', '⚠️ ارسالِ گزارش به گروه ناموفق بود: ' . ($res['description'] ?? ''));
        stateClear($uid);
        return;
    }

    submissionUpdate($subId, [
        'group_chat_id' => (int)$groupId,
        'group_message_id' => (int)$res['result']['message_id'],
    ]);

    stateClear($uid);
    tgSendMessage($chatId, '✅ گزارش شما دریافت شد و برای بررسی ارسال شد. نتیجه به همین چت اطلاع داده می‌شود.');
}

function handleTagPick(array $cq, int $subId, int $tagId): void {
    $uid = (int)$cq['from']['id'];
    if (!reportIsAdmin($uid)) { tgAnswerCallback($cq['id'], 'اجازه‌ی این کار را ندارید.', true); return; }

    $sub = submissionGet($subId);
    if (!$sub) { tgAnswerCallback($cq['id'], 'یافت نشد.', true); return; }
    if ($sub['status'] !== 'pending') { tgAnswerCallback($cq['id'], 'قبلاً بررسی شده است.', true); return; }

    $newTag = ((int)$sub['tag_id'] === $tagId) ? null : $tagId; // انتخاب دوباره = لغوِ تگ
    submissionUpdate($subId, ['tag_id' => $newTag]);
    $sub['tag_id'] = $newTag;

    $cap = buildGroupCaption($sub);
    tgEditCaption((int)$sub['group_chat_id'], (int)$sub['group_message_id'], $cap['text'], $cap['entities'], [
        'reply_markup' => kbReview($subId, tagGetAll(), $newTag, false),
    ]);
    tgAnswerCallback($cq['id'], $newTag ? 'برچسب ثبت شد.' : 'برچسب برداشته شد.');
}

function handleDecision(array $cq, string $action, int $subId): void {
    $uid = (int)$cq['from']['id'];
    if (!reportIsAdmin($uid)) { tgAnswerCallback($cq['id'], 'اجازه‌ی این کار را ندارید.', true); return; }

    $sub = submissionGet($subId);
    if (!$sub) { tgAnswerCallback($cq['id'], 'یافت نشد.', true); return; }
    if ($sub['status'] !== 'pending') { tgAnswerCallback($cq['id'], 'قبلاً بررسی شده است.', true); return; }

    if ($action === 'approve') {
        $channelId = settingGet('report_channel_id');
        if (!$channelId) {
            tgAnswerCallback($cq['id'], 'کانالِ مقصد تنظیم نشده — از پنلِ مدیریت تنظیم کنید.', true);
            reportAdminAlertOnce('no_channel', '⚠️ کانالِ مقصد هنوز تنظیم نشده — از «پنل مدیریت ← کانال مقصد» تنظیم کنید.');
            return;
        }

        $cap = buildChannelCaption($sub);
        $res = tgCopyMessage((int)$channelId, (int)$sub['src_chat_id'], (int)$sub['src_message_id'], [
            'caption' => $cap['text'] !== '' ? $cap['text'] : null,
            'caption_entities' => $cap['entities'] ?: null,
        ]);

        if (empty($res['ok'])) {
            tgAnswerCallback($cq['id'], 'ارسال به کانال ناموفق بود.', true);
            reportAdminAlertOnce('channel_copy_fail', '⚠️ ارسال به کانال ناموفق بود: ' . ($res['description'] ?? ''));
            return;
        }

        submissionUpdate($subId, [
            'status' => 'approved', 'decided_by' => $uid, 'decided_at' => time(),
            'channel_chat_id' => (int)$channelId, 'channel_message_id' => (int)$res['result']['message_id'],
        ]);
        $sub['status'] = 'approved';
        tgSendMessage((int)$sub['src_chat_id'], '✅ گزارش شما تایید و منتشر شد؛ با تشکر از همکاریِ شما.');
        tgAnswerCallback($cq['id'], 'تایید شد ✅');
    } else {
        submissionUpdate($subId, ['status' => 'rejected', 'decided_by' => $uid, 'decided_at' => time()]);
        $sub['status'] = 'rejected';
        tgSendMessage((int)$sub['src_chat_id'], '❌ گزارش شما بررسی و رد شد.');
        tgAnswerCallback($cq['id'], 'رد شد ❌');
    }

    $cap = buildGroupCaption($sub);
    tgEditCaption((int)$sub['group_chat_id'], (int)$sub['group_message_id'], $cap['text'], $cap['entities'], [
        'reply_markup' => kbReview($subId, tagGetAll(), $sub['tag_id'] ? (int)$sub['tag_id'] : null, true),
    ]);
}
