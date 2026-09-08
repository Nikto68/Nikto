<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Timeframes are configuration strings throughout this app (spec #6: never
 * hard-coded to a fixed set) — this only parses "<number><unit>" (m/h/d/w)
 * into minutes so callers can compare/sort arbitrary timeframe strings,
 * e.g. to pick "the largest configured timeframe" as an HTF reference.
 */
final class Timeframe
{
    public static function minutes(string $timeframe): int
    {
        if (preg_match('/^(\d+)([mhdw])$/i', $timeframe, $m) !== 1) {
            return 0;
        }

        $value = (int) $m[1];

        return $value * match (strtolower($m[2])) {
            'm' => 1,
            'h' => 60,
            'd' => 1440,
            'w' => 10080,
            default => 0,
        };
    }

    /**
     * @param string[] $timeframes
     */
    public static function largest(array $timeframes): ?string
    {
        if ($timeframes === []) {
            return null;
        }

        usort($timeframes, static fn (string $a, string $b): int => self::minutes($b) <=> self::minutes($a));

        return $timeframes[0];
    }
}
