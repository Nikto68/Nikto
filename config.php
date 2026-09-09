<?php

declare(strict_types=1);

/**
 * ============================================================================
 * config.php — Configuration, Environment, Database bootstrap, Logger and
 * shared low-level Telegram entity/text utilities.
 *
 * No trading/business logic lives here. This file only:
 *   - loads environment variables (env.php, or classic .env text file)
 *   - exposes typed configuration via Config::
 *   - owns the single PDO connection + schema migration via Database::
 *   - provides a minimal structured Logger:: (never logs secrets)
 *   - provides TelegramEntityUtils:: (UTF-16 safe entity math), shared by
 *     bot.php (TextFormatManager) and signal.php (SignalFormatter) so both
 *     can render {placeholder} templates without corrupting Telegram
 *     message entities (premium custom emoji, bold, spoilers, etc).
 * ============================================================================
 */

// ============================================================================
// SECTION 0 — EXCEPTIONS
// ============================================================================

class AppException extends RuntimeException
{
}

final class ConfigException extends AppException
{
}

final class DatabaseException extends AppException
{
}

// ============================================================================
// SECTION 1 — ENV LOADER
// ============================================================================

final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];
    private static bool $loaded = false;

    /**
     * $dir is the project root. Two supported sources, both optional and
     * mergeable (env.php values win on conflict):
     *
     *   env.php  — `<?php return ['KEY' => 'value', ...];`. Preferred: it
     *              has no leading-dot filename (some cPanel File Manager
     *              builds cannot create/rename to a dotfile correctly —
     *              they turn ".env" into "env." instead) and, being a
     *              .php file, the webserver always executes it rather
     *              than ever serving it as a downloadable text file, so
     *              it needs no extra .htaccess protection to stay safe.
     *   .env     — classic KEY=value text file, for hosts where a
     *              leading-dot filename works fine.
     */
    public static function load(string $dir): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        $phpFile = $dir . '/env.php';
        if (is_file($phpFile)) {
            $data = require $phpFile;
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    self::$vars[(string) $key] = (string) $value;
                    if (getenv((string) $key) === false) {
                        putenv($key . '=' . $value);
                    }
                }
            }
        }

        $envFile = $dir . '/.env';
        if (is_file($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }
                    if (!str_contains($line, '=')) {
                        continue;
                    }
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    if (strlen($value) >= 2) {
                        $first = $value[0];
                        $last = $value[strlen($value) - 1];
                        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                            $value = substr($value, 1, -1);
                        }
                    }
                    self::$vars[$key] = $value;
                    if (getenv($key) === false) {
                        putenv($key . '=' . $value);
                    }
                }
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $fromEnv = getenv($key);
        if ($fromEnv !== false && $fromEnv !== '') {
            return $fromEnv;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }
        return self::$vars[$key] ?? $default;
    }

    public static function getInt(string $key, int $default): int
    {
        $v = self::get($key);
        return $v === null || $v === '' ? $default : (int) $v;
    }

    public static function getFloat(string $key, float $default): float
    {
        $v = self::get($key);
        return $v === null || $v === '' ? $default : (float) $v;
    }

    public static function getBool(string $key, bool $default): bool
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    /** @return string[] */
    public static function getList(string $key, string $default = ''): array
    {
        $v = self::get($key, $default) ?? '';
        if (trim($v) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $v)), static fn($x) => $x !== ''));
    }
}

Env::load(__DIR__);

// ============================================================================
// SECTION 2 — APP CONFIG
// ============================================================================

enum RunMode: string
{
    case LIVE = 'LIVE';
    case DRY_RUN = 'DRY_RUN';
}

final class Config
{
    // -- Telegram ------------------------------------------------------
    public static function telegramBotToken(): string
    {
        $t = Env::get('TELEGRAM_BOT_TOKEN', '');
        if ($t === '' || $t === null) {
            throw new ConfigException('TELEGRAM_BOT_TOKEN is not set in environment/.env');
        }
        return $t;
    }

    public static function telegramWebhookSecret(): string
    {
        return Env::get('TELEGRAM_WEBHOOK_SECRET', '') ?? '';
    }

    public static function telegramApiBase(): string
    {
        return 'https://api.telegram.org/bot' . self::telegramBotToken();
    }

    /** @return int[] Telegram numeric user IDs allowed into the admin panel */
    public static function adminIds(): array
    {
        return array_map('intval', Env::getList('ADMIN_IDS', ''));
    }

