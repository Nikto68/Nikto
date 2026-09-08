<?php

declare(strict_types=1);

namespace App\Exchange;

use React\Promise\PromiseInterface;

/**
 * Binance Spot REST adapter. Binance's own interval strings (1m, 5m, 15m,
 * 1h, 4h, 1d, ...) already match the timeframe strings used everywhere
 * else in this app, so no translation table is needed here.
 */
final class Binance extends AbstractExchange
{
    public function code(): string
    {
        return 'binance';
    }

    public function supportsWebSocket(): bool
    {
        return true;
    }

    public function getMarkets(): PromiseInterface
    {
        $base = (string) $this->config['rest_base'];

        return $this->getJson("{$base}/api/v3/exchangeInfo")->then(
            fn (array $data): array => array_map(
                fn (array $s): array => $this->normalizeMarket($s),
                array_filter($data['symbols'] ?? [], static fn (array $s): bool => ($s['isSpotTradingAllowed'] ?? true) === true),
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
            'interval' => $this->mapInterval($interval),
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

    private function mapInterval(string $interval): string
    {
        // Binance's own vocabulary already matches ours 1:1.
        return $interval;
    }

    /**
     * @param array<string, mixed> $s
     * @return array<string, mixed>
     */
    private function normalizeMarket(array $s): array
    {
        $priceFilter = $this->findFilter($s['filters'] ?? [], 'PRICE_FILTER');
        $lotSize = $this->findFilter($s['filters'] ?? [], 'LOT_SIZE');
        $notional = $this->findFilter($s['filters'] ?? [], 'NOTIONAL') ?? $this->findFilter($s['filters'] ?? [], 'MIN_NOTIONAL');

        return [
            'symbol' => $s['symbol'],
            'base_asset' => $s['baseAsset'],
            'quote_asset' => $s['quoteAsset'],
            'market_type' => 'spot',
            'status' => strtolower((string) ($s['status'] ?? 'TRADING')) === 'trading' ? 'trading' : 'break',
            'price_precision' => isset($priceFilter['tickSize']) ? $this->precisionFromStep((string) $priceFilter['tickSize']) : null,
            'qty_precision' => isset($lotSize['stepSize']) ? $this->precisionFromStep((string) $lotSize['stepSize']) : null,
            'min_notional' => isset($notional['minNotional']) ? (float) $notional['minNotional'] : null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $filters
     * @return array<string, mixed>|null
     */
    private function findFilter(array $filters, string $type): ?array
    {
        foreach ($filters as $filter) {
            if (($filter['filterType'] ?? null) === $type) {
                return $filter;
            }
        }

        return null;
    }

    private function precisionFromStep(string $step): int
    {
        $pos = strpos($step, '1');

        return $pos === false ? 0 : max(0, $pos - 1);
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
