<?php

declare(strict_types=1);

namespace App\Bot\Admin\Indicators;

use App\Bot\CallbackHandlerInterface;
use App\Bot\Keyboard;
use App\Bot\UpdateContext;
use App\Indicators\IndicatorManager;
use App\Telegram\TelegramSender;

/**
 * "📈 اندیکاتورها": read-only — the plugin registry itself (spec #7) is
 * code (IndicatorManager::builtIns()), and per-indicator params are set
 * per strategy in its `config` JSON, not globally, so there is nothing to
 * edit here — this is purely "what is available to reference in a
 * strategy's config".
 */
final class IndicatorsCallbackHandler implements CallbackHandlerInterface
{
    public function __construct(
        private readonly IndicatorManager $indicators,
        private readonly TelegramSender $sender,
    ) {
    }

    public function requiresAdmin(): bool
    {
        return true;
    }

    public function supports(string $callbackData): bool
    {
        return $callbackData === 'indicators:list';
    }

    public function handle(UpdateContext $context): void
    {
        if ($context->chatId === null) {
            return;
        }

        $names = $this->indicators->names();
        $text = "📈 اندیکاتورهای موجود:\n\n" . implode("\n", array_map(static fn (string $n): string => "• {$n}", $names))
            . "\n\nپارامترهای هر اندیکاتور (مثل period) در تنظیمات هر استراتژی مشخص می‌شود.";

        $keyboard = (new Keyboard())->button('⬅️ بازگشت به منو', 'menu:root');

        if ($context->callbackMessageId !== null) {
            $this->sender->editMessageText($context->chatId, $context->callbackMessageId, $text, [], $keyboard->toArray());

            if ($context->callbackQueryId !== null) {
                $this->sender->answerCallbackQuery($context->callbackQueryId);
            }

            return;
        }

        $this->sender->sendMessage($context->chatId, $text, [], null, $keyboard->toArray());
    }
}
