<?php

declare(strict_types=1);

namespace App\Exchange;

use React\Promise\PromiseInterface;

/**
 * MEXC Spot REST adapter. MEXC's public spot v3 API is intentionally
 * Binance-compatible (same endpoint paths and response shapes), so this
 * mirrors Binance.php closely.
 *
 * MEXC has no stable public JSON WebSocket for spot tickers/klines at the
 * time of writing (its newer streams are protobuf-encoded and change
 * across versions) — supportsWebSocket() is false and the worker falls
 * back to REST polling for this exchange (spec #28's documented fallback
 * path), which keeps this adapter honest instead of guessing at a wire
 * format that can't be verified here. Flip it to true once a
 * MEXCWebSocketClient is written and verified against the live API.
 */
final class MEXC extends AbstractExchange
{
    private const INTERVAL_MAP = [
        '1m' => '1m',
        '5m' => '5m',
        '15m' => '15m',
        '30m' => '30m',
        '1h' => '60m',
        '4h' => '4h',
        '1d' => '1d',
    ];

    public function code(): string
    {
        return 'mexc';
    }

    public function supportsWebSocket(): bool
    {
        return false;
    }

    public function getMarkets(): PromiseInterface
    {
        $base = (string) $this->config['rest_base'];

        return $this->getJson("{$base}/api/v3/exchangeInfo")->then(
            fn (array $data): array => array_map(
                fn (array $s): array => $this->normalizeMarket($s),
                $data['symbols'] ?? [],
            ),
        );
    }

    public function getTicker(string $symbol): PromiseInterface
    {
        $base = (string) $this->config['rest_base'];

        return $this->getJson("{$base}/api/v3/ticker/24hr", ['symbol' => $symbol])
            ->then(fn (array $data): array => $this->normalizeTicker($data));
    }

    public function getTickers(array $symbols = []): PromiseInterface
    {
        $base = (string) $this->config['rest_base'];

        return $this->getJson("{$base}/api/v3/ticker/24hr")->then(function (array $data) use ($symbols): array {
            $tickers = array_map(fn (array $t): array => $this->normalizeTicker($t), $data);

            if ($symbols === []) {
                return $tickers;
            }

            $wanted = array_flip($symbols);

            return array_values(array_filter($tickers, static fn (array $t): bool => isset($wanted[$t['symbol']])));
        });
    }

    public function getKlines(string $symbol, string $interval, int $limit = 500, ?int $startTime = null): PromiseInterface
    {
        $base = (string) $this->config['rest_base'];

        $query = [
            'symbol' => $symbol,
            'interval' => self::INTERVAL_MAP[$interval] ?? $interval,
            'limit' => min($limit, 1000),
        ];
        if ($startTime !== null) {
            $query['startTime'] = $startTime;
        }

        return $this->getJson("{$base}/api/v3/klines", $query)->then(
            fn (array $rows): array => array_map(fn (array $row): array => $this->normalizeCandle($row), $rows),
        );
    }

    public function getOrderBook(string $symbol, int $limit = 100): PromiseInterface
    {
        $base = (string) $this->config['rest_base'];

        return $this->getJson("{$base}/api/v3/depth", ['symbol' => $symbol, 'limit' => $limit])->then(
            fn (array $data): array => [
                'bids' => array_map(static fn (array $l): array => [(float) $l[0], (float) $l[1]], $data['bids'] ?? []),
                'asks' => array_map(static fn (array $l): array => [(float) $l[0], (float) $l[1]], $data['asks'] ?? []),
            ],
        );
    }

    /**
     * @param array<string, mixed> $s
     * @return array<string, mixed>
     */
    private function normalizeMarket(array $s): array
    {
        $status = strtoupper((string) ($s['status'] ?? 'ENABLED'));

        return [
            'symbol' => $s['symbol'],
            'base_asset' => $s['baseAsset'],
            'quote_asset' => $s['quoteAsset'],
            'market_type' => 'spot',
            'status' => in_array($status, ['ENABLED', 'TRADING', '1'], true) ? 'trading' : 'break',
            'price_precision' => isset($s['quotePrecision']) ? (int) $s['quotePrecision'] : null,
            'qty_precision' => isset($s['baseAssetPrecision']) ? (int) $s['baseAssetPrecision'] : null,
            'min_notional' => isset($s['quoteAmountPrecision']) ? (float) $s['quoteAmountPrecision'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $t
     * @return array<string, mixed>
     */
    private function normalizeTicker(array $t): array
    {
        $bid = (float) ($t['bidPrice'] ?? 0);
        $ask = (float) ($t['askPrice'] ?? 0);
        $mid = $bid > 0 && $ask > 0 ? ($bid + $ask) / 2 : (float) ($t['lastPrice'] ?? 0);

        return [
            'symbol' => $t['symbol'],
            'price' => (float) ($t['lastPrice'] ?? 0),
            'volume_24h' => (float) ($t['volume'] ?? 0),
            'quote_volume_24h' => (float) ($t['quoteVolume'] ?? 0),
            'price_change_percent_24h' => (float) ($t['priceChangePercent'] ?? 0),
            'high_24h' => (float) ($t['highPrice'] ?? 0),
            'low_24h' => (float) ($t['lowPrice'] ?? 0),
            'spread_percent' => $mid > 0 && $bid > 0 && $ask > 0 ? (($ask - $bid) / $mid) * 100 : 0.0,
            'trades_count_24h' => isset($t['count']) ? (int) $t['count'] : null,
        ];
    }

    /**
     * @param array<int, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeCandle(array $row): array
    {
        return [
            'open_time' => (int) $row[0],
            'open' => (float) $row[1],
            'high' => (float) $row[2],
            'low' => (float) $row[3],
            'close' => (float) $row[4],
            'volume' => (float) $row[5],
            'close_time' => (int) $row[6],
            'quote_volume' => isset($row[7]) ? (float) $row[7] : null,
            'trades_count' => isset($row[8]) ? (int) $row[8] : null,
            'is_closed' => true,
        ];
    }
}
