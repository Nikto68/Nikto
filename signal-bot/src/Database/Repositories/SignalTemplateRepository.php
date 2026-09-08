<?php

declare(strict_types=1);

namespace App\Database\Repositories;

use App\Telegram\TelegramEntities;

/**
 * Signal message templates (spec #17) — stored the same way as
 * text_formats (raw text + entities, never Markdown) so premium emoji and
 * other formatting placed around {placeholder} tokens survive rendering.
 * A channel with no signal_template_id falls back to the row where
 * is_default = 1.
 */
final class SignalTemplateRepository extends Repository
{
    /**
     * @return array{id: int, text: string, entities: array<int, array<string, mixed>>, parse_mode: ?string, reply_to_message_id: ?int, quote_text: ?string, quote_entities: array<int, array<string, mixed>>, quote_position: ?int}|null
     */
    public function find(int $id): ?array
    {
        return $this->hydrate($this->db->selectOne('SELECT * FROM signal_templates WHERE id = :id', ['id' => $id]));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function default(): ?array
    {
        return $this->hydrate($this->db->selectOne('SELECT * FROM signal_templates WHERE is_default = 1 LIMIT 1'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_map(fn (array $row): array => $this->hydrate($row), $this->db->select('SELECT * FROM signal_templates ORDER BY id ASC'));
    }

    /**
     * @param array<int, array<string, mixed>> $entities
     */
    public function create(string $name, string $text, array $entities, ?int $createdByAdminId = null): string
    {
        return $this->db->insert('signal_templates', [
            'name' => $name,
            'template_text' => $text,
            'entities' => TelegramEntities::encode(TelegramEntities::normalize($entities)),
            'is_default' => 0,
            'created_by_admin_id' => $createdByAdminId,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $entities
     */
    public function update(int $id, string $text, array $entities): void
    {
        $this->db->update('signal_templates', [
            'template_text' => $text,
            'entities' => TelegramEntities::encode(TelegramEntities::normalize($entities)),
        ], ['id' => $id]);
    }

    public function makeDefault(int $id): void
    {
        $this->db->transaction(function () use ($id): void {
            $this->db->statement('UPDATE signal_templates SET is_default = 0');
            $this->db->statement('UPDATE signal_templates SET is_default = 1 WHERE id = :id', ['id' => $id]);
        });
    }

    public function delete(int $id): int
    {
        return $this->db->statement('DELETE FROM signal_templates WHERE id = :id AND is_default = 0', ['id' => $id]);
    }

    /**
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>|null
     */
    private function hydrate(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'text' => $row['template_text'],
            'entities' => TelegramEntities::decode($row['entities']),
            'parse_mode' => $row['parse_mode'],
            'is_default' => (bool) $row['is_default'],
            'reply_to_message_id' => $row['reply_to_message_id'] === null ? null : (int) $row['reply_to_message_id'],
            'quote_text' => $row['quote_text'],
            'quote_entities' => $row['quote_entities'] === null ? [] : TelegramEntities::decode($row['quote_entities']),
            'quote_position' => $row['quote_position'] === null ? null : (int) $row['quote_position'],
        ];
    }
}
