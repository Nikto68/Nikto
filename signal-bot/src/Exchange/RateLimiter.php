<?php

declare(strict_types=1);

namespace App\Exchange;

/**
 * Simple sliding-window request limiter, one instance per exchange (see
 * config/exchanges.php `rate_limit`). AbstractExchange asks it "can I go
 * now, or how long until I can" before every REST call, so ~200 symbols
 * across multiple timeframes never burst past what an exchange allows
 * (spec #27).
 */
final class RateLimiter
{
    /** @var float[] unix timestamps (with microsecond precision) of recent requests */
    private array $timestamps = [];

    public function __construct(
        private readonly int $maxRequests,
        private readonly int $perSeconds,
    ) {
    }

    /**
     * Seconds to wait before the next request is allowed. 0.0 means go now.
     */
    public function waitTime(): float
    {
        $this->prune();

        if (count($this->timestamps) < $this->maxRequests) {
            return 0.0;
        }

        $oldest = $this->timestamps[0];
        $wait = ($oldest + $this->perSeconds) - microtime(true);

        return max(0.0, $wait);
    }

    public function recordRequest(): void
    {
        $this->timestamps[] = microtime(true);
        $this->prune();
    }

    private function prune(): void
    {
        $threshold = microtime(true) - $this->perSeconds;
        $this->timestamps = array_values(array_filter(
            $this->timestamps,
            static fn (float $ts): bool => $ts >= $threshold,
        ));
    }
}
