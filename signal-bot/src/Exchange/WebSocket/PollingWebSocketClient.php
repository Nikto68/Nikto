<?php

declare(strict_types=1);

namespace App\Exchange\WebSocket;

use App\Exchange\ExchangeInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use Throwable;

/**
 * REST-poll shim implementing the same interface as a real WebSocket
 * client, for exchanges without one (spec #28's documented fallback:
 * "WebSocket where possible, REST otherwise" — driven by the worker's own
 * internal timer, never cron). MarketWorker treats every exchange
 * identically; it never needs to know MEXC/Wallex are polled instead of
 * pushed.
 */
final class PollingWebSocketClient implements ExchangeWebSocketInterface
{
    private ?TimerInterface $timer = null;

    /** @var string[] */
    private array $symbols = [];

    private bool $lastPollSucceeded = false;

    public function __construct(
        private readonly ExchangeInterface $exchange,
        private readonly LoggerInterface $logger,
        private readonly int $intervalSeconds = 5,
    ) {
    }

    /**
     * @param string[] $symbols
     */
    public function start(array $symbols, callable $onTicker): void
    {
        $this->symbols = $symbols;
        $this->timer = Loop::addPeriodicTimer($this->intervalSeconds, function () use ($onTicker): void {
            $this->poll($onTicker);
        });

        $this->poll($onTicker);
    }

    public function stop(): void
    {
        if ($this->timer !== null) {
            Loop::cancelTimer($this->timer);
            $this->timer = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->timer !== null && $this->lastPollSucceeded;
    }

    /**
     * @param callable(array<string, mixed>): void $onTicker
     */
    private function poll(callable $onTicker): void
    {
        if ($this->symbols === []) {
            return;
        }

        $this->exchange->getTickers($this->symbols)->then(
            function (array $tickers) use ($onTicker): void {
                $this->lastPollSucceeded = true;
                foreach ($tickers as $ticker) {
                    $onTicker($ticker);
                }
            },
            function (Throwable $e): void {
                $this->lastPollSucceeded = false;
                $this->logger->warning('Polling ticker fetch failed', [
                    'exchange' => $this->exchange->code(),
                    'exception' => $e->getMessage(),
                ]);
            },
        );
    }
}
