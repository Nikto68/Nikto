<?php

declare(strict_types=1);

namespace App\Strategy\Zones;

/**
 * Buckets typical price ((H+L+C)/3) into a histogram weighted by volume.
 * Bins that got visited often and carried above-average volume are where
 * price has repeatedly reacted — this is "Volume" + "Repeated Rejection" +
 * "Price Clustering" from spec #8, which are really one statistical
 * technique (a volume-weighted price histogram) viewed three ways.
 */
final class PriceClusterDetector implements ZoneDetectorInterface
{
    public function method(): string
    {
        return 'volume_cluster';
    }

    public function detect(array $candles, string $timeframe, array $params = []): array
    {
        $bins = (int) ($params['bins'] ?? 24);
        $minTouches = (int) ($params['min_touches'] ?? 3);
        $count = count($candles);

        if ($count < $bins) {
            return [];
        }

        $highs = array_map(static fn (array $c): float => (float) $c['high'], $candles);
        $lows = array_map(static fn (array $c): float => (float) $c['low'], $candles);
        $rangeHigh = max($highs);
        $rangeLow = min($lows);

        if ($rangeHigh <= $rangeLow) {
            return [];
        }

        $binSize = ($rangeHigh - $rangeLow) / $bins;
        $binTouches = array_fill(0, $bins, 0);
        $binVolume = array_fill(0, $bins, 0.0);

        foreach ($candles as $candle) {
            $typical = ((float) $candle['high'] + (float) $candle['low'] + (float) $candle['close']) / 3;
            $binIndex = (int) min($bins - 1, max(0, floor(($typical - $rangeLow) / $binSize)));
            $binTouches[$binIndex]++;
            $binVolume[$binIndex] += (float) $candle['volume'];
        }

        $avgVolume = array_sum($binVolume) / $bins;
        $lastClose = (float) end($candles)['close'];
        $candidates = [];

        for ($b = 0; $b < $bins; $b++) {
            if ($binTouches[$b] < $minTouches || $binVolume[$b] < $avgVolume) {
                continue;
            }

            $low = $rangeLow + $b * $binSize;
            $high = $low + $binSize;
            $mid = ($low + $high) / 2;

            $candidates[] = [
                'type' => $mid <= $lastClose ? 'support' : 'resistance',
                'high' => $high,
                'low' => $low,
                'strength' => min(30.0, 8.0 + $binTouches[$b] * 2),
                'touches' => $binTouches[$b],
                'volume' => $binVolume[$b],
                'method' => $this->method(),
            ];
        }

        return $candidates;
    }
}
