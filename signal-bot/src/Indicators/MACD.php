<?php

declare(strict_types=1);

namespace App\Indicators;

/**
 * MACD line = EMA(fast) - EMA(slow); signal = EMA(MACD line, signalPeriod);
 * histogram = MACD - signal.
 */
final class MACD implements IndicatorInterface
{
    public function name(): string
    {
        return 'MACD';
    }

    public function defaultParams(): array
    {
        return ['fast_period' => 12, 'slow_period' => 26, 'signal_period' => 9, 'source' => 'close'];
    }

    public function calculate(array $candles, array $params = []): array
    {
        $params = [...$this->defaultParams(), ...$params];
        $fast = (int) $params['fast_period'];
        $slow = (int) $params['slow_period'];
        $signalPeriod = (int) $params['signal_period'];

        $closes = IndicatorMath::column($candles, $params['source']);
        $count = count($closes);
        $result = array_fill(0, $count, ['macd' => null, 'signal' => null, 'histogram' => null]);

        $emaFast = IndicatorMath::ema($closes, $fast);
        $emaSlow = IndicatorMath::ema($closes, $slow);

        if ($count <= $slow) {
            return $result;
        }

        // Dense (no-null) MACD line starting where both EMAs exist, so it
        // can be fed straight into ema() for the signal line.
        $macdDense = [];
        for ($i = $slow - 1; $i < $count; $i++) {
            $macdDense[] = $emaFast[$i] - $emaSlow[$i];
            $result[$i] = ['macd' => end($macdDense), 'signal' => null, 'histogram' => null];
        }

        $signalDense = IndicatorMath::ema($macdDense, $signalPeriod);

        foreach ($signalDense as $denseIndex => $signalValue) {
            if ($signalValue === null) {
                continue;
            }

            $candleIndex = ($slow - 1) + $denseIndex;
            $macdValue = $result[$candleIndex]['macd'];
            $result[$candleIndex] = [
                'macd' => $macdValue,
                'signal' => $signalValue,
                'histogram' => $macdValue - $signalValue,
            ];
        }

        return $result;
    }
}
