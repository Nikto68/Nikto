<?php

declare(strict_types=1);

namespace App\Database\Repositories;

/**
 * Typed access to bot_settings — the database-driven runtime configuration
 * (scanner thresholds, mode, cooldowns, ...) editable from the admin panel
 * (spec #22). Values are stored as TEXT with an explicit value_type column
 * so callers get back int/float/bool/array, not strings to re-parse.
 */
final class SettingsRepository extends Repository
{
    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $rows = $this->db->select('SELECT setting_key, setting_value, value_type FROM bot_settings');

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $this->cast($row['setting_value'], $row['value_type']);
        }

        return $settings;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->db->selectOne(
            'SELECT setting_value, value_type FROM bot_settings WHERE setting_key = :key',
            ['key' => $key],
        );

        return $row === null ? $default : $this->cast($row['setting_value'], $row['value_type']);
    }

    public function set(string $key, mixed $value, string $type = 'string', ?int $updatedByAdminId = null): void
    {
        $stored = $type === 'json' ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value;

        $existing = $this->db->selectOne('SELECT id FROM bot_settings WHERE setting_key = :key', ['key' => $key]);

        if ($existing === null) {
            $this->db->insert('bot_settings', [
                'setting_key' => $key,
                'setting_value' => $stored,
                'value_type' => $type,
                'updated_by_admin_id' => $updatedByAdminId,
            ]);

            return;
        }

        $this->db->update(
            'bot_settings',
            ['setting_value' => $stored, 'value_type' => $type, 'updated_by_admin_id' => $updatedByAdminId],
            ['setting_key' => $key],
        );
    }

    private function cast(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true),
            'json' => json_decode($value, true, 512, JSON_THROW_ON_ERROR),
            default => $value,
        };
    }
}
