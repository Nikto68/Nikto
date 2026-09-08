<?php

declare(strict_types=1);

namespace App\Bot\Admin\Texts;

use App\Bot\CallbackHandlerInterface;
use App\Bot\Keyboard;
use App\Bot\UpdateContext;
use App\Database\Repositories\ConversationStateRepository;
use App\Database\Repositories\TextFormatRepository;
use App\Telegram\ReplyParameters;
use App\Telegram\TelegramSender;

/**
 * "✏️ Text Manager" (spec #20): every user-facing string lives in
 * text_formats, never hard-coded in PHP, and is editable here by sending a
 * new message — text + entities (premium emoji included) are captured
 * exactly as Telegram sends them, never downgraded to Markdown.
 */
final class TextsCallbackHandler implements CallbackHandlerInterface
{
    public function __construct(
        private readonly TextFormatRepository $texts,
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
        return str_starts_with($callbackData, 'texts:');
    }

    public function handle(UpdateContext $context): void
    {
        $data = (string) $context->callbackData;
        $parts = explode(':', $data, 3);
        $action = $parts[1] ?? 'list';
        $key = $parts[2] ?? null;

        match ($action) {
            'list' => $this->renderList($context),
            'view' => $this->renderDetail($context, (string) $key),
            'edit' => $this->promptEdit($context, (string) $key),
            'add' => $this->promptAddKey($context),
            'delete_confirm' => $this->confirmDelete($context, (string) $key),
            'delete' => $this->delete($context, (string) $key),
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

        $keyboard = new Keyboard();
        foreach ($this->texts->all() as $row) {
            $keyboard->button($row['text_key'], "texts:view:{$row['text_key']}");
        }
        $keyboard->button('➕ افزودن متن جدید', 'texts:add');
        $keyboard->button('⬅️ بازگشت به منو', 'menu:root');

        $this->send($context, 'مدیریت متن‌های ربات — یک کلید را انتخاب کنید:', $keyboard);
    }

    private function renderDetail(UpdateContext $context, string $key): void
    {
        $row = $this->texts->find($key);
        if ($row === null || $context->chatId === null) {
            return;
        }

        $keyboard = (new Keyboard())
            ->button('✏️ ویرایش', "texts:edit:{$key}")
            ->button('🗑 حذف', "texts:delete_confirm:{$key}")
            ->button('⬅️ بازگشت', 'texts:list');

        $this->sender->sendMessage(
            (int) $context->chatId,
            "🔑 {$key}\n\n" . $row['text'],
            $row['entities'],
            ReplyParameters::fromStored($row),
            $keyboard->toArray(),
        );
    }

    private function promptEdit(UpdateContext $context, string $key): void
    {
        if ($context->userId === null) {
            return;
        }

        $this->states->set($context->userId, 'texts:awaiting_value', ['key' => $key]);
        $this->send(
            $context,
            "متن جدید برای «{$key}» را ارسال کنید.\n\nفرمت‌بندی (bold/italic/spoiler/ایموجی پرمیوم و ...) حفظ می‌شود. برای اتصال Quote/Reply، روی یک پیام Reply بزنید و سپس متن را ارسال کنید.",
            null,
        );
    }

    private function promptAddKey(UpdateContext $context): void
    {
        if ($context->userId === null) {
            return;
        }

        $this->states->set($context->userId, 'texts:awaiting_new_key');
        $this->send($context, 'کلید متن جدید را وارد کنید (فقط حروف/عدد انگلیسی و _، بدون فاصله).', null);
    }

    private function confirmDelete(UpdateContext $context, string $key): void
    {
        $keyboard = (new Keyboard())
            ->button('✅ بله، حذف شود', "texts:delete:{$key}")
            ->button('❌ انصراف', "texts:view:{$key}");

        $this->send($context, "متن «{$key}» حذف شود؟", $keyboard);
    }

    private function delete(UpdateContext $context, string $key): void
    {
        $this->texts->delete($key);
        $this->renderList($context);
    }

    private function send(UpdateContext $context, string $text, ?Keyboard $keyboard): void
    {
        if ($context->chatId === null) {
            return;
        }

        $this->sender->sendMessage($context->chatId, $text, [], null, $keyboard?->toArray());
    }
}
