<?php
/**
 * 🌐 سایتِ فروشگاه — صفحه‌ی اصلیِ دامنه (https://DOMAIN/)
 *
 * یک ویترینِ کامل برای همه‌ی چیزهایی که ربات می‌فروشد:
 *
 *   ⭐️ استارز تلگرام      — بسته‌های آماده + ماشین‌حسابِ مقدارِ دلخواه
 *   💎 پریمیوم تلگرام     — ۳/۶/۱۲ ماهه
 *   🎁 گیفت تلگرام        — همه‌ی گیفت‌های مینی‌اپ
 *   ☎️ شماره مجازی        — کشورها + «از چند تومان»
 *   📸 فالوور اینستاگرام  — هر محصول/آیتمی که اسمش «اینستا» دارد
 *   💫 رشد کانال تلگرام   — ری‌اکشن، بازدید، ممبر
 *
 * هیچ قیمتی اینجا نوشته نشده: همه از همان کاتالوگی می‌آید که ربات و
 * مینی‌اپ‌ها می‌فروشند (maGet/maItemPrice/Product::all) — پس قیمتِ
 * سایت همیشه همان قیمتِ فاکتور است، با سود و نرخِ زنده.
 *
 * خرید همچنان داخلِ ربات انجام می‌شود: کیف پول، کارت‌به‌کارت، درگاهِ
 * کریپتو و تحویلِ خودکار آن‌جا هستند و این صفحه هیچ‌کدام را تکرار
 * نمی‌کند. سایت فقط می‌خواند؛ نه سفارشی می‌سازد، نه موجودی‌ای می‌بیند،
 * نه چیزی از کاربر می‌گیرد — پس هیچ سطحِ حمله‌ی تازه‌ای باز نمی‌شود.
 *
 * تنظیمات اختیاری (config.local.php یا متغیر محیطی):
 *   define('SITE_NAME',    'نام فروشگاه');
 *   define('SITE_BOT',     'ShopBot');      // بدون @ — خالی یعنی از خودِ ربات می‌پرسیم
 *   define('SITE_SUPPORT', 'SupportUser');  // آیدی پشتیبانی بدون @
 *   define('SITE_CHANNEL', 'ShopChannel');  // آیدی کانال بدون @
 *   define('SITE_NOTICE',  'متن نوار بالای سایت');
 */

if (is_file(__DIR__ . '/config.local.php')) require_once __DIR__ . '/config.local.php';

if (!defined('SITE_NAME'))    define('SITE_NAME',    getenv('SITE_NAME') ?: 'استار شاپ');
if (!defined('SITE_BOT'))     define('SITE_BOT',     getenv('SITE_BOT') ?: '');
if (!defined('SITE_SUPPORT')) define('SITE_SUPPORT', getenv('SITE_SUPPORT') ?: '');
if (!defined('SITE_CHANNEL')) define('SITE_CHANNEL', getenv('SITE_CHANNEL') ?: '');
if (!defined('SITE_NOTICE'))  define('SITE_NOTICE',  getenv('SITE_NOTICE') ?: '');

/**
 * کتابخانه‌ی ربات فقط برای خواندنِ کاتالوگ بار می‌شود — همان کاری که
 * admin_panel.php می‌کند. اگر به هر دلیلی بالا نیامد (افزونه‌ی sqlite3
 * خاموش، فایلِ ناقص…) سایت نمی‌خوابد: همه‌ی بخش‌ها می‌آیند، فقط به‌جای
 * قیمت «استعلام در ربات» نوشته می‌شود.
 */
$siteLib = false;
try {
    // require یک فایلِ نبود خطای مرگبار است، نه استثنا — پس اول نگاه می‌کنیم
    if (is_file(__DIR__ . '/bot_master_membership.php')) {
        if (!defined('MEMBERSHIP_LIB_ONLY')) define('MEMBERSHIP_LIB_ONLY', true);
        require_once __DIR__ . '/bot_master_membership.php';
        $siteLib = function_exists('maGet') && function_exists('maItemPrice');
    }
} catch (Throwable $e) {
    error_log('[site] کتابخانه‌ی ربات بار نشد: ' . $e->getMessage());
}

require_once __DIR__ . '/site_view.php';

// ============================================================
// 📦 داده‌ی صفحه
// ============================================================

/** آیا این متن مالِ اینستاگرام است؟ — اسمِ آیتم یا دسته‌اش */
function siteIsInsta($s) {
    $s = mb_strtolower((string)$s);
    return str_contains($s, 'اینستا') || str_contains($s, 'instagram') || str_contains($s, 'insta');
}

