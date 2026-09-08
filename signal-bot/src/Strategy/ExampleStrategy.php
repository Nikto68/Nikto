<?php

declare(strict_types=1);

namespace App\Strategy;

/**
 * TEMPLATE ONLY — not registered, not seeded into the `strategies` table,
 * never runs. This exists purely to show the shape of a real
 * StrategyInterface implementation: how to read multi-timeframe candles,
 * indicators, zones, order blocks and FVGs from StrategyContext, and how
 * to hand back a StrategyResult with named confluence factors for
 * ConfluenceEngine to score.
 *
 * Copy this file, rename the class, and replace evaluate() with the real
 * entry/exit conditions once they are supplied (spec #36) — do not use
 * this as a live strategy, it deliberately always returns null.
 */
final class ExampleStrategy implements StrategyInterface
{
    public function code(): string
    {
        return 'example_template';
    }

    /**
     * @return array<string, string>
     */
    public function timeframes(): array
    {
        return [
            'htf' => '4h',
            'confirmation' => '1h',
            'entry' => '15m',
        ];
    }

    public function evaluate(StrategyContext $context): ?StrategyResult
    {
        // --- Illustrative reads only, not real conditions ---
        $htfCandles = $context->candles('4h');
        $entryCandles = $context->candles('15m');
        if ($htfCandles === [] || $entryCandles === []) {
            return null;
        }

        $rsi = $context->indicator('RSI', '15m', ['period' => 14]);
        $nearestSupport = $context->nearestZones('1h', 'support', 1);
        $orderBlocks = $context->orderBlocks('1h');
        $fvgs = $context->fvgs('15m');

        // A real strategy would check concrete conditions here (HTF trend
        // direction, price at a zone/order block, indicator confirmation,
        // volume confirmation, ...) and only then build a StrategyResult.
        // This template intentionally never does, so it can never
        // accidentally fire in production.
        unset($rsi, $nearestSupport, $orderBlocks, $fvgs);

        return null;
    }
}
