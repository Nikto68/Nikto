<?php

declare(strict_types=1);

namespace App\Support;

use React\Promise\PromiseInterface;
use Throwable;

use function React\Promise\all;
use function React\Promise\resolve;

/**
 * React\Promise\all() rejects the whole batch the moment any one promise
 * rejects. In the worker's per-cycle fan-out (candle sync across ~200
 * symbols, say), one symbol's exchange call failing must never lose the
 * results for the other 199 (spec #30) — settle() wraps every promise so
 * all() always resolves, and the caller decides what a failure means.
 */
final class PromiseUtil
{
    /**
     * @param PromiseInterface[] $promises
     * @return PromiseInterface<array<int, array{ok: bool, value: mixed, reason: ?Throwable}>>
     */
    public static function settle(array $promises): PromiseInterface
    {
        if ($promises === []) {
            return resolve([]);
        }

        $wrapped = array_map(
            static fn (PromiseInterface $p): PromiseInterface => $p->then(
                static fn (mixed $value): array => ['ok' => true, 'value' => $value, 'reason' => null],
                static fn (Throwable $e): array => ['ok' => false, 'value' => null, 'reason' => $e],
            ),
            $promises,
        );

        return all($wrapped);
    }
}