    // -- Database --------------------------------------------------------
    /**
     * SQLite storage. Everything lives in one file inside a self-created,
     * self-protected "storage/" directory next to the 4 project files —
     * no separate database server/credentials needed (this is what makes
     * the project deployable on plain cPanel shared hosting with no SSH).
     */
    public static function storageDir(): string
    {
        return rtrim(Env::get('STORAGE_DIR', __DIR__ . '/storage') ?? (__DIR__ . '/storage'), '/');
    }

    public static function dbPath(): string
    {
        return self::storageDir() . '/' . (Env::get('DB_FILE', 'database.sqlite') ?? 'database.sqlite');
    }

    // -- Exchanges ---------------------------------------------------------
    /**
     * Admin-panel-set credentials (stored in bot_settings, editable via
     * 📡 صرافی‌ها → 🔑 API without touching env.php) take precedence over
     * whatever env.php/.env has, falling back to it when nothing was set
     * through the panel. Never throws — DB may not be migrated yet the
     * very first time Config is touched.
     */
    private static function dbOverride(string $key): ?string
    {
        try {
            $stmt = Database::pdo()->prepare('SELECT setting_value FROM bot_settings WHERE setting_key = :k');
            $stmt->execute([':k' => $key]);
            $v = $stmt->fetchColumn();
            return $v !== false && $v !== '' ? (string) $v : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function binanceApiKey(): string
    {
        return self::dbOverride('BINANCE_API_KEY') ?? (Env::get('BINANCE_API_KEY', '') ?? '');
    }

    public static function binanceApiSecret(): string
    {
        return self::dbOverride('BINANCE_API_SECRET') ?? (Env::get('BINANCE_API_SECRET', '') ?? '');
    }

    public static function binanceRestBase(): string
    {
        return Env::get('BINANCE_REST_BASE', 'https://api.binance.com') ?? 'https://api.binance.com';
    }

    public static function binanceWsBase(): string
    {
        return Env::get('BINANCE_WS_BASE', 'wss://stream.binance.com:9443') ?? 'wss://stream.binance.com:9443';
    }

    public static function mexcApiKey(): string
    {
        return self::dbOverride('MEXC_API_KEY') ?? (Env::get('MEXC_API_KEY', '') ?? '');
    }

    public static function mexcApiSecret(): string
    {
        return self::dbOverride('MEXC_API_SECRET') ?? (Env::get('MEXC_API_SECRET', '') ?? '');
    }

    public static function mexcRestBase(): string
    {
        return Env::get('MEXC_REST_BASE', 'https://api.mexc.com') ?? 'https://api.mexc.com';
    }

    public static function mexcWsBase(): string
    {
        return Env::get('MEXC_WS_BASE', 'wss://wbs.mexc.com') ?? 'wss://wbs.mexc.com';
    }

    public static function wallexApiKey(): string
    {
        return self::dbOverride('WALLEX_API_KEY') ?? (Env::get('WALLEX_API_KEY', '') ?? '');
    }

    public static function wallexRestBase(): string
    {
        return Env::get('WALLEX_REST_BASE', 'https://api.wallex.ir') ?? 'https://api.wallex.ir';
    }

    /** @return string[] enabled exchange names, in priority order */
    public static function enabledExchanges(): array
    {
        return Env::getList('ENABLED_EXCHANGES', 'binance,mexc,wallex');
    }

    // -- Scanner -------------------------------------------------------
    public static function scannerTopN(): int
    {
        return Env::getInt('SCANNER_TOP_N', 200);
    }

    public static function scannerIntervalSeconds(): int
    {
        return Env::getInt('SCANNER_INTERVAL_SECONDS', 3600);
    }

    public static function minVolumeUsdt(): float
    {
        return Env::getFloat('MIN_VOLUME_USDT', 1_000_000.0);
    }

    public static function maxSpreadPercent(): float
    {
        return Env::getFloat('MAX_SPREAD_PERCENT', 0.5);
    }

    /** @return string[] allowed quote assets, e.g. ["USDT","USDC"] */
    public static function allowedQuoteAssets(): array
    {
        return Env::getList('ALLOWED_QUOTE_ASSETS', 'USDT');
    }

    public static function includeStablecoinPairs(): bool
    {
        return Env::getBool('INCLUDE_STABLECOIN_PAIRS', false);
    }

    // -- Timeframes ------------------------------------------------------
    /** @return string[] */
    public static function timeframes(): array
    {
        return Env::getList('TIMEFRAMES', '1m,5m,15m,1h,4h,1D');
    }

    // -- Signal thresholds -------------------------------------------------
    public static function minSignalScore(): float
    {
        return Env::getFloat('MIN_SIGNAL_SCORE', 75.0);
    }

    public static function cooldownSeconds(): int
    {
        return Env::getInt('SIGNAL_COOLDOWN_SECONDS', 1800);
    }

    public static function minRiskReward(): float
    {
        return Env::getFloat('MIN_RISK_REWARD', 1.5);
    }

    // -- Confluence weights (configurable, sums need not be 100 — normalized at use) --
    /** @return array<string,float> */
    public static function confluenceWeights(): array
    {
        return [
            'trend'      => Env::getFloat('WEIGHT_TREND', 20.0),
            'support'    => Env::getFloat('WEIGHT_SUPPORT', 15.0),
            'resistance' => Env::getFloat('WEIGHT_RESISTANCE', 15.0),
            'order_block'=> Env::getFloat('WEIGHT_ORDER_BLOCK', 20.0),
            'fvg'        => Env::getFloat('WEIGHT_FVG', 15.0),
            'volume'     => Env::getFloat('WEIGHT_VOLUME', 10.0),
            'indicators' => Env::getFloat('WEIGHT_INDICATORS', 5.0),
        ];
    }

    // -- Worker / runtime ----------------------------------------------------
    public static function runMode(): RunMode
    {
        $v = strtoupper(Env::get('RUN_MODE', 'DRY_RUN') ?? 'DRY_RUN');
        return $v === 'LIVE' ? RunMode::LIVE : RunMode::DRY_RUN;
    }

    public static function workerTickSeconds(): int
    {
        return Env::getInt('WORKER_TICK_SECONDS', 15);
    }

    /**
     * 'daemon' — worker.php loops forever until SIGTERM/SIGINT (needs a
     * host that allows a persistent background process: SSH + nohup/screen,
     * a VPS, "Setup Node.js App"-style always-on process, etc).
     * 'cron'   — worker.php runs one bounded pass (Config::workerMaxRuntimeSeconds())
     * and exits cleanly; a cPanel Cron Job re-invokes it every minute. This
     * is the default because it's the only mode plain shared hosting with
     * just File Manager + Cron Jobs can actually run.
     */
    public static function workerMode(): string
    {
        $v = strtolower(Env::get('WORKER_MODE', 'cron') ?? 'cron');
        return $v === 'daemon' ? 'daemon' : 'cron';
    }

    public static function workerMaxRuntimeSeconds(): int
    {
        return Env::getInt('WORKER_MAX_RUNTIME_SECONDS', 50);
    }

    public static function httpTimeoutSeconds(): int
    {
        return Env::getInt('HTTP_TIMEOUT_SECONDS', 10);
    }

    public static function maxRetries(): int
    {
        return Env::getInt('MAX_RETRIES', 5);
    }

    public static function appTimezone(): string
    {
        return Env::get('APP_TIMEZONE', 'UTC') ?? 'UTC';
    }
}

date_default_timezone_set(Config::appTimezone());

// ============================================================================
// SECTION 3 — DATABASE (PDO singleton + schema migration)
// ============================================================================

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        self::ensureStorageDir();
        $dsn = 'sqlite:' . Config::dbPath();

        try {
            $pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 10, // seconds to wait on a locked db before throwing
            ]);
            // WAL lets worker.php (writer) and bot.php's webhook (occasional
            // reader/writer) touch the database concurrently without
            // "database is locked" errors on every overlap.
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA synchronous = NORMAL');
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 8000');
        } catch (PDOException $e) {
            throw new DatabaseException('Database connection failed: ' . $e->getCode());
        }

        return self::$pdo = $pdo;
    }

    private static function ensureStorageDir(): void
    {
        $dir = Config::storageDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new DatabaseException("storage directory is missing or not writable: $dir");
        }

        // Self-heal: keep the sqlite file (and WAL/journal siblings) and
        // logs out of the public web root even if someone points STORAGE_DIR
        // inside it. Harmless no-op on nginx/php-fpm setups that don't read
        // .htaccess; this is aimed squarely at the Apache/LiteSpeed shared
        // hosting (cPanel) this project is meant to run on.
        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }
    }

    /**
     * Idempotent schema creation. Safe to call on every worker/bot boot.
     */
    public static function migrate(): void
    {
        $pdo = self::pdo();

        // SQLite DDL: INTEGER PRIMARY KEY is the rowid alias (= AUTO_INCREMENT),
        // ENUM becomes TEXT + CHECK, DATETIME columns are TEXT ('Y-m-d H:i:s',
        // which sorts correctly as a string), JSON columns are TEXT holding
        // json_encode() output (same as how PHP already reads/writes them).
        $tables = [
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                telegram_user_id INTEGER NOT NULL,
                username TEXT NULL,
                first_name TEXT NULL,
                last_name TEXT NULL,
                language_code TEXT NULL,
                is_bot INTEGER NOT NULL DEFAULT 0,
                first_seen_at TEXT NOT NULL,
                last_seen_at TEXT NOT NULL,
                UNIQUE (telegram_user_id)
            )",

            "CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                telegram_user_id INTEGER NOT NULL,
                role TEXT NOT NULL DEFAULT 'admin' CHECK (role IN ('owner','admin')),
                added_by INTEGER NULL,
                created_at TEXT NOT NULL,
                UNIQUE (telegram_user_id)
            )",

            "CREATE TABLE IF NOT EXISTS channels (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                chat_id INTEGER NOT NULL,
                title TEXT NULL,
                username TEXT NULL,
                type TEXT NOT NULL DEFAULT 'channel',
                is_active INTEGER NOT NULL DEFAULT 0,
                bot_status TEXT NOT NULL DEFAULT 'unknown' CHECK (bot_status IN ('unknown','member','administrator','left','kicked')),
                can_post INTEGER NOT NULL DEFAULT 0,
                added_by INTEGER NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (chat_id)
            )",

            "CREATE TABLE IF NOT EXISTS channel_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                channel_id INTEGER NOT NULL,
                template_key TEXT NOT NULL DEFAULT 'signal_template',
                min_signal_score REAL NOT NULL DEFAULT 75.00,
                allowed_strategies TEXT NULL,
                allowed_exchanges TEXT NULL,
                enabled INTEGER NOT NULL DEFAULT 1,
                pin_signal INTEGER NOT NULL DEFAULT 0,
                quote_enabled INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (channel_id),
                FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE
            )",

            "CREATE TABLE IF NOT EXISTS bot_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT NOT NULL,
                setting_value TEXT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (setting_key)
            )",

            "CREATE TABLE IF NOT EXISTS text_formats (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                text_key TEXT NOT NULL,
                text_value TEXT NOT NULL,
                entities TEXT NULL,
                updated_at TEXT NOT NULL,
                updated_by INTEGER NULL,
                UNIQUE (text_key)
            )",

            "CREATE TABLE IF NOT EXISTS exchanges (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                display_name TEXT NOT NULL,
                is_enabled INTEGER NOT NULL DEFAULT 1,
                priority INTEGER NOT NULL DEFAULT 0,
                UNIQUE (name)
            )",

            "CREATE TABLE IF NOT EXISTS exchange_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exchange_id INTEGER NOT NULL,
                rate_limit_per_min INTEGER NOT NULL DEFAULT 1200,
                ws_enabled INTEGER NOT NULL DEFAULT 0,
                extra TEXT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (exchange_id),
                FOREIGN KEY (exchange_id) REFERENCES exchanges(id) ON DELETE CASCADE
            )",

            "CREATE TABLE IF NOT EXISTS symbols (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exchange_id INTEGER NOT NULL,
                symbol TEXT NOT NULL,
                base_asset TEXT NOT NULL,
                quote_asset TEXT NOT NULL,
                volume_24h REAL NOT NULL DEFAULT 0,
                liquidity_score REAL NOT NULL DEFAULT 0,
                spread_pct REAL NOT NULL DEFAULT 0,
                volatility REAL NOT NULL DEFAULT 0,
                rank_position INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                last_scanned_at TEXT NULL,
                UNIQUE (exchange_id, symbol),
                FOREIGN KEY (exchange_id) REFERENCES exchanges(id) ON DELETE CASCADE
            )",

            "CREATE TABLE IF NOT EXISTS candles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exchange_id INTEGER NOT NULL,
                symbol TEXT NOT NULL,
                timeframe TEXT NOT NULL,
                open_time INTEGER NOT NULL,
                open_price REAL NOT NULL,
                high_price REAL NOT NULL,
                low_price REAL NOT NULL,
                close_price REAL NOT NULL,
                volume REAL NOT NULL,
                UNIQUE (exchange_id, symbol, timeframe, open_time)
            )",

            "CREATE TABLE IF NOT EXISTS market_data (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exchange_id INTEGER NOT NULL,
                symbol TEXT NOT NULL,
                price REAL NOT NULL,
                bid REAL NOT NULL DEFAULT 0,
                ask REAL NOT NULL DEFAULT 0,
                spread_pct REAL NOT NULL DEFAULT 0,
                volume_24h REAL NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL,
                UNIQUE (exchange_id, symbol)
            )",

            "CREATE TABLE IF NOT EXISTS zones (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exchange_id INTEGER NOT NULL,
                symbol TEXT NOT NULL,
                zone_type TEXT NOT NULL CHECK (zone_type IN ('support','resistance')),
                high_price REAL NOT NULL,
                low_price REAL NOT NULL,
                timeframe TEXT NOT NULL,
                strength REAL NOT NULL DEFAULT 0,
                volume REAL NOT NULL DEFAULT 0,
                touches INTEGER NOT NULL DEFAULT 1,
                source TEXT NOT NULL DEFAULT 'swing',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )",

            "CREATE TABLE IF NOT EXISTS order_blocks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exchange_id INTEGER NOT NULL,
                symbol TEXT NOT NULL,
                ob_type TEXT NOT NULL CHECK (ob_type IN ('bullish','bearish')),
                high_price REAL NOT NULL,
                low_price REAL NOT NULL,
                timeframe TEXT NOT NULL,
                strength REAL NOT NULL DEFAULT 0,
                volume REAL NOT NULL DEFAULT 0,
                mitigated INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )",

            "CREATE TABLE IF NOT EXISTS fvgs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exchange_id INTEGER NOT NULL,
                symbol TEXT NOT NULL,
                fvg_type TEXT NOT NULL CHECK (fvg_type IN ('bullish','bearish')),
                high_price REAL NOT NULL,
                low_price REAL NOT NULL,
                midpoint REAL NOT NULL,
                timeframe TEXT NOT NULL,
                size REAL NOT NULL DEFAULT 0,
                filled INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )",

            "CREATE TABLE IF NOT EXISTS strategies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                display_name TEXT NOT NULL,
                is_enabled INTEGER NOT NULL DEFAULT 1,
                description TEXT NULL,
                UNIQUE (name)
            )",

            "CREATE TABLE IF NOT EXISTS strategy_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                strategy_id INTEGER NOT NULL,
                weights TEXT NULL,
                params TEXT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (strategy_id),
                FOREIGN KEY (strategy_id) REFERENCES strategies(id) ON DELETE CASCADE
            )",

            "CREATE TABLE IF NOT EXISTS signals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT NOT NULL,
                exchange_id INTEGER NOT NULL,
                symbol TEXT NOT NULL,
                direction TEXT NOT NULL CHECK (direction IN ('LONG','SHORT')),
                timeframe TEXT NOT NULL,
                entry_price REAL NOT NULL,
                stop_loss REAL NOT NULL,
                tp1 REAL NULL,
                tp2 REAL NULL,
                tp3 REAL NULL,
                risk_reward REAL NOT NULL DEFAULT 0,
                score REAL NOT NULL DEFAULT 0,
                confidence TEXT NOT NULL DEFAULT 'medium',
                strategy TEXT NOT NULL,
                reasons TEXT NULL,
                fingerprint TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','queued','sent','failed','expired','invalidated','test')),
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (uuid)
            )",

            "CREATE TABLE IF NOT EXISTS signal_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                signal_id INTEGER NOT NULL,
                channel_id INTEGER NULL,
                event_type TEXT NOT NULL CHECK (event_type IN ('queued','sent','failed','retry','test')),
                message_id INTEGER NULL,
                error TEXT NULL,
                attempt INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                FOREIGN KEY (signal_id) REFERENCES signals(id) ON DELETE CASCADE
            )",

            "CREATE TABLE IF NOT EXISTS scanner_runs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exchange_id INTEGER NULL,
                started_at TEXT NOT NULL,
                finished_at TEXT NULL,
                symbols_scanned INTEGER NOT NULL DEFAULT 0,
                symbols_selected INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'running',
                error TEXT NULL
            )",

            "CREATE TABLE IF NOT EXISTS logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                level TEXT NOT NULL DEFAULT 'info' CHECK (level IN ('debug','info','warning','error','critical')),
                channel TEXT NOT NULL DEFAULT 'app',
                message TEXT NOT NULL,
                context TEXT NULL,
                created_at TEXT NOT NULL
            )",

            "CREATE TABLE IF NOT EXISTS admin_states (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                telegram_user_id INTEGER NOT NULL,
                state TEXT NOT NULL,
                payload TEXT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (telegram_user_id)
            )",

            "CREATE TABLE IF NOT EXISTS message_refs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ref_type TEXT NOT NULL,
                ref_id TEXT NOT NULL,
                chat_id INTEGER NOT NULL,
                message_id INTEGER NOT NULL,
                quote_text TEXT NULL,
                quote_entities TEXT NULL,
                created_at TEXT NOT NULL
            )",
        ];

        $indexes = [
            'CREATE INDEX IF NOT EXISTS idx_symbols_active_rank ON symbols (is_active, rank_position)',
            'CREATE INDEX IF NOT EXISTS idx_candles_lookup ON candles (exchange_id, symbol, timeframe, open_time DESC)',
            'CREATE INDEX IF NOT EXISTS idx_zones_lookup ON zones (exchange_id, symbol, timeframe, zone_type)',
            'CREATE INDEX IF NOT EXISTS idx_ob_lookup ON order_blocks (exchange_id, symbol, timeframe, mitigated)',
            'CREATE INDEX IF NOT EXISTS idx_fvg_lookup ON fvgs (exchange_id, symbol, timeframe, filled)',
            'CREATE INDEX IF NOT EXISTS idx_signals_fingerprint ON signals (fingerprint, created_at)',
            'CREATE INDEX IF NOT EXISTS idx_signals_symbol ON signals (exchange_id, symbol, created_at)',
            'CREATE INDEX IF NOT EXISTS idx_signal_events_signal ON signal_events (signal_id)',
            'CREATE INDEX IF NOT EXISTS idx_logs_created ON logs (created_at)',
            'CREATE INDEX IF NOT EXISTS idx_logs_level ON logs (level)',
            'CREATE INDEX IF NOT EXISTS idx_message_refs_ref ON message_refs (ref_type, ref_id)',
        ];

        foreach ($tables as $sql) {
            $pdo->exec($sql);
        }
        foreach ($indexes as $sql) {
            $pdo->exec($sql);
        }

        self::seedDefaults($pdo);
    }

    private static function seedDefaults(PDO $pdo): void
    {
        $now = date('Y-m-d H:i:s');

        // Exchanges
        $exchangeNames = ['binance' => 'Binance', 'mexc' => 'MEXC', 'wallex' => 'Wallex'];
        $stmt = $pdo->prepare(
            'INSERT INTO exchanges (name, display_name, is_enabled, priority)
             VALUES (:name, :display_name, :enabled, :priority)
             ON CONFLICT(name) DO UPDATE SET display_name = excluded.display_name'
        );
        $priority = 0;
        $enabled = Config::enabledExchanges();
        foreach ($exchangeNames as $name => $display) {
            $stmt->execute([
                ':name' => $name,
                ':display_name' => $display,
                ':enabled' => in_array($name, $enabled, true) ? 1 : 0,
                ':priority' => $priority++,
            ]);
        }

        // Default text formats (plain, no entities) — admin can edit via panel.
        $defaults = [
            'welcome' => "به ربات سیگنال خوش آمدید.",
            'help' => "برای مشاهده راهنما با ادمین در تماس باشید.",
            'signal_template' => "🔔 سیگنال جدید\n\nنماد: {symbol}\nصرافی: {exchange}\nجهت: {direction}\nتایم‌فریم: {timeframe}\n\nورود: {entry}\nحد ضرر: {sl}\n\nهدف ۱: {tp1}\nهدف ۲: {tp2}\nهدف ۳: {tp3}\n\nامتیاز: {score}\nاستراتژی: {strategy}\n\nدلایل:\n{reasons}",
            'signal_long' => "🟢 سیگنال LONG برای {symbol}",
            'signal_short' => "🔴 سیگنال SHORT برای {symbol}",
            'error' => "خطایی رخ داد. لطفاً دوباره تلاش کنید.",
            'channel_added' => "✅ کانال با موفقیت اضافه شد.",
            'scanner_status' => "وضعیت اسکنر: {status}",
        ];
        $stmt = $pdo->prepare(
            'INSERT OR IGNORE INTO text_formats (text_key, text_value, entities, updated_at) VALUES (:k, :v, :e, :u)'
        );
        foreach ($defaults as $key => $value) {
            $stmt->execute([':k' => $key, ':v' => $value, ':e' => json_encode([]), ':u' => $now]);
        }

        // Seed admin from env (owner)
        foreach (Config::adminIds() as $adminId) {
            $ins = $pdo->prepare(
                "INSERT INTO admins (telegram_user_id, role, created_at) VALUES (:id, 'owner', :now)
                 ON CONFLICT(telegram_user_id) DO NOTHING"
            );
            $ins->execute([':id' => $adminId, ':now' => $now]);
        }
    }
}

