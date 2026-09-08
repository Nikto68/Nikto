<?php

declare(strict_types=1);

namespace App\Signal;

use App\Telegram\TelegramFormatter;

/**
 * Renders a signal (DB row + display strings) into a channel's template
 * (spec #17). Placeholder set: {direction} {symbol} {exchange} {entry}
 * {stop_loss} {tp1} {tp2} {tp3} {risk_reward} {score} {timeframe}
 * {reasons} — TelegramFormatter does the actual UTF-16-safe substitution
 * so any entity (premium emoji included) placed around a placeholder in
 * the template survives.
 */
final class SignalFormatter
{
    /**
     * @param array<string, mixed> $signal a `signals` row (SignalRepository::find()/history() shape)
     * @return array<string, string>
     */
    public function placeholders(array $signal, string $symbolDisplay, string $exchangeDisplay): array
    {
        $reasons = $signal['reasons'] ?? [];
        if (is_string($reasons)) {
            $reasons = json_decode($reasons, true) ?? [];
        }

        return [
            'direction' => (string) $signal['direction'],
            'symbol' => $symbolDisplay,
            'exchange' => $exchangeDisplay,
            'entry' => $this->formatPrice($signal['entry']),
            'stop_loss' => $this->formatPrice($signal['stop_loss']),
            'tp1' => $this->formatPriceOrDash($signal['take_profit_1'] ?? null),
            'tp2' => $this->formatPriceOrDash($signal['take_profit_2'] ?? null),
            'tp3' => $this->formatPriceOrDash($signal['take_profit_3'] ?? null),
            'risk_reward' => isset($signal['risk_reward']) && $signal['risk_reward'] !== null
                ? number_format((float) $signal['risk_reward'], 2)
                : '-',
            'score' => (string) $signal['score'],
            'confidence' => (string) ($signal['confidence'] ?? ''),
            'timeframe' => (string) $signal['timeframe'],
            'reasons' => implode("\n", array_map(static fn (string $r): string => "• {$r}", $reasons)),
        ];
    }

    /**
     * @param array{text: string, entities: array<int, array<string, mixed>>} $template
     * @param array<string, string> $placeholders
     * @return array{text: string, entities: array<int, array<string, mixed>>}
     */
    public function render(array $template, array $placeholders): array
    {
        return TelegramFormatter::render($template['text'], $template['entities'], $placeholders);
    }

    private function formatPrice(mixed $value): string
    {
        $price = (float) $value;
        $decimals = $price < 1 ? 6 : ($price < 100 ? 4 : 2);

        return rtrim(rtrim(number_format($price, $decimals, '.', ''), '0'), '.');
    }

    private function formatPriceOrDash(mixed $value): string
    {
        return $value === null ? '-' : $this->formatPrice($value);
    }
}
