<?php

declare(strict_types=1);

namespace App\Indicators;

use InvalidArgumentException;

/**
 * The plugin registry: built-in indicators register themselves here by
 * name, and Strategy config (spec #7/#36) refers to them by that name —
 * "RSI", "EMA", etc — never by class. A new indicator is one class plus
 * one register() call, nothing else changes.
 *
 * Indicators are recalculated over the in-memory candle window
 * (CandleManager::recent(), a single bounded DB read) on every call rather
 * than maintaining persistent recursive state across process restarts —
 * for ~200 symbols this is sub-millisecond per indicator, so it is not the
 * "recompute everything from scratch" spec #41 warns against (that refers
 * to re-fetching full history from the exchange/DB, which CandleManager
 * already avoids).
 */
final class IndicatorManager
{
    /** @var array<string, IndicatorInterface> */
    private array $indicators = [];

    public function __construct()
    {
        foreach ($this->builtIns() as $indicator) {
            $this->register($indicator);
        }
    }

    public function register(IndicatorInterface $indicator): void
    {
        $this->indicators[strtoupper($indicator->name())] = $indicator;
    }

    public function has(string $name): bool
    {
        return isset($this->indicators[strtoupper($name)]);
    }

    public function get(string $name): IndicatorInterface
    {
        $indicator = $this->indicators[strtoupper($name)] ?? null;
        if ($indicator === null) {
            throw new InvalidArgumentException("Unknown indicator [$name].");
        }

        return $indicator;
    }

    /**
     * @return string[]
     */
    public function names(): array
    {
        return array_keys($this->indicators);
    }

    /**
     * @param array<int, array<string, mixed>> $candles
     * @param array<string, mixed> $params
     * @return array<int, array<string, float|null>>
     */
    public function calculate(string $name, array $candles, array $params = []): array
    {
        return $this->get($name)->calculate($candles, $params);
    }

    /**
     * Runs several indicators over the same candle set at once.
     *
     * @param array<string, array<string, mixed>> $specs indicator name => params
     * @param array<int, array<string, mixed>> $candles
     * @return array<string, array<int, array<string, float|null>>> indicator name => calculate() output
     */
    public function calculateMany(array $specs, array $candles): array
    {
        $results = [];
        foreach ($specs as $name => $params) {
            $results[strtoupper($name)] = $this->calculate($name, $candles, $params);
        }

        return $results;
    }

    /**
     * Convenience: the latest (most recent candle's) value(s) for an
     * indicator — what Strategy conditions almost always actually want.
     *
     * @param array<int, array<string, mixed>> $candles
     * @param array<string, mixed> $params
     * @return array<string, float|null>|null
     */
    public function latest(string $name, array $candles, array $params = []): ?array
    {
        $series = $this->calculate($name, $candles, $params);

        return $series === [] ? null : end($series);
    }

    /**
     * @return IndicatorInterface[]
     */
    private function builtIns(): array
    {
        return [
            new SMA(),
            new EMA(),
            new RSI(),
            new MACD(),
            new ATR(),
            new Volume(),
            new VWAP(),
            new BollingerBands(),
            new ADX(),
            new Stochastic(),
        ];
    }
}
