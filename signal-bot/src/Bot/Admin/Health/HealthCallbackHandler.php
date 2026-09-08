<?php

declare(strict_types=1);

namespace App\Bot\Admin\Health;

use App\Bot\CallbackHandlerInterface;
use App\Bot\Keyboard;
use App\Bot\UpdateContext;
use App\Database\Repositories\ExchangeRepository;
use App\Database\Repositories\LogRepository;
use App\Database\Repositories\MarketDataRepository;
use App\Database\Repositories\ScannerRunRepository;
use App\Database\Repositories\SettingsRepository;
use App\Database\Repositories\SignalRepository;
use App\Telegram\TelegramSender;
use Throwable;

/**
 * "❤️ سلامت سیستم" (spec #23). There is no direct process handle from the
 * webhook process to the worker daemon, so "is the worker running" is
 * inferred honestly from freshness: the last scanner_runs row finishing
 * within ~2x the configured scan interval means it is almost certainly
 * still alive; older than that means it has stalled or crashed and
 * whatever supervises it (systemd/supervisor) hasn't restarted it yet.
 */
final class HealthCallbackHandler implements CallbackHandlerInterface
{
    public function __construct(
        private readonly ScannerRunRepository $scannerRuns,
        private readonly ExchangeRepository $exchanges,
        private readonly MarketDataRepository $marketData,
        private readonly SignalRepository $signals,
        private readonly LogRepository $logs,
        private readonly SettingsRepository $settings,
        private readonly TelegramSender $sender,
    ) {
    }

    public function requiresAdmin(): bool
    {
        return true;
    }

    public function supports(string $callbackData): bool
    {
        return $callbackData === 'health:show';
    }

    public function handle(UpdateContext $context): void
    {
        if ($context->chatId === null) {
            return;
        }

        $lines = ['❤️ سلامت سیستم', ''];
        $lines[] = 'Worker: ' . $this->workerStatusIcon();
        $lines[] = 'Telegram API: ' . $this->telegramStatusIcon();

        foreach ($this->exchanges->all() as $exchange) {
            $icon = match ($exchange['status']) {
                'online' => '🟢',
                'degraded' => '🟡',
                default => '🔴',
            };
            $enabledNote = ((int) $exchange['enabled']) === 1 ? '' : ' (غیرفعال)';
            $lines[] = "{$exchange['name']}: {$icon}{$enabledNote}";
        }

        $lines[] = '';
        $lines[] = 'نمادهای فعال (Universe): ' . $this->marketData->universeCount();
        $lines[] = 'سیگنال‌های فعال: ' . $this->signals->activeCount();
        $lines[] = 'سیگنال‌های امروز: ' . $this->signals->todayCount();
        $lines[] = 'خطاهای امروز: ' . $this->logs->errorsToday();
        $lines[] = 'حالت: ' . (string) $this->settings->get('mode', 'DRY_RUN');

        $latestRun = $this->scannerRuns->latest();
        $lines[] = 'آخرین اسکن: ' . ($latestRun['finished_at'] ?? $latestRun['started_at'] ?? '-');

        $keyboard = (new Keyboard())
            ->button('🔄 بروزرسانی', 'health:show')
            ->button('⬅️ بازگشت به منو', 'menu:root');

        $text = implode("\n", $lines);

        if ($context->callbackMessageId !== null) {
            $this->sender->editMessageText($context->chatId, $context->callbackMessageId, $text, [], $keyboard->toArray());
        } else {
            $this->sender->sendMessage($context->chatId, $text, [], null, $keyboard->toArray());
        }

        if ($context->callbackQueryId !== null) {
            $this->sender->answerCallbackQuery($context->callbackQueryId);
        }
    }

    private function workerStatusIcon(): string
    {
        $latest = $this->scannerRuns->latest();
        if ($latest === null || $latest['finished_at'] === null) {
            return '🔴 هرگز اجرا نشده';
        }

        $intervalSeconds = (int) $this->settings->get('scan_interval_seconds', 60);
        $ageSeconds = time() - strtotime((string) $latest['finished_at']);

        return $ageSeconds <= max(120, $intervalSeconds * 2) ? '🟢 Running' : '🔴 متوقف/کرش‌شده به نظر می‌رسد';
    }

    private function telegramStatusIcon(): string
    {
        try {
            $this->sender->getMe();

            return '🟢';
        } catch (Throwable) {
            return '🔴';
        }
    }
}
