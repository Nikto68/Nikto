<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Database\Repositories\ZoneRepository;
use App\Strategy\Zones\HtfLevelsDetector;
use App\Strategy\Zones\PreviousHighLowDetector;
use App\Strategy\Zones\PriceClusterDetector;
use App\Strategy\Zones\SwingPointDetector;
use App\Strategy\Zones\ZoneBuilder;
use App\Strategy\Zones\ZoneDetectorInterface;

/**
 * Facade over every S/R detection method (spec #8). Runs all registered
 * ZoneDetectorInterface implementations, merges their candidates into
 * composite zones via ZoneBuilder, and persists the result as the current
 * active set for that symbol/timeframe.
 */
final class SupportResistance
{
    /** @var ZoneDetectorInterface[] */
    private array $detectors;

    public function __construct(
        private readonly ZoneBuilder $builder,
        private readonly ZoneRepository $zones,
        ?array $detectors = null,
    ) {
        $this->detectors = $detectors ?? [
            new SwingPointDetector(),
            new PriceClusterDetector(),
            new PreviousHighLowDetector(),
            new HtfLevelsDetector(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $candles working-timeframe candles
     * @param array<int, array<string, mixed>> $htfCandles higher-timeframe candles, for HtfLevelsDetector
     * @param array<string, mixed> $params merged into every detector's params
     * @return array<int, array{type: string, high: float, low: float, strength: float, touches: int, volume: float, methods: string[]}>
     */
    public function detect(array $candles, string $timeframe, array $htfCandles = [], array $params = []): array
    {
        $params = [...$params, 'htf_candles' => $htfCandles];

        $candidates = [];
        foreach ($this->detectors as $detector) {
            $candidates = [...$candidates, ...$detector->detect($candles, $timeframe, $params)];
        }

        return $this->builder->build($candidates, (float) ($params['merge_tolerance_percent'] ?? 0.3));
    }

    /**
     * detect() + persist as the current active set for this symbol.
     *
     * @param array<int, array<string, mixed>> $candles
     * @param array<int, array<string, mixed>> $htfCandles
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function detectAndStore(int $symbolId, array $candles, string $timeframe, array $htfCandles = [], array $params = []): array
    {
        $zones = $this->detect($candles, $timeframe, $htfCandles, $params);
        $this->zones->replaceForSymbol($symbolId, $timeframe, $zones);

        return $zones;
    }
}
