<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'worker.php can only be run from the CLI: php worker.php';
    exit(1);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/signal.php';
require_once __DIR__ . '/bot.php';

/**
 * ============================================================================
 * worker.php — the one and only long-running process. No cron, ever.
 *
 *   php worker.php
 *
 * START -> Load Config -> Load Symbols -> Fetch Initial Market Data ->
 * Connect WebSocket where available -> loop { Update Market Data -> Scan
 * Symbols -> Indicators -> S/R -> OB -> FVG -> Strategy -> Confluence ->
 * Validate -> Queue -> Send Telegram -> Wait } -> Repeat, forever.
 *
 * One exchange failing (rate limit, outage, bad response) never stops the
 * others — every exchange call goes through ExchangeManager::withIsolation
 * (signal.php), which owns per-exchange circuit breaking. This file adds
 * the outer scheduling/backoff loop, the Telegram send queue, and graceful
 * shutdown.
 * ============================================================================
 */

// ============================================================================
// SECTION 1 — BACKOFF HELPER
// ============================================================================

final class Backoff
{
    private int $failures = 0;

    public function __construct(private int $baseSeconds = 2, private int $maxSeconds = 120)
    {
    }

    public function reset(): void
    {
        $this->failures = 0;
    }

    public function fail(): int
    {
        $this->failures++;
        return $this->delaySeconds();
    }

    public function delaySeconds(): int
    {
        return min($this->maxSeconds, $this->baseSeconds * (2 ** min($this->failures, 10)));
    }
}

// ============================================================================
// SECTION 2 — SIGNAL QUEUE (internal, in-process)
// Decouples signal generation from Telegram delivery, so a slow/failing
// Telegram API call never blocks the scanner from moving to the next symbol.
// ============================================================================

final class SignalQueue
{
    /** @var array<int,Signal> */
    private array $items = [];

    public function push(Signal $signal): void
    {
        $this->items[] = $signal;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function drain(): array
    {
        $items = $this->items;
        $this->items = [];
        return $items;
    }

    public function count(): int
    {
        return count($this->items);
    }
}

// ============================================================================
// SECTION 3 — TELEGRAM DISPATCHER
// Fans one Signal out to every eligible channel (per-channel template,
// minimum score, strategy allow-list, enabled flag), with per-send retry.
// ============================================================================

final class TelegramDispatcher
{
    private SignalFormatter $formatter;
    private TextFormatManager $texts;
    private QuoteManager $quotes;

    public function __construct(
        private TelegramClient $telegram,
        private ChannelManager $channels,
        private SignalRepository $signalRepo,
    ) {
        $this->formatter = new SignalFormatter();
        $this->texts = new TextFormatManager();
        $this->quotes = new QuoteManager();
    }

    public function dispatch(Signal $signal): void
    {
        $eligible = $this->eligibleChannels($signal);
        if (empty($eligible)) {
            $this->signalRepo->updateStatus($signal->id, 'expired');
            return;
        }

        $dryRun = $this->currentRunMode() === 'DRY_RUN';
        $anySent = false;

        foreach ($eligible as $channel) {
            $templateKey = $channel['template_key'] ?: 'signal_template';
            $template = $this->texts->get($templateKey);
            if ($template['text'] === '') {
                $template = $this->texts->get('signal_template');
            }
            $rendered = $this->formatter->format($signal, $template['text'], $template['entities']);

            if ($dryRun) {
                Logger::info('dispatcher', 'DRY_RUN — signal not sent', ['symbol' => $signal->symbol, 'channel' => $channel['chat_id']]);
                $this->logEvent($signal->id, (int) $channel['id'], 'queued', null, null, 0);
                continue;
            }

            $opts = [];
            if ((int) $channel['quote_enabled'] === 1) {
                $ref = $this->quotes->latest('channel_pin', (string) $channel['id']);
                if ($ref !== null) {
                    $opts['reply_parameters'] = $this->quotes->buildReplyParameters($ref);
                }
            }

            $sent = $this->sendWithRetry((int) $channel['chat_id'], $rendered['text'], $rendered['entities'], $opts, $signal->id, (int) $channel['id']);
            if ($sent !== null) {
                $anySent = true;
                if ((int) $channel['pin_signal'] === 1 && $sent > 0) {
                    $this->telegram->pinChatMessage((int) $channel['chat_id'], $sent);
                }
            }
        }

        $this->signalRepo->updateStatus($signal->id, $dryRun ? 'queued' : ($anySent ? 'sent' : 'failed'));
    }

