<?php

function utf16Len(string $s): int {
    return (int)(strlen(mb_convert_encoding($s, 'UTF-16LE', 'UTF-8')) / 2);
}

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

function entityTruncate(array $part, int $max): array {
    $text = $part['text'] ?? '';
    if ($max <= 0) return ['text' => '', 'entities' => []];
    if (utf16Len($text) <= $max) return $part;

    $u = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
    $cut = $max - 1;
    if ($cut > 0) {
        $last = unpack('v', substr($u, ($cut - 1) * 2, 2))[1];
        if ($last >= 0xD800 && $last <= 0xDBFF) $cut--;
    }

    $kept = [];
    foreach (($part['entities'] ?? []) as $e) {
        $start = (int)$e['offset'];
        $end = $start + (int)$e['length'];
        if ($start >= $cut) continue;
        if ($end > $cut) {
            if (($e['type'] ?? '') === 'custom_emoji') continue;
            $e['length'] = $cut - $start;
        }
        $kept[] = $e;
    }
    return ['text' => mb_convert_encoding(substr($u, 0, $cut * 2), 'UTF-8', 'UTF-16LE') . '…', 'entities' => $kept];
}

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
