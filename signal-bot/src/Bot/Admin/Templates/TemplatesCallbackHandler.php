<?php

declare(strict_types=1);

namespace App\Bot\Admin\Templates;

use App\Bot\CallbackHandlerInterface;
use App\Bot\Keyboard;
use App\Bot\UpdateContext;
use App\Database\Repositories\ConversationStateRepository;
use App\Database\Repositories\SignalTemplateRepository;
use App\Telegram\ReplyParameters;
use App\Telegram\TelegramSender;

/**
 * "📝 قالب‌های سیگنال" (spec #17): channels pick a template by id
 * (falling back to is_default); editing here changes the raw template
 * text + entities, same never-Markdown approach as the Text Manager, so a
 * placeholder wrapped in bold/spoiler/premium-emoji in the editor keeps
 * that formatting once real values are substituted in (TelegramFormatter).
 */
final class TemplatesCallbackHandler implements CallbackHandlerInterface
{
    public function __construct(
        private readonly SignalTemplateRepository $templates,
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
        return str_starts_with($callbackData, 'templates:');
    }

    public function handle(UpdateContext $context): void
    {
        $parts = explode(':', (string) $context->callbackData, 3);
        $action = $parts[1] ?? 'list';
        $id = isset($parts[2]) ? (int) $parts[2] : null;

        match ($action) {
            'list' => $this->renderList($context),
            'view' => $this->renderDetail($context, (int) $id),
            'add' => $this->promptAdd($context),
            'edit' => $this->promptEdit($context, (int) $id),
            'setdefault' => $this->setDefault($context, (int) $id),
            'delete' => $this->delete($context, (int) $id),
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
        foreach ($this->templates->all() as $template) {
            $label = ($template['is_default'] ? '⭐️ ' : '') . $template['name'];
            $keyboard->button($label, "templates:view:{$template['id']}");
        }
        $keyboard->button('➕ افزودن قالب جدید', 'templates:add');
        $keyboard->button('⬅️ بازگشت به منو', 'menu:root');

        $this->send($context, 'قالب‌های سیگنال — یک قالب را انتخاب کنید:', $keyboard);
    }

    private function renderDetail(UpdateContext $context, int $id): void
    {
        $template = $this->templates->find($id);
        if ($template === null || $context->chatId === null) {
            return;
        }

        $keyboard = (new Keyboard())
            ->button('✏️ ویرایش', "templates:edit:{$id}")
            ->button('⭐️ پیش‌فرض کردن', "templates:setdefault:{$id}");

        if (!$template['is_default']) {
            $keyboard->button('🗑 حذف', "templates:delete:{$id}");
        }
        $keyboard->button('⬅️ بازگشت', 'templates:list');

        $placeholders = "\n\nPlaceholderها: {direction} {symbol} {exchange} {entry} {stop_loss} {tp1} {tp2} {tp3} {risk_reward} {score} {timeframe} {reasons}";

        $this->sender->sendMessage(
            $context->chatId,
            "📝 {$template['name']}" . ($template['is_default'] ? ' (پیش‌فرض)' : '') . "\n\n" . $template['text'] . $placeholders,
            $template['entities'],
            ReplyParameters::fromStored($template),
            $keyboard->toArray(),
        );
    }

    private function promptAdd(UpdateContext $context): void
    {
        if ($context->userId === null) {
            return;
        }

        $this->states->set($context->userId, 'templates:awaiting_new_name');
        $this->send($context, 'نام قالب جدید را وارد کنید.', null);
    }

    private function promptEdit(UpdateContext $context, int $id): void
    {
        if ($context->userId === null || $this->templates->find($id) === null) {
            return;
        }

        $this->states->set($context->userId, 'templates:awaiting_value', ['id' => $id]);
        $this->send(
            $context,
            "متن جدید قالب را ارسال کنید. از Placeholderهای {symbol}، {entry} و... استفاده کنید؛ فرمت‌بندی و ایموجی پرمیوم حفظ می‌شود.",
            null,
        );
    }

    private function setDefault(UpdateContext $context, int $id): void
    {
        $this->templates->makeDefault($id);
        $this->renderDetail($context, $id);
    }

    private function delete(UpdateContext $context, int $id): void
    {
        $this->templates->delete($id);
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
