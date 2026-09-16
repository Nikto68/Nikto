<?php
/** ساختِ کپشنِ پستِ گروه/تاپیک و کپشنِ نهاییِ کانال، از رویِ یک submission */

function submitterLine(array $sub): array {
    $name = trim(($sub['first_name'] ?? '') !== '' ? $sub['first_name'] : 'کاربر');
    $who = $sub['username'] ? '@' . $sub['username'] : "شناسه: {$sub['user_id']}";
    return ['text' => "👤 از: $name ($who)", 'entities' => []];
}

function statusLine(string $status): array {
    $map = [
        'pending'  => '🕓 وضعیت: در انتظار بررسی',
        'approved' => '✅ وضعیت: تایید و منتشر شد',
        'rejected' => '❌ وضعیت: رد شد',
    ];
    return ['text' => $map[$status] ?? $status, 'entities' => []];
}

function buildGroupCaption(array $sub): array {
    $parts = [submitterLine($sub)];

    $orig = $sub['orig_caption'] ?? '';
    if ($orig !== '') {
        $parts[] = ['text' => "\n\n" . $orig, 'entities' => json_decode($sub['orig_caption_entities'] ?: '[]', true) ?: []];
    }

    if (!empty($sub['tag_id'])) {
        $tag = tagGet((int)$sub['tag_id']);
        if ($tag) {
            $parts[] = ['text' => "\n\n🏷 ", 'entities' => []];
            $parts[] = ['text' => $tag['text'], 'entities' => $tag['entities']];
        }
    }

    $parts[] = ['text' => "\n\n", 'entities' => []];
    $parts[] = statusLine($sub['status']);

    return entityConcat($parts);
}

function buildChannelCaption(array $sub): array {
    $parts = [];

    if (!empty($sub['tag_id'])) {
        $tag = tagGet((int)$sub['tag_id']);
        if ($tag) {
            $parts[] = ['text' => "🏷 ", 'entities' => []];
            $parts[] = ['text' => $tag['text'], 'entities' => $tag['entities']];
            $parts[] = ['text' => "\n\n", 'entities' => []];
        }
    }

    $orig = $sub['orig_caption'] ?? '';
    if ($orig !== '') {
        $parts[] = ['text' => $orig, 'entities' => json_decode($sub['orig_caption_entities'] ?: '[]', true) ?: []];
    }

    if (!$parts) return ['text' => '', 'entities' => []];
    return entityConcat($parts);
}
