<?php
/**
 * ربات سیگنال بازار — مستقل از ربات اصلی (توکن/ادمین جدا).
 *
 * معماری:
 *   - سکرت‌ها (توکن، آیدی ادمین، کلید کرون) از config.local.signals.php کنار
 *     همین فایل خونده می‌شن — همون الگوی config.local.php ربات اصلی، ولی
 *     فایل جدا تا دو ربات قاطی نشن. این فایل تو گیت نیست (.gitignore).
 *   - وضعیت/تنظیمات (کانال، شمارنده‌ی روزانه، کول‌داون هر نماد) تو
 *     signals_data/*.json ذخیره می‌شه — بدون وابستگی به دیتابیس ربات اصلی.
 *   - یک URL کرون (?cron=KEY) هر بار که صدا زده بشه یک دور اسکن بازار
 *     می‌کنه؛ باید هر چند دقیقه (مثلاً هر ۱۰-۱۵ دقیقه) با cron واقعیِ سرور
 *     صدا زده بشه — دقیقاً همون الگوی «bot_master_membership.php?cron=KEY»
 *     که تو ربات اصلی هست.
 *   - وبهوک (بدون ?cron=) پیام‌های ادمین رو می‌گیره: /status، /setchannel،
 *     /setmax — برای تنظیم بدون دست‌زدن به کد.
 *
 * راه‌اندازی روی سرور:
 *   ۱) کنار همین فایل، config.local.signals.php بسازید:
 *        <?php
 *        define('SIGNALS_BOT_TOKEN', 'توکن از BotFather');
 *        define('SIGNALS_ADMIN_ID', آیدی‌عددی‌شما);
 *        define('SIGNALS_CRON_KEY', 'یک رشته‌ی تصادفیِ طولانی و مخفی');
 *   ۲) ربات رو با دسترسیِ ارسال پیام به کانال هدف، ادمینِ اون کانال کنید.
 *   ۳) وبهوک رو ست کنید (setWebhook با آدرس همین فایل).
 *   ۴) یک cron job روی سرور، هر ۱۰-۱۵ دقیقه، همین آدرس رو با ?cron=KEY صدا بزنه.
 *
 * ⚠️ منطق سیگنال (تشخیص ساختار/نواحی) از پاین‌اسکریپتِ اندیکاتور اقتباس
 * شده ولی از صفر با PHP روی کندل‌های خام صرافی نوشته شده — نسخه‌ی اول است؛
 * از همینجا نمی‌شود API صرافی‌ها را زنده تست کرد (شبکه‌ی این محیط به این
 * صرافی‌ها بسته است)، پس حتماً روی سرور واقعی امتحان و در صورت نیاز اصلاح شود.
 */

if (is_file(__DIR__ . '/config.local.signals.php')) require_once __DIR__ . '/config.local.signals.php';

if (!defined('SIGNALS_BOT_TOKEN')) define('SIGNALS_BOT_TOKEN', (string)getenv('SIGNALS_BOT_TOKEN'));
if (!defined('SIGNALS_ADMIN_ID'))  define('SIGNALS_ADMIN_ID', (int)getenv('SIGNALS_ADMIN_ID'));
if (!defined('SIGNALS_CRON_KEY'))  define('SIGNALS_CRON_KEY', (string)getenv('SIGNALS_CRON_KEY'));

if (SIGNALS_BOT_TOKEN === '' || SIGNALS_ADMIN_ID <= 0 || SIGNALS_CRON_KEY === '') {
    http_response_code(500);
    exit("SIGNALS_BOT_TOKEN / SIGNALS_ADMIN_ID / SIGNALS_CRON_KEY تنظیم نشده.\n" .
         "کنار همین فایل، config.local.signals.php بسازید:\n\n" .
         "<?php\n" .
         "define('SIGNALS_BOT_TOKEN', 'توکن از BotFather');\n" .
         "define('SIGNALS_ADMIN_ID', آیدی‌عددیِ‌شما);\n" .
         "define('SIGNALS_CRON_KEY', 'یک رشته‌ی تصادفیِ طولانی و مخفی');\n");
}

