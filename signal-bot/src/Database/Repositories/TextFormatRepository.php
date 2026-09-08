<?php

declare(strict_types=1);

namespace App\Database\Repositories;

use App\Telegram\TelegramEntities;

/**
 * Every user-facing string the bot sends (spec #20) lives in text_formats,
 * keyed by text_key, with its Telegram entities stored alongside so premium
 * emoji/bold/spoiler survive edits made from inside the bot's Text Manager.
 *
 * Optionally also carries quote/reply metadata (spec #19): if an admin
 * edits a text by replying to another message in the editor chat, that
 * message is captured as the quote this text should carry when sent.
 */
final class TextFormatRepository extends Repository
{
    /**
     * @return array{text: string, entities: array<int, array<string, mixed>>, parse_mode: ?string, reply_to_message_id: ?int, quote_text: ?string, quote_entities: array<int, array<string, mixed>>, quote_position: ?int}|null
     */
    public function find(string $key): ?array
    {
        $row = $this->db->selectOne(
            'SELECT text_value, entities, parse_mode, reply_to_message_id, quote_text, quote_entities, quote_position
             FROM text_formats WHERE text_key = :key',
            ['key' => $key],
        );

        if ($row === null) {
            return null;
        }

        return [
            'text' => $row['text_value'],
            'entities' => TelegramEntities::decode($row['entities']),
            'parse_mode' => $row['parse_mode'],
            'reply_to_message_id' => $row['reply_to_message_id'] === null ? null : (int) $row['reply_to_message_id'],
            'quote_text' => $row['quote_text'],
            'quote_entities' => $row['quote_entities'] === null ? [] : TelegramEntities::decode($row['quote_entities']),
            'quote_position' => $row['quote_position'] === null ? null : (int) $row['quote_position'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->db->select('SELECT text_key, text_value, entities, updated_at FROM text_formats ORDER BY text_key ASC');
    }

    /**
     * @param array<int, array<string, mixed>> $entities
     * @param array{reply_to_message_id?: int, quote_text?: string, quote_entities?: array<int, array<string, mixed>>, quote_position?: int}|null $reply
     */
    public function save(
        string $key,
        string $text,
        array $entities,
        ?int $updatedByAdminId = null,
        ?string $parseMode = null,
        ?array $reply = null,
    ): void {
        $existing = $this->db->selectOne('SELECT id FROM text_formats WHERE text_key = :key', ['key' => $key]);

        $data = [
            'text_value' => $text,
            'entities' => TelegramEntities::encode(TelegramEntities::normalize($entities)),
            'parse_mode' => $parseMode,
            'updated_by_admin_id' => $updatedByAdminId,
            'reply_to_message_id' => $reply['reply_to_message_id'] ?? null,
            'quote_text' => $reply['quote_text'] ?? null,
            'quote_entities' => isset($reply['quote_entities']) ? TelegramEntities::encode($reply['quote_entities']) : null,
            'quote_position' => $reply['quote_position'] ?? null,
        ];

        if ($existing === null) {
            $this->db->insert('text_formats', ['text_key' => $key, ...$data]);

            return;
        }

        $this->db->update('text_formats', $data, ['text_key' => $key]);
    }

    public function delete(string $key): int
    {
        return $this->db->statement('DELETE FROM text_formats WHERE text_key = :key', ['key' => $key]);
    }

    public function exists(string $key): bool
    {
        return $this->find($key) !== null;
    }
}
