<?php

if (!defined('SV_OPEN_MAX')) define('SV_OPEN_MAX', 10);
if (!defined('SV_DEFAULT_URL')) define('SV_DEFAULT_URL', 'https://justanotherpanel.com/api/v2');
if (!defined('SV_REFILL_GAP')) define('SV_REFILL_GAP', 86400);

function svApps() {
    return [
        'tg' => ['name' => 'خدمات تلگرام',    'short' => 'تلگرام',    'emoji' => '✈️', 'key' => 'tgs', 'ui' => 'open_tgs'],
        'ig' => ['name' => 'خدمات اینستاگرام', 'short' => 'اینستاگرام', 'emoji' => '📸', 'key' => 'igs', 'ui' => 'open_igs'],
    ];
}

function svAppOfKey($key) {
    foreach (svApps() as $app => $a) if ($a['key'] === $key) return $app;
    return '';
}

function svCats($app) {
    if ($app === 'ig') return [
        'followers' => ['فالوور',        'users'],
        'likes'     => ['لایک',          'heart'],
        'views'     => ['ویو و ریلز',     'play'],
        'comments'  => ['کامنت',         'chat'],
        'story'     => ['استوری',        'ring'],
        'saves'     => ['سیو و اشتراک',   'bookmark'],
        'other'     => ['سایر خدمات',    'spark'],
    ];
    return [
        'members'   => ['ممبر',           'users'],
        'views'     => ['بازدید پست',      'eye'],
        'reactions' => ['ری‌اکشن',         'heart'],
        'premium'   => ['پریمیوم و بوست',  'star'],
        'votes'     => ['رای نظرسنجی',     'chart'],
        'comments'  => ['کامنت',           'chat'],
        'other'     => ['سایر خدمات',      'spark'],
    ];
}

function svCurrencies() {
    return [
        'toman'    => 'تومان',
        'rial'     => 'ریال',
        'usd_live' => 'دلار — با قیمتِ لحظه‌ایِ تتر',
        'usd'      => 'دلار — با نرخِ ثابتی که خودم می‌نویسم',
    ];
}

function svDefaults() {
    return [
        'url'     => SV_DEFAULT_URL,
        'key'     => '',
        'cur'     => 'usd_live',
        'fx'      => 0,
        'fx_live' => 0,
        'pcur'    => '',
        'pbal'    => 0,
        'pbal_at' => 0,
        'markup'  => 30,
        'timeout' => 20,
        'apps'    => [
            'tg' => ['on' => 1, 'title' => 'خدمات تلگرام',
                     'tagline' => 'ممبر، بازدید و ری‌اکشن — شروعِ خودکار در چند دقیقه'],
            'ig' => ['on' => 1, 'title' => 'خدمات اینستاگرام',
                     'tagline' => 'فالوور، لایک و ویو — سریع، امن و بی‌نیاز به رمز'],
        ],
    ];
}

function svCfg() {
    $s = cfg()['svc'] ?? null;
    $c = array_replace_recursive(svDefaults(), is_array($s) ? $s : []);
    if (trim((string)$c['url']) === '') $c['url'] = SV_DEFAULT_URL;
    return $c;
}

function svHost($url = null) {
    return strtolower((string)parse_url(trim((string)($url ?? svCfg()['url'])), PHP_URL_HOST));
}

function svIsJap($url = null) {
    return (bool)preg_match('/(^|\.)justanotherpanel\.com$/', svHost($url));
}

function svPanelName() {
    return svIsJap() ? 'JustAnotherPanel' : (svHost() ?: 'پنلِ خدمات');
}

// واحدی که واقعا با آن حساب می‌شود: اگر پنل خودش گفته دلاری است (یا JAP است) ولی در تنظیمات «تومان/ریال» مانده،
// قیمت‌ها هزار برابر ارزان فروخته می‌شدند — پس خودکار «دلار با قیمتِ لحظه‌ای» حساب می‌شود.
function svCurEff() {
    $c   = svCfg();
    $cur = (string)$c['cur'];
    if (!isset(svCurrencies()[$cur])) $cur = 'usd_live';
    $pc  = mb_strtoupper(trim((string)$c['pcur']));
    $usd = $cur === 'usd' || $cur === 'usd_live';
    if (!$usd && ($pc === 'USD' || $pc === 'USDT' || ($pc === '' && svIsJap()))) return 'usd_live';
    if ($usd && in_array($pc, ['IRT', 'TOMAN', 'TMN', 'تومان'], true)) return 'toman';
    if ($usd && in_array($pc, ['IRR', 'RIAL', 'ریال'], true)) return 'rial';
    return $cur;
}

function svSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!is_array($c['svc'] ?? null)) $c['svc'] = [];
        $fn($c['svc']);
    });
}

function svReady() {
    $c = svCfg();
    return trim((string)$c['url']) !== '' && trim((string)$c['key']) !== '';
}

function svAppOn($app) {
    if (!isset(svApps()[$app])) return false;
    return !empty(svCfg()['apps'][$app]['on']) && svReady();
}

function svUrl($app, $page = '') {
    $key = svApps()[$app]['key'] ?? '';
    $b = maBaseUrl();
    if ($key === '' || $b === '' || !preg_match('#^https://#i', $b)) return '';
    $page = preg_replace('/[^a-z]/', '', (string)$page);
    return $b . (str_contains($b, '?') ? '&' : '?') . 'app=' . $key . '&v=' . maViewVer()
             . ($page !== '' ? '&p=' . $page : '');
}

function svVisible($app) {
    return isset(svApps()[$app]) && !empty(svCfg()['apps'][$app]['on']) && svUrl($app) !== '';
}

function svOpenBtn($app, $page = '', $label = null) {
    if (!svVisible($app)) return null;
    $k = svApps()[$app]['ui'];
    $b = ['text' => $label ?? UT($k), 'web_app' => ['url' => svUrl($app, $page)]];
    $st = UC($k) ?: gs('link');
    if (isStyle($st)) $b['style'] = $st;
    $ic = (string)UI($k);
    if ($label === null && $ic !== '') $b['icon_custom_emoji_id'] = $ic;
    return $b;
}

function svOpenKb($app, $page = '') {
    $b = svOpenBtn($app, $page);
    return $b ? inlineKb([[$b]]) : null;
}


function svDb() {
    static $db = null;
    if ($db !== null) return $db ?: null;
    if (!class_exists('SQLite3')) return $db = false;
    $path = DATA_DIR . '/services.sqlite';
    if (!is_dir(dirname($path))) @mkdir(dirname($path), 0755, true);
    try {
        $db = new SQLite3($path);
    } catch (Throwable $e) {
        error_log('[services] services.sqlite باز نشد: ' . $e->getMessage());
        return $db = false;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec("CREATE TABLE IF NOT EXISTS svc (
        id TEXT PRIMARY KEY, app TEXT NOT NULL DEFAULT '', cat TEXT NOT NULL DEFAULT 'other',
        active INTEGER NOT NULL DEFAULT 0, name TEXT NOT NULL DEFAULT '', pname TEXT NOT NULL DEFAULT '',
        pcat TEXT NOT NULL DEFAULT '', type TEXT NOT NULL DEFAULT '', rate REAL NOT NULL DEFAULT 0,
        min INTEGER NOT NULL DEFAULT 0, max INTEGER NOT NULL DEFAULT 0, price REAL NOT NULL DEFAULT 0,
        refill INTEGER NOT NULL DEFAULT 0, cancel INTEGER NOT NULL DEFAULT 0, gone INTEGER NOT NULL DEFAULT 0,
        pos INTEGER NOT NULL DEFAULT 0, at INTEGER NOT NULL DEFAULT 0)");
    $db->exec('CREATE INDEX IF NOT EXISTS svc_app ON svc(app, active, cat)');
    $db->exec("CREATE TABLE IF NOT EXISTS svo (
        id TEXT PRIMARY KEY, uid INTEGER NOT NULL, uname TEXT NOT NULL DEFAULT '', app TEXT NOT NULL,
        sid TEXT NOT NULL, name TEXT NOT NULL DEFAULT '', cat TEXT NOT NULL DEFAULT '',
        link TEXT NOT NULL, qty INTEGER NOT NULL, unit REAL NOT NULL DEFAULT 0, total REAL NOT NULL DEFAULT 0,
        status TEXT NOT NULL, pst TEXT NOT NULL DEFAULT '', pid TEXT NOT NULL DEFAULT '',
        start INTEGER NOT NULL DEFAULT -1, remains INTEGER NOT NULL DEFAULT -1, refunded REAL NOT NULL DEFAULT 0,
        err TEXT NOT NULL DEFAULT '', created INTEGER NOT NULL, updated INTEGER NOT NULL,
        checked INTEGER NOT NULL DEFAULT 0, tries INTEGER NOT NULL DEFAULT 0)");
    $db->exec('CREATE INDEX IF NOT EXISTS svo_user ON svo(uid, created)');
    $db->exec('CREATE INDEX IF NOT EXISTS svo_open ON svo(status, checked)');
    $added = svDbCols($db, 'svc', ['man' => 'INTEGER NOT NULL DEFAULT 0', 'fa' => 'INTEGER NOT NULL DEFAULT 0']);
    svDbCols($db, 'svo', ['ans' => 'INTEGER NOT NULL DEFAULT 0', 'rfid' => "TEXT NOT NULL DEFAULT ''",
                          'rfst' => "TEXT NOT NULL DEFAULT ''", 'rfat' => 'INTEGER NOT NULL DEFAULT 0',
                          'rfck' => 'INTEGER NOT NULL DEFAULT 0']);
    // سرویس‌هایی که قبلا با نامِ انگلیسیِ پنل دریافت شده بودند، یک بار فارسی می‌شوند
    if (in_array('fa', $added, true)) {
        try { svFaRename(false); } catch (Throwable $e) { error_log('[services] fa rename: ' . $e->getMessage()); }
    }
    return $db;
}

function svDbCols(SQLite3 $db, $table, array $cols) {
    $have = $added = [];
    $res = $db->query('PRAGMA table_info(' . $table . ')');
    while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) $have[(string)$r['name']] = 1;
    foreach ($cols as $name => $def)
        if (!isset($have[$name]) && @$db->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $name . ' ' . $def)) $added[] = $name;
    return $added;
}


function svFx() {
    $c = svCfg();
    switch (svCurEff()) {
        case 'toman': return 1.0;
        case 'rial':  return 0.1;
        case 'usd_live':
            if ((float)$c['fx_live'] > 0) return (float)$c['fx_live'];
            return (float)$c['fx'];
        default: return (float)$c['fx'];
    }
}

function svFxRefresh() {
    if (svCurEff() !== 'usd_live' || !function_exists('pxUsdtIrt')) return 0.0;
    $r = (float)pxUsdtIrt();
    if ($r > 0) svSet(function (&$c) use ($r) { $c['fx_live'] = round($r, 2); });
    return $r;
}

function svRound($v) {
    $v = (float)$v;
    if ($v <= 0) return 0.0;
    if ($v < 1000) return (float)ceil($v);
    return (float)(ceil($v / 10) * 10);
}

function svPrice1k(array $s) {
    if ((float)($s['price'] ?? 0) > 0) return svRound($s['price']);
    $fx = svFx();
    $rate = (float)($s['rate'] ?? 0);
    if ($fx <= 0 || $rate <= 0) return 0.0;
    return svRound($rate * $fx * (1 + max(0.0, (float)svCfg()['markup']) / 100));
}

function svTotal($p1k, $qty) {
    return max(1.0, (float)ceil((float)$p1k * (int)$qty / 1000 - 1e-9));
}

// «Default» = لینک + تعداد. «Poll» = همان به‌علاوه‌ی شماره‌ی گزینه. بقیه (Package، کامنتِ دلخواه، منشن، اشتراکِ خودکار…)
// پارامترها یا قیمت‌گذاریِ دیگری دارند و فروخته نمی‌شوند.
function svKind($type) {
    $t = strtolower(trim((string)$type));
    if ($t === '' || $t === 'default') return 'default';
    if ($t === 'poll') return 'poll';
    return '';
}

function svTypeOk($type) {
    return svKind($type) !== '';
}


