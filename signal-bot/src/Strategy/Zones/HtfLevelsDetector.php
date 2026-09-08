<?php

declare(strict_types=1);

namespace App\Strategy\Zones;

/**
 * Swing highs/lows from a higher timeframe's candles, carried down as
 * candidate zones on the working timeframe (spec #8's "HTF Levels").
 * The HTF candle set is supplied by the caller via $params['htf_candles']
 * (SupportResistance fetches it) since it comes from a different
 * timeframe than $candles — this detector never fetches data itself.
 */
final class HtfLevelsDetector implements ZoneDetectorInterface
{
    public function __construct(
        private readonly SwingPointDetector $swingDetector = new SwingPointDetector(),
    ) {
    }

    public function method(): string
    {
        return 'htf_levels';
    }

    public function detect(array $candles, string $timeframe, array $params = []): array
    {
        $htfCandles = $params['htf_candles'] ?? [];
        if (!is_array($htfCandles) || count($htfCandles) < 7) {
            return [];
        }

        $swings = $this->swingDetector->detect($htfCandles, $timeframe, [
            'lookback' => $params['htf_lookback'] ?? 2,
            'zone_thickness_percent' => $params['zone_thickness_percent'] ?? 0.2,
        ]);

        return array_map(function (array $zone): array {
            $zone['strength'] = 25.0;
            $zone['method'] = $this->method();

            return $zone;
        }, $swings);
    }
}
