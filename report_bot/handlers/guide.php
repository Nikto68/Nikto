<?php

function handleGuideView(array $cq): void {
    $t = botText('guide_text');
    $photo = settingGet('guide_photo_file_id');
    $a = screenAnchorFromCq($cq);
    tgAnswerCallback($cq['id']);

    if ($photo && !$a['has_photo']) {
        $res = tgSendPhoto($a['chat_id'], $photo, $t['text'], $t['entities'], ['reply_markup' => kbBack()]);
        if (!empty($res['ok'])) return;
    }
    screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], $t['text'], $t['entities'], kbBack(), $photo);
}
