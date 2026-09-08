<?php

declare(strict_types=1);

namespace App\Strategy\Smc;

use App\Indicators\IndicatorMath;

/**
 * Order Block / Supply-Demand zones, unifying the source Pine script's
 * "nexua" (Order Blocks at auto HH/LH/LL/HL, ATR-power-filtered) and "smc"
 * (Supply/Demand POI at swing pivots, ATR-buffered) sections — both are,
 * mechanically, the same idea: a zone at a swing extreme that only counts
 * if it formed with above-average conviction, removed once price trades
 * back through it. type=bearish (nexua's HH/LH red box, smc's SUPPLY) is
 * resistance/supply; type=bullish (nexua's LL/HL blue box, smc's DEMAND)
 * is support/demand.
 */
final class SmcZone
{
    /**
     * @param array<int, array<string, mixed>> $candles oldest-to-newest
     * @param array<string, mixed> $params
     * @return array<int, array{type: string, high: float, low: float, timeframe: string, origin_candle_open_time: int, mitigated: bool}>
     */
    public function detect(array $candles, string $timeframe, array $params = []): array
    {
        $swingLookback = (int) ($params['swing_lookback'] ?? 10);
        $atrPeriod = (int) ($params['atr_period'] ?? 14);
        $atrAvgPeriod = (int) ($params['atr_avg_period'] ?? 50);
        $powerMultiple = (float) ($params['power_multiple'] ?? 1.1);
        $bufferAtrFraction = (float) ($params['buffer_atr_fraction'] ?? 0.25);

        $count = count($candles);
        $tr = IndicatorMath::trueRange($candles);
        $atr = IndicatorMath::wilderSmooth($tr, $atrPeriod);

        $atrOffset = $atrPeriod - 1;
        $atrDense = array_values(array_filter($atr, static fn (?float $v): bool => $v !== null));
        $atrAvgDense = IndicatorMath::sma($atrDense, $atrAvgPeriod);

        $swings = SwingFinder::find($candles, $swingLookback);
        $zones = [];

        foreach ($swings as $swing) {
            $idx = $swing['index'];
            $atrValue = $atr[$idx] ?? null;
            $denseIndex = $idx - $atrOffset;
            $atrAvgValue = ($denseIndex >= 0 && isset($atrAvgDense[$denseIndex])) ? $atrAvgDense[$denseIndex] : null;

            if ($atrValue === null || $atrAvgValue === null || $atrValue <= $atrAvgValue * $powerMultiple) {
                continue;
            }

            $buffer = $atrValue * $bufferAtrFraction;
            $isHigh = $swing['type'] === 'high';

            $zones[] = [
                'type' => $isHigh ? 'bearish' : 'bullish',
                'high' => $isHigh ? $swing['price'] : $swing['price'] + $buffer,
                'low' => $isHigh ? $swing['price'] - $buffer : $swing['price'],
                'timeframe' => $timeframe,
                'origin_candle_open_time' => (int) $candles[$idx]['open_time'],
                'mitigated' => $this->isMitigated($isHigh, $swing['price'] - ($isHigh ? $buffer : 0), $swing['price'] + ($isHigh ? 0 : $buffer), array_slice($candles, $idx + 1)),
            ];
        }

        return $zones;
    }

    /**
     * @param array<int, array<string, mixed>> $subsequentCandles
     */
    private function isMitigated(bool $isHigh, float $low, float $high, array $subsequentCandles): bool
    {
        foreach ($subsequentCandles as $candle) {
            $candleLow = (float) $candle['low'];
            $candleHigh = (float) $candle['high'];

            if ($candleLow <= $high && $candleHigh >= $low) {
                return true;
            }
        }

        return false;
    }
}
