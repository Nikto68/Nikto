<?php

declare(strict_types=1);

namespace App\Bot\Admin\Texts;

use App\Bot\StateHandlerInterface;
use App\Bot\UpdateContext;
use App\Database\Repositories\ConversationStateRepository;
use App\Database\Repositories\TextFormatRepository;
use App\Telegram\TelegramEntities;
use App\Telegram\TelegramSender;

final class TextEditStateHandler implements StateHandlerInterface
{
    public function __construct(
        private readonly TextFormatRepository $texts,
        private readonly TelegramSender $sender,
        private readonly ConversationStateRepository $states,
    ) {
    }

    public function handle(UpdateContext $context, array $stateContext): void
    {
        if ($context->chatId === null || $context->userId === null) {
            return;
        }

        $this->states->clear($context->userId);

        $key = (string) ($stateContext['key'] ?? '');
        if ($key === '' || trim($context->text) === '') {
            $this->sender->sendMessage($context->chatId, 'متن نامعتبر بود، دوباره تلاش کنید.');

            return;
        }

        $reply = $this->extractReply($context);

        $this->texts->save($key, $context->text, $context->entities, $context->userId, null, $reply);
        $this->sender->sendMessage($context->chatId, "متن «{$key}» بروزرسانی شد.");
    }

    /**
     * @return array{reply_to_message_id: int, quote_text: string, quote_entities: array<int, array<string, mixed>>}|null
     */
    private function extractReply(UpdateContext $context): ?array
    {
        $repliedTo = $context->raw['message']['reply_to_message'] ?? null;
        if (!is_array($repliedTo) || !isset($repliedTo['message_id'])) {
            return null;
        }

        $parsed = TelegramEntities::fromMessage($repliedTo);

        return [
            'reply_to_message_id' => (int) $repliedTo['message_id'],
            'quote_text' => $parsed['text'],
            'quote_entities' => $parsed['entities'],
        ];
    }
}