define('SIGNALS_DATA_DIR', __DIR__ . '/signals_data');
if (!is_dir(SIGNALS_DATA_DIR)) @mkdir(SIGNALS_DATA_DIR, 0775, true);

// ============================================================
// 🧰 ابزارهای پایه — ذخیره‌سازی JSON، HTTP، تلگرام
// ============================================================

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function sigLoad($name, $default = []) {
    $f = SIGNALS_DATA_DIR . '/' . $name . '.json';
    if (!is_file($f)) return $default;
    $j = json_decode((string)@file_get_contents($f), true);
    return is_array($j) ? $j : $default;
}

function sigSave($name, $data) {
    $f = SIGNALS_DATA_DIR . '/' . $name . '.json';
    $tmp = $f . '.' . getmypid() . '.tmp';
    @file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    @rename($tmp, $f);
}

/** خواندن+تغییر+نوشتن اتمی — جلوی مسابقه‌ی همزمانِ کرون و وبهوک را می‌گیرد. */
function sigMutate($name, callable $fn) {
    $lockFp = @fopen(SIGNALS_DATA_DIR . '/.' . $name . '.lock', 'c');
    if ($lockFp) flock($lockFp, LOCK_EX);
    $data = sigLoad($name);
    $fn($data);
    sigSave($name, $data);
    if ($lockFp) { flock($lockFp, LOCK_UN); fclose($lockFp); }
    return $data;
}

function sigCfgDefaults() {
    return [
        'channel'              => '@autoNikTo',
        'max_per_day'          => 12,
        'cooldown_hours'       => 6,
        'timeframe'            => '1h',
        'trend_len'            => 50,
        'atr_len'              => 14,
        'swing_len'            => 5,
        'stop_atr_mult'        => 1.5,
        'zone_touch_lookback'  => 20,
        'symbols_per_exchange' => 40,
        'exchanges'            => ['binance' => true, 'mexc' => true, 'kucoin' => true, 'wallex' => true],
    ];
}

function sigCfg() {
    static $out = null;
    if ($out !== null) return $out;
    $out = array_replace_recursive(sigCfgDefaults(), sigLoad('config'));
    return $out;
}

function sigCfgSet(callable $fn) {
    sigMutate('config', function (&$c) use ($fn) {
        if (!$c) $c = sigCfgDefaults();
        $fn($c);
    });
}

function sigHttpJson($url, $timeout = 10) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(6, $timeout),
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SignalBot/1.0)',
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    if (!is_string($body) || $body === '') return null;
    $j = json_decode($body, true);
    return is_array($j) ? $j : null;
}

function sigTg($method, $data = [], $timeout = 20) {
    $ch = curl_init('https://api.telegram.org/bot' . SIGNALS_BOT_TOKEN . '/' . $method);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(3, (int)$timeout),
        CURLOPT_CONNECTTIMEOUT => min(10, max(3, (int)$timeout)),
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    if ($res === false) return ['ok' => false, 'description' => 'curl error'];
    $out = json_decode($res, true);
    return is_array($out) ? $out : ['ok' => false, 'description' => 'bad response'];
}

/**
 * رنگی‌کردنِ دکمه (فیلد "style": primary/success/danger) و ایموجی پریمیوم
 * روی دکمه (icon_custom_emoji_id) — هر دو از Bot API 9.4 به بعد.
 * توجه: طبق مستندات رسمی، icon_custom_emoji_id فقط تو چتِ خصوصی/گروه/سوپرگروه
 * تضمین‌شده (نه صراحتاً «کانال») — اگر رو دکمه‌ی پیامِ کانال کار نکرد، جای
 * نگرانی نیست: پیام با استایل رنگیِ دکمه (که این محدودیت را ندارد) و ایموجیِ
 * پریمیومِ داخلِ متنِ پیام (که در ادامه با <tg-emoji> می‌آید) باز هم درست است.
 */
