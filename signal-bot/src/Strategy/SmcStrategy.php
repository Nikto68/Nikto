<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Indicators\IndicatorMath;
use App\Strategy\Smc\LiquidityZones;
use App\Strategy\Smc\SmcZone;
use App\Strategy\Smc\SwingStructure;

/**
 * The real strategy translated from the user's combined SMC Pine Script
 * indicator (S/R multi-timeframe + Market Structure/BOS-CHoCH + Buyside/
 * Sellside Liquidity + Order Block/Supply-Demand + HH/LH/LL/HL). Per the
 * user's explicit direction:
 *
 *   - HTF (4h) Market Structure sets the trade direction: the most recent
 *     BOS/CHoCH break defines bullish -> LONG, bearish -> SHORT.
 *   - A signal only fires while price is at/near an unmitigated Order
 *     Block / Supply-Demand zone (4h or 1h) matching that direction —
 *     this is the actual trigger, not just structure alone.
 *   - Entry = the zone's near edge, Stop Loss = just beyond its far edge
 *     (ATR-buffered), Take Profit = the next opposing 1D/1W S/R level or
 *     liquidity pool beyond entry in the trade's direction, per the
 *     user's explicit answer ("Entry بر اساس Order Block/Supply-Demand،
 *     SL کمی پشت ناحیه، TP در سطح Liquidity/S/R بعدی").
 *   - 1h structure agreement, a liquidity sweep on the opposite side, and
 *     1D/1W S/R confluence each add a named confluence factor —
 *     ConfluenceEngine scores them using the weights configured on this
 *     strategy's `strategies.config` row (never hard-coded here).
 */
final class SmcStrategy implements StrategyInterface
{
    public function code(): string
    {
        return 'smc_confluence';
    }

    /**
     * @return array<string, string>
     */
    public function timeframes(): array
    {
        return [
            'htf' => '4h',
            'confirmation' => '1h',
            'entry' => '15m',
            'daily' => '1d',
            'weekly' => '1w',
        ];
    }

    public function evaluate(StrategyContext $context): ?StrategyResult
    {
        $htfCandles = $context->candles('4h');
        $confCandles = $context->candles('1h');
        $entryCandles = $context->candles('15m');

        if (count($htfCandles) < 60 || count($confCandles) < 60 || count($entryCandles) < 20) {
            return null;
        }

        $htfStructure = (new SwingStructure())->analyze($htfCandles, 3);
        if ($htfStructure['trend'] === null) {
            return null;
        }

        $direction = $htfStructure['trend'] === 'bullish' ? 'LONG' : 'SHORT';
        $wantZoneType = $direction === 'LONG' ? 'bullish' : 'bearish';

        $candidateZones = [
            ...(new SmcZone())->detect($htfCandles, '4h'),
            ...(new SmcZone())->detect($confCandles, '1h'),
        ];

        $zone = $this->nearestActiveZone($candidateZones, $wantZoneType, $context->currentPrice);
        if ($zone === null) {
            return null;
        }

        $reasons = [];
        $factors = ['order_block', 'trend'];
        $reasons[] = sprintf(
            '%s Order Block/Supply-Demand روی %s: [%s , %s]',
            $direction === 'LONG' ? 'Bullish (Demand)' : 'Bearish (Supply)',
            $zone['timeframe'],
            $this->fmt($zone['low']),
            $this->fmt($zone['high']),
        );
        $reasons[] = "جهت معامله بر اساس آخرین {$htfStructure['last_break_type']} در ساختار 4H: " . ($direction === 'LONG' ? 'صعودی' : 'نزولی');

        $confStructure = (new SwingStructure())->analyze($confCandles, 3);
        if ($confStructure['trend'] === $htfStructure['trend']) {
            $factors[] = 'structure_alignment';
            $reasons[] = 'ساختار تایم‌فریم 1H هم‌جهت با 4H است.';
        }

        $liquidity = (new LiquidityZones())->analyze($confCandles);
        $liquiditySwept = $direction === 'LONG' ? $liquidity['sellside_swept_recently'] : $liquidity['buyside_swept_recently'];
        if ($liquiditySwept) {
            $factors[] = 'liquidity';
            $reasons[] = ($direction === 'LONG' ? 'Sellside' : 'Buyside') . ' Liquidity اخیراً Sweep شده (نشانه برگشت احتمالی).';
        }

        $srType = $direction === 'LONG' ? 'support' : 'resistance';
        $srZones = [...$context->nearestZones('1d', $srType, 2), ...$context->nearestZones('1w', $srType, 2)];
        $nearSr = $this->findOverlapping($srZones, $zone);
        if ($nearSr !== null) {
            $factors[] = $srType;
            $reasons[] = 'این ناحیه با یک سطح ' . ($srType === 'support' ? 'Support' : 'Resistance') . ' چندتایم‌فریمی (1D/1W) هم‌پوشانی دارد.';
        }

        $atr = $this->latestAtr($entryCandles, 14) ?? ($zone['high'] - $zone['low']);
        $bufferSize = $atr * 0.25;

        if ($direction === 'LONG') {
            $entry = $zone['high'];
            $stopLoss = $zone['low'] - $bufferSize;
        } else {
            $entry = $zone['low'];
            $stopLoss = $zone['high'] + $bufferSize;
        }

        $oppositeSrType = $direction === 'LONG' ? 'resistance' : 'support';
        $targetSrZones = [...$context->nearestZones('1d', $oppositeSrType, 3), ...$context->nearestZones('1w', $oppositeSrType, 3)];
        $liquidityTarget = $direction === 'LONG' ? $liquidity['buyside'] : $liquidity['sellside'];

        $takeProfits = $this->buildTakeProfits($direction, $entry, $stopLoss, $targetSrZones, $liquidityTarget);

        return new StrategyResult(
            direction: $direction,
            entry: $entry,
            stopLoss: $stopLoss,
            takeProfits: $takeProfits,
            confluenceFactors: $factors,
            reasons: $reasons,
            meta: [
                'zones' => [
                    'primary_zone_type' => $zone['type'],
                    'primary_zone_timeframe' => $zone['timeframe'],
                    'primary_zone_high' => $zone['high'],
                    'primary_zone_low' => $zone['low'],
                ],
                'indicators' => [
                    'htf_structure' => $htfStructure['trend'],
                    'htf_last_break' => $htfStructure['last_break_type'],
                    'confirmation_structure' => $confStructure['trend'],
                    'atr_entry_tf' => $atr,
                ],
            ],
        );
    }

