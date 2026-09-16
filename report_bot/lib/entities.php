<?php
/**
 * ابزارهای کار با entities تلگرام (بولد/لینک/ایموجی‌پریمیوم و...).
 * آفست‌ها در تلگرام بر حسبِ واحدهای UTF-16 هستند، نه بایت و نه کاراکترِ ساده؛
 * برای درست‌کارکردنِ ایموجی/امضی‌های فارسی باید همه‌جا از همین واحد استفاده کرد.
 */

function utf16Len(string $s): int {
    return (int)(strlen(mb_convert_encoding($s, 'UTF-16LE', 'UTF-8')) / 2);
}

/**
 * چند تکه‌ی {text, entities} را پشتِ سرِ هم می‌چسباند و آفستِ entities هر
 * تکه را با طولِ (UTF-16) تکه‌های قبلی جمع می‌زند — تا caption‌های ترکیبی
 * (خطِ فرستنده + متنِ اصلی + تگ + وضعیت) با فرمت/ایموجیِ هرکدام سالم بمانند.
 */
function entityConcat(array $parts): array {
    $text = '';
    $entities = [];
    $offset = 0;
    foreach ($parts as $p) {
        $t = $p['text'] ?? '';
        foreach (($p['entities'] ?? []) as $e) {
            $e['offset'] = $offset + (int)$e['offset'];
            $entities[] = $e;
        }
        $text .= $t;
        $offset += utf16Len($t);
    }
    return ['text' => $text, 'entities' => $entities];
}

/** اولین ایموجیِ پریمیومِ داخلِ متن/کپشنِ یک پیام را برمی‌گرداند (یا null) */
function extractFirstCustomEmoji(array $message): ?array {
    $entities = $message['entities'] ?? ($message['caption_entities'] ?? []);
    $text = $message['text'] ?? ($message['caption'] ?? '');
    if (!$entities || $text === '') return null;

    $utf16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
    foreach ($entities as $e) {
        if (($e['type'] ?? '') === 'custom_emoji') {
            $slice = substr($utf16, (int)$e['offset'] * 2, (int)$e['length'] * 2);
            return [
                'custom_emoji_id' => $e['custom_emoji_id'],
                'placeholder' => mb_convert_encoding($slice, 'UTF-8', 'UTF-16LE'),
            ];
        }
    }
    return null;
}
