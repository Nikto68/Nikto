<?php
/**
 * 🔐 حسابِ کاربریِ سایت — ورود با تایید داخلِ ربات + API خریدِ مستقیم
 *
 * سایت (index.php) هیچ منطقِ خرید/پرداختِ جدیدی ندارد. همان maApi()ِ
 * مینی‌اپ‌ها را صدا می‌زند؛ فقط به‌جای امضای initDataِ تلگرام، کاربر را از
 * نشستِ (session) سایت می‌شناسد. پس قیمت، کیف پول، کد تخفیف، ضدِ تکرار،
 * سقفِ نرخ، تحویلِ خودکار و شماره مجازی همه همان مسیرِ آزموده‌اند.
 *
 * 🔑 ورود — بدونِ رمز، بدونِ ویجت، بدونِ /setdomain:
 *
 *   ۱) سایت یک درخواستِ ورود می‌سازد (۱۲۸ بیت تصادفی + یک کدِ ۴ رقمیِ نمایشی)
 *      و آن را به نشستِ همان مرورگر می‌بندد.
 *   ۲) کاربر روی t.me/<bot>?start=wl_<R> می‌زند؛ ربات کد و دستگاه را نشان
 *      می‌دهد و می‌پرسد «خودتان بودید؟» — با هشدارِ صریح.
 *   ۳) با «تایید»، درخواست به uid گره می‌خورد؛ سایت که هر دو ثانیه می‌پرسد،
 *      فقط در همان نشست واردش می‌کند و درخواست همان لحظه پاک می‌شود.
 *
 *   حدس‌زدنی نیست (R ۱۲۸ بیتی است)، یک‌بارمصرف است، ۵ دقیقه عمر دارد، و
 *   تنها راهِ سوءاستفاده — فرستادنِ لینک برای قربانی — را هم پیامِ ربات
 *   با نمایشِ کد و دستگاه و هشدارِ صریح می‌بندد. بعد از هر ورود، ربات
 *   خبر می‌دهد و دکمه‌ی «خروج از همه‌ی نشست‌های سایت» را کنارش می‌گذارد.
 *
 * هم ربات (برای /start و دکمه‌ها) و هم سایت این فایل را بار می‌کنند.
 */

if (!defined('SL_TTL'))          define('SL_TTL', 300);     // عمرِ هر درخواستِ ورود (ثانیه)
if (!defined('SL_SESSION_DAYS')) define('SL_SESSION_DAYS', 30);

// ============================================================
// 🎫 درخواست‌های ورود — مشترک بین سایت و ربات
// ============================================================

/** «Chrome · Windows» — فقط برای اینکه کاربر در ربات بفهمد کدام دستگاه است */
function slDevice($ua) {
    $ua = (string)$ua;
    $b = 'مرورگر';
    foreach (['Edg/' => 'Edge', 'OPR/' => 'Opera', 'SamsungBrowser' => 'Samsung', 'Firefox/' => 'Firefox',
              'Chrome/' => 'Chrome', 'Safari/' => 'Safari'] as $k => $v) {
        if (stripos($ua, $k) !== false) { $b = $v; break; }
    }
    $o = '';
    foreach (['iPhone' => 'iPhone', 'iPad' => 'iPad', 'Android' => 'Android', 'Windows' => 'Windows',
              'Mac OS' => 'Mac', 'Linux' => 'Linux'] as $k => $v) {
        if (stripos($ua, $k) !== false) { $o = $v; break; }
    }
    return $o !== '' ? $b . ' · ' . $o : $b;
}

function slCreate($ip, $ua) {
    $r    = bin2hex(random_bytes(16));
    $code = (string)random_int(1000, 9999);
    mutate('site_login', function (&$a) use ($r, $code, $ip, $ua) {
        $now = time();
        foreach ($a as $k => $v) {
            if (!is_array($v) || ($now - (int)($v['at'] ?? 0)) > SL_TTL * 2) unset($a[$k]);
        }
        $a[$r] = ['at' => $now, 'code' => $code, 'st' => 'pending', 'ip' => (string)$ip, 'dev' => slDevice($ua)];
    });
    return [$r, $code];
}

