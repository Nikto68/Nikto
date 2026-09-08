<?php

declare(strict_types=1);

namespace App\Strategy;

/**
 * In-memory map of strategy code => StrategyInterface instance. Deliberately
 * separate from the `strategies` DB table (StrategyRepository): the table
 * holds configuration (is it active, what score does it need, ...) and can
 * be edited from the admin panel; this registry holds the actual PHP
 * objects, wired up once at boot in StrategyServiceProvider.
 */
final class StrategyRegistry
{
    /** @var array<string, StrategyInterface> */
    private array $strategies = [];

    public function register(StrategyInterface $strategy): void
    {
        $this->strategies[$strategy->code()] = $strategy;
    }

    public function has(string $code): bool
    {
        return isset($this->strategies[$code]);
    }

    public function get(string $code): ?StrategyInterface
    {
        return $this->strategies[$code] ?? null;
    }

    /**
     * @return StrategyInterface[]
     */
    public function all(): array
    {
        return $this->strategies;
    }
}
