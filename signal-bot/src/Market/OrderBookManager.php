<?php

declare(strict_types=1);

namespace App\Market;

use App\Exchange\ExchangeInterface;
use App\Support\TtlCache;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

/**
 * Order book snapshots are only ever needed fresh-enough for a spread/depth
 * check on one candidate symbol (SignalValidator, spec #37) — never for the
 * whole universe — so a short-TTL in-process cache is enough; no DB table
 * for this (spec #27 "Market Data را Cache کن").
 */
final class OrderBookManager
{
    private const DEFAULT_TTL_SECONDS = 5.0;

    public function __construct(
        private readonly TtlCache $cache,
    ) {
    }

    /**
     * @return PromiseInterface<array{bids: array<int, array{0: float, 1: float}>, asks: array<int, array{0: float, 1: float}>}>
     */
    public function get(ExchangeInterface $exchange, string $symbol, int $limit = 100, float $ttlSeconds = self::DEFAULT_TTL_SECONDS): PromiseInterface
    {
        $key = $exchange->code() . ':' . $symbol . ':' . $limit;
        $cached = $this->cache->get($key);

        if ($cached !== null) {
            return resolve($cached);
        }

        return $exchange->getOrderBook($symbol, $limit)->then(function (array $book) use ($key, $ttlSeconds): array {
            $this->cache->set($key, $book, $ttlSeconds);

            return $book;
        });
    }
}
