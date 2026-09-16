<?php

function handleReportNew(array $cq): void {
    $uid = (int)$cq['from']['id'];
    $a = screenAnchorFromCq($cq);
    stateSet($uid, 'awaiting_report', ['prompt' => $a]);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '📎 عکس یا ویدیوی گزارش را ارسال کنید.', [], kbBack());
    tgAnswerCallback($cq['id']);
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

function handleReportMedia(array $msg, array $st = []): void {
    $uid = (int)$msg['from']['id'];
    $chatId = (int)$msg['chat']['id'];
    $prompt = $st['data']['prompt'] ?? null;
    $anchorChat = $prompt['chat_id'] ?? $chatId;
    $anchorMsg = $prompt['message_id'] ?? null;
    $anchorPhoto = $prompt['has_photo'] ?? false;

    $media = extractReportMedia($msg);
    if (!$media) {
        screenRender($anchorChat, $anchorMsg, $anchorPhoto, '⚠️ فقط عکس یا ویدیو ارسال کنید.', [], kbBack());
        return;
    }

    $groupId = settingGet('report_group_id');
    if (!$groupId) {
        screenRender($anchorChat, $anchorMsg, $anchorPhoto, '⛔️ ارسال گزارش موقتاً غیرفعال است.', [], kbStart(reportIsAdmin($uid)));
        reportAdminAlertOnce('no_report_group', '⚠️ گروه/تاپیکِ گزارش‌ها تنظیم نشده.');
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
        screenRender($anchorChat, $anchorMsg, $anchorPhoto, '⚠️ ثبتِ گزارش ناموفق بود؛ دوباره تلاش کنید.', [], kbStart(reportIsAdmin($uid)));
        reportAdminAlertOnce('group_copy_fail', '⚠️ ارسالِ گزارش به گروه ناموفق بود: ' . ($res['description'] ?? ''));
        stateClear($uid);
        return;
    }

    submissionUpdate($subId, [
        'group_chat_id' => (int)$groupId,
        'group_message_id' => (int)$res['result']['message_id'],
    ]);

    $anchor = screenRender($anchorChat, $anchorMsg, $anchorPhoto,
        '✅ گزارش شما ثبت شد؛ نتیجه همین‌جا اطلاع داده می‌شود.', [], kbStart(reportIsAdmin($uid)));

    submissionUpdate($subId, [
        'notify_chat_id' => $anchor['chat_id'],
        'notify_message_id' => $anchor['message_id'],
        'notify_has_photo' => $anchor['has_photo'] ? 1 : 0,
    ]);

    stateClear($uid);
}

function handleTagPick(array $cq, int $subId, int $tagId): void {
    $uid = (int)$cq['from']['id'];
    if (!reportIsAdmin($uid)) { tgAnswerCallback($cq['id'], 'اجازه‌ی این کار را ندارید.', true); return; }

    $sub = submissionGet($subId);
    if (!$sub) { tgAnswerCallback($cq['id'], 'یافت نشد.', true); return; }
    if ($sub['status'] !== 'pending') { tgAnswerCallback($cq['id'], 'قبلاً بررسی شده است.', true); return; }

    $newTag = ((int)$sub['tag_id'] === $tagId) ? null : $tagId;
    submissionUpdate($subId, ['tag_id' => $newTag]);
    $sub['tag_id'] = $newTag;

    $cap = buildGroupCaption($sub);
    tgEditCaption((int)$sub['group_chat_id'], (int)$sub['group_message_id'], $cap['text'], $cap['entities'], [
        'reply_markup' => kbReview($subId, tagGetAll(), $newTag, false),
    ]);
    tgAnswerCallback($cq['id'], $newTag ? 'برچسب ثبت شد.' : 'برچسب برداشته شد.');
}

function notifySubmitter(array $sub, string $text): void {
    $chatId = (int)($sub['notify_chat_id'] ?: $sub['src_chat_id']);
    $msgId = $sub['notify_message_id'] ? (int)$sub['notify_message_id'] : null;
    $hasPhoto = (bool)$sub['notify_has_photo'];
    $kb = kbStart(reportIsAdmin((int)$sub['user_id']));

    $anchor = screenRender($chatId, $msgId, $hasPhoto, $text, [], $kb);
    submissionUpdate((int)$sub['id'], [
        'notify_chat_id' => $anchor['chat_id'],
        'notify_message_id' => $anchor['message_id'],
        'notify_has_photo' => $anchor['has_photo'] ? 1 : 0,
    ]);
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
            tgAnswerCallback($cq['id'], 'کانالِ مقصد تنظیم نشده.', true);
            reportAdminAlertOnce('no_channel', '⚠️ کانالِ مقصد تنظیم نشده.');
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
        $sub = submissionGet($subId);
        notifySubmitter($sub, '✅ گزارش شما تایید و منتشر شد.');
        tgAnswerCallback($cq['id'], 'تایید شد ✅');
    } else {
        submissionUpdate($subId, ['status' => 'rejected', 'decided_by' => $uid, 'decided_at' => time()]);
        $sub = submissionGet($subId);
        notifySubmitter($sub, '❌ گزارش شما رد شد.');
        tgAnswerCallback($cq['id'], 'رد شد ❌');
    }

    $cap = buildGroupCaption($sub);
    tgEditCaption((int)$sub['group_chat_id'], (int)$sub['group_message_id'], $cap['text'], $cap['entities'], [
        'reply_markup' => kbReview($subId, tagGetAll(), $sub['tag_id'] ? (int)$sub['tag_id'] : null, true),
    ]);
}

function handleAdminReportReply(array $msg): bool {
    $aid = (int)$msg['from']['id'];
    if (!reportIsAdmin($aid)) return false;
    $reply = $msg['reply_to_message'] ?? null;
    if (!$reply) return false;

    $sub = submissionFindByGroupMessage((int)$msg['chat']['id'], (int)$reply['message_id']);
    if (!$sub) return false;

    tgSendMessage((int)$sub['src_chat_id'], '💬 پیامی درباره‌ی گزارش شما:');
    tgCopyMessage((int)$sub['src_chat_id'], (int)$msg['chat']['id'], $msg['message_id']);
    return true;
}
