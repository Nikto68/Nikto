<?php

const REPORT_TEXT_DEFAULTS = [
    'guide_text'              => '📖 راهنما',
    'report_prompt'           => '📎 متن، عکس یا ویدیوی گزارش را ارسال کنید.',
    'report_invalid'          => '⚠️ فقط متن، عکس یا ویدیو ارسال کنید.',
    'report_disabled'         => '⛔️ ارسال گزارش موقتاً غیرفعال است.',
    'report_confirm_prompt'   => 'این محتوا برای بررسی ارسال شود؟',
    'report_submit_failed'    => '⚠️ ثبتِ گزارش ناموفق بود؛ دوباره تلاش کنید.',
    'report_submitted'        => '✅ گزارش شما ثبت شد؛ نتیجه همین‌جا اطلاع داده می‌شود.',
    'report_approved'         => '✅ گزارش شما تایید و منتشر شد.',
    'report_rejected'         => '❌ گزارش شما رد شد.',
    'report_admin_note_prefix'=> '💬 پیامی درباره‌ی گزارش شما:',
    'support_prompt'          => '💬 پیام خود را برای پشتیبانی بفرستید.',
    'support_disabled'        => '⛔️ پشتیبانی موقتاً در دسترس نیست.',
    'support_send_failed'     => '⚠️ ارسالِ پیام ناموفق بود؛ دوباره تلاش کنید.',
    'support_sent'            => '✅ پیام شما برای پشتیبانی ارسال شد.',
    'support_reply_prefix'    => '👨‍💻 پاسخ پشتیبانی:',
];

const REPORT_TEXT_LABELS = [
    'guide_text'               => 'متنِ راهنما',
    'report_prompt'            => 'گزارش: درخواستِ محتوا',
    'report_invalid'           => 'گزارش: محتوایِ نامعتبر',
    'report_disabled'          => 'گزارش: غیرفعال',
    'report_confirm_prompt'    => 'گزارش: تاییدِ ارسال',
    'report_submit_failed'     => 'گزارش: خطایِ ثبت',
    'report_submitted'         => 'گزارش: تاییدیه‌ی ثبت',
    'report_approved'          => 'گزارش: تایید شد',
    'report_rejected'          => 'گزارش: رد شد',
    'report_admin_note_prefix' => 'گزارش: پیشوندِ ریپلایِ مدیر',
    'support_prompt'           => 'پشتیبانی: درخواستِ پیام',
    'support_disabled'         => 'پشتیبانی: غیرفعال',
    'support_send_failed'      => 'پشتیبانی: خطایِ ارسال',
    'support_sent'             => 'پشتیبانی: تاییدیه‌ی ارسال',
    'support_reply_prefix'     => 'پشتیبانی: پیشوندِ پاسخ',
];

const REPORT_TEXT_KEYS = [
    'guide_text',
    'report_prompt', 'report_invalid', 'report_disabled', 'report_confirm_prompt',
    'report_submit_failed', 'report_submitted', 'report_approved', 'report_rejected', 'report_admin_note_prefix',
    'support_prompt', 'support_disabled', 'support_send_failed', 'support_sent', 'support_reply_prefix',
];

function botText(string $key): array {
    $stored = settingGet("txt:$key");
    if ($stored === null) {
        return ['text' => REPORT_TEXT_DEFAULTS[$key] ?? $key, 'entities' => []];
    }
    $ents = json_decode(settingGet("txt:$key:e", '[]'), true) ?: [];
    return ['text' => $stored, 'entities' => $ents];
}

function textSet(string $key, string $text, array $entities): void {
    settingSet("txt:$key", $text);
    settingSet("txt:$key:e", json_encode($entities, JSON_UNESCAPED_UNICODE));
}

function textClear(string $key): void {
    settingDel("txt:$key");
    settingDel("txt:$key:e");
}
