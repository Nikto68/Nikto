<?php

declare(strict_types=1);

namespace App\Indicators;

/**
 * Slow stochastic: raw %K over k_period, smoothed by k_smooth, then %D is
 * an SMA of that smoothed %K. Every intermediate series is compacted to a
 * gap-free "dense" array before being fed into the next SMA, then mapped
 * back to candle-index space by its own start offset — SMA()/EMA() assume
 * a contiguous array with no leading nulls.
 */
final class Stochastic implements IndicatorInterface
{
    public function name(): string
    {
        return 'STOCHASTIC';
    }

    public function defaultParams(): array
    {
        return ['k_period' => 14, 'k_smooth' => 3, 'd_period' => 3];
    }

    public function calculate(array $candles, array $params = []): array
    {
        $params = [...$this->defaultParams(), ...$params];
        $kPeriod = (int) $params['k_period'];
        $kSmooth = (int) $params['k_smooth'];
        $dPeriod = (int) $params['d_period'];

        $highs = IndicatorMath::column($candles, 'high');
        $lows = IndicatorMath::column($candles, 'low');
        $closes = IndicatorMath::column($candles, 'close');
        $count = count($candles);

        $result = array_fill(0, $count, ['k' => null, 'd' => null]);
        if ($count < $kPeriod) {
            return $result;
        }

        // Dense raw %K, starting at candle index (kPeriod - 1).
        $rawKDense = [];
        for ($i = $kPeriod - 1; $i < $count; $i++) {
            $windowHigh = max(array_slice($highs, $i - $kPeriod + 1, $kPeriod));
            $windowLow = min(array_slice($lows, $i - $kPeriod + 1, $kPeriod));
            $range = $windowHigh - $windowLow;

            $rawKDense[] = $range == 0.0 ? 50.0 : (($closes[$i] - $windowLow) / $range) * 100;
        }
        $rawKOffset = $kPeriod - 1;

        // Dense smoothed %K, starting (kSmooth - 1) further in.
        $smoothedKWithNulls = IndicatorMath::sma($rawKDense, $kSmooth);
        $smoothedKDense = array_values(array_filter($smoothedKWithNulls, static fn (?float $v): bool => $v !== null));
        $smoothedKOffset = $rawKOffset + $kSmooth - 1;

        // %D is an SMA of smoothed %K, index-aligned to $smoothedKDense
        // (so candle index = $smoothedKOffset + denseIndex, same as %K).
        $dWithNulls = IndicatorMath::sma($smoothedKDense, $dPeriod);

        foreach ($smoothedKDense as $denseIndex => $kValue) {
            $result[$smoothedKOffset + $denseIndex]['k'] = $kValue;
        }

        foreach ($dWithNulls as $denseIndex => $dValue) {
            if ($dValue === null) {
                continue;
            }

            $result[$smoothedKOffset + $denseIndex]['d'] = $dValue;
        }

        return $result;
    }
}