function sigStripStyles($markup) {
    if (!is_array($markup)) return $markup;
    foreach ($markup['inline_keyboard'] ?? [] as $i => $row)
        foreach ($row as $j => $btn)
            unset($markup['inline_keyboard'][$i][$j]['style'], $markup['inline_keyboard'][$i][$j]['icon_custom_emoji_id']);
    return $markup;
}

function sigSendMessage($chatId, $text, $markup = null) {
    $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => 'true'];
    if ($markup) $data['reply_markup'] = json_encode($markup);
    $res = sigTg('sendMessage', $data);
    if (empty($res['ok']) && $markup) {
        $d = strtolower((string)($res['description'] ?? ''));
        if (str_contains($d, 'style') || str_contains($d, 'icon_custom_emoji_id')) {
            $data['reply_markup'] = json_encode(sigStripStyles($markup));
            $res = sigTg('sendMessage', $data);
        }
    }
    return $res;
}

/** ایموجی پریمیوم داخل متنِ پیام — همون الگوی pxEm() در prices.php. */
function sigEmoji($id, $fallback) {
    $id = trim((string)$id);
    if ($id === '' || !ctype_digit($id)) return $fallback;
    return '<tg-emoji emoji-id="' . $id . '">' . $fallback . '</tg-emoji>';
}

// ============================================================
// 📡 کلاینت‌های صرافی — فقط جفت‌های USDT، هرکدام: فهرست نمادها + کندل
// ============================================================

function sigBinanceSymbols($limit) {
    $j = sigHttpJson('https://api.binance.com/api/v3/ticker/24hr');
    if (!is_array($j)) return [];
    $rows = [];
    foreach ($j as $row) {
        $sym = (string)($row['symbol'] ?? '');
        if (!str_ends_with($sym, 'USDT')) continue;
        if (preg_match('/(UP|DOWN|BULL|BEAR)USDT$/', $sym)) continue; // توکن‌های اهرمی، نویز محضن
        $rows[] = ['symbol' => $sym, 'vol' => (float)($row['quoteVolume'] ?? 0)];
    }
    usort($rows, fn($a, $b) => $b['vol'] <=> $a['vol']);
    return array_slice(array_column($rows, 'symbol'), 0, $limit);
}

function sigBinanceKlines($symbol, $interval, $limit) {
    $j = sigHttpJson('https://api.binance.com/api/v3/klines?symbol=' . urlencode($symbol) . '&interval=' . urlencode($interval) . '&limit=' . (int)$limit);
    if (!is_array($j)) return [];
    $out = [];
    foreach ($j as $row) {
        if (!is_array($row) || count($row) < 5) continue;
        $out[] = ['t' => (int)((float)$row[0] / 1000), 'o' => (float)$row[1], 'h' => (float)$row[2], 'l' => (float)$row[3], 'c' => (float)$row[4]];
    }
    return $out;
}

/** MEXC اسپات — API سازگار با بایننس (همون شکل پاسخ)، فقط نامِ تایم‌فریمِ ۱ساعته 60m است. */
function sigMexcInterval($tf) {
    return $tf === '1h' ? '60m' : $tf;
}

function sigMexcSymbols($limit) {
    $j = sigHttpJson('https://api.mexc.com/api/v3/ticker/24hr');
    if (!is_array($j)) return [];
    $rows = [];
    foreach ($j as $row) {
        $sym = (string)($row['symbol'] ?? '');
        if (!str_ends_with($sym, 'USDT')) continue;
        $rows[] = ['symbol' => $sym, 'vol' => (float)($row['quoteVolume'] ?? 0)];
    }
    usort($rows, fn($a, $b) => $b['vol'] <=> $a['vol']);
    return array_slice(array_column($rows, 'symbol'), 0, $limit);
}

