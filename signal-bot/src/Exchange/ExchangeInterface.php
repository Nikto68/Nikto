<?php

declare(strict_types=1);

namespace App\Exchange;

use React\Promise\PromiseInterface;

/**
 * The one contract every exchange adapter implements. Nothing above this
 * layer (MarketScanner, Strategy, Indicators, ...) ever talks to a
 * concrete Binance/MEXC/Wallex class or builds an exchange-specific
 * request — they depend on this interface only (DIP), so a new exchange
 * is "write one class implementing this + one config/exchanges.php entry",
 * never a change to scanner/strategy code (spec #4).
 *
 * Every REST method returns a React PromiseInterface so the worker's event
 * loop is never blocked waiting on a single HTTP call; synchronous callers
 * (e.g. the admin panel's Test Signal, running inside a normal PHP-FPM
 * request) resolve it with React\Async\await().
 *
 * Standardized shapes (plain arrays, matching the `symbols`/`market_data`/
 * `candles` tables so persistence is a direct mapping):
 *
 * Market:  {symbol, base_asset, quote_asset, market_type, status,
 *           price_precision, qty_precision, min_notional}
 * Ticker:  {symbol, price, volume_24h, quote_volume_24h,
 *           price_change_percent_24h, high_24h, low_24h, spread_percent,
 *           trades_count_24h}
 * Candle:  {open_time, close_time, open, high, low, close, volume,
 *           quote_volume, trades_count, is_closed}
 * OrderBook: {bids: [[price, qty], ...], asks: [[price, qty], ...]}
 */
interface ExchangeInterface
{
    /**
     * Short unique identifier matching config/exchanges.php, e.g. "binance".
     */
    public function code(): string;

    /**
     * @return PromiseInterface<array<int, array<string, mixed>>> Market[]
     */
    public function getMarkets(): PromiseInterface;

    /**
     * @return PromiseInterface<array<string, mixed>> Ticker
     */
    public function getTicker(string $symbol): PromiseInterface;

    /**
     * Batch ticker fetch — always preferred over N single calls when the
     * universe is being ranked (spec #27). Empty array = "all symbols".
     *
     * @param string[] $symbols
     * @return PromiseInterface<array<int, array<string, mixed>>> Ticker[]
     */
    public function getTickers(array $symbols = []): PromiseInterface;

    /**
     * @param string $interval standard timeframe: 1m, 5m, 15m, 1h, 4h, 1d, ...
     * @return PromiseInterface<array<int, array<string, mixed>>> Candle[]
     */
    public function getKlines(string $symbol, string $interval, int $limit = 500, ?int $startTime = null): PromiseInterface;

    /**
     * @return PromiseInterface<array{bids: array<int, array{0: float, 1: float}>, asks: array<int, array{0: float, 1: float}>}>
     */
    public function getOrderBook(string $symbol, int $limit = 100): PromiseInterface;

    /**
     * Whether this adapter has a real WebSocket implementation. When false,
     * the worker relies entirely on REST polling for this exchange (spec
     * #28's fallback path) — MarketWorker doesn't need to know which.
     */
    public function supportsWebSocket(): bool;
}
