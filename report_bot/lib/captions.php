<?php

function captionLimit(array $sub): int {
    return $sub['media_type'] === 'text' ? 4096 : 1024;
}

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

function origPart(array $sub): ?array {
    $orig = $sub['orig_caption'] ?? '';
    if ($orig === '') return null;
    return ['text' => $orig, 'entities' => json_decode($sub['orig_caption_entities'] ?: '[]', true) ?: []];
}

function tagParts(array $sub): array {
    if (empty($sub['tag_id'])) return [];
    $tag = tagGet((int)$sub['tag_id']);
    if (!$tag) return [];
    return [['text' => '🏷 ', 'entities' => []], ['text' => $tag['text'], 'entities' => $tag['entities']]];
}

function concatWithOrig(array $before, ?array $orig, array $after, int $limit): array {
    if ($orig === null) return entityConcat(array_merge($before, $after));
    $used = 0;
    foreach (array_merge($before, $after) as $p) $used += utf16Len($p['text']);
    return entityConcat(array_merge($before, [entityTruncate($orig, $limit - $used)], $after));
}

function buildGroupCaption(array $sub): array {
    $gap = ['text' => "\n\n", 'entities' => []];
    $orig = origPart($sub);

    $before = [submitterLine($sub)];
    if ($orig) $before[] = $gap;

    $after = [];
    $tag = tagParts($sub);
    if ($tag) $after = array_merge([$gap], $tag);
    $after[] = $gap;
    $after[] = statusLine($sub['status']);

    return concatWithOrig($before, $orig, $after, captionLimit($sub));
}

function buildChannelCaption(array $sub): array {
    $orig = origPart($sub);
    $before = tagParts($sub);
    if ($before && $orig) $before[] = ['text' => "\n\n", 'entities' => []];
    return concatWithOrig($before, $orig, [], captionLimit($sub));
}
