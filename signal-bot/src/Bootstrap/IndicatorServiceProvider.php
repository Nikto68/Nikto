<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Core\Application;
use App\Indicators\IndicatorManager;

final class IndicatorServiceProvider
{
    public static function register(Application $app): void
    {
        $app->singleton(IndicatorManager::class, static fn (): IndicatorManager => new IndicatorManager());
    }
}
