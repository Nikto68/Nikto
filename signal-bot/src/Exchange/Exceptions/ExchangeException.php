<?php

declare(strict_types=1);

namespace App\Exchange\Exceptions;

use App\Core\Exceptions\AppException;

/**
 * Raised for any exchange REST/WS failure. Always caught at the scanner
 * boundary and logged — one exchange failing must never stop the others
 * (spec #30): "Binance error -> log -> retry -> fallback -> continue".
 */
class ExchangeException extends AppException
{
    public function __construct(
        string $message,
        private readonly string $exchangeCode = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function exchangeCode(): string
    {
        return $this->exchangeCode;
    }
}
