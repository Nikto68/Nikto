<?php

declare(strict_types=1);

namespace App\Worker;

use App\Database\Repositories\ExchangeRepository;
use App\Database\Repositories\FvgRepository;
use App\Database\Repositories\MarketDataRepository;
use App\Database\Repositories\OrderBlockRepository;
use App\Database\Repositories\SettingsRepository;
use App\Database\Repositories\StrategyRepository;
use App\Database\Repositories\SymbolRepository;
use App\Exchange\ExchangeInterface;
use App\Exchange\ExchangeManager;
use App\Exchange\WebSocket\ExchangeWebSocketFactory;
use App\Exchange\WebSocket\ExchangeWebSocketInterface;
use App\Market\CandleManager;
use App\Market\MarketScanner;
use App\Signal\SignalEngine;
use App\Strategy\FVG;
use App\Strategy\OrderBlock;
use App\Strategy\StrategyEngine;
use App\Strategy\StrategyRegistry;
use App\Strategy\SupportResistance;
use App\Support\PromiseUtil;
use App\Support\Timeframe;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Promise\PromiseInterface;
use Throwable;

use function React\Promise\resolve;

/**
 * The long-running daemon's brain (spec #2/#28): a periodic timer runs the
 * whole "sync symbols -> rank universe -> fetch candles -> detect zones ->
 * evaluate strategies -> validate/dispatch signals" pipeline, and a
 * WebSocket client per exchange keeps prices fresh in between cycles.
 * worker/market_worker.php just constructs this (via bootstrap.php's
 * container) and calls start(), then runs the event loop.
 */
final class MarketWorkerRunner
{
    private bool $cycleRunning = false;
    private bool $stopping = false;
    private ?TimerInterface $scanTimer = null;
    private ?TimerInterface $queueTimer = null;

    /** @var array<string, ExchangeWebSocketInterface> exchange code => client */
    private array $wsClients = [];

    /** @var array<string, string[]> exchange code => subscribed symbols, for change detection */
    private array $wsSubscriptions = [];

    /** @var array<int, string> exchange id => code, resolved once per process */
    private array $exchangeCodeCache = [];

