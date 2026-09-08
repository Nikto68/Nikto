<?php

declare(strict_types=1);

namespace App\Telegram;

/**
 * Renders a stored {text, entities} pair (a text_formats row or a
 * signal_templates row) into a final {text, entities} pair with
 * `{placeholder}` tokens substituted by real values, while keeping every
 * existing entity (premium custom_emoji included) anchored to the right
 * characters.
 *
 * If a template entity's span exactly wraps a placeholder token (e.g. bold
 * applied to the literal text "{symbol}"), that entity is stretched to wrap
 * the substituted value instead of the token — so an admin can bold/spoiler
 * a placeholder in the editor and have that formatting follow the real
 * value on every signal.
 */
final class TelegramFormatter
{
    /**
     * @param array<int, array<string, mixed>> $entities
     * @param array<string, string> $values token (without braces) => replacement text
     * @return array{text: string, entities: array<int, array<string, mixed>>}
     */
    public static function render(string $templateText, array $entities, array $values): array
    {
        $matches = [];
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $templateText, $matches, PREG_OFFSET_CAPTURE);

        if ($matches[0] === []) {
            return ['text' => $templateText, 'entities' => TelegramEntities::normalize($entities)];
        }

        $edits = [];
        $resultText = '';
        $cursor = 0;

        foreach ($matches[0] as $index => [$fullMatch, $byteOffset]) {
            $token = $matches[1][$index][0];
            if (!array_key_exists($token, $values)) {
                continue;
            }

            $replacement = $values[$token];

            $resultText .= substr($templateText, $cursor, $byteOffset - $cursor);
            $resultText .= $replacement;
            $cursor = $byteOffset + strlen($fullMatch);

            $utf16Start = TelegramEntities::utf16Length(substr($templateText, 0, $byteOffset));

            $edits[] = [
                'utf16_start' => $utf16Start,
                'old_length' => TelegramEntities::utf16Length($fullMatch),
                'new_length' => TelegramEntities::utf16Length($replacement),
            ];
        }

        $resultText .= substr($templateText, $cursor);

        $renderedEntities = [];
        foreach (TelegramEntities::normalize($entities) as $entity) {
            $renderedEntities[] = self::relocateEntity($entity, $edits);
        }

        return ['text' => $resultText, 'entities' => $renderedEntities];
    }

    /**
     * @param array<string, mixed> $entity
     * @param array<int, array{utf16_start: int, old_length: int, new_length: int}> $edits
     * @return array<string, mixed>
     */
    private static function relocateEntity(array $entity, array $edits): array
    {
        $offset = (int) $entity['offset'];
        $length = (int) $entity['length'];
        $delta = 0;

        foreach ($edits as $edit) {
            if ($edit['utf16_start'] === $offset && $edit['old_length'] === $length) {
                // Entity exactly wraps this placeholder token: stretch it
                // to the substituted value instead of just shifting.
                $entity['offset'] = $offset + $delta;
                $entity['length'] = $edit['new_length'];

                return $entity;
            }

            if ($edit['utf16_start'] < $offset) {
                $delta += $edit['new_length'] - $edit['old_length'];
            }
        }

        $entity['offset'] = $offset + $delta;

        return $entity;
    }
}