function slGet($r) {
    $a = load('site_login', true);
    $x = $a[$r] ?? null;
    if (!is_array($x) || (time() - (int)($x['at'] ?? 0)) > SL_TTL) return null;
    return $x;
}

/**
 * سایت می‌پرسد: تایید شد؟ — «ok» و «no» همان لحظه پاک می‌شوند تا یک
 * درخواست هرگز دو بار مصرف نشود.
 */
function slConsume($r) {
    $out = null;
    mutate('site_login', function (&$a) use ($r, &$out) {
        $x = $a[$r] ?? null;
        if (!is_array($x)) return;
        if ((time() - (int)($x['at'] ?? 0)) > SL_TTL) { unset($a[$r]); return; }
        $out = $x;
        if ($x['st'] !== 'pending') unset($a[$r]);
    });
    return $out;
}

/** «خروج از همه‌ی نشست‌های سایت» — هر نشستی که قبل از این لحظه وارد شده، باطل می‌شود */
function slRevoke($uid) {
    mutate('site_epoch', function (&$a) use ($uid) { $a[(string)(int)$uid] = microtime(true); });
}
function slRevokedAt($uid) {
    return (float)(load('site_epoch')[(string)(int)$uid] ?? 0);
}

// ============================================================
// 🤖 سمتِ ربات — /start wl_… و دکمه‌های تایید
// ============================================================

function slBotStart($uid, $chatId, $r, $uname, $fname) {
    $u = getUser($uid);
    if ($u && !empty($u['banned'])) { sendMsg(BOT_TOKEN, $chatId, T('banned')); return; }

    $x = preg_match('/^[a-f0-9]{32}$/', (string)$r) ? slGet($r) : null;
    if (!$x || $x['st'] !== 'pending') {
        sendMsg(BOT_TOKEN, $chatId,
            "⌛️ <b>این لینکِ ورود منقضی شده یا قبلا استفاده شده.</b>\n\n" .
            "دوباره در سایت روی «ورود با تلگرام» بزنید.");
        return;
    }

    sendMsg(BOT_TOKEN, $chatId,
        "🔐 <b>ورود به سایت</b>\n\n" .
        "درخواستِ ورود به سایت با حسابِ تلگرامِ شما ثبت شده.\n\n" .
        "🔢 کدِ روی صفحه‌ی سایت: <b>" . h($x['code']) . "</b>\n" .
        "💻 دستگاه: " . h($x['dev']) . "\n\n" .
        "⚠️ فقط اگر <b>خودتان همین حالا</b> روی سایت «ورود» را زده‌اید و کدِ بالا با کدِ روی صفحه یکی است، تایید کنید.\n" .
        "اگر کسی این لینک را برایتان فرستاده، تایید نکنید — با تایید، او به کیف پولِ شما دسترسی پیدا می‌کند.",
        inlineKb([
            [btnCb('✅ تایید ورود', 'wlok_' . $r, 'confirm')],
            [btnCb('❌ من نبودم', 'wlno_' . $r, 'cancel')],
        ]));
}