function sigMexcKlines($symbol, $interval, $limit) {
    $j = sigHttpJson('https://api.mexc.com/api/v3/klines?symbol=' . urlencode($symbol) . '&interval=' . urlencode(sigMexcInterval($interval)) . '&limit=' . (int)$limit);
    if (!is_array($j)) return [];
    $out = [];
    foreach ($j as $row) {
        if (!is_array($row) || count($row) < 5) continue;
        $out[] = ['t' => (int)((float)$row[0] / 1000), 'o' => (float)$row[1], 'h' => (float)$row[2], 'l' => (float)$row[3], 'c' => (float)$row[4]];
    }
    return $out;
}

/** کوکوین — نمادها با خط تیره (BTC-USDT)، کندل‌ها [زمان،اُپن،کلوز،های،لو،...] و نزولی-زمانی هستند. */
function sigKucoinInterval($tf) {
    return $tf === '1h' ? '1hour' : $tf;
}

function sigKucoinSymbols($limit) {
    $j = sigHttpJson('https://api.kucoin.com/api/v1/market/allTickers');
    $rows = $j['data']['ticker'] ?? null;
    if (!is_array($rows)) return [];
    $out = [];
    foreach ($rows as $row) {
        $sym = (string)($row['symbol'] ?? '');
        if (!str_ends_with($sym, '-USDT')) continue;
        $out[] = ['symbol' => $sym, 'vol' => (float)($row['volValue'] ?? 0)];
    }
    usort($out, fn($a, $b) => $b['vol'] <=> $a['vol']);
    return array_slice(array_column($out, 'symbol'), 0, $limit);
}

function sigKucoinKlines($symbol, $interval, $limit) {
    $secondsPerBar = 3600; // فقط ۱ساعته پوشش داده شده؛ برای تایم‌فریم دیگر این نگاشت را کامل کنید
    $endAt = time();
    $startAt = $endAt - $secondsPerBar * ($limit + 5);
    $url = 'https://api.kucoin.com/api/v1/market/candles?symbol=' . urlencode($symbol)
         . '&type=' . urlencode(sigKucoinInterval($interval))
         . '&startAt=' . $startAt . '&endAt=' . $endAt;
    $j = sigHttpJson($url);
    $rows = $j['data'] ?? null;
    if (!is_array($rows)) return [];
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row) || count($row) < 5) continue;
        // ترتیب کوکوین: [زمان, اُپن, کلوز, های, لو, ولوم, ترنوور]
        $out[] = ['t' => (int)$row[0], 'o' => (float)$row[1], 'c' => (float)$row[2], 'h' => (float)$row[3], 'l' => (float)$row[4]];
    }
    usort($out, fn($a, $b) => $a['t'] <=> $b['t']); // کوکوین نزولی می‌دهد؛ صعودی‌اش می‌کنیم
    return $out;
}

/**
 * والکس — همون endpoint که prices.php استفاده می‌کند برای فهرستِ نمادها.
 * ⚠️ کندلِ تاریخی: endpoint استاندارد UDF فرض شده (رایج در صرافی‌های ایرانی)
 * ولی از این محیط قابل تست زنده نیست — اگر جواب نداد، این یک تابع را با
 * endpoint واقعیِ کندلِ والکس عوض کنید؛ بقیه‌ی موتور دست‌نخورده می‌ماند.
 */
function sigWallexSymbols($limit) {
    $j = sigHttpJson('https://api.wallex.ir/v1/markets');
    $syms = $j['result']['symbols'] ?? ($j['symbols'] ?? null);
    if (!is_array($syms)) return [];
    $out = [];
    foreach ($syms as $key => $row) {
        if (!is_array($row)) continue;
        $sym = strtoupper(trim((string)($row['symbol'] ?? $key)));
        if (!str_ends_with($sym, 'USDT')) continue;
        $st = is_array($row['stats'] ?? null) ? $row['stats'] : $row;
        $out[] = ['symbol' => $sym, 'vol' => (float)($st['24h_volume'] ?? $st['quoteVolume'] ?? 0)];
    }
    usort($out, fn($a, $b) => $b['vol'] <=> $a['vol']);
    return array_slice(array_column($out, 'symbol'), 0, $limit);
}

