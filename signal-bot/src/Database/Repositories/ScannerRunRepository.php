<?php

declare(strict_types=1);

namespace App\Database\Repositories;

/**
 * One row per scan cycle — backs the "Last Scan: 17:42:21" / symbol counts
 * shown in System Health (spec #23).
 */
final class ScannerRunRepository extends Repository
{
    public function start(?int $exchangeId = null): int
    {
        return (int) $this->db->insert('scanner_runs', [
            'exchange_id' => $exchangeId,
            'status' => 'running',
        ]);
    }

    public function finish(int $runId, int $symbolsScanned, int $signalsGenerated, int $errorsCount, string $status = 'completed', ?string $notes = null): void
    {
        $this->db->update('scanner_runs', [
            'finished_at' => date('Y-m-d H:i:s'),
            'symbols_scanned' => $symbolsScanned,
            'signals_generated' => $signalsGenerated,
            'errors_count' => $errorsCount,
            'status' => $status,
            'notes' => $notes,
        ], ['id' => $runId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        return $this->db->selectOne('SELECT * FROM scanner_runs ORDER BY id DESC LIMIT 1');
    }

    public function signalsToday(): int
    {
        $row = $this->db->selectOne(
            "SELECT COALESCE(SUM(signals_generated), 0) AS total FROM scanner_runs WHERE started_at >= CURDATE()",
        );

        return (int) ($row['total'] ?? 0);
    }

    public function errorsToday(): int
    {
        $row = $this->db->selectOne(
            "SELECT COALESCE(SUM(errors_count), 0) AS total FROM scanner_runs WHERE started_at >= CURDATE()",
        );

        return (int) ($row['total'] ?? 0);
    }
}
