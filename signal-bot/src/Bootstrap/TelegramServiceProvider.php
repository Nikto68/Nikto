<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Core\Application;
use App\Core\Config;
use App\Database\Database;
use App\Database\Repositories\ChannelRepository;
use App\Logger\LoggerFactory;
use App\Telegram\ChannelManager;
use App\Telegram\TelegramClient;
use App\Telegram\TelegramSender;

final class TelegramServiceProvider
{
    public static function register(Application $app): void
    {
        $app->singleton(TelegramClient::class, static function (Application $app): TelegramClient {
            return new TelegramClient($app->get(Config::class), $app->get(LoggerFactory::class)->channel('telegram'));
        });

        $app->singleton(TelegramSender::class, static function (Application $app): TelegramSender {
            return new TelegramSender($app->get(TelegramClient::class), $app->get(LoggerFactory::class)->channel('telegram'));
        });

        $app->singleton(ChannelRepository::class, static function (Application $app): ChannelRepository {
            return new ChannelRepository($app->get(Database::class));
        });

        $app->singleton(ChannelManager::class, static function (Application $app): ChannelManager {
            return new ChannelManager($app->get(TelegramSender::class), $app->get(ChannelRepository::class));
        });
    }
}
