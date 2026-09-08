<?php

declare(strict_types=1);

namespace App\Indicators;

/**
 * Wilder's Average True Range.
 */
final class ATR implements IndicatorInterface
{
    public function name(): string
    {
        return 'ATR';
    }

    public function defaultParams(): array
    {
        return ['period' => 14];
    }

    public function calculate(array $candles, array $params = []): array
    {
        $params = [...$this->defaultParams(), ...$params];
        $tr = IndicatorMath::trueRange($candles);
        $atr = IndicatorMath::wilderSmooth($tr, (int) $params['period']);

        return array_map(static fn (?float $v): array => ['value' => $v], $atr);
    }
}
