<?php

declare(strict_types=1);

namespace App\Database\Repositories;

/**
 * OHLCV history. bulkUpsert() batches many rows into one multi-row
 * INSERT ... ON DUPLICATE KEY UPDATE per call instead of one round trip per
 * candle — initial sync alone is ~200 symbols x 6 timeframes x up to 500
 * candles, so this matters (spec #41 performance).
 */
final class CandleRepository extends Repository
{
    private const BATCH_SIZE = 200;

    /**
     * @param array<int, array<string, mixed>> $candles Candle[] shape from ExchangeInterface::getKlines()
     */
    public function bulkUpsert(int $symbolId, string $timeframe, array $candles): void
    {
        foreach (array_chunk($candles, self::BATCH_SIZE) as $batch) {
            $this->upsertBatch($symbolId, $timeframe, $batch);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $batch
     */
    private function upsertBatch(int $symbolId, string $timeframe, array $batch): void
    {
        if ($batch === []) {
            return;
        }

        $columns = ['symbol_id', 'timeframe', 'open_time', 'close_time', 'open', 'high', 'low', 'close', 'volume', 'quote_volume', 'trades_count', 'is_closed'];
        $rowPlaceholders = [];
        $params = [];

        foreach ($batch as $i => $candle) {
            $rowPlaceholders[] = '(' . implode(', ', array_map(static fn (string $c): string => ":{$c}{$i}", $columns)) . ')';
            $params["symbol_id{$i}"] = $symbolId;
            $params["timeframe{$i}"] = $timeframe;
            $params["open_time{$i}"] = $candle['open_time'];
            $params["close_time{$i}"] = $candle['close_time'];
            $params["open{$i}"] = $candle['open'];
            $params["high{$i}"] = $candle['high'];
            $params["low{$i}"] = $candle['low'];
            $params["close{$i}"] = $candle['close'];
            $params["volume{$i}"] = $candle['volume'];
            $params["quote_volume{$i}"] = $candle['quote_volume'] ?? null;
            $params["trades_count{$i}"] = $candle['trades_count'] ?? null;
            $params["is_closed{$i}"] = ($candle['is_closed'] ?? true) ? 1 : 0;
        }

        $columnList = implode(', ', array_map(static fn (string $c): string => "`$c`", $columns));
        $updateList = implode(', ', array_map(
            static fn (string $c): string => "`$c` = VALUES(`$c`)",
            ['close_time', 'open', 'high', 'low', 'close', 'volume', 'quote_volume', 'trades_count', 'is_closed'],
        ));

        $sql = sprintf(
            'INSERT INTO candles (%s) VALUES %s ON DUPLICATE KEY UPDATE %s',
            $columnList,
            implode(', ', $rowPlaceholders),
            $updateList,
        );

        $this->db->statement($sql, $params);
    }

    /**
     * Latest known candle's open_time for this symbol/timeframe, so
     * CandleManager can fetch only what is missing instead of the full
     * history again.
     */
    public function latestOpenTime(int $symbolId, string $timeframe): ?int
    {
        $row = $this->db->selectOne(
            'SELECT MAX(open_time) AS latest FROM candles WHERE symbol_id = :symbol_id AND timeframe = :timeframe',
            ['symbol_id' => $symbolId, 'timeframe' => $timeframe],
        );

        return $row === null || $row['latest'] === null ? null : (int) $row['latest'];
    }

    /**
     * Oldest-to-newest, ready to feed straight into an indicator.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $symbolId, string $timeframe, int $limit): array
    {
        $limit = max(0, $limit);

        $rows = $this->db->select(
            "SELECT * FROM candles
             WHERE symbol_id = :symbol_id AND timeframe = :timeframe
             ORDER BY open_time DESC
             LIMIT {$limit}",
            ['symbol_id' => $symbolId, 'timeframe' => $timeframe],
        );

        return array_reverse($rows);
    }

    public function count(int $symbolId, string $timeframe): int
    {
        $row = $this->db->selectOne(
            'SELECT COUNT(*) AS c FROM candles WHERE symbol_id = :symbol_id AND timeframe = :timeframe',
            ['symbol_id' => $symbolId, 'timeframe' => $timeframe],
        );

        return (int) ($row['c'] ?? 0);
    }
}
