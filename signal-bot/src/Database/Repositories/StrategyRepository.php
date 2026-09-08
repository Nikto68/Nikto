<?php

declare(strict_types=1);

namespace App\Database\Repositories;

/**
 * The `strategies` table: registry + STRATEGY CONFIGURATION (spec #36) —
 * HTF/confirmation/entry timeframes, min score, and the open `config` JSON
 * blob ConfluenceEngine reads weights/required confluences from. A
 * strategy row with is_active = 0 never runs; every seeded/example
 * strategy stays inactive until real rules are supplied.
 */
final class StrategyRepository extends Repository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function active(): array
    {
        return array_map(
            fn (array $row): array => $this->decode($row),
            $this->db->select("SELECT * FROM strategies WHERE is_active = 1"),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_map(fn (array $row): array => $this->decode($row), $this->db->select('SELECT * FROM strategies ORDER BY code ASC'));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        $row = $this->db->selectOne('SELECT * FROM strategies WHERE code = :code', ['code' => $code]);

        return $row === null ? null : $this->decode($row);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function upsert(string $code, string $name, ?string $className, array $config, bool $isActive = false): string
    {
        $existing = $this->db->selectOne('SELECT id FROM strategies WHERE code = :code', ['code' => $code]);
        $data = [
            'name' => $name,
            'class_name' => $className,
            'config' => json_encode($config, JSON_THROW_ON_ERROR),
            'is_active' => $isActive ? 1 : 0,
            'htf_timeframe' => $config['timeframes']['htf'] ?? null,
            'confirmation_timeframe' => $config['timeframes']['confirmation'] ?? null,
            'entry_timeframe' => $config['timeframes']['entry'] ?? null,
            'min_score' => $config['min_score'] ?? 75,
        ];

        if ($existing === null) {
            return $this->db->insert('strategies', ['code' => $code, ...$data]);
        }

        $this->db->update('strategies', $data, ['code' => $code]);

        return (string) $existing['id'];
    }

    public function setActive(string $code, bool $active): void
    {
        $this->db->statement('UPDATE strategies SET is_active = :active WHERE code = :code', ['active' => $active ? 1 : 0, 'code' => $code]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decode(array $row): array
    {
        $row['config'] = json_decode($row['config'], true, 512, JSON_THROW_ON_ERROR) ?? [];

        return $row;
    }
}
