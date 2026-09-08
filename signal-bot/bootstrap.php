<?php

declare(strict_types=1);

/**
 * Shared entrypoint for public/webhook.php, every worker/*.php daemon, and
 * any CLI admin script. Loads .env, builds the DI container, and hands it
 * off to one ServiceProvider per module to register its own bindings —
 * see src/Bootstrap/*.php. Returns the ready-to-use Application instance.
 */

use App\Bootstrap\BotServiceProvider;
use App\Bootstrap\CoreServiceProvider;
use App\Bootstrap\ExchangeServiceProvider;
use App\Bootstrap\IndicatorServiceProvider;
use App\Bootstrap\MarketServiceProvider;
use App\Bootstrap\StrategyServiceProvider;
use App\Bootstrap\TelegramServiceProvider;
use App\Core\Application;
use Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    Dotenv::createImmutable(__DIR__)->load();
}

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

$app = new Application();

CoreServiceProvider::register($app);
TelegramServiceProvider::register($app);
ExchangeServiceProvider::register($app);
MarketServiceProvider::register($app);
IndicatorServiceProvider::register($app);
StrategyServiceProvider::register($app);
BotServiceProvider::register($app);

return $app;
