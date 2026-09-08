<?php

declare(strict_types=1);

namespace App\Bot\Commands;

use App\Bot\CommandHandlerInterface;
use App\Bot\UpdateContext;
use App\Database\Repositories\TextFormatRepository;
use App\Telegram\ReplyParameters;
use App\Telegram\TelegramSender;

final class StartCommand implements CommandHandlerInterface
{
    public function __construct(
        private readonly TextFormatRepository $texts,
        private readonly TelegramSender $sender,
    ) {
    }

    public function requiresAdmin(): bool
    {
        return false;
    }

    public function handle(UpdateContext $context): void
    {
        if ($context->chatId === null) {
            return;
        }

        $text = $this->texts->find('welcome');

        $this->sender->sendMessage(
            $context->chatId,
            $text['text'] ?? 'به ربات سیگنال خوش آمدید.',
            $text['entities'] ?? [],
            $text === null ? null : ReplyParameters::fromStored($text),
        );
    }
}
