<?php

declare(strict_types=1);

namespace App\Signal;

use App\Database\Repositories\SettingsRepository;

/**
 * The full pre-send checklist from spec #37. Any failed check means
 * "DO NOT SEND" — SignalEngine never dispatches a candidate that fails
 * here, and every failure reason is returned so it can be logged.
 * "Check Strategy Conditions" itself is enforced upstream by
 * StrategyEngine/ConfluenceEngine (a candidate cannot exist without
 * passing required confluences + min score) — re-checking the score here
 * is still done as defense in depth in case settings changed between
 * candidate generation and validation.
 */
final class SignalValidator
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SignalCooldown $cooldown,
    ) {
    }

    /**
     * @param array<string, mixed> $symbolRow
     * @param array<string, mixed>|null $marketDataRow
     * @return array{valid: bool, failures: string[]}
     */
    public function validate(SignalCandidate $candidate, array $symbolRow, ?array $marketDataRow): array
    {
        $failures = [];

        if (($symbolRow['status'] ?? null) !== 'trading' || (int) ($symbolRow['is_active'] ?? 0) !== 1) {
            $failures[] = 'symbol_not_tradeable';
        }

        if ($marketDataRow === null) {
            $failures[] = 'market_data_missing';
        } else {
            $minLiquidity = (float) $this->settings->get('min_liquidity', 0.0);
            if ((float) $marketDataRow['quote_volume_24h'] < $minLiquidity) {
                $failures[] = 'liquidity_too_low';
            }

            $maxSpread = (float) $this->settings->get('max_spread_percent', 1.0);
            if ($maxSpread > 0 && (float) $marketDataRow['spread_percent'] > $maxSpread) {
                $failures[] = 'spread_too_wide';
            }

            $maxAgeSeconds = (int) $this->settings->get('max_market_data_age_seconds', 300);
            $ageSeconds = time() - strtotime((string) $marketDataRow['captured_at']);
            if ($ageSeconds > $maxAgeSeconds) {
                $failures[] = 'stale_market_data';
            }
        }

        $minScore = (int) $this->settings->get('min_signal_score', 75);
        if ($candidate->score < $minScore) {
            $failures[] = 'score_below_minimum';
        }

        if ($this->cooldown->isBlocked($candidate)) {
            $failures[] = 'duplicate_or_cooldown';
        }

        $failures = [...$failures, ...$this->validateEntryAndTargets($candidate)];

        return ['valid' => $failures === [], 'failures' => $failures];
    }

    /**
     * @return string[]
     */
    private function validateEntryAndTargets(SignalCandidate $candidate): array
    {
        $failures = [];

        if ($candidate->entry <= 0 || $candidate->stopLoss <= 0) {
            $failures[] = 'invalid_entry_or_stop_loss';

            return $failures;
        }

        $isLong = $candidate->direction === 'LONG';

        if ($isLong && $candidate->stopLoss >= $candidate->entry) {
            $failures[] = 'stop_loss_wrong_side';
        }
        if (!$isLong && $candidate->stopLoss <= $candidate->entry) {
            $failures[] = 'stop_loss_wrong_side';
        }

        if ($candidate->takeProfits === []) {
            $failures[] = 'no_take_profits';

            return $failures;
        }

        $previous = $candidate->entry;
        foreach ($candidate->takeProfits as $tp) {
            if ($tp <= 0) {
                $failures[] = 'invalid_take_profit';

                break;
            }

            if ($isLong && $tp <= $previous) {
                $failures[] = 'take_profits_not_ascending';

                break;
            }
            if (!$isLong && $tp >= $previous) {
                $failures[] = 'take_profits_not_descending';

                break;
            }

            $previous = $tp;
        }

        return array_unique($failures);
    }
}
