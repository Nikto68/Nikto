<?php

declare(strict_types=1);

namespace App\Bot\Admin\Settings;

use App\Bot\StateHandlerInterface;
use App\Bot\UpdateContext;
use App\Database\Repositories\ConversationStateRepository;
use App\Database\Repositories\SettingsRepository;
use App\Telegram\TelegramSender;

final class SettingsEditStateHandler implements StateHandlerInterface
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly TelegramSender $sender,
        private readonly ConversationStateRepository $states,
    ) {
    }

    public function handle(UpdateContext $context, array $stateContext): void
    {
        if ($context->chatId === null || $context->userId === null) {
            return;
        }

        $this->states->clear($context->userId);

        $key = (string) ($stateContext['key'] ?? '');
        $type = (string) ($stateContext['type'] ?? 'string');
        $raw = trim($context->text);

        if (!isset(SettingsCallbackHandler::EDITABLE[$key])) {
            return;
        }

        [$parsed, $error] = $this->parse($raw, $type);
        if ($error !== null) {
            $this->sender->sendMessage($context->chatId, $error);

            return;
        }

        $this->settings->set($key, is_array($parsed) ? json_encode($parsed, JSON_THROW_ON_ERROR) : $parsed, $type === 'timeframes' ? 'json' : $type, $context->userId);
        $this->sender->sendMessage($context->chatId, 'تنظیمات بروزرسانی شد.');
    }

    /**
     * @return array{0: mixed, 1: ?string}
     */
    private function parse(string $raw, string $type): array
    {
        return match ($type) {
            'int' => filter_var($raw, FILTER_VALIDATE_INT) !== false
                ? [(int) $raw, null]
                : [null, 'مقدار باید یک عدد صحیح باشد.'],
            'float' => is_numeric($raw)
                ? [(float) $raw, null]
                : [null, 'مقدار باید یک عدد باشد.'],
            'timeframes' => $this->parseTimeframes($raw),
            default => [$raw, null],
        };
    }

    /**
     * @return array{0: mixed, 1: ?string}
     */
    private function parseTimeframes(string $raw): array
    {
        $items = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $v): bool => $v !== ''));

        foreach ($items as $item) {
            if (preg_match('/^\d+[mhdw]$/i', $item) !== 1) {
                return [null, "«{$item}» یک تایم‌فریم معتبر نیست (مثال درست: 15m، 1h، 4h، 1d)."];
            }
        }

        return $items === [] ? [null, 'حداقل یک تایم‌فریم وارد کنید.'] : [$items, null];
    }
}
