<?php
declare(strict_types=1);

namespace EmojiBot;

use RuntimeException;

final class TelegramError extends RuntimeException
{
    public function __construct(string $message, int $code = 0, public readonly int $retryAfter = 0)
    {
        parent::__construct($message, $code);
    }

    /** True if the user blocked the bot / deleted their account / never started it. */
    public function isUnreachableUser(): bool
    {
        return $this->code === 403
            || str_contains($this->getMessage(), 'chat not found')
            || str_contains($this->getMessage(), 'user is deactivated')
            || str_contains($this->getMessage(), 'PEER_ID_INVALID');
    }
}
