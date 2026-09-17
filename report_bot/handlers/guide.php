<?php

function handleGuideView(array $cq): void {
    $t = botText('guide_text');
    $photo = settingGet('guide_photo_file_id');
    $chatId = (int)$cq['message']['chat']['id'];

    if ($photo) {
        tgSendPhoto($chatId, $photo, $t['text'], $t['entities']);
    } else {
        tgSendMessage($chatId, $t['text'], $t['entities']);
    }
    tgAnswerCallback($cq['id']);
}