/** نوعِ خدمتِ رشد، از رویِ اسم — فقط برای تبِ مناسب در صفحه */
function siteGrowthKind($s) {
    $s = mb_strtolower((string)$s);
    if (preg_match('/فالو|follow|ممبر|member|عضو/u', $s)) return 'follow';
    if (preg_match('/لایک|like|ری‌اکشن|ریاکشن|react/u', $s)) return 'like';
    if (preg_match('/بازدید|ویو|view|پلی|play/u', $s))     return 'view';
    if (preg_match('/کامنت|comment/u', $s))                 return 'comment';
    return 'other';
}

/**
 * یک آیتمِ مینی‌اپ → یک پیشنهادِ قابلِ نمایش.
 *
 * آیتمِ «تعدادی» (ری‌اکشن ۱۴۰ تومانی، فالوور ۳۰۰ تومانی) قیمتِ هر ۱ عدد
 * را دارد؛ روی سایت «هر ۱۰۰۰ عدد» خواناتر است و در بازارِ همین خدمات
 * هم همین رسم است. آیتمی که حداقلش زیرِ ۱۰ است (مثلا تون) همان «هر ۱».
 */
function siteOfferFromItem($i, $catName = '') {
    $ask = (string)($i['ask'] ?? 'none');
    $bulk = str_starts_with($ask, 'qty') && (float)($i['min'] ?? 1) >= 10;
    $price = (float)maItemPrice($i);
    return [
        'name'  => (string)$i['name'],
        'desc'  => (string)($i['desc'] ?? ''),
        'emoji' => (string)($i['emoji'] ?? ''),
        'badge' => (string)($i['badge'] ?? ''),
        'cat'   => (string)$catName,
        'price' => $bulk ? $price * 1000 : $price,
        'unit_price' => $price,
        'per'   => $bulk ? 1000 : 1,
        'unit'  => (string)($i['unit'] ?? ''),
        'cur'   => 'تومان',
        'min'   => (float)($i['min'] ?? 1),
        'max'   => (float)($i['max'] ?? 1),
        'qty'   => str_starts_with($ask, 'qty') ? 1 : 0,
        'kind'  => siteGrowthKind($i['name'] . ' ' . $catName),
    ];
}

/** محصولِ فروشگاهِ اصلی (ممبر/فالوورِ پنلی) → یک پیشنهادِ قابلِ نمایش */
function siteOfferFromProduct($p) {
    $f = $p['flow'] ?? [];
    $flow = !empty($f['on']) && !empty($f['ask_qty']);
    $per = $flow ? max(1, (int)($f['per'] ?? 1000)) : 1;
    return [
        'name'  => (string)$p['name'],
        'desc'  => (string)($p['desc'] ?? ''),
        'emoji' => (string)($p['emoji'] ?? ''),
        'badge' => '',
        'cat'   => '',
        'price' => (float)$p['price'],
        'unit_price' => (float)$p['price'] / $per,
        'per'   => $per,
        'unit'  => $flow ? 'عدد' : '',
        'cur'   => (string)($p['currency'] ?? 'تومان'),
        'min'   => $flow ? (float)($f['min'] ?? 1) : 1,
        'max'   => $flow ? (float)($f['max'] ?? 1) : 1,
        'qty'   => $flow ? 1 : 0,
        'kind'  => siteGrowthKind($p['name']),
    ];
}

