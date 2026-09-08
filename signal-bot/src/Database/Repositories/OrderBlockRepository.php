<?php

declare(strict_types=1);

namespace App\Database\Repositories;

final class OrderBlockRepository extends Repository
{
    /**
     * @param array<int, array<string, mixed>> $blocks
     */
    public function upsertBatch(int $symbolId, array $blocks): void
    {
        foreach ($blocks as $block) {
            // mitigated_at uses COALESCE rather than the generic upsert()
            // helper so it is stamped once, the first scan a block shows
            // as mitigated, instead of being bumped to "now" every scan.
            $this->db->statement(
                'INSERT INTO order_blocks
                    (symbol_id, timeframe, type, high, low, origin_candle_open_time, strength, volume, mitigated, mitigated_at)
                 VALUES
                    (:symbol_id, :timeframe, :type, :high, :low, :origin_candle_open_time, :strength, :volume, :mitigated, :mitigated_at)
                 ON DUPLICATE KEY UPDATE
                    high = VALUES(high),
                    low = VALUES(low),
                    strength = VALUES(strength),
                    volume = VALUES(volume),
                    mitigated = VALUES(mitigated),
                    mitigated_at = COALESCE(mitigated_at, VALUES(mitigated_at))',
                [
                    'symbol_id' => $symbolId,
                    'timeframe' => $block['timeframe'],
                    'type' => $block['type'],
                    'high' => $block['high'],
                    'low' => $block['low'],
                    'origin_candle_open_time' => $block['origin_candle_open_time'],
                    'strength' => $block['strength'],
                    'volume' => $block['volume'],
                    'mitigated' => $block['mitigated'] ? 1 : 0,
                    'mitigated_at' => $block['mitigated'] ? date('Y-m-d H:i:s') : null,
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function active(int $symbolId, string $timeframe): array
    {
        return $this->db->select(
            'SELECT * FROM order_blocks WHERE symbol_id = :symbol_id AND timeframe = :timeframe AND mitigated = 0 ORDER BY strength DESC',
            ['symbol_id' => $symbolId, 'timeframe' => $timeframe],
        );
    }
}
