<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Database\Repositories\ExchangeRepository;
use App\Database\Repositories\FvgRepository;
use App\Database\Repositories\OrderBlockRepository;
use App\Database\Repositories\StrategyRepository;
use App\Database\Repositories\ZoneRepository;
use App\Indicators\IndicatorManager;
use App\Market\CandleManager;
use App\Signal\SignalCandidate;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Orchestrates the "Market Data -> Indicators -> Zones -> Strategy ->
 * Confluence -> Signal Candidate" pipeline (spec #11) for one symbol.
 * Never sends anything to Telegram and never writes a `signals` row —
 * SignalEngine (Phase 11) owns validation, deduplication, and dispatch.
 * Every active strategy is evaluated independently; one strategy throwing
 * never stops the others (same isolation principle as exchanges, spec #30).
 */
final class StrategyEngine
{
    public function __construct(
        private readonly StrategyRepository $strategyRepository,
        private readonly StrategyRegistry $registry,
        private readonly CandleManager $candleManager,
        private readonly ZoneRepository $zones,
        private readonly OrderBlockRepository $orderBlocks,
        private readonly FvgRepository $fvgs,
        private readonly IndicatorManager $indicators,
        private readonly ConfluenceEngine $confluence,
        private readonly ExchangeRepository $exchangeRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return SignalCandidate[]
     */
    public function evaluateSymbol(string $exchangeCode, int $symbolId, string $exchangeSymbol, float $currentPrice): array
    {
        $exchangeId = $this->exchangeRepository->idForCode($exchangeCode);
        if ($exchangeId === null) {
            return [];
        }

        $candidates = [];

        foreach ($this->strategyRepository->active() as $strategyRow) {
            $strategy = $this->registry->get($strategyRow['code']);
            if ($strategy === null) {
                $this->logger->warning('Active strategy row has no registered implementation', ['code' => $strategyRow['code']]);

                continue;
            }

            try {
                $candidate = $this->evaluateOne($strategy, $strategyRow, $exchangeCode, $exchangeId, $exchangeSymbol, $symbolId, $currentPrice);
                if ($candidate !== null) {
                    $candidates[] = $candidate;
                }
            } catch (Throwable $e) {
                $this->logger->error('Strategy evaluation failed', [
                    'strategy' => $strategy->code(),
                    'symbol' => $exchangeSymbol,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $candidates;
    }

    /**
     * @param array<string, mixed> $strategyRow
     */
    private function evaluateOne(
        StrategyInterface $strategy,
        array $strategyRow,
        string $exchangeCode,
        int $exchangeId,
        string $exchangeSymbol,
        int $symbolId,
        float $currentPrice,
    ): ?SignalCandidate {
        $timeframes = array_unique(array_values($strategy->timeframes()));

        $candlesByTf = [];
        $zonesByTf = [];
        $orderBlocksByTf = [];
        $fvgsByTf = [];

        foreach ($timeframes as $tf) {
            $candlesByTf[$tf] = $this->candleManager->recent($symbolId, $tf, 500);
            $zonesByTf[$tf] = $this->zones->active($symbolId, $tf);
            $orderBlocksByTf[$tf] = $this->orderBlocks->active($symbolId, $tf);
            $fvgsByTf[$tf] = $this->fvgs->active($symbolId, $tf);
        }

        $context = new StrategyContext(
            $exchangeCode,
            $exchangeSymbol,
            $symbolId,
            $currentPrice,
            $candlesByTf,
            $zonesByTf,
            $orderBlocksByTf,
            $fvgsByTf,
            $this->indicators,
        );

        $result = $strategy->evaluate($context);
        if ($result === null) {
            return null;
        }

        $config = $strategyRow['config'];
        $scoring = $this->confluence->score($result->confluenceFactors, $config);

        if (!$scoring['passes_required']) {
            return null;
        }

        if ($scoring['score'] < $this->confluence->minScore($config, (int) $strategyRow['min_score'])) {
            return null;
        }

        return new SignalCandidate(
            exchangeCode: $exchangeCode,
            exchangeId: $exchangeId,
            symbol: $exchangeSymbol,
            symbolId: $symbolId,
            strategyCode: $strategy->code(),
            strategyId: isset($strategyRow['id']) ? (int) $strategyRow['id'] : null,
            direction: $result->direction,
            timeframe: $strategy->timeframes()['entry'] ?? array_values($strategy->timeframes())[0],
            entry: $result->entry,
            stopLoss: $result->stopLoss,
            takeProfits: $result->takeProfits,
            score: $scoring['score'],
            reasons: $result->reasons,
            zones: $result->meta['zones'] ?? [],
            indicators: $result->meta['indicators'] ?? [],
        );
    }
}