function svGuessApp($s) {
    if ($s === '') return null;
    if (preg_match('/threads|تردز|website|web\s*traffic|\bseo\b/u', $s)) return '';
    $ig = (bool)preg_match('/instagram|\binsta\b|اینستا|\big\b|\bigtv\b/u', $s);
    $tg = (bool)preg_match('/telegram|تلگرام|\btg\b/u', $s);
    if ($ig !== $tg) return $ig ? 'ig' : 'tg';
    if ($ig && $tg) return '';
    if (preg_match('/facebook|\bfb\b|youtube|tik\s?tok|twitter|\bx\s*\(|\bx\.com|spotify|soundcloud|twitch|discord|linkedin|pinterest|' .
                   'snapchat|reddit|quora|\bkick\b|whatsapp|\bvk\b|website|web traffic|google|apple music|deezer|shazam|audiomack|' .
                   'likee|rumble|clubhouse|tidal|trovo|vimeo|dailymotion|kwai|tumblr|bluesky|mixcloud|napster|boomplay|anghami|' .
                   'yandex|trustpilot|\bsteam\b|kakao|viber|\bline\b|snack video|تیک.?تاک|یوتیوب|توییتر|فیسبوک|واتساپ/u', $s)) return '';
    return null;
}

function svGuessCat($app, $s) {
    if ($s === '') return 'other';
    if ($app === 'ig') {
        $map = [
            'story'     => '/stor(y|ies)|استوری/u',
            'followers' => '/follow|فالو/u',
            'likes'     => '/comments?\s*likes?|لایک.?کامنت/u',
            'comments'  => '/comment|repl(y|ies)|کامنت/u',
            'likes2'    => '/like|لایک/u',
            'saves'     => '/save|share|repost|سیو|اشتراک|ذخیره/u',
            'views'     => '/view|reel|play|igtv|video|impression|reach|visit|\blive\b|watch|ویو|بازدید|ریلز/u',
        ];
    } else {
        $s = preg_replace('/non[\s\-_]*premium|no[\s\-_]+premium|بدون\s*پریمیوم/u', ' ', $s);
        $map = [
            'reactions' => '/reaction|react|emoji|ری.?اکشن|واکنش/u',
            'votes'     => '/vote|poll|رای|رأی|نظرسنجی/u',
            'comments'  => '/comment|repl(y|ies)|کامنت/u',
            'premium'   => '/premium[^|\]\)]{0,24}?(member|subscriber|user|account)|(member|subscriber)s?[^|]{0,14}premium|boost|بوست|ممبر.?پریمیوم|پریمیوم.?ممبر/u',
            'views'     => '/view|stor(y|ies)|seen|بازدید|سین|ویو/u',
            'members'   => '/member|subscriber|join|ممبر|عضو|سابسکرایبر/u',
            'premium2'  => '/premium|پریمیوم/u',
        ];
    }
    foreach ($map as $cat => $rx) if (preg_match($rx, $s)) return rtrim($cat, '2');
    return 'other';
}

// اول «دسته»ی پنل (مثلا «Telegram Members» یا «Instagram Story Views») و بعد نامِ سرویس.
function svGuess($pname, $pcat) {
    $c = mb_strtolower(trim((string)$pcat));
    $n = mb_strtolower(trim((string)$pname));
    if (preg_match('/threads|تردز/u', $c . ' ' . $n)) return ['', 'other'];
    $app = svGuessApp($c);
    if ($app === null) $app = svGuessApp($n);
    if (!$app) return ['', 'other'];
    $cat = svGuessCat($app, $c);
    if ($cat === 'other') $cat = svGuessCat($app, $n);
    return [$app, $cat];
}

function svCleanName($pname) {
    $n = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$pname)));
    return mb_substr($n, 0, 90);
}


// ---------- نامِ فارسیِ خودکار برای سرویس‌های پنل (JAP و مشابه) ----------
function svFaDigits($s) {
    return strtr((string)$s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', '.' => '٫']);
}

function svFaAmount($num, $unit = '') {
    $n = (float)str_replace(',', '', (string)$num);
    $u = strtolower((string)$unit);
    if ($u === 'k') $n *= 1000; elseif ($u === 'm') $n *= 1000000; elseif ($u === 'b') $n *= 1000000000;
    if ($n <= 0) return '';
    [$v, $w] = $n >= 1000000 ? [$n / 1000000, ' میلیون'] : ($n >= 1000 ? [$n / 1000, ' هزار'] : [$n, '']);
    return svFaDigits(rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.')) . $w;
}

function svFaTime($a, $b, $unit) {
    $u = strtolower($unit);
    $w = str_starts_with($u, 'd') ? 'روز' : (str_starts_with($u, 'h') ? 'ساعت' : 'دقیقه');
    return svFaDigits($a) . ($b !== '' && $b !== null ? ' تا ' . svFaDigits($b) : '') . ' ' . $w;
}

function svFaBase($app, $cat, $s) {
    $has = fn($rx) => (bool)preg_match($rx, $s);
    if ($app === 'ig') {
        switch ($cat) {
            case 'followers': return 'فالوور';
            case 'likes':
                if ($has('/comments?\s*likes?/')) return 'لایکِ کامنت';
                if ($has('/stor(y|ies)/')) return 'لایکِ استوری';
                if ($has('/reel/')) return 'لایکِ ریلز';
                if ($has('/\blive\b/')) return 'لایکِ لایو';
                return 'لایک';
            case 'comments':
                if ($has('/\blive\b/')) return 'کامنتِ لایو';
                if ($has('/repl(y|ies)/')) return 'ریپلای کامنت';
                return 'کامنت';
            case 'story':
                if ($has('/poll|vote/')) return 'رای نظرسنجیِ استوری';
                if ($has('/like/')) return 'لایکِ استوری';
                if ($has('/link\s*click|sticker/')) return 'کلیکِ لینکِ استوری';
                return 'بازدیدِ استوری';
            case 'saves':
                return $has('/share|repost|send/') && !$has('/save/') ? 'اشتراکِ پست' : 'سیوِ پست';
            case 'views':
                if ($has('/impression|reach/')) return 'ایمپرشن و ریچ';
                if ($has('/profile|visit/')) return 'بازدیدِ پروفایل';
                if ($has('/\blive\b/')) return 'بیننده‌ی لایو';
                if ($has('/reel/')) return 'ویوی ریلز';
                if ($has('/igtv/')) return 'ویوی IGTV';
                if ($has('/video/')) return 'ویوی ویدیو';
                return 'ویو';
        }
        if ($has('/channel/')) return 'عضوِ کانالِ اینستاگرام';
        if ($has('/poll|vote/')) return 'رای نظرسنجی';
        if ($has('/mention/')) return 'منشن';
        if ($has('/direct|\bdm\b/')) return 'دایرکت';
        return 'سرویسِ اینستاگرام';
    }
    switch ($cat) {
        case 'members':
            $g = $has('/group|گروه/'); $c = $has('/channel|کانال/');
            return $g && !$c ? 'ممبرِ گروه' : ($c && !$g ? 'ممبرِ کانال' : 'ممبرِ کانال و گروه');
        case 'premium':
            return $has('/boost|بوست/') ? 'بوستِ کانال' : 'ممبرِ پریمیوم';
        case 'views':
            if ($has('/stor(y|ies)/')) return 'بازدیدِ استوری';
            if ($has('/(auto|future|next\s*\d+)/')) return 'بازدیدِ خودکار';
            if ($has('/premium/')) return 'بازدیدِ پریمیوم';
            return 'بازدیدِ پست';
        case 'reactions':
            if ($has('/stor(y|ies)/')) return 'ری‌اکشنِ استوری';
            if ($has('/premium/')) return 'ری‌اکشنِ پریمیوم';
            return 'ری‌اکشن';
        case 'votes': return 'رای نظرسنجی';
        case 'comments': return 'کامنت';
    }
    if ($has('/\bbot\b|start/')) return 'استارتِ ربات';
    if ($has('/share|forward/')) return 'اشتراکِ پست';
    return 'سرویسِ تلگرام';
}

function svFaName($pname, $pcat, $app, $cat, $refill = 0) {
    if ($app === '' || !isset(svApps()[$app])) return svCleanName($pname);
    $s = mb_strtolower(trim((string)$pname) . ' | ' . trim((string)$pcat));
    $n = mb_strtolower((string)$pname);
    $base = svFaBase($app, $cat, $s);

    $adj = [];
    $add = function ($w) use (&$adj) { if ($w !== '' && !in_array($w, $adj, true)) $adj[] = $w; };
    $tests = [
        '/non[\s\-_]*premium|no[\s\-_]+premium/' => 'معمولی',
        '/real[\s\-]*look/'                       => 'شبیهِ واقعی',
        '/\breal\b(?![\s\-]*look)|\bhuman\b|organic/' => 'واقعی',
        '/\bhq\b|high[\s\-]*quality/'             => 'باکیفیت',
        '/\blq\b|low[\s\-]*quality|cheap/'        => 'اقتصادی',
        '/verified|blue\s*tick/'                  => 'تیک‌آبی',
        '/\bpower\b/'                             => 'قدرتی',
        '/\bactive\b/'                            => 'فعال',
        '/best\s*sell|recommended|popular/'       => 'پرفروش',
        '/positive/'                              => 'مثبت',
        '/negative/'                              => 'منفی',
        '/custom/'                                => 'دلخواه',
        '/random/'                                => 'تصادفی',
        '/\bmix(ed)?\b/'                          => 'ترکیبی',
        '/old\s*accounts?|aged/'                  => 'اکانتِ قدیمی',
        '/slow|gradual|drip/'                     => 'تدریجی',
        '/\bfast\b|super\s*fast|\bquick\b|speedy/' => 'سریع',
    ];
    if ($app === 'ig' && !in_array($cat, ['story'], true)) $tests['/premium|\bvip\b/'] = 'ویژه';
    if ($cat === 'comments') $tests['/emoji/'] = 'ایموجی';
    foreach ($tests as $rx => $w) if (preg_match($rx . 'u', $s)) $add($w);
    $geo = [
        '/\biran(ian)?\b|persian|ایرانی/' => 'ایرانی', '/\busa\b|\bu\.s\.a?\b|united\s*states|\bamerica(n)?\b/' => 'آمریکایی',
        '/\barab(ic)?\b|saudi|\buae\b|emirates|egypt/' => 'عرب', '/turk(ey|ish)?\b|türkiye/' => 'ترک',
        '/russia(n)?/' => 'روس', '/\bindia(n)?\b/' => 'هندی', '/brazil/' => 'برزیلی', '/europe/' => 'اروپایی',
        '/\buk\b|british|england/' => 'انگلیسی', '/german/' => 'آلمانی', '/france|french/' => 'فرانسوی',
        '/spain|spanish/' => 'اسپانیایی', '/ital(y|ian)/' => 'ایتالیایی', '/indonesia/' => 'اندونزیایی', '/korea/' => 'کره‌ای',
        '/japan/' => 'ژاپنی', '/china|chinese/' => 'چینی', '/\basia(n)?\b/' => 'آسیایی', '/africa/' => 'آفریقایی',
        '/latin|latam/' => 'آمریکای لاتین', '/pakistan/' => 'پاکستانی', '/uzbek/' => 'ازبک', '/azer/' => 'آذربایجانی',
        '/global|worldwide|international/' => 'جهانی',
    ];
    $lead = [];
    if (preg_match('/female|women|girls?\b/u', $n)) $lead[] = 'خانم';
    elseif (preg_match('/\bmale\b|\bmen\b/u', $n)) $lead[] = 'آقا';
    foreach ($geo as $rx => $w) if (preg_match($rx . 'u', $n)) { $lead[] = $w; break; }
    $adj = array_merge($lead, $adj);
    if (preg_match('/\+\s*(views?|reach|impressions?|likes?|saves?|shares?)/u', $n, $m)) {
        $plus = ['view' => 'بازدید', 'reac' => 'ریچ', 'impr' => 'ایمپرشن', 'like' => 'لایک', 'save' => 'سیو', 'shar' => 'اشتراک'][substr($m[1], 0, 4)] ?? '';
        if ($plus !== '' && !str_contains($base, $plus) && !($plus === 'بازدید' && str_contains($base, 'ویو'))) $add('+ ' . $plus);
    }

    $f = [];
    if (preg_match('/non[\s\-]*drop|no[\s\-]*drop|0%\s*drop|zero\s*drop/u', $n)) $f[] = 'بدونِ ریزش';
    elseif (preg_match('/low[\s\-]*drop/u', $n)) $f[] = 'ریزشِ کم';
    if (preg_match('/(?:refill|guarantee(?:d)?|ضمانت)\s*[:\-]?\s*(\d+)\s*(d|days?|روز)\b/u', $n, $m)
        || preg_match('/(\d+)\s*(d|days?)\s*(?:refill|guarantee)/u', $n, $m)) $f[] = 'ضمانتِ ' . svFaDigits($m[1]) . ' روزه';
    elseif (preg_match('/(?:refill|guarantee(?:d)?)\s*[:\-]?\s*(\d+)\s*(months?|m)\b|(\d+)\s*months?\s*refill/u', $n, $m))
        $f[] = 'ضمانتِ ' . svFaDigits($m[1] !== '' ? $m[1] : $m[3]) . ' ماهه';
    elseif (preg_match('/lifetime|life\s*time/u', $n)) $f[] = 'ضمانتِ همیشگی';
    elseif (preg_match('/\br(\d{2,3})\b/u', $n, $m)) $f[] = 'ضمانتِ ' . svFaDigits($m[1]) . ' روزه';
    elseif (preg_match('/no\s*refill|non[\s\-]*refill|refill\s*[:\-]?\s*(no\b|none|❌)|without\s*refill/u', $n)) $f[] = 'بدونِ ضمانت';
    elseif ($refill || str_contains($n, '♻') || preg_match('/\brefill\b|guarantee/u', $n)) $f[] = 'ضمانت‌دار';

    if (preg_match('/(last|latest)\s*(\d+)\s*posts?/u', $n, $m)) $f[] = svFaDigits($m[2]) . ' پستِ آخر';
    elseif (preg_match('/(next|future)\s*(\d+)\s*posts?/u', $n, $m)) $f[] = svFaDigits($m[2]) . ' پستِ بعدی';
    elseif (preg_match('/\b1\s*post\b|single\s*post/u', $n)) $f[] = 'یک پست';

    if ($app === 'tg' && $cat === 'premium' && preg_match('/(\d+)\s*(d|days?)\b/u', $n, $m)) $f[] = svFaDigits($m[1]) . ' روزه';
    $nd = preg_replace('/start(?:\s*time)?\s*[:\-]?\s*[\w\s\-–]{0,14}/u', ' ', $n);
    if (preg_match('/(\d+)\s*(minutes?|mins?)\b/u', $nd, $m)) $f[] = svFaDigits($m[1]) . ' دقیقه';

    if (preg_match('/\bmax(?:imum)?\s*[:\-]?\s*([\d][\d.,]*)\s*([kmb])?\b/u', $n, $m)) {
        $a = svFaAmount($m[1], $m[2] ?? '');
        if ($a !== '') $f[] = 'سقفِ ' . $a;
    }
    if (preg_match('/start(?:\s*time)?\s*[:\-]?\s*(instant|immediate)/u', $n)) $f[] = 'شروعِ فوری';
    elseif (preg_match('/start(?:\s*time)?\s*[:\-]?\s*(\d+)\s*(?:-|to|–)\s*(\d+)\s*(min|minutes?|mins?|m|hrs?|hours?|h|days?|d)\b/u', $n, $m))
        $f[] = 'شروعِ ' . svFaTime($m[1], $m[2], $m[3]);
    elseif (preg_match('/start(?:\s*time)?\s*[:\-]?\s*(\d+)\s*(min|minutes?|mins?|m|hrs?|hours?|h|days?|d)\b/u', $n, $m))
        $f[] = 'شروعِ ' . svFaTime($m[1], '', $m[2]);
    elseif (preg_match('/\binstant\b/u', $n)) $f[] = 'شروعِ فوری';

    if (preg_match('/speed\s*[:\-]?\s*([\d][\d.,]*)\s*([km])?\s*(?:\/|per)\s*(d|day|h|hr|hour)\b/u', $n, $m)
        || preg_match('/\b(?:day|daily)\s*[:\-]?\s*([\d][\d.,]*)\s*([km])?\b()/u', $n, $m)
        || preg_match('/([\d][\d.,]*)\s*([km])?\s*\/\s*(d|day|h|hr|hour)\b/u', $n, $m)) {
        $a = svFaAmount($m[1], $m[2] ?? '');
        $per = isset($m[3]) && $m[3] !== '' && $m[3][0] === 'h' ? 'در ساعت' : 'در روز';
        if ($a !== '') $f[] = 'سرعتِ ' . $a . ' ' . $per;
    }
    if ($app === 'tg' && $cat === 'reactions' && preg_match_all('/[\x{1F300}-\x{1FAFF}\x{2764}\x{2600}-\x{26FF}]\x{FE0F}?/u', (string)$pname, $em)) {
        $e = implode('', array_slice(array_unique(array_filter($em[0], fn($x) => !preg_match('/^[\x{267B}\x{26A1}]/u', $x))), 0, 6));
        if ($e !== '') $f[] = $e;
    }

    $out = $base . ($adj ? ' ' . implode(' ', $adj) : '');
    foreach ($f as $k => $x) {
        $try = $out . ($k === 0 ? ' — ' : ' · ') . $x;
        if (mb_strlen($try) > 90) break;
        $out = $try;
    }
    return mb_substr($out, 0, 90);
}

// نامی که هنوز خودکار است (ادمین دستی عوضش نکرده) — با فارسیِ تازه جایگزین می‌شود
function svNameAuto(array $s) {
    return !empty($s['fa']) || (string)$s['name'] === '' || (string)$s['name'] === (string)$s['pname'];
}

function svFaRename($all = false) {
    $db = svDb();
    if (!$db) return 0;
    $res = $db->query('SELECT id, app, cat, name, pname, pcat, refill, fa FROM svc');
    $rows = [];
    while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) $rows[] = $r;
    $st = $db->prepare('UPDATE svc SET name = :n, fa = 1 WHERE id = :id');
    $n = 0;
    $db->exec('BEGIN');
    foreach ($rows as $r) {
        if (!$all && !svNameAuto($r)) continue;
        $new = svFaName($r['pname'], $r['pcat'], (string)$r['app'], (string)$r['cat'], (int)$r['refill']);
        if ($new === (string)$r['name'] && (int)$r['fa']) continue;
        $st->bindValue(':n', $new, SQLITE3_TEXT);
        $st->bindValue(':id', (string)$r['id'], SQLITE3_TEXT);
        $st->execute(); $st->reset();
        $n++;
    }
    $db->exec('COMMIT');
    return $n;
}

function svRowOf(array $r) {
    $r['active'] = (int)$r['active']; $r['min'] = (int)$r['min']; $r['max'] = (int)$r['max'];
    $r['refill'] = (int)$r['refill']; $r['cancel'] = (int)$r['cancel']; $r['gone'] = (int)$r['gone'];
    $r['rate'] = (float)$r['rate']; $r['price'] = (float)$r['price']; $r['pos'] = (int)$r['pos'];
    $r['man'] = (int)($r['man'] ?? 0); $r['fa'] = (int)($r['fa'] ?? 0);
    return $r;
}

function svService($id) {
    $db = svDb();
    if (!$db) return null;
    $st = $db->prepare('SELECT * FROM svc WHERE id = :id');
    $st->bindValue(':id', (string)$id, SQLITE3_TEXT);
    $r = $st->execute()->fetchArray(SQLITE3_ASSOC);
    return $r ? svRowOf($r) : null;
}

function svServices($app, $onlyOn = true) {
    $db = svDb();
    if (!$db) return [];
    $st = $db->prepare('SELECT * FROM svc WHERE app = :a' . ($onlyOn ? ' AND active = 1 AND gone = 0' : '') .
                       ' ORDER BY pos ASC, rate ASC, CAST(id AS INTEGER) ASC');
    $st->bindValue(':a', (string)$app, SQLITE3_TEXT);
    $res = $st->execute();
    $out = [];
    while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) $out[] = svRowOf($r);
    return $out;
}

