<?php

const REPORT_EMOJI_DEFAULTS = [
    'btn_report'   => '📮',
    'btn_support'  => '🎧',
    'btn_account'  => '👤',
    'btn_guide'    => '📖',
    'btn_back'     => '🔙',
    'btn_approve'  => '✅',
    'btn_reject'   => '❌',
    'start_prefix' => '👋',
    'admin_prefix' => '⚙️',
];

function emojiSlotLabel(string $slot): string {
    $labels = [
        'btn_report'   => 'دکمه‌ی «ارسال گزارش»',
        'btn_support'  => 'دکمه‌ی «ارتباط با پشتیبانی»',
        'btn_account'  => 'دکمه‌ی «حساب کاربری»',
        'btn_guide'    => 'دکمه‌ی «راهنما»',
        'btn_back'     => 'دکمه‌ی «بازگشت»',
        'btn_approve'  => 'دکمه‌ی «تایید»',
        'btn_reject'   => 'دکمه‌ی «رد»',
        'admin_prefix' => 'دکمه‌ی «پنل مدیریت»',
    ];
    return $labels[$slot] ?? $slot;
}

function buttonLabelGet(string $slot): ?string {
    return settingGet("btnlabel:$slot");
}

function buttonLabelSet(string $slot, string $label): void {
    settingSet("btnlabel:$slot", $label);
}

function buttonLabelClear(string $slot): void {
    settingDel("btnlabel:$slot");
}

function emojiBtn(string $slot, string $defaultLabel, string $callbackData, ?string $defaultStyle = null): array {
    $label = buttonLabelGet($slot) ?? $defaultLabel;
    $btn = ['text' => trim((REPORT_EMOJI_DEFAULTS[$slot] ?? '') . ' ' . $label), 'callback_data' => $callbackData];
    $style = styleGet($slot);
    if ($style === null) $style = $defaultStyle;
    if ($style) $btn['style'] = $style;
    return $btn;
}
