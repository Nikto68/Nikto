<?php

declare(strict_types=1);

namespace App\Queue;

use App\Database\Repositories\JobRepository;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Drains the `jobs` table: reserve a batch, hand each to its registered
 * JobInterface, retry with exponential backoff on failure up to
 * max_attempts (spec #29). One job failing (e.g. a transient Telegram API
 * error) never affects the others in the batch.
 */
final class QueueWorker
{
    /** @var array<string, JobInterface> */
    private array $jobs = [];

    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function registerJob(string $jobType, JobInterface $job): void
    {
        $this->jobs[$jobType] = $job;
    }

    /**
     * @return int jobs processed in this batch
     */
    public function processBatch(string $queue = 'default', int $limit = 10): int
    {
        $due = $this->jobRepository->reserveDue($queue, $limit);

        foreach ($due as $job) {
            $this->runOne($job);
        }

        return count($due);
    }

    /**
     * @param array<string, mixed> $jobRow
     */
    private function runOne(array $jobRow): void
    {
        $handler = $this->jobs[$jobRow['job_type']] ?? null;

        if ($handler === null) {
            $this->logger->error('No handler registered for job type', ['job_type' => $jobRow['job_type']]);
            $this->jobRepository->markFailed((int) $jobRow['id'], 'no handler registered', (int) $jobRow['attempts'] + 1, (int) $jobRow['max_attempts'], 60);

            return;
        }

        try {
            $handler->handle($jobRow['payload']);
            $this->jobRepository->markDone((int) $jobRow['id']);
        } catch (Throwable $e) {
            $attempts = (int) $jobRow['attempts'] + 1;
            $backoff = min(300, (int) (2 ** $attempts));

            $this->logger->warning('Queue job failed', [
                'job_type' => $jobRow['job_type'],
                'attempt' => $attempts,
                'exception' => $e->getMessage(),
            ]);

            $this->jobRepository->markFailed((int) $jobRow['id'], $e->getMessage(), $attempts, (int) $jobRow['max_attempts'], $backoff);
        }
    }
}
