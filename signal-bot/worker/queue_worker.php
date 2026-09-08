#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Optional standalone queue consumer. market_worker.php already drains the
 * queue itself on its own timer, so running this is only needed if signal/
 * channel volume grows enough to want queue draining as its own supervised
 * process, independent of the scanner. Safe to run alongside
 * market_worker.php — JobRepository::reserveDue() reserves jobs atomically.
 */

use App\Core\Application;
use App\Logger\LoggerFactory;
use App\Worker\QueueWorkerRunner;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "queue_worker.php must be run from the CLI.\n");
    exit(1);
}

/** @var Application $app */
$app = require dirname(__DIR__) . '/bootstrap.php';

/** @var LoggerInterface $logger */
$logger = $app->get(LoggerFactory::class)->channel('signal');

set_exception_handler(static function (Throwable $e) use ($logger): void {
    $logger->error('Uncaught exception, queue worker exiting', ['exception' => $e->getMessage()]);
    exit(1);
});

$runner = $app->get(QueueWorkerRunner::class);

if (extension_loaded('pcntl')) {
    $shutdown = function (int $signal) use ($logger): void {
        $logger->info('Shutdown signal received', ['signal' => $signal]);
        Loop::stop();
    };
    Loop::addSignal(SIGTERM, $shutdown);
    Loop::addSignal(SIGINT, $shutdown);
}

$runner->start();

$logger->info('Queue worker event loop running');
Loop::run();

$logger->info('Queue worker process exiting');
