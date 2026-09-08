<?php

declare(strict_types=1);

namespace App\Bot;

/**
 * Tiny builder for Telegram inline_keyboard markup. Every admin menu in the
 * bot (channels, scanner, strategies, ...) builds its keyboard through
 * here instead of hand-assembling nested arrays.
 */
final class Keyboard
{
    /** @var array<int, array<int, array<string, string>>> */
    private array $rows = [];

    public function row(array $buttons): self
    {
        $this->rows[] = $buttons;

        return $this;
    }

    public function button(string $text, string $callbackData): self
    {
        $this->rows[] = [self::btn($text, $callbackData)];

        return $this;
    }

    public static function btn(string $text, string $callbackData): array
    {
        return ['text' => $text, 'callback_data' => $callbackData];
    }

    public static function url(string $text, string $url): array
    {
        return ['text' => $text, 'url' => $url];
    }

    /**
     * @return array{inline_keyboard: array<int, array<int, array<string, string>>>}
     */
    public function toArray(): array
    {
        return ['inline_keyboard' => $this->rows];
    }
}
