<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Indicators\IndicatorManager;

/**
 * Everything a Strategy is allowed to see for one symbol, gathered once by
 * StrategyEngine and handed in read-only: candles per timeframe, persisted
 * zones/order blocks/FVGs, and on-demand indicator values. A Strategy never
 * touches the database, an exchange, or Telegram directly — only this
 * (spec #11's "Strategy فقط باید Market Data استاندارد دریافت کند").
 */
final class StrategyContext
{
    /**
     * @param array<string, array<int, array<string, mixed>>> $candlesByTimeframe
     * @param array<string, array<int, array<string, mixed>>> $zonesByTimeframe
     * @param array<string, array<int, array<string, mixed>>> $orderBlocksByTimeframe
     * @param array<string, array<int, array<string, mixed>>> $fvgsByTimeframe
     */
    public function __construct(
        public readonly string $exchangeCode,
        public readonly string $symbol,
        public readonly int $symbolId,
        public readonly float $currentPrice,
        private readonly array $candlesByTimeframe,
        private readonly array $zonesByTimeframe,
        private readonly array $orderBlocksByTimeframe,
        private readonly array $fvgsByTimeframe,
        private readonly IndicatorManager $indicators,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function candles(string $timeframe): array
    {
        return $this->candlesByTimeframe[$timeframe] ?? [];
    }

    public function hasTimeframe(string $timeframe): bool
    {
        return isset($this->candlesByTimeframe[$timeframe]) && $this->candlesByTimeframe[$timeframe] !== [];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, float|null>|null
     */
    public function indicator(string $name, string $timeframe, array $params = []): ?array
    {
        return $this->indicators->latest($name, $this->candles($timeframe), $params);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function zones(string $timeframe): array
    {
        return $this->zonesByTimeframe[$timeframe] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function orderBlocks(string $timeframe): array
    {
        return $this->orderBlocksByTimeframe[$timeframe] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fvgs(string $timeframe): array
    {
        return $this->fvgsByTimeframe[$timeframe] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function nearestZones(string $timeframe, string $type, int $limit = 3): array
    {
        $zones = array_values(array_filter($this->zones($timeframe), static fn (array $z): bool => $z['type'] === $type));
        usort($zones, fn (array $a, array $b): int => $this->distanceToPrice($a) <=> $this->distanceToPrice($b));

        return array_slice($zones, 0, $limit);
    }

    /**
     * @param array<string, mixed> $zone
     */
    private function distanceToPrice(array $zone): float
    {
        $mid = ((float) $zone['high'] + (float) $zone['low']) / 2;

        return abs($mid - $this->currentPrice);
    }
}