    /**
     * @param array<int, array{type: string, high: float, low: float, timeframe: string, origin_candle_open_time: int, mitigated: bool}> $zones
     * @return array{type: string, high: float, low: float, timeframe: string, origin_candle_open_time: int, mitigated: bool}|null
     */
    private function nearestActiveZone(array $zones, string $type, float $price): ?array
    {
        $best = null;
        $bestDistance = null;

        foreach ($zones as $zone) {
            if ($zone['type'] !== $type || $zone['mitigated']) {
                continue;
            }

            $height = max(1e-12, $zone['high'] - $zone['low']);
            $tolerance = $height; // price can be up to one zone-height away and still count as "at" the zone
            $distance = $price < $zone['low']
                ? $zone['low'] - $price
                : ($price > $zone['high'] ? $price - $zone['high'] : 0.0);

            if ($distance > $tolerance) {
                continue;
            }

            if ($bestDistance === null || $distance < $bestDistance) {
                $best = $zone;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * @param array<int, array<string, mixed>> $srZones
     * @param array<string, mixed> $obZone
     * @return array<string, mixed>|null
     */
    private function findOverlapping(array $srZones, array $obZone): ?array
    {
        $height = max(1e-12, $obZone['high'] - $obZone['low']);
        $margin = $height * 1.5;

        foreach ($srZones as $sr) {
            if ((float) $sr['low'] <= $obZone['high'] + $margin && (float) $sr['high'] >= $obZone['low'] - $margin) {
                return $sr;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $srZones opposite-type S/R zones (candidate targets)
     * @param array{high: float, low: float}|null $liquidityZone
     * @return float[]
     */
    private function buildTakeProfits(string $direction, float $entry, float $stopLoss, array $srZones, ?array $liquidityZone): array
    {
        $isLong = $direction === 'LONG';
        $levels = [];

        foreach ($srZones as $sr) {
            $level = $isLong ? (float) $sr['low'] : (float) $sr['high'];
            if (($isLong && $level > $entry) || (!$isLong && $level < $entry)) {
                $levels[] = $level;
            }
        }

        if ($liquidityZone !== null) {
            $level = $isLong ? $liquidityZone['low'] : $liquidityZone['high'];
            if (($isLong && $level > $entry) || (!$isLong && $level < $entry)) {
                $levels[] = $level;
            }
        }

        sort($levels);
        if (!$isLong) {
            $levels = array_reverse($levels);
        }

        // Merge levels that are within 0.1% of each other (near-duplicate targets).
        $levels = $this->dedupeClose($levels, $entry * 0.001);
        $levels = array_slice($levels, 0, 3);

        $risk = abs($entry - $stopLoss);
        $rMultiples = [1.5, 2.5, 4.0];
        $i = 0;
        while (count($levels) < 3) {
            $multiple = $rMultiples[$i] ?? (2.0 + $i);
            $levels[] = $isLong ? $entry + $risk * $multiple : $entry - $risk * $multiple;
            $i++;
        }

        return $levels;
    }

    /**
     * @param float[] $levels
     * @return float[]
     */
    private function dedupeClose(array $levels, float $minGap): array
    {
        $result = [];
        foreach ($levels as $level) {
            $last = end($result);
            if ($last === false || abs($level - $last) >= $minGap) {
                $result[] = $level;
            }
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $candles
     */
    private function latestAtr(array $candles, int $period): ?float
    {
        $atr = IndicatorMath::wilderSmooth(IndicatorMath::trueRange($candles), $period);
        $last = end($atr);

        return $last === false ? null : $last;
    }

    private function fmt(float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }
}
