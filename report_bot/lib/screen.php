<?php

function screenAnchorFromCq(array $cq): array {
    return [
        'chat_id' => (int)$cq['message']['chat']['id'],
        'message_id' => (int)$cq['message']['message_id'],
        'has_photo' => isset($cq['message']['photo']),
    ];
}

function screenRender(int $chatId, ?int $messageId, bool $hasPhoto, string $text, array $entities, array $keyboard): array {
    if ($messageId) {
        $res = $hasPhoto
            ? tgEditCaption($chatId, $messageId, $text, $entities, ['reply_markup' => $keyboard])
            : tgEditMessageText($chatId, $messageId, $text, $entities, ['reply_markup' => $keyboard]);
        $notModified = !empty($res['description']) && stripos($res['description'], 'not modified') !== false;
        if (!empty($res['ok']) || $notModified) {
            return ['chat_id' => $chatId, 'message_id' => $messageId, 'has_photo' => $hasPhoto];
        }
    }

    $res = tgSendMessage($chatId, $text, $entities, ['reply_markup' => $keyboard]);
    if (!empty($res['ok'])) {
        return ['chat_id' => $chatId, 'message_id' => (int)$res['result']['message_id'], 'has_photo' => false];
    }
    return ['chat_id' => $chatId, 'message_id' => $messageId, 'has_photo' => $hasPhoto];
}
