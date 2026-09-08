<?php

declare(strict_types=1);

namespace App\Bot\Admin\Signals;

use App\Bot\CallbackHandlerInterface;
use App\Bot\Keyboard;
use App\Bot\UpdateContext;
use App\Database\Repositories\SignalRepository;
use App\Telegram\TelegramSender;

/**
 * "📜 تاریخچه سیگنال‌ها" (spec #38): the most recent signals with their
 * outcome status. Filters by status via the same callback prefix.
 */
final class SignalHistoryCallbackHandler implements CallbackHandlerInterface
{
    private const STATUS_ICONS = [
        'new' => '🆕', 'active' => '🟢', 'closed' => '✅', 'cancelled' => '⚪️', 'invalidated' => '❌',
    ];

    public function __construct(
        private readonly SignalRepository $signals,
        private readonly TelegramSender $sender,
    ) {
    }

    public function requiresAdmin(): bool
    {
        return true;
    }

    public function supports(string $callbackData): bool
    {
        return str_starts_with($callbackData, 'signals:history');
    }

    public function handle(UpdateContext $context): void
    {
        $parts = explode(':', (string) $context->callbackData, 3);
        $status = $parts[2] ?? null;

        $this->render($context, $status === 'all' ? null : $status);

        if ($context->callbackQueryId !== null) {
            $this->sender->answerCallbackQuery($context->callbackQueryId);
        }
    }

    private function render(UpdateContext $context, ?string $status): void
    {
        if ($context->chatId === null) {
            return;
        }

        $signals = $this->signals->history(15, $status);

        $lines = ['📜 تاریخچه سیگنال‌ها' . ($status !== null ? " ({$status})" : '') . "\n"];
        if ($signals === []) {
            $lines[] = 'سیگنالی یافت نشد.';
        }

        foreach ($signals as $signal) {
            $icon = self::STATUS_ICONS[$signal['status']] ?? '•';
            $lines[] = sprintf(
                '%s %s %s | %s | Entry: %s | امتیاز: %d | %s',
                $icon,
                $signal['direction'],
                $signal['symbol'],
                $signal['exchange_code'],
                $signal['entry'],
                (int) $signal['score'],
                $signal['created_at'],
            );
        }

        $keyboard = (new Keyboard())
            ->row([
                Keyboard::btn('همه', 'signals:history:all'),
                Keyboard::btn('فعال', 'signals:history:active'),
                Keyboard::btn('بسته‌شده', 'signals:history:closed'),
            ])
            ->button('⬅️ بازگشت به منو', 'menu:root');

        $text = implode("\n", $lines);

        if ($context->callbackMessageId !== null) {
            $this->sender->editMessageText($context->chatId, $context->callbackMessageId, $text, [], $keyboard->toArray());

            return;
        }

        $this->sender->sendMessage($context->chatId, $text, [], null, $keyboard->toArray());
    }
}