function svCount($app) {
    static $memo = [];
    if (isset($memo[$app])) return $memo[$app];
    $db = svDb();
    if (!$db) return $memo[$app] = 0;
    $st = $db->prepare('SELECT COUNT(*) n FROM svc WHERE app = :a AND active = 1 AND gone = 0');
    $st->bindValue(':a', (string)$app, SQLITE3_TEXT);
    return $memo[$app] = (int)(($st->execute()->fetchArray(SQLITE3_ASSOC))['n'] ?? 0);
}

function svPublic($app) {
    $cats = svCats($app);
    $items = [];
    $n = $from = [];
    foreach (svServices($app) as $s) {
        if (!svTypeOk($s['type'])) continue;
        $p = svPrice1k($s);
        if ($p <= 0) continue;
        $c = isset($cats[$s['cat']]) ? $s['cat'] : 'other';
        $n[$c] = ($n[$c] ?? 0) + 1;
        if (!isset($from[$c]) || $p < $from[$c]) $from[$c] = $p;
        $it = [
            'i' => (string)$s['id'], 'c' => $c, 'n' => $s['name'] !== '' ? $s['name'] : svCleanName($s['pname']),
            'p' => $p, 'mn' => max(1, $s['min']), 'mx' => max(max(1, $s['min']), $s['max']),
            'r' => $s['refill'] ? 1 : 0,
        ];
        if (svKind($s['type']) === 'poll') $it['y'] = 'poll';
        $items[] = $it;
    }
    $outC = [];
    foreach ($cats as $id => [$name, $ic]) {
        if (empty($n[$id])) continue;
        $outC[] = ['id' => $id, 'n' => $name, 'ic' => $ic, 'c' => $n[$id], 'f' => $from[$id]];
    }
    return ['cats' => $outC, 'items' => $items];
}


function svHttp(array $params, $timeout = null) {
    $c   = svCfg();
    $url = trim((string)$c['url']);
    $key = trim((string)$c['key']);
    if ($url === '' || $key === '') return [null, 'آدرس یا کلیدِ API پنلِ خدمات ثبت نشده است.', 'cfg'];
    if (!preg_match('#^https?://\S+$#i', $url)) return [null, 'آدرسِ API باید با https:// شروع شود.', 'cfg'];
    if ((!defined('SV_ALLOW_PRIVATE') || !SV_ALLOW_PRIVATE) && function_exists('ssrfSafeUrl') && !ssrfSafeUrl($url, $why))
        return [null, 'آدرس رد شد: ' . $why, 'cfg'];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['key' => $key] + $params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => max(5, min(60, (int)($timeout ?? $c['timeout']))),
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; NumbixBot/1.0)',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $res  = curl_exec($ch);
    $no   = curl_errno($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false) {
        $never = in_array($no, [1, 3, 5, 6, 7, 35, 51, 58, 60, 77], true);
        return [null, 'اتصال به پنلِ خدمات برقرار نشد: ' . $err, $never ? 'down' : 'net'];
    }
    if ($code < 200 || $code >= 300) return [null, 'پنلِ خدمات خطای ' . $code . ' داد.', 'http'];
    $j = json_decode((string)$res, true);
    if (!is_array($j))
        return [null, 'پاسخِ پنل JSON نبود — آدرسِ API را چک کنید: ' .
                      mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string)$res))), 0, 100), 'json'];
    if (isset($j['error']) && !isset($j['order']))
        return [null, is_string($j['error']) ? $j['error'] : json_encode($j['error'], JSON_UNESCAPED_UNICODE), 'api'];
    return [$j, '', ''];
}

