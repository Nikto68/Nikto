<?php

declare(strict_types=1);

namespace App\Signal;

use App\Database\Repositories\SettingsRepository;
use App\Database\Repositories\SignalRepository;

/**
 * Deduplication + cooldown (spec #14): the same exchange+symbol+direction+
 * strategy+timeframe+zone (SignalCandidate::fingerprint()) must not fire
 * again while an earlier signal with that fingerprint is still new/active,
 * nor within SIGNAL_COOLDOWN seconds of the last one closing.
 */
final class SignalCooldown
{
    public function __construct(
        private readonly SignalRepository $signals,
        private readonly SettingsRepository $settings,
    ) {
    }

    public function isBlocked(SignalCandidate $candidate): bool
    {
        $existing = $this->signals->latestByFingerprint($candidate->fingerprint());
        if ($existing === null) {
            return false;
        }

        if (in_array($existing['status'], ['new', 'active'], true)) {
            return true;
        }

        $cooldownSeconds = (int) $this->settings->get('signal_cooldown_seconds', 1800);
        $reference = $existing['closed_at'] ?? $existing['created_at'];
        $elapsed = time() - strtotime((string) $reference);

        return $elapsed < $cooldownSeconds;
    }
}
