<?php

declare(strict_types=1);

namespace App\Indicators;

/**
 * Wilder's RSI: average gain/loss seeded as a plain average of the first
 * `period` price changes, then smoothed one bar at a time (the same method
 * Wilder used for ATR/ADX). The seed at diff-index (period-1) covers
 * exactly `period` price changes (closes[0..period]), which is why the
 * first RSI value lands on candle index `period`, not `period - 1`.
 */
final class RSI implements IndicatorInterface
{
    public function name(): string
    {
        return 'RSI';
    }

    public function defaultParams(): array
    {
        return ['period' => 14, 'source' => 'close'];
    }

    public function calculate(array $candles, array $params = []): array
    {
        $params = [...$this->defaultParams(), ...$params];
        $period = (int) $params['period'];
        $closes = IndicatorMath::column($candles, $params['source']);
        $count = count($closes);

        $result = array_fill(0, $count, ['value' => null]);
        if ($count <= $period) {
            return $result;
        }

        // diffs[$k] = change from closes[$k] to closes[$k + 1]
        $gains = [];
        $losses = [];
        for ($k = 0; $k < $count - 1; $k++) {
            $change = $closes[$k + 1] - $closes[$k];
            $gains[$k] = max($change, 0.0);
            $losses[$k] = max(-$change, 0.0);
        }

        $avgGain = IndicatorMath::wilderSmooth($gains, $period);
        $avgLoss = IndicatorMath::wilderSmooth($losses, $period);

        for ($k = $period - 1; $k < count($gains); $k++) {
            $gain = $avgGain[$k];
            $loss = $avgLoss[$k];
            if ($gain === null || $loss === null) {
                continue;
            }

            $candleIndex = $k + 1;

            if ($loss == 0.0) {
                $result[$candleIndex] = ['value' => 100.0];

                continue;
            }

            $rs = $gain / $loss;
            $result[$candleIndex] = ['value' => 100 - (100 / (1 + $rs))];
        }

        return $result;
    }
}
