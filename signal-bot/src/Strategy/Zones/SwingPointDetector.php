<?php

declare(strict_types=1);

namespace App\Strategy\Zones;

/**
 * Classic fractal swing high/low detection: a bar is a confirmed swing
 * high when its high is the highest within `lookback` bars on both sides
 * (and symmetrically for swing lows). Each confirmed swing becomes a
 * candidate zone with a small price band around the extreme.
 */
final class SwingPointDetector implements ZoneDetectorInterface
{
    public function method(): string
    {
        return 'swing_high_low';
    }

    public function detect(array $candles, string $timeframe, array $params = []): array
    {
        $lookback = (int) ($params['lookback'] ?? 3);
        $bandPercent = (float) ($params['zone_thickness_percent'] ?? 0.15);
        $count = count($candles);
        $candidates = [];

        for ($i = $lookback; $i < $count - $lookback; $i++) {
            $high = (float) $candles[$i]['high'];
            $low = (float) $candles[$i]['low'];

            if ($this->isExtreme($candles, $i, $lookback, 'high', $high)) {
                $band = $high * ($bandPercent / 100);
                $candidates[] = [
                    'type' => 'resistance',
                    'high' => $high + $band,
                    'low' => $high - $band,
                    'strength' => 15.0,
                    'touches' => 1,
                    'volume' => (float) $candles[$i]['volume'],
                    'method' => $this->method(),
                ];
            }

            if ($this->isExtreme($candles, $i, $lookback, 'low', $low)) {
                $band = $low * ($bandPercent / 100);
                $candidates[] = [
                    'type' => 'support',
                    'high' => $low + $band,
                    'low' => $low - $band,
                    'strength' => 15.0,
                    'touches' => 1,
                    'volume' => (float) $candles[$i]['volume'],
                    'method' => $this->method(),
                ];
            }
        }

        return $candidates;
    }

    /**
     * @param array<int, array<string, mixed>> $candles
     */
    private function isExtreme(array $candles, int $index, int $lookback, string $field, float $value): bool
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
