<?php

declare(strict_types=1);

namespace App\Telegram\Exceptions;

use App\Core\Exceptions\AppException;

class TelegramApiException extends AppException
{
    public function __construct(
        string $message,
        private readonly int $errorCode = 0,
        private readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $errorCode);
    }

    public function errorCode(): int
    {
        return $this->errorCode;
    }

    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function isRateLimited(): bool
    {
        return $this->errorCode === 429;
    }
}
