<?php

declare(strict_types=1);

namespace App\Strategy;

/**
 * Fair Value Gap detection (spec #10) using the standard 3-candle
 * definition:
 *
 *   Bullish FVG at candle i: candles[i-1].high < candles[i+1].low
 *     (gap = [candles[i-1].high, candles[i+1].low])
 *   Bearish FVG at candle i: candles[i-1].low > candles[i+1].high
 *     (gap = [candles[i+1].high, candles[i-1].low])
 *
 * This is an objective geometric pattern, not a trading rule, so it is
 * implemented directly rather than left as a stub — `min_gap_percent`
 * (noise filter) and which timeframes to scan stay configurable per spec
 * #10's request, and exact fill/mitigation semantics can still be tuned
 * once the user gives further rules.
 */
final class FVG
{
    /**
     * @param array<int, array<string, mixed>> $candles oldest-to-newest
     * @param array<string, mixed> $params
     * @return array<int, array{type: string, high: float, low: float, midpoint: float, timeframe: string, size: float, origin_candle_open_time: int, filled: bool, partially_filled: bool}>
     */
    public function detect(array $candles, string $timeframe, array $params = []): array
    {
        $minGapPercent = (float) ($params['min_gap_percent'] ?? 0.05);
        $count = count($candles);
        $gaps = [];

        for ($i = 1; $i < $count - 1; $i++) {
            $prev = $candles[$i - 1];
            $next = $candles[$i + 1];
            $middle = $candles[$i];

            $prevHigh = (float) $prev['high'];
            $prevLow = (float) $prev['low'];
            $nextHigh = (float) $next['high'];
            $nextLow = (float) $next['low'];
            $price = (float) $middle['close'];

            if ($prevHigh < $nextLow) {
                $gap = $this->makeGap('bullish', $prevHigh, $nextLow, $middle, $timeframe, $price, $minGapPercent);
                if ($gap !== null) {
                    $gaps[] = $this->applyFillState($gap, array_slice($candles, $i + 2));
                }

                continue;
            }

            if ($prevLow > $nextHigh) {
                $gap = $this->makeGap('bearish', $nextHigh, $prevLow, $middle, $timeframe, $price, $minGapPercent);
                if ($gap !== null) {
                    $gaps[] = $this->applyFillState($gap, array_slice($candles, $i + 2));
                }
            }
        }

        return $gaps;
    }

    /**
     * @param array<string, mixed> $middleCandle
     * @return array<string, mixed>|null
     */
    private function makeGap(string $type, float $low, float $high, array $middleCandle, string $timeframe, float $price, float $minGapPercent): ?array
    {
        $size = $high - $low;
        if ($price <= 0 || ($size / $price) * 100 < $minGapPercent) {
            return null;
        }

        return [
            'type' => $type,
            'high' => $high,
            'low' => $low,
            'midpoint' => ($high + $low) / 2,
            'timeframe' => $timeframe,
            'size' => $size,
            'origin_candle_open_time' => (int) $middleCandle['open_time'],
            'filled' => false,
            'partially_filled' => false,
        ];
    }

    /**
     * @param array<string, mixed> $gap
     * @param array<int, array<string, mixed>> $subsequentCandles
     * @return array<string, mixed>
     */
    private function applyFillState(array $gap, array $subsequentCandles): array
    {
        foreach ($subsequentCandles as $candle) {
            $low = (float) $candle['low'];
            $high = (float) $candle['high'];

            if ($low <= $gap['low'] && $high >= $gap['high']) {
                $gap['filled'] = true;
                $gap['partially_filled'] = true;

                return $gap;
            }

            if ($low <= $gap['high'] && $high >= $gap['low']) {
                $gap['partially_filled'] = true;
            }
        }

        return $gap;
    }
}
