<?php

declare(strict_types=1);

namespace App\Database\Repositories;

/**
 * The `exchanges` table row per adapter (spec #23 System Health reads
 * status/last_error from here) plus free-form exchange_settings overrides.
 */
final class ExchangeRepository extends Repository
{
    /**
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        return $this->db->selectOne('SELECT * FROM exchanges WHERE code = :code', ['code' => $code]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->db->select('SELECT * FROM exchanges ORDER BY code ASC');
    }

    public function markOnline(string $code): void
    {
        $this->db->statement(
            "UPDATE exchanges SET status = 'online', last_error = NULL, last_checked_at = :now WHERE code = :code",
            ['now' => date('Y-m-d H:i:s'), 'code' => $code],
        );
    }

    public function markDegraded(string $code, string $error): void
    {
        $this->db->statement(
            "UPDATE exchanges SET status = 'degraded', last_error = :error, last_checked_at = :now WHERE code = :code",
            ['error' => $error, 'now' => date('Y-m-d H:i:s'), 'code' => $code],
        );
    }

    public function markOffline(string $code, string $error): void
    {
        $this->db->statement(
            "UPDATE exchanges SET status = 'offline', last_error = :error, last_checked_at = :now WHERE code = :code",
            ['error' => $error, 'now' => date('Y-m-d H:i:s'), 'code' => $code],
        );
    }

    public function idForCode(string $code): ?int
    {
        $row = $this->findByCode($code);

        return $row === null ? null : (int) $row['id'];
    }
}