// ============================================================================
// SECTION 3.5 — SHUTDOWN FLAG (shared)
// A process-wide "please stop" signal that long-running retry/backoff loops
// (HttpClient in signal.php) poll between attempts, so a SIGTERM/SIGINT
// caught by worker.php's own handler can interrupt an in-flight retry
// storm instead of waiting for it to exhaust naturally (which, under a
// full network outage with several exchanges, could otherwise take
// minutes before the process actually exits).
// ============================================================================

final class ShutdownFlag
{
    private static bool $requested = false;

    public static function request(): void
    {
        self::$requested = true;
    }

    public static function isRequested(): bool
    {
        return self::$requested;
    }
}

// ============================================================================
// SECTION 4 — LOGGER
// ============================================================================

final class Logger
{
    private const SECRET_PATTERNS = ['token', 'secret', 'password', 'api_key', 'apikey'];

    public static function debug(string $channel, string $message, array $context = []): void
    {
        self::write('debug', $channel, $message, $context);
    }

    public static function info(string $channel, string $message, array $context = []): void
    {
        self::write('info', $channel, $message, $context);
    }

    public static function warning(string $channel, string $message, array $context = []): void
    {
        self::write('warning', $channel, $message, $context);
    }

    public static function error(string $channel, string $message, array $context = []): void
    {
        self::write('error', $channel, $message, $context);
    }