function sigWallexKlines($symbol, $interval, $limit) {
    $resolution = $interval === '1h' ? '60' : $interval;
    $to = time();
    $from = $to - 3600 * ($limit + 5);
    $url = 'https://api.wallex.ir/v1/udf/history?symbol=' . urlencode($symbol) . '&resolution=' . urlencode($resolution) . '&from=' . $from . '&to=' . $to;
    $j = sigHttpJson($url);
    if (!is_array($j) || ($j['s'] ?? '') !== 'ok' || !isset($j['t'])) return [];
    $out = [];
    $n = count($j['t']);
    for ($i = 0; $i < $n; $i++) {
        $out[] = ['t' => (int)$j['t'][$i], 'o' => (float)$j['o'][$i], 'h' => (float)$j['h'][$i], 'l' => (float)$j['l'][$i], 'c' => (float)$j['c'][$i]];
    }
    return $out;
}

function sigExchangeSymbols($ex, $limit) {
    switch ($ex) {
        case 'binance': return sigBinanceSymbols($limit);
        case 'mexc':    return sigMexcSymbols($limit);
        case 'kucoin':  return sigKucoinSymbols($limit);
        case 'wallex':  return sigWallexSymbols($limit);
    }
    return [];
}

function sigFetchKlines($ex, $symbol, $interval, $limit) {
    switch ($ex) {
        case 'binance': return sigBinanceKlines($symbol, $interval, $limit);
        case 'mexc':    return sigMexcKlines($symbol, $interval, $limit);
        case 'kucoin':  return sigKucoinKlines($symbol, $interval, $limit);
        case 'wallex':  return sigWallexKlines($symbol, $interval, $limit);
    }
    return [];
}

// ============================================================
// 📐 هسته‌ی محاسبات — ATR، میانگین متحرک، سقف/کفِ چرخشی (پیوت)
// ============================================================

function sigAtr(array $c, $len) {
    $n = count($c);
    $tr = [];
    for ($i = 0; $i < $n; $i++) {
        $tr[] = $i === 0
            ? $c[$i]['h'] - $c[$i]['l']
            : max($c[$i]['h'] - $c[$i]['l'], abs($c[$i]['h'] - $c[$i - 1]['c']), abs($c[$i]['l'] - $c[$i - 1]['c']));
    }
    $out = array_fill(0, $n, null);
    $sum = 0;
    for ($i = 0; $i < $n; $i++) {
        $sum += $tr[$i];
        if ($i >= $len) $sum -= $tr[$i - $len];
        if ($i >= $len - 1) $out[$i] = $sum / $len;
    }
    return $out;
}

function sigSma(array $vals, $len) {
    $n = count($vals);
    $out = array_fill(0, $n, null);
    $sum = 0;
    for ($i = 0; $i < $n; $i++) {
        $sum += $vals[$i];
        if ($i >= $len) $sum -= $vals[$i - $len];
        if ($i >= $len - 1) $out[$i] = $sum / $len;
    }
    return $out;
}

/** پیوت های/لو — همون ta.pivothigh/pivotlow: len کندل قبل و بعد باید پایین‌تر/بالاتر باشند. */
function sigPivotHigh(array $c, $len) {
    $n = count($c);
    $out = array_fill(0, $n, null);
    for ($i = $len; $i < $n - $len; $i++) {
        $v = $c[$i]['h'];
        $ok = true;
        for ($k = 1; $k <= $len && $ok; $k++) {
            if ($c[$i - $k]['h'] > $v || $c[$i + $k]['h'] > $v) $ok = false;
        }
        if ($ok) $out[$i] = $v;
    }
    return $out;
}

