<?php

declare(strict_types=1);

namespace App\Strategy\Smc;

use App\Indicators\IndicatorMath;

/**
 * Buyside/Sellside liquidity, translated from the source Pine script's
 * "sina" section: swing highs (resp. lows) that cluster within an
 * ATR-scaled margin of the most recent swing are treated as one pool of
 * resting stop/breakout orders (equal-highs/equal-lows). `margin =
 * atr / liqMar` in the source, with default liqMar ≈ 1.449 — expressed
 * here directly as an ATR fraction (≈0.69) so the meaning is explicit.
 *
 * A "sweep" is the classic SMC pattern this feeds into a Strategy for:
 * price wicks through the pool (grabbing the resting orders) and closes
 * back on the other side — often the trigger for a reversal in the
 * opposite direction.
 */
final class LiquidityZones
{
    /**
     * @param array<int, array<string, mixed>> $candles oldest-to-newest
     * @param array<string, mixed> $params
     * @return array{
     *     buyside: ?array{high: float, low: float},
     *     sellside: ?array{high: float, low: float},
     *     buyside_swept_recently: bool,
     *     sellside_swept_recently: bool,
     * }
     */
    public function analyze(array $candles, array $params = []): array
    {
        $swingLookback = (int) ($params['swing_lookback'] ?? 3);
        $atrPeriod = (int) ($params['atr_period'] ?? 10);
        $marginAtrFraction = (float) ($params['margin_atr_fraction'] ?? 0.69);
        $minTouches = (int) ($params['min_touches'] ?? 3);
        $sweepLookbackBars = (int) ($params['sweep_lookback_bars'] ?? 10);

        $count = count($candles);
        $atr = IndicatorMath::wilderSmooth(IndicatorMath::trueRange($candles), $atrPeriod);
        $lastAtr = $count > 0 ? $atr[$count - 1] : null;

        $swings = SwingFinder::find($candles, $swingLookback);
        $highs = array_values(array_filter($swings, static fn (array $s): bool => $s['type'] === 'high'));
        $lows = array_values(array_filter($swings, static fn (array $s): bool => $s['type'] === 'low'));

        $buyside = $lastAtr === null ? null : $this->cluster($highs, $lastAtr * $marginAtrFraction, $minTouches);
        $sellside = $lastAtr === null ? null : $this->cluster($lows, $lastAtr * $marginAtrFraction, $minTouches);

        return [
            'buyside' => $buyside,
            'sellside' => $sellside,
            'buyside_swept_recently' => $buyside !== null && $this->wasSweptFromBelow($candles, $buyside, $sweepLookbackBars),
            'sellside_swept_recently' => $sellside !== null && $this->wasSweptFromAbove($candles, $sellside, $sweepLookbackBars),
        ];
    }

    /**
     * A liquidity pool is characterized by density (several swings sitting
     * within one ATR-scaled band), not just proximity to whichever swing
     * happens to be most recent — anchoring on the last swing alone means
     * one fresh outlier (e.g. the very breakout that later sweeps the
     * pool becoming, for a moment, the newest swing high itself) can hide
     * a real, well-touched pool. Instead, try every swing as a candidate
     * anchor and keep the densest cluster that clears $minTouches.
     *
     * @param array<int, array{index: int, price: float, type: string}> $swings
     * @return array{high: float, low: float}|null
     */
    private function cluster(array $swings, float $margin, int $minTouches): ?array
    {
        if ($swings === [] || $margin <= 0) {
            return null;
        }

        $best = null;
        $bestCount = 0;

        foreach ($swings as $candidateAnchor) {
            $anchor = $candidateAnchor['price'];
            $clustered = array_values(array_filter(
                $swings,
                static fn (array $s): bool => abs($s['price'] - $anchor) <= $margin,
            ));

            if (count($clustered) >= $minTouches && count($clustered) > $bestCount) {
                $best = $clustered;
                $bestCount = count($clustered);
            }
        }

        if ($best === null) {
            return null;
        }

        $prices = array_column($best, 'price');

        return ['high' => max($prices) + $margin / 2, 'low' => min($prices) - $margin / 2];
    }

    /**
     * Buyside liquidity sits above price — swept when a high pokes above
     * it but the close comes back under (a failed breakout / stop hunt).
     *
     * @param array<int, array<string, mixed>> $candles
     * @param array{high: float, low: float} $zone
     */
    private function wasSweptFromBelow(array $candles, array $zone, int $lookbackBars): bool
    {
        foreach (array_slice($candles, -$lookbackBars) as $candle) {
            if ((float) $candle['high'] > $zone['high'] && (float) $candle['close'] < $zone['high']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sellside liquidity sits below price — swept when a low pokes below
     * it but the close comes back above (failed breakdown / stop hunt).
     *
     * @param array<int, array<string, mixed>> $candles
     * @param array{high: float, low: float} $zone
     */
    private function wasSweptFromAbove(array $candles, array $zone, int $lookbackBars): bool
    {
        foreach (array_slice($candles, -$lookbackBars) as $candle) {
            if ((float) $candle['low'] < $zone['low'] && (float) $candle['close'] > $zone['low']) {
                return true;
            }
        }

        return false;
    }
}
