<?php

declare(strict_types=1);

namespace App\Indicators;

/**
 * Raw volume alongside its own moving average, so a Strategy can express
 * "volume > N * average volume" as a confirmation condition without
 * re-deriving the average itself.
 */
final class Volume implements IndicatorInterface
{
    public function name(): string
    {
        return 'VOLUME';
    }

    public function defaultParams(): array
    {
        return ['period' => 20];
    }

    public function calculate(array $candles, array $params = []): array
    {
        $params = [...$this->defaultParams(), ...$params];
        $volumes = IndicatorMath::column($candles, 'volume');
        $avg = IndicatorMath::sma($volumes, (int) $params['period']);

        $result = [];
        foreach ($volumes as $i => $volume) {
            $result[$i] = ['volume' => $volume, 'average' => $avg[$i]];
        }

        return $result;
    }
}
