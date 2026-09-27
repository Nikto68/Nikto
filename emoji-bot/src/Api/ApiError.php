<?php
declare(strict_types=1);

namespace EmojiBot\Api;

use RuntimeException;

final class ApiError extends RuntimeException
{
    public function __construct(string $message, int $status = 400, public readonly string $errorCode = 'error')
    {
        parent::__construct($message, $status);
    }
}
