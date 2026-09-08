<?php

declare(strict_types=1);

namespace App\Market;

use App\Database\Repositories\MarketDataRepository;
use App\Database\Repositories\SettingsRepository;

/**
 * Turns a batch of tickers into the ranked Top-N universe (spec #5):
 * Volume + Liquidity + Spread filters, then rank by quote volume and keep
 * the configured SCAN_SYMBOL_LIMIT. Liquidity is proxied by 24h quote
 * volume here — a full order-book depth check only happens later, per
 * candidate symbol, in SignalValidator (spec #37), which is far cheaper
 * than depth-checking the whole universe every cycle.
 */
final class VolumeAnalyzer
{
    public function __construct(
        private readonly MarketDataRepository $marketData,
        private readonly SettingsRepository $settings,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $activeSymbols rows from SymbolRepository (must include id, symbol)
     * @param array<int, array<string, mixed>> $tickers Ticker[] from ExchangeInterface
     * @return int how many symbols ended up in the universe
     */
    public function rank(array $activeSymbols, array $tickers): int
    {
        $minVolume = (float) $this->settings->get('min_volume', 0.0);
        $minLiquidity = (float) $this->settings->get('min_liquidity', 0.0);
        $maxSpread = (float) $this->settings->get('max_spread_percent', 1.0);
        $limit = (int) $this->settings->get('scan_symbol_limit', 200);

        $symbolIdByCode = [];
        foreach ($activeSymbols as $s) {
            $symbolIdByCode[$s['symbol']] = (int) $s['id'];
        }

        /** @var array<int, float> $qualified symbol_id => quote_volume_24h */
        $qualified = [];

        foreach ($tickers as $ticker) {
            $symbolId = $symbolIdByCode[$ticker['symbol'] ?? ''] ?? null;
            if ($symbolId === null) {
                continue;
            }

            $quoteVolume = (float) ($ticker['quote_volume_24h'] ?? 0);
            $this->marketData->upsert($symbolId, $ticker, $this->estimateVolatility($ticker));

            $passesVolume = $quoteVolume >= $minVolume;
            $passesLiquidity = $quoteVolume >= $minLiquidity;
            $passesSpread = $maxSpread <= 0 || (float) ($ticker['spread_percent'] ?? 0) <= $maxSpread;

            if ($passesVolume && $passesLiquidity && $passesSpread) {
                $qualified[$symbolId] = $quoteVolume;
            }
        }

        arsort($qualified);

        $rank = 0;
        foreach ($qualified as $symbolId => $volume) {
            $rank++;
            $this->marketData->setUniverseRank($symbolId, $rank, $rank <= $limit);
        }

        return min($rank, $limit);
    }

    /**
     * @param array<string, mixed> $ticker
     */
    private function estimateVolatility(array $ticker): float
    {
        $price = (float) ($ticker['price'] ?? 0);
        if ($price <= 0) {
            return 0.0;
        }

        $high = (float) ($ticker['high_24h'] ?? 0);
        $low = (float) ($ticker['low_24h'] ?? 0);

        return (($high - $low) / $price) * 100;
    }
}
