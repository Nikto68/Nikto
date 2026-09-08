<?php

declare(strict_types=1);

namespace App\Bot\Admin\Templates;

use App\Bot\StateHandlerInterface;
use App\Bot\UpdateContext;
use App\Database\Repositories\ConversationStateRepository;
use App\Database\Repositories\SignalTemplateRepository;
use App\Telegram\TelegramSender;

final class TemplateEditStateHandler implements StateHandlerInterface
{
    public function __construct(
        private readonly SignalTemplateRepository $templates,
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

        $id = (int) ($stateContext['id'] ?? 0);
        if ($id === 0 || trim($context->text) === '') {
            $this->sender->sendMessage($context->chatId, 'متن نامعتبر بود.');

            return;
        }

        $this->templates->update($id, $context->text, $context->entities);
        $this->sender->sendMessage($context->chatId, 'قالب بروزرسانی شد.');
    }
}