function siteData($lib) {
    $d = [
        'live'      => false,
        'name'      => (string)SITE_NAME,
        'bot'       => ltrim((string)SITE_BOT, '@'),
        'support'   => ltrim((string)SITE_SUPPORT, '@'),
        'channel'   => ltrim((string)SITE_CHANNEL, '@'),
        'notice'    => (string)SITE_NOTICE,
        'stars'     => [],   // بسته‌های آماده
        'star_unit' => 0.0,  // قیمتِ هر ۱ استارز (مقدارِ دلخواه)
        'star_min'  => 50,
        'star_max'  => 1000000,
        'premium'   => [],
        'gifts'     => [],
        'coins'     => [],
        'countries' => [],
        'num_total' => 0,
        'num_from'  => 0.0,
        'insta'     => [],
        'tgrowth'   => [],
        'stats'     => ['users' => 0, 'orders' => 0],
        'pay'       => ['wallet' => 1, 'card' => 0, 'crypto' => 0, 'ton' => 0],
    ];
    if (!$lib) return $d;

    // ⚡️ صفحه هیچ‌وقت پشت شبکه نمی‌ماند — همان قاعده‌ی مینی‌اپ‌ها: هرچه
    //    در کش هست (حتی کهنه) همان. تازه‌سازی بعد از بستنِ پاسخ است.
    if (function_exists('maNoNet')) maNoNet(true);
    $d['live'] = true;

    if ($d['bot'] === '' && BOT_TOKEN !== '' && function_exists('botUsername')) {
        try { $d['bot'] = (string)botUsername(); } catch (Throwable $e) { }
    }

    // ── 🌟 خدمات تلگرام: استارز، پریمیوم، گیفت، ارز ──
    $tg = maGet('tg');
    if (!empty($tg['on'])) {
        $catOn = []; $catName = [];
        foreach ((array)($tg['cats'] ?? []) as $c) {
            $catOn[(string)$c['id']]   = !empty($c['on']);
            $catName[(string)$c['id']] = (string)($c['name'] ?? '');
        }
        foreach ((array)($tg['items'] ?? []) as $i) {
            if (empty($i['on']) || trim((string)($i['name'] ?? '')) === '') continue;
            $cid = (string)($i['cat'] ?? '');
            if (isset($catOn[$cid]) && !$catOn[$cid]) continue;
            $auto = (string)($i['auto'] ?? '');
            $cn   = $catName[$cid] ?? '';
            $ord  = (int)($i['order'] ?? 99);

            if ($auto === 'stars' || $cid === 'c_star') {
                if (str_starts_with((string)($i['ask'] ?? ''), 'qty')) {
                    $d['star_unit'] = (float)maItemPrice($i);
                    $d['star_min']  = max(1, (int)($i['min'] ?? 50));
                    $d['star_max']  = max($d['star_min'], (int)($i['max'] ?? 1000000));
                    continue;
                }
                $d['stars'][] = [
                    'name' => (string)$i['name'], 'badge' => (string)($i['badge'] ?? ''),
                    'desc' => (string)($i['desc'] ?? ''), 'price' => (float)maItemPrice($i),
                    'qty'  => (int)($i['stars'] ?? $i['auto_qty'] ?? 0), 'o' => $ord,
                ];
            } elseif ($auto === 'premium' || $cid === 'c_prem') {
                $d['premium'][] = [
                    'name' => (string)$i['name'], 'badge' => (string)($i['badge'] ?? ''),
                    'desc' => (string)($i['desc'] ?? ''), 'price' => (float)maItemPrice($i),
                    'months' => (int)($i['premium'] ?? $i['auto_qty'] ?? 0), 'o' => $ord,
                ];
            } elseif ($auto === 'gift' || $cid === 'c_gift') {
                $d['gifts'][] = [
                    'name' => (string)$i['name'], 'badge' => (string)($i['badge'] ?? ''),
                    'emoji' => (string)($i['emoji'] ?? '🎁'), 'price' => (float)maItemPrice($i),
                    'stars' => (int)($i['stars'] ?? 0), 'o' => $ord,
                ];
            } elseif ($cid === 'c_coin' || $auto === 'ton') {
                $d['coins'][] = [
                    'name' => (string)$i['name'], 'unit' => (string)($i['unit'] ?? ''),
                    'price' => (float)maItemPrice($i), 'o' => $ord,
                ];
            } elseif (siteIsInsta($i['name'] . ' ' . $cn)) {
                $d['insta'][] = siteOfferFromItem($i, $cn) + ['o' => $ord];
            } else {
                $d['tgrowth'][] = siteOfferFromItem($i, $cn) + ['o' => $ord];
            }
        }
    }

    // ── 💫 ری‌اکشن و استوری — و هر دسته‌ی اینستاگرامی که ادمین اضافه کرده ──
    $re = maGet('react');
    if (!empty($re['on'])) {
        $catOn = []; $catName = [];
        foreach ((array)($re['cats'] ?? []) as $c) {
            $catOn[(string)$c['id']]   = !empty($c['on']);
            $catName[(string)$c['id']] = (string)($c['name'] ?? '');
        }
        foreach ((array)($re['items'] ?? []) as $i) {
            if (empty($i['on']) || trim((string)($i['name'] ?? '')) === '') continue;
            $cid = (string)($i['cat'] ?? '');
            if (isset($catOn[$cid]) && !$catOn[$cid]) continue;
            $cn = $catName[$cid] ?? '';
            $o  = siteOfferFromItem($i, $cn) + ['o' => (int)($i['order'] ?? 99)];
            if (siteIsInsta($i['name'] . ' ' . $cn)) $d['insta'][] = $o;
            else $d['tgrowth'][] = $o;
        }
    }

    // ── 🛍 محصول‌های فروشگاهِ اصلی (ممبر، فالوورِ پنلی، …) ──
    if (class_exists('Product')) {
        foreach (Product::all() as $p) {
            if (empty($p['active']) || trim((string)($p['name'] ?? '')) === '') continue;
            if (Product::isFull($p)) continue;
            $o = siteOfferFromProduct($p) + ['o' => (int)($p['order'] ?? 99)];
            if (siteIsInsta($p['name'])) $d['insta'][] = $o;
            else $d['tgrowth'][] = $o;
        }
    }

    // ── ☎️ شماره مجازی ──
    $num = maGet('num');
    if (!empty($num['on'])) {
        $min = 0.0;
        foreach (maCatsPublic($num, 'num') as $c) {
            if ((int)$c['n'] <= 0) continue;
            $d['countries'][] = ['name' => $c['name'], 'flag' => $c['emoji'], 'n' => (int)$c['n'], 'from' => (float)$c['from']];
            if ($c['from'] > 0 && ($min <= 0 || $c['from'] < $min)) $min = (float)$c['from'];
        }
        $d['num_total'] = maCountOn($num);
        $d['num_from']  = $min;
    }

    foreach (['stars', 'premium', 'gifts', 'coins', 'insta', 'tgrowth'] as $k) {
        usort($d[$k], fn($x, $y) => $x['o'] <=> $y['o']);
    }

    // ── 💳 روش‌های پرداخت — همان‌هایی که واقعا روشن‌اند ──
    if (function_exists('maTopupInfo')) {
        $t = maTopupInfo();
        $d['pay']['card']   = (int)$t['on'];
        $d['pay']['crypto'] = (int)$t['gw'];
    }
    foreach ($d['coins'] as $c) if (stripos($c['unit'] . $c['name'], 'TON') !== false) $d['pay']['ton'] = 1;

    // ── 📊 آمارِ واقعی — نه عددِ ساختگی. زیرِ صد نشان داده نمی‌شود. ──
    try {
        if (function_exists('countUsers')) $d['stats']['users'] = (int)countUsers();
        if (function_exists('maOrdersDb') && ($db = maOrdersDb())) {
            $d['stats']['orders'] = (int)$db->querySingle("SELECT COUNT(*) FROM orders WHERE status IN ('paid','done')");
        }
    } catch (Throwable $e) { }

    return $d;
}

