<?php

function handleReportNew(array $cq): void {
    $uid = (int)$cq['from']['id'];
    $a = screenAnchorFromCq($cq);
    stateSet($uid, 'awaiting_report');
    $t = botText('report_prompt');
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], $t['text'], $t['entities'], kbBack());
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
    if (!empty($msg['text'])) return ['type' => 'text', 'file_id' => null];
    return null;
}

function editGroupPost(array $sub, string $text, array $entities, array $opts = []) {
    if ($sub['media_type'] === 'text') {
        return tgEditMessageText((int)$sub['group_chat_id'], (int)$sub['group_message_id'], $text, $entities, $opts);
    }
    return tgEditCaption((int)$sub['group_chat_id'], (int)$sub['group_message_id'], $text, $entities, $opts);
}

function postSubmissionContent(array $sub, int $toChatId, array $capBuilt, array $extraOpts = [], bool $fromGroup = false) {
    if ($sub['media_type'] === 'text') {
        return tgSendMessage($toChatId, $capBuilt['text'] !== '' ? $capBuilt['text'] : '.', $capBuilt['entities'], $extraOpts);
    }
    $fromChat = $fromGroup ? (int)$sub['group_chat_id'] : (int)$sub['src_chat_id'];
    $fromMsg = $fromGroup ? (int)$sub['group_message_id'] : (int)$sub['src_message_id'];
    return tgCopyMessage($toChatId, $fromChat, $fromMsg, array_merge([
        'caption' => $capBuilt['text'] !== '' ? $capBuilt['text'] : null,
        'caption_entities' => $capBuilt['entities'] ?: null,
    ], $extraOpts));
}

function handleReportMedia(array $msg, array $st = []): void {
    $uid = (int)$msg['from']['id'];
    $chatId = (int)$msg['chat']['id'];

    $media = extractReportMedia($msg);
    if (!$media) {
        $t = botText('report_invalid');
        tgSendMessage($chatId, $t['text'], $t['entities'], ['reply_markup' => kbBack()]);
        return;
    }

    $album = $msg['media_group_id'] ?? null;
    if ($album !== null && ($st['state'] ?? null) === 'report_confirm' && ($st['data']['msg']['media_group_id'] ?? null) === $album) {
        return;
    }

    $t = botText('report_confirm_prompt');
    tgSendMessage($chatId, $t['text'], $t['entities'], ['reply_markup' => kbReportConfirm()]);
    stateSet($uid, 'report_confirm', ['msg' => $msg]);
}

function handleReportConfirmSend(array $cq): void {
    $uid = (int)$cq['from']['id'];
    $st = stateGet($uid);
    if ($st['state'] !== 'report_confirm' || empty($st['data']['msg']) || !stateTake($uid, 'report_confirm')) {
        tgAnswerCallback($cq['id'], 'این درخواست منقضی شده؛ دوباره تلاش کنید.', true);
        return;
    }
    $msg = $st['data']['msg'];
    $a = screenAnchorFromCq($cq);

    tgAnswerCallback($cq['id']);
    finalizeReportSubmission($msg, $uid, $a['chat_id'], $a['message_id'], $a['has_photo']);
}

