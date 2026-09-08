<?php

declare(strict_types=1);

namespace App\Strategy;

/**
 * Turns a Strategy's triggered confluence factors into a score using that
 * strategy's own configured weights (spec #12) — never hard-coded numbers.
 * Expects `strategies.config` shaped as:
 *
 *   {
 *     "weights": {"support": 20, "resistance": 20, "order_block": 25,
 *                 "fvg": 20, "volume": 10, "trend": 15},
 *     "required_confluences": ["order_block"],
 *     "min_score": 75
 *   }
 *
 * A factor with no configured weight contributes 0 — an unrecognized name
 * silently doing nothing is safer than an admin typo silently doing
 * everything.
 */
final class ConfluenceEngine
{
    /**
     * @param string[] $triggeredFactors
     * @param array<string, mixed> $strategyConfig
     * @return array{score: int, passes_required: bool, missing_required: string[]}
     */
    public function score(array $triggeredFactors, array $strategyConfig): array
    {
        $weights = $strategyConfig['weights'] ?? [];
        $required = $strategyConfig['required_confluences'] ?? [];

        $score = 0;
        foreach (array_unique($triggeredFactors) as $factor) {
            $score += (int) ($weights[$factor] ?? 0);
        }
        $score = max(0, min(100, $score));

        $missing = array_values(array_diff($required, $triggeredFactors));

        return [
            'score' => $score,
            'passes_required' => $missing === [],
            'missing_required' => $missing,
        ];
    }

    public function minScore(array $strategyConfig, int $default = 75): int
    {
        return (int) ($strategyConfig['min_score'] ?? $default);
    }
}
