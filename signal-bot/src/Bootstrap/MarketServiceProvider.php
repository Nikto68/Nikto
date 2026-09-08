<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Core\Application;
use App\Database\Database;
use App\Database\Repositories\CandleRepository;
use App\Database\Repositories\ExchangeRepository;
use App\Database\Repositories\MarketDataRepository;
use App\Database\Repositories\ScannerRunRepository;
use App\Database\Repositories\SettingsRepository;
use App\Database\Repositories\SymbolRepository;
use App\Exchange\ExchangeManager;
use App\Logger\LoggerFactory;
use App\Market\CandleManager;
use App\Market\MarketScanner;
use App\Market\OrderBookManager;
use App\Market\SymbolManager;
use App\Market\VolumeAnalyzer;
use App\Support\TtlCache;

final class MarketServiceProvider
{
    public static function register(Application $app): void
    {
        $app->singleton(ExchangeRepository::class, static fn (Application $app): ExchangeRepository => new ExchangeRepository($app->get(Database::class)));
        $app->singleton(SymbolRepository::class, static fn (Application $app): SymbolRepository => new SymbolRepository($app->get(Database::class)));
        $app->singleton(MarketDataRepository::class, static fn (Application $app): MarketDataRepository => new MarketDataRepository($app->get(Database::class)));
        $app->singleton(CandleRepository::class, static fn (Application $app): CandleRepository => new CandleRepository($app->get(Database::class)));
        $app->singleton(ScannerRunRepository::class, static fn (Application $app): ScannerRunRepository => new ScannerRunRepository($app->get(Database::class)));

        $app->singleton(TtlCache::class, static fn (): TtlCache => new TtlCache());

        $app->singleton(SymbolManager::class, static function (Application $app): SymbolManager {
            return new SymbolManager(
                $app->get(ExchangeRepository::class),
                $app->get(SymbolRepository::class),
                $app->get(SettingsRepository::class),
                $app->get(LoggerFactory::class)->channel('scanner'),
            );
        });

        $app->singleton(VolumeAnalyzer::class, static function (Application $app): VolumeAnalyzer {
            return new VolumeAnalyzer($app->get(MarketDataRepository::class), $app->get(SettingsRepository::class));
        });

        $app->singleton(CandleManager::class, static function (Application $app): CandleManager {
            return new CandleManager($app->get(CandleRepository::class), $app->get(LoggerFactory::class)->channel('scanner'));
        });

        $app->singleton(OrderBookManager::class, static function (Application $app): OrderBookManager {
            return new OrderBookManager($app->get(TtlCache::class));
        });

        $app->singleton(MarketScanner::class, static function (Application $app): MarketScanner {
            return new MarketScanner(
                $app->get(ExchangeManager::class),
                $app->get(ExchangeRepository::class),
                $app->get(SymbolManager::class),
                $app->get(VolumeAnalyzer::class),
                $app->get(SymbolRepository::class),
                $app->get(MarketDataRepository::class),
                $app->get(ScannerRunRepository::class),
                $app->get(LoggerFactory::class)->channel('scanner'),
            );
        });
    }
}