function svErrText($raw) {
    $e = mb_strtolower((string)$raw);
    if (str_contains($e, 'api key') || str_contains($e, 'invalid key') || str_contains($e, 'incorrect key'))
        return 'کلیدِ API پنلِ خدمات درست نیست.';
    if (str_contains($e, 'fund') || str_contains($e, 'balance') || str_contains($e, 'insufficient'))
        return 'موجودیِ پنلِ خدمات کافی نیست.';
    if (str_contains($e, 'service'))
        return 'این سرویس در پنلِ خدمات غیرفعال شده است.';
    if (str_contains($e, 'quantity') && (str_contains($e, 'less') || str_contains($e, 'min')))
        return 'تعداد کمتر از حداقلِ این سرویس است.';
    if (str_contains($e, 'quantity') && (str_contains($e, 'more') || str_contains($e, 'max')))
        return 'تعداد بیشتر از حداکثرِ این سرویس است.';
    if (str_contains($e, 'active order') || str_contains($e, 'duplicate') || str_contains($e, 'wait until'))
        return 'برای همین لینک یک سفارشِ دیگر در حالِ انجام است؛ بعد از تمام شدنش دوباره ثبت کنید.';
    if (str_contains($e, 'link') || str_contains($e, 'url') || str_contains($e, 'private'))
        return 'لینک پذیرفته نشد — لینکِ عمومی و درست بفرستید (پیج/کانال نباید خصوصی باشد).';
    if (str_contains($e, 'incorrect request'))
        return 'پنلِ خدمات درخواست را نشناخت — آدرسِ API را چک کنید.';
    return 'پنلِ خدمات خطا داد: ' . mb_substr((string)$raw, 0, 160);
}

function svBalance() {
    [$j, $err, $kind] = svHttp(['action' => 'balance'], 15);
    if ($j === null) return [0.0, '', $kind === 'api' ? svErrText($err) : $err];
    if (!isset($j['balance'])) return [0.0, '', 'پاسخِ «balance» پنل ناقص بود.'];
    $bal = (float)str_replace(',', '', (string)$j['balance']);
    $cur = mb_strtoupper(mb_substr(trim((string)($j['currency'] ?? '')), 0, 10));
    $was = svCurEff();
    svSet(function (&$c) use ($bal, $cur) {
        $c['pbal'] = $bal; $c['pbal_at'] = time();
        if ($cur !== '') $c['pcur'] = $cur;
    });
    if (svCurEff() === 'usd_live' && ($was !== 'usd_live' || svFx() <= 0)) svFxRefresh();
    return [$bal, $cur, ''];
}

// توضیحِ کوتاه وقتی واحدِ پنل با تنظیمات جور نبود و خودکار عوض شد
function svCurNote() {
    $set = (string)svCfg()['cur'];
    $eff = svCurEff();
    if ($set === $eff) return '';
    return 'پنل گفته قیمت‌هایش به ' . (mb_strtoupper((string)svCfg()['pcur']) ?: 'دلار') . ' است؛ برای همین قیمت‌ها با «' .
           svCurrencies()[$eff] . '» حساب می‌شوند (نه «' . (svCurrencies()[$set] ?? $set) . '»).';
}

function svImport() {
    @set_time_limit(120);
    [$j, $err, $kind] = svHttp(['action' => 'services'], 45);
    if ($j === null) return [false, $kind === 'api' ? svErrText($err) : $err];
    $list = isset($j[0]) ? array_values($j) : (is_array($j['services'] ?? null) ? array_values($j['services']) : []);
    if (!$list) return [false, 'پنل هیچ سرویسی برنگرداند.'];

    $db = svDb();
    if (!$db) return [false, 'دیتابیسِ خدمات باز نشد.'];
    if (trim((string)svCfg()['pcur']) === '') svBalance();
    svFxRefresh();

    $seen = [];
    $new = $upd = $tgN = $igN = $skip = $moved = 0;
    $now = time();
    $db->exec('BEGIN IMMEDIATE');
    try {
        $get = $db->prepare('SELECT id, app, cat, active, man FROM svc WHERE id = :id');
        $mv  = $db->prepare('UPDATE svc SET app = :app, cat = :cat, active = CASE WHEN :app = \'\' THEN 0 ELSE active END WHERE id = :id');
        $ins = $db->prepare('INSERT INTO svc (id, app, cat, active, name, pname, pcat, type, rate, min, max, refill, cancel, gone, pos, at)
                             VALUES (:id, :app, :cat, 0, :name, :pname, :pcat, :type, :rate, :min, :max, :refill, :cancel, 0, :pos, :at)');
        $up  = $db->prepare('UPDATE svc SET pname = :pname, pcat = :pcat, type = :type, rate = :rate, min = :min, max = :max,
                             refill = :refill, cancel = :cancel, gone = 0, at = :at WHERE id = :id');
        $pos = 0;
        foreach ($list as $s) {
            if (!is_array($s) || !isset($s['service'])) continue;
            $id = trim((string)$s['service']);
            if ($id === '' || strlen($id) > 40) continue;
            $pos++;
            $seen[$id] = 1;
            $pname = svCleanName($s['name'] ?? '');
            $pcat  = mb_substr(trim((string)($s['category'] ?? '')), 0, 120);
            $vals = [
                ':pname' => $pname, ':pcat' => $pcat, ':type' => mb_substr(trim((string)($s['type'] ?? '')), 0, 40),
                ':rate' => (float)str_replace(',', '', (string)($s['rate'] ?? 0)),
                ':min' => max(0, (int)($s['min'] ?? 0)), ':max' => max(0, (int)($s['max'] ?? 0)),
                ':refill' => !empty($s['refill']) && $s['refill'] !== 'false' ? 1 : 0,
                ':cancel' => !empty($s['cancel']) && $s['cancel'] !== 'false' ? 1 : 0,
                ':at' => $now, ':id' => $id,
            ];
            $get->bindValue(':id', $id, SQLITE3_TEXT);
            $have = $get->execute()->fetchArray(SQLITE3_ASSOC);
            $get->reset();
            if ($have) {
                foreach ($vals as $k => $v) $up->bindValue($k, $v);
                $up->execute(); $up->reset();
                $upd++;
                // دسته‌بندیِ خودکار دوباره حساب می‌شود، مگر ادمین خودش بخش/دسته را عوض کرده باشد
                // یا سرویسِ روشن بخواهد از یک مینی‌اپ به دیگری برود.
                if (!(int)$have['man']) {
                    [$gApp, $gCat] = svGuess($pname, $pcat);
                    $oApp = (string)$have['app'];
                    if (($gApp !== $oApp || $gCat !== (string)$have['cat']) && !((int)$have['active'] && $gApp !== $oApp)) {
                        $mv->bindValue(':app', $gApp, SQLITE3_TEXT);
                        $mv->bindValue(':cat', $gCat, SQLITE3_TEXT);
                        $mv->bindValue(':id', $id, SQLITE3_TEXT);
                        $mv->execute(); $mv->reset();
                        $moved++;
                    }
                }
                continue;
            }
            [$app, $cat] = svGuess($pname, $pcat);
            if ($app === 'tg') $tgN++; elseif ($app === 'ig') $igN++; else $skip++;
            foreach ($vals as $k => $v) $ins->bindValue($k, $v);
            $ins->bindValue(':app', $app, SQLITE3_TEXT);
            $ins->bindValue(':cat', $cat, SQLITE3_TEXT);
            $ins->bindValue(':name', $pname, SQLITE3_TEXT);
            $ins->bindValue(':pos', $pos, SQLITE3_INTEGER);
            $ins->execute(); $ins->reset();
            $new++;
        }
        $gone = 0;
        $res = $db->query('SELECT id FROM svc WHERE gone = 0');
        $drop = [];
        while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) if (!isset($seen[(string)$r['id']])) $drop[] = (string)$r['id'];
        $g = $db->prepare('UPDATE svc SET gone = 1, active = 0 WHERE id = :id');
        foreach ($drop as $id) { $g->bindValue(':id', $id, SQLITE3_TEXT); $g->execute(); $g->reset(); $gone++; }
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        error_log('[services] import: ' . $e->getMessage());
        return [false, 'ذخیره‌ی سرویس‌ها نشد: ' . $e->getMessage()];
    }
    $faN = svFaRename(false);
    $note = svCurNote();
    return [true, 'از ' . svPanelName() . ' ' . fmtNum(count($seen)) . ' سرویس خوانده شد — تازه: ' . fmtNum($new) .
                  ' (تلگرام ' . fmtNum($tgN) . '، اینستاگرام ' . fmtNum($igN) . '، بقیه‌ی شبکه‌ها ' . fmtNum($skip) . ')' .
                  ' · به‌روزشده: ' . fmtNum($upd) . ($moved ? ' · دسته‌بندیِ دوباره: ' . fmtNum($moved) : '') .
                  ' · حذف‌شده از پنل: ' . fmtNum($gone) . ($faN ? ' · نامِ فارسی: ' . fmtNum($faN) : '') .
                  ($new ? "\nسرویس‌های تازه خاموش‌اند؛ در «سرویس‌ها» آن‌هایی را که می‌خواهید بفروشید روشن کنید." : '') .
                  ($note !== '' ? "\n" . $note : '')];
}

function svServiceSave($id, array $f) {
    $db = svDb();
    if (!$db) return false;
    $s = svService($id);
    if (!$s) return false;
    $app = isset(svApps()[$f['app'] ?? '']) ? (string)$f['app'] : ($f['app'] === '' ? '' : $s['app']);
    $cats = $app !== '' ? svCats($app) : [];
    $cat = isset($cats[$f['cat'] ?? '']) ? (string)$f['cat'] : (isset($cats[$s['cat']]) ? $s['cat'] : 'other');
    $name = mb_substr(trim((string)($f['name'] ?? '')), 0, 90);
    $man = ($app !== $s['app'] || $cat !== $s['cat']) ? 1 : (int)($s['man'] ?? 0);
    // خالی = نامِ فارسیِ خودکار · همان نامِ قبلی = دست نخورده · هر چیزِ دیگر = نامِ دستیِ ادمین
    if ($name === '' || ($name === $s['name'] && svNameAuto($s))) {
        $fa = 1;
        $name = svFaName($s['pname'], $s['pcat'], $app, $cat, $s['refill']);
    } else {
        $fa = $name === $s['name'] ? (int)$s['fa'] : 0;
    }
    $st = $db->prepare('UPDATE svc SET app = :app, cat = :cat, active = :on, name = :name, price = :price, man = :man, fa = :fa WHERE id = :id');
    $st->bindValue(':man', $man, SQLITE3_INTEGER);
    $st->bindValue(':fa', $fa, SQLITE3_INTEGER);
    $st->bindValue(':app', $app, SQLITE3_TEXT);
    $st->bindValue(':cat', $cat, SQLITE3_TEXT);
    $st->bindValue(':on', (!empty($f['on']) && $app !== '' && !$s['gone'] && svTypeOk($s['type'])) ? 1 : 0, SQLITE3_INTEGER);
    $st->bindValue(':name', $name !== '' ? $name : $s['pname'], SQLITE3_TEXT);
    $st->bindValue(':price', max(0.0, (float)($f['price'] ?? 0)), SQLITE3_FLOAT);
    $st->bindValue(':id', (string)$id, SQLITE3_TEXT);
    $st->execute();
    return true;
}

function svServiceBulk(array $ids, $on) {
    $db = svDb();
    if (!$db || !$ids) return 0;
    $n = 0;
    $st = $db->prepare("UPDATE svc SET active = :on WHERE id = :id AND app <> '' AND gone = 0");
    $db->exec('BEGIN');
    foreach ($ids as $id) {
        $s = svService($id);
        if (!$s || ($on && !svTypeOk($s['type']))) continue;
        $st->bindValue(':on', $on ? 1 : 0, SQLITE3_INTEGER);
        $st->bindValue(':id', (string)$id, SQLITE3_TEXT);
        $st->execute(); $st->reset();
        $n += $db->changes();
    }
    $db->exec('COMMIT');
    return $n;
}

function svAdminList($app, $q, $onlyOn, $page, $per, $cat = '', $pcat = '') {
    $db = svDb();
    if (!$db) return [[], 0];
    $w = ['gone = 0'];
    $b = [];
    if ($app === 'none') $w[] = "app = ''";
    elseif ($app !== 'all') { $w[] = 'app = :app'; $b[':app'] = $app; }
    if ($onlyOn === 'on')  $w[] = 'active = 1';
    if ($onlyOn === 'off') $w[] = 'active = 0';
    if ($cat !== '')  { $w[] = 'cat = :cat'; $b[':cat'] = $cat; }
    if ($pcat !== '') { $w[] = 'pcat = :pcat'; $b[':pcat'] = $pcat; }
    if ($q !== '') {
        $w[] = "(id = :qe OR name LIKE :q ESCAPE '\\' OR pname LIKE :q ESCAPE '\\' OR pcat LIKE :q ESCAPE '\\')";
        $b[':qe'] = $q;
        $b[':q']  = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    }
    $where = ' WHERE ' . implode(' AND ', $w);
    $c = $db->prepare('SELECT COUNT(*) n FROM svc' . $where);
    foreach ($b as $k => $v) $c->bindValue($k, $v, SQLITE3_TEXT);
    $total = (int)(($c->execute()->fetchArray(SQLITE3_ASSOC))['n'] ?? 0);
    $s = $db->prepare('SELECT * FROM svc' . $where . ' ORDER BY active DESC, app ASC, cat ASC, pos ASC LIMIT :l OFFSET :o');
    foreach ($b as $k => $v) $s->bindValue($k, $v, SQLITE3_TEXT);
    $s->bindValue(':l', max(1, (int)$per), SQLITE3_INTEGER);
    $s->bindValue(':o', max(0, ((int)$page - 1) * (int)$per), SQLITE3_INTEGER);
    $res = $s->execute();
    $out = [];
    while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) $out[] = svRowOf($r);
    return [$out, $total];
}