/** دکمه‌های تایید/رد/خروج — true یعنی این دکمه مالِ همین‌جا بود */
function slBotCallback($data, $uid, $chatId, $msgId, $cbId, $uname, $fname) {
    if ($data === 'wlout') {
        slRevoke($uid);
        answerCb(BOT_TOKEN, $cbId, '✅ از همه‌ی نشست‌های سایت خارج شدید.', true);
        if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, "🚪 از همه‌ی نشست‌های سایت خارج شدید.");
        return true;
    }
    if (!preg_match('/^wl(ok|no)_([a-f0-9]{32})$/', (string)$data, $m)) return false;

    $r  = $m[2];
    $ok = ($m[1] === 'ok');
    $res = 'expired';
    mutate('site_login', function (&$a) use ($r, $ok, $uid, $uname, $fname, &$res) {
        $x = $a[$r] ?? null;
        if (!is_array($x) || (time() - (int)($x['at'] ?? 0)) > SL_TTL) { unset($a[$r]); return; }
        if ($x['st'] !== 'pending') { $res = 'used'; return; }
        $a[$r]['st'] = $ok ? 'ok' : 'no';
        if ($ok) {
            $a[$r]['uid']   = (int)$uid;
            $a[$r]['uname'] = (string)$uname;
            $a[$r]['fname'] = (string)$fname;
        }
        $res = $ok ? 'ok' : 'no';
    });

    if ($res === 'ok') {
        answerCb(BOT_TOKEN, $cbId, '✅ وارد سایت شدید');
        if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId,
            "✅ <b>وارد سایت شدید.</b>\n\nبه صفحه‌ی سایت برگردید — خودکار وارد شده‌اید.\n\n" .
            "اگر این شما نبودید، همین حالا دکمه‌ی زیر را بزنید.",
            inlineKb([[btnCb('🚪 خروج از همه‌ی نشست‌های سایت', 'wlout', 'cancel')]]));
    } elseif ($res === 'no') {
        answerCb(BOT_TOKEN, $cbId, 'رد شد');
        if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, "❌ درخواستِ ورود رد شد. کسی وارد نشد.");
    } else {
        answerCb(BOT_TOKEN, $cbId, '⌛️ این درخواست منقضی شده. دوباره از سایت وارد شوید.', true);
        if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, "⌛️ این درخواستِ ورود منقضی شده.");
    }
    return true;
}

// ============================================================
// 🍪 نشستِ سایت
// ============================================================

function slHttps() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

/**
 * نشست‌ها کنارِ بقیه‌ی داده‌ها (DATA_DIR/site_sessions) می‌مانند، نه در پوشه‌ی
 * سراسریِ PHP: روی هاستِ اشتراکی، پاک‌کنِ سراسری بعد از ۲۴ دقیقه همه را
 * پاک می‌کرد و کاربر هر روز دوباره باید وارد می‌شد. پاک‌سازیِ خودمان را
 * هم خودمان انجام می‌دهیم، گاه‌به‌گاه.
 */
function slSessionStart() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $life = SL_SESSION_DAYS * 86400;
    $dir  = DATA_DIR . '/site_sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
        // لایه‌ی دوم، جدا از قفلِ خودِ DATA_DIR: فهرست و محتوای نشست‌ها هرگز از وب خوانده نشود
        @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
        @file_put_contents($dir . '/index.html', '');
    }
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir);
        if (random_int(1, 200) === 1) {
            foreach ((array)glob($dir . '/sess_*') as $f) {
                if (is_file($f) && (time() - (int)@filemtime($f)) > $life) @unlink($f);
            }
        }
    }
    ini_set('session.gc_maxlifetime', (string)$life);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('shop_sid');
    session_set_cookie_params([
        'lifetime' => $life, 'path' => '/', 'httponly' => true,
        'samesite' => 'Lax', 'secure' => slHttps(),
    ]);
    session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

/**
 * کوکیِ «واردشده‌ام» — بی‌محتوا و خواندنی برای JS، فقط تا صفحه بداند
 * لازم است همان اول نشست را بپرسد. مهمان‌ها هیچ نشستی نمی‌سازند مگر
 * وقتی واقعا دکمه‌ی ورود یا خرید را بزنند؛ وگرنه روی هاستِ اشتراکی هر
 * بازدید یک فایلِ نشست می‌شد و سقفِ تعدادِ فایل زود پر می‌شد.
 */
