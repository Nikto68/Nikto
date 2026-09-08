<?php

declare(strict_types=1);

namespace App\Bot\Admin\Strategies;

use App\Bot\CallbackHandlerInterface;
use App\Bot\Keyboard;
use App\Bot\UpdateContext;
use App\Database\Repositories\StrategyRepository;
use App\Strategy\StrategyRegistry;
use App\Telegram\TelegramSender;

/**
 * "🧠 استراتژی‌ها": lists whatever is registered in `strategies` (spec
 * #35/#36 — empty until real rules are supplied and a StrategyInterface
 * implementation is registered in StrategyRegistry) with an active/
 * inactive toggle. Toggling a strategy with no registered implementation
 * is harmless — StrategyEngine skips and logs a warning for it — but is
 * still called out here so an admin doesn't wonder why nothing happens.
 */
final class StrategiesCallbackHandler implements CallbackHandlerInterface
{
    public function __construct(
        private readonly StrategyRepository $strategies,
        private readonly StrategyRegistry $registry,
        private readonly TelegramSender $sender,
    ) {
    }

    public function requiresAdmin(): bool
    {
        return true;
    }

    public function supports(string $callbackData): bool
    {
        return str_starts_with($callbackData, 'strategies:');
    }

    public function handle(UpdateContext $context): void
    {
        $parts = explode(':', (string) $context->callbackData);
        $action = $parts[1] ?? 'list';

        if ($action === 'toggle' && isset($parts[2])) {
            $this->toggle($parts[2]);
        }

        $this->renderList($context);

        if ($context->callbackQueryId !== null) {
            $this->sender->answerCallbackQuery($context->callbackQueryId);
        }
    }

    private function toggle(string $code): void
    {
        $strategy = $this->strategies->findByCode($code);
        if ($strategy !== null) {
            $this->strategies->setActive($code, ((int) $strategy['is_active']) !== 1);
        }
    }

    private function renderList(UpdateContext $context): void
    {
        if ($context->chatId === null) {
            return;
        }

        $all = $this->strategies->all();
        $keyboard = new Keyboard();

        $lines = ["🧠 استراتژی‌ها\n"];

        if ($all === []) {
            $lines[] = 'هنوز هیچ استراتژی‌ای ثبت نشده است. طبق معماری، قوانین معاملاتی باید توسط شما مشخص و به‌صورت کلاس StrategyInterface اضافه شوند.';
        }

        foreach ($all as $strategy) {
            $isActive = ((int) $strategy['is_active']) === 1;
            $hasImplementation = $this->registry->has($strategy['code']);
            $icon = $isActive ? '🟢' : '⚪️';
            $implNote = $hasImplementation ? '' : ' (بدون پیاده‌سازی ثبت‌شده)';
            $lines[] = "{$icon} {$strategy['name']} — امتیاز حداقل: {$strategy['min_score']}{$implNote}";

            $keyboard->button(
                ($isActive ? '⚪️ غیرفعال کردن ' : '🟢 فعال کردن ') . $strategy['name'],
                "strategies:toggle:{$strategy['code']}",
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
