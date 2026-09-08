<?php

declare(strict_types=1);

namespace App\Strategy;

/**
 * What a Strategy hands back to StrategyEngine: a proposed trade idea plus
 * which named confluence factors fired. ConfluenceEngine turns
 * `confluenceFactors` into a score using the strategy's configured
 * weights — the Strategy itself never computes a score (spec #11/#12: that
 * is Confluence's job, kept separate on purpose).
 */
final class StrategyResult
{
    /**
     * @param string[] $confluenceFactors names matching keys in the strategy's configured weights
     * @param string[] $reasons human-readable explanations, used in the signal template's {reasons}
     * @param float[] $takeProfits
     * @param array<string, mixed> $meta zone/OB/FVG snapshots etc. carried through to Signal for audit
     */
    public function __construct(
        public readonly string $direction,
        public readonly float $entry,
        public readonly float $stopLoss,
        public readonly array $takeProfits,
        public readonly array $confluenceFactors,
        public readonly array $reasons,
        public readonly array $meta = [],
    ) {
    }
}
