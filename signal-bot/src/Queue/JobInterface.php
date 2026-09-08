<?php

declare(strict_types=1);

namespace App\Queue;

/**
 * One job class per job_type string. QueueWorker resolves job_type ->
 * JobInterface via its registry and calls handle() with the decoded
 * payload — adding a new job type never touches QueueWorker itself.
 */
interface JobInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload): void;
}
