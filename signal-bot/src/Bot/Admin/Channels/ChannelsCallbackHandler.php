<?php

declare(strict_types=1);

namespace App\Bot\Admin\Channels;

use App\Bot\CallbackHandlerInterface;
use App\Bot\Keyboard;
use App\Bot\UpdateContext;
use App\Database\Repositories\ChannelRepository;
use App\Database\Repositories\ConversationStateRepository;
use App\Telegram\ChannelManager;
use App\Telegram\TelegramSender;

/**
 * "📺 کانال‌ها" section: list / add / toggle / remove / refresh-permissions,
 * and per-channel filters (minimum score, direction). Every mutation goes
 * through ChannelRepository / ChannelManager — this class only renders
 * keyboards and reacts to button presses.
 */
final class ChannelsCallbackHandler implements CallbackHandlerInterface
{
    public function __construct(
        private readonly ChannelRepository $channels,
        private readonly ChannelManager $channelManager,
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
        return str_starts_with($callbackData, 'channels:');
    }

    public function handle(UpdateContext $context): void
    {
        $data = (string) $context->callbackData;
        $parts = explode(':', $data);
        $action = $parts[1] ?? 'list';

        match ($action) {
            'list' => $this->renderList($context),
            'add' => $this->promptAdd($context),
            'view' => $this->renderDetail($context, (int) ($parts[2] ?? 0)),
            'toggle' => $this->toggle($context, (int) ($parts[2] ?? 0)),
            'refresh' => $this->refresh($context, (int) ($parts[2] ?? 0)),
            'remove_confirm' => $this->confirmRemove($context, (int) ($parts[2] ?? 0)),
            'remove' => $this->remove($context, (int) ($parts[2] ?? 0)),
            'setdirection' => $this->setDirection($context, (int) ($parts[2] ?? 0), $parts[3] ?? 'ANY'),
            'setscore' => $this->promptSetScore($context, (int) ($parts[2] ?? 0)),
            default => null,
        };

        if ($context->callbackQueryId !== null) {
            $this->sender->answerCallbackQuery($context->callbackQueryId);
        }
    }

    public function renderList(UpdateContext $context): void
    {
        if ($context->chatId === null) {
            return;
        }

        $channels = $this->channels->all();
        $keyboard = new Keyboard();

        foreach ($channels as $channel) {
            $status = ((int) $channel['enabled']) === 1 ? '🟢' : '⚪️';
            $label = $status . ' ' . ($channel['title'] ?? $channel['username'] ?? $channel['chat_id']);
            $keyboard->button($label, "channels:view:{$channel['id']}");
        }

        $keyboard->button('➕ افزودن کانال', 'channels:add');

        $text = $channels === []
            ? 'هنوز کانالی اضافه نشده است.'
            : 'کانال‌های ثبت‌شده:';

        $this->send($context, $text, $keyboard);
    }

    private function promptAdd(UpdateContext $context): void
    {
        if ($context->userId === null || $context->chatId === null) {
            return;
        }

        $this->states->set($context->userId, 'channels:awaiting_identifier');
        $this->send(
            $context,
            "آیدی عددی کانال (مثلاً -1001234567890) یا یوزرنیم آن (مثلاً @mychannel) را ارسال کنید.\n\nربات باید از قبل در آن کانال به عنوان ادمین با دسترسی ارسال پیام اضافه شده باشد.",
            null,
        );
    }

    private function renderDetail(UpdateContext $context, int $id): void
    {
        $channel = $this->channels->find($id);
        if ($channel === null || $context->chatId === null) {
            return;
        }

        $enabledLabel = ((int) $channel['enabled']) === 1 ? 'غیرفعال کردن' : 'فعال کردن';
        $permissionLabel = ((int) $channel['bot_can_post']) === 1 ? '✅ مجاز به ارسال' : '⛔️ عدم دسترسی ارسال';

        $text = sprintf(
            "📺 %s\nChat ID: %s\nنوع: %s\nحداقل امتیاز سیگنال: %d\nفیلتر جهت: %s\nوضعیت ربات: %s",
            $channel['title'] ?? $channel['username'] ?? '-',
            $channel['chat_id'],
            $channel['type'],
            (int) $channel['minimum_score'],
            $channel['direction_filter'],
            $permissionLabel,
        );

        $keyboard = (new Keyboard())
            ->button($enabledLabel, "channels:toggle:{$id}")
            ->button('🎯 تنظیم حداقل امتیاز', "channels:setscore:{$id}")
            ->row([
                Keyboard::btn('ANY', "channels:setdirection:{$id}:ANY"),
                Keyboard::btn('LONG', "channels:setdirection:{$id}:LONG"),
                Keyboard::btn('SHORT', "channels:setdirection:{$id}:SHORT"),
            ])
            ->button('🔄 بررسی مجدد دسترسی ربات', "channels:refresh:{$id}")
            ->button('🗑 حذف کانال', "channels:remove_confirm:{$id}")
            ->button('⬅️ بازگشت', 'channels:list');

        $this->send($context, $text, $keyboard);
    }

    private function toggle(UpdateContext $context, int $id): void
    {
        $channel = $this->channels->find($id);
        if ($channel === null) {
            return;
        }

        $this->channels->setEnabled($id, ((int) $channel['enabled']) !== 1);
        $this->renderDetail($context, $id);
    }

    private function refresh(UpdateContext $context, int $id): void
    {
        $this->channelManager->refreshPermissions($id);
        $this->renderDetail($context, $id);
    }

    private function confirmRemove(UpdateContext $context, int $id): void
    {
        $channel = $this->channels->find($id);
        if ($channel === null || $context->chatId === null) {
            return;
        }

        $keyboard = (new Keyboard())
            ->button('✅ بله، حذف شود', "channels:remove:{$id}")
            ->button('❌ انصراف', "channels:view:{$id}");

        $this->send($context, 'از حذف این کانال مطمئن هستید؟', $keyboard);
    }

    private function remove(UpdateContext $context, int $id): void
    {
        $this->channels->delete($id);
        $this->renderList($context);
    }

    private function setDirection(UpdateContext $context, int $id, string $direction): void
    {
        if (!in_array($direction, ['ANY', 'LONG', 'SHORT'], true)) {
            return;
        }

        $this->channels->update($id, ['direction_filter' => $direction]);
        $this->renderDetail($context, $id);
    }

    private function promptSetScore(UpdateContext $context, int $id): void
    {
        if ($context->userId === null) {
            return;
        }

        $this->states->set($context->userId, 'channels:awaiting_min_score', ['channel_id' => $id]);
        $this->send($context, 'حداقل امتیاز سیگنال برای این کانال را به‌صورت عدد (۰ تا ۱۰۰) ارسال کنید.', null);
    }

    private function send(UpdateContext $context, string $text, ?Keyboard $keyboard): void
    {
        if ($context->chatId === null) {
            return;
        }

        if ($context->callbackMessageId !== null) {
            $this->sender->editMessageText($context->chatId, $context->callbackMessageId, $text, [], $keyboard?->toArray());

            return;
        }

        $this->sender->sendMessage($context->chatId, $text, [], null, $keyboard?->toArray());
    }
}
