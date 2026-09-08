<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Core\Application;
use App\Core\Config;
use App\Database\Database;
use App\Database\Repositories\AdminRepository;
use App\Database\Repositories\ConversationStateRepository;
use App\Database\Repositories\SettingsRepository;
use App\Database\Repositories\TextFormatRepository;
use App\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * Registers the collaborators every other provider (and most of the app)
 * depends on: config, logging, the database connection, and the handful of
 * repositories that are not specific to one feature area.
 */
final class CoreServiceProvider
{
    public static function register(Application $app): void
    {
        $app->singleton(Config::class, static function (): Config {
            return new Config(dirname(__DIR__, 2) . '/config');
        });

        $app->singleton(LoggerFactory::class, static function (Application $app): LoggerFactory {
            return new LoggerFactory($app->get(Config::class));
        });

        // Most classes just want a generic PSR-3 logger; anything needing a
        // specific channel type-hints LoggerFactory and calls ->channel(...).
        $app->singleton(LoggerInterface::class, static function (Application $app): LoggerInterface {
            return $app->get(LoggerFactory::class)->channel('app');
        });

        $app->singleton(Database::class, static function (Application $app): Database {
            return new Database(
                $app->get(Config::class),
                $app->get(LoggerFactory::class)->channel('app'),
            );
        });

        $app->singleton(SettingsRepository::class, static function (Application $app): SettingsRepository {
            return new SettingsRepository($app->get(Database::class));
        });

        $app->singleton(AdminRepository::class, static function (Application $app): AdminRepository {
            return new AdminRepository($app->get(Database::class));
        });

        $app->singleton(TextFormatRepository::class, static function (Application $app): TextFormatRepository {
            return new TextFormatRepository($app->get(Database::class));
        });

        $app->singleton(ConversationStateRepository::class, static function (Application $app): ConversationStateRepository {
            return new ConversationStateRepository($app->get(Database::class));
        });
    }
}
