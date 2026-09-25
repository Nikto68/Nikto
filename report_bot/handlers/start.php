<?php

function startScreenContent(int $uid): array {
    $startText = settingGet('start_text');
    $startEntities = json_decode(settingGet('start_text_entities', '[]'), true) ?: [];

    if ($startText === null) {
        $startText = REPORT_EMOJI_DEFAULTS['start_prefix'] . ' به ربات گزارشات خوش آمدید!';
        $startEntities = [];
    }

    return [
        'text' => $startText,
        'entities' => $startEntities,
        'keyboard' => kbStart(reportIsAdmin($uid)),
        'photo' => settingGet('start_photo_file_id'),
    ];
}

function handleStartCommand(array $msg): void {
    $chatId = (int)$msg['chat']['id'];
    $uid = (int)$msg['from']['id'];
    stateClear($uid);

    $c = startScreenContent($uid);
    if ($c['photo']) {
        $res = tgSendPhoto($chatId, $c['photo'], $c['text'], $c['entities'], ['reply_markup' => $c['keyboard']]);
        if (!empty($res['ok'])) return;
    }
    tgSendMessage($chatId, $c['text'], $c['entities'], ['reply_markup' => $c['keyboard']]);
}

function handleNavBack(array $cq): void {
    $uid = (int)$cq['from']['id'];
    $st = stateGet($uid);
    stateClear($uid);
    tgAnswerCallback($cq['id']);

    $a = screenAnchorFromCq($cq);
    if (reportIsAdmin($uid) && $st['state'] && strncmp($st['state'], 'admin_', 6) === 0) {
        screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], '⚙️ پنل مدیریت ربات گزارشات', [], kbAdminMenu());
        return;
    }

    $c = startScreenContent($uid);
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], $c['text'], $c['entities'], $c['keyboard'], $c['photo']);
}
