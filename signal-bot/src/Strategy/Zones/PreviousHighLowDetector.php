<?php

declare(strict_types=1);

namespace App\Strategy\Zones;

/**
 * The extremes of the prior structure — highest high / lowest low over a
 * lookback window, excluding the most recent bars so it reflects "prior"
 * levels rather than the current move (spec #8's "Previous High/Low").
 */
final class PreviousHighLowDetector implements ZoneDetectorInterface
{
    public function method(): string
    {
        return 'previous_high_low';
    }

    public function detect(array $candles, string $timeframe, array $params = []): array
    {
        $lookback = (int) ($params['lookback'] ?? 50);
        $excludeRecent = (int) ($params['exclude_recent'] ?? 3);
        $bandPercent = (float) ($params['zone_thickness_percent'] ?? 0.15);
        $count = count($candles);

        $end = $count - $excludeRecent;
        $start = max(0, $end - $lookback);
        if ($end <= $start) {
            return [];
        }

        $window = array_slice($candles, $start, $end - $start);
        $high = max(array_map(static fn (array $c): float => (float) $c['high'], $window));
        $low = min(array_map(static fn (array $c): float => (float) $c['low'], $window));

        $highBand = $high * ($bandPercent / 100);
        $lowBand = $low * ($bandPercent / 100);

        return [
            [
                'type' => 'resistance',
                'high' => $high + $highBand,
                'low' => $high - $highBand,
                'strength' => 18.0,
                'touches' => 1,
                'volume' => null,
                'method' => $this->method(),
            ],
            [
                'type' => 'support',
                'high' => $low + $lowBand,
                'low' => $low - $lowBand,
                'strength' => 18.0,
                'touches' => 1,
                'volume' => null,
                'method' => $this->method(),
            ],
        ];
    }
}
