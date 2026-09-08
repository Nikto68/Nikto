<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimal in-process TTL cache. Used where a full database round trip
 * would be wasteful for data that is only ever needed "fresh enough"
 * within the current worker cycle (e.g. order book snapshots) — see
 * spec #27 "Market Data را Cache کن".
 */
final class TtlCache
{
    /** @var array<string, array{expires_at: float, value: mixed}> */
    private array $items = [];

    public function get(string $key): mixed
    {
        $entry = $this->items[$key] ?? null;
        if ($entry === null) {
            return null;
        }

        if ($entry['expires_at'] < microtime(true)) {
            unset($this->items[$key]);

            return null;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, float $ttlSeconds): void
    {
        $this->items[$key] = ['expires_at' => microtime(true) + $ttlSeconds, 'value' => $value];
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
}