    public static function critical(string $channel, string $message, array $context = []): void
    {
        self::write('critical', $channel, $message, $context);
    }

    private static function write(string $level, string $channel, string $message, array $context): void
    {
        $context = self::redact($context);
        $message = self::redactString($message);

        $line = sprintf('[%s] [%s] [%s] %s %s', date('Y-m-d H:i:s'), strtoupper($level), $channel, $message, empty($context) ? '' : json_encode($context, JSON_UNESCAPED_UNICODE));
        fwrite($level === 'error' || $level === 'critical' ? STDERR : STDOUT, $line . PHP_EOL);

        try {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO logs (level, channel, message, context, created_at) VALUES (:level, :channel, :message, :context, :now)'
            );
            $stmt->execute([
                ':level' => $level,
                ':channel' => substr($channel, 0, 32),
                ':message' => $message,
                ':context' => empty($context) ? null : json_encode($context, JSON_UNESCAPED_UNICODE),
                ':now' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            // Logging must never crash the caller; DB may not be migrated yet.
        }
    }

    private static function redact(array $context): array
    {
        $out = [];
        foreach ($context as $k => $v) {
            $keyLower = strtolower((string) $k);
            $isSecret = false;
            foreach (self::SECRET_PATTERNS as $pattern) {
                if (str_contains($keyLower, $pattern)) {
                    $isSecret = true;
                    break;
                }
            }
            if ($isSecret) {
                $out[$k] = '[REDACTED]';
            } elseif (is_array($v)) {
                $out[$k] = self::redact($v);
            } else {
                $out[$k] = is_string($v) ? self::redactString($v) : $v;
            }
        }
        return $out;
    }

    private static function redactString(string $s): string
    {
        $s = preg_replace('/\d{6,}:[A-Za-z0-9_-]{30,}/', '[REDACTED_TOKEN]', $s) ?? $s;
        return $s;
    }
}

// ============================================================================
// SECTION 5 — SHARED TELEGRAM ENTITY UTILITIES
// (UTF-16 code-unit safe placeholder rendering — used by bot.php's
//  TextFormatManager and signal.php's SignalFormatter so Premium custom
//  emoji / bold / spoiler / etc entities never shift or break.)
// ============================================================================

final class TelegramEntityUtils
{
    /**
     * Telegram entity offsets/lengths are counted in UTF-16 code units,
     * NOT bytes and NOT PHP string length. This computes that length for
     * an arbitrary UTF-8 string without requiring ext-intl.
     */
    public static function utf16Length(string $text): int
    {
        $len = 0;
        $bytes = strlen($text);
        $i = 0;
        while ($i < $bytes) {
            $ord = ord($text[$i]);
            if ($ord < 0x80) {
                $cp = $ord;
                $i += 1;
            } elseif (($ord & 0xE0) === 0xC0 && $i + 1 < $bytes) {
                $cp = (($ord & 0x1F) << 6) | (ord($text[$i + 1]) & 0x3F);
                $i += 2;
            } elseif (($ord & 0xF0) === 0xE0 && $i + 2 < $bytes) {
                $cp = (($ord & 0x0F) << 12) | ((ord($text[$i + 1]) & 0x3F) << 6) | (ord($text[$i + 2]) & 0x3F);
                $i += 3;
            } elseif (($ord & 0xF8) === 0xF0 && $i + 3 < $bytes) {
                $cp = (($ord & 0x07) << 18) | ((ord($text[$i + 1]) & 0x3F) << 12) | ((ord($text[$i + 2]) & 0x3F) << 6) | (ord($text[$i + 3]) & 0x3F);
                $i += 4;
            } else {
                $cp = 0xFFFD;
                $i += 1;
            }
            $len += ($cp > 0xFFFF) ? 2 : 1;
        }
        return $len;
    }

