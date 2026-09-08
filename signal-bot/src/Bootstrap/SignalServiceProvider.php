<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Core\Application;
use App\Database\Database;
use App\Database\Repositories\ChannelRepository;
use App\Database\Repositories\MarketDataRepository;
use App\Database\Repositories\SettingsRepository;
use App\Database\Repositories\SignalRepository;
use App\Database\Repositories\SignalTemplateRepository;
use App\Database\Repositories\SymbolRepository;
use App\Logger\LoggerFactory;
use App\Queue\QueueInterface;
use App\Signal\SignalCooldown;
use App\Signal\SignalEngine;
use App\Signal\SignalFormatter;
use App\Signal\SignalValidator;

final class SignalServiceProvider
{
    public static function register(Application $app): void
    {
        $app->singleton(SignalRepository::class, static fn (Application $app): SignalRepository => new SignalRepository($app->get(Database::class)));
        $app->singleton(SignalTemplateRepository::class, static fn (Application $app): SignalTemplateRepository => new SignalTemplateRepository($app->get(Database::class)));

        $app->singleton(SignalCooldown::class, static function (Application $app): SignalCooldown {
            return new SignalCooldown($app->get(SignalRepository::class), $app->get(SettingsRepository::class));
        });

        $app->singleton(SignalValidator::class, static function (Application $app): SignalValidator {
            return new SignalValidator($app->get(SettingsRepository::class), $app->get(SignalCooldown::class));
        });

        $app->singleton(SignalFormatter::class, static fn (): SignalFormatter => new SignalFormatter());

        $app->singleton(SignalEngine::class, static function (Application $app): SignalEngine {
            return new SignalEngine(
                $app->get(SignalValidator::class),
                $app->get(SignalRepository::class),
                $app->get(SymbolRepository::class),
                $app->get(MarketDataRepository::class),
                $app->get(ChannelRepository::class),
                $app->get(QueueInterface::class),
                $app->get(SettingsRepository::class),
                $app->get(LoggerFactory::class)->channel('signal'),
            );
        });
    }
}
