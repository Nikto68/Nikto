<?php

declare(strict_types=1);

namespace App\Database\Repositories;

/**
 * The tradeable symbol universe as last reported by each exchange (spec
 * #5). Not a fixed 200 rows — every symbol an exchange lists is upserted
 * here; MarketDataRepository's `is_in_universe`/`volume_rank` flags decide
 * the actual Top-N the scanner works with.
 */
final class SymbolRepository extends Repository
{
    /**
     * @param array<string, mixed> $market Market shape from ExchangeInterface::getMarkets()
     */
    public function upsert(int $exchangeId, array $market): string
    {
        return $this->db->upsert('symbols', [
            'exchange_id' => $exchangeId,
            'symbol' => $market['symbol'],
            'base_asset' => $market['base_asset'],
            'quote_asset' => $market['quote_asset'],
            'market_type' => $market['market_type'] ?? 'spot',
            'status' => $market['status'] ?? 'trading',
            'is_active' => 1,
            'price_precision' => $market['price_precision'] ?? null,
            'qty_precision' => $market['qty_precision'] ?? null,
            'min_notional' => $market['min_notional'] ?? null,
            'last_seen_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Symbols the exchange no longer lists (delisted, or dropped from this
     * scan's response) are flagged inactive rather than deleted, so signal
     * history keeps a valid symbol_id FK.
     *
     * @param string[] $seenSymbols
     */
    public function deactivateMissing(int $exchangeId, array $seenSymbols): int
    {
        if ($seenSymbols === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($seenSymbols), '?'));
        $params = array_merge([$exchangeId], $seenSymbols);

        return $this->db->statement(
            "UPDATE symbols SET is_active = 0 WHERE exchange_id = ? AND is_active = 1 AND symbol NOT IN ($placeholders)",
            $params,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM symbols WHERE id = :id', ['id' => $id]);
    }

    /**
     * Every exchange's row for a symbol code (Test Signal's lookup, spec
     * #39, needs to disambiguate when the same symbol trades on more than
     * one configured exchange).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAcrossExchanges(string $symbol): array
    {
        return $this->db->select(
            'SELECT s.*, ex.code AS exchange_code FROM symbols s
             JOIN exchanges ex ON ex.id = s.exchange_id
             WHERE s.symbol = :symbol AND s.is_active = 1',
            ['symbol' => strtoupper($symbol)],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByExchangeAndSymbol(int $exchangeId, string $symbol): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM symbols WHERE exchange_id = :exchange_id AND symbol = :symbol',
            ['exchange_id' => $exchangeId, 'symbol' => $symbol],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activeByExchange(int $exchangeId, ?string $quoteAsset = null): array
    {
        $sql = "SELECT * FROM symbols WHERE exchange_id = :exchange_id AND is_active = 1 AND status = 'trading'";
        $params = ['exchange_id' => $exchangeId];

        if ($quoteAsset !== null) {
            $sql .= ' AND quote_asset = :quote_asset';
            $params['quote_asset'] = $quoteAsset;
        }

        return $this->db->select($sql, $params);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function universe(int $limit): array
    {
        // PDO with emulated prepares disabled cannot bind LIMIT as a
        // placeholder (MySQL's binary protocol requires it as a literal
        // integer), so it is cast and interpolated directly — safe since
        // it is forced through (int), never user-controlled SQL text.
        $limit = max(0, $limit);

        return $this->db->select(
            "SELECT s.*, md.volume_rank, md.price, md.volume_24h, md.quote_volume_24h
             FROM symbols s
             JOIN market_data md ON md.symbol_id = s.id
             WHERE md.is_in_universe = 1
             ORDER BY md.volume_rank ASC
             LIMIT {$limit}",
        );
    }
}