function sigPivotLow(array $c, $len) {
    $n = count($c);
    $out = array_fill(0, $n, null);
    for ($i = $len; $i < $n - $len; $i++) {
        $v = $c[$i]['l'];
        $ok = true;
        for ($k = 1; $k <= $len && $ok; $k++) {
            if ($c[$i - $k]['l'] < $v || $c[$i + $k]['l'] < $v) $ok = false;
        }
        if ($ok) $out[$i] = $v;
    }
    return $out;
}

// ============================================================
// 🎯 موتور سیگنال — روند + شکست ساختار + برخورد به ناحیه (همون سه‌تایید
// که تو خودِ اندیکاتور پاین‌اسکریپت بعد از چند بار اصلاح به‌اش رسیدیم)
// ============================================================

function sigEvaluateSymbol(array $candles, array $cfg) {
    $n = count($candles);
    $need = max($cfg['trend_len'], $cfg['atr_len']) + $cfg['swing_len'] * 2 + 5;
    if ($n < $need) return null;

    $closes   = array_column($candles, 'c');
    $atr      = sigAtr($candles, $cfg['atr_len']);
    $trendMa  = sigSma($closes, $cfg['trend_len']);
    $pivHigh  = sigPivotHigh($candles, $cfg['swing_len']);
    $pivLow   = sigPivotLow($candles, $cfg['swing_len']);

    $last  = $n - 1;
    $close = $candles[$last]['c'];
    $atrNow = $atr[$last];
    $trendNow = $trendMa[$last];
    if ($atrNow === null || $atrNow <= 0 || $trendNow === null) return null;

    // آخرین سقف/کفِ چرخشیِ تاییدشده قبل از کندلِ الان
    $swingHigh = null; $swingLow = null;
    for ($i = $last - $cfg['swing_len']; $i >= 0; $i--) {
        if ($swingHigh === null && $pivHigh[$i] !== null) $swingHigh = $pivHigh[$i];
        if ($swingLow  === null && $pivLow[$i]  !== null) $swingLow  = $pivLow[$i];
        if ($swingHigh !== null && $swingLow !== null) break;
    }
    if ($swingHigh === null || $swingLow === null) return null;

    $trendUp = $close > $trendNow;
    $trendDn = $close < $trendNow;
    $prevClose = $candles[$last - 1]['c'];

    // ۱) تاییدیه‌ی اول: شکستِ ساختار هم‌جهت با روند
    $bullBOS = $trendUp && $close > $swingHigh && $prevClose <= $swingHigh;
    $bearBOS = $trendDn && $close < $swingLow  && $prevClose >= $swingLow;
    if (!$bullBOS && !$bearBOS) return null;

    // ۲) تاییدیه‌ی دوم: طیِ چند کندلِ اخیر، قیمت به ناحیه‌ی مقابل (POI) برخورد کرده باشد
    $buf = $atrNow * 0.25;
    $lookback = min($last, (int)$cfg['zone_touch_lookback']);
    $touchedDemand = false; $touchedSupply = false;
    for ($i = $last - $lookback; $i < $last; $i++) {
        if ($i < 0) continue;
        if ($candles[$i]['l'] <= $swingLow + $buf)  $touchedDemand = true;
        if ($candles[$i]['h'] >= $swingHigh - $buf) $touchedSupply = true;
    }

    if ($bullBOS && $touchedDemand) {
        $stop = $close - $atrNow * $cfg['stop_atr_mult'];
        $risk = $close - $stop;
        if ($risk <= 0) return null;
        return ['side' => 'BUY', 'entry' => $close, 'stop' => $stop, 'risk' => $risk, 'atr' => $atrNow];
    }
    if ($bearBOS && $touchedSupply) {
        $stop = $close + $atrNow * $cfg['stop_atr_mult'];
        $risk = $stop - $close;
        if ($risk <= 0) return null;
        return ['side' => 'SELL', 'entry' => $close, 'stop' => $stop, 'risk' => $risk, 'atr' => $atrNow];
    }
    return null;
}

