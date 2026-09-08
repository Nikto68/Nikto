<?php

declare(strict_types=1);

namespace App\Database\Repositories;

final class FvgRepository extends Repository
{
    /**
     * @param array<int, array<string, mixed>> $gaps
     */
    public function upsertBatch(int $symbolId, array $gaps): void
    {
        foreach ($gaps as $gap) {
            // filled_at uses COALESCE for the same reason as
            // OrderBlockRepository::upsertBatch() — stamped once, not
            // reset to "now" on every re-scan while still filled.
            $this->db->statement(
                'INSERT INTO fvgs
                    (symbol_id, timeframe, type, high, low, midpoint, size, origin_candle_open_time, filled, partially_filled, filled_at)
                 VALUES
                    (:symbol_id, :timeframe, :type, :high, :low, :midpoint, :size, :origin_candle_open_time, :filled, :partially_filled, :filled_at)
                 ON DUPLICATE KEY UPDATE
                    high = VALUES(high),
                    low = VALUES(low),
                    midpoint = VALUES(midpoint),
                    size = VALUES(size),
                    filled = VALUES(filled),
                    partially_filled = VALUES(partially_filled),
                    filled_at = COALESCE(filled_at, VALUES(filled_at))',
                [
                    'symbol_id' => $symbolId,
                    'timeframe' => $gap['timeframe'],
                    'type' => $gap['type'],
                    'high' => $gap['high'],
                    'low' => $gap['low'],
                    'midpoint' => $gap['midpoint'],
                    'size' => $gap['size'],
                    'origin_candle_open_time' => $gap['origin_candle_open_time'],
                    'filled' => $gap['filled'] ? 1 : 0,
                    'partially_filled' => $gap['partially_filled'] ? 1 : 0,
                    'filled_at' => $gap['filled'] ? date('Y-m-d H:i:s') : null,
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
            'SELECT * FROM fvgs WHERE symbol_id = :symbol_id AND timeframe = :timeframe AND filled = 0 ORDER BY origin_candle_open_time DESC',
            ['symbol_id' => $symbolId, 'timeframe' => $timeframe],
        );
    }
}
