<?php

declare(strict_types=1);

namespace App\Bot\Admin\Templates;

use App\Bot\StateHandlerInterface;
use App\Bot\UpdateContext;
use App\Database\Repositories\ConversationStateRepository;
use App\Database\Repositories\SignalTemplateRepository;
use App\Telegram\TelegramSender;

final class AddTemplateNameStateHandler implements StateHandlerInterface
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

        $name = trim($context->text);
        if ($name === '') {
            $this->sender->sendMessage($context->chatId, 'نام نامعتبر است، دوباره ارسال کنید.');

            return;
        }

        $id = (int) $this->templates->create($name, "{direction} {symbol}\nEntry: {entry}\nSL: {stop_loss}\nTP1: {tp1}\nScore: {score}/100", [], $context->userId);
        $this->states->set($context->userId, 'templates:awaiting_value', ['id' => $id]);
        $this->sender->sendMessage($context->chatId, "قالب «{$name}» ساخته شد. حالا متن آن را ارسال کنید.");
    }
}
