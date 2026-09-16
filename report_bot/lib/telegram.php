<?php
/** کلاینتِ سبکِ Bot API — فقط چیزهایی که همین ربات لازم دارد. */

function tgCall(string $method, array $params = []) {
    $params = array_filter($params, fn($v) => $v !== null);
    $url = 'https://api.telegram.org/bot' . REPORT_BOT_TOKEN . '/' . $method;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE),
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        reportLog("tgCall($method) curl error: $err");
        return ['ok' => false, 'description' => $err];
    }
    $data = json_decode($resp, true);
    if (!is_array($data)) {
        reportLog("tgCall($method) bad response: $resp");
        return ['ok' => false, 'description' => 'invalid json response'];
    }
    if (empty($data['ok'])) {
        reportLog("tgCall($method) failed: " . ($data['description'] ?? json_encode($data)));
    }
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

function tgEditCaption(int $chatId, int $messageId, string $caption, array $entities = [], array $opts = []) {
    return tgCall('editMessageCaption', array_merge([
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'caption' => $caption,
        'caption_entities' => $entities ?: null,
    ], $opts));
}

function tgEditReplyMarkup(int $chatId, int $messageId, ?array $markup) {
    return tgCall('editMessageReplyMarkup', [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'reply_markup' => $markup ?: ['inline_keyboard' => []],
    ]);
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
