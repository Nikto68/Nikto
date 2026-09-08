<?php

declare(strict_types=1);

namespace App\Bot\Admin;

use App\Bot\CallbackHandlerInterface;
use App\Bot\CommandHandlerInterface;
use App\Bot\Keyboard;
use App\Bot\UpdateContext;
use App\Telegram\TelegramSender;

/**
 * The "⚙️ مدیریت" root menu (spec #21). Every section below is its own
 * small CallbackHandler — this class only renders the root and reacts to
 * "menu:root"; it never grows a branch per section.
 */
final class AdminHandler implements CommandHandlerInterface, CallbackHandlerInterface
{
    public function __construct(
        private readonly TelegramSender $sender,
    ) {
    }

    public function requiresAdmin(): bool
    {
        return true;
    }

    public function supports(string $callbackData): bool
    {
        return $callbackData === 'menu:root';
    }

    public function handle(UpdateContext $context): void
    {
        if ($context->chatId === null) {
            return;
        }

        $keyboard = (new Keyboard())
            ->button('📡 صرافی‌ها', 'exchanges:list')
            ->button('📺 کانال‌ها', 'channels:list')
            ->button('📊 اسکنر و سیگنال', 'settings:list')
            ->button('📈 اندیکاتورها', 'indicators:list')
            ->button('🧠 استراتژی‌ها', 'strategies:list')
            ->button('📝 قالب‌های سیگنال', 'templates:list')
            ->button('✏️ متن‌های ربات', 'texts:list')
            ->button('🧪 تست سیگنال', 'testsignal:start')
            ->button('📜 تاریخچه سیگنال‌ها', 'signals:history')
            ->button('📋 لاگ‌ها', 'logs:list')
            ->button('❤️ سلامت سیستم', 'health:show');

        $text = "⚙️ پنل مدیریت\n\nیک بخش را انتخاب کنید:";

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
