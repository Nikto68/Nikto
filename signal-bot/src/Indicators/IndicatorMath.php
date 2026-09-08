<?php

declare(strict_types=1);

namespace App\Indicators;

/**
 * Shared numeric building blocks (SMA/EMA/Wilder smoothing/true range) so
 * every indicator implements its own formula in terms of these instead of
 * re-deriving smoothing logic. All functions are index-aligned to their
 * input: the result array is the same length, with `null` wherever there
 * is not yet enough history to produce a value.
 */
final class IndicatorMath
{
    /**
     * @param array<int, float> $values
     * @return array<int, float|null>
     */
    public static function sma(array $values, int $period): array
    {
        $count = count($values);
        $result = array_fill(0, $count, null);

        for ($i = $period - 1; $i < $count; $i++) {
            $sum = 0.0;
            for ($j = $i - $period + 1; $j <= $i; $j++) {
                $sum += $values[$j];
            }
            $result[$i] = $sum / $period;
        }

        return $result;
    }

    /**
     * Seeded with an SMA of the first $period values, then the standard EMA
     * recursion for every value after that.
     *
     * @param array<int, float> $values
     * @return array<int, float|null>
     */
    public static function ema(array $values, int $period): array
    {
        $count = count($values);
        $result = array_fill(0, $count, null);

        if ($count < $period) {
            return $result;
        }

        $multiplier = 2 / ($period + 1);
        $seed = array_sum(array_slice($values, 0, $period)) / $period;
        $result[$period - 1] = $seed;

        for ($i = $period; $i < $count; $i++) {
            $result[$i] = ($values[$i] - $result[$i - 1]) * $multiplier + $result[$i - 1];
        }

        return $result;
    }

    /**
     * Wilder's smoothing (used by RSI/ATR/ADX): seeded with a plain SMA of
     * the first $period values, then averaged-in one value at a time.
     *
     * @param array<int, float> $values
     * @return array<int, float|null>
     */
    public static function wilderSmooth(array $values, int $period): array
    {
        $count = count($values);
        $result = array_fill(0, $count, null);

        if ($count < $period) {
            return $result;
        }

        $seed = array_sum(array_slice($values, 0, $period)) / $period;
        $result[$period - 1] = $seed;

        for ($i = $period; $i < $count; $i++) {
            $result[$i] = (($result[$i - 1] * ($period - 1)) + $values[$i]) / $period;
        }

        return $result;
    }

    /**
     * @param array<int, float> $values
     */
    public static function stdDev(array $values, int $period, int $index, float $mean): float
    {
        $sumSquares = 0.0;
        for ($j = $index - $period + 1; $j <= $index; $j++) {
            $sumSquares += ($values[$j] - $mean) ** 2;
        }

        return sqrt($sumSquares / $period);
    }

    /**
     * True Range per bar. Index 0 has no previous close, so it is just
     * high - low.
     *
     * @param array<int, array<string, mixed>> $candles
     * @return array<int, float>
     */
    public static function trueRange(array $candles): array
    {
        $count = count($candles);
        $tr = [];

        for ($i = 0; $i < $count; $i++) {
            $high = (float) $candles[$i]['high'];
            $low = (float) $candles[$i]['low'];

            if ($i === 0) {
                $tr[$i] = $high - $low;

                continue;
            }

            $prevClose = (float) $candles[$i - 1]['close'];
            $tr[$i] = max($high - $low, abs($high - $prevClose), abs($low - $prevClose));
        }

        return $tr;
    }

    /**
     * @param array<int, array<string, mixed>> $candles
     * @return array<int, float>
     */
    public static function column(array $candles, string $key): array
    {
        return array_map(static fn (array $c): float => (float) $c[$key], $candles);
    }
}
