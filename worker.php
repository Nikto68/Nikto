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
 * worker.php — runs the whole Load Symbols -> Market Data -> Scan ->
 * Indicators -> S/R -> OB -> FVG -> Strategy -> Confluence -> Validate ->
 * Queue -> Send Telegram cycle. Two execution modes, chosen by WORKER_MODE:
 *
 *   daemon  php worker.php   loops forever until SIGTERM/SIGINT — the
 *           original "no cron, ever" model. Needs a host that allows a
 *           persistent background process (SSH + nohup/screen, a VPS, ...).
 *
 *   cron    php worker.php   runs ONE bounded pass (up to
 *           WORKER_MAX_RUNTIME_SECONDS, default 50s) and exits cleanly.
 *           A cPanel Cron Job re-invokes it (every 1 minute — cPanel's
 *           minimum granularity) to approximate continuous operation. This
 *           is the default: it's the only mode plain shared hosting with
 *           just File Manager + Cron Jobs (no SSH, no persistent process)
 *           can actually run. A lock file prevents two invocations
 *           overlapping if one runs long; scanner/timeframe due-times are
 *           persisted to the database (not kept in memory) so scheduling
 *           survives across the process restarting every single minute.
 *           In this mode there is no persistent WebSocket connection (a
 *           connection that lives ~50s and dies is pointless) — market
 *           data is REST-polled, same as the fallback path in daemon mode.
 *
 * One exchange failing (rate limit, outage, bad response) never stops the
 * others — every exchange call goes through ExchangeManager::withIsolation
 * (signal.php), which owns per-exchange circuit breaking.
 * ============================================================================
 */

// ============================================================================
// SECTION 0 — LOCK FILE
// Stops two overlapping invocations (a slow cron run still finishing when
// the next minute's cron fires) from touching market data / the signal
// queue at the same time.
// ============================================================================

final class LockFile
{
    /** @var resource|null */
    private $handle = null;

    public function acquire(string $path): bool
    {
        $this->handle = @fopen($path, 'c');
        if ($this->handle === false) {
            $this->handle = null;
            return false;
        }
        return flock($this->handle, LOCK_EX | LOCK_NB);
    }

