<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Indicators\IndicatorMath;

/**
 * Order Block detection (spec #9). The exact definition is meant to come
 * from the user later ("تعریف دقیق Order Block را از من دریافت کن") — this
 * is a documented, fully configurable DEFAULT so the rest of the pipeline
 * (Strategy/Confluence/Test Signal) has something real to run against
 * meanwhile:
 *
 *   Bullish OB: the last bearish (close < open) candle immediately before
 *   a bullish displacement candle whose body is >= `displacement_atr_multiple`
 *   x ATR. Bearish OB is the mirror image.
 *
 * Every threshold is a param with a sane default — swap the whole
 * algorithm out (or just retune params) once exact rules are supplied,
 * without touching anything that consumes OrderBlock[] output.
 */
final class OrderBlock
{
    /**
     * @param array<int, array<string, mixed>> $candles oldest-to-newest
     * @param array<string, mixed> $params
     * @return array<int, array{type: string, high: float, low: float, timeframe: string, origin_candle_open_time: int, strength: float, volume: float, mitigated: bool}>
     */
    public function detect(array $candles, string $timeframe, array $params = []): array
    {
        $atrPeriod = (int) ($params['atr_period'] ?? 14);
        $displacementMultiple = (float) ($params['displacement_atr_multiple'] ?? 1.5);
        $lookback = (int) ($params['lookback'] ?? 3);

        $count = count($candles);
        if ($count <= $atrPeriod + $lookback) {
            return [];
        }

        $tr = IndicatorMath::trueRange($candles);
        $atr = IndicatorMath::wilderSmooth($tr, $atrPeriod);

        $blocks = [];

        for ($i = $atrPeriod; $i < $count; $i++) {
            $atrValue = $atr[$i];
            if ($atrValue === null || $atrValue <= 0) {
                continue;
            }

            $open = (float) $candles[$i]['open'];
            $close = (float) $candles[$i]['close'];
            $body = abs($close - $open);
            if ($body < $displacementMultiple * $atrValue) {
                continue;
            }

            $isBullishDisplacement = $close > $open;
            $origin = $this->findOriginCandle($candles, $i, $lookback, $isBullishDisplacement);
            if ($origin === null) {
                continue;
            }

            [$originIndex, $originCandle] = $origin;

            $blocks[] = [
                'type' => $isBullishDisplacement ? 'bullish' : 'bearish',
                'high' => (float) $originCandle['high'],
                'low' => (float) $originCandle['low'],
                'timeframe' => $timeframe,
                'origin_candle_open_time' => (int) $originCandle['open_time'],
                'strength' => min(100.0, 40.0 + (($body / $atrValue) - $displacementMultiple) * 10),
                'volume' => (float) $originCandle['volume'],
                // Skip both the origin candle and the displacement candle
                // itself — the displacement candle's low/high routinely
                // overlaps the origin's range simply because it opens
                // right where the origin closed, which is not mitigation.
                'mitigated' => $this->isMitigated($originCandle, $isBullishDisplacement, array_slice($candles, $i + 1)),
            ];
        }

        return $blocks;
    }

    /**
     * The origin candle is the most recent opposite-colored candle before
     * the displacement move.
     *
     * @param array<int, array<string, mixed>> $candles
     * @return array{0: int, 1: array<string, mixed>}|null
     */
    private function findOriginCandle(array $candles, int $displacementIndex, int $lookback, bool $isBullishDisplacement): ?array
    {
        for ($j = $displacementIndex - 1; $j >= max(0, $displacementIndex - $lookback); $j--) {
            $open = (float) $candles[$j]['open'];
            $close = (float) $candles[$j]['close'];
            $isBearishCandle = $close < $open;

            if ($isBullishDisplacement && $isBearishCandle) {
                return [$j, $candles[$j]];
            }
            if (!$isBullishDisplacement && !$isBearishCandle && $close > $open) {
                return [$j, $candles[$j]];
            }
        }

        return null;
    }

    /**
     * An order block is mitigated once price later trades back into its
     * [low, high] range.
     *
     * @param array<string, mixed> $origin
     * @param array<int, array<string, mixed>> $subsequentCandles
     */
    private function isMitigated(array $origin, bool $isBullish, array $subsequentCandles): bool
    {
        $high = (float) $origin['high'];
        $low = (float) $origin['low'];

        foreach ($subsequentCandles as $candle) {
            $candleLow = (float) $candle['low'];
            $candleHigh = (float) $candle['high'];

            if ($candleLow <= $high && $candleHigh >= $low) {
                return true;
            }
        }

        return false;
    }
}