// ============================================================
// 🎯 ورودی
// ============================================================

/**
 * داده‌ی صفحه ۳۰ ثانیه کش می‌شود: چند ده آیتم، قیمتِ هرکدام، شمارشِ
 * کاربرها — ارزان نیست که برای هر بازدیدکننده از نو ساخته شود، و
 * قیمت‌ها آن‌قدر سریع عوض نمی‌شوند.
 */
$data = null;
$needFresh = false;
if ($siteLib) {
    try {
        $needFresh = function_exists('pxVal') && maCacheGet('px_pairs', (int)pxVal('ttl', 120)) === null;
        $data = maCacheGet('site_boot', 30);
        if (!is_array($data)) {
            $data = siteData(true);
            maCachePut('site_boot', $data);
        }
    } catch (Throwable $e) {
        error_log('[site] ' . $e->getMessage());
        $data = null;
    }
}
if (!is_array($data)) $data = siteData(false);

siteHeaders();
$html = siteView($data);

// جواب رفت؛ حالا اگر قیمت‌ها کهنه بودند برای بازدیدکننده‌ی بعدی تازه‌شان کن.
// closeRequest() ربات به کار نمی‌آید: Content-Type را JSON می‌گذارد.
if ($needFresh) {
    ignore_user_abort(true);
    header('Content-Length: ' . strlen($html));
    header('Connection: close');
    echo $html;
    if (function_exists('fastcgi_finish_request'))      fastcgi_finish_request();
    elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
    else { while (ob_get_level() > 0) @ob_end_flush(); @flush(); }

    maNoNet(false);
    try {
        if (function_exists('pxFetch'))        pxFetch(true);
        if (function_exists('axRatesRefresh')) axRatesRefresh();
    } catch (Throwable $e) { error_log('[site-warm] ' . $e->getMessage()); }
    exit;
}
echo $html;
