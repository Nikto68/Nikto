<?php

declare(strict_types=1);

namespace App\Strategy\Zones;

/**
 * One S/R detection method (spec #8: swing high/low, volume, repeated
 * rejection, price clustering, HTF levels, previous high/low, ...).
 * ZoneBuilder merges every detector's candidates into composite Zones —
 * adding a new detection method is one class implementing this, nothing
 * else changes.
 */
interface ZoneDetectorInterface
{
    /**
     * Short identifier stored in a Zone's `methods` list.
     */
    public function method(): string;

    /**
     * @param array<int, array<string, mixed>> $candles oldest-to-newest, standard Candle shape
     * @param array<string, mixed> $params
     * @return array<int, array{type: string, high: float, low: float, strength: float, touches: int, volume: ?float, method: string}>
     */
    public function detect(array $candles, string $timeframe, array $params = []): array;
}
