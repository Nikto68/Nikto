<?php

declare(strict_types=1);

namespace App\Bot\Admin\Texts;

use App\Bot\StateHandlerInterface;
use App\Bot\UpdateContext;
use App\Database\Repositories\ConversationStateRepository;
use App\Telegram\TelegramSender;

final class AddTextKeyStateHandler implements StateHandlerInterface
{
    public function __construct(
        private readonly TelegramSender $sender,
        private readonly ConversationStateRepository $states,
    ) {
    }

    public function handle(UpdateContext $context, array $stateContext): void
    {
        if ($context->chatId === null || $context->userId === null) {
            return;
        }

        $key = trim($context->text);

        if (preg_match('/^[a-zA-Z0-9_]{2,64}$/', $key) !== 1) {
            $this->sender->sendMessage(
                $context->chatId,
                'کلید نامعتبر است. فقط حروف/عدد انگلیسی و _ مجاز است، دوباره ارسال کنید.',
            );

            return;
        }

        $this->states->set($context->userId, 'texts:awaiting_value', ['key' => $key]);
        $this->sender->sendMessage($context->chatId, "حالا متن مربوط به «{$key}» را ارسال کنید.");
    }
}
