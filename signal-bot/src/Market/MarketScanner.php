<?php

declare(strict_types=1);

namespace App\Market;

use App\Database\Repositories\ExchangeRepository;
use App\Database\Repositories\MarketDataRepository;
use App\Database\Repositories\ScannerRunRepository;
use App\Database\Repositories\SymbolRepository;
use App\Exchange\ExchangeInterface;
use App\Exchange\ExchangeManager;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use Throwable;

use function React\Promise\all;
use function React\Promise\resolve;

/**
 * One full scan cycle: sync each enabled exchange's symbol list, pull
 * batched tickers, rank into the Top-N universe. Driven by the worker's
 * internal timer (spec #22 SCAN_INTERVAL), never cron. Every exchange runs
 * independently and failures are caught per-exchange (spec #30) — Wallex
 * being down never stops Binance/MEXC from scanning.
 */
final class MarketScanner
{
    public function __construct(
        private readonly ExchangeManager $exchangeManager,
        private readonly ExchangeRepository $exchangeRepository,
        private readonly SymbolManager $symbolManager,
        private readonly VolumeAnalyzer $volumeAnalyzer,
        private readonly SymbolRepository $symbols,
        private readonly MarketDataRepository $marketData,
        private readonly ScannerRunRepository $scannerRuns,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return PromiseInterface<array{symbols_scanned: int, universe_size: int, errors: int}>
     */
    public function scanAll(): PromiseInterface
    {
        $runId = $this->scannerRuns->start();
        $exchanges = $this->exchangeManager->all();

        if ($exchanges === []) {
            $this->scannerRuns->finish($runId, 0, 0, 0, 'completed', 'no enabled exchanges');

            return resolve(['symbols_scanned' => 0, 'universe_size' => 0, 'errors' => 0]);
        }

        $pipelines = array_map(
            fn (ExchangeInterface $exchange): PromiseInterface => $this->scanExchange($exchange),
            array_values($exchanges),
        );

        return all($pipelines)->then(function (array $results) use ($runId): array {
            $symbolsScanned = array_sum(array_column($results, 'symbols_scanned'));
            $errors = array_sum(array_column($results, 'errors'));
            $universeSize = $this->marketData->universeCount();

            $this->scannerRuns->finish($runId, $symbolsScanned, 0, $errors);

            $this->logger->info('Scan cycle completed', [
                'symbols_scanned' => $symbolsScanned,
                'universe_size' => $universeSize,
                'errors' => $errors,
            ]);

            return ['symbols_scanned' => $symbolsScanned, 'universe_size' => $universeSize, 'errors' => $errors];
        });
    }

    /**
     * @return PromiseInterface<array{symbols_scanned: int, errors: int}>
     */
    private function scanExchange(ExchangeInterface $exchange): PromiseInterface
    {
        return $this->symbolManager->sync($exchange)->then(
            function (int $syncedCount) use ($exchange): PromiseInterface {
                if ($syncedCount === 0) {
                    return resolve(['symbols_scanned' => 0, 'errors' => 1]);
                }

                $exchangeId = $this->exchangeRepository->idForCode($exchange->code());
                if ($exchangeId === null) {
                    return resolve(['symbols_scanned' => 0, 'errors' => 1]);
                }

                $activeSymbols = $this->symbols->activeByExchange($exchangeId);
                $this->marketData->clearUniverseForExchangeSymbols($exchangeId);

                return $exchange->getTickers()->then(
                    function (array $tickers) use ($activeSymbols): array {
                        $this->volumeAnalyzer->rank($activeSymbols, $tickers);

                        return ['symbols_scanned' => count($activeSymbols), 'errors' => 0];
                    },
                    function (Throwable $e) use ($exchange): array {
                        $this->exchangeRepository->markDegraded($exchange->code(), $e->getMessage());
                        $this->logger->error('Ticker fetch failed during scan', [
                            'exchange' => $exchange->code(),
                            'exception' => $e->getMessage(),
                        ]);

                        return ['symbols_scanned' => 0, 'errors' => 1];
                    },
                );
            },
        );
    }
}
