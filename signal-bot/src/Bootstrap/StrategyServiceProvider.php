<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Core\Application;
use App\Database\Database;
use App\Database\Repositories\FvgRepository;
use App\Database\Repositories\OrderBlockRepository;
use App\Database\Repositories\ZoneRepository;
use App\Strategy\FVG;
use App\Strategy\OrderBlock;
use App\Strategy\SupportResistance;
use App\Strategy\Zones\ZoneBuilder;

final class StrategyServiceProvider
{
    public static function register(Application $app): void
    {
        $app->singleton(ZoneRepository::class, static fn (Application $app): ZoneRepository => new ZoneRepository($app->get(Database::class)));
        $app->singleton(OrderBlockRepository::class, static fn (Application $app): OrderBlockRepository => new OrderBlockRepository($app->get(Database::class)));
        $app->singleton(FvgRepository::class, static fn (Application $app): FvgRepository => new FvgRepository($app->get(Database::class)));

        $app->singleton(ZoneBuilder::class, static fn (): ZoneBuilder => new ZoneBuilder());

        $app->singleton(SupportResistance::class, static function (Application $app): SupportResistance {
            return new SupportResistance($app->get(ZoneBuilder::class), $app->get(ZoneRepository::class));
        });

        $app->singleton(OrderBlock::class, static fn (): OrderBlock => new OrderBlock());
        $app->singleton(FVG::class, static fn (): FVG => new FVG());
    }
}
