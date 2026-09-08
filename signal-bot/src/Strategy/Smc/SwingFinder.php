<?php

declare(strict_types=1);

namespace App\Strategy\Smc;

/**
 * Shared fractal swing-high/low detector used by SwingStructure,
 * LiquidityZones and SmcZone — a bar is a confirmed swing when its
 * high/low is the extreme within `lookback` bars on both sides. This is
 * the PHP equivalent of the Pine source's repeated 3-bar/N-bar pivot
 * pattern (liqQueryRaw / nxCalculatePivots / ta.pivothigh-pivotlow),
 * generalized to one parametrized routine instead of three near-duplicate
 * ones, since all three are the same fractal check at different lookback
 * lengths ("Short/Intermediate/Long Term" in the source script).
 */
final class SwingFinder
{
    /**
     * @param array<int, array<string, mixed>> $candles oldest-to-newest
     * @return array<int, array{index: int, price: float, type: string}> sorted by index ascending
     */
    public static function find(array $candles, int $lookback): array
    {
        $count = count($candles);
        $swings = [];

        for ($i = $lookback; $i < $count - $lookback; $i++) {
            $high = (float) $candles[$i]['high'];
            $low = (float) $candles[$i]['low'];

            if (self::isExtreme($candles, $i, $lookback, 'high', $high)) {
                $swings[] = ['index' => $i, 'price' => $high, 'type' => 'high'];
            }

            if (self::isExtreme($candles, $i, $lookback, 'low', $low)) {
                $swings[] = ['index' => $i, 'price' => $low, 'type' => 'low'];
            }
        }

        return $swings;
    }

    /**
     * @param array<int, array<string, mixed>> $candles
     */
    private static function isExtreme(array $candles, int $index, int $lookback, string $field, float $value): bool
    {
        for ($j = $index - $lookback; $j <= $index + $lookback; $j++) {
            if ($j === $index) {
                continue;
            }

            $other = (float) $candles[$j][$field];
            if ($field === 'high' && $other >= $value) {
                return false;
            }
            if ($field === 'low' && $other <= $value) {
                return false;
            }
        }

        return true;
    }
}
