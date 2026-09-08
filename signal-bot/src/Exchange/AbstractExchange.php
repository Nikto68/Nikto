<?php

declare(strict_types=1);

namespace App\Exchange;

use App\Exchange\Exceptions\ExchangeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\Http\Browser;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Throwable;

use function React\Promise\reject;
use function React\Promise\resolve;

/**
 * Everything Binance/MEXC/Wallex share: rate-limited, retried, logged HTTP
 * GET requests returning decoded JSON. Concrete adapters only know their
 * own endpoints and response shapes — see spec #4 (exchange logic isolated
 * from everything else) and #27 (rate limiting, one place for all of it).
 */
abstract class AbstractExchange implements ExchangeInterface
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        protected readonly Browser $http,
        protected readonly RateLimiter $rateLimiter,
        protected readonly LoggerInterface $logger,
        /** @var array<string, mixed> */
        protected readonly array $config,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @return PromiseInterface<mixed>
     */
    protected function getJson(string $url, array $query = []): PromiseInterface
    {
        $fullUrl = $query === [] ? $url : $url . '?' . http_build_query($query);

        return $this->requestWithRetry($fullUrl, 1);
    }

    /**
     * @return PromiseInterface<mixed>
     */
    private function requestWithRetry(string $url, int $attempt): PromiseInterface
    {
        $wait = $this->rateLimiter->waitTime();

        $issue = function () use ($url, $attempt): PromiseInterface {
            $this->rateLimiter->recordRequest();

            return $this->http->get($url)->then(
                fn (ResponseInterface $response): mixed => $this->decode($response, $url),
                function (Throwable $e) use ($url, $attempt): PromiseInterface {
                    if ($attempt < self::MAX_ATTEMPTS && $this->isTransient($e)) {
                        $this->logger->warning('Exchange request failed, retrying', [
                            'exchange' => $this->code(),
                            'url' => $this->redactUrl($url),
                            'attempt' => $attempt,
                        ]);

                        return $this->delay(0.3 * (2 ** ($attempt - 1)))
                            ->then(fn () => $this->requestWithRetry($url, $attempt + 1));
                    }

                    $this->logger->error('Exchange request failed', [
                        'exchange' => $this->code(),
                        'url' => $this->redactUrl($url),
                        'exception' => $e->getMessage(),
                    ]);

                    return reject(new ExchangeException(
                        "[{$this->code()}] request failed: {$e->getMessage()}",
                        $this->code(),
                        0,
                        $e,
                    ));
                },
            );
        };

        return $wait > 0.0 ? $this->delay($wait)->then($issue) : $issue();
    }

    /**
     * @return PromiseInterface<mixed>
     */
    private function decode(ResponseInterface $response, string $url): mixed
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if ($status >= 400) {
            $this->logger->error('Exchange API error', [
                'exchange' => $this->code(),
                'url' => $this->redactUrl($url),
                'status' => $status,
            ]);

            return reject(new ExchangeException("[{$this->code()}] HTTP {$status}", $this->code()));
        }

        if (!is_array($decoded)) {
            return reject(new ExchangeException("[{$this->code()}] non-JSON response", $this->code()));
        }

        return resolve($decoded);
    }

    /**
     * @return PromiseInterface<null>
     */
    private function delay(float $seconds): PromiseInterface
    {
        $deferred = new Deferred();
        Loop::addTimer($seconds, static function () use ($deferred): void {
            $deferred->resolve(null);
        });

        return $deferred->promise();
    }

    private function isTransient(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'timed out')
            || str_contains($message, 'Connection')
            || str_contains($message, '50');
    }

    /**
     * Strips query strings (API keys sometimes ride along as query params)
     * before a URL ever reaches a log line.
     */
    private function redactUrl(string $url): string
    {
        return explode('?', $url)[0];
    }
}
