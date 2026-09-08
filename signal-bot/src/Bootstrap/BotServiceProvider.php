<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Bot\Admin\Channels\AddChannelStateHandler;
use App\Bot\Admin\Channels\ChannelsCallbackHandler;
use App\Bot\Admin\Channels\ChannelsCommand;
use App\Bot\Admin\Channels\ChatMemberUpdateHandler;
use App\Bot\Admin\Channels\SetMinScoreStateHandler;
use App\Bot\Admin\Texts\AddTextKeyStateHandler;
use App\Bot\Admin\Texts\TextEditStateHandler;
use App\Bot\Admin\Texts\TextsCallbackHandler;
use App\Bot\Admin\Texts\TextsCommand;
use App\Bot\Bot;
use App\Bot\Commands\StartCommand;
use App\Core\Application;
use App\Database\Repositories\AdminRepository;
use App\Database\Repositories\ChannelRepository;
use App\Database\Repositories\ConversationStateRepository;
use App\Database\Repositories\TextFormatRepository;
use App\Logger\LoggerFactory;
use App\Telegram\ChannelManager;
use App\Telegram\TelegramSender;

/**
 * Wires the Bot router together with every command/callback/state handler.
 * Adding a new admin section later (Scanner, Strategies, Templates, ...)
 * means adding its own registrations here — Bot.php itself never changes.
 */
final class BotServiceProvider
{
    public static function register(Application $app): void
    {
        $app->singleton(ChannelsCallbackHandler::class, static function (Application $app): ChannelsCallbackHandler {
            return new ChannelsCallbackHandler(
                $app->get(ChannelRepository::class),
                $app->get(ChannelManager::class),
                $app->get(TelegramSender::class),
                $app->get(ConversationStateRepository::class),
            );
        });

        $app->singleton(TextsCallbackHandler::class, static function (Application $app): TextsCallbackHandler {
            return new TextsCallbackHandler(
                $app->get(TextFormatRepository::class),
                $app->get(TelegramSender::class),
                $app->get(ConversationStateRepository::class),
            );
        });

        $app->singleton(Bot::class, static function (Application $app): Bot {
            $bot = new Bot(
                $app->get(AdminRepository::class),
                $app->get(TextFormatRepository::class),
                $app->get(TelegramSender::class),
                $app->get(LoggerFactory::class)->channel('app'),
                $app->get(ConversationStateRepository::class),
            );

            $bot->registerCommand('/start', new StartCommand(
                $app->get(TextFormatRepository::class),
                $app->get(TelegramSender::class),
            ));

            $bot->registerCommand('/channels', new ChannelsCommand($app->get(ChannelsCallbackHandler::class)));
            $bot->registerCallbackHandler($app->get(ChannelsCallbackHandler::class));

            $bot->registerStateHandler('channels:awaiting_identifier', new AddChannelStateHandler(
                $app->get(ChannelManager::class),
                $app->get(TelegramSender::class),
                $app->get(ConversationStateRepository::class),
            ));

            $bot->registerStateHandler('channels:awaiting_min_score', new SetMinScoreStateHandler(
                $app->get(ChannelRepository::class),
                $app->get(TelegramSender::class),
                $app->get(ConversationStateRepository::class),
            ));

            $bot->registerMyChatMemberHandler(new ChatMemberUpdateHandler(
                $app->get(ChannelRepository::class),
                $app->get(LoggerFactory::class)->channel('telegram'),
            ));

            $bot->registerCommand('/texts', new TextsCommand($app->get(TextsCallbackHandler::class)));
            $bot->registerCallbackHandler($app->get(TextsCallbackHandler::class));

            $bot->registerStateHandler('texts:awaiting_value', new TextEditStateHandler(
                $app->get(TextFormatRepository::class),
                $app->get(TelegramSender::class),
                $app->get(ConversationStateRepository::class),
            ));

            $bot->registerStateHandler('texts:awaiting_new_key', new AddTextKeyStateHandler(
                $app->get(TelegramSender::class),
                $app->get(ConversationStateRepository::class),
            ));

            return $bot;
        });
    }
}