// دسته‌های خودِ پنل (مثلا «Telegram Members») با تعداد، برای فیلترِ صفحه‌ی سرویس‌ها
function svPcats($app) {
    $db = svDb();
    if (!$db) return [];
    $w = 'gone = 0';
    if ($app === 'none') $w .= " AND app = ''";
    elseif ($app !== 'all') $w .= ' AND app = :app';
    $st = $db->prepare('SELECT pcat, COUNT(*) n, SUM(active) a FROM svc WHERE ' . $w . ' GROUP BY pcat ORDER BY MIN(pos) ASC');
    if ($app !== 'none' && $app !== 'all') $st->bindValue(':app', (string)$app, SQLITE3_TEXT);
    $res = $st->execute();
    $out = [];
    while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) $out[(string)$r['pcat']] = [(int)$r['n'], (int)$r['a']];
    return $out;
}

function svStats() {
    $db = svDb();
    $o = ['tg' => 0, 'ig' => 0, 'none' => 0, 'tg_on' => 0, 'ig_on' => 0, 'run' => 0, 'check' => 0, 'today' => 0, 'sum' => 0.0];
    if (!$db) return $o;
    $res = $db->query('SELECT app, active, COUNT(*) n FROM svc WHERE gone = 0 GROUP BY app, active');
    while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) {
        $k = (string)$r['app'] === '' ? 'none' : (string)$r['app'];
        if (!isset($o[$k])) continue;
        $o[$k] += (int)$r['n'];
        if ((int)$r['active'] && $k !== 'none') $o[$k . '_on'] += (int)$r['n'];
    }
    $o['run']   = (int)$db->querySingle("SELECT COUNT(*) FROM svo WHERE status = 'run'");
    $o['check'] = (int)$db->querySingle("SELECT COUNT(*) FROM svo WHERE status = 'check'");
    $st = $db->prepare("SELECT COUNT(*) n, COALESCE(SUM(total - refunded), 0) s FROM svo WHERE created >= :t AND status NOT IN ('failed', 'canceled', 'new')");
    $st->bindValue(':t', strtotime('today'), SQLITE3_INTEGER);
    $r = $st->execute()->fetchArray(SQLITE3_ASSOC);
    $o['today'] = (int)($r['n'] ?? 0);
    $o['sum']   = (float)($r['s'] ?? 0);
    return $o;
}


function svLink($app, $raw) {
    $l = trim((string)$raw);
    $l = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\s]+/u', '', (string)$l);
    if ($l === '') return ['', 'لینک یا آیدی را وارد کنید.'];
    if (mb_strlen($l) > 300) return ['', 'لینک خیلی بلند است.'];

    if ($app === 'tg') {
        if (preg_match('/^@([A-Za-z][A-Za-z0-9_]{3,31})$/', $l, $m)) return ['https://t.me/' . $m[1], ''];
        if (preg_match('#^(?:https?://)?(?:www\.)?(?:t\.me|telegram\.me|telegram\.dog)/(\S+)$#i', $l, $m))
            return ['https://t.me/' . $m[1], ''];
        return ['', 'لینکِ تلگرام درست نیست. مثل https://t.me/channel یا @channel بفرستید.'];
    }
    if (preg_match('/^@?([A-Za-z0-9._]{1,30})$/', $l, $m) && !str_contains($m[1], '..'))
        return ['https://www.instagram.com/' . $m[1] . '/', ''];
    if (preg_match('#^(?:https?://)?(?:www\.|m\.)?(?:instagram\.com|instagr\.am)/(\S+)$#i', $l, $m))
        return ['https://www.instagram.com/' . $m[1], ''];
    return ['', 'لینکِ اینستاگرام درست نیست. مثل https://instagram.com/username یا @username بفرستید.'];
}


function svOrder($id) {
    $db = svDb();
    if (!$db) return null;
    $st = $db->prepare('SELECT * FROM svo WHERE id = :id');
    $st->bindValue(':id', (string)$id, SQLITE3_TEXT);
    $r = $st->execute()->fetchArray(SQLITE3_ASSOC);
    return $r ?: null;
}

function svOrderSet($id, array $f, $whereStatus = null) {
    $db = svDb();
    if (!$db || !$f) return false;
    $sets = [];
    foreach (array_keys($f) as $k) $sets[] = $k . ' = :' . $k;
    $sql = 'UPDATE svo SET ' . implode(', ', $sets) . ', updated = :_u WHERE id = :_id' .
           ($whereStatus !== null ? ' AND status = :_ws' : '');
    $st = $db->prepare($sql);
    foreach ($f as $k => $v) $st->bindValue(':' . $k, $v, is_int($v) ? SQLITE3_INTEGER : (is_float($v) ? SQLITE3_FLOAT : SQLITE3_TEXT));
    $st->bindValue(':_u', time(), SQLITE3_INTEGER);
    $st->bindValue(':_id', (string)$id, SQLITE3_TEXT);
    if ($whereStatus !== null) $st->bindValue(':_ws', (string)$whereStatus, SQLITE3_TEXT);
    $st->execute();
    return $db->changes() > 0;
}

function svOrdersFor($uid, $app = '', $limit = 40) {
    $db = svDb();
    if (!$db) return [];
    $st = $db->prepare('SELECT * FROM svo WHERE uid = :u' . ($app !== '' ? ' AND app = :a' : '') .
                       " AND status <> 'new' ORDER BY created DESC LIMIT :n");
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    if ($app !== '') $st->bindValue(':a', (string)$app, SQLITE3_TEXT);
    $st->bindValue(':n', max(1, (int)$limit), SQLITE3_INTEGER);
    $res = $st->execute();
    $out = [];
    while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) $out[] = $r;
    return $out;
}

function svOpenCount($uid) {
    $db = svDb();
    if (!$db) return 0;
    $st = $db->prepare("SELECT COUNT(*) n FROM svo WHERE uid = :u AND status IN ('run', 'check', 'new')");
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    return (int)(($st->execute()->fetchArray(SQLITE3_ASSOC))['n'] ?? 0);
}

function svStatusText($st, $pst = '') {
    if ($st === 'run') {
        $p = strtolower((string)$pst);
        return $p === 'pending' || $p === '' ? 'در صف' : 'در حال انجام';
    }
    return [
        'done' => 'انجام شد', 'partial' => 'ناقص — مابقی برگشت', 'canceled' => 'لغو شد — پول برگشت',
        'failed' => 'ثبت نشد — پول برگشت', 'check' => 'در حال بررسی', 'new' => 'در حال ثبت',
    ][$st] ?? $st;
}

function svRow($o) {
    if (!$o) return null;
    $qty = (int)$o['qty'];
    $rem = (int)$o['remains'];
    $done = 0;
    if ($o['status'] === 'done') $done = 100;
    elseif ($rem >= 0 && $qty > 0) $done = (int)max(0, min(100, round(($qty - $rem) * 100 / $qty)));
    $r = [
        'id' => (string)$o['id'], 'app' => (string)$o['app'], 'n' => (string)$o['name'], 'c' => (string)$o['cat'],
        'l' => (string)$o['link'], 'q' => $qty, 't' => (float)$o['total'], 'rf' => (float)$o['refunded'],
        'st' => (string)$o['status'], 'sx' => svStatusText((string)$o['status'], (string)$o['pst']),
        'sc' => (int)$o['start'], 'rm' => $rem, 'pc' => $done, 'at' => (int)$o['created'],
    ];
    if ((int)($o['ans'] ?? 0) > 0) $r['an'] = (int)$o['ans'];
    if (svRefillable($o)) $r['rb'] = 1;
    if ((string)($o['rfid'] ?? '') !== '') $r['rs'] = svRefillText((string)$o['rfst']);
    return $r;
}


// ---------- ریفیل (جبرانِ ریزش) ----------
function svRefillIds() {
    static $ids = null;
    if ($ids !== null) return $ids;
    $ids = [];
    $db = svDb();
    if (!$db) return $ids;
    $res = $db->query('SELECT id FROM svc WHERE refill = 1');
    while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) $ids[(string)$r['id']] = 1;
    return $ids;
}

function svRefillDone($st) {
    return in_array(strtolower(trim((string)$st)), ['completed', 'complete', 'done', 'rejected', 'canceled', 'cancelled', 'error', 'failed', 'expired'], true);
}

function svRefillText($st) {
    $s = strtolower(trim((string)$st));
    return [
        '' => 'ریفیل در صف', 'pending' => 'ریفیل در صف', 'in progress' => 'ریفیل در حالِ انجام', 'processing' => 'ریفیل در حالِ انجام',
        'completed' => 'ریفیل انجام شد', 'complete' => 'ریفیل انجام شد', 'done' => 'ریفیل انجام شد',
        'rejected' => 'ریفیل رد شد', 'canceled' => 'ریفیل لغو شد', 'cancelled' => 'ریفیل لغو شد',
        'error' => 'ریفیل ثبت نشد', 'failed' => 'ریفیل ثبت نشد', 'expired' => 'ریفیل بی‌جواب ماند',
    ][$s] ?? ('ریفیل: ' . mb_substr($s, 0, 20));
}

function svRefillable($o) {
    if (!in_array((string)$o['status'], ['done', 'partial'], true) || (string)$o['pid'] === '') return false;
    if (!isset(svRefillIds()[(string)$o['sid']])) return false;
    if ((int)$o['created'] < time() - 400 * 86400) return false;
    if ((string)($o['rfid'] ?? '') === '') return true;
    return svRefillDone((string)$o['rfst']) && (int)$o['rfat'] <= time() - SV_REFILL_GAP;
}

function svRefillErr($raw) {
    $e = mb_strtolower((string)$raw);
    if (str_contains($e, 'api key') || str_contains($e, 'invalid key')) return 'اتصال به پنلِ خدمات مشکل دارد؛ کمی بعد امتحان کنید.';
    if (str_contains($e, 'already') || str_contains($e, 'wait') || str_contains($e, '24') || str_contains($e, 'recently'))
        return 'برای این سفارش همین تازگی ریفیل ثبت شده؛ بعدا دوباره امتحان کنید.';
    if (str_contains($e, 'expire') || str_contains($e, 'period') || str_contains($e, 'days'))
        return 'مهلتِ ضمانتِ این سفارش تمام شده است.';
    if (str_contains($e, 'not') || str_contains($e, 'disabled') || str_contains($e, 'unavailable'))
        return 'فعلا ریزشی برای جبران نیست یا ریفیلِ این سفارش ممکن نیست.';
    return 'پنلِ خدمات ریفیل را نپذیرفت: ' . mb_substr((string)$raw, 0, 120);
}

