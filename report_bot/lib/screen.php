<?php

function screenAnchorFromCq(array $cq): array {
    return [
        'chat_id' => (int)$cq['message']['chat']['id'],
        'message_id' => (int)$cq['message']['message_id'],
        'has_photo' => isset($cq['message']['photo']),
    ];
}

function screenEditOk($res): bool {
    if (!empty($res['ok'])) return true;
    return !empty($res['description']) && stripos($res['description'], 'not modified') !== false;
}

function screenRender(int $chatId, ?int $messageId, bool $hasPhoto, string $text, array $entities, array $keyboard, ?string $photo = null): array {
    if ($messageId) {
        $opts = ['reply_markup' => $keyboard];
        if ($hasPhoto && $photo) {
            $media = ['type' => 'photo', 'media' => $photo, 'caption' => $text];
            if ($entities) $media['caption_entities'] = $entities;
            $res = tgEditMessageMedia($chatId, $messageId, $media, $opts);
        } elseif ($hasPhoto) {
            $res = tgEditCaption($chatId, $messageId, $text, $entities, $opts);
        } else {
            $res = tgEditMessageText($chatId, $messageId, $text, $entities, $opts);
        }
        if (screenEditOk($res)) {
            return ['chat_id' => $chatId, 'message_id' => $messageId, 'has_photo' => $hasPhoto];
        }
    }

    $res = tgSendMessage($chatId, $text, $entities, ['reply_markup' => $keyboard]);
    if (!empty($res['ok'])) {
        return ['chat_id' => $chatId, 'message_id' => (int)$res['result']['message_id'], 'has_photo' => false];
    }
    return ['chat_id' => $chatId, 'message_id' => $messageId, 'has_photo' => $hasPhoto];
}

function msgThreadId(array $msg): ?int {
    if (empty($msg['is_topic_message']) || !isset($msg['message_thread_id'])) return null;
    return (int)$msg['message_thread_id'];
}

function replyOptsFor(array $msg): array {
    $opts = ['reply_parameters' => ['message_id' => (int)$msg['message_id'], 'allow_sending_without_reply' => true]];
    $thread = msgThreadId($msg);
    if ($thread !== null) $opts['message_thread_id'] = $thread;
    return $opts;
}
