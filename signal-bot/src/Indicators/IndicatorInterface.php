<?php

declare(strict_types=1);

namespace App\Indicators;

/**
 * One indicator = one plugin. Adding a new one later is "write a class
 * implementing this + register it in IndicatorManager/the `indicators`
 * table" — nothing else in the strategy/scanner layers changes (spec #7).
 *
 * Formulas here (EMA, RSI, MACD, ...) are standard, objective technical-
 * analysis math, not trading rules — when should they cause a LONG/SHORT
 * signal, at what thresholds, is `for the user to specify later` per spec
 * #7/#36 and lives in Strategy config, not here.
 */
interface IndicatorInterface
{
    /**
     * Short unique identifier, e.g. "EMA", "RSI", "MACD".
     */
    public function name(): string;

    /**
     * @return array<string, mixed>
     */
    public function defaultParams(): array;

    /**
     * @param array<int, array<string, mixed>> $candles oldest-to-newest, standard Candle shape
     * @param array<string, mixed> $params merged over defaultParams()
     * @return array<int, array<string, float|null>> same length/order as $candles; null until enough history exists
     */
    public function calculate(array $candles, array $params = []): array;
}
