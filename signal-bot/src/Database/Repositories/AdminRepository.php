<?php

declare(strict_types=1);

namespace App\Database\Repositories;

/**
 * Who is allowed into the Telegram admin panel. syncFromConfig() bootstraps
 * TELEGRAM_ADMIN_IDS (env) into the table as super_admins on first boot;
 * after that the `admins` table is authoritative and can be managed from
 * inside the bot itself.
 */
final class AdminRepository extends Repository
{
    public function isAdmin(int $telegramUserId): bool
    {
        return $this->db->selectOne(
            'SELECT id FROM admins WHERE telegram_user_id = :id',
            ['id' => $telegramUserId],
        ) !== null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->db->select('SELECT * FROM admins ORDER BY created_at ASC');
    }

    public function add(int $telegramUserId, string $role = 'admin', ?string $username = null, ?int $addedBy = null): string
    {
        return $this->db->insert('admins', [
            'telegram_user_id' => $telegramUserId,
            'username' => $username,
            'role' => $role,
            'added_by' => $addedBy,
        ]);
    }

    public function remove(int $telegramUserId): int
    {
        return $this->db->statement('DELETE FROM admins WHERE telegram_user_id = :id', ['id' => $telegramUserId]);
    }

    /**
     * @param int[] $telegramUserIds
     */
    public function syncFromConfig(array $telegramUserIds): void
    {
        foreach ($telegramUserIds as $id) {
            if ($id > 0 && !$this->isAdmin($id)) {
                $this->add($id, 'super_admin');
            }
        }
    }
}
