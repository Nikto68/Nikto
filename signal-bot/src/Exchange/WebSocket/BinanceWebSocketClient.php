<?php

declare(strict_types=1);

namespace App\Exchange\WebSocket;

use Psr\Log\LoggerInterface;
use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket as RatchetConnection;
use React\EventLoop\Loop;
use Throwable;

/**
 * Binance's combined-stream ticker feed: one connection, N `<symbol>@ticker`
 * streams. Reconnects with exponential backoff (spec #2.6/#2.7) and never
 * throws out of start() — a dead socket just means $onTicker stops firing
 * until reconnectNow() succeeds again.
 */
final class BinanceWebSocketClient implements ExchangeWebSocketInterface
{
    private const MIN_BACKOFF = 1.0;
    private const MAX_BACKOFF = 60.0;

    private ?RatchetConnection $connection = null;
    private float $backoff = self::MIN_BACKOFF;
    private bool $stopping = false;

    /** @var string[] */
    private array $symbols = [];

    /** @var callable(array<string, mixed>): void */
    private $onTicker;

    public function __construct(
        private readonly string $wsBase,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string[] $symbols
     */
    public function start(array $symbols, callable $onTicker): void
    {
        $this->symbols = $symbols;
        $this->onTicker = $onTicker;
        $this->stopping = false;
        $this->connect();
    }

    public function stop(): void
    {
        $this->stopping = true;
        $this->connection?->close();
        $this->connection = null;
    }

    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    private function connect(): void
    {
        if ($this->symbols === [] || $this->stopping) {
            return;
        }

        $streams = implode('/', array_map(
            static fn (string $s): string => strtolower($s) . '@ticker',
            $this->symbols,
        ));
        $url = "{$this->wsBase}/stream?streams={$streams}";

        $connector = new Connector();

        $connector($url)->then(
            function (RatchetConnection $conn): void {
                $this->connection = $conn;
                $this->backoff = self::MIN_BACKOFF;
                $this->logger->info('Binance WebSocket connected', ['symbols' => count($this->symbols)]);

                $conn->on('message', function ($message): void {
                    $this->handleMessage((string) $message);
                });

                $conn->on('close', function ($code = null, $reason = null): void {
                    $this->connection = null;
                    $this->logger->warning('Binance WebSocket closed', ['code' => $code, 'reason' => $reason]);
                    $this->scheduleReconnect();
                });
            },
            function (Throwable $e): void {
                $this->logger->error('Binance WebSocket connection failed', ['exception' => $e->getMessage()]);
                $this->scheduleReconnect();
            },
        );
    }

    private function scheduleReconnect(): void
    {
        if ($this->stopping) {
            return;
        }

        $wait = $this->backoff;
        $this->backoff = min(self::MAX_BACKOFF, $this->backoff * 2);

        Loop::addTimer($wait, function (): void {
            $this->connect();
        });
    }

    private function handleMessage(string $raw): void
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['data'])) {
            return;
        }

        $data = $decoded['data'];
        if (($data['e'] ?? null) !== '24hrTicker') {
            return;
        }

        $bid = (float) ($data['b'] ?? 0);
        $ask = (float) ($data['a'] ?? 0);
        $last = (float) ($data['c'] ?? 0);
        $mid = $bid > 0 && $ask > 0 ? ($bid + $ask) / 2 : $last;

        ($this->onTicker)([
            'symbol' => $data['s'] ?? '',
            'price' => $last,
            'volume_24h' => (float) ($data['v'] ?? 0),
            'quote_volume_24h' => (float) ($data['q'] ?? 0),
            'price_change_percent_24h' => (float) ($data['P'] ?? 0),
            'high_24h' => (float) ($data['h'] ?? 0),
            'low_24h' => (float) ($data['l'] ?? 0),
            'spread_percent' => $mid > 0 && $bid > 0 && $ask > 0 ? (($ask - $bid) / $mid) * 100 : 0.0,
            'trades_count_24h' => isset($data['n']) ? (int) $data['n'] : null,
        ]);
    }
}
