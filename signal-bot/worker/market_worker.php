#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * The long-running daemon (spec #2): loads config, syncs symbols, fetches
 * candles, detects zones, connects WebSockets, then runs the scan/signal/
 * queue cycle forever on internal timers — no cron anywhere. Restart-on-
 * crash is left to systemd/supervisor (see README.md); this process itself
 * only handles graceful shutdown on SIGTERM/SIGINT.
 *
 * Run directly for development: php worker/market_worker.php
 * In production, run under systemd/supervisor so a crash gets restarted —
 * see the [Unit]/[program:] examples in README.md.
 */

use App\Core\Application;
use App\Core\Config;
use App\Database\Repositories\AdminRepository;
use App\Logger\LoggerFactory;
use App\Worker\MarketWorkerRunner;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "market_worker.php must be run from the CLI.\n");
    exit(1);
}

/** @var Application $app */
$app = require dirname(__DIR__) . '/bootstrap.php';

/** @var LoggerInterface $logger */
$logger = $app->get(LoggerFactory::class)->channel('scanner');

set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($logger): bool {
    $logger->warning('PHP warning/notice', ['message' => $message, 'file' => $file, 'line' => $line]);

    return true;
});

set_exception_handler(static function (Throwable $e) use ($logger): void {
    $logger->error('Uncaught exception, worker exiting', [
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    exit(1);
});

// Idempotent bootstrap step — see the same call in public/webhook.php.
$app->get(AdminRepository::class)->syncFromConfig((array) $app->get(Config::class)->get('telegram.admin_ids', []));

$runner = $app->get(MarketWorkerRunner::class);

$shutdown = function (int $signal) use ($runner, $logger): void {
    $logger->info('Shutdown signal received, stopping gracefully', ['signal' => $signal]);
    $runner->stop();
    Loop::stop();
};

if (extension_loaded('pcntl')) {
    Loop::addSignal(SIGTERM, $shutdown);
    Loop::addSignal(SIGINT, $shutdown);
} else {
    $logger->warning('pcntl extension not loaded — graceful shutdown via SIGTERM/SIGINT is unavailable; the process will still be safely restartable by systemd/supervisor on SIGKILL.');
}

$runner->start();

$logger->info('Market worker event loop running');
Loop::run();

$logger->info('Market worker process exiting');