function finalizeReportSubmission(array $msg, int $uid, int $anchorChat, ?int $anchorMsg, bool $anchorPhoto): void {
    $chatId = (int)$msg['chat']['id'];
    $media = extractReportMedia($msg);

    $groupId = settingGet('report_group_id');
    if (!$groupId) {
        $t = botText('report_disabled');
        screenRender($anchorChat, $anchorMsg, $anchorPhoto, $t['text'], $t['entities'], kbStart(reportIsAdmin($uid)));
        reportAdminAlertOnce('no_report_group', '⚠️ گروه/تاپیکِ گزارش‌ها تنظیم نشده.');
        stateClear($uid);
        return;
    }

    $caption = $msg['caption'] ?? ($msg['text'] ?? '');
    $captionEntities = $msg['caption_entities'] ?? ($msg['entities'] ?? []);

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
    $postOpts = ['reply_markup' => $kb];
    if ($topicId) $postOpts['message_thread_id'] = (int)$topicId;

    $res = postSubmissionContent($sub, (int)$groupId, $capBuilt, $postOpts);

    if (empty($res['ok'])) {
        $t = botText('report_submit_failed');
        screenRender($anchorChat, $anchorMsg, $anchorPhoto, $t['text'], $t['entities'], kbStart(reportIsAdmin($uid)));
        reportAdminAlertOnce('group_copy_fail', '⚠️ ارسالِ گزارش به گروه ناموفق بود: ' . ($res['description'] ?? ''));
        stateClear($uid);
        return;
    }

    submissionUpdate($subId, [
        'group_chat_id' => (int)$groupId,
        'group_message_id' => (int)$res['result']['message_id'],
    ]);

    $t = botText('report_submitted');
    $anchor = screenRender($anchorChat, $anchorMsg, $anchorPhoto, $t['text'], $t['entities'], kbStart(reportIsAdmin($uid)));

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
    if ($newTag !== null && !tagGet($newTag)) {
        tgEditReplyMarkup((int)$sub['group_chat_id'], (int)$sub['group_message_id'],
            kbReview($subId, tagGetAll(), $sub['tag_id'] ? (int)$sub['tag_id'] : null, false));
        tgAnswerCallback($cq['id'], 'این تگ حذف شده.', true);
        return;
    }

    submissionUpdate($subId, ['tag_id' => $newTag]);
    $sub['tag_id'] = $newTag;

    $cap = buildGroupCaption($sub);
    editGroupPost($sub, $cap['text'], $cap['entities'], [
        'reply_markup' => kbReview($subId, tagGetAll(), $newTag, false),
    ]);
    tgAnswerCallback($cq['id'], $newTag ? 'برچسب ثبت شد.' : 'برچسب برداشته شد.');
}

function notifySubmitter(array $sub, array $textPart): void {
    $chatId = (int)$sub['src_chat_id'];
    $kb = kbStart(reportIsAdmin((int)$sub['user_id']));
    tgSendMessage($chatId, $textPart['text'], $textPart['entities'], ['reply_markup' => $kb]);
}

function handleDecision(array $cq, string $action, int $subId): void {
    $uid = (int)$cq['from']['id'];
    if (!reportIsAdmin($uid)) { tgAnswerCallback($cq['id'], 'اجازه‌ی این کار را ندارید.', true); return; }

    $sub = submissionGet($subId);
    if (!$sub) { tgAnswerCallback($cq['id'], 'یافت نشد.', true); return; }
    if (!in_array($sub['status'], ['pending', 'processing'], true)) { tgAnswerCallback($cq['id'], 'قبلاً بررسی شده است.', true); return; }

    $channelId = null;
    if ($action === 'approve') {
        $channelId = settingGet('report_channel_id');
        if (!$channelId) {
            tgAnswerCallback($cq['id'], 'کانالِ مقصد تنظیم نشده.', true);
            reportAdminAlertOnce('no_channel', '⚠️ کانالِ مقصد تنظیم نشده.');
            return;
        }
    }

    if (!submissionClaim($subId)) { tgAnswerCallback($cq['id'], 'قبلاً بررسی شده است.', true); return; }

    if ($action === 'approve') {
        $cap = buildChannelCaption($sub);
        $res = postSubmissionContent($sub, (int)$channelId, $cap);
        if (empty($res['ok']) && $sub['media_type'] !== 'text' && $cap['text'] !== '' && !empty($sub['group_message_id'])) {
            $res = postSubmissionContent($sub, (int)$channelId, $cap, [], true);
        }

        if (empty($res['ok'])) {
            submissionUpdate($subId, ['status' => 'pending', 'decided_at' => null]);
            tgAnswerCallback($cq['id'], 'ارسال به کانال ناموفق بود.', true);
            reportAdminAlertOnce('channel_copy_fail', '⚠️ ارسال به کانال ناموفق بود: ' . ($res['description'] ?? ''));
            return;
        }

        submissionUpdate($subId, [
            'status' => 'approved', 'decided_by' => $uid, 'decided_at' => time(),
            'channel_chat_id' => (int)$channelId, 'channel_message_id' => (int)$res['result']['message_id'],
        ]);
        $sub = submissionGet($subId);
        notifySubmitter($sub, botText('report_approved'));
        tgAnswerCallback($cq['id'], 'تایید شد ✅');
    } else {
        submissionUpdate($subId, ['status' => 'rejected', 'decided_by' => $uid, 'decided_at' => time()]);
        $sub = submissionGet($subId);
        notifySubmitter($sub, botText('report_rejected'));
        tgAnswerCallback($cq['id'], 'رد شد ❌');
    }

    $cap = buildGroupCaption($sub);
    editGroupPost($sub, $cap['text'], $cap['entities'], [
        'reply_markup' => kbReview($subId, tagGetAll(), $sub['tag_id'] ? (int)$sub['tag_id'] : null, true),
    ]);
}

