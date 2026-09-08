<?php

declare(strict_types=1);

namespace App\Bot\Admin\Channels;

use App\Bot\StateHandlerInterface;
use App\Bot\UpdateContext;
use App\Database\Repositories\ConversationStateRepository;
use App\Telegram\ChannelManager;
use App\Telegram\TelegramSender;

final class AddChannelStateHandler implements StateHandlerInterface
{
    public function __construct(
        private readonly ChannelManager $channelManager,
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

        $result = $this->channelManager->addChannel($context->text, $context->userId);

        $this->sender->sendMessage($context->chatId, $result['message']);
    }
}
