<?php

declare(strict_types=1);

namespace App\Market;

use App\Database\Repositories\CandleRepository;
use App\Exchange\ExchangeInterface;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use Throwable;

/**
 * Keeps `candles` fresh without ever recomputing full history when only
 * the newest bars are missing (spec #41): once at least $minCandles exist,
 * every later call fetches only candles after the last stored open_time.
 */
final class CandleManager
{
    public function __construct(
        private readonly CandleRepository $candles,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return PromiseInterface<int> candles fetched (0 on failure, logged not thrown)
     */
    public function ensureFresh(
        ExchangeInterface $exchange,
        int $symbolId,
        string $exchangeSymbol,
        string $timeframe,
        int $minCandles = 500,
    ): PromiseInterface {
        $existingCount = $this->candles->count($symbolId, $timeframe);
        $latest = $this->candles->latestOpenTime($symbolId, $timeframe);

        $fetch = ($latest === null || $existingCount < $minCandles)
            ? $exchange->getKlines($exchangeSymbol, $timeframe, $minCandles)
            : $exchange->getKlines($exchangeSymbol, $timeframe, 200, $latest + 1);

        return $fetch->then(
            function (array $fetchedCandles) use ($symbolId, $timeframe): int {
                if ($fetchedCandles !== []) {
                    $this->candles->bulkUpsert($symbolId, $timeframe, $fetchedCandles);
                }

                return count($fetchedCandles);
            },
            function (Throwable $e) use ($exchangeSymbol, $timeframe): int {
                $this->logger->warning('Candle fetch failed', [
                    'symbol' => $exchangeSymbol,
                    'timeframe' => $timeframe,
                    'exception' => $e->getMessage(),
                ]);

                return 0;
            },
        );
    }

    /**
     * @return array<int, array<string, mixed>> oldest-to-newest
     */
    public function recent(int $symbolId, string $timeframe, int $limit): array
    {
        return $this->candles->recent($symbolId, $timeframe, $limit);
    }
}
