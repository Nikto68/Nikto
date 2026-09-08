<?php

declare(strict_types=1);

namespace App\Indicators;

/**
 * Wilder's ADX/+DI/-DI. Directional movement and true range are smoothed
 * over `period`, DI is derived from those, DX from the DI spread, and ADX
 * is itself a Wilder-smoothed average of DX — so the first ADX value only
 * appears after roughly 2 * period bars of history.
 */
final class ADX implements IndicatorInterface
{
    public function name(): string
    {
        return 'ADX';
    }

    public function defaultParams(): array
    {
        return ['period' => 14];
    }

    public function calculate(array $candles, array $params = []): array
    {
        $params = [...$this->defaultParams(), ...$params];
        $period = (int) $params['period'];

        $highs = IndicatorMath::column($candles, 'high');
        $lows = IndicatorMath::column($candles, 'low');
        $count = count($candles);

        $result = array_fill(0, $count, ['adx' => null, 'plus_di' => null, 'minus_di' => null]);
        if ($count <= $period * 2) {
            return $result;
        }

        $tr = IndicatorMath::trueRange($candles);

        // Dense arrays over bars 1..count-1 (directional movement needs a
        // previous bar), so dense-index k = candle index k + 1.
        $plusDMDense = [];
        $minusDMDense = [];
        $trDense = [];

        for ($i = 1; $i < $count; $i++) {
            $upMove = $highs[$i] - $highs[$i - 1];
            $downMove = $lows[$i - 1] - $lows[$i];

            $plusDMDense[] = ($upMove > $downMove && $upMove > 0) ? $upMove : 0.0;
            $minusDMDense[] = ($downMove > $upMove && $downMove > 0) ? $downMove : 0.0;
            $trDense[] = $tr[$i];
        }
        $dmOffset = 1;

        $smoothedPlusDM = IndicatorMath::wilderSmooth($plusDMDense, $period);
        $smoothedMinusDM = IndicatorMath::wilderSmooth($minusDMDense, $period);
        $smoothedTR = IndicatorMath::wilderSmooth($trDense, $period);

        // Dense DI/DX, starting where the smoothing above first produces a
        // value (dense-index period - 1 -> candle index dmOffset + period - 1).
        $dxDense = [];
        for ($k = $period - 1; $k < count($trDense); $k++) {
            $trValue = $smoothedTR[$k];
            $plusDmValue = $smoothedPlusDM[$k];
            $minusDmValue = $smoothedMinusDM[$k];
            if ($trValue === null || $plusDmValue === null || $minusDmValue === null || $trValue == 0.0) {
                continue;
            }

            $plusDi = 100 * $plusDmValue / $trValue;
            $minusDi = 100 * $minusDmValue / $trValue;
            $diSum = $plusDi + $minusDi;
            $dx = $diSum == 0.0 ? 0.0 : 100 * abs($plusDi - $minusDi) / $diSum;

            $candleIndex = $dmOffset + $k;
            $result[$candleIndex]['plus_di'] = $plusDi;
            $result[$candleIndex]['minus_di'] = $minusDi;
            $dxDense[] = $dx;
        }
        $dxOffset = $dmOffset + $period - 1;

        $adxDense = IndicatorMath::wilderSmooth($dxDense, $period);
        foreach ($adxDense as $denseIndex => $adxValue) {
            if ($adxValue === null) {
                continue;
            }

            $result[$dxOffset + $denseIndex]['adx'] = $adxValue;
        }

        return $result;
    }
}