// ============================================================
// ✉️ فرمت پیام — ایموجی پریمیوم در متن + دکمه‌ی رنگیِ شیشه‌ای
// ============================================================

function sigFmtNum($v) {
    $s = number_format((float)$v, 8, '.', '');
    $s = rtrim(rtrim($s, '0'), '.');
    return $s === '' ? '0' : $s;
}

function sigFormatMessage($ex, $symbol, array $sig, array $cfg) {
    $isBuy = $sig['side'] === 'BUY';
    $dir   = $isBuy ? sigEmoji($cfg['emoji']['buy'] ?? '', '🟢') : sigEmoji($cfg['emoji']['sell'] ?? '', '🔴');
    $r     = $sig['risk'];
    $sign  = $isBuy ? 1 : -1;

    $lines = [];
    $lines[] = "{$dir} <b>" . ($isBuy ? 'خرید / LONG' : 'فروش / SHORT') . "</b> — <code>" . h($symbol) . "</code> (" . h($ex) . ")";
    $lines[] = '';
    $lines[] = sigEmoji($cfg['emoji']['entry'] ?? '', '🎯') . ' ورود: <code>' . sigFmtNum($sig['entry']) . '</code>';
    $lines[] = sigEmoji($cfg['emoji']['stop'] ?? '', '🛑') . ' استاپ: <code>' . sigFmtNum($sig['stop']) . '</code>';
    for ($i = 1; $i <= 4; $i++) {
        $tp = $sig['entry'] + $sign * $r * $i;
        $lines[] = sigEmoji($cfg['emoji']['target'] ?? '', '🎯') . " تارگت {$i}: <code>" . sigFmtNum($tp) . '</code>';
    }
    $text = implode("\n", $lines);

    $style = $isBuy ? 'success' : 'danger';
    $btn = ['text' => ($isBuy ? '🟢 لانگ' : '🔴 شورت') . ' گرفته شد', 'callback_data' => 'sig_ack:' . substr(md5($ex . $symbol . time()), 0, 8), 'style' => $style];
    $iconId = trim((string)($cfg['emoji']['btn'] ?? ''));
    if ($iconId !== '' && ctype_digit($iconId)) $btn['icon_custom_emoji_id'] = $iconId;

    return [$text, ['inline_keyboard' => [[$btn]]]];
}

// ============================================================
// 🔁 یک دورِ اسکنِ کاملِ بازار
// ============================================================

function sigRunScanCycle() {
    $cfg = sigCfg();
    $today = date('Y-m-d');
    $state = sigLoad('state', ['day' => $today, 'count' => 0, 'last' => []]);
    if (($state['day'] ?? '') !== $today) $state = ['day' => $today, 'count' => 0, 'last' => []];

    if ($state['count'] >= $cfg['max_per_day']) return;

    $candidates = [];
    foreach ($cfg['exchanges'] as $ex => $on) {
        if (!$on) continue;
        foreach (sigExchangeSymbols($ex, (int)$cfg['symbols_per_exchange']) as $sym) {
            $candidates[] = ['ex' => $ex, 'symbol' => $sym];
        }
    }

    $found = [];
    foreach ($candidates as $cand) {
        $key = $cand['ex'] . ':' . $cand['symbol'];
        $lastAt = $state['last'][$key] ?? 0;
        if (time() - $lastAt < $cfg['cooldown_hours'] * 3600) continue;

        $candles = sigFetchKlines($cand['ex'], $cand['symbol'], $cfg['timeframe'], 200);
        if (count($candles) < 60) continue;

        $sig = sigEvaluateSymbol($candles, $cfg);
        if ($sig) $found[] = $cand + ['sig' => $sig];
    }

    // شکستِ قوی‌تر (نسبت به ATR خودش) اول فرستاده شود
    usort($found, fn($a, $b) => ($b['sig']['risk'] / $b['sig']['atr']) <=> ($a['sig']['risk'] / $a['sig']['atr']));

    foreach ($found as $cand) {
        if ($state['count'] >= $cfg['max_per_day']) break;
        [$text, $markup] = sigFormatMessage($cand['ex'], $cand['symbol'], $cand['sig'], $cfg);
        $res = sigSendMessage($cfg['channel'], $text, $markup);
        $key = $cand['ex'] . ':' . $cand['symbol'];
        $state['last'][$key] = time();
        if (!empty($res['ok'])) $state['count']++;
    }
    sigSave('state', $state);
}

