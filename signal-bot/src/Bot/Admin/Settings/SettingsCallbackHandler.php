<?php

declare(strict_types=1);

namespace App\Bot\Admin\Settings;

use App\Bot\CallbackHandlerInterface;
use App\Bot\Keyboard;
use App\Bot\UpdateContext;
use App\Database\Repositories\ConversationStateRepository;
use App\Database\Repositories\SettingsRepository;
use App\Telegram\TelegramSender;

/**
 * "📊 اسکنر و سیگنال": Auto Signal mode (spec #40 LIVE/DRY_RUN), scanner
 * thresholds (spec #22), signal score/cooldown, and default timeframes
 * (spec #6) — all bot_settings rows, all editable here instead of only
 * from .env. One consolidated menu rather than four separate ones (Auto
 * Signal / Scanner / Signal Settings / Timeframes / Symbol Limit) since
 * they are all the same underlying key-value store.
 */
final class SettingsCallbackHandler implements CallbackHandlerInterface
{
    /**
     * key => [label, type, default]
     */
    public const EDITABLE = [
        'scan_symbol_limit' => ['🔢 تعداد نماد (Top N)', 'int', 200],
        'scan_interval_seconds' => ['⏱ فاصله اسکن (ثانیه)', 'int', 60],
        'min_volume' => ['💰 حداقل حجم', 'float', 100000.0],
        'min_liquidity' => ['💧 حداقل نقدشوندگی', 'float', 50000.0],
        'max_spread_percent' => ['↔️ حداکثر اسپرد (٪)', 'float', 1.0],
        'min_signal_score' => ['🎯 حداقل امتیاز سیگنال', 'int', 75],
        'signal_cooldown_seconds' => ['🧊 کول‌داون سیگنال (ثانیه)', 'int', 1800],
        'default_timeframes' => ['⏱ تایم‌فریم‌های پیش‌فرض (با , جدا کنید)', 'timeframes', ['15m', '1h', '4h']],
    ];

    public function __construct(
        private readonly SettingsRepository $settings,
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
        return str_starts_with($callbackData, 'settings:');
    }

    public function handle(UpdateContext $context): void
    {
        $parts = explode(':', (string) $context->callbackData, 3);
        $action = $parts[1] ?? 'list';
        $key = $parts[2] ?? null;

        match ($action) {
            'togglemode' => $this->toggleMode(),
            'edit' => $this->promptEdit($context, (string) $key),
            default => null,
        };

        if ($action !== 'edit') {
            $this->renderList($context);
        }

        if ($context->callbackQueryId !== null) {
            $this->sender->answerCallbackQuery($context->callbackQueryId);
        }
    }

    private function toggleMode(): void
    {
        $current = (string) $this->settings->get('mode', 'DRY_RUN');
        $this->settings->set('mode', $current === 'LIVE' ? 'DRY_RUN' : 'LIVE', 'string');
    }

    public function renderList(UpdateContext $context): void
    {
        if ($context->chatId === null) {
            return;
        }

        $mode = (string) $this->settings->get('mode', 'DRY_RUN');
        $lines = ["📊 تنظیمات اسکنر و سیگنال\n", ($mode === 'LIVE' ? '🟢 حالت: LIVE (ارسال واقعی)' : '🟡 حالت: DRY_RUN (بدون ارسال واقعی)')];

        $keyboard = (new Keyboard())->button(
            $mode === 'LIVE' ? '🟡 تغییر به DRY_RUN' : '🟢 تغییر به LIVE',
            'settings:togglemode',
        );

        foreach (self::EDITABLE as $key => [$label, $type, $default]) {
            $value = $this->settings->get($key, $default);
            $display = is_array($value) ? implode(', ', $value) : (string) $value;
            $lines[] = "{$label}: {$display}";
            $keyboard->button("✏️ {$label}", "settings:edit:{$key}");
        }

        $keyboard->button('⬅️ بازگشت به منو', 'menu:root');

        $text = implode("\n", $lines);

        if ($context->callbackMessageId !== null) {
            $this->sender->editMessageText($context->chatId, $context->callbackMessageId, $text, [], $keyboard->toArray());

            return;
        }

        $this->sender->sendMessage($context->chatId, $text, [], null, $keyboard->toArray());
    }

    private function promptEdit(UpdateContext $context, string $key): void
    {
        if (!isset(self::EDITABLE[$key]) || $context->userId === null || $context->chatId === null) {
            return;
        }

        [$label, $type] = self::EDITABLE[$key];

        $this->states->set($context->userId, 'settings:awaiting_value', ['key' => $key, 'type' => $type]);

        $hint = match ($type) {
            'int' => 'یک عدد صحیح ارسال کنید.',
            'float' => 'یک عدد (اعشاری مجاز) ارسال کنید.',
            'timeframes' => 'تایم‌فریم‌ها را با کاما جدا کنید، مثلاً: 15m,1h,4h',
            default => 'مقدار جدید را ارسال کنید.',
        };

        if ($context->callbackMessageId !== null) {
            $this->sender->editMessageText($context->chatId, $context->callbackMessageId, "مقدار جدید برای «{$label}»:\n{$hint}");

            return;
        }

        $this->sender->sendMessage($context->chatId, "مقدار جدید برای «{$label}»:\n{$hint}");
    }
}
