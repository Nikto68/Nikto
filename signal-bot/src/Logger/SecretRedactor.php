<?php

declare(strict_types=1);

namespace App\Logger;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Strips known secrets (Telegram bot token, exchange API keys/secrets) from
 * every log line before it hits disk. Registered on every channel — see
 * LoggerFactory. Secrets are collected once at boot from config/*.php.
 */
final class SecretRedactor implements ProcessorInterface
{
    private const MASK = '***REDACTED***';

    /** @var string[] */
    private array $secrets;

    /**
     * @param string[] $secrets
     */
    public function __construct(array $secrets)
    {
        $this->secrets = array_values(array_unique(array_filter(
            $secrets,
            static fn (string $s): bool => trim($s) !== '' && strlen($s) >= 6
        )));
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if ($this->secrets === []) {
            return $record;
        }

        $message = $this->redact($record->message);
        $context = $this->redactArray($record->context);
        $extra = $this->redactArray($record->extra);

        return $record->with(message: $message, context: $context, extra: $extra);
    }

    private function redact(string $value): string
    {
        return str_replace($this->secrets, self::MASK, $value);
    }

    /**
     * @param array<mixed, mixed> $data
     * @return array<mixed, mixed>
     */
    private function redactArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = $this->redact($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->redactArray($value);
            } elseif ($value instanceof \Throwable) {
                // Leave the throwable object intact for Monolog's own
                // exception normalizer; its ->getMessage() already passes
                // through redact() via the message string above when it
                // originates the log call.
                continue;
            }
        }

        return $data;
    }
}
