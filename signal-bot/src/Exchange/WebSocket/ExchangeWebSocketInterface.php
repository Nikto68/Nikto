<?php

declare(strict_types=1);

namespace App\Exchange\WebSocket;

/**
 * Real-time price/ticker feed for one exchange (spec #28's WebSocket half
 * of the hybrid). Every implementation — a real WS client or a REST-poll
 * shim — owns its own reconnect/backoff and must never let a dropped
 * connection propagate as an exception into the worker's event loop; it
 * just stops calling $onTicker until it reconnects.
 */
interface ExchangeWebSocketInterface
{
    /**
     * @param string[] $symbols
     * @param callable(array<string, mixed> $ticker): void $onTicker
     */
    public function start(array $symbols, callable $onTicker): void;

    public function stop(): void;

    public function isConnected(): bool;
}
