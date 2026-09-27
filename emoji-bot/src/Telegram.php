<?php
declare(strict_types=1);

namespace EmojiBot;

use CURLFile;

/**
 * Minimal Bot API client. Uses JSON bodies, or multipart/form-data when a CURLFile is present.
 */
final class Telegram
{
    public function __construct(
        private readonly string $token,
        private readonly string $apiBase = 'https://api.telegram.org',
    ) {
    }

    /**
     * @param array $params method parameters; nested arrays are JSON-encoded, CURLFile values are uploaded
     * @param int $timeout seconds
     * @param int $maxRetryWait retry once after a 429 if Telegram asks to wait no longer than this
     * @throws TelegramError
     */
    public function call(string $method, array $params = [], int $timeout = 30, int $maxRetryWait = 5): mixed
    {
        $attempt = 0;
        while (true) {
            try {
                return $this->request($method, $params, $timeout);
            } catch (TelegramError $e) {
                $attempt++;
                if ($e->retryAfter > 0 && $e->retryAfter <= $maxRetryWait && $attempt < 3) {
                    sleep($e->retryAfter);
                    continue;
                }
                if ($e->getCode() === 0 && $attempt < 2) {
                    usleep(500_000);
                    continue;
                }
                throw $e;
            }
        }
    }

    /** Same as call() but swallows errors; returns null on failure. Useful for best-effort messages. */
    public function safe(string $method, array $params = [], int $timeout = 15): mixed
    {
        try {
            return $this->call($method, $params, $timeout, 0);
        } catch (TelegramError) {
            return null;
        }
    }

    private function request(string $method, array $params, int $timeout): mixed
    {
        $url = $this->apiBase . '/bot' . $this->token . '/' . $method;
        $hasFile = false;
        foreach ($params as $v) {
            if ($v instanceof CURLFile) {
                $hasFile = true;
                break;
            }
        }

        $ch = curl_init($url);
        $opts = [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
        ];
        if ($hasFile) {
            $fields = [];
            foreach ($params as $k => $v) {
                if ($v === null) {
                    continue;
                }
                $fields[$k] = match (true) {
                    $v instanceof CURLFile => $v,
                    is_array($v) => json_encode($v, JSON_UNESCAPED_UNICODE),
                    is_bool($v) => $v ? 'true' : 'false',
                    default => (string) $v,
                };
            }
            $opts[CURLOPT_POSTFIELDS] = $fields;
        } else {
            $params = array_filter($params, fn ($v) => $v !== null);
            $opts[CURLOPT_POSTFIELDS] = json_encode($params ?: new \stdClass(), JSON_UNESCAPED_UNICODE);
            $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $errno) {
            throw new TelegramError("network error: $error", 0);
        }
        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            throw new TelegramError('invalid response from Bot API', 0);
        }
        if (empty($data['ok'])) {
            throw new TelegramError(
                (string) ($data['description'] ?? 'unknown error'),
                (int) ($data['error_code'] ?? 0),
                (int) ($data['parameters']['retry_after'] ?? 0),
            );
        }
        return $data['result'] ?? null;
    }
}