function slFlag($on) {
    setcookie('shop_in', $on ? '1' : '', [
        'expires' => $on ? time() + SL_SESSION_DAYS * 86400 : time() - 3600,
        'path' => '/', 'secure' => slHttps(), 'httponly' => false, 'samesite' => 'Lax',
    ]);
}

/** کاربرِ واردشده — یا null. نشستی که بعد از ورودش «خروج از همه» زده شده، باطل است. */
function slUser() {
    $uid = (int)($_SESSION['uid'] ?? 0);
    if ($uid <= 0) return null;
    if ((float)($_SESSION['at'] ?? 0) <= slRevokedAt($uid)) {
        unset($_SESSION['uid'], $_SESSION['uname'], $_SESSION['fname'], $_SESSION['at']);
        return null;
    }
    return ['id' => $uid, 'username' => (string)($_SESSION['uname'] ?? ''),
            'first_name' => (string)($_SESSION['fname'] ?? ''), 'last_name' => ''];
}

function slPublicUser() {
    $s = slUser();
    if (!$s) return null;
    $u = getUser($s['id']);
    return [
        'id'      => $s['id'],
        'name'    => $s['first_name'] !== '' ? $s['first_name'] : ($s['username'] !== '' ? '@' . $s['username'] : 'کاربر'),
        'uname'   => $s['username'],
        'balance' => (float)($u['balance'] ?? 0),
    ];
}

/**
 * کاربری که maApi() باید به‌جای امضای initData بپذیرد.
 *
 * فقط slApi() همین درخواست آن را پر می‌کند — هیچ پارامتری از بیرون به
 * آن راه ندارد؛ پس درخواستِ عادیِ مینی‌اپ یا وبهوک هرگز از این در رد نمی‌شود.
 */
function siteApiUser($set = null) {
    static $u = null;
    if ($set !== null) $u = $set;
    return $u;
}

// ============================================================
// 🛰 API سایت — index.php?sapi=<op>
// ============================================================

