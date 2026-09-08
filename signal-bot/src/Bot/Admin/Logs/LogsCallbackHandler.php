<?php

declare(strict_types=1);

namespace App\Bot\Admin\Logs;

use App\Bot\CallbackHandlerInterface;
use App\Bot\Keyboard;
use App\Bot\UpdateContext;
use App\Database\Repositories\LogRepository;
use App\Telegram\TelegramSender;

/**
 * "📋 لاگ‌ها" (spec #24): the most recent ERROR-and-above records, mirrored
 * into the DB by DatabaseLogHandler. Full detail (every level, stack
 * traces) always stays in storage/logs/*.log — this is a quick "is
 * anything on fire" view without SSH access.
 */
final class LogsCallbackHandler implements CallbackHandlerInterface
{
    public function __construct(
        private readonly LogRepository $logs,
        private readonly TelegramSender $sender,
    ) {
    }

    public function requiresAdmin(): bool
    {
        return true;
    }

    public function supports(string $callbackData): bool
    {
        return str_starts_with($callbackData, 'logs:');
    }

    public function handle(UpdateContext $context): void
    {
        if ($context->chatId === null) {
            return;
        }

        $rows = $this->logs->recent(10);

        $lines = ["📋 آخرین خطاها\n"];
        if ($rows === []) {
            $lines[] = 'در حال حاضر خطایی ثبت نشده است. ✅';
        }

        foreach ($rows as $row) {
            $lines[] = sprintf('[%s] %s.%s: %s', $row['created_at'], $row['component'], $row['level'], mb_substr($row['message'], 0, 200));
        }

        $keyboard = (new Keyboard())->button('⬅️ بازگشت به منو', 'menu:root');
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
}
