<?php

declare(strict_types=1);

namespace App\Strategy\Smc;

/**
 * Market Structure / BOS-CHoCH, translated from the source Pine script's
 * "sina" (Market Structure) and "nexua" (i-CHoCH/i-BOS) sections: track the
 * most recent unbroken swing high/low; once a bar's CLOSE trades through
 * it, that is a structure break in that direction. Whether the break is
 * labeled BOS (trend continuation) or CHoCH (trend reversal) depends on
 * whether the new break direction matches the currently-tracked trend —
 * exactly the `movingSmall` state in the source script's nxDrawInternal().
 */
final class SwingStructure
{
    /**
     * @param array<int, array<string, mixed>> $candles oldest-to-newest
     * @return array{trend: ?string, last_break_type: ?string, last_break_index: ?int, last_break_price: ?float, watched_high: ?float, watched_low: ?float}
     */
    public function analyze(array $candles, int $swingLookback = 3): array
    {
        $swings = SwingFinder::find($candles, $swingLookback);
        $swingsByIndex = [];
        foreach ($swings as $swing) {
            $swingsByIndex[$swing['index']][] = $swing;
        }

        $watchedHigh = null;
        $watchedLow = null;
        $trend = null;
        $lastBreakType = null;
        $lastBreakIndex = null;
        $lastBreakPrice = null;

        $count = count($candles);
        for ($i = 0; $i < $count; $i++) {
            foreach ($swingsByIndex[$i] ?? [] as $swing) {
                if ($swing['type'] === 'high') {
                    $watchedHigh = $swing['price'];
                } else {
                    $watchedLow = $swing['price'];
                }
            }

            $close = (float) $candles[$i]['close'];

            if ($watchedHigh !== null && $close > $watchedHigh) {
                $lastBreakType = ($trend === 'bearish' || $trend === null) ? 'CHoCH' : 'BOS';
                $lastBreakIndex = $i;
                $lastBreakPrice = $watchedHigh;
                $trend = 'bullish';
                $watchedHigh = null;
            }

            if ($watchedLow !== null && $close < $watchedLow) {
                $lastBreakType = ($trend === 'bullish' || $trend === null) ? 'CHoCH' : 'BOS';
                $lastBreakIndex = $i;
                $lastBreakPrice = $watchedLow;
                $trend = 'bearish';
                $watchedLow = null;
            }
        }

        return [
            'trend' => $trend,
            'last_break_type' => $lastBreakType,
            'last_break_index' => $lastBreakIndex,
            'last_break_price' => $lastBreakPrice,
            'watched_high' => $watchedHigh,
            'watched_low' => $watchedLow,
        ];
    }
}
