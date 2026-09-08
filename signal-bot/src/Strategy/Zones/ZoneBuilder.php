<?php

declare(strict_types=1);

namespace App\Strategy\Zones;

/**
 * Merges every detector's candidate zones into composite Zones (spec #8):
 * overlapping or near-adjacent candidates of the same type combine into
 * one zone whose strength reflects how many independent methods agree —
 * that confluence is the entire point of running several detectors.
 */
final class ZoneBuilder
{
    /**
     * @param array<int, array{type: string, high: float, low: float, strength: float, touches: int, volume: ?float, method: string}> $candidates
     * @return array<int, array{type: string, high: float, low: float, strength: float, touches: int, volume: float, methods: string[]}>
     */
    public function build(array $candidates, float $mergeTolerancePercent = 0.3): array
    {
        $byType = ['support' => [], 'resistance' => []];
        foreach ($candidates as $candidate) {
            $byType[$candidate['type']][] = $candidate;
        }

        $zones = [];
        foreach ($byType as $type => $group) {
            $zones = [...$zones, ...$this->mergeGroup($group, $mergeTolerancePercent)];
        }

        usort($zones, static fn (array $a, array $b): int => $b['strength'] <=> $a['strength']);

        return $zones;
    }

    /**
     * @param array<int, array<string, mixed>> $group
     * @return array<int, array<string, mixed>>
     */
    private function mergeGroup(array $group, float $tolerancePercent): array
    {
        if ($group === []) {
            return [];
        }

        usort($group, static fn (array $a, array $b): int => $a['low'] <=> $b['low']);

        $merged = [];
        $current = $group[0];

        for ($i = 1; $i < count($group); $i++) {
            $next = $group[$i];
            $mid = ($current['high'] + $current['low']) / 2;
            $tolerance = $mid * ($tolerancePercent / 100);

            if ($next['low'] <= $current['high'] + $tolerance) {
                $current = $this->combine($current, $next);

                continue;
            }

            $merged[] = $this->finalize($current);
            $current = $next;
        }
        $merged[] = $this->finalize($current);

        return $merged;
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array<string, mixed>
     */
    private function combine(array $a, array $b): array
    {
        $methods = is_array($a['method'] ?? null) ? $a['method'] : [$a['method']];
        if (!in_array($b['method'], $methods, true)) {
            $methods[] = $b['method'];
        }

        return [
            'type' => $a['type'],
            'high' => max($a['high'], $b['high']),
            'low' => min($a['low'], $b['low']),
            'strength' => $a['strength'] + $b['strength'],
            'touches' => $a['touches'] + $b['touches'],
            'volume' => ($a['volume'] ?? 0) + ($b['volume'] ?? 0),
            'method' => $methods,
        ];
    }

    /**
     * @param array<string, mixed> $zone
     * @return array<string, mixed>
     */
    private function finalize(array $zone): array
    {
        $methods = is_array($zone['method']) ? $zone['method'] : [$zone['method']];
        $confluenceBonus = (count($methods) - 1) * 10.0;

        return [
            'type' => $zone['type'],
            'high' => $zone['high'],
            'low' => $zone['low'],
            'strength' => min(100.0, $zone['strength'] + $confluenceBonus),
            'touches' => $zone['touches'],
            'volume' => (float) $zone['volume'],
            'methods' => array_values(array_unique($methods)),
        ];
    }
}
