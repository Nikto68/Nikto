<?php

function handleStartCommand(array $msg): void {
    $chatId = (int)$msg['chat']['id'];
    $uid = (int)$msg['from']['id'];
    stateClear($uid);

    $startText = settingGet('start_text');
    $startEntities = json_decode(settingGet('start_text_entities', '[]'), true) ?: [];

    if ($startText === null) {
        $built = entityConcat([
            emojiTextPart('start_prefix'),
            ['text' => " به ربات گزارشات خوش آمدید!\n\nبرای ارسالِ گزارش (عکس یا ویدیو) یا ارتباط با پشتیبانی، یکی از دکمه‌های زیر را بزنید.", 'entities' => []],
        ]);
        $startText = $built['text'];
        $startEntities = $built['entities'];
    }

    $kb = kbStart(reportIsAdmin($uid));
    $photoId = settingGet('start_photo_file_id');

    if ($photoId) {
        tgSendPhoto($chatId, $photoId, $startText, $startEntities, ['reply_markup' => $kb]);
    } else {
        tgSendMessage($chatId, $startText, $startEntities, ['reply_markup' => $kb]);
    }
}
