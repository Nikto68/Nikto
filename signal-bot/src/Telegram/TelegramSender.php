<?php

declare(strict_types=1);

namespace App\Telegram;

use Psr\Log\LoggerInterface;

/**
 * High-level Bot API operations built on top of TelegramClient. Every
 * outbound text always goes through here with (text, entities) — never
 * parse_mode + hand-escaped Markdown/HTML — so premium custom emoji and any
 * other entity type survive verbatim (spec #18).
 *
 * Quote/reply (spec #19) uses the official `reply_parameters` object
 * (Bot API 7.0+), never a hand-rolled "> quoted text" prefix, so Telegram
 * renders it as a native reply block with correct formatting.
 */
final class TelegramSender
{
    public function __construct(
        private readonly TelegramClient $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $entities
     * @param array<string, mixed>|null $replyMarkup
     * @return array<string, mixed>
     */
    public function sendMessage(
        int|string $chatId,
        string $text,
        array $entities = [],
        ?ReplyParameters $reply = null,
        ?array $replyMarkup = null,
    ): array {
        return $this->client->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'entities' => $entities === [] ? null : $entities,
            'reply_parameters' => $reply?->toArray(),
            'reply_markup' => $replyMarkup,
            'link_preview_options' => ['is_disabled' => true],
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $entities
     * @param array<string, mixed>|null $replyMarkup
     * @return array<string, mixed>
     */
    public function editMessageText(
        int|string $chatId,
        int $messageId,
        string $text,
        array $entities = [],
        ?array $replyMarkup = null,
    ): array {
        return $this->client->call('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'entities' => $entities === [] ? null : $entities,
            'reply_markup' => $replyMarkup,
            'link_preview_options' => ['is_disabled' => true],
        ]);
    }

    /**
     * @param array<string, mixed> $replyMarkup
     * @return array<string, mixed>
     */
    public function editMessageReplyMarkup(int|string $chatId, int $messageId, array $replyMarkup): array
    {
        return $this->client->call('editMessageReplyMarkup', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => $replyMarkup,
        ]);
    }

    public function deleteMessage(int|string $chatId, int $messageId): bool
    {
        return (bool) $this->client->call('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    public function pinChatMessage(int|string $chatId, int $messageId, bool $disableNotification = true): bool
    {
        return (bool) $this->client->call('pinChatMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'disable_notification' => $disableNotification,
        ]);
    }

    public function unpinChatMessage(int|string $chatId, ?int $messageId = null): bool
    {
        return (bool) $this->client->call('unpinChatMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): bool
    {
        return (bool) $this->client->call('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getChat(int|string $chatId): array
    {
        return $this->client->call('getChat', ['chat_id' => $chatId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getChatMember(int|string $chatId, int $userId): array
    {
        return $this->client->call('getChatMember', ['chat_id' => $chatId, 'user_id' => $userId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMe(): array
    {
        return $this->client->call('getMe');
    }

    public function setWebhook(string $url, string $secretToken): bool
    {
        return (bool) $this->client->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
            'allowed_updates' => ['message', 'edited_message', 'callback_query', 'my_chat_member'],
        ]);
    }
}
