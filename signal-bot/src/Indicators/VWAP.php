<?php

declare(strict_types=1);

namespace App\Indicators;

/**
 * Volume-Weighted Average Price, cumulative from the start of each UTC
 * calendar day (the conventional VWAP reset point) using typical price
 * (H+L+C)/3.
 */
final class VWAP implements IndicatorInterface
{
    public function name(): string
    {
        return 'VWAP';
    }

    public function defaultParams(): array
    {
        return [];
    }

    public function calculate(array $candles, array $params = []): array
    {
        $count = count($candles);
        $result = array_fill(0, $count, ['value' => null]);

        $cumulativePV = 0.0;
        $cumulativeVolume = 0.0;
        $currentDay = null;

        for ($i = 0; $i < $count; $i++) {
            $day = (int) floor(((int) $candles[$i]['open_time']) / 86_400_000);
            if ($day !== $currentDay) {
                $currentDay = $day;
                $cumulativePV = 0.0;
                $cumulativeVolume = 0.0;
            }

            $typicalPrice = ((float) $candles[$i]['high'] + (float) $candles[$i]['low'] + (float) $candles[$i]['close']) / 3;
            $volume = (float) $candles[$i]['volume'];

            $cumulativePV += $typicalPrice * $volume;
            $cumulativeVolume += $volume;

            $result[$i] = ['value' => $cumulativeVolume > 0 ? $cumulativePV / $cumulativeVolume : $typicalPrice];
        }

        return $result;
    }
}
