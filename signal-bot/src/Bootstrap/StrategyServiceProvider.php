<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Core\Application;
use App\Database\Database;
use App\Database\Repositories\ExchangeRepository;
use App\Database\Repositories\FvgRepository;
use App\Database\Repositories\OrderBlockRepository;
use App\Database\Repositories\StrategyRepository;
use App\Database\Repositories\ZoneRepository;
use App\Indicators\IndicatorManager;
use App\Logger\LoggerFactory;
use App\Market\CandleManager;
use App\Strategy\ConfluenceEngine;
use App\Strategy\FVG;
use App\Strategy\OrderBlock;
use App\Strategy\SmcStrategy;
use App\Strategy\StrategyEngine;
use App\Strategy\StrategyRegistry;
use App\Strategy\SupportResistance;
use App\Strategy\Zones\ZoneBuilder;

final class StrategyServiceProvider
{
    public static function register(Application $app): void
    {
        $app->singleton(ZoneRepository::class, static fn (Application $app): ZoneRepository => new ZoneRepository($app->get(Database::class)));
        $app->singleton(OrderBlockRepository::class, static fn (Application $app): OrderBlockRepository => new OrderBlockRepository($app->get(Database::class)));
        $app->singleton(FvgRepository::class, static fn (Application $app): FvgRepository => new FvgRepository($app->get(Database::class)));
        $app->singleton(StrategyRepository::class, static fn (Application $app): StrategyRepository => new StrategyRepository($app->get(Database::class)));

        $app->singleton(ZoneBuilder::class, static fn (): ZoneBuilder => new ZoneBuilder());

        $app->singleton(SupportResistance::class, static function (Application $app): SupportResistance {
            return new SupportResistance($app->get(ZoneBuilder::class), $app->get(ZoneRepository::class));
        });

        $app->singleton(OrderBlock::class, static fn (): OrderBlock => new OrderBlock());
        $app->singleton(FVG::class, static fn (): FVG => new FVG());

        $app->singleton(ConfluenceEngine::class, static fn (): ConfluenceEngine => new ConfluenceEngine());

        // smc_confluence (src/Strategy/SmcStrategy.php) is the real strategy,
        // translated from the user's combined SMC Pine Script indicator —
        // its matching `strategies` row is seeded active by migration 011.
        // Adding another real strategy later is the same pattern: implement
        // StrategyInterface, register it here, add/activate its DB row.
        $app->singleton(StrategyRegistry::class, static function (): StrategyRegistry {
            $registry = new StrategyRegistry();
            $registry->register(new SmcStrategy());

            return $registry;
        });

        $app->singleton(StrategyEngine::class, static function (Application $app): StrategyEngine {
            return new StrategyEngine(
                $app->get(StrategyRepository::class),
                $app->get(StrategyRegistry::class),
                $app->get(CandleManager::class),
                $app->get(ZoneRepository::class),
                $app->get(OrderBlockRepository::class),
                $app->get(FvgRepository::class),
                $app->get(IndicatorManager::class),
                $app->get(ConfluenceEngine::class),
                $app->get(ExchangeRepository::class),
                $app->get(LoggerFactory::class)->channel('scanner'),
            );
        });
    }
}
