<?php

declare(strict_types=1);

namespace App\Database\Repositories;

/**
 * Backs multi-step admin conversations (add channel, edit a text, ...).
 * Each webhook request is a stateless, fresh PHP process, so "what is this
 * admin in the middle of doing" has to live somewhere durable — here.
 */
final class ConversationStateRepository extends Repository
{
    /**
     * @return array{state: string, context: array<string, mixed>}|null
     */
    public function get(int $telegramUserId): ?array
    {
        $row = $this->db->selectOne(
            'SELECT state, context FROM admin_states WHERE telegram_user_id = :id',
            ['id' => $telegramUserId],
        );

        if ($row === null) {
            return null;
        }

        return [
            'state' => $row['state'],
            'context' => json_decode($row['context'], true, 512, JSON_THROW_ON_ERROR) ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function set(int $telegramUserId, string $state, array $context = []): void
    {
        $encodedContext = json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $existing = $this->db->selectOne(
            'SELECT telegram_user_id FROM admin_states WHERE telegram_user_id = :id',
            ['id' => $telegramUserId],
        );

        if ($existing === null) {
            $this->db->insert('admin_states', [
                'telegram_user_id' => $telegramUserId,
                'state' => $state,
                'context' => $encodedContext,
            ]);

            return;
        }

        $this->db->update(
            'admin_states',
            ['state' => $state, 'context' => $encodedContext],
            ['telegram_user_id' => $telegramUserId],
        );
    }

    public function clear(int $telegramUserId): void
    {
        $this->db->statement('DELETE FROM admin_states WHERE telegram_user_id = :id', ['id' => $telegramUserId]);
    }
}
