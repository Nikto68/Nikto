<?php

declare(strict_types=1);

namespace App\Queue;

/**
 * Signal dispatch never calls Telegram directly (spec #29): SignalEngine
 * pushes a job here and returns immediately, so a slow/erroring Telegram
 * API call can never stall the scanner. See src/Queue/Queue.php for the
 * DB-backed implementation and worker/queue_worker.php for the consumer.
 */
interface QueueInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function push(string $jobType, array $payload, string $queue = 'default', int $delaySeconds = 0, int $maxAttempts = 5): void;
}
