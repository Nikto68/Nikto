<?php

declare(strict_types=1);

namespace App\Indicators;

final class BollingerBands implements IndicatorInterface
{
    public function name(): string
    {
        return 'BOLLINGER';
    }

    public function defaultParams(): array
    {
        return ['period' => 20, 'std_dev' => 2.0, 'source' => 'close'];
    }

    public function calculate(array $candles, array $params = []): array
    {
        $params = [...$this->defaultParams(), ...$params];
        $period = (int) $params['period'];
        $multiplier = (float) $params['std_dev'];

        $values = IndicatorMath::column($candles, $params['source']);
        $count = count($values);
        $middle = IndicatorMath::sma($values, $period);

        $result = array_fill(0, $count, ['upper' => null, 'middle' => null, 'lower' => null]);

        for ($i = $period - 1; $i < $count; $i++) {
            $mean = $middle[$i];
            if ($mean === null) {
                continue;
            }

            $std = IndicatorMath::stdDev($values, $period, $i, $mean);
            $result[$i] = [
                'upper' => $mean + $multiplier * $std,
                'middle' => $mean,
                'lower' => $mean - $multiplier * $std,
            ];
        }

        return $result;
    }
}
