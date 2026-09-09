<?php

declare(strict_types=1);

/**
 * ============================================================================
 * config.php — Configuration, Environment, Database bootstrap, Logger and
 * shared low-level Telegram entity/text utilities.
 *
 * No trading/business logic lives here. This file only:
 *   - loads environment variables (.env)
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

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (is_file($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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

Env::load(__DIR__ . '/.env');

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
    public static function dbHost(): string
    {
        return Env::get('DB_HOST', '127.0.0.1') ?? '127.0.0.1';
    }

    public static function dbPort(): int
    {
        return Env::getInt('DB_PORT', 3306);
    }

    public static function dbName(): string
    {
        return Env::get('DB_NAME', 'nikto_signals') ?? 'nikto_signals';
    }

    public static function dbUser(): string
    {
        return Env::get('DB_USER', 'root') ?? 'root';
    }

    public static function dbPass(): string
    {
        return Env::get('DB_PASS', '') ?? '';
    }

    // -- Exchanges ---------------------------------------------------------
    public static function binanceApiKey(): string
    {
        return Env::get('BINANCE_API_KEY', '') ?? '';
    }

    public static function binanceApiSecret(): string
    {
        return Env::get('BINANCE_API_SECRET', '') ?? '';
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
        return Env::get('MEXC_API_KEY', '') ?? '';
    }

    public static function mexcApiSecret(): string
    {
        return Env::get('MEXC_API_SECRET', '') ?? '';
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
        return Env::get('WALLEX_API_KEY', '') ?? '';
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

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            Config::dbHost(),
            Config::dbPort(),
            Config::dbName()
        );

        try {
            self::$pdo = new PDO($dsn, Config::dbUser(), Config::dbPass(), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'",
            ]);
        } catch (PDOException $e) {
            // Never leak DSN credentials into logs/exception chains.
            throw new DatabaseException('Database connection failed: ' . $e->getCode());
        }

        return self::$pdo;
    }

    /**
     * Idempotent schema creation. Safe to call on every worker/bot boot.
     */
    public static function migrate(): void
    {
        $pdo = self::pdo();
        $engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $statements = [
            "CREATE TABLE IF NOT EXISTS users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                telegram_user_id BIGINT NOT NULL,
                username VARCHAR(64) NULL,
                first_name VARCHAR(128) NULL,
                last_name VARCHAR(128) NULL,
                language_code VARCHAR(16) NULL,
                is_bot TINYINT(1) NOT NULL DEFAULT 0,
                first_seen_at DATETIME NOT NULL,
                last_seen_at DATETIME NOT NULL,
                UNIQUE KEY uq_users_tgid (telegram_user_id)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS admins (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                telegram_user_id BIGINT NOT NULL,
                role ENUM('owner','admin') NOT NULL DEFAULT 'admin',
                added_by BIGINT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_admins_tgid (telegram_user_id)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS channels (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chat_id BIGINT NOT NULL,
                title VARCHAR(255) NULL,
                username VARCHAR(64) NULL,
                type VARCHAR(32) NOT NULL DEFAULT 'channel',
                is_active TINYINT(1) NOT NULL DEFAULT 0,
                bot_status ENUM('unknown','member','administrator','left','kicked') NOT NULL DEFAULT 'unknown',
                can_post TINYINT(1) NOT NULL DEFAULT 0,
                added_by BIGINT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_channels_chatid (chat_id)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS channel_settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                channel_id BIGINT UNSIGNED NOT NULL,
                template_key VARCHAR(64) NOT NULL DEFAULT 'signal_template',
                min_signal_score DECIMAL(5,2) NOT NULL DEFAULT 75.00,
                allowed_strategies JSON NULL,
                allowed_exchanges JSON NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                pin_signal TINYINT(1) NOT NULL DEFAULT 0,
                quote_enabled TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_channel_settings_channel (channel_id),
                CONSTRAINT fk_channel_settings_channel FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE
            ) $engine",

            "CREATE TABLE IF NOT EXISTS bot_settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(128) NOT NULL,
                setting_value MEDIUMTEXT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_bot_settings_key (setting_key)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS text_formats (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                text_key VARCHAR(128) NOT NULL,
                text_value MEDIUMTEXT NOT NULL,
                entities JSON NULL,
                updated_at DATETIME NOT NULL,
                updated_by BIGINT NULL,
                UNIQUE KEY uq_text_formats_key (text_key)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS exchanges (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(32) NOT NULL,
                display_name VARCHAR(64) NOT NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                priority INT NOT NULL DEFAULT 0,
                UNIQUE KEY uq_exchanges_name (name)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS exchange_settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                exchange_id BIGINT UNSIGNED NOT NULL,
                rate_limit_per_min INT NOT NULL DEFAULT 1200,
                ws_enabled TINYINT(1) NOT NULL DEFAULT 0,
                extra JSON NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_exchange_settings_exchange (exchange_id),
                CONSTRAINT fk_exchange_settings_exchange FOREIGN KEY (exchange_id) REFERENCES exchanges(id) ON DELETE CASCADE
            ) $engine",

            "CREATE TABLE IF NOT EXISTS symbols (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                exchange_id BIGINT UNSIGNED NOT NULL,
                symbol VARCHAR(32) NOT NULL,
                base_asset VARCHAR(16) NOT NULL,
                quote_asset VARCHAR(16) NOT NULL,
                volume_24h DECIMAL(24,8) NOT NULL DEFAULT 0,
                liquidity_score DECIMAL(10,4) NOT NULL DEFAULT 0,
                spread_pct DECIMAL(10,6) NOT NULL DEFAULT 0,
                volatility DECIMAL(10,6) NOT NULL DEFAULT 0,
                rank_position INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                last_scanned_at DATETIME NULL,
                UNIQUE KEY uq_symbols_exchange_symbol (exchange_id, symbol),
                KEY idx_symbols_active_rank (is_active, rank_position),
                CONSTRAINT fk_symbols_exchange FOREIGN KEY (exchange_id) REFERENCES exchanges(id) ON DELETE CASCADE
            ) $engine",

            "CREATE TABLE IF NOT EXISTS candles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                exchange_id BIGINT UNSIGNED NOT NULL,
                symbol VARCHAR(32) NOT NULL,
                timeframe VARCHAR(8) NOT NULL,
                open_time BIGINT NOT NULL,
                open_price DECIMAL(24,10) NOT NULL,
                high_price DECIMAL(24,10) NOT NULL,
                low_price DECIMAL(24,10) NOT NULL,
                close_price DECIMAL(24,10) NOT NULL,
                volume DECIMAL(24,8) NOT NULL,
                UNIQUE KEY uq_candles_unique (exchange_id, symbol, timeframe, open_time),
                KEY idx_candles_lookup (exchange_id, symbol, timeframe, open_time DESC)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS market_data (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                exchange_id BIGINT UNSIGNED NOT NULL,
                symbol VARCHAR(32) NOT NULL,
                price DECIMAL(24,10) NOT NULL,
                bid DECIMAL(24,10) NOT NULL DEFAULT 0,
                ask DECIMAL(24,10) NOT NULL DEFAULT 0,
                spread_pct DECIMAL(10,6) NOT NULL DEFAULT 0,
                volume_24h DECIMAL(24,8) NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_market_data (exchange_id, symbol)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS zones (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                exchange_id BIGINT UNSIGNED NOT NULL,
                symbol VARCHAR(32) NOT NULL,
                zone_type ENUM('support','resistance') NOT NULL,
                high_price DECIMAL(24,10) NOT NULL,
                low_price DECIMAL(24,10) NOT NULL,
                timeframe VARCHAR(8) NOT NULL,
                strength DECIMAL(6,2) NOT NULL DEFAULT 0,
                volume DECIMAL(24,8) NOT NULL DEFAULT 0,
                touches INT NOT NULL DEFAULT 1,
                source VARCHAR(32) NOT NULL DEFAULT 'swing',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_zones_lookup (exchange_id, symbol, timeframe, zone_type)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS order_blocks (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                exchange_id BIGINT UNSIGNED NOT NULL,
                symbol VARCHAR(32) NOT NULL,
                ob_type ENUM('bullish','bearish') NOT NULL,
                high_price DECIMAL(24,10) NOT NULL,
                low_price DECIMAL(24,10) NOT NULL,
                timeframe VARCHAR(8) NOT NULL,
                strength DECIMAL(6,2) NOT NULL DEFAULT 0,
                volume DECIMAL(24,8) NOT NULL DEFAULT 0,
                mitigated TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_ob_lookup (exchange_id, symbol, timeframe, mitigated)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS fvgs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                exchange_id BIGINT UNSIGNED NOT NULL,
                symbol VARCHAR(32) NOT NULL,
                fvg_type ENUM('bullish','bearish') NOT NULL,
                high_price DECIMAL(24,10) NOT NULL,
                low_price DECIMAL(24,10) NOT NULL,
                midpoint DECIMAL(24,10) NOT NULL,
                timeframe VARCHAR(8) NOT NULL,
                size DECIMAL(24,10) NOT NULL DEFAULT 0,
                filled TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_fvg_lookup (exchange_id, symbol, timeframe, filled)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS strategies (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(64) NOT NULL,
                display_name VARCHAR(128) NOT NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                description VARCHAR(255) NULL,
                UNIQUE KEY uq_strategies_name (name)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS strategy_settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                strategy_id BIGINT UNSIGNED NOT NULL,
                weights JSON NULL,
                params JSON NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_strategy_settings_strategy (strategy_id),
                CONSTRAINT fk_strategy_settings_strategy FOREIGN KEY (strategy_id) REFERENCES strategies(id) ON DELETE CASCADE
            ) $engine",

            "CREATE TABLE IF NOT EXISTS signals (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid VARCHAR(36) NOT NULL,
                exchange_id BIGINT UNSIGNED NOT NULL,
                symbol VARCHAR(32) NOT NULL,
                direction ENUM('LONG','SHORT') NOT NULL,
                timeframe VARCHAR(8) NOT NULL,
                entry_price DECIMAL(24,10) NOT NULL,
                stop_loss DECIMAL(24,10) NOT NULL,
                tp1 DECIMAL(24,10) NULL,
                tp2 DECIMAL(24,10) NULL,
                tp3 DECIMAL(24,10) NULL,
                risk_reward DECIMAL(10,4) NOT NULL DEFAULT 0,
                score DECIMAL(6,2) NOT NULL DEFAULT 0,
                confidence VARCHAR(16) NOT NULL DEFAULT 'medium',
                strategy VARCHAR(64) NOT NULL,
                reasons JSON NULL,
                fingerprint VARCHAR(64) NOT NULL,
                status ENUM('pending','queued','sent','failed','expired','invalidated','test') NOT NULL DEFAULT 'pending',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_signals_uuid (uuid),
                KEY idx_signals_fingerprint (fingerprint, created_at),
                KEY idx_signals_symbol (exchange_id, symbol, created_at)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS signal_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                signal_id BIGINT UNSIGNED NOT NULL,
                channel_id BIGINT UNSIGNED NULL,
                event_type ENUM('queued','sent','failed','retry','test') NOT NULL,
                message_id BIGINT NULL,
                error TEXT NULL,
                attempt INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_signal_events_signal (signal_id),
                CONSTRAINT fk_signal_events_signal FOREIGN KEY (signal_id) REFERENCES signals(id) ON DELETE CASCADE
            ) $engine",

            "CREATE TABLE IF NOT EXISTS scanner_runs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                exchange_id BIGINT UNSIGNED NULL,
                started_at DATETIME NOT NULL,
                finished_at DATETIME NULL,
                symbols_scanned INT NOT NULL DEFAULT 0,
                symbols_selected INT NOT NULL DEFAULT 0,
                status VARCHAR(16) NOT NULL DEFAULT 'running',
                error TEXT NULL
            ) $engine",

            "CREATE TABLE IF NOT EXISTS logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                level ENUM('debug','info','warning','error','critical') NOT NULL DEFAULT 'info',
                channel VARCHAR(32) NOT NULL DEFAULT 'app',
                message TEXT NOT NULL,
                context JSON NULL,
                created_at DATETIME NOT NULL,
                KEY idx_logs_created (created_at),
                KEY idx_logs_level (level)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS admin_states (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                telegram_user_id BIGINT NOT NULL,
                state VARCHAR(64) NOT NULL,
                payload JSON NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_admin_states_user (telegram_user_id)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS message_refs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ref_type VARCHAR(32) NOT NULL,
                ref_id VARCHAR(64) NOT NULL,
                chat_id BIGINT NOT NULL,
                message_id BIGINT NOT NULL,
                quote_text MEDIUMTEXT NULL,
                quote_entities JSON NULL,
                created_at DATETIME NOT NULL,
                KEY idx_message_refs_ref (ref_type, ref_id)
            ) $engine",
        ];

        foreach ($statements as $sql) {
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
             ON DUPLICATE KEY UPDATE display_name = VALUES(display_name)'
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
            'INSERT IGNORE INTO text_formats (text_key, text_value, entities, updated_at) VALUES (:k, :v, :e, :u)'
        );
        foreach ($defaults as $key => $value) {
            $stmt->execute([':k' => $key, ':v' => $value, ':e' => json_encode([]), ':u' => $now]);
        }

        // Seed admin from env (owner)
        foreach (Config::adminIds() as $adminId) {
            $ins = $pdo->prepare(
                'INSERT INTO admins (telegram_user_id, role, created_at) VALUES (:id, "owner", :now)
                 ON DUPLICATE KEY UPDATE telegram_user_id = telegram_user_id'
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