function svRefillReq($uid, $id) {
    $o = svOrder($id);
    if (!$o || (int)$o['uid'] !== (int)$uid) return [false, 'not_found', 'این سفارش پیدا نشد.', []];
    if (!svRefillable($o)) {
        $why = (string)$o['rfid'] !== '' && !svRefillDone((string)$o['rfst'])
            ? 'ریفیلِ قبلیِ این سفارش هنوز در حالِ انجام است.'
            : ((string)$o['rfid'] !== '' ? 'هر ۲۴ ساعت یک بار می‌شود ریفیل خواست.' : 'این سفارش ریفیل ندارد.');
        return [false, 'no_refill', $why, ['row' => svRow($o)]];
    }
    [$j, $err, $kind] = svHttp(['action' => 'refill', 'order' => (string)$o['pid']], 20);
    $rid = '';
    if (is_array($j)) {
        $one = isset($j[0]) && is_array($j[0]) ? $j[0] : $j;
        $v = $one['refill'] ?? null;
        if (is_array($v)) { $err = (string)($v['error'] ?? 'refill rejected'); $kind = 'api'; }
        elseif ($v !== null && trim((string)$v) !== '' && trim((string)$v) !== '0') $rid = mb_substr(trim((string)$v), 0, 40);
        elseif (isset($one['error'])) { $err = (string)$one['error']; $kind = 'api'; }
        else { $err = 'پاسخِ ناشناخته'; $kind = 'api'; }
    }
    if ($rid === '') {
        $msg = $kind === 'api' ? svRefillErr($err) : 'اتصال به پنلِ خدمات برقرار نشد؛ کمی بعد دوباره بزنید.';
        return [false, 'refill_failed', $msg, ['row' => svRow($o)]];
    }
    svOrderSet((string)$o['id'], ['rfid' => $rid, 'rfst' => 'pending', 'rfat' => time(), 'rfck' => time()]);
    maNoteAdd((int)$uid, "♻️ <b>درخواستِ ریفیل ثبت شد</b>\n\n📦 " . h((string)$o['name']) . "\n🧾 <code>" . h((string)$o['id']) . '</code>');
    return [true, '', '', ['row' => svRow(svOrder((string)$o['id']))]];
}

function svRefillApply(array $o, $st) {
    $s = strtolower(trim(is_string($st) ? $st : ''));
    if (is_array($st)) $s = 'error';
    if ($s === '') return false;
    $was = strtolower((string)$o['rfst']);
    svOrderSet((string)$o['id'], ['rfst' => mb_substr($s, 0, 20), 'rfck' => time()]);
    if ($s === $was || !svRefillDone($s)) return false;
    $ok = in_array($s, ['completed', 'complete', 'done'], true);
    $txt = ($ok ? "♻️ <b>ریفیلِ سفارشِ شما انجام شد</b>" : "♻️ <b>ریفیلِ سفارش انجام نشد</b>") .
           "\n\n📦 " . h((string)$o['name']) . "\n📝 " . h(svRefillText($s)) . "\n🧾 <code>" . h((string)$o['id']) . '</code>';
    maNoteAdd((int)$o['uid'], $txt);
    sendMsg(BOT_TOKEN, (int)$o['uid'], $txt, svOpenKb((string)$o['app'], 'orders'));
    return true;
}

function svRefillSync($limit = 30) {
    $db = svDb();
    if (!$db || !svReady()) return 0;
    $st = $db->prepare("SELECT * FROM svo WHERE rfid <> '' AND rfst NOT IN ('completed','complete','done','rejected','canceled','cancelled','error','failed','expired')
                        AND rfck < :cut ORDER BY rfck ASC LIMIT :n");
    $st->bindValue(':cut', time() - 240, SQLITE3_INTEGER);
    $st->bindValue(':n', max(1, min(100, (int)$limit)), SQLITE3_INTEGER);
    $res = $st->execute();
    $rows = [];
    while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) {
        if ((int)$r['rfat'] < time() - 20 * 86400) { svOrderSet((string)$r['id'], ['rfst' => 'expired', 'rfck' => time()]); continue; }
        $rows[(string)$r['rfid']] = $r;
    }
    if (!$rows) return 0;
    foreach ($rows as $r) svOrderSet((string)$r['id'], ['rfck' => time()]);
    $n = 0;
    if (count($rows) === 1) {
        $rid = (string)array_key_first($rows);
        [$j] = svHttp(['action' => 'refill_status', 'refill' => $rid], 15);
        if (is_array($j) && isset($j['status']) && svRefillApply($rows[$rid], $j['status'])) $n++;
        return $n;
    }
    [$j] = svHttp(['action' => 'refill_status', 'refills' => implode(',', array_keys($rows))], 25);
    if (!is_array($j)) return 0;
    foreach ($j as $k => $one) {
        if (!is_array($one)) continue;
        $rid = (string)($one['refill'] ?? $k);
        if (!isset($rows[$rid]) || !array_key_exists('status', $one)) continue;
        if (svRefillApply($rows[$rid], $one['status'])) $n++;
    }
    return $n;
}

function svOrderCreate($uid, $uname, $app, array $s, $link, $qty, $unit, $total) {
    $db = svDb();
    if (!$db) return '';
    $id = 'sv_' . base_convert((string)time(), 10, 36) . bin2hex(random_bytes(3));
    $st = $db->prepare('INSERT INTO svo (id, uid, uname, app, sid, name, cat, link, qty, unit, total, status, created, updated)
                        VALUES (:id, :u, :un, :a, :s, :n, :c, :l, :q, :p, :t, :st, :at, :at)');
    $st->bindValue(':id', $id, SQLITE3_TEXT);
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $st->bindValue(':un', (string)$uname, SQLITE3_TEXT);
    $st->bindValue(':a', (string)$app, SQLITE3_TEXT);
    $st->bindValue(':s', (string)$s['id'], SQLITE3_TEXT);
    $st->bindValue(':n', $s['name'] !== '' ? $s['name'] : svCleanName($s['pname']), SQLITE3_TEXT);
    $st->bindValue(':c', (string)$s['cat'], SQLITE3_TEXT);
    $st->bindValue(':l', (string)$link, SQLITE3_TEXT);
    $st->bindValue(':q', (int)$qty, SQLITE3_INTEGER);
    $st->bindValue(':p', (float)$unit, SQLITE3_FLOAT);
    $st->bindValue(':t', (float)$total, SQLITE3_FLOAT);
    $st->bindValue(':st', 'new', SQLITE3_TEXT);
    $st->bindValue(':at', time(), SQLITE3_INTEGER);
    return $st->execute() ? $id : '';
}

function svOrderDrop($id) {
    $db = svDb();
    if (!$db) return;
    $st = $db->prepare("DELETE FROM svo WHERE id = :id AND status = 'new'");
    $st->bindValue(':id', (string)$id, SQLITE3_TEXT);
    $st->execute();
}

function svRefund($id, $amount, $note, $tell = true) {
    $db = svDb();
    $amount = (float)floor((float)$amount);
    if (!$db || $amount <= 0) return 0.0;
    $st = $db->prepare('UPDATE svo SET refunded = refunded + :a, updated = :u WHERE id = :id AND refunded + :a <= total + 0.001');
    $st->bindValue(':a', $amount, SQLITE3_FLOAT);
    $st->bindValue(':u', time(), SQLITE3_INTEGER);
    $st->bindValue(':id', (string)$id, SQLITE3_TEXT);
    $st->execute();
    if ($db->changes() < 1) return 0.0;
    $o = svOrder($id);
    if (!$o) return 0.0;
    addBalance((int)$o['uid'], $amount);
    $txt = "💰 <b>مبلغ به کیف پول شما برگشت</b>\n\n" .
           '➕ ' . fmtNum($amount) . " تومان\n" .
           '📦 ' . h((string)$o['name']) . "\n" .
           ($note !== '' ? '📝 ' . h($note) . "\n" : '') .
           '🧾 <code>' . h((string)$id) . '</code>';
    maNoteAdd((int)$o['uid'], $txt);
    if ($tell) sendMsg(BOT_TOKEN, (int)$o['uid'], $txt, svOpenKb((string)$o['app'], 'orders'));
    return $amount;
}


function svBuy($uid, $uname, $app, $sid, $link, $qty, $seen = 0.0, $ans = 0) {
    if (!svAppOn($app)) return [false, 'closed', 'این بخش موقتا بسته است — کمی بعد دوباره امتحان کنید.', []];
    $s = svService($sid);
    if (!$s || $s['app'] !== $app || !$s['active'] || $s['gone'] || !svTypeOk($s['type']))
        return [false, 'bad_item', 'این سرویس دیگر فعال نیست — صفحه را دوباره باز کنید.', []];
    $poll = svKind($s['type']) === 'poll';
    $ans  = (int)$ans;
    if ($poll && ($ans < 1 || $ans > 20))
        return [false, 'bad_answer', 'شماره‌ی گزینه‌ی نظرسنجی را بنویسید (گزینه‌ی اول = ۱).', []];

    $qty = (int)$qty;
    $mn  = max(1, $s['min']);
    $mx  = max($mn, $s['max']);
    if ($qty < $mn || $qty > $mx)
        return [false, 'bad_qty', 'تعداد باید بین ' . fmtNum($mn) . ' و ' . fmtNum($mx) . ' باشد.', []];

    [$link, $lerr] = svLink($app, $link);
    if ($lerr !== '') return [false, 'bad_link', $lerr, []];

    $p1k = svPrice1k($s);
    if ($p1k <= 0) return [false, 'bad_price', 'قیمتِ این سرویس هنوز تنظیم نشده است.', []];
    $total = svTotal($p1k, $qty);
    if ($seen > 0 && abs($seen - $total) > max(1.0, $total * 0.005))
        return [false, 'price_changed', 'قیمت همین الان به‌روز شد — مبلغِ تازه را ببینید و دوباره بزنید.',
                ['total' => $total, 'p' => $p1k]];

    if (svOpenCount($uid) >= SV_OPEN_MAX)
        return [false, 'too_many', 'الان ' . SV_OPEN_MAX . ' سفارشِ در حالِ انجام دارید؛ کمی صبر کنید تا تمام شوند.', []];
    if (maDuplicateOrder($uid, 'sv_' . $app, (string)$sid, $qty, $link . ($poll ? '#' . $ans : ''), 30))
        return [false, 'duplicate', 'همین سفارش چند لحظه پیش ثبت شد — در «سفارش‌ها» ببینید.', []];

    $bal = (float)(getUser($uid)['balance'] ?? 0);
    if ($bal + 0.001 < $total)
        return [false, 'no_balance', 'موجودی کافی نیست. ' . fmtNum(maMoney($total - $bal)) . ' تومان کم دارید.',
                ['balance' => $bal, 'need' => maMoney($total - $bal), 'total' => $total]];

    $id = svOrderCreate($uid, $uname, $app, $s, $link, $qty, $p1k, $total);
    if ($id === '') return [false, 'failed', 'ثبتِ سفارش انجام نشد — دوباره امتحان کنید.', []];
    if (!maDebit($uid, $total)) {
        svOrderDrop($id);
        $bal = (float)(getUser($uid)['balance'] ?? 0);
        return [false, 'no_balance', 'موجودی کافی نیست.', ['balance' => $bal, 'need' => maMoney($total - $bal), 'total' => $total]];
    }

    $params = ['action' => 'add', 'service' => (string)$sid, 'link' => $link, 'quantity' => $qty];
    if ($poll) { $params['answer_number'] = $ans; svOrderSet($id, ['ans' => $ans]); }
    [$j, $err, $kind] = svHttp($params);
    $balNow = fn() => (float)(getUser($uid)['balance'] ?? 0);

    if ($j !== null && isset($j['order']) && trim((string)$j['order']) !== '') {
        svOrderSet($id, ['status' => 'run', 'pst' => 'pending', 'pid' => (string)$j['order'], 'checked' => time()]);
        maNoteAdd($uid,
            "🧾 <b>سفارش ثبت شد</b>\n\n" .
            '📦 ' . h((string)$s['name']) . "\n" .
            '🔢 تعداد: ' . fmtNum($qty) . "\n" .
            '💰 ' . fmtNum($total) . " تومان\n" .
            '🧾 <code>' . h($id) . '</code>');
        return [true, '', '', ['order' => $id, 'row' => svRow(svOrder($id)), 'balance' => $balNow()]];
    }

    if ($kind === 'net') {
        svOrderSet($id, ['status' => 'check', 'err' => mb_substr((string)$err, 0, 300)]);
        adminAlertOnce('svc_check_' . $id,
            "🧩 <b>سفارشِ خدمات نیاز به بررسی دارد</b>\n\n" .
            "پنل در زمانِ مقرر جواب نداد؛ معلوم نیست سفارش آنجا ثبت شد یا نه.\n" .
            '📦 ' . h((string)$s['name']) . ' · ' . fmtNum($qty) . "\n" .
            '🔗 <code>' . h($link) . "</code>\n" .
            '🧾 <code>' . h($id) . "</code>\n\n" .
            "در پنلِ وب ← سفارش‌های خدمات، بعد از نگاه کردن در پنلِ خدمات، «برگشتِ پول» یا «انجام‌شده» بزنید.", 86400);
        return [true, '', '', ['order' => $id, 'row' => svRow(svOrder($id)), 'balance' => $balNow(),
                               'warn' => 'پاسخِ پنل دیر رسید؛ سفارش ثبت شد و بررسی می‌شود. اگر انجام نشد، پول برمی‌گردد.']];
    }

    svOrderSet($id, ['status' => 'failed', 'err' => mb_substr((string)$err, 0, 300)]);
    svRefund($id, $total, 'ثبتِ سفارش در پنلِ خدمات انجام نشد', false);
    $human = $kind === 'api' ? svErrText($err) : 'اتصال به پنلِ خدمات برقرار نشد.';
    if ($kind !== 'api' || str_contains($human, 'موجودی') || str_contains($human, 'کلید'))
        adminAlertOnce('svc_fail_' . substr(md5($human), 0, 8),
            "🧩 <b>ثبتِ سفارشِ خدمات ناموفق بود — پولِ کاربر برگشت</b>\n\n<code>" . h(mb_substr((string)$err, 0, 300)) .
            "</code>\n\n" . h($human), 900);
    return [false, 'failed', $human . ' مبلغ به کیف پولتان برگشت.', ['balance' => $balNow()]];
}


function svApplyStatus(array $o, array $st) {
    if ($o['status'] !== 'run') return false;
    $s = strtolower(trim((string)($st['status'] ?? '')));
    $start = isset($st['start_count']) && is_numeric($st['start_count']) ? (int)$st['start_count'] : (int)$o['start'];
    $rem   = isset($st['remains']) && is_numeric($st['remains']) ? max(0, (int)$st['remains']) : (int)$o['remains'];
    $qty   = (int)$o['qty'];
    $total = (float)$o['total'];
    $id    = (string)$o['id'];
    $uid   = (int)$o['uid'];
    $app   = (string)$o['app'];
    $name  = (string)$o['name'];

    $terminal = '';
    if (in_array($s, ['completed', 'complete', 'success', 'done'], true)) $terminal = 'done';
    elseif ($s === 'partial') $terminal = 'partial';
    elseif (in_array($s, ['canceled', 'cancelled', 'refunded', 'cancel', 'fail', 'failed', 'error'], true)) $terminal = 'canceled';

    if ($terminal === '') {
        svOrderSet($id, ['pst' => mb_substr($s, 0, 30), 'start' => $start, 'remains' => $rem, 'checked' => time()], 'run');
        return false;
    }
    if (!svOrderSet($id, ['status' => $terminal, 'pst' => $s, 'start' => $start,
                          'remains' => $terminal === 'done' ? 0 : $rem, 'checked' => time()], 'run')) return false;

    $kb = svOpenKb($app, 'orders');
    if ($terminal === 'done') {
        $txt = "✅ <b>سفارشِ شما انجام شد</b>\n\n📦 " . h($name) . "\n🔢 تعداد: " . fmtNum($qty) .
               "\n🧾 <code>" . h($id) . '</code>';
        maNoteAdd($uid, $txt);
        sendMsg(BOT_TOKEN, $uid, $txt, $kb);
        if ($total > 0) payReferralCommission($uid, $total);
        return true;
    }
    if ($terminal === 'partial') {
        $rem = min($qty, max(0, $rem));
        $back = $qty > 0 ? floor($total * $rem / $qty) : 0;
        $txt = "🟡 <b>سفارشِ شما ناقص تمام شد</b>\n\n📦 " . h($name) . "\n🔢 انجام‌شده: " . fmtNum($qty - $rem) .
               ' از ' . fmtNum($qty) . "\n🧾 <code>" . h($id) . '</code>';
        maNoteAdd($uid, $txt);
        sendMsg(BOT_TOKEN, $uid, $txt, $kb);
        if ($back > 0) svRefund($id, $back, 'مابقیِ سفارشِ ناقص');
        if ($total - $back > 0) payReferralCommission($uid, $total - $back);
        return true;
    }
    svRefund($id, $total - (float)$o['refunded'], 'سفارش در پنلِ خدمات لغو شد');
    return true;
}

function svSync($limit = 40, $uid = 0, $minAge = 50) {
    $db = svDb();
    if (!$db || !svReady()) return 0;
    $st = $db->prepare("SELECT * FROM svo WHERE status = 'run' AND pid <> '' AND checked < :cut" .
                       ($uid ? ' AND uid = :u' : '') . ' ORDER BY checked ASC LIMIT :n');
    $st->bindValue(':cut', time() - max(5, (int)$minAge), SQLITE3_INTEGER);
    if ($uid) $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $st->bindValue(':n', max(1, min(100, (int)$limit)), SQLITE3_INTEGER);
    $res = $st->execute();
    $rows = [];
    while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) $rows[(string)$r['pid']] = $r;
    if (!$rows) return 0;

    $mark = $db->prepare('UPDATE svo SET checked = :t, tries = tries + 1 WHERE id = :id');
    foreach ($rows as $r) {
        $mark->bindValue(':t', time(), SQLITE3_INTEGER);
        $mark->bindValue(':id', (string)$r['id'], SQLITE3_TEXT);
        $mark->execute(); $mark->reset();
    }

    $n = 0;
    if (count($rows) === 1) {
        $pid = (string)array_key_first($rows);
        [$j] = svHttp(['action' => 'status', 'order' => $pid], 15);
        if (is_array($j) && svApplyStatus($rows[$pid], $j)) $n++;
        return $n;
    }
    [$j] = svHttp(['action' => 'status', 'orders' => implode(',', array_keys($rows))], 25);
    if (!is_array($j)) return 0;
    $multi = false;
    foreach ($rows as $pid => $r) {
        $one = $j[$pid] ?? $j[(int)$pid] ?? null;
        if (!is_array($one)) continue;
        $multi = true;
        if (isset($one['error']) && !isset($one['status'])) continue;
        if (svApplyStatus($r, $one)) $n++;
    }
    if (!$multi) {
        foreach (array_slice($rows, 0, 5, true) as $pid => $r) {
            [$one] = svHttp(['action' => 'status', 'order' => (string)$pid], 15);
            if (is_array($one) && svApplyStatus($r, $one)) $n++;
        }
    }
    return $n;
}

