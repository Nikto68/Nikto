<?php

function kbStart(bool $isAdmin): array {
    $rows = [
        [
            emojiBtn('btn_report', 'ارسال گزارش', 'rep:new', 'primary'),
            emojiBtn('btn_support', 'ارتباط با پشتیبانی', 'sup:new', 'primary'),
        ],
        [emojiBtn('btn_account', 'حساب کاربری', 'acc:view', 'primary')],
    ];
    if ($isAdmin) {
        $rows[] = [emojiBtn('admin_prefix', 'پنل مدیریت', 'adm:menu')];
    }
    return ['inline_keyboard' => $rows];
}

function kbBack(): array {
    return ['inline_keyboard' => [[emojiBtn('btn_back', 'بازگشت', 'nav:back')]]];
}

function kbReview(int $submissionId, array $tags, ?int $selectedTagId, bool $decided): array {
    $rows = [];
    $row = [];
    foreach ($tags as $t) {
        $mark = ($selectedTagId !== null && (int)$t['id'] === $selectedTagId) ? '✅ ' : '';
        $row[] = ['text' => $mark . $t['text'], 'callback_data' => "tg:$submissionId:{$t['id']}"];
        if (count($row) === 2) { $rows[] = $row; $row = []; }
    }
    if ($row) $rows[] = $row;

    if (!$decided) {
        $rows[] = [
            emojiBtn('btn_approve', 'تایید', "ap:$submissionId", 'success'),
            emojiBtn('btn_reject', 'رد', "rj:$submissionId", 'danger'),
        ];
    }
    return ['inline_keyboard' => $rows];
}

function kbAdminMenu(): array {
    return ['inline_keyboard' => [
        [['text' => '🏷 مدیریت تگ‌ها', 'callback_data' => 'adm:tags']],
        [['text' => '⭐ ایموجی‌های پریمیوم', 'callback_data' => 'adm:emoji']],
        [['text' => '🖼 عکس پیام خوش‌آمد', 'callback_data' => 'adm:startphoto']],
        [['text' => '✏️ متن پیام خوش‌آمد', 'callback_data' => 'adm:starttext']],
        [['text' => '👥 گروه/تاپیک گزارش', 'callback_data' => 'adm:group']],
        [['text' => '📨 گروه/تاپیک پشتیبانی', 'callback_data' => 'adm:supportgroup']],
        [['text' => '📢 کانال مقصد', 'callback_data' => 'adm:channel']],
        [['text' => 'ℹ️ وضعیت تنظیمات', 'callback_data' => 'adm:status']],
    ]];
}

function kbBackToAdmin(): array {
    return ['inline_keyboard' => [[emojiBtn('btn_back', 'بازگشت', 'adm:menu')]]];
}
