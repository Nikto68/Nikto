<?php
/**
 * ایموجیِ پریمیوم، جای‌به‌جاشونده توسط پنلِ مدیریت.
 *
 * ⚠️ محدودیتِ واقعیِ Bot API: روی متنِ دکمه‌های شیشه‌ای هیچ entity/فرمتی
 * پذیرفته نمی‌شود — فقط رشته‌ی ساده. یعنی گرافیکِ متحرکِ ایموجیِ پریمیوم
 * فقط داخلِ متنِ پیام‌ها/کپشن‌ها (با caption_entities از نوعِ custom_emoji)
 * دیده می‌شود؛ روی دکمه‌ها همیشه همان کاراکترِ ساده‌ی جایگزین (placeholder)
 * نمایش داده می‌شود، نه نسخه‌ی پریمیوم.
 */

const REPORT_EMOJI_DEFAULTS = [
    'btn_report'   => '📮',
    'btn_support'  => '🎧',
    'btn_approve'  => '✅',
    'btn_reject'   => '❌',
    'start_prefix' => '👋',
    'admin_prefix' => '⚙️',
];

function emojiSlotLabel(string $slot): string {
    $labels = [
        'btn_report'   => 'دکمه‌ی «ارسال گزارش»',
        'btn_support'  => 'دکمه‌ی «ارتباط با پشتیبانی»',
        'btn_approve'  => 'دکمه‌ی «تایید»',
        'btn_reject'   => 'دکمه‌ی «رد»',
        'start_prefix' => 'ابتدای پیام خوش‌آمد',
        'admin_prefix' => 'دکمه‌ی «پنل مدیریت»',
    ];
    return $labels[$slot] ?? $slot;
}

function emojiGet(string $slot): array {
    $stmt = reportDb()->prepare('SELECT custom_emoji_id, placeholder FROM premium_emoji WHERE slot = :s');
    $stmt->bindValue(':s', $slot, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if ($row) return ['id' => $row['custom_emoji_id'], 'char' => $row['placeholder']];
    return ['id' => null, 'char' => REPORT_EMOJI_DEFAULTS[$slot] ?? '•'];
}

function emojiSet(string $slot, string $customEmojiId, string $placeholder): void {
    $stmt = reportDb()->prepare('INSERT INTO premium_emoji (slot, custom_emoji_id, placeholder, updated_at) VALUES (:s,:i,:p,:t)
        ON CONFLICT(slot) DO UPDATE SET custom_emoji_id=excluded.custom_emoji_id, placeholder=excluded.placeholder, updated_at=excluded.updated_at');
    $stmt->bindValue(':s', $slot, SQLITE3_TEXT);
    $stmt->bindValue(':i', $customEmojiId, SQLITE3_TEXT);
    $stmt->bindValue(':p', $placeholder, SQLITE3_TEXT);
    $stmt->bindValue(':t', time(), SQLITE3_INTEGER);
    $stmt->execute();
}

function emojiClear(string $slot): void {
    $stmt = reportDb()->prepare('DELETE FROM premium_emoji WHERE slot = :s');
    $stmt->bindValue(':s', $slot, SQLITE3_TEXT);
    $stmt->execute();
}

/** برای متن/کپشن: {text, entities} که entity پریمیوم را هم شامل می‌شود */
function emojiTextPart(string $slot): array {
    $e = emojiGet($slot);
    if ($e['id']) {
        return ['text' => $e['char'], 'entities' => [[
            'type' => 'custom_emoji', 'offset' => 0, 'length' => utf16Len($e['char']),
            'custom_emoji_id' => $e['id'],
        ]]];
    }
    return ['text' => $e['char'], 'entities' => []];
}

/** برای دکمه‌ها: فقط کاراکترِ ساده (بدونِ گرافیکِ پریمیوم — محدودیتِ تلگرام) */
function emojiButtonChar(string $slot): string {
    return emojiGet($slot)['char'];
}