function svTick() {
    $n = 0;
    if (!svReady()) return 0;
    $fx = DATA_DIR . '/.svc_fx_at';
    if (time() - (@filemtime($fx) ?: 0) >= 600) { @touch($fx); svFxRefresh(); }
    try { $n = svSync(60); } catch (Throwable $e) { error_log('[services] sync: ' . $e->getMessage()); }
    try { $n += svRefillSync(40); } catch (Throwable $e) { error_log('[services] refill sync: ' . $e->getMessage()); }
    return $n;
}

function svAdminResolve($id, $how) {
    $o = svOrder($id);
    if (!$o) return [false, 'سفارش پیدا نشد.'];
    if ($how === 'refund') {
        if (!in_array($o['status'], ['check', 'run'], true)) return [false, 'این سفارش باز نیست.'];
        if (!svOrderSet($id, ['status' => 'canceled', 'pst' => 'admin'], (string)$o['status'])) return [false, 'وضعیت همین الان عوض شد.'];
        $back = svRefund($id, (float)$o['total'] - (float)$o['refunded'], 'لغو توسط پشتیبانی');
        return [true, 'سفارش لغو شد و ' . fmtNum($back) . ' تومان به کیف پولِ کاربر برگشت.'];
    }
    if ($how === 'done') {
        if ($o['status'] !== 'check') return [false, 'فقط سفارشِ «در حالِ بررسی» را می‌شود دستی انجام‌شده زد.'];
        if (!svOrderSet($id, ['status' => 'done', 'pst' => 'admin', 'remains' => 0], 'check')) return [false, 'وضعیت همین الان عوض شد.'];
        $txt = "✅ <b>سفارشِ شما انجام شد</b>\n\n📦 " . h((string)$o['name']) . "\n🧾 <code>" . h($id) . '</code>';
        maNoteAdd((int)$o['uid'], $txt);
        sendMsg(BOT_TOKEN, (int)$o['uid'], $txt, svOpenKb((string)$o['app'], 'orders'));
        if ((float)$o['total'] > 0) payReferralCommission((int)$o['uid'], (float)$o['total']);
        return [true, 'سفارش انجام‌شده ثبت شد.'];
    }
    if ($how === 'sync') {
        if ($o['status'] !== 'run' || (string)$o['pid'] === '') return [false, 'این سفارش در پنلِ خدمات ثبت نشده یا باز نیست.'];
        [$j, $err, $kind] = svHttp(['action' => 'status', 'order' => (string)$o['pid']], 15);
        if (!is_array($j)) return [false, $kind === 'api' ? svErrText($err) : $err];
        svApplyStatus($o, $j);
        $n = svOrder($id);
        return [true, 'وضعیت: ' . svStatusText((string)$n['status'], (string)$n['pst'])];
    }
    if ($how === 'cancel') {
        if ($o['status'] !== 'run' || (string)$o['pid'] === '') return [false, 'فقط سفارشِ «در حالِ انجام» که در پنل ثبت شده لغوشدنی است.'];
        [$j, $err, $kind] = svHttp(['action' => 'cancel', 'orders' => (string)$o['pid']], 20);
        if (!is_array($j)) return [false, $kind === 'api' ? 'پنل لغو را نپذیرفت: ' . mb_substr((string)$err, 0, 150) : $err];
        $one = null;
        foreach ((isset($j[0]) ? $j : [$j]) as $x)
            if (is_array($x) && (!isset($x['order']) || (string)$x['order'] === (string)$o['pid'])) { $one = $x; break; }
        $c = $one['cancel'] ?? null;
        if (is_array($c) || empty($c))
            return [false, 'پنل لغو را نپذیرفت: ' . mb_substr((string)(is_array($c) ? ($c['error'] ?? json_encode($c)) : ($one['error'] ?? 'این سفارش لغوشدنی نیست')), 0, 150)];
        svOrderSet($id, ['checked' => 0], 'run');
        return [true, 'درخواستِ لغو به پنل رفت. وقتی پنل لغو را تایید کند، مبلغ (یا مابقیِ انجام‌نشده) خودکار به کیف پولِ کاربر برمی‌گردد.'];
    }
    return [false, 'کارِ ناشناخته'];
}

function svAdminOrders($status, $q, $page, $per) {
    $db = svDb();
    if (!$db) return [[], 0];
    $w = ["status <> 'new'"];
    $b = [];
    if ($status !== '' && $status !== 'all') { $w[] = 'status = :s'; $b[':s'] = $status; }
    if ($q !== '') {
        $w[] = "(id = :qe OR pid = :qe OR CAST(uid AS TEXT) = :qe OR link LIKE :q ESCAPE '\\' OR name LIKE :q ESCAPE '\\')";
        $b[':qe'] = $q;
        $b[':q'] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    }
    $where = ' WHERE ' . implode(' AND ', $w);
    $c = $db->prepare('SELECT COUNT(*) n FROM svo' . $where);
    foreach ($b as $k => $v) $c->bindValue($k, $v, SQLITE3_TEXT);
    $total = (int)(($c->execute()->fetchArray(SQLITE3_ASSOC))['n'] ?? 0);
    $s = $db->prepare('SELECT * FROM svo' . $where . ' ORDER BY created DESC LIMIT :l OFFSET :o');
    foreach ($b as $k => $v) $s->bindValue($k, $v, SQLITE3_TEXT);
    $s->bindValue(':l', max(1, (int)$per), SQLITE3_INTEGER);
    $s->bindValue(':o', max(0, ((int)$page - 1) * (int)$per), SQLITE3_INTEGER);
    $res = $s->execute();
    $out = [];
    while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) $out[] = $r;
    return [$out, $total];
}


