<?php

declare(strict_types=1);

namespace App\Strategy;

/**
 * One trading strategy = one class implementing this. This is the
 * placeholder the architecture keeps open for the user's own rules (spec
 * #35/#36) — StrategyEngine discovers and runs whatever implements this
 * interface and is marked active in the `strategies` table; nothing about
 * the Exchange, Market, Indicator, or Confluence layers needs to change
 * when a new one is added.
 *
 * IMPORTANT: no concrete strategy with real entry/exit conditions ships in
 * this codebase yet — see ExampleStrategy.php for a structural template
 * only. Trading rules are supplied by the user and translated into a real
 * implementation of this interface (spec #36's "وقتی من قوانین دقیق را
 * دادم، آنها را دقیقاً تبدیل به Code کن").
 */
interface StrategyInterface
{
    /**
     * Matches the `code` column in the `strategies` table.
     */
    public function code(): string;

    /**
     * Which timeframes this strategy needs candles/indicators for, e.g.
     * ['htf' => '4h', 'confirmation' => '1h', 'entry' => '15m']. Keys are
     * free-form labels the strategy itself defines and uses in evaluate();
     * StrategyEngine only uses the values to know what to fetch.
     *
     * @return array<string, string>
     */
    public function timeframes(): array;

    /**
     * @return StrategyResult|null null = no trade idea for this symbol right now
     */
    public function evaluate(StrategyContext $context): ?StrategyResult;
}