function slOut($d, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * از سایت فقط همین‌ها — و هرکدام فقط با همین کلیدِ اپ. مسیرهای مدیریتی
 * (adm_*) عمدا اینجا نیستند: مدیریت از داخلِ تلگرام، نه از یک کوکیِ مرورگر.
 */
function slAllowed() {
    $shop = ['tg', 'num', 'react'];
    return [
        'me_all' => ['unified'], 'topup' => ['unified'],
        'order' => $shop, 'coupon_check' => $shop,
        'num_state' => ['num'], 'num_code' => ['num'], 'num_cancel' => ['num'],
        'num_repeat' => ['num'], 'num_orders' => ['num'], 'num_cat' => ['num'], 'num_search' => ['num'],
    ];
}

function slApi($op) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') slOut(['ok' => false, 'error' => 'bad_method'], 405);
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) slOut(['ok' => false, 'error' => 'too_large'], 413);

    // 🛡 فقط از همین دامنه — مرورگر Origin را جعل‌ناپذیر می‌فرستد
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        $port = parse_url($origin, PHP_URL_PORT);
        $from = (string)parse_url($origin, PHP_URL_HOST) . ($port ? ':' . $port : '');
        if (strcasecmp($from, (string)($_SERVER['HTTP_HOST'] ?? '')) !== 0)
            slOut(['ok' => false, 'error' => 'bad_origin'], 403);
    }

    $ip = maClientIp();
    if (!maRateOk('sip', $ip, 300, 60))
        slOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'درخواست‌ها زیاد است، کمی صبر کنید.'], 429);

    slSessionStart();

    // 🛡 CSRF — جز «hello» که خودِ توکن را می‌دهد
    if ($op !== 'hello') {
        $tok = (string)($_SERVER['HTTP_X_CSRF'] ?? '');
        if ($tok === '' || !hash_equals((string)$_SESSION['csrf'], $tok))
            slOut(['ok' => false, 'error' => 'csrf', 'message' => 'نشست منقضی شد؛ صفحه را تازه کنید.'], 403);
    }

    if ($op === 'hello') {
        $u = slPublicUser();
        if (!$u && !empty($_COOKIE['shop_in'])) slFlag(false);
        session_write_close();
        slOut(['ok' => true, 'csrf' => (string)$_SESSION['csrf'], 'user' => $u]);
    }

    if ($op === 'login_start') {
        if (!maRateOk('slst', $ip, 12, 600))
            slOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'چند دقیقه بعد دوباره امتحان کنید.'], 429);
        $bot = function_exists('botUsername') && BOT_TOKEN !== '' ? (string)botUsername() : '';
        if ($bot === '' && defined('SITE_BOT')) $bot = ltrim((string)SITE_BOT, '@');
        if ($bot === '') slOut(['ok' => false, 'error' => 'no_bot', 'message' => 'ربات هنوز تنظیم نشده است.'], 503);
        [$r, $code] = slCreate($ip, (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $_SESSION['lr'] = $r;
        slOut(['ok' => true, 'link' => 'https://t.me/' . rawurlencode($bot) . '?start=wl_' . $r,
               'code' => $code, 'ttl' => SL_TTL]);
    }

    if ($op === 'login_poll') {
        $r = (string)($_SESSION['lr'] ?? '');
        if ($r === '') slOut(['ok' => true, 'st' => 'none']);
        $x = slConsume($r);
        if (!$x)               { unset($_SESSION['lr']); slOut(['ok' => true, 'st' => 'expired']); }
        if ($x['st'] === 'no') { unset($_SESSION['lr']); slOut(['ok' => true, 'st' => 'denied']); }
        if ($x['st'] !== 'ok') slOut(['ok' => true, 'st' => 'pending']);

        $uid = (int)$x['uid'];
        $u = getUser($uid);
        if ($u && !empty($u['banned'])) { unset($_SESSION['lr']); slOut(['ok' => false, 'error' => 'banned', 'message' => 'دسترسی شما مسدود است.'], 403); }

        // 🛡 تثبیتِ نشست (session fixation): شناسه‌ی نشست بعد از ورود عوض می‌شود
        session_regenerate_id(true);
        unset($_SESSION['lr']);
        $_SESSION['uid']   = $uid;
        $_SESSION['uname'] = (string)($x['uname'] ?? '');
        $_SESSION['fname'] = (string)($x['fname'] ?? '');
        $_SESSION['at']    = microtime(true);
        $_SESSION['csrf']  = bin2hex(random_bytes(16));
        touchUser($uid, (string)$_SESSION['uname'], (string)$_SESSION['fname']);
        slFlag(true);
        slOut(['ok' => true, 'st' => 'ok', 'csrf' => $_SESSION['csrf'], 'user' => slPublicUser()]);
    }

    if ($op === 'logout') {
        $_SESSION = [];
        session_regenerate_id(true);
        slFlag(false);
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        slOut(['ok' => true, 'csrf' => $_SESSION['csrf']]);
    }

    if ($op === 'api') {
        $user = slUser();
        if (!$user) slOut(['ok' => false, 'error' => 'login', 'message' => 'اول وارد حسابتان شوید.'], 401);
        // قفلِ نشست را همین‌جا رها کن — سفارشِ شماره ممکن است چند ثانیه با پنل حرف بزند
        session_write_close();

        $body   = json_decode((string)file_get_contents('php://input', false, null, 0, 32768), true);
        $action = (string)($body['action'] ?? '');
        $app    = (string)($body['app'] ?? '');
        $allow  = slAllowed();
        if (!isset($allow[$action]) || !in_array($app, $allow[$action], true))
            slOut(['ok' => false, 'error' => 'not_allowed'], 403);

        siteApiUser($user);
        maApi();   // خودش جواب می‌دهد و exit می‌کند
    }

    slOut(['ok' => false, 'error' => 'unknown_op'], 400);
}