// ============================================================
// 🚪 ورودی‌ها — کرون (اسکن) و وبهوک (دستورهای ادمین)
// ============================================================

if (isset($_GET['cron'])) {
    if (!hash_equals(SIGNALS_CRON_KEY, (string)$_GET['cron'])) { http_response_code(403); exit('forbidden'); }
    sigRunScanCycle();
    echo 'ok';
    exit;
}

$update = json_decode((string)file_get_contents('php://input'), true);
if (is_array($update)) {
    $msg = $update['message'] ?? null;
    $fromId = (int)($msg['from']['id'] ?? 0);
    if ($msg && $fromId === SIGNALS_ADMIN_ID) {
        $chatId = $msg['chat']['id'];
        $text = trim((string)($msg['text'] ?? ''));
        $cfg = sigCfg();

        if ($text === '/start' || $text === '/status') {
            $state = sigLoad('state', ['day' => '', 'count' => 0]);
            $today = date('Y-m-d');
            $count = ($state['day'] ?? '') === $today ? (int)($state['count'] ?? 0) : 0;
            $exList = implode('، ', array_keys(array_filter($cfg['exchanges']))) ?: '—';
            sigSendMessage($chatId,
                "📡 <b>وضعیتِ ربات سیگنال</b>\n\n" .
                'کانال: <code>' . h($cfg['channel']) . "</code>\n" .
                "امروز فرستاده‌شده: <b>{$count}</b> / {$cfg['max_per_day']}\n" .
                "تایم‌فریم: {$cfg['timeframe']}\n" .
                'صرافی‌های فعال: ' . h($exList) . "\n\n" .
                "/setchannel @channel — تغییرِ کانال\n" .
                '/setmax 12 — سقفِ سیگنالِ روزانه'
            );
        } elseif (str_starts_with($text, '/setchannel')) {
            $val = trim(substr($text, strlen('/setchannel')));
            if ($val === '') {
                sigSendMessage($chatId, 'مثال: <code>/setchannel @channelname</code> یا آیدیِ عددیِ کانال');
            } else {
                sigCfgSet(function (&$c) use ($val) { $c['channel'] = $val; });
                sigSendMessage($chatId, '✅ کانالِ سیگنال‌ها شد: <code>' . h($val) . '</code>');
            }
        } elseif (str_starts_with($text, '/setmax')) {
            $val = (int)trim(substr($text, strlen('/setmax')));
            if ($val > 0) {
                sigCfgSet(function (&$c) use ($val) { $c['max_per_day'] = $val; });
                sigSendMessage($chatId, "✅ سقفِ سیگنالِ روزانه شد: {$val}");
            } else {
                sigSendMessage($chatId, 'مثال: <code>/setmax 12</code>');
            }
        }
    }
    // callback_data (دکمه‌ی «گرفته شد») فعلاً فقط تاییدِ خودکار می‌گیرد، بدون منطقِ اضافه
    $cb = $update['callback_query'] ?? null;
    if ($cb && (int)($cb['from']['id'] ?? 0) === SIGNALS_ADMIN_ID) {
        sigTg('answerCallbackQuery', ['callback_query_id' => $cb['id']]);
    }
}