function svBoot($app) {
    $c = svCfg()['apps'][$app] ?? [];
    $pub = svPublic($app);
    $links = [];
    if (maReady()) $links['num'] = maUrl();
    foreach (array_keys(svApps()) as $a) if ($a !== $app && svVisible($a)) $links[$a] = svUrl($a);
    return [
        'app'     => $app,
        'title'   => (string)($c['title'] ?? svApps()[$app]['name']),
        'tagline' => (string)($c['tagline'] ?? ''),
        'cats'    => $pub['cats'],
        'items'   => $pub['items'],
        'topup'   => maTopupInfo(),
        'bot'     => (string)botUsername(),
        'links'   => $links,
    ];
}

function svView($app, array $boot) {
    $e    = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $font = maFontCss();
    $tpl  = $app === 'ig' ? svTplIg() : svTplTg();
    return strtr($tpl, [
        '__TITLE__' => $e($boot['title'] ?? ''),
        '__TAG__'   => $e($boot['tagline'] ?? ''),
        '__FONT__'  => $font !== ''
            ? (is_file(__DIR__ . '/fonts/Vazirmatn.woff2')
                ? '<link rel="preload" href="fonts/Vazirmatn.woff2" as="font" type="font/woff2" crossorigin>' . "\n" : '')
              . "<style>\n" . $font . "</style>"
            : '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800;900&display=swap">',
        '__BOOT__'  => json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG |
                                          JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ]);
}

function svServe($key) {
    $app = svAppOfKey($key);
    if ($app === '' || !svVisible($app)) {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        echo maClosedPage();
        exit;
    }
    $html = svView($app, svBoot($app));
    maSecurityHeaders();
    $tag = substr(hash('sha256', $html), 0, 32);
    header('ETag: W/"' . $tag . '"');
    if (strpos((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''), $tag) !== false) { http_response_code(304); exit; }
    maEmit($html);
    exit;
}

function svApiAction($action, array $body, $uid, $uname, $initData) {
    $app = (string)($body['app'] ?? '');
    if (!isset(svApps()[$app])) maApiOut(['ok' => false, 'error' => 'bad_app', 'message' => 'بخشِ نامعتبر.'], 400);
    $bal = fn() => (float)(getUser($uid)['balance'] ?? 0);

    if ($action === 'sv_buy') {
        if (!maRateOk('svbuy', $uid, 8, 60))
            maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'سفارش‌های پشت‌سرهم زیاد شد. یک دقیقه صبر کنید.'], 429);
        if (!maNonceOk($initData, 25))
            maApiOut(['ok' => false, 'error' => 'replay', 'message' => 'سقفِ سفارشِ این نشست پر شد. مینی‌اپ را ببندید و دوباره باز کنید.'], 409);
        if (!isAdmin($uid) && function_exists('masterJoinMissing') && ($miss = masterJoinMissing($uid))) {
            $names = [];
            foreach ($miss as $m) $names[] = (string)($m['title'] ?? '');
            maApiOut(['ok' => false, 'error' => 'join_required',
                      'message' => "برای سفارش، اول در کانال‌های زیر عضو شوید:\n" . implode('، ', $names)], 403);
        }
        [$ok, $err, $msg, $data] = svBuy($uid, $uname, $app, (string)($body['sid'] ?? ''), (string)($body['link'] ?? ''),
                                         (int)maNum($body['qty'] ?? 0), maNum($body['seen'] ?? 0), (int)maNum($body['ans'] ?? 0));
        if (!$ok) {
            $code = ['no_balance' => 402, 'price_changed' => 409, 'duplicate' => 409, 'too_many' => 409, 'closed' => 503][$err] ?? 400;
            maApiOut(['ok' => false, 'error' => $err, 'message' => $msg] + $data, $code);
        }
        maApiOut(['ok' => true] + $data);
    }

    if ($action === 'sv_orders') {
        $list = array_map('svRow', svOrdersFor($uid, $app, 40));
        $stale = false;
        foreach ($list as $r) if ($r['st'] === 'run') { $stale = true; break; }
        maApiOut(['ok' => true, 'list' => $list, 'balance' => $bal()], 200,
            $stale ? function () use ($uid) { svSync(10, $uid, 40); } : null);
    }

    if ($action === 'sv_order') {
        $o = svOrder((string)($body['id'] ?? ''));
        if (!$o || (int)$o['uid'] !== (int)$uid) maApiOut(['ok' => false, 'error' => 'not_found', 'message' => 'این سفارش پیدا نشد.'], 404);
        if ($o['status'] === 'run' && (int)$o['checked'] < time() - 30 && maRateOk('svchk', $uid, 12, 60)) {
            svSync(1, $uid, 30);
            $o = svOrder((string)$o['id']);
        }
        maApiOut(['ok' => true, 'row' => svRow($o), 'balance' => $bal()]);
    }

    if ($action === 'sv_refill') {
        if (!maRateOk('svrf', $uid, 6, 300))
            maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'چند دقیقه صبر کنید و دوباره بزنید.'], 429);
        [$ok, $err, $msg, $data] = svRefillReq($uid, (string)($body['id'] ?? ''));
        if (!$ok) maApiOut(['ok' => false, 'error' => $err, 'message' => $msg] + $data, $err === 'not_found' ? 404 : 409);
        maApiOut(['ok' => true, 'message' => 'درخواستِ ریفیل ثبت شد؛ ریزش‌ها جبران می‌شود.'] + $data);
    }

    maApiOut(['ok' => false, 'error' => 'unknown_action'], 400);
}


function svShopKb($uid) {
    $rows = [[btnCb(UT('shop_orders'), 'shop_orders', 'info', UC('shop_orders'))]];
    $mid = [];
    foreach (['tg', 'ig'] as $a) if ($b = svOpenBtn($a)) $mid[] = $b;
    if ($mid) $rows[] = $mid;
    $num = maReady() ? maOpenKb() : null;
    if ($num) $rows[] = $num['inline_keyboard'][0];
    if (count($rows) === 1) return null;
    $ic = (string)UI('shop_orders');
    if ($ic !== '') $rows[0][0]['icon_custom_emoji_id'] = $ic;
    return inlineKb($rows);
}

function svOrdersText($uid) {
    $all = [];
    foreach (MaOrder::forUser($uid, 8) as $o) {
        $act = function_exists('numGet') ? numGet((string)$o['id']) : null;
        $all[] = [strtotime((string)($o['created_at'] ?? '')) ?: 0,
                  '☎️ <b>' . h((string)($o['item_name'] ?? '')) . '</b>' .
                  (!empty($act['phone']) ? "\n   <code>" . h((string)$act['phone']) . '</code>' : '') .
                  (!empty($act['code']) ? ' · کد: <code>' . h((string)$act['code']) . '</code>' : '') .
                  "\n   " . MaOrder::statusLabel((string)($o['status'] ?? '')) . ' · ' . fmtNum((float)($o['total'] ?? 0)) . ' تومان'];
    }
    foreach (svOrdersFor($uid, '', 8) as $o) {
        $r = svRow($o);
        $all[] = [(int)$o['created'],
                  (svApps()[$o['app']]['emoji'] ?? '🧩') . ' <b>' . h((string)$o['name']) . '</b>' .
                  "\n   🔢 " . fmtNum((int)$o['qty']) . ' · ' . h($r['sx']) .
                  ($r['st'] === 'run' && $r['rm'] >= 0 ? ' (' . fmtNum($r['pc']) . '٪)' : '') .
                  ' · ' . fmtNum((float)$o['total']) . ' تومان'];
    }
    if (!$all) return "📦 <b>سفارش‌های ثبت‌شده</b>\n\nهنوز سفارشی ثبت نکرده‌اید.";
    usort($all, fn($a, $b) => $b[0] <=> $a[0]);
    $t = "📦 <b>سفارش‌های ثبت‌شده</b>\n";
    foreach (array_slice($all, 0, 10) as [$at, $line]) $t .= "\n" . $line . "\n";
    return mb_substr($t, 0, 3900);
}

function svCallback($data, $uid, $chatId, $msgId, $cbId, $isAdmin) {
    if ($data === 'shop_orders') {
        answerCb(BOT_TOKEN, $cbId);
        $rows = [];
        if (maReady() && ($b = maWebAppBtn(UT('my_orders'), 'orders', 'link'))) $rows[] = [$b];
        $line = [];
        foreach (['tg', 'ig'] as $a)
            if ($b = svOpenBtn($a, 'orders', svApps()[$a]['emoji'] . ' سفارش‌های ' . svApps()[$a]['short'])) $line[] = $b;
        if ($line) $rows[] = $line;
        $rows[] = [btnUI('back', 'shop_back', 'nav')];
        $t = svOrdersText($uid);
        if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
        else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
        return true;
    }
    if ($data === 'shop_back') {
        answerCb(BOT_TOKEN, $cbId);
        showShop($uid, $chatId);
        return true;
    }
    if (!str_starts_with($data, 'svadm')) return false;
    if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, '🔒', true); return true; }
    panelMode(true);
    if (preg_match('/^svadm_tog_(tg|ig)$/', $data, $m)) {
        $on = !empty(svCfg()['apps'][$m[1]]['on']);
        svSet(function (&$c) use ($m, $on) { $c['apps'][$m[1]]['on'] = $on ? 0 : 1; });
        answerCb(BOT_TOKEN, $cbId, $on ? '❌ بسته شد' : '✅ باز شد');
    } elseif ($data === 'svadm_sync') {
        $n = svSync(100, 0, 5);
        answerCb(BOT_TOKEN, $cbId, '🔄 ' . fmtNum($n) . ' سفارش به‌روز شد', true);
    } else {
        answerCb(BOT_TOKEN, $cbId);
    }
    svAdmHome($chatId, $msgId);
    return true;
}

function svAdmHome($chatId, $msgId = null) {
    $c  = svCfg();
    $st = svStats();
    $t  = "🧩 <b>مینی‌اپ‌های خدمات تلگرام و اینستاگرام</b>\n\n";
    $t .= '🔌 ' . h(svPanelName()) . ': ' . (svReady() ? '✅ کلید ثبت شده' : '❌ کلیدِ API ثبت نشده') .
          ((int)$c['pbal_at'] > 0 ? ' · موجودی: <b>' . h(rtrim(rtrim(number_format((float)$c['pbal'], 2, '.', ','), '0'), '.')) . ' ' .
                                    h((string)$c['pcur']) . '</b>' : '') . "\n";
    foreach (svApps() as $a => $ai) {
        $t .= $ai['emoji'] . ' ' . h($ai['name']) . ': ' . (!empty($c['apps'][$a]['on']) ? '✅ باز' : '❌ بسته') .
              ' · سرویسِ فعال: <b>' . fmtNum($st[$a . '_on']) . '</b> از ' . fmtNum($st[$a]) . "\n";
    }
    $t .= "\n⏳ در حالِ انجام: <b>" . fmtNum($st['run']) . '</b> · 🔎 نیاز به بررسی: <b>' . fmtNum($st['check']) . "</b>\n";
    $t .= '📅 امروز: <b>' . fmtNum($st['today']) . '</b> سفارش · <b>' . fmtNum($st['sum']) . "</b> تومان\n\n";
    $t .= "آدرس و کلیدِ API، سود و روشن کردنِ سرویس‌ها در <b>پنلِ وب ← خدمات</b> است.";
    $rows = [];
    foreach (svApps() as $a => $ai)
        $rows[] = [btnCb((!empty($c['apps'][$a]['on']) ? '❌ بستنِ ' : '✅ باز کردنِ ') . $ai['name'], 'svadm_tog_' . $a, 'info')];
    $rows[] = [btnCb('🔄 به‌روزرسانیِ وضعیتِ سفارش‌ها', 'svadm_sync', 'admin')];
    $test = [];
    foreach (['tg', 'ig'] as $a) if ($b = svOpenBtn($a, '', '🧪 ' . svApps()[$a]['short'])) $test[] = $b;
    if ($test) $rows[] = $test;
    $rows[] = [btnCb(UT('back'), 'maadm_home', 'nav')];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

require_once __DIR__ . '/miniapp_view_tgs.php';
require_once __DIR__ . '/miniapp_view_igs.php';
