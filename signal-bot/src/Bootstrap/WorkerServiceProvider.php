<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Core\Application;
use App\Database\Repositories\ExchangeRepository;
use App\Database\Repositories\FvgRepository;
use App\Database\Repositories\MarketDataRepository;
use App\Database\Repositories\OrderBlockRepository;
use App\Database\Repositories\SettingsRepository;
use App\Database\Repositories\StrategyRepository;
use App\Database\Repositories\SymbolRepository;
use App\Exchange\ExchangeManager;
use App\Exchange\WebSocket\ExchangeWebSocketFactory;
use App\Logger\LoggerFactory;
use App\Market\CandleManager;
use App\Market\MarketScanner;
use App\Queue\QueueWorker;
use App\Signal\SignalEngine;
use App\Strategy\FVG;
use App\Strategy\OrderBlock;
use App\Strategy\StrategyEngine;
use App\Strategy\StrategyRegistry;
use App\Strategy\SupportResistance;
use App\Worker\MarketWorkerRunner;
use App\Worker\QueueWorkerRunner;

final class WorkerServiceProvider
{
    public static function register(Application $app): void
    {
        $app->singleton(QueueWorkerRunner::class, static function (Application $app): QueueWorkerRunner {
            return new QueueWorkerRunner($app->get(QueueWorker::class), $app->get(LoggerFactory::class)->channel('signal'));
        });

        $app->singleton(MarketWorkerRunner::class, static function (Application $app): MarketWorkerRunner {
            return new MarketWorkerRunner(
                $app->get(ExchangeManager::class),
                $app->get(ExchangeRepository::class),
                $app->get(ExchangeWebSocketFactory::class),
                $app->get(MarketScanner::class),
                $app->get(SymbolRepository::class),
                $app->get(CandleManager::class),
                $app->get(SupportResistance::class),
                $app->get(OrderBlock::class),
                $app->get(OrderBlockRepository::class),
                $app->get(FVG::class),
                $app->get(FvgRepository::class),
                $app->get(StrategyEngine::class),
                $app->get(StrategyRegistry::class),
                $app->get(StrategyRepository::class),
                $app->get(SignalEngine::class),
                $app->get(MarketDataRepository::class),
                $app->get(SettingsRepository::class),
                $app->get(QueueWorkerRunner::class),
                $app->get(LoggerFactory::class)->channel('scanner'),
            );
        });
    }
}
