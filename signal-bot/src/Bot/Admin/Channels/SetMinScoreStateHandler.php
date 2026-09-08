<?php

declare(strict_types=1);

namespace App\Bot\Admin\Channels;

use App\Bot\StateHandlerInterface;
use App\Bot\UpdateContext;
use App\Database\Repositories\ChannelRepository;
use App\Database\Repositories\ConversationStateRepository;
use App\Telegram\TelegramSender;

final class SetMinScoreStateHandler implements StateHandlerInterface
{
    public function __construct(
        private readonly ChannelRepository $channels,
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

        $channelId = (int) ($stateContext['channel_id'] ?? 0);
        $score = filter_var(trim($context->text), FILTER_VALIDATE_INT);

        if ($channelId === 0 || $score === false || $score < 0 || $score > 100) {
            $this->sender->sendMessage($context->chatId, 'مقدار نامعتبر است. لطفاً عددی بین ۰ تا ۱۰۰ ارسال کنید.');

            return;
        }

        $this->channels->update($channelId, ['minimum_score' => $score]);
        $this->sender->sendMessage($context->chatId, 'حداقل امتیاز بروزرسانی شد.');
    }
}
