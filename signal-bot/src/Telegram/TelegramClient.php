<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Core\Config;
use App\Telegram\Exceptions\TelegramApiException;
use CurlHandle;
use Psr\Log\LoggerInterface;

/**
 * Thin, honest wrapper over the Telegram Bot API: one method, call(), that
 * every higher-level class (TelegramSender, Bot, AdminHandler, ...) goes
 * through. Nothing above this class builds raw HTTP requests, so retry
 * policy, timeouts and rate-limit handling live in exactly one place.
 */
class TelegramClient
{
    private const MAX_ATTEMPTS = 3;
    private const TIMEOUT_SECONDS = 15;

    private readonly string $baseUrl;

    public function __construct(
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
        $token = (string) $this->config->get('telegram.bot_token');
        $apiBase = (string) $this->config->get('telegram.api_base');
        $this->baseUrl = "{$apiBase}/bot{$token}";
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function call(string $method, array $params = []): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < self::MAX_ATTEMPTS) {
            $attempt++;

            try {
                return $this->request($method, $params);
            } catch (TelegramApiException $e) {
                $lastException = $e;

                if ($e->isRateLimited()) {
                    $wait = max(1, (int) $e->retryAfter());
                    $this->logger->warning('Telegram rate limited, backing off', [
                        'method' => $method,
                        'retry_after' => $wait,
                    ]);
                    sleep($wait);

                    continue;
                }

                if (!$this->isRetryable($e) || $attempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }

                usleep((int) (300_000 * (2 ** ($attempt - 1))));
            }
        }

        throw $lastException ?? new TelegramApiException("Telegram call to [$method] failed with no response.");
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function request(string $method, array $params): array
    {
        $url = "{$this->baseUrl}/{$method}";
        $payload = $this->encodeParams($params);

        $handle = curl_init($url);
        if (!$handle instanceof CurlHandle) {
            throw new TelegramApiException('Could not initialise cURL handle.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);

        $body = curl_exec($handle);
        $curlErrno = curl_errno($handle);
        $curlError = curl_error($handle);
        $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($body === false || $curlErrno !== 0) {
            $this->logger->error('Telegram request transport error', ['method' => $method, 'error' => $curlError]);

            throw new TelegramApiException("Telegram transport error calling [$method]: {$curlError}");
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw new TelegramApiException("Telegram returned a non-JSON response for [$method] (HTTP {$httpCode}).");
        }

        if (($decoded['ok'] ?? false) !== true) {
            $description = (string) ($decoded['description'] ?? 'Unknown Telegram API error');
            $errorCode = (int) ($decoded['error_code'] ?? $httpCode);
            $retryAfter = $decoded['parameters']['retry_after'] ?? null;

            $this->logger->error('Telegram API error', [
                'method' => $method,
                'error_code' => $errorCode,
                'description' => $description,
            ]);

            throw new TelegramApiException($description, $errorCode, $retryAfter === null ? null : (int) $retryAfter);
        }

        return $decoded['result'] ?? [];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function encodeParams(array $params): string
    {
        $clean = array_filter($params, static fn (mixed $v): bool => $v !== null);

        return json_encode($clean, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function isRetryable(TelegramApiException $e): bool
    {
        return $e->errorCode() >= 500 || $e->errorCode() === 0;
    }
}
