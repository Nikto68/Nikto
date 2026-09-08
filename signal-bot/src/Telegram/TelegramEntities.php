<?php

declare(strict_types=1);

namespace App\Telegram;

/**
 * Telegram MessageEntity offsets/lengths are UTF-16 code units, not bytes
 * and not Unicode codepoints. Every piece of code in this project that
 * stores, edits, or renders entities (premium custom_emoji included) goes
 * through here for length math, so that math is only ever written once.
 *
 * We never collapse text+entities down to Markdown: Markdown cannot express
 * custom_emoji, spoiler, or arbitrary nested entities losslessly, so text
 * and entities are always persisted and sent together (spec #18).
 */
final class TelegramEntities
{
    public const PRESERVABLE_TYPES = [
        'bold', 'italic', 'underline', 'strikethrough', 'spoiler', 'code',
        'pre', 'text_link', 'text_mention', 'mention', 'hashtag', 'cashtag',
        'bot_command', 'url', 'email', 'phone_number', 'custom_emoji',
        'blockquote', 'expandable_blockquote',
    ];

    public static function utf16Length(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        $utf16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');

        return (int) (mb_strlen($utf16, 'UTF-16LE'));
    }

    /**
     * Extracts {text, entities} out of a raw Telegram Message array exactly
     * as received, ready to persist as-is.
     *
     * @param array<string, mixed> $message
     * @return array{text: string, entities: array<int, array<string, mixed>>}
     */
    public static function fromMessage(array $message): array
    {
        $text = (string) ($message['text'] ?? $message['caption'] ?? '');
        $entities = $message['entities'] ?? $message['caption_entities'] ?? [];

        return [
            'text' => $text,
            'entities' => self::normalize($entities),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $entities
     * @return array<int, array<string, mixed>>
     */
    public static function normalize(array $entities): array
    {
        $normalized = [];

        foreach ($entities as $entity) {
            if (!isset($entity['type'], $entity['offset'], $entity['length'])) {
                continue;
            }

            $normalized[] = array_filter([
                'type' => (string) $entity['type'],
                'offset' => (int) $entity['offset'],
                'length' => (int) $entity['length'],
                'url' => $entity['url'] ?? null,
                'user' => $entity['user'] ?? null,
                'language' => $entity['language'] ?? null,
                'custom_emoji_id' => $entity['custom_emoji_id'] ?? null,
            ], static fn (mixed $v): bool => $v !== null);
        }

        // offset ascending keeps rendering/shift logic simple and matches
        // what Telegram itself sends.
        usort($normalized, static fn (array $a, array $b): int => $a['offset'] <=> $b['offset']);

        return $normalized;
    }

    /**
     * Shifts every entity whose span starts at or after $fromOffset by
     * $delta UTF-16 units. Used when a template placeholder is replaced by
     * a value of a different length, so entities anchored after it in the
     * template stay attached to the right characters.
     *
     * @param array<int, array<string, mixed>> $entities
     * @return array<int, array<string, mixed>>
     */
    public static function shift(array $entities, int $fromOffset, int $delta): array
    {
        if ($delta === 0) {
            return $entities;
        }

        foreach ($entities as &$entity) {
            if ($entity['offset'] >= $fromOffset) {
                $entity['offset'] += $delta;
            }
        }
        unset($entity);

        return $entities;
    }

    /**
     * @param array<int, array<string, mixed>> $entities
     */
    public static function encode(array $entities): string
    {
        return json_encode($entities, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function decode(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? self::normalize($decoded) : [];
    }
}
