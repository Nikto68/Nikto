<?php

function tgHttp(string $method, array $params): array {
    $base = getenv('REPORT_API_BASE') ?: 'https://api.telegram.org';
    $ch = curl_init($base . '/bot' . REPORT_BOT_TOKEN . '/' . $method);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false) return ['ok' => false, 'description' => "curl: $err"];
    $data = json_decode($resp, true);
    return is_array($data) ? $data : ['ok' => false, 'description' => 'invalid json response'];
}

function reportChatMigrated(int $oldId, int $newId): void {
    foreach (['report_group_id', 'support_group_id'] as $k) {
        if ((int)settingGet($k, 0) === $oldId) settingSet($k, $newId);
    }
}

function tgCall(string $method, array $params = []) {
    $params = array_filter($params, fn($v) => $v !== null);

    for ($attempt = 0; ; $attempt++) {
        $data = tgHttp($method, $params);
        if (!empty($data['ok']) || $attempt >= 2) break;

        $migrateTo = $data['parameters']['migrate_to_chat_id'] ?? null;
        $retryAfter = (int)($data['parameters']['retry_after'] ?? 0);
        if ($migrateTo && isset($params['chat_id'])) {
            reportChatMigrated((int)$params['chat_id'], (int)$migrateTo);
            $params['chat_id'] = (int)$migrateTo;
        } elseif ($retryAfter > 0 && $retryAfter <= 30) {
            sleep($retryAfter);
        } else {
            break;
        }
    }

    if (empty($data['ok'])) reportLog("tgCall($method) failed: " . ($data['description'] ?? json_encode($data)));
    return $data;
}

function tgSendMessage(int $chatId, string $text, array $entities = [], array $opts = []) {
    return tgCall('sendMessage', array_merge([
        'chat_id' => $chatId,
        'text' => $text,
        'entities' => $entities ?: null,
    ], $opts));
}

function tgSendPhoto(int $chatId, string $fileId, string $caption = '', array $captionEntities = [], array $opts = []) {
    return tgCall('sendPhoto', array_merge([
        'chat_id' => $chatId,
        'photo' => $fileId,
        'caption' => $caption !== '' ? $caption : null,
        'caption_entities' => $captionEntities ?: null,
    ], $opts));
}

function tgCopyMessage(int $toChatId, int $fromChatId, int $messageId, array $opts = []) {
    return tgCall('copyMessage', array_merge([
        'chat_id' => $toChatId,
        'from_chat_id' => $fromChatId,
        'message_id' => $messageId,
    ], $opts));
}

function tgEditMessageText(int $chatId, int $messageId, string $text, array $entities = [], array $opts = []) {
    return tgCall('editMessageText', array_merge([
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'entities' => $entities ?: null,
    ], $opts));
}

function tgEditCaption(int $chatId, int $messageId, string $caption, array $entities = [], array $opts = []) {
    return tgCall('editMessageCaption', array_merge([
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'caption' => $caption,
        'caption_entities' => $entities ?: null,
    ], $opts));
}

function tgEditMessageMedia(int $chatId, int $messageId, array $media, array $opts = []) {
    return tgCall('editMessageMedia', array_merge([
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'media' => $media,
    ], $opts));
}

function tgEditReplyMarkup(int $chatId, int $messageId, ?array $markup) {
    return tgCall('editMessageReplyMarkup', [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'reply_markup' => $markup ?: ['inline_keyboard' => []],
    ]);
}

function tgDeleteMessage(int $chatId, int $messageId) {
    return tgCall('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
}

function tgAnswerCallback(string $callbackId, string $text = '', bool $alert = false) {
    return tgCall('answerCallbackQuery', [
        'callback_query_id' => $callbackId,
        'text' => $text !== '' ? $text : null,
        'show_alert' => $alert,
    ]);
}

function tgGetChat($chatId) {
    return tgCall('getChat', ['chat_id' => $chatId]);
}

function tgGetChatMember($chatId, int $userId) {
    return tgCall('getChatMember', ['chat_id' => $chatId, 'user_id' => $userId]);
}
