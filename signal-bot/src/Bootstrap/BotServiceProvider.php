<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Bot\Admin\AdminHandler;
use App\Bot\Admin\Channels\AddChannelStateHandler;
use App\Bot\Admin\Channels\ChannelsCallbackHandler;
use App\Bot\Admin\Channels\ChannelsCommand;
use App\Bot\Admin\Channels\ChatMemberUpdateHandler;
use App\Bot\Admin\Channels\SetMinScoreStateHandler;
use App\Bot\Admin\Exchanges\ExchangesCallbackHandler;
use App\Bot\Admin\Health\HealthCallbackHandler;
use App\Bot\Admin\Indicators\IndicatorsCallbackHandler;
use App\Bot\Admin\Logs\LogsCallbackHandler;
use App\Bot\Admin\Settings\SettingsCallbackHandler;
use App\Bot\Admin\Settings\SettingsEditStateHandler;
use App\Bot\Admin\Signals\SignalHistoryCallbackHandler;
use App\Bot\Admin\Signals\TestSignalCallbackHandler;
use App\Bot\Admin\Signals\TestSignalStateHandler;
use App\Bot\Admin\Strategies\StrategiesCallbackHandler;
use App\Bot\Admin\Templates\AddTemplateNameStateHandler;
use App\Bot\Admin\Templates\TemplateEditStateHandler;
use App\Bot\Admin\Templates\TemplatesCallbackHandler;
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
use App\Database\Repositories\ExchangeRepository;
use App\Database\Repositories\FvgRepository;
use App\Database\Repositories\LogRepository;
use App\Database\Repositories\MarketDataRepository;
use App\Database\Repositories\OrderBlockRepository;
use App\Database\Repositories\ScannerRunRepository;
use App\Database\Repositories\SettingsRepository;
use App\Database\Repositories\SignalRepository;
use App\Database\Repositories\SignalTemplateRepository;
use App\Database\Repositories\StrategyRepository;
use App\Database\Repositories\SymbolRepository;
use App\Database\Repositories\TextFormatRepository;
use App\Exchange\ExchangeManager;
use App\Indicators\IndicatorManager;
use App\Logger\LoggerFactory;
use App\Market\CandleManager;
use App\Signal\SignalFormatter;
use App\Strategy\FVG;
use App\Strategy\OrderBlock;
use App\Strategy\StrategyEngine;
use App\Strategy\StrategyRegistry;
use App\Strategy\SupportResistance;
use App\Telegram\ChannelManager;
use App\Telegram\TelegramSender;

/**
 * Wires the Bot router together with every command/callback/state handler.
 * Adding a new admin section means adding its own registrations here —
 * Bot.php itself never changes (spec #34, no God class).
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

        $app->singleton(SettingsCallbackHandler::class, static function (Application $app): SettingsCallbackHandler {
            return new SettingsCallbackHandler(
                $app->get(SettingsRepository::class),
                $app->get(TelegramSender::class),
                $app->get(ConversationStateRepository::class),
            );
        });

        $app->singleton(TemplatesCallbackHandler::class, static function (Application $app): TemplatesCallbackHandler {
            return new TemplatesCallbackHandler(
                $app->get(SignalTemplateRepository::class),
                $app->get(TelegramSender::class),
                $app->get(ConversationStateRepository::class),
            );
        });

        $app->singleton(TestSignalStateHandler::class, static function (Application $app): TestSignalStateHandler {
            return new TestSignalStateHandler(
                $app->get(SymbolRepository::class),
                $app->get(ExchangeManager::class),
                $app->get(CandleManager::class),
                $app->get(IndicatorManager::class),
                $app->get(SupportResistance::class),
                $app->get(OrderBlock::class),
                $app->get(OrderBlockRepository::class),
                $app->get(FVG::class),
                $app->get(FvgRepository::class),
                $app->get(StrategyEngine::class),
                $app->get(SignalFormatter::class),
                $app->get(SettingsRepository::class),
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

            $adminHandler = new AdminHandler($app->get(TelegramSender::class));
            $bot->registerCommand('/admin', $adminHandler);
            $bot->registerCallbackHandler($adminHandler);

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

            $bot->registerCallbackHandler(new ExchangesCallbackHandler(
                $app->get(ExchangeRepository::class),
                $app->get(TelegramSender::class),
            ));

            $bot->registerCallbackHandler($app->get(SettingsCallbackHandler::class));
            $bot->registerStateHandler('settings:awaiting_value', new SettingsEditStateHandler(
                $app->get(SettingsRepository::class),
                $app->get(TelegramSender::class),
                $app->get(ConversationStateRepository::class),
            ));

            $bot->registerCallbackHandler(new StrategiesCallbackHandler(
                $app->get(StrategyRepository::class),
                $app->get(StrategyRegistry::class),
                $app->get(TelegramSender::class),
            ));

            $bot->registerCallbackHandler(new IndicatorsCallbackHandler(
                $app->get(IndicatorManager::class),
                $app->get(TelegramSender::class),
            ));

            $bot->registerCallbackHandler($app->get(TemplatesCallbackHandler::class));
            $bot->registerStateHandler('templates:awaiting_value', new TemplateEditStateHandler(
                $app->get(SignalTemplateRepository::class),
                $app->get(TelegramSender::class),
                $app->get(ConversationStateRepository::class),
            ));
            $bot->registerStateHandler('templates:awaiting_new_name', new AddTemplateNameStateHandler(
                $app->get(SignalTemplateRepository::class),
                $app->get(TelegramSender::class),
                $app->get(ConversationStateRepository::class),
            ));

            $bot->registerCallbackHandler(new SignalHistoryCallbackHandler(
                $app->get(SignalRepository::class),
                $app->get(TelegramSender::class),
            ));

            $bot->registerCallbackHandler(new LogsCallbackHandler(
                $app->get(LogRepository::class),
                $app->get(TelegramSender::class),
            ));

            $bot->registerCallbackHandler(new HealthCallbackHandler(
                $app->get(ScannerRunRepository::class),
                $app->get(ExchangeRepository::class),
                $app->get(MarketDataRepository::class),
                $app->get(SignalRepository::class),
                $app->get(LogRepository::class),
                $app->get(SettingsRepository::class),
                $app->get(TelegramSender::class),
            ));

            $bot->registerCallbackHandler(new TestSignalCallbackHandler(
                $app->get(TelegramSender::class),
                $app->get(ConversationStateRepository::class),
            ));
            $bot->registerStateHandler('testsignal:awaiting_symbol', $app->get(TestSignalStateHandler::class));

            return $bot;
        });
    }
}