    private function sendWithRetry(int $chatId, string $text, array $entities, array $opts, int $signalId, int $channelId, int $maxAttempts = 3): ?int
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $result = $this->telegram->sendMessage($chatId, $text, $entities, $opts);
            if ($result['ok'] ?? false) {
                $messageId = (int) ($result['result']['message_id'] ?? 0);
                $this->logEvent($signalId, $channelId, 'sent', $messageId, null, $attempt);
                return $messageId;
            }
            $error = (string) ($result['description'] ?? 'unknown error');
            $this->logEvent($signalId, $channelId, 'retry', null, $error, $attempt);
            Logger::warning('dispatcher', 'send failed, retrying', ['chat_id' => $chatId, 'attempt' => $attempt, 'error' => $error]);
            usleep(500_000 * $attempt);
        }
        $this->logEvent($signalId, $channelId, 'failed', null, 'max attempts reached', $maxAttempts);
        return null;
    }

    private function logEvent(int $signalId, int $channelId, string $type, ?int $messageId, ?string $error, int $attempt): void
    {
        Database::pdo()->prepare(
            'INSERT INTO signal_events (signal_id, channel_id, event_type, message_id, error, attempt, created_at)
             VALUES (:sid, :cid, :type, :mid, :err, :att, :now)'
        )->execute([
            ':sid' => $signalId, ':cid' => $channelId, ':type' => $type, ':mid' => $messageId,
            ':err' => $error, ':att' => $attempt, ':now' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function eligibleChannels(Signal $signal): array
    {
        $channels = $this->channels->listActiveWithSettings();
        return array_values(array_filter($channels, static function ($c) use ($signal) {
            if ((float) $c['min_signal_score'] > $signal->score) {
                return false;
            }
            $allowedStrategies = json_decode((string) ($c['allowed_strategies'] ?? 'null'), true);
            if (is_array($allowedStrategies) && !empty($allowedStrategies) && !in_array($signal->strategy, $allowedStrategies, true)) {
                return false;
            }
            $allowedExchanges = json_decode((string) ($c['allowed_exchanges'] ?? 'null'), true);
            if (is_array($allowedExchanges) && !empty($allowedExchanges) && !in_array($signal->exchange, $allowedExchanges, true)) {
                return false;
            }
            return true;
        }));
    }

    private function currentRunMode(): string
    {
        $stmt = Database::pdo()->prepare('SELECT setting_value FROM bot_settings WHERE setting_key = "run_mode"');
        $stmt->execute();
        $v = $stmt->fetchColumn();
        return $v !== false && $v !== '' ? (string) $v : Config::runMode()->value;
    }
}

// ============================================================================
// SECTION 4 — WORKER (main loop)
// ============================================================================

final class Worker
{
    private ExchangeManager $exchangeManager;
    private MarketDataStore $marketData;
    private MarketScanner $scanner;
    private SignalGenerator $signalGenerator;
    private SignalQueue $queue;
    private TelegramDispatcher $dispatcher;
    private SymbolRepository $symbolRepo;

    private bool $running = true;
    private int $lastScanAt = 0;

    /** @var array<string,int> timeframe => unix timestamp of next due scan */
    private array $nextTimeframeRun = [];

    /** @var array<string,Backoff> per-exchange backoff state for the outer loop */
    private array $exchangeBackoff = [];

    public function __construct()
    {
        Database::migrate();

        $this->exchangeManager = new ExchangeManager();
        $this->marketData = new MarketDataStore();
        $this->scanner = new MarketScanner();
        $this->signalGenerator = new SignalGenerator(new IndicatorEngine());
        $this->queue = new SignalQueue();
        $telegram = new TelegramClient();
        $channels = new ChannelManager($telegram);
        $this->dispatcher = new TelegramDispatcher($telegram, $channels, new SignalRepository());
        $this->symbolRepo = new SymbolRepository();

        foreach ($this->exchangeManager->adapters() as $name => $adapter) {
            $this->exchangeBackoff[$name] = new Backoff();
        }
        foreach (Config::timeframes() as $tf) {
            $this->nextTimeframeRun[$tf] = 0;
        }

        $this->installSignalHandlers();
    }

    private function installSignalHandlers(): void
    {
        if (!function_exists('pcntl_async_signals')) {
            return;
        }
        pcntl_async_signals(true);
        $handler = function (int $signo): void {
            Logger::info('worker', 'shutdown signal received', ['signal' => $signo]);
            $this->running = false;
            ShutdownFlag::request();
        };
        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);
    }

    public function run(): void
    {
        Logger::info('worker', 'worker starting', ['run_mode' => Config::runMode()->value]);

        // Load Symbols (whatever the last scan produced) + Fetch Initial
        // Market Data before entering the steady-state loop.
        $this->runScanner();
        $this->primeMarketData();

        while ($this->running) {
            $tickStart = microtime(true);

            try {
                $this->maybeRunScanner();
                $this->updateMarketData();
                $this->scanAndGenerateSignals();
                $this->processQueue();
                $this->heartbeat();
            } catch (Throwable $e) {
                // The loop itself must never die — log and continue.
                Logger::critical('worker', 'unhandled error in tick', ['error' => $e->getMessage()]);
            }

            $elapsed = microtime(true) - $tickStart;
            $sleepFor = max(1, Config::workerTickSeconds() - (int) $elapsed);
            $this->sleepInterruptible($sleepFor);
        }

        Logger::info('worker', 'worker stopped gracefully');
    }

    private function sleepInterruptible(int $seconds): void
    {
        for ($i = 0; $i < $seconds && $this->running; $i++) {
            sleep(1);
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
    }

    private function heartbeat(): void
    {
        Database::pdo()->prepare(
            'INSERT INTO bot_settings (setting_key, setting_value, updated_at) VALUES ("worker_heartbeat", :v, :now)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)'
        )->execute([':v' => (string) time(), ':now' => date('Y-m-d H:i:s')]);
    }

    private function maybeRunScanner(): void
    {
        if (time() - $this->lastScanAt < Config::scannerIntervalSeconds()) {
            return;
        }
        $this->runScanner();
    }

    private function runScanner(): void
    {
        Logger::info('worker', 'running market scanner');
        $selected = $this->scanner->scan($this->exchangeManager);
        $this->lastScanAt = time();
        Logger::info('worker', 'scanner completed', ['symbols_selected' => $selected]);
    }

    private function primeMarketData(): void
    {
        $symbols = $this->symbolRepo->listActive();
        foreach ($symbols as $row) {
            if (!$this->running) {
                break;
            }
            $this->syncSymbolCandles($row['exchange'], $row['symbol'], Config::timeframes(), 200);
        }
    }

    /**
     * REST-based incremental update every tick (cheap: only timeframes
     * whose candle would plausibly have closed since the last check), plus
     * best-effort WebSocket polling for exchanges that support it. A given
     * exchange failing here only affects that exchange (per-exchange
     * Backoff), Binance/MEXC/Wallex are otherwise fully independent.
     */
    private function updateMarketData(): void
    {
        $now = time();
        $dueTimeframes = [];
        foreach (Config::timeframes() as $tf) {
            if ($now >= ($this->nextTimeframeRun[$tf] ?? 0)) {
                $dueTimeframes[] = $tf;
                $this->nextTimeframeRun[$tf] = $now + $this->timeframeSeconds($tf);
            }
        }
        if (empty($dueTimeframes)) {
            return;
        }

        $symbols = $this->symbolRepo->listActive();
        foreach ($symbols as $row) {
            if (!$this->running) {
                break;
            }
            $exchange = $row['exchange'];
            if (!$this->exchangeManager->isHealthy($exchange)) {
                continue; // circuit open — skip this tick for this exchange only
            }
            $this->syncSymbolCandles($exchange, $row['symbol'], $dueTimeframes, 200);
            $this->syncSymbolTicker($exchange, $row['symbol']);
        }
    }

    private function syncSymbolCandles(string $exchange, string $symbol, array $timeframes, int $limit): void
    {
        foreach ($timeframes as $tf) {
            $candles = $this->exchangeManager->withIsolation($exchange, fn(ExchangeAdapter $a) => $a->fetchCandles($symbol, $tf, $limit));
            if (is_array($candles) && !empty($candles)) {
                $this->marketData->candleManager()->upsertMany($exchange, $symbol, $tf, $candles);
                $this->exchangeBackoff[$exchange]->reset();
            }
        }
    }

    private function syncSymbolTicker(string $exchange, string $symbol): void
    {
        $tickers = $this->exchangeManager->withIsolation($exchange, fn(ExchangeAdapter $a) => $a->fetchTicker24h());
        if (is_array($tickers) && isset($tickers[$symbol])) {
            $t = $tickers[$symbol];
            $this->marketData->upsertTicker($exchange, $symbol, $t['lastPrice'], $t['bid'], $t['ask'], $t['volume']);
        }
    }

    private function timeframeSeconds(string $timeframe): int
    {
        return match ($timeframe) {
            '1m' => 60, '5m' => 300, '15m' => 900, '1h' => 3600, '4h' => 14400, '1D' => 86400,
            default => 300,
        };
    }

    private function scanAndGenerateSignals(): void
    {
        $symbols = $this->symbolRepo->listActive();
        $timeframes = Config::timeframes();
        $primaryTimeframe = $timeframes[array_key_last($timeframes)] ?? '1h';

        foreach ($symbols as $row) {
            if (!$this->running) {
                break;
            }
            $exchange = $row['exchange'];
            if (!$this->exchangeManager->isHealthy($exchange)) {
                continue;
            }

            try {
                $snapshot = $this->marketData->buildSnapshot($exchange, $row['symbol'], $timeframes);
                if ($snapshot->price <= 0) {
                    continue;
                }
                $signal = $this->signalGenerator->generate($snapshot, $primaryTimeframe);
                if ($signal !== null) {
                    Logger::info('worker', 'signal generated', ['symbol' => $signal->symbol, 'direction' => $signal->direction->value, 'score' => $signal->score]);
                    $this->queue->push($signal);
                }
            } catch (Throwable $e) {
                Logger::error('worker', 'signal pipeline failed for symbol', ['symbol' => $row['symbol'], 'exchange' => $exchange, 'error' => $e->getMessage()]);
            }
        }
    }

    private function processQueue(): void
    {
        if ($this->queue->isEmpty()) {
            return;
        }
        foreach ($this->queue->drain() as $signal) {
            try {
                $this->dispatcher->dispatch($signal);
            } catch (Throwable $e) {
                Logger::error('worker', 'dispatch failed', ['symbol' => $signal->symbol, 'error' => $e->getMessage()]);
            }
        }
    }
}

// ============================================================================
// SECTION 5 — ENTRYPOINT
// ============================================================================

(new Worker())->run();
