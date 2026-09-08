<?php

declare(strict_types=1);

namespace App\Market;

use App\Database\Repositories\ExchangeRepository;
use App\Database\Repositories\SettingsRepository;
use App\Database\Repositories\SymbolRepository;
use App\Exchange\ExchangeInterface;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use Throwable;

use function React\Promise\resolve;

/**
 * Keeps the `symbols` table in sync with what an exchange actually lists
 * (spec #5's "Exchange Markets -> Filter Invalid Symbols -> Filter
 * Stable/Quote Rules" steps). Ranking/liquidity filtering happens later in
 * VolumeAnalyzer — this class only decides which symbols are even
 * candidates.
 */
final class SymbolManager
{
    public function __construct(
        private readonly ExchangeRepository $exchanges,
        private readonly SymbolRepository $symbols,
        private readonly SettingsRepository $settings,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return PromiseInterface<int> number of symbols synced
     */
    public function sync(ExchangeInterface $exchange): PromiseInterface
    {
        $exchangeRow = $this->exchanges->findByCode($exchange->code());
        if ($exchangeRow === null) {
            $this->logger->error('Unknown exchange row, run migrations/seed first', ['exchange' => $exchange->code()]);

            return resolve(0);
        }
        $exchangeId = (int) $exchangeRow['id'];

        return $exchange->getMarkets()->then(
            function (array $markets) use ($exchange, $exchangeId): int {
                $allowedQuotes = $this->allowedQuoteAssets();
                $seen = [];

                foreach ($markets as $market) {
                    if (($market['status'] ?? '') !== 'trading') {
                        continue;
                    }
                    if ($allowedQuotes !== [] && !in_array($market['quote_asset'], $allowedQuotes, true)) {
                        continue;
                    }

                    $this->symbols->upsert($exchangeId, $market);
                    $seen[] = $market['symbol'];
                }

                $this->symbols->deactivateMissing($exchangeId, $seen);
                $this->exchanges->markOnline($exchange->code());

                return count($seen);
            },
            function (Throwable $e) use ($exchange, $exchangeId): int {
                $this->exchanges->markDegraded($exchange->code(), $e->getMessage());
                $this->logger->error('Symbol sync failed', ['exchange' => $exchange->code(), 'exception' => $e->getMessage()]);

                return 0;
            },
        );
    }

    /**
     * @return string[]
     */
    private function allowedQuoteAssets(): array
    {
        $configured = $this->settings->get('universe_quote_assets', []);

        return is_array($configured) ? $configured : [];
    }
}
