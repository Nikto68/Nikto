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
                    // env.php is loaded first, above, and must win on a key
                    // both files set (the doc comment above promises exactly
                    // that) — so .env only fills in keys env.php left unset.
                    if (!array_key_exists($key, self::$vars)) {
                        self::$vars[$key] = $value;
                    }
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

    public static function gateRestBase(): string
    {
        return Env::get('GATE_REST_BASE', 'https://api.gateio.ws') ?? 'https://api.gateio.ws';
    }

    public static function bitgetRestBase(): string
    {
        return Env::get('BITGET_REST_BASE', 'https://api.bitget.com') ?? 'https://api.bitget.com';
    }

    public static function htxRestBase(): string
    {
        return Env::get('HTX_REST_BASE', 'https://api.huobi.pro') ?? 'https://api.huobi.pro';
    }

    /**
     * CryptoCompare is a market-data aggregator, not an exchange with its
     * own trading/compliance obligations -- unlike Binance/MEXC it's a
     * realistic route around a host IP being geo-blocked by an exchange
     * directly. Free tier works without a key at low volume; set one for
     * higher rate limits.
     */
    public static function cryptocompareApiKey(): string
    {
        return self::dbOverride('CRYPTOCOMPARE_API_KEY') ?? (Env::get('CRYPTOCOMPARE_API_KEY', '') ?? '');
    }

    public static function cryptocompareRestBase(): string
    {
        return Env::get('CRYPTOCOMPARE_REST_BASE', 'https://min-api.cryptocompare.com') ?? 'https://min-api.cryptocompare.com';
    }

    public static function coinalyzeApiKey(): string
    {
        return self::dbOverride('COINALYZE_API_KEY') ?? (Env::get('COINALYZE_API_KEY', '') ?? '');
    }

    public static function coinalyzeRestBase(): string
    {
        return Env::get('COINALYZE_REST_BASE', 'https://api.coinalyze.net/v1') ?? 'https://api.coinalyze.net/v1';
    }

    /** Coinalyze's own symbol notation, e.g. BTCUSDT_PERP.A for Binance USDT-M. */
    public static function coinalyzeSymbol(): string
    {
        return self::dbOverride('COINALYZE_SYMBOL') ?? (Env::get('COINALYZE_SYMBOL', 'BTCUSDT_PERP.A') ?? 'BTCUSDT_PERP.A');
    }

    /**
     * Bybit / OKX / KuCoin: large, high-volume exchanges whose public spot
     * market-data endpoints need no API key. Every exchange is isolated via
     * ExchangeManager's circuit breaker, so one being blocked or down just
     * means it contributes 0 symbols instead of breaking anything else.
     */
    public static function bybitRestBase(): string
    {
        return Env::get('BYBIT_REST_BASE', 'https://api.bybit.com') ?? 'https://api.bybit.com';
    }

    public static function okxRestBase(): string
    {
        return Env::get('OKX_REST_BASE', 'https://www.okx.com') ?? 'https://www.okx.com';
    }

    public static function kucoinRestBase(): string
    {
        return Env::get('KUCOIN_REST_BASE', 'https://api.kucoin.com') ?? 'https://api.kucoin.com';
    }

    /** @return string[] enabled exchange names, in priority order */
    public static function enabledExchanges(): array
    {
        return Env::getList('ENABLED_EXCHANGES', 'mexc,binance,bybit,okx,kucoin,gate,bitget,htx,cryptocompare');
    }

    /**
     * The venue a signal is issued on when several list the same pair.
     *
     * MEXC leads because it lists far more of the small caps and memecoins
     * this bot hunts than the others do — a setup found on a coin that is
     * simply not tradable on the reader's exchange is a wasted signal. The
     * others stay enabled as a fallback for whatever MEXC does not carry.
     */
    public static function primaryExchange(): string
    {
        $override = self::dbOverride('PRIMARY_EXCHANGE');
        $name = strtolower(trim((string) ($override ?? Env::get('PRIMARY_EXCHANGE', 'mexc') ?? 'mexc')));
        return $name === '' ? 'mexc' : $name;
    }

    // -- Scanner -------------------------------------------------------
    // Every scanner filter below is admin-panel-editable (📊 Scanner →
    // ⚙️ تنظیمات فیلتر), stored via the same DB-override mechanism as the
    // exchange API keys, so they can be tuned live from Telegram without
    // touching env.php — deliberately, since the "right" threshold depends
    // on which exchange/market is actually reachable and varies a lot
    // (Binance-scale liquidity vs. a smaller regional exchange).
    public static function scannerTopN(): int
    {
        $override = self::dbOverride('SCANNER_TOP_N');
        // 0 means no cap: every pair that clears the volume floor stays in
        // the universe. The rotation below is what bounds the work, not an
        // arbitrary slice of the market.
        return $override !== null ? (int) $override : Env::getInt('SCANNER_TOP_N', 0);
    }

    public static function scannerIntervalSeconds(): int
    {
        return Env::getInt('SCANNER_INTERVAL_SECONDS', 3600);
    }

    public static function minVolumeUsdt(): float
    {
        $override = self::dbOverride('MIN_VOLUME_USDT');
        // The only real gate on which coins exist for this bot. Set at the
        // ALT tier floor (see SymbolClassifier::tier()) on purpose: a coin
        // thin enough to fall into MICRO is exactly the kind a $100 entry
        // can get pumped-and-dumped or slipped on, real turnover or not
        // enough of it to absorb an entry/exit cleanly.
        return $override !== null ? (float) $override : Env::getFloat('MIN_VOLUME_USDT', 5_000_000.0);
    }

    public static function maxSpreadPercent(): float
    {
        $override = self::dbOverride('MAX_SPREAD_PERCENT');
        return $override !== null ? (float) $override : Env::getFloat('MAX_SPREAD_PERCENT', 0.5);
    }

    /**
     * Quote assets a pair may be priced in. Returning [] means no filter at
     * all, which only happens when the value is the explicit '*' sentinel.
     *
     * An EMPTY setting resolves to the dollar-stablecoin set rather than to
     * "allow everything". Allowing everything sounds harmless and is not:
     * regional exchanges list fiat pairs, so an unfiltered scan ranks things
     * like USDTTMN — Tether priced in Iranian toman — as a tradable symbol
     * and happily publishes leveraged "signals" on a currency peg. This bot
     * trades crypto against a dollar stablecoin; that is the default.
     *
     * @return string[]
     */
    public const DEFAULT_QUOTE_ASSETS = ['USDT', 'USDC', 'FDUSD', 'BUSD', 'TUSD', 'DAI'];

    public static function allowedQuoteAssets(): array
    {
        $override = self::dbOverride('ALLOWED_QUOTE_ASSETS');
        $raw = $override ?? (Env::get('ALLOWED_QUOTE_ASSETS', '') ?? '');

        if (trim($raw) === '*') {
            return []; // explicit "no filter"
        }
        $list = array_values(array_filter(array_map(
            static fn($x) => strtoupper(trim($x)),
            explode(',', $raw)
        ), static fn($x) => $x !== ''));

        return empty($list) ? self::DEFAULT_QUOTE_ASSETS : $list;
    }

    /**
     * Quote currencies that are never tradable here, whatever
     * ALLOWED_QUOTE_ASSETS says — including when it says '*'.
     *
     * These are national currencies. A pair quoted in one is a fiat on-ramp
     * rate, not a leveraged crypto instrument: the bot published signals on
     * USDTTMN (toman) and USDTBRL (real), which are currency pegs. The
     * allow-list alone did not stop it, because an operator who wants "all
     * crypto quotes" reaches for '*' and gets fiat with it.
     *
     * @return string[]
     */
    public static function blockedQuoteAssets(): array
    {
        $custom = Env::getList('BLOCKED_QUOTE_ASSETS', '');
        if (!empty($custom)) {
            return array_map('strtoupper', $custom);
        }
        return [
            'TMN', 'IRT', 'IRR', 'TRY', 'BRL', 'RUB', 'UAH', 'ARS', 'NGN', 'ZAR',
            'EUR', 'GBP', 'JPY', 'KRW', 'CNY', 'INR', 'IDR', 'VND', 'THB', 'PHP',
            'MXN', 'COP', 'PLN', 'CZK', 'RON', 'HUF', 'AUD', 'CAD', 'CHF', 'SEK',
            'NOK', 'DKK', 'AED', 'SAR', 'EGP', 'PKR', 'BDT', 'KZT', 'GEL', 'AZN',
        ];
    }

    public static function includeStablecoinPairs(): bool
    {
        return Env::getBool('INCLUDE_STABLECOIN_PAIRS', false);
    }

    // -- Timeframes ------------------------------------------------------
    /** @return string[] */
    public static function timeframes(): array
    {
        return Env::getList('TIMEFRAMES', '15m,30m,1h,2h');
    }

    // -- Signal thresholds -------------------------------------------------
    /**
     * Minimum CONVICTION for a signal to be publishable.
     *
     * Note the scale changed with the confluence rework: this used to be a
     * directional score where >=60 meant bullish, which made 75 a sane-
     * looking (but short-blocking) threshold. It is now symmetric
     * conviction — 0 is "no evidence either way", 100 is "everything
     * aligns" — so a long and an equally strong short are gated identically.
     */
    public static function minSignalScore(): float
    {
        $override = self::dbOverride('MIN_SIGNAL_SCORE');
        return $override !== null ? (float) $override : Env::getFloat('MIN_SIGNAL_SCORE', 45.0);
    }

    public static function cooldownSeconds(): int
    {
        return Env::getInt('SIGNAL_COOLDOWN_SECONDS', 900);
    }

    /**
     * After a coin stops out, refuse a fresh signal on it for this many
     * seconds -- even a genuinely new, independent setup, since it is
     * still fighting the same area of the chart that just took the stop.
     * 0 disables this and returns to the old behaviour (a new setup can
     * fire again the moment it qualifies). Checked by base asset, the same
     * way SignalRepository::hasOpenPositionForBaseAsset() already is, so a
     * BTC stop on one exchange also cools BTC down on every other.
     */
    public static function symbolLossCooldownSeconds(): int
    {
        $override = self::dbOverride('SYMBOL_LOSS_COOLDOWN_SECONDS');
        return max(0, $override !== null ? (int) $override : Env::getInt('SYMBOL_LOSS_COOLDOWN_SECONDS', 10800));
    }

    /**
     * The higher-timeframe confirmation cascade: which timeframe an entry
     * timeframe must be checked against for HTF bias, e.g. "15m:1h,30m:2h,
     * 1h:4h" -- roughly a 4x step, not simply "whatever is next in
     * SIGNAL_TIMEFRAMES" (which for the default 15m/30m/1h/2h list would
     * check 15m against 30m, far too close to carry independent context).
     * Returns null when $timeframe has no entry in the map, in which case
     * higherTimeframeBias() falls back to its old "next configured
     * timeframe" behaviour.
     */
    public static function htfConfirmTimeframe(string $timeframe): ?string
    {
        $override = self::dbOverride('HTF_CONFIRM_MAP');
        $raw = (string) ($override ?? Env::get('HTF_CONFIRM_MAP', '15m:1h,30m:2h,1h:4h,2h:4h,4h:1d'));
        foreach (explode(',', $raw) as $pair) {
            $parts = explode(':', trim($pair), 2);
            if (count($parts) === 2 && trim($parts[0]) === $timeframe) {
                $target = trim($parts[1]);
                return $target === '' ? null : $target;
            }
        }
        return null;
    }

    /**
     * The A+ Setup Filter: a second, independent-evidence-counting gate on
     * top of confluence_pro's own capped-group score. A setup can clear the
     * score floor on strength piled into one or two groups; this instead
     * asks how many of seven genuinely separate confirmations (HTF trend,
     * liquidity sweep, CHoCH/BOS, a fresh OB/FVG, an actual retest pattern,
     * displacement+volume, room to target) are actually present, and
     * rejects a handful of setup shapes (an entry from dead-center of a
     * range) outright regardless of score. See APlusSetupFilter.
     */
    public static function requireAPlusSetup(): bool
    {
        $override = self::dbOverride('REQUIRE_APLUS_SETUP');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        // Off by default -- stacked on top of an already-strict
        // confluence_pro (score floor, room-to-target, fresh-reversal-zone,
        // HTF alignment, zone confluence, ...), this cut signal volume to
        // zero in practice. Confluence_pro's own gates are the enforced
        // baseline; this stays available as an OPTIONAL extra layer for
        // whoever wants to trade it stricter, toggled on from the panel.
        return Env::getBool('REQUIRE_APLUS_SETUP', false);
    }

    /** How many of the 7 A+ confirmations are required. Clamped to 1-7. */
    public static function aPlusMinConfirmations(): int
    {
        $override = self::dbOverride('APLUS_MIN_CONFIRMATIONS');
        $value = $override !== null ? (int) $override : Env::getInt('APLUS_MIN_CONFIRMATIONS', 3);
        return max(1, min(7, $value));
    }

    /**
     * A manually-entered blackout window (free text, parsed with
     * strtotime -- e.g. "2026-09-20 16:00") during which no new signal is
     * generated at all, for a known high-impact release (CPI/FOMC/NFP/rate
     * decision) the operator wants to sit out. There is no live economic
     * calendar wired into this project -- the operator enters the window
     * themselves from the panel. Returns null when unset or unparsable.
     */
    public static function newsBlackoutStart(): ?int
    {
        $override = self::dbOverride('NEWS_BLACKOUT_START');
        $raw = trim((string) ($override ?? Env::get('NEWS_BLACKOUT_START', '')));
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        return $ts !== false ? $ts : null;
    }

    /** See newsBlackoutStart(). */
    public static function newsBlackoutEnd(): ?int
    {
        $override = self::dbOverride('NEWS_BLACKOUT_END');
        $raw = trim((string) ($override ?? Env::get('NEWS_BLACKOUT_END', '')));
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        return $ts !== false ? $ts : null;
    }

    /** True right now if both ends of the blackout window are set and valid and "now" falls inside them. */
    public static function isNewsBlackoutActive(): bool
    {
        $start = self::newsBlackoutStart();
        $end = self::newsBlackoutEnd();
        if ($start === null || $end === null || $start > $end) {
            return false;
        }
        $now = time();
        return $now >= $start && $now <= $end;
    }

    /**
     * SignalValidator gates a signal's realised R:R against
     * min(this, tp1RiskReward()) -- the lower of the two -- so this floor
     * never contradicts an operator-configured TP1 that is deliberately
     * tighter than the stop (TP1_LEVERAGED_PCT below MAX_STOP_LEVERAGED_PCT
     * means tp1RiskReward() < 1 on purpose, a fast partial scoop before the
     * trade is proven). Raise this only if TP1 itself should require more
     * reward than that.
     */
    public static function minRiskReward(): float
    {
        $override = self::dbOverride('MIN_RISK_REWARD');
        return $override !== null ? (float) $override : Env::getFloat('MIN_RISK_REWARD', 1.2);
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

    // -- Automatic trading policy -------------------------------------------
    // These are what make the bot run unattended: how often it is allowed
    // to speak, which timeframes it may speak about, how much leverage it
    // announces per coin, and where the targets sit. All of them are
    // panel-editable (🤖 اتومات) through the same DB-override mechanism as
    // the scanner filters, so they can be retuned from Telegram without
    // touching env.php.

    /**
     * Timeframes a signal may be issued on — deliberately narrower than
     * TIMEFRAMES (which also drives candle collection): 1m/5m noise is
     * useful as context but is not something to trade off.
     *
     * @return string[]
     */
    public static function signalTimeframes(): array
    {
        $override = self::dbOverride('SIGNAL_TIMEFRAMES');
        $list = $override !== null
            ? array_values(array_filter(array_map('trim', explode(',', $override))))
            : Env::getList('SIGNAL_TIMEFRAMES', '15m,30m,1h,2h');
        return empty($list) ? ['15m', '30m', '1h', '2h'] : $list;
    }

    /**
     * Targets and the stop are specified as LEVERAGED account percentages —
     * "stop no worse than 30%, first target 28%, second 50%, third 90%,
     * fourth 160%" — not as price moves and not as risk multiples. The
     * planner converts each one with the leverage the coin actually gets,
     * so at 20x these are price moves of 1.5% / 1.4% / 2.5% / 4.5% / 8%.
     * TP1 sitting BELOW the stop's leveraged percentage is intentional here
     * (a fast, close first scoop taken well before the trade is proven,
     * R:R under 1) — the real reward is meant to come from TP2-TP4 if price
     * keeps going; see minRiskReward()'s comment for how that interacts
     * with the R:R floor.
     */
    public static function tp1LeveragedPercent(): float
    {
        $override = self::dbOverride('TP1_LEVERAGED_PCT');
        return max(1.0, $override !== null ? (float) $override : Env::getFloat('TP1_LEVERAGED_PCT', 28.0));
    }

    /** Leveraged profit at the second target. */
    public static function tp2LeveragedPercent(): float
    {
        $override = self::dbOverride('TP2_LEVERAGED_PCT');
        return max(1.0, $override !== null ? (float) $override : Env::getFloat('TP2_LEVERAGED_PCT', 50.0));
    }

    /** Leveraged profit at the third target. */
    public static function tp3LeveragedPercent(): float
    {
        $override = self::dbOverride('TP3_LEVERAGED_PCT');
        return max(1.0, $override !== null ? (float) $override : Env::getFloat('TP3_LEVERAGED_PCT', 90.0));
    }

    /** Leveraged profit at the fourth (final) target — the trade closes here. */
    public static function tp4LeveragedPercent(): float
    {
        $override = self::dbOverride('TP4_LEVERAGED_PCT');
        return max(1.0, $override !== null ? (float) $override : Env::getFloat('TP4_LEVERAGED_PCT', 160.0));
    }

    /** Worst leveraged loss a published stop may carry. */
    public static function maxStopLeveragedPercent(): float
    {
        $override = self::dbOverride('MAX_STOP_LEVERAGED_PCT');
        return max(1.0, $override !== null ? (float) $override : Env::getFloat('MAX_STOP_LEVERAGED_PCT', 30.0));
    }

    /** Risk-multiple of the first target, derived from the leveraged percentages above. */
    public static function tp1RiskReward(): float
    {
        return round(self::tp1LeveragedPercent() / self::maxStopLeveragedPercent(), 2);
    }

    /** Risk-multiple of the second target. */
    public static function tp2RiskReward(): float
    {
        return round(self::tp2LeveragedPercent() / self::maxStopLeveragedPercent(), 2);
    }

    /** Risk-multiple of the third target. */
    public static function tp3RiskReward(): float
    {
        return round(self::tp3LeveragedPercent() / self::maxStopLeveragedPercent(), 2);
    }

    /** Risk-multiple of the fourth (final) target. */
    public static function tp4RiskReward(): float
    {
        return round(self::tp4LeveragedPercent() / self::maxStopLeveragedPercent(), 2);
    }

    // -- Where the reader actually trades --------------------------------
    //
    // Analysis runs on MEXC (deepest small-cap coverage), but the trades
    // are taken on Toobit and Ourbit, so a setup on a coin neither of them
    // lists is a signal nobody can act on. These listings are fetched once
    // every few hours and cached. Leave the list empty to disable the
    // filter entirely.

    /** @return string[] */
    public static function tradableVenues(): array
    {
        $override = self::dbOverride('TRADABLE_VENUES');
        $raw = $override ?? (Env::get('TRADABLE_VENUES', 'toobit,ourbit') ?? '');
        $list = array_values(array_filter(array_map(
            static fn(string $v): string => strtolower(trim($v)),
            explode(',', $raw)
        )));
        return $list === ['off'] || $list === ['none'] ? [] : $list;
    }

    /**
     * Where a venue's instrument list lives. The defaults follow each
     * exchange's documented convention; if one moves, point this at the new
     * URL from the panel rather than waiting for a code change — the parser
     * does not care about the response's exact shape.
     */
    public static function venueListingUrl(string $venue): string
    {
        $key = strtoupper($venue) . '_LISTINGS_URL';
        $override = self::dbOverride($key);
        if ($override !== null && trim($override) !== '') {
            return trim($override);
        }
        $configured = Env::get($key, '') ?? '';
        if (trim($configured) !== '') {
            return trim($configured);
        }
        return match (strtolower($venue)) {
            // Toobit publishes spot symbols and USDT-M contracts in one
            // document (ccxt reads the same endpoint).
            'toobit' => 'https://api.toobit.com/api/v1/exchangeInfo',
            // Ourbit is built on the MEXC codebase and follows its futures
            // contract-detail path.
            'ourbit' => 'https://contract.ourbit.com/api/v1/contract/detail',
            'mexc' => 'https://contract.mexc.com/api/v1/contract/detail',
            'bitunix' => 'https://fapi.bitunix.com/api/v1/futures/market/trading_pairs',
            default => '',
        };
    }

    /** How long a fetched venue listing stays good for. */
    public static function venueListingTtlSeconds(): int
    {
        $override = self::dbOverride('VENUE_LISTINGS_TTL');
        return max(300, $override !== null ? (int) $override : Env::getInt('VENUE_LISTINGS_TTL', 21600));
    }

    // -- Confluence engine ------------------------------------------------
    //
    // The combination strategy scores a setup by how many independent
    // modules agree with it. These are the weights and the bar it has to
    // clear. Raising MIN_CONFLUENCE_SCORE means fewer, better-supported
    // signals; lowering it means more, thinner ones.

    /** Points for each of the six entry setups that fires. */
    public static function setupWeight(): float
    {
        return self::weight('CONFLUENCE_SETUP_WEIGHT', 18.0);
    }

    /** Points for a breakout from a statistically qualified range. */
    public static function rangeWeight(): float
    {
        return self::weight('CONFLUENCE_RANGE_WEIGHT', 16.0);
    }

    /** Points for a BOS / CHoCH agreeing with the trade. */
    public static function structureWeight(): float
    {
        return self::weight('CONFLUENCE_STRUCTURE_WEIGHT', 14.0);
    }

    /** Points for a liquidity sweep, or untapped liquidity ahead. */
    public static function liquidityWeight(): float
    {
        return self::weight('CONFLUENCE_LIQUIDITY_WEIGHT', 12.0);
    }

    /** Points for the ALMA wave and the EMA band pointing the same way. */
    public static function trendWeight(): float
    {
        return self::weight('CONFLUENCE_TREND_WEIGHT', 12.0);
    }

    /** Points for an order block / FVG / supply-demand zone behind the stop. */
    public static function zoneWeight(): float
    {
        return self::weight('CONFLUENCE_ZONE_WEIGHT', 14.0);
    }

    /** Points for a micro-structure break (iBOS) agreeing with the trade. */
    public static function internalStructureWeight(): float
    {
        return self::weight('CONFLUENCE_INTERNAL_WEIGHT', 8.0);
    }

    /** Points for trading from the correct half of the dealing range. */
    public static function premiumDiscountWeight(): float
    {
        return self::weight('CONFLUENCE_PD_WEIGHT', 12.0);
    }

    /** Points for price sitting inside the 62-79% optimal-entry pocket. */
    public static function oteWeight(): float
    {
        return self::weight('CONFLUENCE_OTE_WEIGHT', 10.0);
    }

    /** Points for the next timeframe up agreeing. */
    public static function htfWeight(): float
    {
        return self::weight('CONFLUENCE_HTF_WEIGHT', 14.0);
    }

    /**
     * Points for a fresh breaker block: a liquidity sweep immediately
     * followed by a structure shift the other way. Weighted close to a
     * structure break because that is what it is, plus the sweep in front
     * of it -- and because it is one of the few tells that shows up at the
     * START of a move instead of after it.
     */
    public static function breakerBlockWeight(): float
    {
        return self::weight('CONFLUENCE_BREAKER_WEIGHT', 16.0);
    }

    /** Points for a volatility squeeze whose first candle out already leans a direction. */
    public static function squeezeWeight(): float
    {
        return self::weight('CONFLUENCE_SQUEEZE_WEIGHT', 10.0);
    }

    /** Points for RSI sitting where this coin's own swing highs/lows have actually formed (kernel-density read, not a fixed 30/70 line). */
    public static function rsiReversalWeight(): float
    {
        return self::weight('CONFLUENCE_RSI_REVERSAL_WEIGHT', 10.0);
    }

    /** Points for the adaptive SuperTrend AI (Clustering) trend read agreeing. */
    public static function superTrendWeight(): float
    {
        return self::weight('CONFLUENCE_SUPERTREND_WEIGHT', 8.0);
    }

    /** Points for a body-to-body volume imbalance in the trade's direction on the current candle. */
    public static function volumeImbalanceWeight(): float
    {
        return self::weight('CONFLUENCE_VOLUME_IMBALANCE_WEIGHT', 6.0);
    }

    /** Points for a clean displacement candle in the trade's direction. */
    public static function displacementWeight(): float
    {
        return self::weight('CONFLUENCE_DISPLACEMENT_WEIGHT', 6.0);
    }

    /** Points for price sitting at the single most-tested S/R zone within reach. */
    public static function strongZoneWeight(): float
    {
        return self::weight('CONFLUENCE_STRONG_ZONE_WEIGHT', 8.0);
    }

    /**
     * A hard reject, not just a missed bonus: the single strongest S/R zone
     * within reach (see nearestStrongZone()) opposing the chosen direction
     * blocks the trade outright once it has been respected at least this
     * many times -- a level nobody has broken in months is not something
     * eight smaller, correlated votes get to outvote.
     */
    public static function strongZoneVetoTouches(): int
    {
        $override = self::dbOverride('STRONG_ZONE_VETO_TOUCHES');
        return max(1, $override !== null ? (int) $override : Env::getInt('STRONG_ZONE_VETO_TOUCHES', 3));
    }

    /** Points for the 200-period EMA agreeing with the trade's direction (long-horizon trend context). */
    public static function ema200Weight(): float
    {
        return self::weight('CONFLUENCE_EMA200_WEIGHT', 8.0);
    }

    /** Points for MACD's histogram/line agreeing with the trade's direction. */
    public static function macdWeight(): float
    {
        return self::weight('CONFLUENCE_MACD_WEIGHT', 8.0);
    }

    /**
     * Off by default, same as requireKillzone() -- a market strength gate
     * is a meaningful behaviour change (it can silence a symbol that would
     * otherwise have qualified), so it stays opt-in until the operator has
     * actually turned it on and watched what it does to signal frequency.
     */
    public static function requireAdxFilter(): bool
    {
        $override = self::dbOverride('REQUIRE_ADX_FILTER');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('REQUIRE_ADX_FILTER', false);
    }

    /**
     * Below this, ADX reads "no real trend" -- 20 is the textbook floor
     * (20-25 emerging, 25+ trending, 40+ strong). Only enforced when
     * requireAdxFilter() is on.
     */
    public static function minAdx(): float
    {
        $override = self::dbOverride('MIN_ADX');
        return $override !== null ? (float) $override : Env::getFloat('MIN_ADX', 20.0);
    }

    /** Points for a nearby FVG price has meaningfully retraced into (see minFvgMitigationPercent). */
    public static function fvgMitigationWeight(): float
    {
        return self::weight('CONFLUENCE_FVG_MITIGATION_WEIGHT', 8.0);
    }

    /** How far into an FVG price must have retraced, as a percent of its height, to count as tested rather than barely touched. */
    public static function minFvgMitigationPercent(): float
    {
        $override = self::dbOverride('MIN_FVG_MITIGATION_PCT');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('MIN_FVG_MITIGATION_PCT', 5.0));
    }

    // -- Independent scoring groups ---------------------------------------
    //
    // Every vote above belongs to one of six independent evidence groups.
    // A setup with five correlated structure-ish votes should not outscore
    // one with two votes each from five genuinely different groups, so each
    // group's contribution is capped before the caps are summed into the
    // final score checked against minConfluenceScore(). Which side wins is
    // still decided by the raw, uncapped vote total -- only the published
    // score changes.

    /** Cap on points from the structure group: BOS/CHoCH, wave shift, iBOS, range breakout, break-retest/range-break setups. */
    public static function confluenceStructureCap(): float
    {
        return self::weight('CONFLUENCE_STRUCTURE_CAP', 30.0);
    }

    /** Cap on points from the liquidity group: sweeps, fresh/flipped breaker blocks, untapped liquidity ahead, sweep/big-move setups. */
    public static function confluenceLiquidityCap(): float
    {
        return self::weight('CONFLUENCE_LIQUIDITY_CAP', 28.0);
    }

    /** Cap on points from the location group: strong zone, order-block/FVG guard, FVG mitigation, premium/discount, OTE, VWAP/EMA setups. */
    public static function confluenceLocationCap(): float
    {
        return self::weight('CONFLUENCE_LOCATION_CAP', 26.0);
    }

    /** Cap on points from the momentum group: squeeze breakout, RSI-KDE reversal, SuperTrend AI, divergence/channel setups. */
    public static function confluenceMomentumCap(): float
    {
        return self::weight('CONFLUENCE_MOMENTUM_CAP', 22.0);
    }

    /** Cap on points from the volume group: volume imbalance, displacement. */
    public static function confluenceVolumeCap(): float
    {
        return self::weight('CONFLUENCE_VOLUME_CAP', 14.0);
    }

    /** Cap on points from the HTF group: trend alignment (wave+EMA), higher-timeframe bias. */
    public static function confluenceHtfCap(): float
    {
        return self::weight('CONFLUENCE_HTF_CAP', 22.0);
    }

    /**
     * ICT "killzones" (Asian/London/New York session windows) as a
     * required gate. Off by default -- crypto has no exchange session of
     * its own the way forex does, so this is only for an operator who
     * wants to test it explicitly, not a default assumption that it helps.
     */
    public static function requireKillzone(): bool
    {
        $override = self::dbOverride('REQUIRE_KILLZONE');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('REQUIRE_KILLZONE', false);
    }

    /**
     * Refuse to buy in premium or sell in discount outright, rather than
     * merely scoring it lower. A sweep reversal is exempt — taking the
     * other side of a stop run is precisely a trade against the range.
     */
    public static function requireDiscountPremium(): bool
    {
        $override = self::dbOverride('REQUIRE_DISCOUNT_PREMIUM');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('REQUIRE_DISCOUNT_PREMIUM', true);
    }

    /** Refuse a trade the next timeframe up disagrees with. */
    public static function requireHtfAlignment(): bool
    {
        $override = self::dbOverride('REQUIRE_HTF_ALIGNMENT');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('REQUIRE_HTF_ALIGNMENT', true);
    }

    // -- Sweep / big-move trigger -----------------------------------------

    /** How many bars back the swept extreme is measured over. */
    public static function bigMoveLookback(): int
    {
        $override = self::dbOverride('BIG_MOVE_LOOKBACK');
        return max(5, $override !== null ? (int) $override : Env::getInt('BIG_MOVE_LOOKBACK', 20));
    }

    /** How many bars the confirmation candle has to arrive in. */
    public static function bigMoveConfirmBars(): int
    {
        $override = self::dbOverride('BIG_MOVE_CONFIRM_BARS');
        return max(1, $override !== null ? (int) $override : Env::getInt('BIG_MOVE_CONFIRM_BARS', 3));
    }

    /** Minimum rejection wick on the sweep candle, as a fraction of its range. */
    public static function bigMoveMinWick(): float
    {
        $override = self::dbOverride('BIG_MOVE_MIN_WICK');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('BIG_MOVE_MIN_WICK', 0.15));
    }

    /** Minimum body of the confirmation candle, as a fraction of its range. */
    public static function bigMoveBodyStrength(): float
    {
        $override = self::dbOverride('BIG_MOVE_BODY_STRENGTH');
        return max(0.05, $override !== null ? (float) $override : Env::getFloat('BIG_MOVE_BODY_STRENGTH', 0.55));
    }

    /**
     * The bar a setup's total has to clear before it is published, on the
     * capped-group scale (see confluence*Cap() above; max achievable is
     * their sum, ~142 at the defaults). Recalibrated twice with the same
     * synthetic-run methodology (test_group_score_calibration.php) each
     * time the surrounding filters changed enough to move what "the same
     * selectivity as before" actually means:
     *
     *   1. 100 -> 90 when scoring moved from a flat sum to capped
     *      independent groups -- the old flat-sum number meant something
     *      stricter on the new scale, since caps already remove most of
     *      what used to let correlated votes inflate a score.
     *   2. 90 -> 85 when the fresh-reversal-zone and room-to-target hard
     *      gates were added -- both prune setups BEFORE the score is even
     *      computed, so the population reaching the score check is already
     *      more selective. Leaving the bar at 90 on top of that measured as
     *      noticeably stricter than intended (32% of gate-clearing setups
     *      passing vs. the ~40% benchmark every prior calibration targets);
     *      85 restores it.
     *
     * Recalibrate again the same way any time the vote list, the group
     * caps, or a hard gate upstream of the score check changes.
     */
    public static function minConfluenceScore(): float
    {
        $override = self::dbOverride('MIN_CONFLUENCE_SCORE');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('MIN_CONFLUENCE_SCORE', 75.0));
    }

    /**
     * Band height over ATR*sqrt(length) below which a window counts as a
     * range outright, regardless of how it ranks against its own history.
     * A random walk sits near 1.0, so the default is a genuinely tight box.
     */
    public static function rangeAbsoluteCompression(): float
    {
        $override = self::dbOverride('RANGE_ABS_COMPRESSION');
        return max(0.05, $override !== null ? (float) $override : Env::getFloat('RANGE_ABS_COMPRESSION', 0.75));
    }

    /**
     * How many recent candles are held back when measuring a range, so the
     * breakout itself is judged against the box rather than absorbed into it.
     */
    public static function rangeBreakLookback(): int
    {
        $override = self::dbOverride('RANGE_BREAK_LOOKBACK');
        return max(1, $override !== null ? (int) $override : Env::getInt('RANGE_BREAK_LOOKBACK', 3));
    }

    /** How far from price a protective zone may sit, in ATR, and still count. */
    public static function zoneReachAtr(): float
    {
        $override = self::dbOverride('ZONE_REACH_ATR');
        return max(0.2, $override !== null ? (float) $override : Env::getFloat('ZONE_REACH_ATR', 2.5));
    }

    /** Breathing room added beyond the protective zone, in ATR -- the NORMAL-volatility value. See stopPadAtrCalm()/stopPadAtrVolatile(). */
    public static function stopPadAtr(): float
    {
        $override = self::dbOverride('STOP_PAD_ATR');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('STOP_PAD_ATR', 0.25));
    }

    /** Breathing room beyond the protective zone, in ATR, when this coin is quieter than its own recent past (see volatilityCalmRatio()). A calm market needs less padding to avoid noise wicking the stop. */
    public static function stopPadAtrCalm(): float
    {
        $override = self::dbOverride('STOP_PAD_ATR_CALM');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('STOP_PAD_ATR_CALM', 0.15));
    }

    /** Breathing room beyond the protective zone, in ATR, when this coin is choppier than its own recent past (see volatilityHotRatio()). A volatile market needs more padding, or ordinary noise stops the trade out. */
    public static function stopPadAtrVolatile(): float
    {
        $override = self::dbOverride('STOP_PAD_ATR_VOLATILE');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('STOP_PAD_ATR_VOLATILE', 0.35));
    }

    /** Below this ratio of recent ATR to this coin's own prior ATR, the market counts as "calm" for stopPadAtrCalm(). */
    public static function volatilityCalmRatio(): float
    {
        $override = self::dbOverride('VOLATILITY_CALM_RATIO');
        return max(0.05, $override !== null ? (float) $override : Env::getFloat('VOLATILITY_CALM_RATIO', 0.85));
    }

    /** Above this ratio of recent ATR to this coin's own prior ATR, the market counts as "volatile" for stopPadAtrVolatile(). */
    public static function volatilityHotRatio(): float
    {
        $override = self::dbOverride('VOLATILITY_HOT_RATIO');
        return max(1.0, $override !== null ? (float) $override : Env::getFloat('VOLATILITY_HOT_RATIO', 1.3));
    }

    /**
     * The nearest opposing structure ahead (untapped liquidity / EQH / EQL)
     * must be at least this many multiples of the stop's own risk distance
     * away, or the trade is refused outright: a target 0.3R away is not
     * something worth publishing even if everything else about the setup
     * is clean. Only checked when such a structure was actually found --
     * no opposing evidence in reach means nothing to refuse over.
     */
    public static function minRoomToTargetR(): float
    {
        $override = self::dbOverride('MIN_ROOM_TO_TARGET_R');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('MIN_ROOM_TO_TARGET_R', 1.5));
    }

    /** How many candles old a structure shift may be and still be tradable. */
    public static function structureMaxAge(): int
    {
        $override = self::dbOverride('STRUCTURE_MAX_AGE');
        return max(1, $override !== null ? (int) $override : Env::getInt('STRUCTURE_MAX_AGE', 3));
    }

    private static function weight(string $key, float $default): float
    {
        $override = self::dbOverride($key);
        return max(0.0, $override !== null ? (float) $override : Env::getFloat($key, $default));
    }

    // -- Setup scanner tuning ---------------------------------------------

    /** Volume a setup's candle needs, as a multiple of the 20-candle average. */
    public static function setupVolumeMultiple(): float
    {
        $override = self::dbOverride('SETUP_VOLUME_MULT');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('SETUP_VOLUME_MULT', 1.0));
    }

    /** Bars price must spend on one side of VWAP before a reclaim counts. */
    public static function vwapAwayBars(): int
    {
        $override = self::dbOverride('VWAP_AWAY_BARS');
        return max(1, $override !== null ? (int) $override : Env::getInt('VWAP_AWAY_BARS', 6));
    }

    /** How recently price must have touched the 21 EMA for a pullback entry. */
    public static function emaTouchWindow(): int
    {
        $override = self::dbOverride('EMA_TOUCH_WINDOW');
        return max(1, $override !== null ? (int) $override : Env::getInt('EMA_TOUCH_WINDOW', 3));
    }

    /** Swing length used by the setup scanner's pivots. */
    public static function setupPivotLength(): int
    {
        $override = self::dbOverride('SETUP_PIVOT_LENGTH');
        return max(2, $override !== null ? (int) $override : Env::getInt('SETUP_PIVOT_LENGTH', 5));
    }

    /** How close a retest has to come to the broken level, in ATR. */
    public static function retestTolerance(): float
    {
        $override = self::dbOverride('RETEST_TOLERANCE_ATR');
        return max(0.01, $override !== null ? (float) $override : Env::getFloat('RETEST_TOLERANCE_ATR', 0.3));
    }

    /** How many bars a break stays eligible for its retest. */
    public static function retestWindow(): int
    {
        $override = self::dbOverride('RETEST_WINDOW');
        return max(2, $override !== null ? (int) $override : Env::getInt('RETEST_WINDOW', 20));
    }

    /** Lookback for the liquidity-sweep extreme. */
    public static function sweepLookback(): int
    {
        $override = self::dbOverride('SWEEP_LOOKBACK');
        return max(5, $override !== null ? (int) $override : Env::getInt('SWEEP_LOOKBACK', 20));
    }

    /** Largest gap, in bars, between the two pivots of a divergence. */
    public static function divergenceGap(): int
    {
        $override = self::dbOverride('DIVERGENCE_GAP');
        return max(5, $override !== null ? (int) $override : Env::getInt('DIVERGENCE_GAP', 60));
    }

    // -- Strategy quality gate -------------------------------------------
    //
    // These are the knobs that trade signal COUNT for win rate. Every one
    // of them makes the bot pickier; loosening them produces more signals
    // and worse ones. They are deliberately strict out of the box.

    /** Which strategy the worker runs. */
    public static function strategyName(): string
    {
        $override = self::dbOverride('STRATEGY');
        $name = trim((string) ($override ?? Env::get('STRATEGY', 'confluence_pro') ?? 'confluence_pro'));
        return $name === '' ? 'confluence_pro' : $name;
    }

    /**
     * Volume on the breaking candle, as a multiple of the prior 20-candle
     * average. Only used by structure_break (not the active confluence_pro
     * strategy) -- a hard gate on this was tried in confluence_pro and cut
     * signal volume to zero stacked on top of its other gates, so it was
     * reverted to a StructureBreakStrategy-only setting again.
     */
    public static function breakVolumeRatio(): float
    {
        $override = self::dbOverride('BREAK_VOLUME_RATIO');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('BREAK_VOLUME_RATIO', 1.3));
    }

    /** How far past the broken level price may already be, in ATR, before the entry is a chase. */
    public static function maxChaseAtr(): float
    {
        $override = self::dbOverride('MAX_CHASE_ATR');
        return max(0.1, $override !== null ? (float) $override : Env::getFloat('MAX_CHASE_ATR', 1.5));
    }

    /** How far a coin must have run off its floor before a short counts as fading a pump. */
    public static function reversalRunPercent(): float
    {
        $override = self::dbOverride('REVERSAL_RUN_PCT');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('REVERSAL_RUN_PCT', 12.0));
    }

    /** How tight a 40-candle range has to be, as a percent of price, to count as a base. */
    public static function baseRangePercent(): float
    {
        $override = self::dbOverride('BASE_RANGE_PCT');
        return max(0.5, $override !== null ? (float) $override : Env::getFloat('BASE_RANGE_PCT', 12.0));
    }

    /** Refuse an entry with no order block or FVG behind it to put the stop against. */
    public static function requireZoneConfluence(): bool
    {
        $override = self::dbOverride('REQUIRE_ZONE_CONFLUENCE');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('REQUIRE_ZONE_CONFLUENCE', true);
    }

    /**
     * Refuse a pullback/reversal entry (buying a demand zone after a drop,
     * selling a supply zone after a rally) unless a real hammer/shooting-star
     * candle just confirmed the turn on the fast timeframe -- see
     * ReversalCandleEngine. A continuation/breakout entry already has its
     * own confirmation (the break itself) and is not affected by this.
     */
    public static function requireReversalCandle(): bool
    {
        $override = self::dbOverride('REQUIRE_REVERSAL_CANDLE');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('REQUIRE_REVERSAL_CANDLE', true);
    }

    /** The fast timeframe a reversal/pullback entry's candle is confirmed on. */
    public static function reversalConfirmTimeframe(): string
    {
        $override = self::dbOverride('REVERSAL_CONFIRM_TIMEFRAME');
        $tf = trim((string) ($override ?? Env::get('REVERSAL_CONFIRM_TIMEFRAME', '5m') ?? '5m'));
        return $tf === '' ? '5m' : $tf;
    }

    /**
     * Refuse a pullback/reversal entry when the zone its stop leans on
     * already existed before the liquidity sweep that is supposed to
     * justify the reversal. A real reversal narrative is: a stop pool gets
     * swept, price reverses and leaves behind the very order block/FVG/
     * supply-demand zone it is now retracing into -- that zone is born
     * DURING the reversal leg, not before it. A zone that predates the
     * sweep is unconnected history: real, but not evidence for THIS trade.
     * Only checked when there is a sweep to anchor to; continuation/
     * breakout entries have no such narrative and are not affected.
     */
    public static function requireFreshReversalZone(): bool
    {
        $override = self::dbOverride('REQUIRE_FRESH_REVERSAL_ZONE');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('REQUIRE_FRESH_REVERSAL_ZONE', true);
    }

    /**
     * Requires the liquidity sweep itself to have happened AT the zone
     * (order block / support-resistance / supply-demand) the reversal's
     * stop leans on -- not just that the zone was born afterward (see
     * requireFreshReversalZone() above, which only checks time order).
     * This is the "a sharp, violent move that reacts exactly at a key
     * zone" case: a funding-squeeze/liquidation-style wick that sweeps a
     * level and rejects right where an order block, support, or
     * resistance already sits, rather than two nearby but unrelated
     * events. Only checked when there is a sweep to anchor to;
     * continuation/breakout entries are not affected.
     */
    public static function requireSweepAtZone(): bool
    {
        $override = self::dbOverride('REQUIRE_SWEEP_AT_ZONE');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('REQUIRE_SWEEP_AT_ZONE', true);
    }

    /**
     * Seconds of each invocation given to the symbol rotation.
     *
     * The universe is now every perpetual above the volume floor — several
     * hundred coins — which cannot be swept in one cron minute. Instead each
     * invocation walks as far along the list as this budget allows and
     * remembers where it stopped, so the whole market is covered on a
     * rotation of a few minutes rather than a slice of it being covered
     * over and over.
     */
    public static function rotationBudgetSeconds(): int
    {
        $override = self::dbOverride('ROTATION_BUDGET_SECONDS');
        return max(5, $override !== null ? (int) $override : Env::getInt('ROTATION_BUDGET_SECONDS', 32));
    }

    /** Hard ceiling on symbols visited per invocation, whatever the clock says. */
    public static function rotationMaxSymbols(): int
    {
        $override = self::dbOverride('ROTATION_MAX_SYMBOLS');
        return max(1, $override !== null ? (int) $override : Env::getInt('ROTATION_MAX_SYMBOLS', 400));
    }

    // -- Scanner buckets -------------------------------------------------
    //
    // The universe is not simply "the highest-volume coins": it is built
    // from three baskets, so the pass always contains today's biggest
    // movers in BOTH directions as well as the steady liquid names. A coin
    // that just ran 40% and a coin that just dumped 40% are the two places
    // a reversal or a continuation setup actually shows up; ranking on
    // volume alone would mean neither is ever looked at.

    /** Share of the universe reserved for the biggest 24h gainers, in percent. */
    public static function scannerGainerShare(): float
    {
        $override = self::dbOverride('SCANNER_GAINER_SHARE');
        return min(100.0, max(0.0, $override !== null ? (float) $override : Env::getFloat('SCANNER_GAINER_SHARE', 40.0)));
    }

    /** Share of the universe reserved for the biggest 24h losers, in percent. */
    public static function scannerLoserShare(): float
    {
        $override = self::dbOverride('SCANNER_LOSER_SHARE');
        return min(100.0, max(0.0, $override !== null ? (float) $override : Env::getFloat('SCANNER_LOSER_SHARE', 25.0)));
    }

    /** How far a coin must have moved in 24h to count as a mover at all. */
    public static function scannerMinMovePercent(): float
    {
        $override = self::dbOverride('SCANNER_MIN_MOVE_PCT');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('SCANNER_MIN_MOVE_PCT', 4.0));
    }

    // -- Money management ----------------------------------------------
    //
    // The bot does not place orders, so "money management" here means the
    // numbers a reader needs to size the trade themselves: how much of the
    // account to risk, what margin that works out to at this leverage, and
    // when to stop trading for the day. All of it is derived from the stop
    // distance the planner already produced — a fixed-percent-of-account
    // risk, which is the only sizing rule that survives a losing streak.

    /** Reference account size the suggested position is calculated from. */
    public static function accountBalance(): float
    {
        $override = self::dbOverride('ACCOUNT_BALANCE');
        return max(1.0, $override !== null ? (float) $override : Env::getFloat('ACCOUNT_BALANCE', 1000.0));
    }

    /** Percent of the account put at risk on a single trade. */
    public static function riskPerTradePercent(): float
    {
        $override = self::dbOverride('RISK_PER_TRADE_PCT');
        $v = $override !== null ? (float) $override : Env::getFloat('RISK_PER_TRADE_PCT', 2.0);
        return min(100.0, max(0.1, $v));
    }

    /** Losing trades in one day after which the bot stops publishing until tomorrow. */
    public static function maxDailyLosses(): int
    {
        $override = self::dbOverride('MAX_DAILY_LOSSES');
        return max(1, $override !== null ? (int) $override : Env::getInt('MAX_DAILY_LOSSES', 6));
    }

    /** Signals published in one day after which the bot stops until tomorrow. 0 = no cap. */
    public static function maxDailySignals(): int
    {
        $override = self::dbOverride('MAX_DAILY_SIGNALS');
        return max(0, $override !== null ? (int) $override : Env::getInt('MAX_DAILY_SIGNALS', 24));
    }

    /**
     * Trades allowed open at the same time. While the live count is at or
     * above this, maybeEmitSignal() (worker.php) will not publish another
     * one, no matter how many qualifying candidates a pass finds -- new
     * signals resume only once an open trade closes and frees a slot.
     * 0 = no cap.
     */
    public static function maxOpenTrades(): int
    {
        $override = self::dbOverride('MAX_OPEN_TRADES');
        return max(0, $override !== null ? (int) $override : Env::getInt('MAX_OPEN_TRADES', 3));
    }

    /**
     * How many of a pass's qualifying candidates may be published at once.
     * Default 1 -- exactly one signal per pass, never a burst of several
     * qualifying candidates dispatched together. The daily caps still
     * bind above it regardless.
     */
    public static function signalsPerPass(): int
    {
        $override = self::dbOverride('SIGNALS_PER_PASS');
        return max(1, $override !== null ? (int) $override : Env::getInt('SIGNALS_PER_PASS', 1));
    }

    /** Move the stop to entry once TP1 is hit, and announce it. */
    public static function riskFreeEnabled(): bool
    {
        $override = self::dbOverride('RISK_FREE_ENABLED');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('RISK_FREE_ENABLED', true);
    }

    /**
     * How much of the position a trader following the signal is expected
     * to close at TP1 before the stop moves to entry. A "risk-free" close
     * afterwards (result 'be') is never really a 0% trade -- this share of
     * the position already banked the TP1 profit, so the reported PnL for
     * a be/tp2 close is a blend of the two fills, not just the last one.
     */
    public static function tp1ClosePercent(): float
    {
        $override = self::dbOverride('TP1_CLOSE_PERCENT');
        $value = $override !== null ? (float) $override : Env::getFloat('TP1_CLOSE_PERCENT', 50.0);
        return max(0.0, min(100.0, $value));
    }

    /**
     * Trade-management advisories (Worker::monitorOpenPositions()), each
     * sent at most once per trade, as a reply -- never a forced close:
     *
     * - Before TP1: if price has given back this % of the ORIGINAL stop
     *   distance without ever reaching TP1, the trade is quietly going
     *   wrong -- a heads-up.
     * - After TP3: if price retraces this % of the TP2-TP3 leg from its
     *   post-TP3 peak without reaching TP4, that reads as stalling rather
     *   than continuing -- a "consider locking in profit here" reply.
     */
    public static function advisoryStopWarnPercent(): float
    {
        $override = self::dbOverride('ADVISORY_STOP_WARN_PCT');
        $value = $override !== null ? (float) $override : Env::getFloat('ADVISORY_STOP_WARN_PCT', 70.0);
        return max(1.0, min(100.0, $value));
    }

    public static function advisoryStallRetracePercent(): float
    {
        $override = self::dbOverride('ADVISORY_STALL_RETRACE_PCT');
        $value = $override !== null ? (float) $override : Env::getFloat('ADVISORY_STALL_RETRACE_PCT', 50.0);
        return max(1.0, min(100.0, $value));
    }


    // -- Leverage ----------------------------------------------------------

    /** @return string[] base assets that get the high-leverage treatment */
    public static function majorAssets(): array
    {
        $override = self::dbOverride('LEVERAGE_MAJOR_ASSETS');
        $list = $override !== null
            ? array_values(array_filter(array_map('trim', explode(',', strtoupper($override)))))
            : array_map('strtoupper', Env::getList('LEVERAGE_MAJOR_ASSETS', 'BTC,ETH'));
        return empty($list) ? ['BTC', 'ETH'] : $list;
    }

    public static function leverageMajor(): int
    {
        $override = self::dbOverride('LEVERAGE_MAJOR');
        return max(1, $override !== null ? (int) $override : Env::getInt('LEVERAGE_MAJOR', 20));
    }

    /** Lower bound of the auto-picked range for altcoins / low-cap / high-volatility pairs. */
    public static function leverageAltMin(): int
    {
        $override = self::dbOverride('LEVERAGE_ALT_MIN');
        return max(1, $override !== null ? (int) $override : Env::getInt('LEVERAGE_ALT_MIN', 20));
    }

    /** Upper bound of that range — reserved for the most liquid, least volatile alts. */
    public static function leverageAltMax(): int
    {
        $override = self::dbOverride('LEVERAGE_ALT_MAX');
        return max(1, $override !== null ? (int) $override : Env::getInt('LEVERAGE_ALT_MAX', 25));
    }

    /**
     * Fraction of the liquidation distance the stop loss is allowed to use.
     *
     * This is what keeps high announced leverage honest. At 150x a position
     * liquidates roughly 0.67% against you, so a stop placed 2% away — a
     * perfectly normal structural stop on a 4h chart — would be wiped out
     * long before it was ever reached. The planner therefore caps the stop
     * distance at buffer x (100 / leverage) percent, and the targets (1.2R
     * / 1.3R) are measured from that capped risk.
     */
    public static function leverageLiquidationBuffer(): float
    {
        $override = self::dbOverride('LEVERAGE_LIQUIDATION_BUFFER');
        $v = $override !== null ? (float) $override : Env::getFloat('LEVERAGE_LIQUIDATION_BUFFER', 0.75);
        return max(0.05, min(0.95, $v));
    }

    /**
     * How many symbols one signal pass may evaluate. Each symbol costs an
     * S/R + order-block + FVG + confluence run per timeframe, so on a cron
     * host with a ~50 second budget this is the knob that keeps a pass
     * inside it. Majors are always evaluated first (SymbolRepository::universe).
     */
    public static function signalMaxSymbolsPerPass(): int
    {
        $override = self::dbOverride('SIGNAL_MAX_SYMBOLS_PER_PASS');
        // 0 means no cap — see rotation, which bounds a pass by TIME rather
        // than by a symbol count, so every coin is reached in turn.
        return max(0, $override !== null ? (int) $override : Env::getInt('SIGNAL_MAX_SYMBOLS_PER_PASS', 0));
    }

    /**
     * Whether detected zones / order blocks / FVGs are written to their
     * tables. Nothing in the project reads them back — they are a debugging
     * aid — and a DELETE+INSERT per symbol per timeframe is the most
     * expensive thing a scan pass does on SQLite. Off by default.
     */
    public static function persistStructure(): bool
    {
        return Env::getBool('PERSIST_STRUCTURE', false);
    }

    /** Floor on stop distance (percent of entry), so a stop can never land inside the spread. */
    public static function minStopPercent(): float
    {
        $override = self::dbOverride('MIN_STOP_PERCENT');
        return max(0.01, $override !== null ? (float) $override : Env::getFloat('MIN_STOP_PERCENT', 0.12));
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

    // -- Logging -----------------------------------------------------------
    /**
     * Minimum level that gets written anywhere. Defaults to 'error': the
     * bot is meant to run unattended and silently, and the per-symbol
     * debug/info chatter is what used to fill both the log table and the
     * cron mail. Set LOG_LEVEL=info temporarily when diagnosing something.
     */
    public static function logMinLevel(): string
    {
        $v = strtolower(Env::get('LOG_LEVEL', 'error') ?? 'error');
        return in_array($v, ['debug', 'info', 'warning', 'error', 'critical'], true) ? $v : 'error';
    }

    /** Days of log rows to keep. Old rows are pruned on migrate(); 0 disables pruning. */
    public static function logRetentionDays(): int
    {
        return max(0, Env::getInt('LOG_RETENTION_DAYS', 3));
    }

    // -- Channel post button (inline "glass" button under every send) ----
    /**
     * One inline keyboard button shown under every message the bot posts
     * to a channel — the signal card, the profit-shot/close notices, the
     * advisory warnings. Purely presentational: nothing here feeds back
     * into signal.php's strategy engine. `style` is Bot API 9.4's
     * (February 2026) button color field: primary=blue, success=green,
     * danger=red. Admin-editable from /panel → دکمه‌های کانال; a row in
     * bot_settings always wins over the default below.
     */
    private const CHANNEL_BUTTON_STYLES = ['primary', 'success', 'danger'];
    private const CHANNEL_BUTTON_DEFAULTS = [
        1 => ['text' => '📢 کانال ما', 'url' => 'https://t.me', 'style' => 'success'],
    ];

    public static function channelButtonText(int $slot): string
    {
        $default = self::CHANNEL_BUTTON_DEFAULTS[$slot]['text'] ?? '';
        return self::dbOverride("BUTTON{$slot}_TEXT") ?? $default;
    }

    public static function channelButtonUrl(int $slot): string
    {
        $default = self::CHANNEL_BUTTON_DEFAULTS[$slot]['url'] ?? '';
        return self::dbOverride("BUTTON{$slot}_URL") ?? $default;
    }

    public static function channelButtonStyle(int $slot): string
    {
        $v = self::dbOverride("BUTTON{$slot}_STYLE");
        if ($v !== null && in_array($v, self::CHANNEL_BUTTON_STYLES, true)) {
            return $v;
        }
        return self::CHANNEL_BUTTON_DEFAULTS[$slot]['style'] ?? 'primary';
    }

    /** Custom (Premium) emoji id shown before the button text, or null when none is set. */
    public static function channelButtonEmojiId(int $slot): ?string
    {
        return self::dbOverride("BUTTON{$slot}_EMOJI_ID");
    }

    /** @return string[] valid `style` values Bot API 9.4+ accepts */
    public static function channelButtonStyles(): array
    {
        return self::CHANNEL_BUTTON_STYLES;
    }

    /**
     * Builds the `reply_markup` for every channel send: one row with the
     * single button. Returns null only if its text or URL somehow ends up
     * empty (there is always a built-in default for both, so in practice
     * this only happens if it was explicitly cleared).
     *
     * @return array{inline_keyboard: array<int, array<int, array<string,mixed>>>}|null
     */
    public static function channelButtonsKeyboard(): ?array
    {
        $text = trim(self::channelButtonText(1));
        $url = trim(self::channelButtonUrl(1));
        if ($text === '' || $url === '') {
            return null;
        }
        $button = ['text' => $text, 'url' => $url, 'style' => self::channelButtonStyle(1)];
        $emojiId = self::channelButtonEmojiId(1);
        if ($emojiId !== null && $emojiId !== '') {
            $button['icon_custom_emoji_id'] = $emojiId;
        }
        return ['inline_keyboard' => [[$button]]];
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

        // Added after the initial release -- ensureColumn() is idempotent
        // (checks PRAGMA table_info first) so this is safe to run on every
        // request against a database that already has these columns.
        self::ensureColumn($pdo, 'signals', 'outcome', "TEXT NULL CHECK (outcome IS NULL OR outcome IN ('tp1','sl'))");
        self::ensureColumn($pdo, 'signals', 'resolved_at', 'TEXT NULL');
        self::ensureColumn($pdo, 'signals', 'resolved_price', 'REAL NULL');

        // Automatic-trade lifecycle. `outcome` above carries a CHECK
        // constraint from an earlier release that only allows 'tp1'/'sl',
        // and SQLite cannot drop a column constraint without rebuilding the
        // table (which would cascade-delete signal_events). So the richer
        // states live in `result`, and `outcome` keeps getting the legacy
        // win/loss value so older reads of it stay correct.
        self::ensureColumn($pdo, 'signals', 'leverage', 'REAL NULL');
        self::ensureColumn($pdo, 'signals', 'tier', 'TEXT NULL');
        self::ensureColumn($pdo, 'signals', 'stage', "TEXT NOT NULL DEFAULT 'open'");
        self::ensureColumn($pdo, 'signals', 'result', 'TEXT NULL');
        self::ensureColumn($pdo, 'signals', 'active_stop', 'REAL NULL');
        self::ensureColumn($pdo, 'signals', 'tp1_hit_at', 'TEXT NULL');
        self::ensureColumn($pdo, 'signals', 'tp1_hit_price', 'REAL NULL');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_signals_open ON signals (status, resolved_at)');

        // Four-target trade plan (TP3/TP4) plus the trailing/advisory
        // "trade management" stages: tp2 and tp3 each get their own
        // hit-tracking pair (mirrors tp1's), and advisory_sent_at makes sure
        // the bot ever sends at most one "consider exiting" message per
        // trade instead of repeating itself on every worker pass.
        self::ensureColumn($pdo, 'signals', 'tp4', 'REAL NULL');
        self::ensureColumn($pdo, 'signals', 'tp2_hit_at', 'TEXT NULL');
        self::ensureColumn($pdo, 'signals', 'tp2_hit_price', 'REAL NULL');
        self::ensureColumn($pdo, 'signals', 'tp3_hit_at', 'TEXT NULL');
        self::ensureColumn($pdo, 'signals', 'tp3_hit_price', 'REAL NULL');
        self::ensureColumn($pdo, 'signals', 'peak_price', 'REAL NULL');
        // Two independent one-shot budgets, not one shared between them: a
        // trade that got an early "this is going wrong" warning and then
        // recovered all the way to TP3 still deserves the later "it's
        // stalling before TP4" warning too -- these are different moments
        // in the trade's life, not the same advisory repeated.
        self::ensureColumn($pdo, 'signals', 'advisory_sent_at', 'TEXT NULL');
        self::ensureColumn($pdo, 'signals', 'stall_advisory_sent_at', 'TEXT NULL');

        // Signed 24h move and which scanner bucket picked the symbol up
        // (gainer / loser / liquid). The strategies read both: "a memecoin
        // that has already run" is a precondition of the CHoCH setup, and
        // abs(volatility) alone cannot tell a +40% day from a -40% one.
        self::ensureColumn($pdo, 'symbols', 'change_24h', 'REAL NOT NULL DEFAULT 0');
        self::ensureColumn($pdo, 'symbols', 'bucket', "TEXT NOT NULL DEFAULT 'liquid'");

        self::pruneLogs($pdo);
        self::seedDefaults($pdo);
    }

    private static function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        $stmt = $pdo->query("PRAGMA table_info($table)");
        foreach ($stmt->fetchAll() as $col) {
            if (strcasecmp((string) $col['name'], $column) === 0) {
                return;
            }
        }
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }

    /**
     * Keeps the log table from growing without bound on a bot that is meant
     * to run forever untouched. Cheap enough to run on every migrate()
     * (i.e. every worker invocation) because the created_at index makes it
     * a range delete.
     */
    private static function pruneLogs(PDO $pdo): void
    {
        $days = Config::logRetentionDays();
        if ($days <= 0) {
            return;
        }
        try {
            $pdo->prepare('DELETE FROM logs WHERE created_at < :cutoff')
                ->execute([':cutoff' => date('Y-m-d H:i:s', time() - $days * 86400)]);
        } catch (Throwable) {
            // Never let housekeeping break a boot.
        }
    }

    /** The pre-automation template, recognised so it can be upgraded in place. */
    private const LEGACY_SIGNAL_TEMPLATE = "🔔 سیگنال جدید\n\nنماد: {symbol}\nصرافی: {exchange}\nجهت: {direction}\nتایم‌فریم: {timeframe}\n\nورود: {entry}\nحد ضرر: {sl}\n\nهدف ۱: {tp1}\nهدف ۲: {tp2}\nهدف ۳: {tp3}\n\nامتیاز: {score}\nاستراتژی: {strategy}\n\nدلایل:\n{reasons}";

    /**
     * The first automatic template, superseded by the market-entry one
     * below and recognised here so an untouched copy can be upgraded.
     */
    private const AUTO_SIGNAL_TEMPLATE_V1 = "🚨 سیگنال جدید | {direction_fa}\n\n💎 ارز: {symbol}\n🏦 صرافی: {exchange}\n⏱ تایم‌فریم: {timeframe}\n⚡️ اهرم پیشنهادی: {leverage}\n🏷 نوع ارز: {tier}\n\n📍 نقطه ورود: {entry}\n🛑 حد ضرر: {sl}\n\n🎯 تارگت ۱: {tp1}  ({tp1_profit}% با اهرم)\n🎯 تارگت ۲: {tp2}  ({tp2_profit}% با اهرم)\n\n⚖️ ریسک به ریوارد: {rr}\n📊 امتیاز: {score} | اعتبار: {confidence_fa}\n🔻 فاصله حد ضرر: {risk_pct}%\n\n♻️ بعد از تارگت ۱ حد ضرر روی نقطه ورود منتقل می‌شود (ریسک‌فری) و معامله تا تارگت ۲ ادامه پیدا می‌کند.";

    /**
     * The market-entry template, superseded by the one below once money
     * management was added, and recognised here so an untouched copy can be
     * upgraded.
     */
    private const AUTO_SIGNAL_TEMPLATE_V2 = "🚨 سیگنال جدید | {direction_fa}\n\n💎 ارز: {symbol}\n🏦 صرافی: {exchange}\n⏱ تایم‌فریم: {timeframe}\n⚡️ اهرم پیشنهادی: {leverage}\n🏷 نوع ارز: {tier}\n\n⚡️ نوع ورود: {entry_mode} — همین الان وارد شوید\n📍 قیمت ورود: {entry}\n🛑 حد ضرر: {sl}\n\n🎯 تارگت ۱: {tp1}  ({tp1_profit}% با اهرم)\n🎯 تارگت ۲: {tp2}  ({tp2_profit}% با اهرم)\n\n⚖️ ریسک به ریوارد: {rr}\n📊 امتیاز: {score} | اعتبار: {confidence_fa}\n🔻 فاصله حد ضرر: {risk_pct}%\n\n♻️ بعد از تارگت ۱ حد ضرر روی نقطه ورود منتقل می‌شود (ریسک‌فری) و معامله تا تارگت ۲ ادامه پیدا می‌کند.";

    /** Superseded by AUTO_SIGNAL_TEMPLATE below once the plan grew from two targets to four; kept only so upgradeUntouchedText() can still recognise and migrate an untouched older install. */
    private const AUTO_SIGNAL_TEMPLATE_V3 = "🚨 سیگنال جدید | {direction_fa}\n\n💎 ارز: {symbol}\n🏦 صرافی: {exchange}\n⏱ تایم‌فریم: {timeframe}\n⚡️ اهرم: {leverage}\n🏷 نوع ارز: {tier}\n\n⚡️ نوع ورود: {entry_mode} — همین الان وارد شوید\n📍 قیمت ورود: {entry}\n🛑 حد ضرر: {sl}  ({sl_loss}% با اهرم)\n\n🎯 تارگت ۱: {tp1}  ({tp1_profit}% با اهرم)\n🎯 تارگت ۲: {tp2}  ({tp2_profit}% با اهرم)\n\n💼 مدیریت سرمایه\n• سرمایه مرجع: {balance}\n• ریسک این معامله: {risk_per_trade} یعنی {risk_amount}\n• مارجین پیشنهادی: {margin}\n• حجم پوزیشن: {position_size}\n\n⚖️ ریسک به ریوارد: {rr}\n📊 امتیاز: {score} | اعتبار: {confidence_fa}\n\n♻️ بعد از تارگت ۱ حد ضرر روی نقطه ورود منتقل می‌شود (ریسک‌فری) و معامله تا تارگت ۲ ادامه پیدا می‌کند.";

    /**
     * Default entry-signal caption for the automatic bot.
     *
     * Entries are taken at the price the signal was generated on, so the
     * caption says "market" out loud: a follower who sets a trigger order
     * at {entry} instead simply never gets filled once price has moved on.
     * The money-management block is the position the reader should actually
     * take — sized from the stop, not from the leverage. Four scale-out
     * targets now (was two) — the stop trails up behind each one, so the
     * worst case keeps improving as the trade progresses instead of only
     * ever being "breakeven or the full loss."
     */
    private const AUTO_SIGNAL_TEMPLATE = "🚨 سیگنال جدید | {direction_fa}\n\n💎 ارز: {symbol}\n🏦 صرافی: {exchange}\n⏱ تایم‌فریم: {timeframe}\n⚡️ اهرم: {leverage}\n🏷 نوع ارز: {tier}\n\n⚡️ نوع ورود: {entry_mode} — همین الان وارد شوید\n📍 قیمت ورود: {entry}\n🛑 حد ضرر: {sl}  ({sl_loss}% با اهرم)\n\n🎯 تارگت ۱: {tp1}  ({tp1_profit}% با اهرم)\n🎯 تارگت ۲: {tp2}  ({tp2_profit}% با اهرم)\n🎯 تارگت ۳: {tp3}  ({tp3_profit}% با اهرم)\n🎯 تارگت ۴: {tp4}  ({tp4_profit}% با اهرم)\n\n💼 مدیریت سرمایه\n• سرمایه مرجع: {balance}\n• ریسک این معامله: {risk_per_trade} یعنی {risk_amount}\n• مارجین پیشنهادی: {margin}\n• حجم پوزیشن: {position_size}\n\n⚖️ ریسک به ریوارد: {rr}\n📊 امتیاز: {score} | اعتبار: {confidence_fa}\n\n♻️ بعد از هر تارگت، حد ضرر به سمت تارگت قبلی منتقل می‌شود (ریسک‌فری و سپس تریلینگ) و معامله تا تارگت ۴ ادامه پیدا می‌کند.";

    /** Superseded once TP2 stopped being the final target -- kept only so upgradeUntouchedText() can migrate an untouched older install. */
    private const RESULT_TP2_V1 = "🏆 تارگت ۲ فعال شد | {symbol}\n\n💰 سود نهایی با اهرم {leverage}: {pnl}%\n📈 حرکت قیمت: {move}%\n\n📍 ورود: {entry}\n🎯 خروج: {exit}\n\n✅ معامله با موفقیت بسته شد. ربات به سراغ سیگنال بعدی می‌رود.";

    /**
     * Replaces a seeded default text with a newer one ONLY while it is
     * still byte-identical to the old default and carries no entities —
     * i.e. nobody has customised it. Idempotent.
     */
    private static function upgradeUntouchedText(PDO $pdo, string $key, string $oldDefault, string $newValue, string $now): void
    {
        try {
            $stmt = $pdo->prepare('SELECT text_value, entities FROM text_formats WHERE text_key = :k');
            $stmt->execute([':k' => $key]);
            $row = $stmt->fetch();
            if ($row === false) {
                return;
            }
            $entities = json_decode((string) ($row['entities'] ?? '[]'), true);
            if ((string) $row['text_value'] !== $oldDefault || !empty($entities)) {
                return;
            }
            $pdo->prepare('UPDATE text_formats SET text_value = :v, updated_at = :u WHERE text_key = :k')
                ->execute([':v' => $newValue, ':u' => $now, ':k' => $key]);
        } catch (Throwable) {
            // A failed cosmetic upgrade must never block a boot.
        }
    }

    private static function seedDefaults(PDO $pdo): void
    {
        $now = date('Y-m-d H:i:s');

        // Exchanges
        $exchangeNames = [
            'binance' => 'Binance', 'mexc' => 'MEXC', 'bybit' => 'Bybit', 'okx' => 'OKX',
            'kucoin' => 'KuCoin', 'gate' => 'Gate.io', 'bitget' => 'Bitget', 'htx' => 'HTX',
            'cryptocompare' => 'CryptoCompare',
        ];
        // Wallex was removed; drop any row a previous install left behind so
        // it stops showing in the panel and the health screen.
        $pdo->prepare('DELETE FROM exchanges WHERE name = :n')->execute([':n' => 'wallex']);
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
            'signal_template' => self::AUTO_SIGNAL_TEMPLATE,
            'signal_long' => "🟢 سیگنال LONG برای {symbol}",
            'signal_short' => "🟡 سیگنال SHORT برای {symbol}",
            'error' => "خطایی رخ داد. لطفاً دوباره تلاش کنید.",
            'channel_added' => "✅ کانال با موفقیت اضافه شد.",
            'scanner_status' => "وضعیت اسکنر: {status}",

            // Trade-result announcements. New keys, so an existing install
            // picks them up on its next boot without touching anything the
            // operator may already have customised.
            'result_tp1' => "✅ تارگت ۱ فعال شد | {symbol}\n\n💰 سود با اهرم {leverage}: {pnl}%\n📈 حرکت قیمت: {move}%\n\n📍 ورود: {entry}\n🎯 خروج: {exit}\n\n🛡 معامله ریسک‌فری شد — حد ضرر روی نقطه ورود ({entry}) منتقل شد و از این لحظه این معامله ضرری ندارد.\n🎯 تارگت بعدی: {tp2}",
            'result_tp2' => "🏆 تارگت ۲ فعال شد | {symbol}\n\n💰 سود با اهرم {leverage}: {pnl}%\n📈 حرکت قیمت: {move}%\n\n📍 ورود: {entry}\n🎯 خروج: {exit}\n\n🛡 حد ضرر به سطح تارگت ۱ منتقل شد — از این لحظه سود این معامله قفل شده.\n🎯 تارگت بعدی: {tp3}",
            'result_tp3' => "🏆 تارگت ۳ فعال شد | {symbol}\n\n💰 سود با اهرم {leverage}: {pnl}%\n📈 حرکت قیمت: {move}%\n\n📍 ورود: {entry}\n🎯 خروج: {exit}\n\n🛡 حد ضرر به سطح تارگت ۲ منتقل شد.\n🎯 تارگت نهایی: {tp4}\n\n👀 اگه حرکت اینجا بایسته و برنگرده به تارگت ۴، ممکنه پیشنهاد بدیم زودتر خارج بشی.",
            'result_tp4' => "🏆🏆 تارگت ۴ (نهایی) فعال شد | {symbol}\n\n💰 سود نهایی با اهرم {leverage}: {pnl}%\n📈 حرکت قیمت: {move}%\n\n📍 ورود: {entry}\n🎯 خروج: {exit}\n\n✅ معامله با موفقیت و کامل بسته شد. ربات به سراغ سیگنال بعدی می‌رود.",
            'result_trail' => "🛡 معامله با سود قفل‌شده بسته شد | {symbol}\n\nقیمت بعد از رسیدن به یکی از تارگت‌ها برگشت و به حد ضرر تریلینگ (بالاتر از نقطه ورود) خورد.\n\n📍 ورود: {entry}\n🎯 خروج: {exit}\n💰 نتیجه با اهرم {leverage}: {pnl}%\n\nربات به سراغ سیگنال بعدی می‌رود.",
            'result_sl' => "❌ حد ضرر فعال شد | {symbol}\n\n📉 نتیجه با اهرم {leverage}: {pnl}%\n📈 حرکت قیمت: {move}%\n\n📍 ورود: {entry}\n🛑 خروج: {exit}\n\nمعامله بسته شد. ربات به سراغ سیگنال بعدی می‌رود.",
            'result_be' => "🛡 معامله بدون ضرر بسته شد | {symbol}\n\nقیمت بعد از فعال شدن تارگت ۱ به نقطه ورود برگشت و چون معامله ریسک‌فری شده بود، بدون ضرر بسته شد.\n\n📍 ورود: {entry}\n🎯 خروج: {exit}\n💰 نتیجه با اهرم {leverage}: {pnl}%\n\nربات به سراغ سیگنال بعدی می‌رود.",
        ];
        $stmt = $pdo->prepare(
            'INSERT OR IGNORE INTO text_formats (text_key, text_value, entities, updated_at) VALUES (:k, :v, :e, :u)'
        );
        foreach ($defaults as $key => $value) {
            $stmt->execute([':k' => $key, ':v' => $value, ':e' => json_encode([]), ':u' => $now]);
        }

        // The signal template gained {leverage}, {tp1_profit}, {risk_pct}
        // and friends. INSERT OR IGNORE above cannot update an install that
        // already has a row, so an untouched original default is migrated
        // in place here — a template the operator actually edited (or gave
        // premium-emoji entities to) is left exactly as it is.
        self::upgradeUntouchedText($pdo, 'signal_template', self::LEGACY_SIGNAL_TEMPLATE, self::AUTO_SIGNAL_TEMPLATE, $now);
        self::upgradeUntouchedText($pdo, 'signal_template', self::AUTO_SIGNAL_TEMPLATE_V1, self::AUTO_SIGNAL_TEMPLATE, $now);
        self::upgradeUntouchedText($pdo, 'signal_template', self::AUTO_SIGNAL_TEMPLATE_V2, self::AUTO_SIGNAL_TEMPLATE, $now);
        self::upgradeUntouchedText($pdo, 'signal_template', self::AUTO_SIGNAL_TEMPLATE_V3, self::AUTO_SIGNAL_TEMPLATE, $now);
        self::upgradeUntouchedText($pdo, 'result_tp2', self::RESULT_TP2_V1, $defaults['result_tp2'], $now);

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

    /** @var resource|null */
    private static $stderr = null;
    /** @var resource|null */
    private static $stdout = null;

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

    private const LEVELS = ['debug' => 10, 'info' => 20, 'warning' => 30, 'error' => 40, 'critical' => 50];

    private static ?int $threshold = null;

    /**
     * The bot is designed to run unattended and quiet ("بدون هیچ لاگ خاصی"),
     * so anything below LOG_LEVEL (default: error) is dropped before it
     * reaches either the output streams or the log table.
     */
    private static function enabled(string $level): bool
    {
        if (self::$threshold === null) {
            self::$threshold = self::LEVELS[Config::logMinLevel()] ?? 40;
        }
        return (self::LEVELS[$level] ?? 0) >= self::$threshold;
    }

    private static function write(string $level, string $channel, string $message, array $context): void
    {
        if (!self::enabled($level)) {
            return;
        }
        $context = self::redact($context);
        $message = self::redactString($message);

        $line = sprintf('[%s] [%s] [%s] %s %s', date('Y-m-d H:i:s'), strtoupper($level), $channel, $message, empty($context) ? '' : json_encode($context, JSON_UNESCAPED_UNICODE));

        // STDERR/STDOUT are only predefined under the CLI SAPI (worker.php).
        // bot.php runs as a web request (webhook, via PHP-FPM/mod_php),
        // where referencing those constants directly is a fatal "undefined
        // constant" error -- and since that happened inside this very
        // logger, called from the webhook's own top-level catch block, it
        // was masking whatever the real error actually was. php://stderr
        // and php://stdout streams work identically under both SAPIs.
        self::$stderr ??= @fopen('php://stderr', 'a') ?: null;
        self::$stdout ??= @fopen('php://stdout', 'a') ?: null;
        $stream = ($level === 'error' || $level === 'critical') ? self::$stderr : self::$stdout;
        if ($stream !== null) {
            @fwrite($stream, $line . PHP_EOL);
        }

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
                    'delta' => self::utf16Length((string) $placeholders[$name]) - $utf16TokenLen,
                ];
            }
        }

        // Remap every entity from template coordinates to rendered
        // coordinates in ONE pass, reading token positions that always stay
        // in template coordinates.
        //
        // The previous version walked the tokens in an outer loop and
        // mutated entity offsets as it went, then compared those already-
        // shifted offsets against the NEXT token's untouched template
        // coordinates. With placeholders that shrink the text — and most of
        // them do, "{leverage}" is ten units and "150x" is four — an entity
        // could slide backwards past a later token's start and get treated
        // as overlapping it, which clamped it onto the wrong text or
        // collapsed it to nothing. On a template where the admin had quoted
        // each line and put a premium emoji on it, that silently moved the
        // quotes off their lines and dropped emoji entities, so the message
        // arrived unformatted.
        $workingEntities = [];
        foreach ($entities as $entity) {
            $start = (int) ($entity['offset'] ?? 0);
            $length = (int) ($entity['length'] ?? 0);
            $newStart = self::mapOffset($tokens, $start, false);
            $newEnd = self::mapOffset($tokens, $start + $length, true);
            $entity['offset'] = $newStart;
            $entity['length'] = max(0, $newEnd - $newStart);
            $workingEntities[] = $entity;
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

    /**
     * Maps one template offset to its rendered offset.
     *
     * @param array<int,array<string,mixed>> $tokens ordered, in template coordinates
     * @param bool $isEnd treat the position as an entity's end, so a position
     *                    that lands inside a replaced token snaps past the
     *                    replacement rather than in front of it
     */
    private static function mapOffset(array $tokens, int $pos, bool $isEnd): int
    {
        $shift = 0;
        foreach ($tokens as $token) {
            if ($token['utf16_end'] <= $pos) {
                $shift += $token['delta'];
                continue;
            }
            if ($token['utf16_start'] < $pos && $pos < $token['utf16_end']) {
                // Inside a replaced placeholder: snap to an edge of the
                // replacement instead of landing in the middle of a value.
                return $isEnd
                    ? $token['utf16_start'] + $shift + self::utf16Length($token['value'])
                    : $token['utf16_start'] + $shift;
            }
            // Tokens are in ascending order, so everything from here on
            // starts at or after $pos and cannot move it.
            break;
        }
        return $pos + $shift;
    }

    // ------------------------------------------------------------------
    // Delivery fallback
    // ------------------------------------------------------------------

    /**
     * Removes custom_emoji entities, leaving the fallback emoji characters
     * in place. Used to re-send a message that Telegram rejected because
     * the bot is not allowed to use custom emoji in that chat.
     *
     * @param array<int,array<string,mixed>> $entities
     * @return array<int,array<string,mixed>>
     */
    public static function stripCustomEmoji(array $entities): array
    {
        return array_values(array_filter(
            $entities,
            static fn($e) => ($e['type'] ?? '') !== 'custom_emoji'
        ));
    }

    /** @param array<int,array<string,mixed>> $entities */
    public static function hasCustomEmoji(array $entities): bool
    {
        foreach ($entities as $e) {
            if (($e['type'] ?? '') === 'custom_emoji') {
                return true;
            }
        }
        return false;
    }

    /**
     * Same Fragment-username restriction as message-body custom emoji
     * (see stripCustomEmoji above), just for a button's icon_custom_emoji_id
     * instead of a text entity.
     *
     * @param array{inline_keyboard: array<int, array<int, array<string,mixed>>>} $keyboard
     */
    public static function hasButtonEmojiIcon(array $keyboard): bool
    {
        foreach ($keyboard['inline_keyboard'] ?? [] as $row) {
            foreach ($row as $button) {
                if (!empty($button['icon_custom_emoji_id'])) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Removes icon_custom_emoji_id from every button, leaving text/url/style
     * untouched. Used to re-send a message Telegram rejected because the
     * bot isn't allowed to put a custom emoji icon on a button in that chat.
     *
     * @param array{inline_keyboard: array<int, array<int, array<string,mixed>>>} $keyboard
     * @return array{inline_keyboard: array<int, array<int, array<string,mixed>>>}
     */
    public static function stripButtonEmojiIcons(array $keyboard): array
    {
        $keyboard['inline_keyboard'] = array_map(
            static function (array $row): array {
                return array_map(static function (array $button): array {
                    unset($button['icon_custom_emoji_id']);
                    return $button;
                }, $row);
            },
            $keyboard['inline_keyboard'] ?? []
        );
        return $keyboard;
    }
}