    /**
     * Renders {placeholder} tokens inside $template, replacing them with
     * $placeholders values while correctly shifting every entity's
     * offset/length (UTF-16 units) so formatting/custom-emoji entities
     * that sit before, after, or wrap around a placeholder stay intact.
     *
     * @param array<int,array<string,mixed>> $entities Telegram entity objects (offset/length in UTF-16 units)
     * @param array<string,string> $placeholders token => replacement text (plain text, no entities of its own)
     * @return array{text:string, entities:array<int,array<string,mixed>>}
     */
    public static function renderTemplate(string $template, array $entities, array $placeholders): array
    {
        // Locate every {token} occurrence (byte offsets are fine here since
        // "{" "}" and placeholder names are ASCII) and compute its UTF-16
        // start/end within the original template.
        $tokens = [];
        if (preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $template, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $idx => $full) {
                $name = $matches[1][$idx][0];
                if (!array_key_exists($name, $placeholders)) {
                    continue;
                }
                $byteStart = $full[1];
                $utf16Start = self::utf16Length(substr($template, 0, $byteStart));
                $utf16TokenLen = self::utf16Length($full[0]);
                $tokens[] = [
                    'byte_start' => $byteStart,
                    'byte_len' => strlen($full[0]),
                    'utf16_start' => $utf16Start,
                    'utf16_end' => $utf16Start + $utf16TokenLen,
                    'value' => (string) $placeholders[$name],
                ];
            }
        }

