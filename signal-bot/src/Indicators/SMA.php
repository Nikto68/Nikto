<?php

declare(strict_types=1);

namespace App\Indicators;

final class SMA implements IndicatorInterface
{
    public function name(): string
    {
        return 'SMA';
    }

    public function defaultParams(): array
    {
        return ['period' => 20, 'source' => 'close'];
    }

    public function calculate(array $candles, array $params = []): array
    {
        $params = [...$this->defaultParams(), ...$params];
        $values = IndicatorMath::column($candles, $params['source']);
        $sma = IndicatorMath::sma($values, (int) $params['period']);

        return array_map(static fn (?float $v): array => ['value' => $v], $sma);
    }
}
