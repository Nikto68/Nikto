<?php

declare(strict_types=1);

namespace App\Queue;

use App\Database\Repositories\JobRepository;

final class Queue implements QueueInterface
{
    public function __construct(
        private readonly JobRepository $jobs,
    ) {
    }

    public function push(string $jobType, array $payload, string $queue = 'default', int $delaySeconds = 0, int $maxAttempts = 5): void
    {
        $this->jobs->push($jobType, $payload, $queue, $delaySeconds, $maxAttempts);
    }
}