    public function __construct(
        private readonly ExchangeManager $exchangeManager,
        private readonly ExchangeRepository $exchangeRepository,
        private readonly ExchangeWebSocketFactory $wsFactory,
        private readonly MarketScanner $scanner,
        private readonly SymbolRepository $symbols,
        private readonly CandleManager $candleManager,
        private readonly SupportResistance $supportResistance,
        private readonly OrderBlock $orderBlock,
        private readonly OrderBlockRepository $orderBlockRepository,
        private readonly FVG $fvg,
        private readonly FvgRepository $fvgRepository,
        private readonly StrategyEngine $strategyEngine,
        private readonly StrategyRegistry $strategyRegistry,
        private readonly StrategyRepository $strategyRepository,
        private readonly SignalEngine $signalEngine,
        private readonly MarketDataRepository $marketData,
        private readonly SettingsRepository $settings,
        private readonly QueueWorkerRunner $queueRunner,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function start(): void
    {
        $this->logger->info('Market worker starting');

        // Startup sequence (spec #28): the first cycle IS steps 2-6
        // (load symbols, fetch initial candles, build indicators on demand,
        // detect zones, connect WebSocket) — indicators are computed
        // on-demand from stored candles, so there is no separate "build"
        // step. Step 7-9 (scanner/signal engine/queue) become the
        // recurring timers registered below.
        $this->runCycle();

        $intervalSeconds = max(10, (int) $this->settings->get('scan_interval_seconds', 60));
        $this->scanTimer = Loop::addPeriodicTimer($intervalSeconds, function (): void {
            $this->runCycle();
        });

        $this->queueTimer = Loop::addPeriodicTimer(3.0, function (): void {
            $this->queueRunner->tick();
        });

        $this->logger->info('Market worker started', ['scan_interval_seconds' => $intervalSeconds]);
    }

    public function stop(): void
    {
        $this->stopping = true;

        if ($this->scanTimer !== null) {
            Loop::cancelTimer($this->scanTimer);
        }
        if ($this->queueTimer !== null) {
            Loop::cancelTimer($this->queueTimer);
        }

        foreach ($this->wsClients as $client) {
            $client->stop();
        }

        $this->logger->info('Market worker stopped');
    }

    private function runCycle(): PromiseInterface
    {
        if ($this->cycleRunning || $this->stopping) {
            return resolve(null);
        }
        $this->cycleRunning = true;
        $startedAt = microtime(true);

        $promise = $this->scanner->scanAll()->then(function (array $scanResult): PromiseInterface {
            $timeframes = $this->neededTimeframes();
            $limit = (int) $this->settings->get('scan_symbol_limit', 200);
            $universe = $this->symbols->universe($limit);

            return $this->syncCandles($universe, $timeframes)->then(function () use ($universe, $timeframes, $scanResult): array {
                $signalsGenerated = $this->detectZonesAndEvaluate($universe, $timeframes);
                $this->refreshWebSocketSubscriptions($universe);

                return [...$scanResult, 'signals_generated' => $signalsGenerated];
            });
        });

        return $promise->then(
            function (array $result) use ($startedAt): array {
                $this->cycleRunning = false;
                $this->logger->info('Worker cycle complete', [
                    ...$result,
                    'seconds' => round(microtime(true) - $startedAt, 2),
                ]);

                return $result;
            },
            function (Throwable $e): null {
                $this->cycleRunning = false;
                $this->logger->error('Worker cycle failed', ['exception' => $e->getMessage()]);

                return null;
            },
        );
    }

    /**
     * @param array<int, array<string, mixed>> $universe
     * @param string[] $timeframes
     * @return PromiseInterface<mixed>
     */
    private function syncCandles(array $universe, array $timeframes): PromiseInterface
    {
        $promises = [];

        foreach ($universe as $row) {
            $exchange = $this->exchangeFor((int) $row['exchange_id']);
            if ($exchange === null) {
                continue;
            }

            foreach ($timeframes as $tf) {
                $promises[] = $this->candleManager->ensureFresh($exchange, (int) $row['id'], (string) $row['symbol'], $tf, 500);
            }
        }

        return PromiseUtil::settle($promises);
    }

    /**
     * @param array<int, array<string, mixed>> $universe
     * @param string[] $timeframes
     */
    private function detectZonesAndEvaluate(array $universe, array $timeframes): int
    {
        $htfTimeframe = Timeframe::largest($timeframes);
        $signalsGenerated = 0;

        foreach ($universe as $row) {
            $symbolId = (int) $row['id'];
            $exchangeCode = $this->exchangeCodeFor((int) $row['exchange_id']);
            if ($exchangeCode === null) {
                continue;
            }

            $htfCandles = $htfTimeframe !== null ? $this->candleManager->recent($symbolId, $htfTimeframe, 500) : [];

            foreach ($timeframes as $tf) {
                $candles = $this->candleManager->recent($symbolId, $tf, 500);
                if ($candles === []) {
                    continue;
                }

                $this->supportResistance->detectAndStore($symbolId, $candles, $tf, $tf === $htfTimeframe ? [] : $htfCandles);
                $this->orderBlockRepository->upsertBatch($symbolId, $this->orderBlock->detect($candles, $tf));
                $this->fvgRepository->upsertBatch($symbolId, $this->fvg->detect($candles, $tf));
            }

            $currentPrice = (float) ($row['price'] ?? 0);
            $candidates = $this->strategyEngine->evaluateSymbol($exchangeCode, $symbolId, (string) $row['symbol'], $currentPrice);

            foreach ($candidates as $candidate) {
                if ($this->signalEngine->process($candidate) !== null) {
                    $signalsGenerated++;
                }
            }
        }

        return $signalsGenerated;
    }

    /**
     * @param array<int, array<string, mixed>> $universe
     */
    private function refreshWebSocketSubscriptions(array $universe): void
    {
        $byExchange = [];
        foreach ($universe as $row) {
            $code = $this->exchangeCodeFor((int) $row['exchange_id']);
            if ($code === null) {
                continue;
            }
            $byExchange[$code][] = (string) $row['symbol'];
        }

        foreach ($this->exchangeManager->all() as $code => $exchange) {
            $wanted = $byExchange[$code] ?? [];
            $current = $this->wsSubscriptions[$code] ?? [];

            // Compare as sets, not ordered lists: volume_rank reshuffles
            // the universe's order almost every cycle even when the set
            // of symbols in it hasn't changed, which would otherwise
            // force a WebSocket reconnect every single cycle for nothing.
            $wantedSet = $wanted;
            $currentSet = $current;
            sort($wantedSet);
            sort($currentSet);

            if ($wantedSet === $currentSet) {
                continue;
            }

            $client = $this->wsClients[$code] ??= $this->wsFactory->make($exchange);
            $client->stop();

            if ($wanted === []) {
                unset($this->wsSubscriptions[$code]);

                continue;
            }

            $client->start($wanted, function (array $ticker) use ($code): void {
                $this->onTicker($code, $ticker);
            });
            $this->wsSubscriptions[$code] = $wanted;
        }
    }

    /**
     * @param array<string, mixed> $ticker
     */
    private function onTicker(string $exchangeCode, array $ticker): void
    {
        $exchangeId = $this->exchangeRepository->idForCode($exchangeCode);
        if ($exchangeId === null || ($ticker['symbol'] ?? '') === '') {
            return;
        }

        $symbolRow = $this->symbols->findByExchangeAndSymbol($exchangeId, (string) $ticker['symbol']);
        if ($symbolRow === null) {
            return;
        }

        // Real-time price only — full ticker/volume/spread refresh stays
        // on the periodic REST scan cycle (VolumeAnalyzer::rank()).
        $price = (float) ($ticker['price'] ?? 0);
        if ($price > 0) {
            $this->marketData->updatePrice((int) $symbolRow['id'], $price);
        }
    }

    /**
     * @return string[]
     */
    private function neededTimeframes(): array
    {
        $fromStrategies = [];
        foreach ($this->strategyRepository->active() as $row) {
            $strategy = $this->strategyRegistry->get((string) $row['code']);
            if ($strategy === null) {
                continue;
            }
            $fromStrategies = [...$fromStrategies, ...array_values($strategy->timeframes())];
        }

        if ($fromStrategies !== []) {
            return array_values(array_unique($fromStrategies));
        }

        $default = $this->settings->get('default_timeframes', ['15m', '1h', '4h']);

        return is_array($default) && $default !== [] ? $default : ['15m', '1h', '4h'];
    }

    private function exchangeFor(int $exchangeId): ?ExchangeInterface
    {
        $code = $this->exchangeCodeFor($exchangeId);

        return $code === null ? null : $this->exchangeManager->get($code);
    }

    private function exchangeCodeFor(int $exchangeId): ?string
    {
        if (isset($this->exchangeCodeCache[$exchangeId])) {
            return $this->exchangeCodeCache[$exchangeId];
        }

        $row = $this->exchangeRepository->find($exchangeId);
        if ($row === null) {
            return null;
        }

        return $this->exchangeCodeCache[$exchangeId] = (string) $row['code'];
    }
}
