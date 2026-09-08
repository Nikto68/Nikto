<?php

declare(strict_types=1);

namespace App\Bot\Admin\Signals;

use App\Bot\CallbackHandlerInterface;
use App\Bot\UpdateContext;
use App\Database\Repositories\ConversationStateRepository;
use App\Telegram\TelegramSender;

/**
 * "🧪 Test Signal" (spec #39) entry point — prompts for a symbol, the
 * actual pipeline run happens in TestSignalStateHandler once it arrives.
 */
final class TestSignalCallbackHandler implements CallbackHandlerInterface
{
    public function __construct(
        private readonly TelegramSender $sender,
        private readonly ConversationStateRepository $states,
    ) {
    }

    public function requiresAdmin(): bool
    {
        return true;
    }

    public function supports(string $callbackData): bool
    {
        return $callbackData === 'testsignal:start';
    }

    public function handle(UpdateContext $context): void
    {
        if ($context->chatId === null || $context->userId === null) {
            return;
        }

        $this->states->set($context->userId, 'testsignal:awaiting_symbol');
        $this->sender->sendMessage(
            $context->chatId,
            "نماد را ارسال کنید (مثلاً BTCUSDT یا binance:BTCUSDT).\n\nپایپ‌لاین کامل (اندیکاتورها، نواحی، Order Block، FVG و استراتژی‌های فعال) روی این نماد اجرا می‌شود اما هیچ سیگنالی واقعاً ارسال نخواهد شد.",
        );

        if ($context->callbackQueryId !== null) {
            $this->sender->answerCallbackQuery($context->callbackQueryId);
        }
    }
}
