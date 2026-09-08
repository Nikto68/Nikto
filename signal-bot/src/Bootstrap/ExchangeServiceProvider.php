<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Core\Application;
use App\Core\Config;
use App\Exchange\ExchangeManager;
use App\Exchange\WebSocket\ExchangeWebSocketFactory;
use App\Logger\LoggerFactory;
use React\Http\Browser;

final class ExchangeServiceProvider
{
    public static function register(Application $app): void
    {
        $app->singleton(Browser::class, static function (): Browser {
            return (new Browser())->withTimeout(15);
        });

        $app->singleton(ExchangeManager::class, static function (Application $app): ExchangeManager {
            return new ExchangeManager(
                $app->get(Config::class),
                $app->get(LoggerFactory::class),
                $app->get(Browser::class),
            );
        });

        $app->singleton(ExchangeWebSocketFactory::class, static function (Application $app): ExchangeWebSocketFactory {
            return new ExchangeWebSocketFactory($app->get(Config::class), $app->get(LoggerFactory::class));
        });
    }
}
