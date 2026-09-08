<?php

declare(strict_types=1);

namespace App\Database\Repositories;

/**
 * Telegram channels the bot may publish signals to, each with its own
 * filters (minimum score, direction, allowed strategies) so one deployment
 * can run "score > 80 only" and "LONG only" channels side by side (spec #16).
 */
final class ChannelRepository extends Repository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(bool $onlyEnabled = false): array
    {
        $sql = 'SELECT * FROM channels';
        if ($onlyEnabled) {
            $sql .= ' WHERE enabled = 1';
        }
        $sql .= ' ORDER BY created_at DESC';

        return $this->db->select($sql);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM channels WHERE id = :id', ['id' => $id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByChatId(int $chatId): ?array
    {
        return $this->db->selectOne('SELECT * FROM channels WHERE chat_id = :chat_id', ['chat_id' => $chatId]);
    }

    public function create(
        int $chatId,
        ?string $username,
        ?string $title,
        string $type,
        bool $botIsAdmin,
        bool $botCanPost,
        ?int $addedByAdminId,
    ): string {
        return $this->db->insert('channels', [
            'chat_id' => $chatId,
            'username' => $username,
            'title' => $title,
            'type' => $type,
            'bot_is_admin' => $botIsAdmin ? 1 : 0,
            'bot_can_post' => $botCanPost ? 1 : 0,
            'permissions_checked_at' => date('Y-m-d H:i:s'),
            'added_by_admin_id' => $addedByAdminId,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): int
    {
        return $this->db->update('channels', $data, ['id' => $id]);
    }

    public function delete(int $id): int
    {
        return $this->db->statement('DELETE FROM channels WHERE id = :id', ['id' => $id]);
    }

    public function setEnabled(int $id, bool $enabled): void
    {
        $this->db->update('channels', ['enabled' => $enabled ? 1 : 0], ['id' => $id]);
    }

    public function updatePermissions(int $id, bool $botIsAdmin, bool $botCanPost): void
    {
        $this->db->update('channels', [
            'bot_is_admin' => $botIsAdmin ? 1 : 0,
            'bot_can_post' => $botCanPost ? 1 : 0,
            'permissions_checked_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function getSetting(int $channelId, string $key, mixed $default = null): mixed
    {
        $row = $this->db->selectOne(
            'SELECT setting_value FROM channel_settings WHERE channel_id = :channel_id AND setting_key = :key',
            ['channel_id' => $channelId, 'key' => $key],
        );

        return $row === null ? $default : $row['setting_value'];
    }

    public function setSetting(int $channelId, string $key, string $value): void
    {
        $existing = $this->db->selectOne(
            'SELECT id FROM channel_settings WHERE channel_id = :channel_id AND setting_key = :key',
            ['channel_id' => $channelId, 'key' => $key],
        );

        if ($existing === null) {
            $this->db->insert('channel_settings', ['channel_id' => $channelId, 'setting_key' => $key, 'setting_value' => $value]);

            return;
        }

        $this->db->update('channel_settings', ['setting_value' => $value], ['id' => $existing['id']]);
    }

    /**
     * @return string[]
     */
    public function enabledStrategyCodes(int $channelId): array
    {
        $rows = $this->db->select(
            'SELECT s.code FROM channel_strategies cs
             JOIN strategies s ON s.id = cs.strategy_id
             WHERE cs.channel_id = :channel_id',
            ['channel_id' => $channelId],
        );

        return array_column($rows, 'code');
    }

    /**
     * @param int[] $strategyIds
     */
    public function setStrategies(int $channelId, array $strategyIds): void
    {
        $this->db->transaction(function () use ($channelId, $strategyIds): void {
            $this->db->statement('DELETE FROM channel_strategies WHERE channel_id = :channel_id', ['channel_id' => $channelId]);

            foreach ($strategyIds as $strategyId) {
                $this->db->insert('channel_strategies', ['channel_id' => $channelId, 'strategy_id' => $strategyId]);
            }
        });
    }

    /**
     * Enabled channels whose filters (minimum_score, direction_filter,
     * enabled_strategies) let this signal through, and whose bot
     * permissions were last confirmed OK (spec #16's per-channel routing:
     * "Channel A -> score > 80", "Channel B -> everything", "Channel C ->
     * LONG only").
     *
     * @return array<int, array<string, mixed>>
     */
    public function matchingChannels(int $score, string $direction, ?int $strategyId): array
    {
        $channels = $this->db->select(
            "SELECT * FROM channels
             WHERE enabled = 1 AND bot_can_post = 1
               AND minimum_score <= :score
               AND (direction_filter = 'ANY' OR direction_filter = :direction)",
            ['score' => $score, 'direction' => $direction],
        );

        return array_values(array_filter(
            $channels,
            function (array $channel) use ($strategyId): bool {
                $allowed = $this->allowedStrategyIds((int) $channel['id']);

                return $allowed === [] || $strategyId === null || in_array((string) $strategyId, $allowed, true);
            },
        ));
    }

    /**
     * @return string[]
     */
    private function allowedStrategyIds(int $channelId): array
    {
        $rows = $this->db->select('SELECT strategy_id FROM channel_strategies WHERE channel_id = :channel_id', ['channel_id' => $channelId]);

        return array_map(static fn (array $r): string => (string) $r['strategy_id'], $rows);
    }
}
