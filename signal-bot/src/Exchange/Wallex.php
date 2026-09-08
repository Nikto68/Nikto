<?php

declare(strict_types=1);

namespace App\Exchange;

use React\Promise\PromiseInterface;

/**
 * Wallex REST adapter (api.wallex.ir). Wallex's `/v1/markets` endpoint
 * returns every symbol's static info AND its 24h stats in one call, which
 * doubles as both getMarkets() and a batch getTickers() source.
 *
 * Field names here follow Wallex's publicly documented v1 API as of this
 * writing; verify against https://api.wallex.ir/docs before going live —
 * this is the newest/least-standard of the three integrations.
 *
 * No public JSON WebSocket client is implemented here (Wallex's realtime
 * feed is Socket.IO-based, not plain WS) — supportsWebSocket() is false
 * and the worker uses REST polling for this exchange, same as MEXC.
 */
final class Wallex extends AbstractExchange
{
    private const RESOLUTION_MAP = [
        '1m' => '1',
        '5m' => '5',
        '15m' => '15',
        '1h' => '60',
        '4h' => '240',
        '1d' => '1D',
    ];

    public function code(): string
    {
        return 'wallex';
    }

    public function supportsWebSocket(): bool
    {
        return false;
    }

    public function getMarkets(): PromiseInterface
    {
        return $this->fetchMarketsRaw()->then(
            fn (array $symbols): array => array_map(fn (array $m): array => $this->normalizeMarket($m), array_values($symbols)),
        );
    }

    public function getTicker(string $symbol): PromiseInterface
    {
        return $this->fetchMarketsRaw()->then(function (array $symbols) use ($symbol): array {
            $market = $symbols[$symbol] ?? null;
            if ($market === null) {
                return [];
            }

            return $this->normalizeTicker($market);
        });
    }

    public function getTickers(array $symbols = []): PromiseInterface
    {
        return $this->fetchMarketsRaw()->then(function (array $allSymbols) use ($symbols): array {
            $tickers = array_map(fn (array $m): array => $this->normalizeTicker($m), array_values($allSymbols));

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
        $resolution = self::RESOLUTION_MAP[$interval] ?? $interval;
        $to = time();
        $from = $startTime !== null ? intdiv($startTime, 1000) : $to - ($limit * $this->intervalSeconds($interval));

        return $this->getJson("{$base}/v1/udf/history", [
            'symbol' => $symbol,
            'resolution' => $resolution,
            'from' => $from,
            'to' => $to,
        ])->then(function (array $data) use ($interval): array {
            if (($data['s'] ?? null) !== 'ok') {
                return [];
            }

            $candles = [];
            $count = count($data['t'] ?? []);
            $step = $this->intervalSeconds($interval);

            for ($i = 0; $i < $count; $i++) {
                $openTime = ((int) $data['t'][$i]) * 1000;
                $candles[] = [
                    'open_time' => $openTime,
                    'open' => (float) $data['o'][$i],
                    'high' => (float) $data['h'][$i],
                    'low' => (float) $data['l'][$i],
                    'close' => (float) $data['c'][$i],
                    'volume' => (float) ($data['v'][$i] ?? 0),
                    'close_time' => $openTime + ($step * 1000) - 1,
                    'quote_volume' => null,
                    'trades_count' => null,
                    'is_closed' => true,
                ];
            }

            return $candles;
        });
    }

    public function getOrderBook(string $symbol, int $limit = 100): PromiseInterface
    {
        $base = (string) $this->config['rest_base'];

        return $this->getJson("{$base}/v1/depth", ['symbol' => $symbol])->then(function (array $data) use ($limit): array {
            $result = $data['result'] ?? $data;

            $bids = array_map(
                static fn (array $l): array => [(float) $l['price'], (float) $l['quantity']],
                array_slice($result['bid'] ?? [], 0, $limit),
            );
            $asks = array_map(
                static fn (array $l): array => [(float) $l['price'], (float) $l['quantity']],
                array_slice($result['ask'] ?? [], 0, $limit),
            );

            return ['bids' => $bids, 'asks' => $asks];
        });
    }

    /**
     * @return PromiseInterface<array<string, array<string, mixed>>>
     */
    private function fetchMarketsRaw(): PromiseInterface
    {
        $base = (string) $this->config['rest_base'];

        return $this->getJson("{$base}/v1/markets")->then(
            fn (array $data): array => $data['result']['symbols'] ?? [],
        );
    }

    /**
     * @param array<string, mixed> $m
     * @return array<string, mixed>
     */
    private function normalizeMarket(array $m): array
    {
        return [
            'symbol' => $m['symbol'],
            'base_asset' => $m['baseAsset'] ?? $m['base_asset'] ?? '',
            'quote_asset' => $m['quoteAsset'] ?? $m['quote_asset'] ?? '',
            'market_type' => 'spot',
            'status' => ($m['is_enable'] ?? $m['enable'] ?? true) ? 'trading' : 'break',
            'price_precision' => isset($m['price_precision']) ? (int) $m['price_precision'] : null,
            'qty_precision' => isset($m['quantity_precision']) ? (int) $m['quantity_precision'] : null,
            'min_notional' => isset($m['min_qty']) ? (float) $m['min_qty'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $m
     * @return array<string, mixed>
     */
    private function normalizeTicker(array $m): array
    {
        $stats = $m['stats'] ?? $m;
        $bid = (float) ($stats['bidPrice'] ?? 0);
        $ask = (float) ($stats['askPrice'] ?? 0);
        $last = (float) ($stats['lastPrice'] ?? 0);
        $mid = $bid > 0 && $ask > 0 ? ($bid + $ask) / 2 : $last;

        return [
            'symbol' => $m['symbol'],
            'price' => $last,
            'volume_24h' => (float) ($stats['24h_volume'] ?? 0),
            'quote_volume_24h' => (float) ($stats['24h_quoteVolume'] ?? 0),
            'price_change_percent_24h' => (float) ($stats['24h_ch'] ?? 0),
            'high_24h' => (float) ($stats['24h_high'] ?? 0),
            'low_24h' => (float) ($stats['24h_low'] ?? 0),
            'spread_percent' => $mid > 0 && $bid > 0 && $ask > 0 ? (($ask - $bid) / $mid) * 100 : 0.0,
            'trades_count_24h' => null,
        ];
    }

    private function intervalSeconds(string $interval): int
    {
        return match ($interval) {
            '1m' => 60,
            '5m' => 300,
            '15m' => 900,
            '1h' => 3600,
            '4h' => 14400,
            '1d' => 86400,
            default => 60,
        };
    }
}
