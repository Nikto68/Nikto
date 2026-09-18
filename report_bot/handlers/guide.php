<?php

/**
 * ادیتِ همان پیام (استارت) به متنِ راهنما، با دکمه‌ی بازگشت. تنها استثنا:
 * وقتی راهنما عکسِ خودش را دارد ولی پیامِ فعلی عکس ندارد — تلگرام اجازه‌ی
 * تبدیلِ پیامِ متنی به پیامِ عکس‌دار را با ادیت نمی‌دهد، پس آن یک حالت
 * ناچاراً پیامِ تازه می‌ماند.
 */
function handleGuideView(array $cq): void {
    $t = botText('guide_text');
    $photo = settingGet('guide_photo_file_id');
    $a = screenAnchorFromCq($cq);

    if ($photo && !$a['has_photo']) {
        tgSendPhoto($a['chat_id'], $photo, $t['text'], $t['entities'], ['reply_markup' => kbBack()]);
    } else {
        screenRender($a['chat_id'], $a['message_id'], $a['has_photo'], $t['text'], $t['entities'], kbBack());
    }
    tgAnswerCallback($cq['id']);
}
