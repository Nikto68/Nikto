<?php

declare(strict_types=1);

namespace App\Database\Repositories;

/**
 * The `jobs` table backing the DB queue (spec #29). Deliberately simple —
 * no external queue broker — since throughput here is "one job per signal
 * per channel", not per-symbol-per-scan.
 */
final class JobRepository extends Repository
{
    /**
     * @param array<string, mixed> $payload
     */
    public function push(string $jobType, array $payload, string $queue, int $delaySeconds, int $maxAttempts): void
    {
        $this->db->insert('jobs', [
            'queue' => $queue,
            'job_type' => $jobType,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'max_attempts' => $maxAttempts,
            'available_at' => date('Y-m-d H:i:s', time() + max(0, $delaySeconds)),
        ]);
    }

    /**
     * Reserves up to $limit due jobs atomically (UPDATE ... LIMIT then
     * re-select) so two queue workers running side by side never both
     * pick up the same job.
     *
     * @return array<int, array<string, mixed>>
     */
    public function reserveDue(string $queue, int $limit): array
    {
        $limit = max(1, $limit);
        $token = bin2hex(random_bytes(8));

        // Native prepared statements (PDO::ATTR_EMULATE_PREPARES = false)
        // cannot bind the same named placeholder twice, so `now` appears
        // as two distinct params even though both hold the same value.
        $now = date('Y-m-d H:i:s');
        $this->db->statement(
            "UPDATE jobs SET status = 'reserved', reserved_at = :now1, last_error = :token
             WHERE queue = :queue AND status = 'pending' AND available_at <= :now2
             ORDER BY available_at ASC LIMIT {$limit}",
            ['now1' => $now, 'now2' => $now, 'queue' => $queue, 'token' => "__reserving__{$token}"],
        );

        $rows = $this->db->select(
            "SELECT * FROM jobs WHERE queue = :queue AND status = 'reserved' AND last_error = :token",
            ['queue' => $queue, 'token' => "__reserving__{$token}"],
        );

        foreach ($rows as $row) {
            $this->db->update('jobs', ['last_error' => null], ['id' => $row['id']]);
        }

        return array_map(function (array $row): array {
            $row['payload'] = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR) ?? [];

            return $row;
        }, $rows);
    }

    public function markDone(int $id): void
    {
        $this->db->update('jobs', ['status' => 'done'], ['id' => $id]);
    }

    public function markFailed(int $id, string $error, int $attempts, int $maxAttempts, int $backoffSeconds): void
    {
        $status = $attempts >= $maxAttempts ? 'failed' : 'pending';

        $this->db->update('jobs', [
            'status' => $status,
            'attempts' => $attempts,
            'last_error' => mb_substr($error, 0, 2000),
            'available_at' => date('Y-m-d H:i:s', time() + $backoffSeconds),
        ], ['id' => $id]);
    }

    public function pendingCount(string $queue = 'default'): int
    {
        $row = $this->db->selectOne(
            "SELECT COUNT(*) AS c FROM jobs WHERE queue = :queue AND status IN ('pending', 'reserved')",
            ['queue' => $queue],
        );

        return (int) ($row['c'] ?? 0);
    }
}
