<?php

declare(strict_types=1);

namespace App\Database\Repositories;

/**
 * One row per symbol holding its latest ticker snapshot plus the scanner's
 * universe-ranking output (volume_rank, is_in_universe). This — not a
 * history table — is what VolumeAnalyzer/MarketScanner read and write on
 * every cycle (spec #5).
 */
final class MarketDataRepository extends Repository
{
    /**
     * @param array<string, mixed> $ticker Ticker shape from ExchangeInterface
     */
    public function upsert(int $symbolId, array $ticker, ?float $volatility = null): void
    {
        $this->db->upsert('market_data', [
            'symbol_id' => $symbolId,
            'price' => $ticker['price'] ?? 0,
            'volume_24h' => $ticker['volume_24h'] ?? 0,
            'quote_volume_24h' => $ticker['quote_volume_24h'] ?? 0,
            'price_change_percent_24h' => $ticker['price_change_percent_24h'] ?? 0,
            'high_24h' => $ticker['high_24h'] ?? 0,
            'low_24h' => $ticker['low_24h'] ?? 0,
            'spread_percent' => $ticker['spread_percent'] ?? 0,
            'trades_count_24h' => $ticker['trades_count_24h'] ?? null,
            'volatility' => $volatility,
        ]);
    }

    public function setUniverseRank(int $symbolId, ?int $rank, bool $isInUniverse): void
    {
        $this->db->statement(
            'UPDATE market_data SET volume_rank = :rank, is_in_universe = :in_universe WHERE symbol_id = :symbol_id',
            ['rank' => $rank, 'in_universe' => $isInUniverse ? 1 : 0, 'symbol_id' => $symbolId],
        );
    }

    public function clearUniverseForExchangeSymbols(int $exchangeId): void
    {
        $this->db->statement(
            'UPDATE market_data md
             JOIN symbols s ON s.id = md.symbol_id
             SET md.is_in_universe = 0, md.volume_rank = NULL
             WHERE s.exchange_id = :exchange_id',
            ['exchange_id' => $exchangeId],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $symbolId): ?array
    {
        return $this->db->selectOne('SELECT * FROM market_data WHERE symbol_id = :symbol_id', ['symbol_id' => $symbolId]);
    }

    public function universeCount(): int
    {
        $row = $this->db->selectOne('SELECT COUNT(*) AS c FROM market_data WHERE is_in_universe = 1');

        return (int) ($row['c'] ?? 0);
    }
}
