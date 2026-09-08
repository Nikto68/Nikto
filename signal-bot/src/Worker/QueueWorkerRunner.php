<?php

declare(strict_types=1);

namespace App\Worker;

use App\Queue\QueueWorker;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use Throwable;

/**
 * Drains the DB queue on a timer (spec #29). MarketWorkerRunner calls
 * tick() itself so a single `market_worker.php` process is enough by
 * default; start() is for running queue draining as its own supervised
 * process (worker/queue_worker.php) when scaling signal volume needs it.
 * Both can run at once safely — JobRepository::reserveDue() reserves rows
 * atomically, so two consumers never process the same job twice.
 */
final class QueueWorkerRunner
{
    public function __construct(
        private readonly QueueWorker $queueWorker,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function tick(int $limit = 10): void
    {
        try {
            $processed = $this->queueWorker->processBatch('default', $limit);
            if ($processed > 0) {
                $this->logger->debug('Queue batch processed', ['count' => $processed]);
            }
        } catch (Throwable $e) {
            $this->logger->error('Queue tick failed', ['exception' => $e->getMessage()]);
        }
    }

    public function start(float $intervalSeconds = 3.0): void
    {
        $this->logger->info('Queue worker started', ['interval_seconds' => $intervalSeconds]);
        Loop::addPeriodicTimer($intervalSeconds, function (): void {
            $this->tick();
        });
    }
}
