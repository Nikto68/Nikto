<?php

declare(strict_types=1);

namespace App\Bot;

use App\Telegram\TelegramEntities;

/**
 * Normalizes the handful of Telegram update shapes (message, callback_query,
 * my_chat_member) the bot cares about into one convenient read-only view,
 * so handlers never poke at raw $update[...] arrays directly.
 */
final class UpdateContext
{
    /**
     * @param array<string, mixed> $raw
     */
    private function __construct(
        public readonly array $raw,
        public readonly string $kind,
        public readonly ?int $chatId,
        public readonly ?string $chatType,
        public readonly ?int $userId,
        public readonly ?string $username,
        public readonly string $text,
        public readonly array $entities,
        public readonly ?string $callbackData,
        public readonly ?int $callbackMessageId,
        public readonly ?string $callbackQueryId,
    ) {
    }

    /**
     * @param array<string, mixed> $update
     */
    public static function fromUpdate(array $update): self
    {
        if (isset($update['callback_query'])) {
            $cb = $update['callback_query'];
            $message = $cb['message'] ?? [];
            $chat = $message['chat'] ?? [];

            return new self(
                raw: $update,
                kind: 'callback_query',
                chatId: isset($chat['id']) ? (int) $chat['id'] : null,
                chatType: $chat['type'] ?? null,
                userId: isset($cb['from']['id']) ? (int) $cb['from']['id'] : null,
                username: $cb['from']['username'] ?? null,
                text: '',
                entities: [],
                callbackData: $cb['data'] ?? null,
                callbackMessageId: isset($message['message_id']) ? (int) $message['message_id'] : null,
                callbackQueryId: $cb['id'] ?? null,
            );
        }

        if (isset($update['my_chat_member'])) {
            $event = $update['my_chat_member'];
            $chat = $event['chat'] ?? [];

            return new self(
                raw: $update,
                kind: 'my_chat_member',
                chatId: isset($chat['id']) ? (int) $chat['id'] : null,
                chatType: $chat['type'] ?? null,
                userId: isset($event['from']['id']) ? (int) $event['from']['id'] : null,
                username: $event['from']['username'] ?? null,
                text: '',
                entities: [],
                callbackData: null,
                callbackMessageId: null,
                callbackQueryId: null,
            );
        }

        $message = $update['message'] ?? $update['edited_message'] ?? [];
        $chat = $message['chat'] ?? [];
        $parsed = TelegramEntities::fromMessage($message);

        return new self(
            raw: $update,
            kind: isset($update['message']) ? 'message' : (isset($update['edited_message']) ? 'edited_message' : 'unknown'),
            chatId: isset($chat['id']) ? (int) $chat['id'] : null,
            chatType: $chat['type'] ?? null,
            userId: isset($message['from']['id']) ? (int) $message['from']['id'] : null,
            username: $message['from']['username'] ?? null,
            text: $parsed['text'],
            entities: $parsed['entities'],
            callbackData: null,
            callbackMessageId: null,
            callbackQueryId: null,
        );
    }

    public function isPrivateChat(): bool
    {
        return $this->chatType === 'private';
    }

    public function isCommand(string $command): bool
    {
        return $this->text === $command || str_starts_with($this->text, $command . ' ') || str_starts_with($this->text, $command . '@');
    }
}
