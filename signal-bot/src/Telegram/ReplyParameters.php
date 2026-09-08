<?php

declare(strict_types=1);

namespace App\Telegram;

/**
 * Maps directly onto the Bot API's `reply_parameters` object (Bot API
 * 7.0+), which is what makes native quote/reply possible without breaking
 * formatting (spec #19). quote_entities lets the quoted excerpt keep its
 * own formatting independent of the entities on the new message text.
 */
final class ReplyParameters
{
    /**
     * @param array<int, array<string, mixed>> $quoteEntities
     */
    public function __construct(
        public readonly int $messageId,
        public readonly ?string $quoteText = null,
        public readonly array $quoteEntities = [],
        public readonly ?int $quotePosition = null,
        public readonly bool $allowSendingWithoutReply = true,
    ) {
    }

    /**
     * Builds a ReplyParameters from a stored text_formats/signal_templates
     * row (reply_to_message_id/quote_text/quote_entities/quote_position),
     * or null if that row carries no reply/quote metadata.
     *
     * @param array{reply_to_message_id?: ?int, quote_text?: ?string, quote_entities?: array<int, array<string, mixed>>, quote_position?: ?int} $row
     */
    public static function fromStored(array $row): ?self
    {
        $messageId = $row['reply_to_message_id'] ?? null;
        if ($messageId === null) {
            return null;
        }

        return new self(
            messageId: (int) $messageId,
            quoteText: $row['quote_text'] ?? null,
            quoteEntities: $row['quote_entities'] ?? [],
            quotePosition: $row['quote_position'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'message_id' => $this->messageId,
            'quote' => $this->quoteText,
            'quote_entities' => $this->quoteEntities === [] ? null : $this->quoteEntities,
            'quote_position' => $this->quotePosition,
            'allow_sending_without_reply' => $this->allowSendingWithoutReply,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
