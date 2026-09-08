<?php

declare(strict_types=1);

namespace App\Bot\Admin\Exchanges;

use App\Bot\CallbackHandlerInterface;
use App\Bot\Keyboard;
use App\Bot\UpdateContext;
use App\Database\Repositories\ExchangeRepository;
use App\Telegram\TelegramSender;

/**
 * "📡 صرافی‌ها": read-only status (adapters themselves are wired in
 * config/exchanges.php per spec #4 — enabling/disabling which adapters
 * exist is a deploy-time decision) plus an enabled/disabled toggle per
 * exchange row, which the scanner reads via ExchangeManager::isEnabled().
 */
final class ExchangesCallbackHandler implements CallbackHandlerInterface
{
    public function __construct(
        private readonly ExchangeRepository $exchanges,
        private readonly TelegramSender $sender,
    ) {
    }

    public function requiresAdmin(): bool
    {
        return true;
    }

    public function supports(string $callbackData): bool
    {
        return str_starts_with($callbackData, 'exchanges:');
    }

    public function handle(UpdateContext $context): void
    {
        $parts = explode(':', (string) $context->callbackData);
        $action = $parts[1] ?? 'list';

        match ($action) {
            'toggle' => $this->toggle((string) ($parts[2] ?? '')),
            default => null,
        };

        $this->renderList($context);

        if ($context->callbackQueryId !== null) {
            $this->sender->answerCallbackQuery($context->callbackQueryId);
        }
    }

    private function toggle(string $code): void
    {
        $exchange = $this->exchanges->findByCode($code);
        if ($exchange === null) {
            return;
        }

        $this->exchanges->setEnabled($code, ((int) $exchange['enabled']) !== 1);
    }

    private function renderList(UpdateContext $context): void
    {
        if ($context->chatId === null) {
            return;
        }

        $keyboard = new Keyboard();
        $lines = ["📡 صرافی‌ها:\n"];

        foreach ($this->exchanges->all() as $exchange) {
            $statusIcon = match ($exchange['status']) {
                'online' => '🟢',
                'degraded' => '🟡',
                default => '🔴',
            };
            $enabledIcon = ((int) $exchange['enabled']) === 1 ? '✅' : '⛔️';
            $lines[] = "{$statusIcon} {$exchange['name']} — {$enabledIcon}" . (((int) $exchange['enabled']) === 1 ? ' فعال' : ' غیرفعال');

            $keyboard->button(
                (((int) $exchange['enabled']) === 1 ? '⛔️ غیرفعال کردن ' : '✅ فعال کردن ') . $exchange['name'],
                "exchanges:toggle:{$exchange['code']}",
            );
        }

        $keyboard->button('⬅️ بازگشت به منو', 'menu:root');

        $text = implode("\n", $lines);

        if ($context->callbackMessageId !== null) {
            $this->sender->editMessageText($context->chatId, $context->callbackMessageId, $text, [], $keyboard->toArray());

            return;
        }

        $this->sender->sendMessage($context->chatId, $text, [], null, $keyboard->toArray());
    }
}
