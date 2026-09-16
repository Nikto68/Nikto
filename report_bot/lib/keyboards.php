<?php
/** ساختِ کیبوردهای شیشه‌ای */

function kbStart(bool $isAdmin): array {
    $rows = [[
        ['text' => trim(emojiButtonChar('btn_report') . ' ارسال گزارش'), 'callback_data' => 'rep:new'],
        ['text' => trim(emojiButtonChar('btn_support') . ' ارتباط با پشتیبانی'), 'callback_data' => 'sup:new'],
    ]];
    if ($isAdmin) {
        $rows[] = [['text' => trim(emojiButtonChar('admin_prefix') . ' پنل مدیریت'), 'callback_data' => 'adm:menu']];
    }
    return ['inline_keyboard' => $rows];
}

function kbCancel(): array {
    return ['inline_keyboard' => [[['text' => '🔙 انصراف', 'callback_data' => 'rep:cancel']]]];
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
            ['text' => trim(emojiButtonChar('btn_approve') . ' تایید'), 'callback_data' => "ap:$submissionId"],
            ['text' => trim(emojiButtonChar('btn_reject') . ' رد'), 'callback_data' => "rj:$submissionId"],
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
        [['text' => '📢 کانال مقصد', 'callback_data' => 'adm:channel']],
        [['text' => 'ℹ️ وضعیت تنظیمات', 'callback_data' => 'adm:status']],
    ]];
}

function kbBackToAdmin(): array {
    return ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'adm:menu']]]];
}
