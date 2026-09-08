<?php

declare(strict_types=1);

namespace App\Signal;

/**
 * The artifact that crosses from the Strategy/Confluence stage into the
 * Signal stage (spec #11's pipeline: "... Confluence -> Signal Candidate
 * -> Validation -> Signal -> Telegram"). Not yet validated, deduplicated,
 * or persisted — SignalValidator/SignalEngine (Phase 11) do that next.
 */
final class SignalCandidate
{
    /**
     * @param float[] $takeProfits
     * @param string[] $reasons
     * @param array<string, mixed> $zones
     * @param array<string, mixed> $indicators
     */
    public function __construct(
        public readonly string $exchangeCode,
        public readonly int $exchangeId,
        public readonly string $symbol,
        public readonly int $symbolId,
        public readonly string $strategyCode,
        public readonly ?int $strategyId,
        public readonly string $direction,
        public readonly string $timeframe,
        public readonly float $entry,
        public readonly float $stopLoss,
        public readonly array $takeProfits,
        public readonly int $score,
        public readonly array $reasons,
        public readonly array $zones,
        public readonly array $indicators,
    ) {
    }

    public function riskReward(): ?float
    {
        if ($this->takeProfits === []) {
            return null;
        }

        $risk = abs($this->entry - $this->stopLoss);
        if ($risk <= 0.0) {
            return null;
        }

        $reward = abs($this->takeProfits[0] - $this->entry);

        return round($reward / $risk, 3);
    }

    /**
     * exchange + symbol + direction + strategy + timeframe, rounded to the
     * nearest zone the entry sits in — see SignalCooldown (Phase 11) for
     * how this is used to suppress repeat signals for the same setup.
     */
    public function fingerprint(): string
    {
        $zoneKey = isset($this->zones['primary_zone_id']) ? (string) $this->zones['primary_zone_id'] : 'na';

        return implode(':', [
            $this->exchangeCode,
            $this->symbol,
            $this->direction,
            $this->strategyCode,
            $this->timeframe,
            $zoneKey,
        ]);
    }
}
