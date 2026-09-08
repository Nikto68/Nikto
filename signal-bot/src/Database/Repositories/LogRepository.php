<?php

declare(strict_types=1);

namespace App\Database\Repositories;

/**
 * Reads the `logs` table DatabaseLogHandler writes to (ERROR+ only,
 * mirrored from every channel) — backs System Health's "Errors Today" and
 * the admin panel's "📋 Logs" screen (spec #23/#24).
 */
final class LogRepository extends Repository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 20, ?string $component = null): array
    {
        $limit = max(1, $limit);
        $sql = 'SELECT * FROM logs';
        $params = [];

        if ($component !== null) {
            $sql .= ' WHERE component = :component';
            $params['component'] = $component;
        }

        $sql .= " ORDER BY id DESC LIMIT {$limit}";

        return $this->db->select($sql, $params);
    }

    public function errorsToday(): int
    {
        $row = $this->db->selectOne("SELECT COUNT(*) AS c FROM logs WHERE created_at >= CURDATE()");

        return (int) ($row['c'] ?? 0);
    }
}
