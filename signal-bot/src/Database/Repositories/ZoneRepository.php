<?php

declare(strict_types=1);

namespace App\Database\Repositories;

/**
 * S/R zones are a "current view" recomputed from a rolling candle window
 * every scan (like market_data), not an append-only log — replaceForSymbol()
 * retires the previous active set and inserts the freshly detected one.
 */
final class ZoneRepository extends Repository
{
    /**
     * @param array<int, array{type: string, high: float, low: float, strength: float, touches: int, volume: float, methods: string[]}> $zones
     */
    public function replaceForSymbol(int $symbolId, string $timeframe, array $zones): void
    {
        $this->db->transaction(function () use ($symbolId, $timeframe, $zones): void {
            $this->db->statement(
                "UPDATE zones SET is_active = 0, invalidated_at = :now
                 WHERE symbol_id = :symbol_id AND timeframe = :timeframe AND is_active = 1",
                ['now' => date('Y-m-d H:i:s'), 'symbol_id' => $symbolId, 'timeframe' => $timeframe],
            );

            foreach ($zones as $zone) {
                $this->db->insert('zones', [
                    'symbol_id' => $symbolId,
                    'timeframe' => $timeframe,
                    'type' => $zone['type'],
                    'high' => $zone['high'],
                    'low' => $zone['low'],
                    'strength' => $zone['strength'],
                    'touches' => $zone['touches'],
                    'volume' => $zone['volume'],
                    'methods' => json_encode($zone['methods'], JSON_THROW_ON_ERROR),
                    'is_active' => 1,
                ]);
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function active(int $symbolId, string $timeframe): array
    {
        return $this->db->select(
            'SELECT * FROM zones WHERE symbol_id = :symbol_id AND timeframe = :timeframe AND is_active = 1 ORDER BY strength DESC',
            ['symbol_id' => $symbolId, 'timeframe' => $timeframe],
        );
    }
}