function handleGroupEditStart(array $cq, int $subId): void {
    $uid = (int)$cq['from']['id'];
    if (!reportIsAdmin($uid)) { tgAnswerCallback($cq['id'], 'اجازه‌ی این کار را ندارید.', true); return; }

    $sub = submissionGet($subId);
    if (!$sub) { tgAnswerCallback($cq['id'], 'یافت نشد.', true); return; }
    if ($sub['status'] !== 'pending') { tgAnswerCallback($cq['id'], 'قبلاً بررسی شده است.', true); return; }

    $old = stateGet($uid);
    if ($old['state'] === 'admin_await_group_edit' && !empty($old['data']['prompt'])) {
        tgDeleteMessage((int)$old['data']['chat'], (int)$old['data']['prompt']);
    }

    $post = $cq['message'];
    $chatId = (int)$post['chat']['id'];
    $res = tgSendMessage($chatId, '✏️ متن جدید:', [], array_merge(replyOptsFor($post), [
        'reply_markup' => ['force_reply' => true],
    ]));

    stateSet($uid, 'admin_await_group_edit', [
        'subId' => $subId,
        'chat' => $chatId,
        'thread' => msgThreadId($post),
        'prompt' => !empty($res['ok']) ? (int)$res['result']['message_id'] : null,
        'at' => time(),
    ]);
    tgAnswerCallback($cq['id']);
}

function handleGroupEditMessage(array $msg, array $st): bool {
    $uid = (int)$msg['from']['id'];
    $d = $st['data'];

    if (time() - (int)($d['at'] ?? 0) > 900) { stateClear($uid); return false; }
    if ((int)$msg['chat']['id'] !== (int)($d['chat'] ?? 0)) return false;
    if (msgThreadId($msg) !== ($d['thread'] ?? null)) return false;

    $reply = $msg['reply_to_message'] ?? null;
    if ($reply && empty($reply['forum_topic_created']) && (int)$reply['message_id'] !== (int)($d['prompt'] ?? 0)) return false;

    $newText = $msg['text'] ?? '';
    if (trim($newText) === '') return false;

    stateClear($uid);
    if (!empty($d['prompt'])) tgDeleteMessage((int)$d['chat'], (int)$d['prompt']);

    $subId = (int)$d['subId'];
    $sub = submissionGet($subId);
    if (!$sub || $sub['status'] !== 'pending') return true;

    submissionUpdate($subId, [
        'orig_caption' => $newText,
        'orig_caption_entities' => json_encode($msg['entities'] ?? [], JSON_UNESCAPED_UNICODE),
    ]);

    $sub = submissionGet($subId);
    $cap = buildGroupCaption($sub);
    editGroupPost($sub, $cap['text'], $cap['entities'], [
        'reply_markup' => kbReview($subId, tagGetAll(), $sub['tag_id'] ? (int)$sub['tag_id'] : null, false),
    ]);
    return true;
}

function handleAdminReportReply(array $msg): bool {
    $aid = (int)$msg['from']['id'];
    if (!reportIsAdmin($aid)) return false;
    $reply = $msg['reply_to_message'] ?? null;
    if (!$reply) return false;

    $sub = submissionFindByGroupMessage((int)$msg['chat']['id'], (int)$reply['message_id']);
    if (!$sub) return false;

    $t = botText('report_admin_note_prefix');
    tgSendMessage((int)$sub['src_chat_id'], $t['text'], $t['entities']);
    $res = tgCopyMessage((int)$sub['src_chat_id'], (int)$msg['chat']['id'], (int)$msg['message_id']);
    if (empty($res['ok'])) tgSendMessage((int)$msg['chat']['id'], '⚠️ به کاربر نرسید.', [], replyOptsFor($msg));
    return true;
}