    public function release(): void
    {
        if ($this->handle !== null) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
    }
}

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

    /**
     * Announces a resolved trade (SL or TP1 hit) as a reply to the original
     * signal message, in every channel that actually received it (LIVE
     * sends only -- a DRY_RUN "queued" row was never really posted, so
     * there's nothing to reply to and nothing worth announcing there).
     */
    public function announceOutcome(array $signalRow, string $outcome, float $price): void
    {
        $stmt = Database::pdo()->prepare(
            "SELECT se.channel_id, se.message_id, c.chat_id
             FROM signal_events se
             JOIN channels c ON c.id = se.channel_id
             WHERE se.signal_id = :sid AND se.event_type = 'sent' AND se.message_id IS NOT NULL"
        );
        $stmt->execute([':sid' => $signalRow['id']]);
        $rows = $stmt->fetchAll();
        if (empty($rows)) {
            return;
        }

        $entry = (float) $signalRow['entry_price'];
        $direction = (string) $signalRow['direction'];
        $win = $outcome === 'tp1';
        $pct = $entry > 0
            ? (($direction === 'LONG' ? ($price - $entry) : ($entry - $price)) / $entry) * 100
            : 0.0;
        $priceStr = rtrim(rtrim(number_format($price, 8, '.', ''), '0'), '.');
        $text = sprintf(
            "%s %s %s\n\nنتیجه: %s\nقیمت: %s\n%s: %.2f%%",
            $win ? '✅' : '❌',
            $signalRow['symbol'],
            $direction,
            $win ? 'تارگت ۱ فعال شد' : 'حد ضرر فعال شد',
            $priceStr,
            $win ? 'سود' : 'ضرر',
            abs($pct)
        );

        foreach ($rows as $row) {
            $opts = ['reply_parameters' => $this->quotes->buildReplyParameters([
                'message_id' => (int) $row['message_id'],
                'chat_id' => (int) $row['chat_id'],
            ])];
            $this->sendWithRetry((int) $row['chat_id'], $text, [], $opts, (int) $signalRow['id'], (int) $row['channel_id']);
        }
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
    private SignalRepository $signalRepo;

    private bool $running = true;
    private int $lastScanAt = 0;

    /** @var array<string,int> timeframe => unix timestamp of next due scan */
    private array $nextTimeframeRun = [];

    /** @var array<string,Backoff> per-exchange backoff state for the outer loop */
    private array $exchangeBackoff = [];

    private ScannerRunRepository $scannerRunRepo;

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
        $this->signalRepo = new SignalRepository();
        $this->dispatcher = new TelegramDispatcher($telegram, $channels, $this->signalRepo);
        $this->symbolRepo = new SymbolRepository();
        $this->scannerRunRepo = new ScannerRunRepository();

        foreach ($this->exchangeManager->adapters() as $name => $adapter) {
            $this->exchangeBackoff[$name] = new Backoff();
        }

        // Cron mode restarts this whole process every ~1 minute, so "last
        // run" state can't live in memory (it would reset every restart and
        // make every symbol/timeframe look due on every single invocation,
        // hammering exchange APIs). Load it from the database instead;
        // daemon mode reads the same state once and then keeps it in memory
        // for the life of the process, same as before.
        $lastScan = $this->scannerRunRepo->lastRunAt();
        $this->lastScanAt = $lastScan !== null ? (int) strtotime($lastScan) : 0;
        $this->nextTimeframeRun = array_merge(array_fill_keys(Config::timeframes(), 0), $this->loadTimeframeSchedule());

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
        // SIGALRM drives the cron-mode hard deadline below (pcntl_alarm) —
        // same handler as SIGTERM/SIGINT, so it interrupts an in-flight
        // HTTP retry storm exactly the same way (ShutdownFlag is what
        // HttpClient actually polls; a deadline check in the tick loop
        // alone would NOT catch a retry storm stuck inside the very first,
        // pre-loop scanner/priming calls — confirmed by testing).
        pcntl_signal(SIGALRM, $handler);
    }

    public function run(): void
    {
        $mode = Config::workerMode();
        Logger::info('worker', 'worker starting', ['run_mode' => Config::runMode()->value, 'worker_mode' => $mode]);

        $lock = new LockFile();
        if (!$lock->acquire(Config::storageDir() . '/worker.lock')) {
            Logger::info('worker', 'another worker invocation is still running — skipping this one');
            return;
        }

        if ($mode === 'cron' && function_exists('pcntl_alarm')) {
            pcntl_alarm(Config::workerMaxRuntimeSeconds());
        }

        try {
            $this->runLoop($mode);
        } finally {
            if (function_exists('pcntl_alarm')) {
                pcntl_alarm(0); // cancel any pending alarm — we're exiting on our own
            }
            $lock->release();
        }
    }

    private function runLoop(string $mode): void
    {
        // daemon: no deadline, runs until SIGTERM/SIGINT flips $this->running.
        // cron: exit on our own before the host's cron/process limits would
        // kill us anyway, so the next minute's invocation starts clean. The
        // real enforcement is the SIGALRM set in run(); this time-based
        // check is just what keeps the *tick loop itself* from starting
        // another full cycle once the budget is spent.
        $deadline = $mode === 'daemon' ? PHP_INT_MAX : (time() + Config::workerMaxRuntimeSeconds());

        // Load Symbols (whatever the last scan produced) + Fetch Initial
        // Market Data before entering the steady-state loop. Gated the same
        // way as every later tick, so a cron invocation doesn't re-scan or
        // re-prime data that's already fresh from the previous minute.
        $this->maybeRunScanner();
        $this->primeMarketData();

        while ($this->running && time() < $deadline) {
            $tickStart = microtime(true);

            try {
                $this->maybeRunScanner();
                $this->updateMarketData();
                $this->resolveOpenPositions();
                $this->scanAndGenerateSignals();
                $this->processQueue();
                $this->heartbeat();
            } catch (Throwable $e) {
                // The loop itself must never die — log and continue.
                Logger::critical('worker', 'unhandled error in tick', ['error' => $e->getMessage()]);
            }

            $elapsed = microtime(true) - $tickStart;
            $remaining = $deadline - time();
            if ($remaining <= 0) {
                break;
            }
            $sleepFor = max(1, min(Config::workerTickSeconds(), $remaining) - (int) $elapsed);
            $this->sleepInterruptible($sleepFor);
        }

        Logger::info('worker', $mode === 'daemon' ? 'worker stopped gracefully' : 'worker run finished (cron invocation)');
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
            'INSERT INTO bot_settings (setting_key, setting_value, updated_at) VALUES (\'worker_heartbeat\', :v, :now)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at'
        )->execute([':v' => (string) time(), ':now' => date('Y-m-d H:i:s')]);
    }

    /** @return array<string,int> timeframe => unix timestamp of next due run, as of the last saved state */
    private function loadTimeframeSchedule(): array
    {
        $stmt = Database::pdo()->prepare('SELECT setting_value FROM bot_settings WHERE setting_key = \'tf_schedule\'');
        $stmt->execute();
        $v = $stmt->fetchColumn();
        $data = $v !== false && $v !== '' ? json_decode((string) $v, true) : null;
        return is_array($data) ? array_map('intval', $data) : [];
    }

    /** @param array<string,int> $schedule */
    private function saveTimeframeSchedule(array $schedule): void
    {
        Database::pdo()->prepare(
            'INSERT INTO bot_settings (setting_key, setting_value, updated_at) VALUES (\'tf_schedule\', :v, :now)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at'
        )->execute([':v' => json_encode($schedule), ':now' => date('Y-m-d H:i:s')]);
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

    /**
     * Fetches history only for symbol/timeframe pairs that have none yet.
     * In daemon mode this only ever matters once, right after start. In
     * cron mode this method runs at the top of every single invocation
     * (every ~1 minute) — the hasAny() check is what stops that from
     * re-fetching all candles for every symbol on every invocation once
     * the database is actually populated.
     */
    private function primeMarketData(): void
    {
        $symbols = $this->symbolRepo->listActive();
        $candleManager = $this->marketData->candleManager();
        foreach ($symbols as $row) {
            if (!$this->running) {
                break;
            }
            $missing = array_values(array_filter(
                Config::timeframes(),
                static fn(string $tf) => !$candleManager->hasAny($row['exchange'], $row['symbol'], $tf)
            ));
            if (!empty($missing)) {
                $this->syncSymbolCandles($row['exchange'], $row['symbol'], $missing, 200);
            }
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
        // Persist immediately (not just kept in memory) — cron mode restarts
        // this whole process on the next minute's invocation, so this is
        // the only place "when is each timeframe next due" survives.
        $this->saveTimeframeSchedule($this->nextTimeframeRun);

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

    /**
     * Checks every open (dispatched, not-yet-resolved) signal against the
     * latest known price and closes it out the moment SL or TP1 is
     * touched -- this is what frees a symbol up for a new signal (see
     * SignalGenerator::generate()'s hasOpenPosition() gate) instead of
     * firing a new one on top of a still-running trade. Uses whatever
     * price updateMarketData() just fetched this tick, no extra API calls.
     */
    private function resolveOpenPositions(): void
    {
        foreach ($this->signalRepo->openPositions() as $row) {
            $price = $this->marketData->latestPrice((string) $row['exchange'], (string) $row['symbol']);
            if ($price <= 0) {
                continue;
            }

            $direction = (string) $row['direction'];
            $stopLoss = (float) $row['stop_loss'];
            $tp1 = $row['tp1'] !== null ? (float) $row['tp1'] : null;

            $hitSl = $direction === 'LONG' ? $price <= $stopLoss : $price >= $stopLoss;
            $hitTp1 = $tp1 !== null && ($direction === 'LONG' ? $price >= $tp1 : $price <= $tp1);

            // If both would trigger on the same tick (a big move between
            // checks, or a very tight stop), SL wins -- resolving a signal
            // as a win it may never have actually reached intrabar would
            // be the worse mistake of the two.
            $outcome = $hitSl ? 'sl' : ($hitTp1 ? 'tp1' : null);
            if ($outcome === null) {
                continue;
            }

            $this->signalRepo->resolve((int) $row['id'], $outcome, $price);
            Logger::info('worker', 'position resolved', ['symbol' => $row['symbol'], 'outcome' => $outcome, 'price' => $price]);
            try {
                $this->dispatcher->announceOutcome($row, $outcome, $price);
            } catch (Throwable $e) {
                Logger::error('worker', 'outcome announcement failed', ['symbol' => $row['symbol'], 'error' => $e->getMessage()]);
            }
        }
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