        // Adjust entities against tokens, left to right.
        $workingEntities = array_map(static fn($e) => $e, $entities);
        foreach ($tokens as $token) {
            $delta = self::utf16Length($token['value']) - ($token['utf16_end'] - $token['utf16_start']);
            foreach ($workingEntities as &$entity) {
                $eStart = (int) ($entity['offset'] ?? 0);
                $eLen = (int) ($entity['length'] ?? 0);
                $eEnd = $eStart + $eLen;

                if ($eStart >= $token['utf16_end']) {
                    // Entity entirely after the token: shift start only.
                    $entity['offset'] = $eStart + $delta;
                } elseif ($eEnd <= $token['utf16_start']) {
                    // Entity entirely before the token: untouched.
                } elseif ($eStart <= $token['utf16_start'] && $eEnd >= $token['utf16_end']) {
                    // Entity wraps around the token (e.g. bold spanning {symbol}): extend/shrink length.
                    $entity['length'] = $eLen + $delta;
                } else {
                    // Entity partially overlaps the token boundary — clamp to
                    // the token edge to avoid corrupting an unrelated span.
                    if ($eStart >= $token['utf16_start']) {
                        $entity['offset'] = $token['utf16_start'];
                    }
                    $entity['length'] = max(0, $eLen + $delta);
                }
            }
            unset($entity);
        }

        // Now perform the textual substitution (byte-safe, right to left so
        // earlier byte offsets stay valid while we mutate the string).
        $text = $template;
        foreach (array_reverse($tokens) as $token) {
            $text = substr_replace($text, $token['value'], $token['byte_start'], $token['byte_len']);
        }

        // Drop any zero-length entities left over from a fully-collapsed token.
        $workingEntities = array_values(array_filter($workingEntities, static fn($e) => ((int) ($e['length'] ?? 0)) > 0));

        return ['text' => $text, 'entities' => $workingEntities];
    }
}
