<?php
/**
 * 👑 پنل مدیریت وب — فروشگاه + ربات‌های اپلودر
 *
 * منطق داده از bot_master_membership.php می‌آید (کتابخانه مشترک)
 * تا هرگز دو نسخه ناهماهنگ از داده‌ها وجود نداشته باشد.
 */

/**
 * 🔑 رمز پنل وب.
 *
 * config.local.php باید قبل از تعریفِ رمز خوانده شود، وگرنه مقدارِ
 * پایین زودتر می‌نشیند و رمزِ واقعی هیچ‌وقت اثر نمی‌کند.
 * ترتیب: فایل محلی ← متغیر محیطی ← مقدار پیش‌فرض.
 */
if (is_file(__DIR__ . '/config.local.php')) require_once __DIR__ . '/config.local.php';
// هر دو نام پذیرفته می‌شوند تا اگر در config.local.php هرکدام را نوشتید کار کند
if (!defined('ADMIN_PASSWORD'))
    define('ADMIN_PASSWORD', defined('ADMIN_PANEL_PASS')
        ? (string)ADMIN_PANEL_PASS
        : (string)getenv('ADMIN_PANEL_PASS'));

// رمزِ تنظیم‌نشده = پنل اصلا باز نمی‌شود. از این پنل می‌شود به موجودی
// کاربران و ولت TON رسید، پس بدون رمز حتی صفحه‌ی ورود هم نباید بیاید.
//
// حداقل ۶ کاراکتر است نه بیشتر، چون چیزی که رمزِ کوتاه را در برابر حدس
// آنلاین نگه می‌دارد طولش نیست — قفلِ بعد از چند تلاش است (پایین‌تر:
// ۶ تلاش برای هر IP و ۳۰ تلاش در کل، بعد ۱۵ دقیقه قفل).
if (strlen(ADMIN_PASSWORD) < 6) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("رمز پنل تنظیم نشده است.\n\n" .
         "کنار همین فایل در config.local.php بنویسید:\n\n" .
         "define('ADMIN_PANEL_PASS', 'رمز شما');\n");
}

define('MEMBERSHIP_LIB_ONLY', true);
require_once __DIR__ . '/bot_master_membership.php';

// کوکی نشست: نه در دسترس جاوااسکریپت، نه فرستاده‌شده از سایت دیگر،
// و روی HTTPS فقط رمزنگاری‌شده. بدون این‌ها یک لینک بیرونی یا یک XSS
// می‌توانست نشستِ مدیر را بدزدد.
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_name('mybot_panel');
session_start();

// ------------------------------------------------------------
// 🔐 ورود
// ------------------------------------------------------------

/**
 * 🛡 سد حمله‌ی امتحان‌کردنِ رمز
 *
 * قبلا فقط ۴۰۰ میلی‌ثانیه تاخیر بود؛ یعنی ۱۵۰ رمز در دقیقه، و رمزی که
 * در فهرست‌های رایج باشد ظرف چند ثانیه پیدا می‌شد. حالا بعد از ۶ تلاش
 * ناموفق، آن IP یک ربع بیرون می‌ماند.
 */
define('PANEL_MAX_TRIES', 6);
define('PANEL_MAX_TRIES_ALL', 30);   // مجموع تلاش ناموفق از همه‌ی آی‌پی‌ها
define('PANEL_LOCK_SECONDS', 900);
define('PANEL_IDLE_SECONDS', 7200);

function panelIp() {
    // خودِ IP ذخیره نمی‌شود، فقط اثر انگشتش
    return substr(hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '0')), 0, 24);
}

/** چند ثانیه دیگر قفل است؟ ۰ یعنی باز است */
/**
 * قفل ورود.
 *
 * قفلِ فقط-IP را کسی که چند آی‌پی دارد دور می‌زند، پس یک شمارنده‌ی
 * سراسری هم هست: مجموع تلاش‌های ناموفق از هر جایی. یعنی حتی حمله‌ی
 * پخش‌شده روی صدها آی‌پی هم بعد از ۳۰ حدس متوقف می‌شود.
 */
function panelLockLeft() {
    $a = load('panel_lock');
    $now = time();

    $r = $a[panelIp()] ?? null;
    if (is_array($r) && (int)($r['n'] ?? 0) >= PANEL_MAX_TRIES) {
        $left = PANEL_LOCK_SECONDS - ($now - (int)($r['at'] ?? 0));
        if ($left > 0) return $left;
    }

    $g = $a['_all'] ?? null;
    if (is_array($g) && (int)($g['n'] ?? 0) >= PANEL_MAX_TRIES_ALL) {
        $left = PANEL_LOCK_SECONDS - ($now - (int)($g['at'] ?? 0));
        if ($left > 0) return $left;
    }
    return 0;
}

function panelNoteFail() {
    $k = panelIp();
    mutate('panel_lock', function (&$a) use ($k) {
        foreach ([$k, '_all'] as $kk) {
            $r = $a[$kk] ?? ['n' => 0, 'at' => 0];
            if (time() - (int)$r['at'] > PANEL_LOCK_SECONDS) $r = ['n' => 0, 'at' => 0];
            $r['n'] = (int)$r['n'] + 1;
            $r['at'] = time();
            $a[$kk] = $r;
        }
        foreach ($a as $kk => $vv)
            if ($kk !== '_all' && time() - (int)($vv['at'] ?? 0) > 86400) unset($a[$kk]);
    });
}

function panelClearFails() {
    $k = panelIp();
    // ورود موفق، شمارنده‌ی سراسری را هم صفر می‌کند — وگرنه یک حمله‌ی
    // بی‌ربط می‌توانست خود مدیر را بیرون نگه دارد
    mutate('panel_lock', function (&$a) use ($k) { unset($a[$k], $a['_all']); });
}

function renderLogin($error) { ?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>ورود — پنل مدیریت</title><style>
*{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;display:grid;place-items:center;font-family:system-ui,'Segoe UI',Tahoma,sans-serif;
background:#0c0c0c;padding:20px}
.card{background:#161616;padding:40px 32px;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.5);width:100%;max-width:380px;text-align:center;border:1px solid #2b2b2b}
h1{font-size:22px;margin-bottom:6px;color:#f2f2f2}
p.sub{color:#8a8a8a;font-size:13px;margin-bottom:24px}
input{width:100%;padding:14px 16px;border:1.5px solid #2b2b2b;border-radius:10px;font-size:15px;font-family:inherit;
margin-bottom:14px;text-align:center;background:#1e1e1e;color:#f2f2f2}
input:focus{outline:none;border-color:#2f7de1}
button{width:100%;padding:14px;border:1.5px solid #2f7de1;border-radius:10px;background:#2f7de1;
color:#fff;font-size:16px;font-weight:700;cursor:pointer;font-family:inherit}
button:hover{opacity:.85}
.err{background:#5c2224;color:#f5a3a6;border:1px solid #e5484d;padding:10px;border-radius:10px;font-size:13px;margin-bottom:14px}
</style></head><body>
<form class="card" method="post">
  <div style="font-size:44px">👑</div><h1>پنل مدیریت</h1><p class="sub">فروشگاه تلگرام</p>
  <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
  <input type="password" name="password" placeholder="رمز عبور" autofocus required>
  <button type="submit">ورود</button>
</form></body></html>
<?php }

if (isset($_GET['logout'])) {
    $_SESSION = []; session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
}

// نشستِ رهاشده تا ابد باز نمی‌ماند
if (!empty($_SESSION['logged_in'])) {
    $seen = (int)($_SESSION['seen'] ?? 0);
    if ($seen > 0 && time() - $seen > PANEL_IDLE_SECONDS) {
        $_SESSION = []; session_destroy(); session_start();
    } else {
        $_SESSION['seen'] = time();
    }
}

if (empty($_SESSION['logged_in'])) {
    $err = '';
    $left = panelLockLeft();
    if ($left > 0) {
        $err = 'به‌خاطر تلاش‌های ناموفق، ورود تا ' . ceil($left / 60) . ' دقیقه دیگر بسته است.';
    } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['password'])) {
        if (hash_equals(ADMIN_PASSWORD, (string)$_POST['password'])) {
            panelClearFails();
            session_regenerate_id(true);
            $_SESSION['logged_in'] = true;
            $_SESSION['seen'] = time();
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
        }
        panelNoteFail();
        usleep(400000);
        $left = panelLockLeft();
        $err = $left > 0
            ? 'رمز اشتباه بود. ورود تا ' . ceil($left / 60) . ' دقیقه دیگر بسته شد.'
            : 'رمز عبور اشتباه است.';
    }
    renderLogin($err); exit;
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

function checkCsrf() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400); exit('درخواست نامعتبر (CSRF).');
    }
}

/**
 * 🔐 متنِ فلش را امن می‌کند — قبلا با strip_tags($s, '<code><b>') رندر
 * می‌شد، که برچسب‌های مجاز را با هر ویژگی‌ای که داشتند عینا رد می‌کرد
 * (strip_tags فقط اسمِ تگ را چک می‌کند، نه صفاتش). خیلی از این پیام‌ها
 * عنوانِ کانال/گروهِ تلگرام را عینا داخلشان می‌گذارند — و برخلافِ
 * یوزرنیم، عنوانِ کانال هیچ محدودیتِ نویسه‌ای ندارد. یعنی یک عنوانِ
 * کانالِ مخرب مثلِ «<b onmouseover="...">‌» با اضافه‌کردنِ همان کانال
 * به فهرستِ عضویتِ اجباری، مستقیم توی جلسه‌ی ادمین اجرا می‌شد.
 *
 * اینجا اول همه‌چیز escape می‌شود، بعد فقط همین چهار برچسبِ ساده و
 * بدونِ‌هیچ‌ویژگی دوباره باز می‌شوند — همان‌هایی که خودِ این فایل عمداً
 * برای تاکید/کد می‌گذارد. حتی اگر متنِ مخرب دقیقاً همین رشته‌ها را
 * داشته باشد، حداکثر بولد/کد نشان داده می‌شود، نه اسکریپت.
 */
function flashSafeHtml($s) {
    $s = h((string)$s);
    return strtr($s, [
        '&lt;b&gt;' => '<b>', '&lt;/b&gt;' => '</b>',
        '&lt;code&gt;' => '<code>', '&lt;/code&gt;' => '</code>',
    ]);
}

function go($flash = null, $type = 'ok') {
    if ($flash !== null) $_SESSION['flash'] = ['msg' => $flash, 'type' => $type];
    $tab = $_POST['tab'] ?? $_GET['tab'] ?? 'dashboard';
    $extra = !empty($_POST['bot']) ? '&bot=' . urlencode($_POST['bot']) : '';
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?tab=' . urlencode($tab) . $extra);
    exit;
}

function baseUrl() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    return $scheme . '://' . $host . $dir;
}

// ------------------------------------------------------------
// 📮 عملیات
// ------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $a = $_POST['action'] ?? '';

    // ---- دکمه‌ها ----


    // ═══════════ ⚡ خودکارسازی ═══════════

    if ($a === 'auto_api') {
        $base = rtrim(trim($_POST['base'] ?? ''), '/');
        $key  = trim($_POST['api_key'] ?? '');
        if ($base !== '' && !preg_match('#^https://#i', $base)) go('آدرس پنل باید با https شروع شود.', 'err');
        maSetRoot(function (&$m) use ($base, $key) {
            if ($base !== '') $m['fulfill']['base'] = $base;
            if ($key !== '')  $m['fulfill']['auth_value'] = $key;
            if (trim((string)($m['fulfill']['auth_key'] ?? '')) === '') $m['fulfill']['auth_key'] = 'Authorization';
            $m['fulfill']['on']       = !empty($_POST['f_on']);
            $m['fulfill']['auto_pay'] = !empty($_POST['f_auto']);
        });
        if (!empty($_POST['preset'])) axMarketPreset();
        go('اتصال پنل فروش ذخیره شد.');
    }

    if ($a === 'auto_wallet') {
        $addr = trim($_POST['w_addr'] ?? '');
        $mn   = trim($_POST['w_mn'] ?? '');
        $pw   = trim($_POST['w_pw'] ?? '');
        $api  = rtrim(trim($_POST['w_api'] ?? ''), '/');
        $akey = trim($_POST['w_apikey'] ?? '');

        if ($addr !== '') {
            try { tonParseAddress($addr); }
            catch (Throwable $e) { go('آدرس ولت معتبر نیست: ' . $e->getMessage(), 'err'); }
        }
        if ($mn !== '') {
            [$cOk, $cWhy] = tonCryptoReady();
            if (!$cOk) go($cWhy, 'err');
            $words = array_values(array_filter(preg_split('/\s+/u', $mn), fn($x) => $x !== ''));
            if (count($words) !== 24) go('عبارت بازیابی باید دقیقا ۲۴ کلمه باشد — الان ' . count($words) . ' کلمه.', 'err');
            try { tonKeyFromMnemonic($words); }
            catch (Throwable $e) { go('عبارت بازیابی خوانده نشد: ' . $e->getMessage(), 'err'); }
            $mn = strtolower(implode(' ', $words));
        }

        axSet(function (&$c) use ($addr, $mn, $pw, $api, $akey) {
            if ($addr !== '') { $c['wallet']['address'] = $addr;  $c['wallet']['verified'] = 0; }
            if ($mn   !== '') { $c['wallet']['mnemonic'] = axWalletEncrypt($mn);   $c['wallet']['verified'] = 0; }
            // «-» یعنی پاکش کن؛ خالی یعنی دست نزن
            if ($pw === '-')  { $c['wallet']['passphrase'] = '';  $c['wallet']['verified'] = 0; }
            elseif ($pw !== ''){ $c['wallet']['passphrase'] = $pw; $c['wallet']['verified'] = 0; }
            if ($api  !== '') $c['wallet']['api'] = $api;
            if ($akey !== '') $c['wallet']['api_key'] = $akey;
            $c['wallet']['version'] = in_array($_POST['w_ver'] ?? '', ['v4r2','v3r2'], true) ? $_POST['w_ver'] : 'v4r2';
            $c['wallet']['max_ton'] = (string)(float)($_POST['w_max'] ?? 1);
            $c['wallet']['day_ton'] = (string)(float)($_POST['w_day'] ?? 5);
            $c['wallet']['dry']     = !empty($_POST['w_dry']);
            $c['wallet']['on']      = !empty($_POST['w_on']);
        });

        // روشن کردن بدون تایید مالکیت، یعنی امضای کور — اجازه نمی‌دهیم
        if (!empty($_POST['w_on'])) {
            [$vok, $verr] = axWalletVerify();
            if (!$vok) {
                axSet(function (&$c) { $c['wallet']['on'] = false; });
                go("<b>ذخیره شد، ولی روشن نشد.</b>\nتایید مالکیت ناموفق بود:\n\n" . $verr .
                   "\n\nتا وقتی این تیک سبز نشود ربات هیچ تراکنشی امضا نمی‌کند — " .
                   "امضای کور روی ولتی که مطمئن نیستیم مال شماست، خطرناک است.", 'err');
            }
        }
        go('تنظیمات ولت ذخیره شد.');
    }

    if ($a === 'auto_verify') {
        [$cOk, $cWhy] = tonCryptoReady();
        if (!$cOk) go($cWhy, 'err');
        [$vok, $verr] = axWalletVerify(true);
        $bal = axWalletBalance();
        go($vok
            ? "✅ <b>تایید شد.</b>" . ($verr !== '' ? "\n" . $verr : '') .
              ($bal !== null ? "\nموجودی: " . $bal . ' TON' : '')
            : '❌ ' . $verr, $vok ? 'ok' : 'err');
    }

    if ($a === 'auto_fix') {
        [$cOk, $cWhy] = tonCryptoReady();
        if (!$cOk) go($cWhy, 'err');
        [$fixed, $info] = axWalletAutoFix();
        go($fixed
            ? "🎯 <b>آدرس درست پیدا و ذخیره شد:</b>\n<code>" . $info . "</code>\n\n" .
              "زنجیره تایید کرد که این آدرس همان کلید عبارت بازیابی شماست.\n" .
              "حالا می‌توانید تیک «روشن» را بزنید."
            : "پیدا نشد:\n" . $info, $fixed ? 'ok' : 'err');
    }

    if ($a === 'auto_diag') {
        $rows = axWalletDiagnose();
        $t = "🩻 <b>تشخیص لایه‌به‌لایه</b>\n\n";
        foreach ($rows as $r) {
            $t .= ($r['ok'] ? '✅ ' : '⚠️ ') . '<b>' . $r['step'] . "</b>\n";
            if (trim((string)$r['info']) !== '') $t .= '   ' . $r['info'] . "\n";
        }
        go($t, 'ok');
    }

    if ($a === 'auto_wipe') {
        axSet(function (&$c) { $c['wallet']['mnemonic'] = ''; $c['wallet']['on'] = false; $c['wallet']['verified'] = 0; });
        go('عبارت بازیابی پاک شد و ولت خاموش شد.', 'warn');
    }

    if ($a === 'save_sales') {
        $post = $_POST;
        cfgSet(function (&$c) use ($post) {
            $c['sales']['on']        = !empty($post['sales_on']);
            $c['sales']['chat_id']   = trim($post['sales_chat'] ?? '');
            $c['sales']['template']  = $post['sales_tpl'] ?? '';
            $c['sales']['show_user'] = !empty($post['sales_user']);
        });
        go('تنظیمات کانال فروش ذخیره شد.');
    }

    if ($a === 'test_sales') {
        $s = cfg()['sales'];
        if (empty($s['chat_id'])) go('اول آیدی کانال را بگذارید.', 'err');
        $r = sendMsg(BOT_TOKEN, $s['chat_id'], strtr($s['template'], [
            '{product}' => 'محصول نمونه', '{code}' => 'or_test123', '{amount}' => '50,000',
            '{currency}' => 'تومان', '{count}' => '7', '{limit}' => '100', '{remaining}' => '93',
            '{limit_part}' => " از 100\n🎯 باقی‌مانده: <b>93</b>", '{user}' => '@example',
            '{user_id}' => '123456', '{date}' => nowStr(),
        ]));
        go(!empty($r['ok']) ? 'پیام آزمایشی ارسال شد.' : 'خطا: ' . ($r['description'] ?? ''),
           !empty($r['ok']) ? 'ok' : 'err');
    }

    if ($a === 'broadcast_child') {
        $text = trim($_POST['text'] ?? '');
        if ($text === '') go('متن خالی است.', 'err');
        $ids = $_POST['bots'] ?? null;
        [$n, $err] = bcQueueChild($text, is_array($ids) && $ids ? $ids : null);
        if ($n <= 0) go($err ?: 'هیچ گیرنده‌ای نبود.', 'err');
        go("در صف ارسال قرار گرفت — {$n} گیرنده از ربات‌های زیرمجموعه.");
    }

    // ---- متن‌ها ----

    // ---- پشتیبانی ----
    if ($a === 'save_support') {
        $post = $_POST;
        cfgSet(function (&$c) use ($post) {
            // دو دکمه اصلی
            foreach (['direct', 'indirect'] as $mk) {
                $c['support_main'][$mk]['emoji'] = trim($post["sm_emoji_$mk"] ?? '');
                $c['support_main'][$mk]['text']  = trim($post["sm_text_$mk"] ?? $c['support_main'][$mk]['text']);
                $col = $post["sm_color_$mk"] ?? 'none';
                $c['support_main'][$mk]['color'] = isStyle($col) ? $col : 'none';
                $c['support_main'][$mk]['icon']  = trim($post["sm_icon_$mk"] ?? '');
                if ($mk === 'direct') $c['support_main'][$mk]['value'] = trim($post['sm_value_direct'] ?? '');
            }
            $n = count($c['support_methods']);
            for ($i = 0; $i < $n; $i++) {
                $c['support_methods'][$i]['on']    = !empty($post["s_on_$i"]);
                $c['support_methods'][$i]['kind']  = 'indirect';
                $c['support_methods'][$i]['type']  = $post["s_type_$i"] ?? 'url';
                $c['support_methods'][$i]['emoji'] = trim($post["s_emoji_$i"] ?? '');
                $c['support_methods'][$i]['label'] = trim($post["s_label_$i"] ?? '');
                $c['support_methods'][$i]['value'] = trim($post["s_value_$i"] ?? '');
            }
        });
        go('روش‌های پشتیبانی ذخیره شد.');
    }

    // ---- تنظیمات عمومی ----
    if ($a === 'save_tariff') {
        $post = $_POST;
        cfgSet(function (&$c) use ($post) {
            $c['tariff']['on']   = !empty($post['tf_on']);
            $c['tariff']['auto'] = !empty($post['tf_auto']);
            $txt = (string)($post['tf_text'] ?? '');
            if (trim($txt) !== '') $c['tariff']['text'] = $txt;
            $c['tariff']['btn']['text']   = trim($post['tf_btn_text'] ?? '') ?: 'لیست تعرفه‌ها';
            $c['tariff']['btn']['emoji']  = trim($post['tf_btn_emoji'] ?? '');
            $c['tariff']['btn']['color']  = isStyle($post['tf_btn_color'] ?? '') ? $post['tf_btn_color'] : 'none';
            $c['tariff']['back']['text']  = trim($post['tf_back_text'] ?? '') ?: 'برگشت';
            $c['tariff']['back']['emoji'] = trim($post['tf_back_emoji'] ?? '');
            $c['tariff']['back']['color'] = isStyle($post['tf_back_color'] ?? '') ? $post['tf_back_color'] : 'none';
        });
        go('لیست تعرفه‌ها ذخیره شد.');
    }

    if ($a === 'save_gateway') {
        $post = $_POST;
        $u = trim($post['gw_base'] ?? '');
        if ($u !== '' && !preg_match('#^https://#i', $u)) go('آدرس ربات باید با https:// شروع شود.', 'err');
        cfgSet(function (&$c) use ($post, $u) {
            $c['gateway']['on']       = !empty($post['gw_on']);
            $c['gateway']['provider'] = in_array($post['gw_prov'] ?? '', ['oxapay','nowpayments','custom'], true)
                                        ? $post['gw_prov'] : 'oxapay';
            $c['gateway']['api_key']   = trim($post['gw_key'] ?? '');
            $c['gateway']['ipn_secret']= trim($post['gw_ipn'] ?? '');
            $c['gateway']['base_url']  = $u;
            $c['gateway']['coin']      = strtoupper(trim($post['gw_coin'] ?? 'USDT'));
            $c['gateway']['network']   = strtoupper(trim($post['gw_net'] ?? ''));
            $c['gateway']['rate']      = max(0, (float)str_replace(',', '', $post['gw_rate'] ?? 0));
            $c['gateway']['expire']    = max(5, (int)($post['gw_exp'] ?? 30));
            $c['gateway']['min']       = max(0, (float)str_replace(',', '', $post['gw_min'] ?? 0));
            $c['gateway']['custom_url']= trim($post['gw_curl'] ?? '');
        });
        go('درگاه پرداخت ذخیره شد.');
    }
    if ($a === 'report_group_all') {
        $lnk = trim($_POST['glink'] ?? '');
        [$lc, ] = parseChatLink($lnk);
        if ($lc === null) go('لینک یا آیدی گروه شناخته نشد.', 'err');
        $info = tg(BOT_TOKEN, 'getChat', ['chat_id' => $lc], 8);
        if (empty($info['ok'])) go('ربات به این گروه دسترسی ندارد: ' . ($info['description'] ?? '—'), 'err');
        $n = 0;
        foreach (saleButtons() as $sb) { reportMutate($sb['id'], function (&$r) use ($lc) { $r['chat_id'] = $lc; $r['on'] = true; }); $n++; }
        foreach (Product::all() as $pr) { reportMutate($pr['id'], function (&$r) use ($lc) { $r['chat_id'] = $lc; $r['on'] = true; }); $n++; }
        go('گروه «' . ($info['result']['title'] ?? $lc) . '» روی ' . $n . ' محصول نشست. حالا لینک تاپیک هرکدام را بگذارید.');
    }
    if ($a === 'seen_channels') {
        mutate('channels', function (&$a2) {
            foreach ($a2 as $k => $c) { $a2[$k]['seen'] = true; unset($a2[$k]['lost_admin']); }
        });
        go('علامت‌ها پاک شد.');
    }
    if ($a === 'save_join') {
        $post = $_POST;
        cfgSet(function (&$c) use ($post) {
            $c['join']['on'] = !empty($post['jn_on']);
            // 📝 متنِ قفل و متنِ دکمه از اینجا دست نمی‌خورَند — ویرایششان
            // فقط داخل خودِ ربات است (/panel ← 🔒 عضویت اجباری).
        });
        go('عضویت اجباری ذخیره شد.');
    }
    if ($a === 'add_join_channel') {
        $cid = trim($_POST['chat_id'] ?? '');
        if ($cid === '') go('آیدی کانال لازم است.', 'err');
        $info = tg(BOT_TOKEN, 'getChat', ['chat_id' => $cid], 8);
        if (empty($info['ok'])) go('ربات به این کانال دسترسی ندارد: ' . ($info['description'] ?? '—'), 'err');
        $r = $info['result'];
        $title = trim($_POST['title'] ?? '') ?: ($r['title'] ?? $cid);
        $url = trim($_POST['url'] ?? '');
        if ($url === '' && !empty($r['username'])) $url = 'https://t.me/' . $r['username'];
        if ($url === '') { $inv = tg(BOT_TOKEN, 'exportChatInviteLink', ['chat_id' => $cid], 8); $url = $inv['result'] ?? ''; }
        cfgSet(function (&$c) use ($cid, $title, $url) {
            if (!is_array($c['join']['channels'] ?? null)) $c['join']['channels'] = [];
            foreach ($c['join']['channels'] as $x) if ((string)$x['chat_id'] === (string)$cid) return;
            $c['join']['channels'][] = ['chat_id' => $cid, 'title' => $title, 'url' => $url];
            $c['join']['on'] = true;
        });
        go('کانال «' . $title . '» اضافه شد.');
    }
    if ($a === 'del_join_channel') {
        $i = (int)($_POST['i'] ?? -1);
        cfgSet(function (&$c) use ($i) {
            if (isset($c['join']['channels'][$i])) {
                unset($c['join']['channels'][$i]);
                $c['join']['channels'] = array_values($c['join']['channels']);
            }
        });
        go('کانال حذف شد.');
    }

    if ($a === 'save_settings') {
        $post = $_POST;
        cfgSet(function (&$c) use ($post) {
            $c['wallets']['usdt']      = trim($post['usdt'] ?? '');
            $c['wallets']['trx']       = trim($post['trx'] ?? '');
            $c['wallets']['card']      = trim($post['card'] ?? '');
            $c['wallets']['card_name'] = trim($post['card_name'] ?? '');
            $c['referral']['on']       = !empty($post['ref_on']);
            $c['referral']['percent']  = max(0, min(100, (float)($post['ref_percent'] ?? 0)));
            $c['uploader']['delete_seconds']  = max(5, (int)($post['del_sec'] ?? 30));
            $c['uploader']['force_join']      = !empty($post['force_join']);
            $c['uploader']['protect_content'] = !empty($post['protect']);
            // این دو فقط از فرم «تنظیمات» می‌آیند؛ فرم‌های دیگر نباید صفرشان کنند
            if (!empty($post['adv_scope'])) {
                $c['test_mode']               = !empty($post['test_mode']);
                $c['ui']['speed_show_perday'] = !empty($post['speed_perday']);
                $c['auto_approve']            = !empty($post['auto_approve']);
                $c['campaign_keep_days']      = max(0, (int)($post['keep_days'] ?? 3));
            }
        });
        go('تنظیمات ذخیره شد.');
    }

    // ---- ☎️ شماره مجازی ----
    if ($a === 'save_numbers') {
        $post = $_POST;
        $prov = ($post['provider'] ?? '5sim') === 'numberland' ? 'numberland' : '5sim';
        // 🛡 آدرسِ فروشنده — همان سدِ SSRF که ذخیره‌سازیِ داخلِ خودِ ربات
        //    هم دارد؛ فرمِ وب مسیرِ جدایی بود و این چک را نداشت.
        $baseIn = trim((string)($post['base'] ?? ''));
        if ($baseIn !== '' && !preg_match('#^https?://[^\s]+$#i', $baseIn)) {
            go('⚠️ آدرسِ فروشنده باید با http:// یا https:// شروع شود.', 'err');
        }
        if ($baseIn !== '' && function_exists('ssrfSafeUrl') && !ssrfSafeUrl($baseIn, $ssrfWhy)) {
            go('⚠️ آدرسِ فروشنده رد شد: ' . $ssrfWhy, 'err');
        }
        numSet(function (&$c) use ($post, $prov, $baseIn) {
            $c['provider']   = $prov;
            $c['wait']       = max(60, min(86400, (int)($post['wait'] ?? 900)));
            $c['poll']       = max(2, min(300, (int)($post['poll'] ?? 6)));
            $c['markup']     = max(0, min(1000, (float)str_replace([',', '،'], '', $post['markup'] ?? 0)));
            $c['sync_price'] = !empty($post['sync_price']);
            $c['api']['on']      = !empty($post['num_on']);
            $c['api']['timeout'] = max(5, (int)($post['timeout'] ?? 15));
            $c['api']['rate']    = max(0, (float)str_replace([',', '،'], '', $post['rate'] ?? 0));
            $c['api']['max']     = max(0, (float)str_replace([',', '،'], '', $post['max'] ?? 0));
            $c['api']['base']    = $baseIn;
            $c['api']['nl_svc']  = trim((string)($post['nl_svc'] ?? '')) ?: '1';
            $key = trim((string)($post['api_key'] ?? ''));
            if ($key !== '') { if ($prov === 'numberland') $c['api']['nl_key'] = $key; else $c['api']['token'] = $key; }
        });
        if (function_exists('maSet')) {
            $catsMax = max(0, (int)($post['cats_max'] ?? 0));
            maSet('num', function (&$a) use ($catsMax) { $a['cats_max'] = $catsMax; });
        }
        go('✅ تنظیماتِ شماره مجازی ذخیره شد.');
    }

    // ---- 🚀 مینی‌اپ‌ها ----
    if ($a === 'save_miniapps_root') {
        maSetRoot(function (&$m) {
            $m['base_url']   = trim((string)($_POST['base_url'] ?? ''));
            $m['row_layout'] = trim((string)($_POST['row_layout'] ?? '')) ?: '1,1';
        });
        go('✅ آدرس و چیدمانِ مینی‌اپ‌ها ذخیره شد.');
    }
    if ($a === 'save_miniapp_app') {
        $key = in_array((string)($_POST['key'] ?? ''), maKeys(), true) ? (string)$_POST['key'] : 'tg';
        $post = $_POST;
        maSet($key, function (&$app) use ($post) {
            $app['on'] = !empty($post['app_on']);
            if (!is_array($app['theme'] ?? null)) $app['theme'] = [];
            foreach (['c1', 'c2', 'c3', 'c4', 'bg'] as $ck) {
                $v = trim((string)($post['theme_' . $ck] ?? ''));
                if (preg_match('/^#[0-9a-fA-F]{6}$/', $v)) $app['theme'][$ck] = $v;
            }
            $app['theme']['glow']  = !empty($post['theme_glow']);
            $app['theme']['grain'] = !empty($post['theme_grain']);
            $app['theme']['fx']    = max(0, min(2, (int)($post['theme_fx'] ?? 2)));
        });
        go('✅ تنظیماتِ این مینی‌اپ ذخیره شد.');
    }
    if ($a === 'save_miniapp_cats') {
        $key = in_array((string)($_POST['key'] ?? ''), maKeys(), true) ? (string)$_POST['key'] : 'tg';
        $onIds = (array)($_POST['cat_on'] ?? []);
        maSet($key, function (&$app) use ($onIds) {
            if (!is_array($app['cats'] ?? null)) return;
            foreach ($app['cats'] as $i => $c) {
                $app['cats'][$i]['on'] = in_array((string)($c['id'] ?? ''), $onIds, true);
            }
        });
        go('✅ دسته‌بندی‌ها ذخیره شد.');
    }
    if ($a === 'save_miniapp_items') {
        $key = in_array((string)($_POST['key'] ?? ''), maKeys(), true) ? (string)$_POST['key'] : 'tg';
        $onIds  = (array)($_POST['item_on'] ?? []);
        $prices = (array)($_POST['item_price'] ?? []);
        $mins   = (array)($_POST['item_min'] ?? []);
        $maxs   = (array)($_POST['item_max'] ?? []);
        $qtyAsk = ['qty', 'qty_wallet', 'qty_username', 'qty_link'];
        maSet($key, function (&$app) use ($onIds, $prices, $mins, $maxs, $qtyAsk) {
            if (!is_array($app['items'] ?? null)) return;
            foreach ($app['items'] as $i => $it) {
                $id = (string)($it['id'] ?? '');
                $app['items'][$i]['on'] = in_array($id, $onIds, true);
                if (isset($prices[$id]) && is_numeric(str_replace([',', '،'], '', $prices[$id]))) {
                    $app['items'][$i]['price'] = (float)str_replace([',', '،'], '', $prices[$id]);
                }
                // 🔢 حداقل/حداکثرِ تعداد — فقط برای موارد قابل‌تعداد (تون/ترون/استارزِ
                // دلخواه...)؛ بقیه (هدیه‌ها، بسته‌های ثابت) همیشه دقیقا ۱ می‌مانند.
                if (!in_array((string)($it['ask'] ?? ''), $qtyAsk, true)) continue;
                if (isset($mins[$id])) { $v = maNum($mins[$id]); if ($v > 0) $app['items'][$i]['min'] = $v; }
                if (isset($maxs[$id])) { $v = maNum($maxs[$id]); if ($v > 0) $app['items'][$i]['max'] = $v; }
            }
        });
        go('✅ سرویس‌ها ذخیره شد.');
    }

    // ---- 💎 الماس ----
    if ($a === 'save_diamond') {
        $post = $_POST;
        dmSet(function (&$c) use ($post) {
            $c['on']         = !empty($post['dm_on']);
            $c['group_only'] = !empty($post['group_only']);
            $c['word']       = trim((string)($post['word'] ?? 'الماس')) ?: 'الماس';
            $c['aliases']    = trim((string)($post['aliases'] ?? ''));
            $c['cooldown']   = max(0, (int)($post['cooldown'] ?? 300));
            $c['base']       = max(0, (float)str_replace([',', '،'], '', $post['base'] ?? 0));
            $c['ratio']      = max(1, (float)str_replace([',', '،'], '', $post['ratio'] ?? 1));
            $c['min']        = max(0, (float)str_replace([',', '،'], '', $post['min_reward'] ?? 0));
            $c['cap']        = max(0, (float)str_replace([',', '،'], '', $post['cap'] ?? 0));
            $c['top_n']      = max(1, (int)($post['top_n'] ?? 10));
            $c['level_step'] = max(1, (int)($post['level_step'] ?? 10000));
            $c['to_wallet']  = max(0, (float)str_replace([',', '،'], '', $post['to_wallet'] ?? 0));
            $c['min_swap']   = max(0, (float)str_replace([',', '،'], '', $post['min_swap'] ?? 0));
            if (!is_array($c['gift'] ?? null)) $c['gift'] = [];
            $c['gift']['on']    = !empty($post['gift_on']);
            $c['gift']['cost']  = max(0, (float)str_replace([',', '،'], '', $post['gift_cost'] ?? 0));
            $c['gift']['app']   = ($post['gift_app'] ?? 'tg') === 'num' ? 'num' : 'tg';
            $c['gift']['item']  = trim((string)($post['gift_item'] ?? ''));
            $c['gift']['word']  = trim((string)($post['gift_word'] ?? 'هدیه')) ?: 'هدیه';
            $c['gift']['limit'] = max(0, (int)($post['gift_limit'] ?? 0));
            if (!is_array($c['jail'] ?? null)) $c['jail'] = [];
            $c['jail']['words'] = trim((string)($post['jail_words'] ?? ''));
            $c['jail']['need']  = max(1, (int)($post['jail_need'] ?? 3));
            $c['jail']['secs']  = max(0, (int)($post['jail_secs'] ?? 3600));
            $c['jail']['color'] = isStyle($post['jail_color'] ?? '') ? $post['jail_color'] : 'danger';
        });
        go('✅ تنظیماتِ الماس ذخیره شد.');
    }

    // ---- 🎮 بازی‌ها ----
    if ($a === 'save_games') {
        $post = $_POST;
        gmSet(function (&$c) use ($post) {
            $c['on']         = !empty($post['gm_on']);
            $c['duel_board'] = !empty($post['duel_board']);
            $c['open_max']   = max(1, (int)($post['open_max'] ?? 2));
            $c['expire']     = max(10, (int)($post['expire'] ?? 180));
            $c['wait']       = max(1, (int)($post['wait'] ?? 8));
            $c['join_max']   = max(2, (int)($post['join_max'] ?? 50));
            $c['word_duel']  = trim((string)($post['word_duel'] ?? '')) ?: 'چالش';
            $c['word_rand']  = trim((string)($post['word_rand'] ?? '')) ?: 'بازی';
            $c['word_bal']   = trim((string)($post['word_bal'] ?? '')) ?: 'موجودی';
            $c['word_send']  = trim((string)($post['word_send'] ?? '')) ?: 'انتقال';
            $c['min']        = max(0, (float)str_replace([',', '،'], '', $post['gm_min'] ?? 0));
            $c['max']        = max(0, (float)str_replace([',', '،'], '', $post['gm_max'] ?? 0));
            $c['tax']        = max(0, min(100, (float)str_replace([',', '،'], '', $post['tax'] ?? 0)));
            $c['send_tax']   = max(0, min(100, (float)str_replace([',', '،'], '', $post['send_tax'] ?? 0)));
        });
        go('✅ تنظیماتِ بازی‌ها ذخیره شد.');
    }

    // ---- 🏦 بانک ----
    if ($a === 'save_bank') {
        $post = $_POST;
        $num = fn($k, $d = 0) => (float)str_replace([',', '،'], '', $post[$k] ?? $d);
        bkSet(function (&$c) use ($post, $num) {
            $c['on']            = !empty($post['bk_on']);
            $c['group_only']    = !empty($post['bk_group_only']);
            $c['word_hack']     = trim((string)($post['word_hack'] ?? '')) ?: 'هک';
            $c['min_withdraw']  = max(0, $num('min_withdraw', 50000));
            $c['manual_protect']= max(60, (int)$num('manual_protect', 900));
            $c['shield_after']  = max(0, (int)$num('shield_after', 300));
            $c['hack_cooldown'] = max(60, (int)$num('hack_cooldown', 1200));
            $c['level_step']    = max(1, (int)$num('level_step', 500000));
            $c['top_n']         = max(1, (int)$num('top_n', 10));

            if (!is_array($c['rng'] ?? null)) $c['rng'] = [];
            $r = &$c['rng'];
            $r['base_success']  = max(0, min(100, $num('rng_base', 42)));
            $r['success_floor'] = max(0, min(100, $num('rng_floor', 18)));
            $r['success_ceil']  = max(0, min(100, $num('rng_ceil', 72)));
            $r['jitter_pct']    = max(0, min(50, $num('rng_jitter', 12)));
            $r['jackpot_pct']   = max(0, min(100, $num('rng_jackpot_pct', 0.4)));
            $r['perfect_pct']   = max(0, min(100, $num('rng_perfect_pct', 7)));
            $r['critfail_pct']  = max(0, min(100, $num('rng_critfail_pct', 8)));
            $r['partial_share'] = max(0, min(1, $num('rng_partial_share', 0.35)));
            $r['jackpot_min']   = max(0, min(100, $num('rng_jackpot_min', 25)));
            $r['jackpot_max']   = max(0, min(100, $num('rng_jackpot_max', 40)));
            $r['perfect_min']   = max(0, min(100, $num('rng_perfect_min', 10)));
            $r['perfect_max']   = max(0, min(100, $num('rng_perfect_max', 16)));
            $r['success_min']   = max(0, min(100, $num('rng_success_min', 4)));
            $r['success_max']   = max(0, min(100, $num('rng_success_max', 10)));
            $r['partial_min']   = max(0, min(100, $num('rng_partial_min', 1)));
            $r['partial_max']   = max(0, min(100, $num('rng_partial_max', 4)));
            $r['critfail_min']  = max(0, min(100, $num('rng_critfail_min', 5)));
            $r['critfail_max']  = max(0, min(100, $num('rng_critfail_max', 15)));
            unset($r);
        });
        go('✅ تنظیماتِ بانک ذخیره شد.');
    }
    if ($a === 'save_bank_texts') {
        $post = $_POST;
        bkSet(function (&$c) use ($post) {
            if (!is_array($c['texts'] ?? null)) $c['texts'] = [];
            foreach (bkDefaults()['texts'] as $k => $_) {
                if (isset($post['txt_' . $k])) $c['texts'][$k] = trim((string)$post['txt_' . $k]);
            }
            if (!is_array($c['icons'] ?? null)) $c['icons'] = [];
            if (!is_array($c['btns']  ?? null)) $c['btns']  = [];
            foreach (['btn_protect', 'btn_deposit', 'btn_withdraw'] as $k) {
                if (isset($post['icon_' . $k])) $c['icons'][$k] = trim((string)$post['icon_' . $k]);
                if (!is_array($c['btns'][$k] ?? null)) $c['btns'][$k] = [];
                $c['btns'][$k]['color'] = isStyle($post['color_' . $k] ?? '') ? $post['color_' . $k] : 'none';
            }
        });
        go('✅ متن‌ها و ایموجیِ دکمه‌های بانک ذخیره شد.');
    }

    // ---- 💣 مین‌یاب ----
    if ($a === 'save_mine') {
        $post = $_POST;
        $num = fn($k, $d = 0) => (float)str_replace([',', '،'], '', $post[$k] ?? $d);
        mnSet(function (&$c) use ($post, $num) {
            $c['on']         = !empty($post['mn_on']);
            $c['group_only'] = !empty($post['mn_group_only']);
            $c['word']       = trim((string)($post['mn_word'] ?? '')) ?: 'مین';
            $c['entry_min']  = max(0, $num('entry_min', 100));
            $c['entry_max']  = max($c['entry_min'], $num('entry_max', 1000000));
            $c['min_safe_for_protection'] = max(0, (int)$num('min_safe', 3));
            $c['reward_growth']   = max(1, $num('reward_growth', 1.5));
            $c['max_active_games']= max(1, (int)$num('max_active', 200));
            $c['game_timeout']    = max(60, (int)$num('game_timeout', 1800));
            $c['expire_refund']   = !empty($post['expire_refund']);
            $c['game_cooldown']   = max(0, (int)$num('game_cooldown', 0));

            $rewards = [];
            for ($i = 1; $i <= 8; $i++) $rewards[] = max(0, $num('reward_' . $i, 0));
            $c['rewards'] = $rewards;
        });
        go('✅ تنظیماتِ مین‌یاب ذخیره شد.');
    }
    if ($a === 'save_mine_texts') {
        $post = $_POST;
        mnSet(function (&$c) use ($post) {
            if (!is_array($c['texts'] ?? null)) $c['texts'] = [];
            foreach (mnDefaults()['texts'] as $k => $_) {
                if (isset($post['txt_' . $k])) $c['texts'][$k] = trim((string)$post['txt_' . $k]);
            }
            if (!is_array($c['icons'] ?? null)) $c['icons'] = [];
            if (!is_array($c['btns']  ?? null)) $c['btns']  = [];
            foreach (['btn_field', 'btn_join', 'btn_cancel', 'btn_cash'] as $k) {
                if (isset($post['icon_' . $k])) $c['icons'][$k] = trim((string)$post['icon_' . $k]);
                if (!is_array($c['btns'][$k] ?? null)) $c['btns'][$k] = [];
                $c['btns'][$k]['color'] = isStyle($post['color_' . $k] ?? '') ? $post['color_' . $k] : 'none';
            }
        });
        go('✅ متن‌ها و ایموجیِ دکمه‌های مین‌یاب ذخیره شد.');
    }

    // ---- 🩺 تشخیص و سرعت ----
    if (in_array($a, ['adm_write_test', 'adm_leak_test', 'adm_speed_test', 'adm_auto_setup'], true)) {
        $report = $a === 'adm_write_test' ? admWriteTestText()
                : ($a === 'adm_leak_test' ? admLeakTestText()
                : ($a === 'adm_speed_test' ? admSpeedText() : autoSetupRun()));
        go($report, (str_contains($report, '🔴') || str_contains($report, '🚨')) ? 'err' : 'ok');
    }

    // ---- محصولات ----
    if ($a === 'add_product') {
        $name = trim($_POST['name'] ?? '');
        $price = str_replace(',', '', trim($_POST['price'] ?? ''));
        if ($name === '' || !is_numeric($price)) go('نام و قیمت معتبر لازم است.', 'err');
        $p = Product::create($name, (float)$price, trim($_POST['currency'] ?? 'تومان'),
                             (int)($_POST['limit'] ?? 0), trim($_POST['desc'] ?? ''),
                             $_POST['bot_id'] ?: null);
        $post = $_POST;
        mutate('products', function (&$all) use ($p, $post) {
            $id = $p['id'];
            if (!isset($all[$id])) return;
            $all[$id]['link_code'] = trim($post['link_code'] ?? '');
            $all[$id]['emoji']     = trim($post['emoji'] ?? '💠');
            $all[$id]['color']     = isStyle($post['color'] ?? '') ? $post['color'] : 'none';
            $all[$id]['icon']      = trim($post['icon'] ?? '');
            $all[$id]['row']       = max(0, (int)($post['row'] ?? 0));
            $all[$id]['order']     = max(1, (int)($post['order'] ?? 99));
        });
        go('محصول «' . $name . '» ساخته شد.');
    }
    if ($a === 'del_product') {
        $id = $_POST['id'] ?? '';
        mutate('products', function (&$all) use ($id) { unset($all[$id]); });
        go('محصول حذف شد.');
    }
    if ($a === 'toggle_product') {
        $id = $_POST['id'] ?? '';
        mutate('products', function (&$all) use ($id) {
            if (isset($all[$id])) $all[$id]['active'] = empty($all[$id]['active']);
        });
        go('وضعیت محصول تغییر کرد.');
    }
    if ($a === 'link_product') {
        $id = $_POST['id'] ?? '';
        $post = $_POST;
        mutate('products', function (&$all) use ($id, $post) {
            if (!isset($all[$id])) return;
            $all[$id]['bot_id']    = ($post['bot_id'] ?? '') ?: null;
            $all[$id]['link_code'] = trim($post['link_code'] ?? '');
            $all[$id]['name']      = trim($post['name'] ?? $all[$id]['name']);
            $all[$id]['price']     = (float)str_replace(',', '', $post['price'] ?? $all[$id]['price']);
            $all[$id]['emoji']     = trim($post['emoji'] ?? '');
            $all[$id]['color']     = isStyle($post['color'] ?? '') ? $post['color'] : 'none';
            $all[$id]['icon']      = trim($post['icon'] ?? '');
            $all[$id]['row']       = max(0, (int)($post['row'] ?? 0));
            $all[$id]['order']     = max(1, (int)($post['order'] ?? 99));
            $all[$id]['smm_service'] = trim($post['smm_service'] ?? '');
            $all[$id]['smm_auto_price'] = !empty($post['smm_auto_price']);
            $all[$id]['sale_cat'] = in_array($post['sale_cat'] ?? '', ['fake_member', 'boost'], true) ? $post['sale_cat'] : '';
            // 📝 متن‌های اختصاصیِ محصول از اینجا دست نمی‌خورَند — ویرایششان
            // فقط داخل خودِ ربات است (/panel ← 🎨 دکمه‌ها ← محصول ← 📝
            // متن‌های اختصاصی)، تا ذخیره‌ی هر فیلدِ دیگر اینجا پاکشان نکند.
        });
        go('محصول به‌روزرسانی شد.');
    }

    // ---- اتصال به پنل SMM (فروشنده‌ی ممبر/فالوور) ----
    if ($a === 'save_smm') {
        $base = rtrim(trim($_POST['smm_base'] ?? ''), '/');
        $key  = trim($_POST['smm_key'] ?? '');
        if ($base !== '' && !preg_match('#^https://#i', $base)) go('آدرس پنل باید با https شروع شود.', 'err');
        cfgSet(function (&$c) use ($base, $key) {
            if (!is_array($c['smm'] ?? null)) $c['smm'] = [];
            $c['smm']['base']    = $base;
            $c['smm']['key']     = $key;
            $c['smm']['timeout'] = max(5, (int)($_POST['smm_timeout'] ?? 15));
            $c['smm']['on']      = !empty($_POST['smm_on']);
        });
        go('اتصال پنل ممبر ذخیره شد.');
    }

    // ---- راه‌اندازیِ یک‌کلیکِ بوستِ تلگرام روی یک دکمه‌ی موجود ----
    if ($a === 'boost_quickstart') {
        $parts = explode('|', (string)($_POST['target'] ?? ''), 2);
        if (count($parts) !== 2 || !function_exists('boostQuickSetup') || !boostQuickSetup($parts[0], $parts[1]))
            go('دکمه پیدا نشد.', 'err');
        go('✅ دکمه به «بوست تلگرام» تبدیل شد — پایین همین صفحه، برو رو همون دکمه و برای هر ۴ مدت، سرویسِ واقعیِ پنل رو از دراپ‌داون انتخاب کن.');
    }

    // ---- سود روی محصولات — یک‌جا برای همه‌ی بخش‌ها ----
    if ($a === 'save_profit') {
        $numOnly = fn($k) => (float)str_replace([',', '،'], '', $_POST[$k] ?? 0);
        pfSet(function (&$c) use ($numOnly) {
            $c['on']  = !empty($_POST['pf_on']);
            $c['all'] = ['mode' => ($_POST['pf_all_mode'] ?? 'pct') === 'fixed' ? 'fixed' : 'pct',
                         'v' => $numOnly('pf_all_v')];
            foreach (['member', 'ma', 'fake_member', 'boost'] as $sec) {
                $mode = $_POST['pf_' . $sec . '_mode'] ?? 'off';
                if ($mode === 'off') { $c[$sec] = ['mode' => null, 'v' => null]; continue; }
                $c[$sec] = ['mode' => $mode === 'fixed' ? 'fixed' : 'pct', 'v' => $numOnly('pf_' . $sec . '_v')];
            }
        });
        if (function_exists('numSet')) numSet(function (&$c) use ($numOnly) { $c['markup'] = $numOnly('num_markup'); });
        if (function_exists('pxSet'))  pxSet(function (&$c) use ($numOnly)  { $c['margin'] = $numOnly('px_margin'); });
        // 🎁⭐️💎🪙 چهار دسته‌ی مینی‌اپ — همان زیرساختِ axCfg()['pricing']['margin'] که از قبل هست
        if (function_exists('axSet')) {
            axSet(function (&$c) use ($numOnly) {
                foreach (['c_gift', 'c_star', 'c_prem', 'c_coin'] as $cat) {
                    $mode = $_POST['pf_' . $cat . '_mode'] ?? 'off';
                    if (!is_array($c['pricing']['margin'] ?? null)) $c['pricing']['margin'] = [];
                    if ($mode === 'off') { unset($c['pricing']['margin'][$cat]); continue; }
                    $c['pricing']['margin'][$cat] = ['mode' => $mode === 'fixed' ? 'fixed' : 'pct',
                                                      'v' => $numOnly('pf_' . $cat . '_v')];
                }
            });
        }
        go('تنظیمات سود ذخیره شد.');
    }
    if ($a === 'profit_every') {
        $pct = (float)str_replace([',', '،'], '', $_POST['every_pct'] ?? 0);
        if (function_exists('pfSetAll')) pfSetAll($pct);
        go('✅ ' . rtrim(rtrim(number_format($pct, 1), '0'), '.') . '٪ روی همه‌ی بخش‌ها نشست.');
    }

    // ---- تست اتصال به پنل SMM — فقط موجودی را می‌خواند، پولی خرج نمی‌شود ----
    if ($a === 'smm_test') {
        [$ok, $res, $err] = smmCall('balance');
        if (!$ok) go('❌ اتصال برقرار نشد: ' . $err, 'err');
        $bal = $res['balance'] ?? '—';
        $cur = $res['currency'] ?? '';
        go('✅ اتصال برقرار است. موجودی پنل: ' . $bal . ' ' . $cur);
    }

    // ---- گرفتنِ دوبارهٔ فهرستِ سرویس‌های پنل ----
    if ($a === 'smm_refresh_services') {
        [$ok, $n] = smmServicesRefresh();
        if (!$ok) go('❌ لیست گرفته نشد: ' . $n, 'err');
        go("✅ {$n} سرویس از پنل گرفته شد — پایین همین صفحه، توی هر محصول قابل انتخاب است.");
    }

    // ---- قیمت‌گذاری دکمه‌های فروش (خودِ دکمه = محصول) ----
    // ---- افزودن/حذفِ یک ردیفِ سرعت/پلن — برای زمانی که ۳۰/۹۰ روزه‌ی ثابت کافی نیست ----
    if ($a === 'add_speed') {
        $bid = $_POST['bid'] ?? ''; $sid = $_POST['sid'] ?? '';
        if (!findSub($bid, $sid)) go('دکمه پیدا نشد.', 'err');
        $txt = trim($_POST['new_sp_text'] ?? '');
        if ($txt === '') go('یک متن برای پلنِ جدید بنویس.', 'err');
        subMutate($bid, $sid, function (&$x) use ($txt) {
            if (!is_array($x['flow'] ?? null)) $x['flow'] = defaultFlow();
            if (!is_array($x['flow']['speeds'] ?? null)) $x['flow']['speeds'] = [];
            $x['flow']['speeds'][] = [
                'id' => uid('sp'), 'text' => $txt, 'emoji' => trim($_POST['new_sp_emoji'] ?? ''),
                'mult' => 1, 'per_day' => 0, 'color' => 'none', 'icon' => '', 'on' => true, 'smm_service' => '',
            ];
        });
        go('✅ پلنِ جدید اضافه شد — پایینِ همون دکمه، براش رنگ/سرویس/متن تنظیم کن.');
    }
    if ($a === 'del_speed') {
        $bid = $_POST['bid'] ?? ''; $sid = $_POST['sid'] ?? ''; $spid = $_POST['spid'] ?? '';
        if (!findSub($bid, $sid)) go('دکمه پیدا نشد.', 'err');
        subMutate($bid, $sid, function (&$x) use ($spid) {
            if (!is_array($x['flow']['speeds'] ?? null)) return;
            $x['flow']['speeds'] = array_values(array_filter($x['flow']['speeds'], fn($s) => ($s['id'] ?? '') !== $spid));
        });
        go('پلن حذف شد.');
    }

    if ($a === 'save_btn_price') {
        $bid = $_POST['bid'] ?? ''; $sid = $_POST['sid'] ?? '';
        if (!findSub($bid, $sid)) go('دکمه پیدا نشد.', 'err');

        $price = str_replace([',', '،', ' '], '', trim($_POST['price'] ?? ''));
        if (!is_numeric($price) || (float)$price < 0) go('قیمت باید عدد باشد.', 'err');

        $min = (int)str_replace([',', '،'], '', $_POST['min'] ?? '0');
        $max = (int)str_replace([',', '،'], '', $_POST['max'] ?? '0');
        $per = (int)str_replace([',', '،'], '', $_POST['per'] ?? '1000');
        if ($min < 1)    go('حداقل تعداد باید بزرگ‌تر از صفر باشد.', 'err');
        if ($max <= $min) go('حداکثر باید از حداقل بیشتر باشد.', 'err');
        if ($per < 1)    go('«قیمت به ازای هر …» باید بزرگ‌تر از صفر باشد.', 'err');

        $cur  = trim($_POST['currency'] ?? 'تومان');
        $desc = trim($_POST['desc'] ?? '');
        $post = $_POST;

        subMutate($bid, $sid, function (&$x) use ($price, $cur, $min, $max, $per, $desc, $post) {
            $x['price']    = (float)$price;
            $x['currency'] = $cur !== '' ? $cur : 'تومان';
            $x['desc']     = $desc;
            $x['smm_service'] = trim($post['smm_service'] ?? '');
            $x['smm_auto_price'] = !empty($post['smm_auto_price']);
            $x['sale_cat'] = in_array($post['sale_cat'] ?? '', ['fake_member', 'boost'], true) ? $post['sale_cat'] : '';
            // 📝 متن‌های اختصاصی از اینجا دست نمی‌خورَند — ویرایششان فقط
            // داخل خودِ ربات است (📝 متن‌های اختصاصی)، تا ذخیره‌ی قیمت/سرعت
            // اینجا پاکشان نکند.
            if (!is_array($x['flow'] ?? null)) $x['flow'] = [];
            // ⚠️ ask_admin دیگر همیشه true نیست — بوست/گیفت نیازی به ادمین‌شدنِ
            // ربات در کانال ندارند، فقط محصولاتِ ممبرگیریِ واقعی نیاز دارند
            $x['flow'] = array_merge(defaultFlow(), $x['flow'], ['on' => true, 'ask_admin' => !empty($post['ask_admin'])]);
            $x['flow']['min'] = $min;
            $x['flow']['max'] = $max;
            $x['flow']['per'] = $per;
            $x['flow']['speed_layout'] = trim((string)($post['speed_layout'] ?? '1')) ?: '1';
            $x['flow']['speed_mode'] = ($post['speed_mode'] ?? 'grid') === 'carousel' ? 'carousel' : 'grid';
            $x['flow']['speed_prev_label'] = trim((string)($post['speed_prev_label'] ?? ''));
            $x['flow']['speed_next_label'] = trim((string)($post['speed_next_label'] ?? ''));

            // متن، ایموجی، رنگ، ضریب، نفر/روز و توضیح هر سرعت
            foreach ($x['flow']['speeds'] as $i => $sp) {
                $id = $sp['id'];
                if (isset($post['mult'][$id]) && is_numeric(str_replace(',', '', $post['mult'][$id]))) {
                    $m = (float)str_replace(',', '', $post['mult'][$id]);
                    if ($m > 0) $x['flow']['speeds'][$i]['mult'] = $m;
                }
                if (isset($post['perday'][$id])) {
                    $pd = (int)str_replace([',', '،'], '', $post['perday'][$id]);
                    if ($pd >= 0) $x['flow']['speeds'][$i]['per_day'] = $pd;
                }
                if (isset($post['sptext'][$id])) {
                    $tx = trim((string)$post['sptext'][$id]);
                    if ($tx !== '') $x['flow']['speeds'][$i]['text'] = $tx;
                }
                if (isset($post['spemoji'][$id]))
                    $x['flow']['speeds'][$i]['emoji'] = trim((string)$post['spemoji'][$id]);
                if (isset($post['spdesc'][$id]))
                    $x['flow']['speeds'][$i]['desc'] = trim((string)$post['spdesc'][$id]);
                if (isset($post['spcolor'][$id]))
                    $x['flow']['speeds'][$i]['color'] = isStyle($post['spcolor'][$id]) ? $post['spcolor'][$id] : 'none';
                if (isset($post['spsmm'][$id]))
                    $x['flow']['speeds'][$i]['smm_service'] = trim((string)$post['spsmm'][$id]);
                $x['flow']['speeds'][$i]['on'] = !empty($post['spon'][$id]);
            }
        });
        go('قیمت‌گذاری ذخیره شد.');
    }
    if ($a === 'save_btn_report') {
        $bid = $_POST['bid'] ?? ''; $sid = $_POST['sid'] ?? '';
        if (!findSub($bid, $sid)) go('دکمه پیدا نشد.', 'err');
        $post = $_POST;

        foreach ([0, 1] as $i) {
            $u = trim($post['burl'][$i] ?? '');
            if ($u !== '' && !preg_match('#^(https?://|tg://)#i', $u))
                go('لینک دکمه ' . ($i + 1) . ' باید با https:// شروع شود.', 'err');
        }

        // اگر لینک تاپیک داده شده، گروه و تاپیک را از آن بخوان
        $lnk = trim($post['rlink'] ?? '');
        if ($lnk !== '') {
            [$lc, $lt] = parseChatLink($lnk);
            if ($lc === null) go('لینک تاپیک شناخته نشد. از خود تاپیک Copy Link بگیرید.', 'err');
            $post['rchat'] = $lc; $post['rthread'] = $lt;
        }

        reportMutate(subProductId($bid, $sid), function (&$r) use ($post) {
            $r['on']        = !empty($post['ron']);
            $r['chat_id']   = trim($post['rchat'] ?? '');
            $r['thread_id'] = max(0, (int)($post['rthread'] ?? 0));
            $txt = (string)($post['rtext'] ?? '');
            if (trim($txt) !== '') $r['text'] = $txt;
            $r['btn_row'] = !empty($post['brow']);
            foreach ([0, 1] as $i) {
                if (!isset($r['buttons'][$i]))
                    $r['buttons'][$i] = ['text'=>'','url'=>'','color'=>'none','icon'=>'','on'=>true];
                $r['buttons'][$i]['text']  = trim($post['btext'][$i] ?? '');
                $r['buttons'][$i]['url']   = trim($post['burl'][$i] ?? '');
                $r['buttons'][$i]['color'] = isStyle($post['bcolor'][$i] ?? '') ? $post['bcolor'][$i] : 'none';
                $r['buttons'][$i]['on']    = !empty($post['bon'][$i]);
            }
        });
        go('گزارش خرید ذخیره شد.');
    }
    if ($a === 'test_btn_report') {
        $bid = $_POST['bid'] ?? ''; $sid = $_POST['sid'] ?? '';
        $pid = subProductId($bid, $sid);
        $p = Product::get($pid);
        if (!$p) go('دکمه پیدا نشد.', 'err');
        reportSale([
            'id' => 'or_TEST000000', 'type' => 'product', 'product_id' => $pid,
            'user_id' => ADMIN_ID, 'username' => 'admin', 'amount' => (float)$p['price'],
            'currency' => $p['currency'], 'created_at' => nowStr(),
            'meta' => ['link' => 'https://t.me/example', 'qty' => 5000, 'speed' => 'نمونه',
                       'per_day' => 5000, 'eta' => 'حدود 1 روز', 'chat_title' => 'کانال نمونه'],
        ], true);
        $rr = reportOf($p);
        if (trim((string)$rr['chat_id']) === '') go('اول آیدی گروه را تنظیم و ذخیره کنید.', 'err');
        go('گزارش آزمایشی فرستاده شد.' . (empty($rr['on'])
            ? ' توجه: گزارش این محصول خاموش است، پس خریدهای واقعی گزارش نمی‌شوند.'
            : ' اگر نرسید، ربات را در گروه ادمین کنید.'));
    }

    if ($a === 'toggle_btn_product') {
        $bid = $_POST['bid'] ?? ''; $sid = $_POST['sid'] ?? '';
        if (!findSub($bid, $sid)) go('دکمه پیدا نشد.', 'err');
        subMutate($bid, $sid, function (&$x) { $x['on'] = empty($x['on']); });
        go('وضعیت دکمه تغییر کرد.');
    }

    if ($a === 'save_product_layout') {
        $lay = $_POST['product_layout'] ?? '1';
        cfgSet(function (&$c) use ($lay) { $c['ui']['product_layout'] = trim($lay); });
        go('چیدمان محصولات ذخیره شد.');
    }

    // ---- 🧲 چیدمانِ دستیِ محصولات: گریدِ ۷ ردیف × ۳ دکمه ----
    // هر خانه یعنی یک (row, order) مشخص. اول همان ۲۱ خانه از هر مالکِ
    // قبلی‌شان پاک می‌شود، بعد مقدارهای تازه از فرم روی‌شان می‌نشیند —
    // این‌طور جابه‌جایی، پاک‌کردن و جای‌گزینی همه با یک قانون ساده جواب می‌دهند.
    if ($a === 'save_product_grid') {
        $grid = (array)($_POST['grid'] ?? []);
        mutate('products', function (&$all) use ($grid) {
            for ($r = 1; $r <= 7; $r++) {
                for ($c = 1; $c <= 3; $c++) {
                    $ord = $r * 10 + $c;
                    foreach ($all as $pid => &$p) {
                        if ((int)($p['row'] ?? 0) === $r && (int)($p['order'] ?? 0) === $ord) $p['row'] = 0;
                    }
                    unset($p);
                }
            }
            for ($r = 1; $r <= 7; $r++) {
                for ($c = 1; $c <= 3; $c++) {
                    $pid = trim((string)($grid[$r][$c] ?? ''));
                    if ($pid === '' || !isset($all[$pid])) continue;
                    $all[$pid]['row']   = $r;
                    $all[$pid]['order'] = $r * 10 + $c;
                }
            }
        });
        go('چیدمانِ دستیِ محصولات ذخیره شد.');
    }

    // ---- 🧲 چیدمانِ دستیِ دکمه‌های واقعیِ «ثبت سفارش»: گریدِ ۷×۳ ----
    // همان قانونِ گریدِ محصولات، ولی این‌بار روی خودِ زیردکمه‌های دکمه‌ی
    // buy — همان چیزی که در ربات زیرِ «ثبت سفارش» به مشتری نشان داده
    // می‌شود (چه محصول‌محور، چه خودقیمت‌گذار مثلِ ممبر/بوست، چه دکمه‌های
    // مینی‌اپِ ادغام‌شده). دکمه‌های مینی‌اپ رکوردِ subs ندارند، پس جای
    // row/order‌شان جدا (میان‌اپ) است — با شناسه‌ی مصنوعیِ __ma_<کلید>
    // تشخیص داده می‌شوند و در همان یک cfgSet اتمیک ذخیره می‌شوند.
    if ($a === 'save_button_grid') {
        $grid = (array)($_POST['grid'] ?? []);
        cfgSet(function (&$c) use ($grid) {
            for ($gr = 1; $gr <= 7; $gr++) {
                for ($gc = 1; $gc <= 3; $gc++) {
                    $ord = $gr * 10 + $gc;
                    if (!empty($c['buttons']['buy']['subs'])) {
                        foreach ($c['buttons']['buy']['subs'] as $i => &$sub) {
                            if ((int)($sub['row'] ?? 0) === $gr && (int)($sub['order'] ?? 0) === $ord) $sub['row'] = 0;
                        }
                        unset($sub);
                    }
                    foreach (array_keys($c['miniapps']['apps'] ?? []) as $mk) {
                        if (!is_array($c['miniapps']['apps'][$mk]['btn'] ?? null)) continue;
                        $btn = &$c['miniapps']['apps'][$mk]['btn'];
                        if ((int)($btn['row'] ?? 0) === $gr && (int)($btn['order'] ?? 0) === $ord) $btn['row'] = 0;
                        unset($btn);
                    }
                }
            }
            for ($gr = 1; $gr <= 7; $gr++) {
                for ($gc = 1; $gc <= 3; $gc++) {
                    $id = trim((string)($grid[$gr][$gc] ?? ''));
                    if ($id === '') continue;
                    $ord = $gr * 10 + $gc;
                    if (str_starts_with($id, '__ma_')) {
                        $mk = substr($id, 5);
                        if (!isset($c['miniapps']['apps'][$mk])) continue;
                        if (!is_array($c['miniapps']['apps'][$mk]['btn'] ?? null)) $c['miniapps']['apps'][$mk]['btn'] = [];
                        $c['miniapps']['apps'][$mk]['btn']['row']   = $gr;
                        $c['miniapps']['apps'][$mk]['btn']['order'] = $ord;
                        continue;
                    }
                    if (empty($c['buttons']['buy']['subs'])) continue;
                    foreach ($c['buttons']['buy']['subs'] as $i => &$sub) {
                        if (($sub['id'] ?? '') === $id) { $sub['row'] = $gr; $sub['order'] = $ord; break; }
                    }
                    unset($sub);
                }
            }
        });
        go('چیدمانِ دستیِ دکمه‌های ثبت سفارش ذخیره شد.');
    }

    // ---- دکمه سفارشی ----

    // ---- شرکا ----
    if ($a === 'add_partner') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') go('نام لازم است.', 'err');
        $p = Partner::create($name, trim($_POST['bot_username'] ?? ''), (int)($_POST['owner_id'] ?? 0));
        go('شریک «' . $name . '» ساخته شد. کلید API پایین صفحه است.');
    }
    if ($a === 'del_partner')    { Partner::remove($_POST['id'] ?? ''); go('شریک حذف شد.'); }
    if ($a === 'rotate_key')     { Partner::rotateKey($_POST['id'] ?? ''); go('کلید عوض شد — کلید قبلی دیگر کار نمی‌کند.'); }
    if ($a === 'toggle_partner') {
        $id = $_POST['id'] ?? '';
        mutate('partners', function (&$x) use ($id) {
            if (isset($x[$id])) $x[$id]['active'] = empty($x[$id]['active']);
        });
        go('وضعیت شریک تغییر کرد.');
    }

    // ---- کمپین‌ها (سفارش ممبر) ----
    if ($a === 'add_campaign') {
        $chat   = trim($_POST['chat_id'] ?? '');
        $target = (int)($_POST['target'] ?? 0);
        if ($chat === '' || $target <= 0) go('آیدی کانال و تعداد ممبر لازم است.', 'err');

        $r = tg(BOT_TOKEN, 'getChat', ['chat_id' => $chat], 8);
        if (empty($r['ok'])) go('کانال پیدا نشد: ' . ($r['description'] ?? '') . ' — ربات مادر را در کانال ادمین کنید.', 'err');
        $title = trim($_POST['title'] ?? '') ?: ($r['result']['title'] ?? $chat);
        $un    = $r['result']['username'] ?? '';
        $url   = trim($_POST['url'] ?? '') ?: ($un ? "https://t.me/$un" : '');
        if (!$url) {
            $inv = tg(BOT_TOKEN, 'createChatInviteLink', ['chat_id' => $chat, 'name' => 'کمپین'], 8);
            if (!empty($inv['ok'])) $url = $inv['result']['invite_link'];
        }
        Campaign::create($title, $chat, $url, $target,
                         (array)($_POST['partners'] ?? []), (array)($_POST['bots'] ?? []),
                         trim($_POST['note'] ?? ''));
        go('کمپین «' . $title . '» ساخته شد — ' . $target . ' ممبر.');
    }
    if ($a === 'del_campaign') { Campaign::remove($_POST['id'] ?? ''); go('کمپین حذف شد.'); }
    if ($a === 'toggle_campaign') {
        $id = $_POST['id'] ?? '';
        mutate('campaigns', function (&$x) use ($id) {
            if (isset($x[$id])) $x[$id]['active'] = empty($x[$id]['active']);
        });
        go('وضعیت کمپین تغییر کرد.');
    }
    if ($a === 'edit_campaign') {
        $id = $_POST['id'] ?? '';
        $post = $_POST;
        mutate('campaigns', function (&$x) use ($id, $post) {
            if (!isset($x[$id])) return;
            $x[$id]['target']   = max(0, (int)($post['target'] ?? 0));
            $x[$id]['title']    = trim($post['title'] ?? $x[$id]['title']);
            $x[$id]['url']      = trim($post['url'] ?? $x[$id]['url']);
            if (isset($post['chat_id'])) {
                $newChat = trim($post['chat_id']);
                if ($newChat !== '' && $newChat !== ($x[$id]['chat_id'] ?? '')) {
                    // آیدی کانال تازه آمد — خطاها را صفر و کمپین را روشن کن
                    $x[$id]['chat_id']       = $newChat;
                    $x[$id]['fails']         = 0;
                    $x[$id]['paused_reason'] = '';
                    $x[$id]['active']        = true;
                }
            }
            $x[$id]['partners'] = array_values((array)($post['partners'] ?? []));
            $x[$id]['bots']     = array_values((array)($post['bots'] ?? []));
        });
        go('کمپین به‌روزرسانی شد.');
    }

    // ---- مدیران ربات اپلودر ----
    if ($a === 'add_bot_admin') {
        $bid = $_POST['id'] ?? '';
        $u = (int)($_POST['user_id'] ?? 0);
        if ($u <= 0) go('آیدی عددی معتبر بدهید.', 'err');
        BotManager::addAdmin($bid, $u);
        $b = BotManager::get($bid);
        if ($b) sendMsg($b['token'], $u, "👑 شما به‌عنوان مدیر ربات @" . h($b['username']) . " ثبت شدید.\n\nبا /panel وارد پنل شوید.");
        go('مدیر اضافه شد.');
    }
    if ($a === 'del_bot_admin') {
        BotManager::removeAdmin($_POST['id'] ?? '', (int)($_POST['user_id'] ?? 0));
        go('مدیر حذف شد.');
    }

    // ---- تنظیمات کامل هر ربات اپلودر ----
    if ($a === 'save_bot_full') {
        $id = $_POST['id'] ?? '';
        $post = $_POST;
        if (!BotManager::get($id)) go('ربات پیدا نشد.', 'err');

        BotManager::setSetting($id, 'delete_seconds', max(5, (int)($post['del_sec'] ?? 30)));
        BotManager::setSetting($id, 'force_join', !empty($post['force_join']));
        BotManager::setSetting($id, 'protect_content', !empty($post['protect']));
        BotManager::setSetting($id, 'inline_wait', !empty($post['inline_wait']));
        foreach (['start_text','join_text','joined_btn','warn_text','deleted_text','expired_text','menu_text'] as $k) {
            if (isset($post[$k])) BotManager::setSetting($id, $k, $post[$k]);
        }
        $btns = BotManager::settings($id)['buttons'];
        foreach (array_keys($btns) as $bk) {
            $btns[$bk]['emoji'] = trim($post["b_emoji_$bk"] ?? '');
            $btns[$bk]['text']  = trim($post["b_text_$bk"] ?? $btns[$bk]['text']);
            $btns[$bk]['color'] = isStyle($post["b_color_$bk"] ?? '') ? $post["b_color_$bk"] : 'none';
            $btns[$bk]['icon']  = trim($post["b_icon_$bk"] ?? '');
            $btns[$bk]['row']   = max(1, (int)($post["b_row_$bk"] ?? 1));
            $btns[$bk]['order'] = max(1, (int)($post["b_order_$bk"] ?? 1));
            $btns[$bk]['on']    = !empty($post["b_on_$bk"]);
        }
        BotManager::setSetting($id, 'buttons', $btns);

        $gc = BotManager::settings($id)['glass_colors'];
        foreach (array_keys($gc) as $role) {
            $v = $post["bg_$role"] ?? 'none';
            $gc[$role] = isStyle($v) ? $v : 'none';
        }
        BotManager::setSetting($id, 'glass_colors', $gc);

        // کانال‌های مخصوص این ربات
        $chosen = $post['bot_channels'] ?? [];
        mutate('channels', function (&$all) use ($id, $chosen) {
            foreach ($all as $cid => $ch) {
                $bots = array_values(array_filter($ch['bots'] ?? [], fn($x) => $x !== $id));
                if (in_array($cid, (array)$chosen, true)) $bots[] = $id;
                $all[$cid]['bots'] = array_values(array_unique($bots));
            }
        });

        if (!empty($post['apply_all'])) {
            $src = BotManager::settings($id);
            foreach (BotManager::all() as $ob) {
                if ($ob['id'] === $id) continue;
                foreach (['delete_seconds','force_join','protect_content','inline_wait','start_text',
                          'join_text','joined_btn','warn_text','deleted_text','expired_text',
                          'menu_text','buttons','glass_colors'] as $k) {
                    BotManager::setSetting($ob['id'], $k, $src[$k]);
                }
            }
            go('تنظیمات ذخیره و روی همه ربات‌ها اعمال شد.');
        }
        go('تنظیمات ربات ذخیره شد.');
    }

    // ---- کانال‌های اجباری ----
    if ($a === 'add_channel') {
        $chat = trim($_POST['chat_id'] ?? '');
        if ($chat === '') go('آیدی کانال لازم است.', 'err');
        $r = tg(BOT_TOKEN, 'getChat', ['chat_id' => $chat]);
        if (empty($r['ok'])) go('کانال پیدا نشد: ' . ($r['description'] ?? ''), 'err');
        $title = $r['result']['title'] ?? $chat;
        $un = $r['result']['username'] ?? '';
        $url = trim($_POST['url'] ?? '') ?: ($un ? "https://t.me/$un" : ($r['result']['invite_link'] ?? ''));
        Channels::add($chat, $title, $url, (array)($_POST['bots'] ?? []));
        go('کانال «' . $title . '» اضافه شد. ربات‌های اپلودر را در آن ادمین کنید.');
    }
    if ($a === 'del_channel') { Channels::remove($_POST['id'] ?? ''); go('کانال حذف شد.'); }
    if ($a === 'health') {
        $lines = [];
        foreach (Channels::health() as $r) {
            $lines[] = ($r['ok'] ? '✅' : '❌') . ' ' . $r['title'] .
                       ($r['ok'] ? ' — ربات مادر دسترسی دارد' : ' — ' . ($r['error'] ?: 'ربات مادر ادمین نیست'));
        }
        $_SESSION['health'] = $lines ?: ['کانالی برای بررسی نیست.'];
        go('بررسی انجام شد.');
    }
    if ($a === 'toggle_channel') {
        $id = $_POST['id'] ?? '';
        mutate('channels', function (&$c) use ($id) {
            if (isset($c[$id])) $c[$id]['on'] = empty($c[$id]['on']);
        });
        go('وضعیت کانال تغییر کرد.');
    }

    // ---- ربات‌ها ----
    if ($a === 'locks_rebalance') {
        $n = 0;
        foreach (Campaign::all() as $c) {
            if (empty($c['active']) || Campaign::isDone($c)) continue;
            mutate('campaigns', function (&$a2) use ($c) { if (isset($a2[$c['id']])) $a2[$c['id']]['bots'] = []; });
            assignCampaignBots($c['id']);
            $n++;
        }
        go("✅ {$n} کمپین دوباره بین ربات‌ها پخش شد.");
    }
    if ($a === 'add_bot') {
        $token = trim($_POST['token'] ?? '');
        if (!preg_match('/^\d{6,}:[A-Za-z0-9_\-]{30,}$/', $token)) go('فرمت توکن درست نیست.', 'err');
        $me = tg($token, 'getMe', []);
        if (empty($me['ok'])) go('توکن معتبر نیست: ' . ($me['description'] ?? ''), 'err');
        $bot = BotManager::create($token, $me['result']['username']);
        $hook = baseUrl() . '/bot_master_membership.php?bot=' . $bot['id'];
        $r = tg($token, 'setWebhook', ['url' => $hook, 'drop_pending_updates' => 'true', 'secret_token' => WEBHOOK_SECRET]);
        go('ربات @' . $bot['username'] . ' اضافه شد.' .
           (!empty($r['ok']) ? ' وبهوک تنظیم شد.' : ' هشدار: وبهوک تنظیم نشد.'),
           !empty($r['ok']) ? 'ok' : 'warn');
    }
    if ($a === 'del_bot') {
        $id = $_POST['id'] ?? '';
        $b = BotManager::get($id);
        if ($b) tg($b['token'], 'deleteWebhook', []);
        mutate('bots', function (&$all) use ($id) { unset($all[$id]); });
        go('ربات حذف شد.');
    }
    if ($a === 'bot_webhook') {
        $b = BotManager::get($_POST['id'] ?? '');
        if (!$b) go('ربات پیدا نشد.', 'err');
        $r = tg($b['token'], 'setWebhook',
            ['url' => baseUrl() . '/bot_master_membership.php?bot=' . $b['id'], 'drop_pending_updates' => 'true', 'secret_token' => WEBHOOK_SECRET]);
        go(!empty($r['ok']) ? 'وبهوک تنظیم شد.' : 'خطا: ' . ($r['description'] ?? ''), !empty($r['ok']) ? 'ok' : 'err');
    }
    if ($a === 'master_webhook') {
        // my_chat_member لازم است تا ثبت خودکار کانال کار کند
        $r = tg(BOT_TOKEN, 'setWebhook', [
            'url' => baseUrl() . '/bot_master_membership.php',
            'drop_pending_updates' => 'true',
            'allowed_updates' => json_encode(['message', 'callback_query', 'my_chat_member']),
            'secret_token' => WEBHOOK_SECRET,
        ]);
        go(!empty($r['ok']) ? 'وبهوک ربات مادر تنظیم شد.' : 'خطا: ' . ($r['description'] ?? ''), !empty($r['ok']) ? 'ok' : 'err');
    }
    if ($a === 'save_bot') {
        $id = $_POST['id'] ?? '';
        BotManager::setSetting($id, 'delete_seconds', max(5, (int)($_POST['del_sec'] ?? 30)));
        BotManager::setSetting($id, 'force_join', !empty($_POST['force_join']));
        BotManager::setSetting($id, 'protect_content', !empty($_POST['protect']));
        foreach (['start_text', 'join_text', 'joined_btn', 'warn_text', 'deleted_text', 'expired_text'] as $k) {
            if (isset($_POST[$k])) BotManager::setSetting($id, $k, $_POST[$k]);
        }
        go('تنظیمات ربات ذخیره شد.');
    }
    if ($a === 'del_link') {
        Links::remove($_POST['bot'] ?? '', $_POST['code'] ?? '');
        go('لینک حذف شد.');
    }

    // ---- سفارش‌ها ----
    if ($a === 'approve_order') {
        [$ok, $res] = Order::approve($_POST['id'] ?? '', ADMIN_ID);
        if (!$ok) go($res, 'err');
        completeApprovedOrder($res);   // اطلاع به کاربر + تحویل + اعلام در کانال فروش
        go('سفارش تایید شد و به کاربر اطلاع داده شد.');
    }
    if ($a === 'reject_order') {
        [$ok, $res] = Order::reject($_POST['id'] ?? '', ADMIN_ID);
        if (!$ok) go($res, 'err');
        sendMsg(BOT_TOKEN, $res['user_id'], T('rejected'));
        go('سفارش رد شد.');
    }

    // ---- کاربران ----
    if ($a === 'ban_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        mutateUser($uid, function (&$user) {
            if ($user !== null) $user['banned'] = empty($user['banned']);
        });
        go('وضعیت کاربر تغییر کرد.');
    }
    if ($a === 'set_balance') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $val = (float)str_replace(',', '', $_POST['balance'] ?? '0');
        mutateUser($uid, function (&$user) use ($val) {
            if ($user !== null) $user['balance'] = $val;
        });
        go('موجودی به‌روزرسانی شد.');
    }
    if ($a === 'broadcast') {
        $text = trim($_POST['text'] ?? '');
        if ($text === '') go('متن خالی است.', 'err');
        // حلقه‌ی مستقیم با هزار کاربر بیش از یک دقیقه طول می‌کشید و
        // مرورگر/PHP وسط کار قطع می‌کرد. حالا در صف می‌رود و پس‌زمینه می‌فرستد.
        [$n, $err] = bcQueue($text);
        if ($n <= 0) go($err ?: 'هیچ گیرنده‌ای نبود.', 'err');
        go("در صف ارسال قرار گرفت — {$n} گیرنده. ارسال در پس‌زمینه انجام می‌شود.");
    }

    go();
}

// ------------------------------------------------------------
// 📊 داده‌ها
// ------------------------------------------------------------

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$C        = cfg();
$products = Product::all();
$bots     = BotManager::all();
$orders   = Order::all();
$users    = allUsers();
// شمارش زیرمجموعه‌ها یک‌بار برای کل جدول (قبلا برای هر ردیف کل کاربران پیمایش می‌شد)
$refCount = [];
foreach ($users as $_u) {
    $r = (int)($_u['referrer'] ?? 0);
    if ($r > 0) $refCount[$r] = ($refCount[$r] ?? 0) + 1;
}
$channels  = Channels::all();
$partners  = Partner::all();
$campaigns = Campaign::all();

uasort($orders, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
$pending  = array_filter($orders, fn($o) => $o['status'] === Order::REVIEW);
$approved = array_filter($orders, fn($o) => $o['status'] === Order::APPROVED);

$revenue = [];
foreach ($approved as $o) {
    if ($o['type'] !== 'product') continue;
    $revenue[$o['currency']] = ($revenue[$o['currency']] ?? 0) + (float)$o['amount'];
}
$totalBalance = 0;
foreach ($users as $u) $totalBalance += (float)($u['balance'] ?? 0);

$tab    = $_GET['tab'] ?? 'dashboard';
$curBot = $_GET['bot'] ?? '';

/**
 * فیلدِ انتخابِ سرویسِ پنلِ SMM — اگر لیست از قبل گرفته شده باشد یک
 * <select> واقعی نشان می‌دهد (نام + قیمتِ هر ۱۰۰۰ تا)، وگرنه یک ورودیِ
 * متنیِ ساده برای واردکردنِ دستیِ شماره — تا وقتی هنوز لیستی نگرفته‌ای
 * هم چیزی خراب نشود.
 */
/** سرویس‌های کش‌شده به شکلِ [{v: شناسه, t: برچسب}] — برای رندرِ سلکت و فیلترِ جاوااسکریپتی هر دو */
function smmOptionsList($list) {
    $out = [];
    foreach ($list as $s) {
        $sid = (string)($s['service'] ?? '');
        if ($sid === '') continue;
        $out[] = ['v' => $sid, 't' => trim(($s['name'] ?? 'سرویس ' . $sid) . ' — ' . ($s['rate'] ?? '?') . '/1000')];
    }
    return $out;
}

/**
 * سلکتِ قابلِ‌جستجوی یک سرویسِ پنل. پنل‌های SMM معمولاً هزاران سرویس (همه‌ی
 * پلتفرم‌ها) دارند، نه فقط بوستِ تلگرام — بدونِ جستجو، پیداکردنِ یک موردِ
 * خاص (مثلاً «بوستِ ۱۴ روزه») توی یک سلکتِ چند-هزارتایی عملاً غیرممکنه.
 */
function smmSelectHtml($name, $opts, $current, $emptyLabel = '— دستی نیست، وصل نکن —') {
    echo '<input type="text" class="smm-search" placeholder="🔎 جستجو در سرویس‌ها (اسم یا شماره)…" ' .
         'oninput="smmFilterSelect(this)" style="margin-bottom:6px">';
    echo '<select name="' . h($name) . '" data-options="' . h(json_encode($opts, JSON_UNESCAPED_UNICODE)) . '" ' .
         'data-empty="' . h($emptyLabel) . '" style="max-width:100%">';
    echo '<option value="">' . h($emptyLabel) . '</option>';
    foreach ($opts as $o) {
        echo '<option value="' . h($o['v']) . '"' . ((string)$current === (string)$o['v'] ? ' selected' : '') . '>' .
             h($o['t']) . '</option>';
    }
    // اگر شماره‌ی فعلی توی لیستِ تازه نبود (مثلا از پنلی دیگر یا حذف شده)، گمش نکن
    if ($current !== '' && !in_array((string)$current, array_map(fn($o) => (string)$o['v'], $opts), true)) {
        echo '<option value="' . h($current) . '" selected>سرویسِ فعلی (' . h($current) . ') — دیگر در لیست نیست</option>';
    }
    echo '</select>';
}

function smmServiceField($current, $autoOn = false) {
    $list = function_exists('smmServicesCached') ? smmServicesCached() : [];
    if (!$list) {
        echo '<input name="smm_service" value="' . h($current) . '" placeholder="مثلا 1234" style="direction:ltr">';
        echo '<small class="muted">لیستِ سرویس‌ها هنوز گرفته نشده — بالای همین تب، «🔄 بروزرسانی لیست سرویس‌ها» را بزن. ' .
             'تا وقتی نگرفتی، می‌تونی همین‌جا شماره‌ی سرویس رو دستی از پنلِ خودت بنویسی.</small>';
    } else {
        smmSelectHtml('smm_service', smmOptionsList($list), $current);
    }
    // ⚠️ این چک‌باکس باید همیشه دیده بشه — حتی وقتی لیستِ سرویس‌ها هنوز نیومده و بالا
    // فقط یک اینپوتِ متنی داریم؛ وگرنه ادمین راهی برای روشن‌کردنِ قیمتِ خودکار نداره.
    echo '<label style="font-weight:500;display:block;margin-top:6px">' .
         '<input type="checkbox" name="smm_auto_price" value="1" style="width:auto"' . ($autoOn ? ' checked' : '') . '> ' .
         '🔄 قیمتِ خودکار از نرخِ پنل (تتر → تومان، با سودِ بخش «خرید ممبر»)</label>';
    echo '<small class="muted">وقتی روشنه، فیلدِ «قیمت پایه» بالا نادیده گرفته می‌شود — قیمت هربار از نرخِ زنده‌ی ' .
         'پنل و تتر و سودِ تنظیم‌شده در تب سود حساب می‌شود.</small>';
}

/** این محصول برای محاسبه‌ی سود، دسته‌اش چیه — تا تبِ «سود» بشناستش و جدا حسابش کند */
function saleCatField($current) {
    $opts = ['' => '🎯 ممبر (پیش‌فرض)', 'fake_member' => '👤 ممبر فیک', 'boost' => '🚀 بوست'];
    echo '<select name="sale_cat">';
    foreach ($opts as $v => $l)
        echo '<option value="' . h($v) . '"' . ($current === $v ? ' selected' : '') . '>' . h($l) . '</option>';
    echo '</select>';
}

/** لینکِ همین صفحه با چند پارامترِ GET عوض‌شده — برای فیلتر/مرتب‌سازی/صفحه‌بندی */
function qsWith($params) {
    $q = array_merge($_GET, $params);
    unset($q['bot']); // پارامترِ ربات مالِ تبِ ربات‌هاست، اینجا بی‌ربط است
    return '?' . http_build_query($q);
}

/** نوارِ صفحه‌بندیِ ساده — ۱ … قبل، فعلی، بعد … آخر */
function pager($page, $pages) {
    if ($pages <= 1) return;
    echo '<div class="pager">';
    if ($page > 1) echo '<a href="' . h(qsWith(['page' => $page - 1])) . '">‹ قبلی</a>';
    $start = max(1, $page - 2); $end = min($pages, $page + 2);
    if ($start > 1) { echo '<a href="' . h(qsWith(['page' => 1])) . '">1</a>'; if ($start > 2) echo '<span class="dots">…</span>'; }
    for ($i = $start; $i <= $end; $i++) {
        echo $i === $page ? '<span class="cur">' . $i . '</span>' : '<a href="' . h(qsWith(['page' => $i])) . '">' . $i . '</a>';
    }
    if ($end < $pages) { if ($end < $pages - 1) echo '<span class="dots">…</span>'; echo '<a href="' . h(qsWith(['page' => $pages])) . '">' . $pages . '</a>'; }
    if ($page < $pages) echo '<a href="' . h(qsWith(['page' => $page + 1])) . '">بعدی ›</a>';
    echo '</div>';
}

function uLabel($users, $id) {
    $u = $users[(string)$id] ?? null;
    if ($u && !empty($u['username']))   return '@' . $u['username'];
    if ($u && !empty($u['first_name'])) return $u['first_name'];
    return (string)$id;
}
function oBadge($s) {
    $m = ['pending' => ['⏳ منتظر رسید', 'gray'], 'review' => ['🧾 بررسی', 'amber'],
          'approved' => ['✅ تایید', 'green'], 'rejected' => ['❌ رد', 'red']];
    [$l, $c] = $m[$s] ?? ['—', 'gray'];
    return '<span class="badge ' . $c . '">' . $l . '</span>';
}

/**
 * 📈 نمودارِ خطیِ ۷ روزِ اخیر — از همان $orders که قبلا خوانده شده،
 * بدون هیچ کوئری یا فراخوانیِ اضافه. فقط SVG خام، بدونِ کتابخانه.
 */
function dashSparkline($orders, $days = 7) {
    $buckets = [];
    for ($i = $days - 1; $i >= 0; $i--) $buckets[date('Y-m-d', strtotime("-$i day"))] = 0;
    foreach ($orders as $o) {
        $d = substr((string)($o['created_at'] ?? ''), 0, 10);
        if (isset($buckets[$d])) $buckets[$d]++;
    }
    $vals = array_values($buckets);
    $max = max(1, max($vals));
    $w = 280; $h = 52; $step = $w / max(1, count($vals) - 1);
    $pts = [];
    foreach ($vals as $i => $v) $pts[] = round($i * $step, 1) . ',' . round($h - ($v / $max) * ($h - 6) - 3, 1);
    $line = implode(' ', $pts);
    $fillPts = '0,' . $h . ' ' . $line . ' ' . $w . ',' . $h;
    $total = array_sum($vals);
    $out = '<svg class="sparkline" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img" ' .
           'aria-label="سفارش‌های ۷ روز اخیر: مجموعا ' . $total . ' سفارش">' .
           '<polygon points="' . h($fillPts) . '" fill="var(--blue-dim)" opacity=".5"></polygon>' .
           '<polyline points="' . h($line) . '" fill="none" stroke="var(--blue)" stroke-width="2.2" ' .
           'stroke-linecap="round" stroke-linejoin="round"></polyline></svg>';
    return [$out, $total];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>پنل مدیریت فروشگاه</title>
<style>
:root{--bg:#0a0a10;--panel:rgba(26,26,36,.66);--panel-alt:rgba(38,38,50,.5);--ink:#f2f2f2;--ink-soft:#c2c2c2;--muted:#8a8a8a;
--line:rgba(255,255,255,.09);--line-soft:rgba(255,255,255,.06);--blue:#2f7de1;--blue-dim:#1c3f66;--red:#e5484d;--red-dim:#5c2224;
--green:#2fbf6f;--green-dim:#1d4a32;--amber:#e0a72e;--amber-dim:#54430f;--blur:18px}
*{box-sizing:border-box;margin:0;padding:0}
html{background:var(--bg)}
body{font-family:Vazirmatn,Vazir,'IRANSans','IRANYekan',system-ui,-apple-system,'Segoe UI',Tahoma,sans-serif;
background:var(--bg);color:var(--ink);padding-bottom:60px;
position:relative;min-height:100vh;font-size:14px;line-height:1.6}
:focus-visible{outline:2px solid var(--blue);outline-offset:2px;border-radius:4px}
.ltr{direction:ltr;unicode-bidi:isolate}
.amount{font-variant-numeric:tabular-nums;font-weight:800;direction:ltr;unicode-bidi:isolate;display:inline-block}
/* 🌌 اتمسفرِ شیشه‌ای — چند لکه‌ی رنگیِ ثابت پشتِ همه‌چیز، بدونِ filter، هزینه‌ی اسکرول صفر */
body::before{content:"";position:fixed;inset:0;z-index:0;pointer-events:none;
  background:
    radial-gradient(46vw 46vw at 92% -6%,rgba(47,125,225,.16),transparent 68%),
    radial-gradient(40vw 40vw at 2% 18%,rgba(47,191,111,.12),transparent 66%),
    radial-gradient(36vw 36vw at 78% 108%,rgba(150,80,230,.1),transparent 64%)}
header,.shell{position:relative;z-index:1}
a{color:inherit;text-decoration:none}
header{background:rgba(8,8,12,.62);backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));
color:#fff;padding:16px 20px;border-bottom:1px solid var(--line)}
.wrap{max-width:1200px;margin:0 auto;padding:0 16px}
header h1{font-size:19px;font-weight:800}
header .row{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.logout{background:var(--red-dim);border:1px solid var(--red);color:#fff;padding:8px 16px;border-radius:8px;font-size:13px;transition:transform .15s,opacity .15s}
.logout:hover{opacity:.85;transform:translateY(-1px)}
.hdr-search input{padding:8px 12px;font-size:12.5px;background:rgba(255,255,255,.06);border-color:var(--line)}
.hdr-meta{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.status-pill{display:inline-flex;align-items:center;gap:7px;font-size:12.5px;color:var(--ink-soft);
  background:rgba(255,255,255,.05);border:1px solid var(--line);padding:6px 12px;border-radius:20px}
.status-dot{width:8px;height:8px;border-radius:50%;background:var(--green);box-shadow:0 0 0 3px var(--green-dim);flex:0 0 auto}
.status-dot.warn{background:var(--amber);box-shadow:0 0 0 3px var(--amber-dim)}
.status-dot.bad{background:var(--red);box-shadow:0 0 0 3px var(--red-dim)}
@media(prefers-reduced-motion:no-preference){.status-dot{animation:pulseDot 2.4s ease-in-out infinite}}
@keyframes pulseDot{0%,100%{opacity:1}50%{opacity:.5}}
.crumb{font-size:12.5px;color:var(--muted);margin-bottom:14px;display:flex;align-items:center;gap:6px}
.crumb b{color:var(--ink-soft);font-weight:700}

/* ---------- shell: right sidebar + content (folder-grouped nav) ---------- */
.nav-toggle-cb{display:none}
.nav-toggle-btn{display:none}
.nav-backdrop{display:none}
.shell{display:flex;align-items:flex-start;max-width:1320px;margin:0 auto}
.sidebar{width:225px;flex:0 0 225px;background:rgba(6,6,10,.58);backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));
border-left:1px solid var(--line);
min-height:calc(100vh - 57px);position:sticky;top:0;align-self:flex-start;overflow-y:auto}
.sidebar-inner{padding:14px 10px}
.nav-folder{margin-bottom:6px;border:none}
.nav-folder>summary{list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;
font-size:11px;font-weight:800;color:var(--muted);text-transform:uppercase;
letter-spacing:.4px;padding:8px 10px;border-radius:7px;user-select:none}
.nav-folder>summary::-webkit-details-marker{display:none}
.nav-folder>summary:hover{background:var(--panel-alt);color:var(--ink-soft)}
.nav-folder>summary .fc{transition:transform .18s;font-size:10px;opacity:.7}
.nav-folder[open]>summary .fc{transform:rotate(90deg)}
.nav-folder-body{padding-top:2px}
.sidebar a{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:9px 12px;border-radius:8px;font-size:13.5px;font-weight:600;
color:var(--ink-soft);margin-bottom:2px;transition:background .15s,color .15s,transform .15s}
.sidebar a:hover{background:var(--panel-alt);transform:translateX(-2px)}
.sidebar a.on{background:linear-gradient(135deg,var(--blue),#4f9cf0);color:#fff;box-shadow:0 6px 16px -8px rgba(47,125,225,.6)}
.sidebar a .nbadge{flex:0 0 auto;background:var(--red);color:#fff;font-size:10.5px;font-weight:800;
  border-radius:20px;padding:1px 7px;min-width:18px;text-align:center;line-height:1.6}
.sidebar a.on .nbadge{background:rgba(255,255,255,.28)}
.content{flex:1;min-width:0;padding:18px 16px}
.content .wrap{max-width:1000px;margin:0;padding:0}
@media(max-width:900px){
  .nav-toggle-btn{display:flex;align-items:center;justify-content:center;margin:10px 16px;min-height:46px;
    padding:10px 14px;background:var(--blue);color:#fff;
    border-radius:8px;font-weight:700;font-size:13.5px;text-align:center;cursor:pointer;
    max-width:1200px;margin-left:auto;margin-right:auto}
  .sidebar{position:fixed;top:0;right:0;height:100vh;z-index:50;transform:translateX(100%);
    transition:transform .22s ease;box-shadow:-6px 0 24px rgba(0,0,0,.5);width:270px;max-width:82vw}
  .nav-toggle-cb:checked ~ .shell .sidebar{transform:translateX(0)}
  .nav-toggle-cb:checked ~ .nav-backdrop{display:block;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:40}
  .content{padding:6px 16px}
  .sidebar a{min-height:44px;padding:11px 12px}
  .nav-folder>summary{min-height:40px}
  .hdr-meta{gap:8px}
  .status-pill{padding:5px 9px;font-size:11.5px}
  .status-pill:nth-child(2){display:none}
}
@media(max-width:640px){
  .hdr-search{display:none}
}
@media(max-width:420px){
  header h1{font-size:15px}
  .status-pill{display:none}
}

.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin:0 0 20px}
.stat{background:var(--panel);backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));
padding:18px;border-radius:12px;border:1px solid var(--line);transition:transform .18s,box-shadow .18s}
.stat:hover{transform:translateY(-2px);box-shadow:0 12px 28px -18px rgba(0,0,0,.6)}
.stat .n{font-size:26px;font-weight:800;color:var(--blue)}
.stat .l{color:var(--muted);font-size:12.5px;margin-top:5px}
.card{background:var(--panel);backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));
border-radius:12px;border:1px solid var(--line);margin-bottom:18px;overflow:hidden;
box-shadow:0 14px 34px -24px rgba(0,0,0,.7);animation:cardIn .35s cubic-bezier(.2,.9,.3,1) backwards}
@keyframes cardIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.card h2{padding:15px 18px;font-size:14.5px;font-weight:800;border-bottom:1px solid var(--line);background:rgba(255,255,255,.03)}
.card>summary{list-style:none;cursor:pointer;user-select:none;display:flex;align-items:center;justify-content:space-between;
  gap:10px;flex-wrap:wrap;padding:15px 18px;font-size:14.5px;font-weight:800;border-bottom:1px solid var(--line);background:rgba(255,255,255,.03)}
.card>summary::-webkit-details-marker{display:none}
.card>summary::after{content:'▾';color:var(--muted);font-size:12px;font-weight:400;transition:transform .2s;flex:0 0 auto}
.card[open]>summary::after{transform:rotate(180deg)}
.card:not([open])>summary{border-bottom:0}
.card.prodcard.filtered-out{display:none}
.card .body{padding:18px}
.subcard{background:var(--panel-alt);border:1px solid var(--line);border-radius:10px;padding:14px;margin-bottom:16px;transition:border-color .15s}
.subcard:hover{border-color:rgba(255,255,255,.16)}
.subcard:last-child{margin-bottom:0}
.subcard>h3{font-size:13px;font-weight:800;color:var(--ink);margin-bottom:11px;display:flex;align-items:center;gap:6px}
/* ---------- بخش‌های جمع‌شونده: فقط عنوان دیده می‌شود، کلیک باز می‌کند ---------- */
.subcard>summary{list-style:none;cursor:pointer;user-select:none;display:flex;align-items:center;justify-content:space-between;gap:8px}
.subcard>summary::-webkit-details-marker{display:none}
.subcard>summary>h3{margin-bottom:0;flex:1}
.subcard>summary::after{content:'▾';color:var(--muted);font-size:12px;transition:transform .2s;flex:0 0 auto}
.subcard[open]>summary{margin-bottom:11px}
.subcard[open]>summary::after{transform:rotate(180deg)}
.grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:13px}
label{display:block;font-size:12.5px;font-weight:700;color:var(--ink-soft);margin-bottom:5px}
input,select,textarea{width:100%;padding:10px 12px;border:1.5px solid var(--line);border-radius:8px;
font-size:13.5px;font-family:inherit;background:var(--panel-alt);color:var(--ink);transition:border-color .15s,box-shadow .15s}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(47,125,225,.18)}
textarea{min-height:90px;resize:vertical;line-height:1.9}
.btn{display:inline-block;padding:10px 18px;border:1.5px solid var(--blue);border-radius:8px;font-size:13.5px;font-weight:700;
cursor:pointer;font-family:inherit;color:#fff;background:var(--blue);transition:transform .15s,opacity .15s,box-shadow .15s}
.btn:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 8px 20px -12px rgba(47,125,225,.7)}
.btn:active{transform:translateY(0) scale(.98)}
.btn.g{background:var(--green);border-color:var(--green)}.btn.r{background:var(--red);border-color:var(--red)}.btn.b{background:var(--blue);border-color:var(--blue)}
.btn.sm{padding:6px 12px;font-size:12px}
.btn.ghost{background:transparent;color:var(--ink);border-color:var(--line)}
table{width:100%;border-collapse:collapse;font-size:13px}
th{background:rgba(255,255,255,.03);padding:11px;text-align:right;font-weight:700;color:var(--ink-soft);white-space:nowrap;border-bottom:1px solid var(--line)}
td{padding:11px;border-top:1px solid var(--line-soft);vertical-align:middle;color:var(--ink)}
.scroll{overflow-x:auto}

/* ---------- 📦 محصول‌ها: جمع‌شونده — فقط خلاصه دیده می‌شود، کلیک باز می‌کند ---------- */
.itemrow{border:1px solid var(--line);border-radius:10px;margin-bottom:8px;background:var(--panel-alt);overflow:hidden}
.itemrow[open]{border-color:rgba(47,125,225,.4)}
.itemrow>summary{list-style:none;cursor:pointer;display:flex;align-items:center;gap:10px;padding:11px 13px;user-select:none}
.itemrow>summary::-webkit-details-marker{display:none}
.itemrow>summary .ir-name{flex:1;min-width:0;font-weight:700;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.itemrow>summary .ir-price{flex:0 0 auto;font-size:12.5px;color:var(--muted);direction:ltr}
.itemrow>summary .ir-car{flex:0 0 auto;transition:transform .2s;color:var(--muted);font-size:12px}
.itemrow[open]>summary .ir-car{transform:rotate(180deg)}
.itemrow .ir-body{padding:4px 13px 13px;border-top:1px solid var(--line-soft);
animation:pgIn .22s cubic-bezier(.2,.9,.3,1)}
@keyframes pgIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}
.badge{display:inline-block;padding:4px 10px;border-radius:20px;font-size:11.5px;font-weight:700;white-space:nowrap;border:1px solid var(--line)}
.badge.green{background:var(--green-dim);color:#8fe6b6;border-color:var(--green)}.badge.amber{background:var(--amber-dim);color:#f0cb75;border-color:var(--amber)}
.badge.red{background:var(--red-dim);color:#f5a3a6;border-color:var(--red)}.badge.gray{background:var(--panel-alt);color:var(--ink-soft);border-color:var(--line)}
.flash{padding:13px 17px;border-radius:10px;margin:16px 0;font-size:13.5px;font-weight:600;border:1px solid var(--line)}
.flash.ok{background:var(--green-dim);color:#8fe6b6;border-color:var(--green)}.flash.err{background:var(--red-dim);color:#f5a3a6;border-color:var(--red)}
.flash.warn{background:var(--amber-dim);color:#f0cb75;border-color:var(--amber)}
.empty{text-align:center;padding:38px 20px;color:var(--muted);font-size:13.5px;line-height:1.9}
.empty .ic{font-size:34px;margin-bottom:10px;opacity:.6}
.empty .cta{margin-top:14px}

/* ---------- ⚡ عملیات سریع ---------- */
.qa-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:11px}
.qa{display:flex;flex-direction:column;align-items:center;gap:7px;padding:16px 10px;border-radius:12px;
  border:1px solid var(--line);background:var(--panel-alt);color:var(--ink);text-align:center;
  font-size:12.5px;font-weight:700;transition:transform .16s,border-color .16s,background .16s;cursor:pointer}
.qa:hover{transform:translateY(-2px);border-color:rgba(47,125,225,.5);background:rgba(47,125,225,.08)}
.qa .e{font-size:22px}

/* ---------- 📣 بنر اقدام (سفارش‌های منتظر و مشابه) ---------- */
.callout{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:16px 18px;border-radius:12px;
  border:1px solid var(--amber);background:var(--amber-dim);margin-bottom:18px}
.callout.ok{border-color:var(--green);background:var(--green-dim)}
.callout .ic{font-size:26px;flex:0 0 auto}
.callout .tx{flex:1;min-width:200px}
.callout .tx b{display:block;font-size:14.5px;margin-bottom:2px}
.callout .tx span{font-size:12px;color:var(--ink-soft)}

/* ---------- 🔎 جستجو/فیلتر/مرتب‌سازی ---------- */
.toolbar{display:flex;gap:9px;flex-wrap:wrap;align-items:center;margin-bottom:14px}
.toolbar .search{position:relative;flex:1;min-width:180px}
.toolbar .search input{padding-right:36px}
.toolbar .search .si{position:absolute;top:50%;right:12px;transform:translateY(-50%);color:var(--muted);pointer-events:none;font-size:14px}
.toolbar select{width:auto;min-width:120px}
.chiprow{display:flex;gap:6px;flex-wrap:wrap}
.chiprow a{padding:6px 13px;border-radius:20px;font-size:12px;font-weight:700;border:1px solid var(--line);
  color:var(--ink-soft);background:var(--panel-alt);transition:.15s}
.chiprow a.on{background:var(--blue);border-color:var(--blue);color:#fff}
.chiprow a:hover{border-color:var(--blue)}

/* ---------- 📄 صفحه‌بندی ---------- */
.pager{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:16px;flex-wrap:wrap}
.pager a,.pager span{min-width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;
  padding:0 8px;border-radius:8px;font-size:12.5px;font-weight:700;border:1px solid var(--line);color:var(--ink-soft)}
.pager a:hover{border-color:var(--blue);color:#fff;background:var(--blue)}
.pager span.cur{background:var(--blue);border-color:var(--blue);color:#fff}
.pager span.dots{border:none;color:var(--muted)}

/* ---------- 🔐 فیلد سکرت — پنهان با دکمه‌ی نمایش ---------- */
.secret{position:relative}
.secret input{padding-left:42px}
.secret button{position:absolute;top:50%;left:6px;transform:translateY(-50%);background:transparent;border:0;
  color:var(--muted);cursor:pointer;font-size:15px;padding:6px;line-height:1}
.secret button:hover{color:var(--ink)}
.secret-box{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.secret-box code{direction:ltr;word-break:break-all;overflow-wrap:anywhere;max-width:100%}
.secret-box button{background:var(--panel-alt);border:1px solid var(--line);border-radius:7px;padding:4px 10px;
  font-size:11.5px;cursor:pointer;color:var(--ink-soft);font-family:inherit}

/* ---------- 🪟 مودالِ تاییدِ عملیات مهم ---------- */
.modal-backdrop{position:fixed;inset:0;background:rgba(4,4,8,.66);backdrop-filter:blur(4px);
  z-index:200;display:none;align-items:center;justify-content:center;padding:20px}
.modal-backdrop.on{display:flex}
.modal{background:#16161e;border:1px solid var(--line);border-radius:14px;max-width:400px;width:100%;
  padding:22px;box-shadow:0 30px 70px rgba(0,0,0,.6);animation:modalIn .2s cubic-bezier(.2,.9,.3,1)}
@keyframes modalIn{from{opacity:0;transform:scale(.95) translateY(6px)}to{opacity:1;transform:none}}
.modal h3{font-size:15.5px;margin-bottom:12px}
.modal .mrow{display:flex;justify-content:space-between;gap:10px;padding:9px 0;border-top:1px solid var(--line-soft);font-size:13px}
.modal .mrow:first-of-type{border-top:none}
.modal .mrow b{color:var(--ink)}
.modal .mwarn{margin-top:12px;font-size:12px;color:#f0cb75;background:var(--amber-dim);border-radius:8px;padding:9px 11px;line-height:1.8}
.modal .mact{display:flex;gap:10px;margin-top:18px}
.modal .mact button{flex:1}

/* ---------- 🍞 توست (روی flash موجود) ---------- */
.flash{position:relative;padding-left:36px}
.flash .fx{position:absolute;left:8px;top:50%;transform:translateY(-50%);background:transparent;border:0;
  color:inherit;opacity:.6;cursor:pointer;font-size:15px;padding:4px}
.flash .fx:hover{opacity:1}

/* ---------- 📱 جدول → کارت روی موبایل ---------- */
@media(max-width:640px){
  table.responsive thead{display:none}
  table.responsive,table.responsive tbody,table.responsive tr,table.responsive td{display:block;width:100%}
  table.responsive tr{border:1px solid var(--line);border-radius:10px;margin-bottom:10px;padding:6px 4px;background:var(--panel-alt)}
  table.responsive td{border-top:none;padding:7px 10px;display:flex;justify-content:space-between;gap:10px;text-align:left}
  table.responsive td:before{content:attr(data-label);font-weight:700;color:var(--muted);font-size:11.5px;text-align:right}
}
.sparkline{display:block;width:100%;height:56px}
code{background:var(--panel-alt);color:var(--ink-soft);padding:2px 6px;border-radius:5px;font-size:11.5px;direction:ltr;display:inline-block}
.muted{color:var(--muted);font-size:12px}
.inline{display:inline}
.brow8{grid-template-columns:44px 1fr 96px 52px 90px 52px 52px 40px!important;gap:7px!important}
.brow{display:grid;grid-template-columns:44px 1fr 90px 70px 60px 46px;gap:8px;align-items:center;
padding:10px;border:1px solid var(--line);border-radius:8px;margin-bottom:8px;background:var(--panel-alt)}
.brow input,.brow select{padding:8px;font-size:13px}
.prev{background:var(--panel-alt);border:1px solid var(--line);border-radius:10px;padding:14px;margin-top:12px}
.pbtn{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:10px;text-align:center;font-size:13.5px;margin:4px 0;color:var(--ink)}
.pgrid{display:flex;gap:6px}
.pgrid .pbtn{flex:1;margin:0}

/* ---------- 🧲 گریدِ چیدمانِ دستی — ۷ ردیف × ۳ خانه‌ی شیشه‌ای ---------- */
.layoutgrid{display:flex;flex-direction:column;gap:8px}
.layoutgrid .lrow{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.gridslot{position:relative}
.gridslot select{appearance:none;-webkit-appearance:none;width:100%;padding:14px 10px;border-radius:12px;
  border:1.5px dashed var(--line);background:var(--panel-alt);color:var(--muted);
  font-family:inherit;font-size:12.5px;font-weight:800;text-align:center;text-align-last:center;
  cursor:pointer;transition:.15s}
.gridslot select.filled{border-style:solid;border-color:var(--blue);background:linear-gradient(135deg,var(--blue),#4f9cf0);color:#fff}
.gridslot select:focus{outline:none;box-shadow:0 0 0 3px rgba(47,125,225,.25)}
@media(max-width:640px){.layoutgrid .lrow{gap:5px}.gridslot select{padding:11px 4px;font-size:11px}}
.srow{display:grid;grid-template-columns:40px 90px 100px 60px 1fr 1.4fr;gap:8px;align-items:center;
padding:9px;border:1px solid var(--line);border-radius:8px;margin-bottom:8px;background:var(--panel-alt)}
.srow input,.srow select{padding:8px;font-size:12.5px}
.tgrid{display:grid;gap:14px}
.bar{height:9px;background:var(--panel-alt);border-radius:20px;overflow:hidden;border:1px solid var(--line)}
.bar-in{height:100%;background:var(--blue);border-radius:20px;transition:width .3s}
pre.code{background:#000;color:#d8d8d8;padding:13px;border-radius:8px;font-size:11.5px;
line-height:1.75;overflow-x:auto;direction:ltr;text-align:left;white-space:pre;margin:0;border:1px solid var(--line)}
details summary::-webkit-details-marker{display:none}
.note{background:var(--panel-alt);border-right:4px solid var(--blue);border-radius:8px;padding:12px 14px;
margin-bottom:14px;font-size:12.5px;line-height:1.95;color:var(--ink-soft)}
.tbar{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:6px}
.tbar button{background:var(--panel-alt);border:1px solid var(--line);border-radius:7px;padding:5px 10px;font-size:11.5px;
cursor:pointer;font-family:inherit;color:var(--ink-soft)}
.tbar button:hover{background:#292929}
.pbtn.pb-b{background:var(--blue);color:#fff;border-color:var(--blue)}
.pbtn.pb-g{background:var(--green);color:#fff;border-color:var(--green)}
.pbtn.pb-r{background:var(--red);color:#fff;border-color:var(--red)}
@media(max-width:900px){.brow,.brow8,.srow{grid-template-columns:1fr 1fr!important;gap:6px!important}}
@media(max-width:640px){.card .body{padding:15px}header h1{font-size:16px}}
@media(prefers-reduced-motion:reduce){
  .card,.itemrow .ir-body,.sidebar a,.stat,.btn,.logout,.status-dot,.modal{animation:none!important;transition:none!important}
}
@supports not (backdrop-filter:blur(1px)){
  header,.sidebar,.card,.stat{background:#141420}
}
</style>
</head>
<body>

<header><div class="wrap row">
  <h1>👑 پنل مدیریت فروشگاه</h1>
  <form method="get" class="hdr-search" style="flex:1;min-width:160px;max-width:280px">
    <input type="hidden" name="tab" value="users">
    <input type="text" name="q" placeholder="🔎 جستجوی کاربر (آیدی/یوزرنیم)…">
  </form>
  <div class="hdr-meta">
    <span class="status-pill"><span class="status-dot"></span> سیستم فعال</span>
    <span class="status-pill">👤 مدیر</span>
    <a class="logout" href="?logout=1">🚪 خروج</a>
  </div>
</div></header>

<?php
// آیکن + برچسبِ متنیِ هر تب — بدون شمارنده؛ شمارنده‌ها جدا زیرِ همین آرایه محاسبه می‌شوند
$tabs = [
  'dashboard' => '📊 داشبورد',
  'orders'    => '🧾 سفارش‌ها',
  'products'  => '🛒 محصولات',
  'profit'    => '📈 سود',
  'support'   => '📞 پشتیبانی',
  'bots'      => '🤖 ربات‌های اپلودر',
  'channels'  => '📢 کانال‌ها',
  'campaigns' => '🎯 کمپین‌ها',
  'partners'  => '🤝 ربات‌های شریک',
  'referral'  => '👥 رفرال',
  'users'     => '👥 کاربران',
  'auto'      => '⚡ خودکارسازی',
  'settings'  => '⚙️ تنظیمات',
  'numbers'   => '☎️ شماره مجازی',
  'miniapps'  => '🚀 مینی‌اپ‌ها',
  'diamond'   => '💎 الماس',
  'games'     => '🎮 بازی‌ها',
  'bank'      => '🏦 بانک',
  'mine'      => '💣 مین‌یاب',
];
// 🔴 فقط جایی که واقعا «نیاز به اقدام» معنی دارد badge می‌گیرد — عددهای الکی شلوغی می‌سازند
$tabBadges = ['orders' => count($pending)];
// 📁 فولدربندیِ تب‌ها — هر دسته زیرِ عنوانِ خودش تو سایدبار
$tabFolders = [
  'نمای کلی'  => ['dashboard'],
  'فروش'      => ['orders', 'products', 'profit'],
  'کاربران'   => ['users', 'referral', 'support'],
  'زیرساخت'   => ['bots', 'channels', 'campaigns', 'partners'],
  'ویژگی‌ها'  => ['numbers', 'miniapps', 'diamond', 'games', 'bank', 'mine'],
  'سیستم'     => ['auto', 'settings'],
];
?>
<input type="checkbox" id="navToggle" class="nav-toggle-cb">
<label for="navToggle" class="nav-toggle-btn">☰ منو</label>
<label for="navToggle" class="nav-backdrop"></label>

<div class="shell">
  <aside class="sidebar"><div class="sidebar-inner">
    <?php foreach ($tabFolders as $folderTitle => $folderTabs): ?>
      <details class="nav-folder" open>
        <summary><span><?= h($folderTitle) ?></span><span class="fc">▸</span></summary>
        <div class="nav-folder-body">
        <?php foreach ($folderTabs as $k): $n = $tabBadges[$k] ?? 0; ?>
          <a href="?tab=<?= $k ?>" class="<?= $tab === $k ? 'on' : '' ?>">
            <span><?= $tabs[$k] ?></span>
            <?php if ($n > 0): ?><span class="nbadge"><?= $n ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </div></aside>

  <main class="content"><div class="wrap">
<?php if ($flash): ?>
  <div class="flash <?= h($flash['type']) ?>" id="flashToast" style="line-height:2">
    <button type="button" class="fx" aria-label="بستن" onclick="this.parentElement.style.display='none'">✕</button>
    <?= nl2br(flashSafeHtml($flash['msg'])) ?>
  </div>
<?php endif; ?>
<?php if (strlen(ADMIN_PASSWORD) < 12 || !preg_match('/[^A-Za-z0-9]/', ADMIN_PASSWORD)): ?>
  <div class="flash err">🟠 <b>رمز پنل ضعیف است.</b>
  از این پنل می‌شود به موجودی کاربران و ولت TON رسید. رمز را در
  <code>config.local.php</code> به دست‌کم ۱۲ کاراکتر با حرف و عدد و علامت تغییر دهید.</div>
<?php endif; ?>

<?php // ================= داشبورد ================= ?>
<?php if ($tab === 'dashboard'): ?>
  <h2 style="font-size:19px;margin-bottom:4px">خوش آمدید 👋</h2>
  <p class="muted" style="margin-bottom:18px">خلاصه‌ی وضعیتِ همین الان — برای جزئیات، به تبِ مربوطه بروید.</p>

  <?php if (count($pending) > 0): ?>
  <div class="callout">
    <div class="ic">⚠️</div>
    <div class="tx"><b><?= count($pending) ?> سفارش منتظر تایید</b>
      <span>این‌ها منتظرِ بررسیِ رسید و تاییدِ شما هستند.</span></div>
    <a class="btn b" href="?tab=orders">مشاهده سفارش‌ها →</a>
  </div>
  <?php else: ?>
  <div class="callout ok">
    <div class="ic">✓</div>
    <div class="tx"><b>سفارشی منتظر بررسی نیست</b><span>همه‌چیز مرتب است.</span></div>
  </div>
  <?php endif; ?>

  <div class="stats">
    <div class="stat"><div class="n"><?= count($users) ?></div><div class="l">👥 کاربران</div></div>
    <div class="stat"><div class="n"><?= count($orders) ?></div><div class="l">🛒 سفارش‌ها</div></div>
    <div class="stat"><div class="n amount"><?= fmtNum(array_sum($revenue)) ?></div><div class="l">💰 فروش تاییدشده</div></div>
    <div class="stat"><div class="n amount"><?= fmtNum($totalBalance) ?></div><div class="l">💳 موجودی کاربران</div></div>
    <div class="stat"><div class="n"><?= count(array_filter($bots, fn($b) => !empty($b['active']))) ?></div><div class="l">🤖 ربات‌های فعال</div></div>
  </div>

  <div class="card"><h2>⚡ عملیات سریع</h2><div class="body">
    <div class="qa-grid">
      <a class="qa" href="?tab=products"><span class="e">➕</span> افزودن محصول</a>
      <a class="qa" href="?tab=orders"><span class="e">🧾</span> سفارش‌های منتظر</a>
      <a class="qa" href="?tab=users"><span class="e">👤</span> جستجوی کاربر</a>
      <a class="qa" href="?tab=bots"><span class="e">🤖</span> مدیریت ربات</a>
      <a class="qa" href="?tab=channels"><span class="e">📢</span> مدیریت کانال</a>
      <a class="qa" href="?tab=settings"><span class="e">⚙️</span> تنظیمات</a>
    </div>
  </div></div>

  <div class="card"><h2>📈 سفارش‌های ۷ روز اخیر</h2><div class="body">
    <?php if (!$orders): ?><div class="empty"><div class="ic">📈</div>هنوز سفارشی ثبت نشده.</div>
    <?php else: [$spark, $sparkTotal] = dashSparkline($orders); ?>
      <?= $spark ?>
      <div class="muted" style="margin-top:8px">مجموع ۷ روز اخیر: <b class="amount"><?= $sparkTotal ?></b> سفارش</div>
    <?php endif; ?>
  </div></div>

  <div class="card"><h2>💰 فروش تایید شده — به تفکیک ارز</h2><div class="body">
    <?php if (!$revenue): ?><div class="empty"><div class="ic">💰</div>هنوز فروشی ثبت نشده.</div>
    <?php else: ?><div class="stats" style="margin:0">
      <?php foreach ($revenue as $cur => $amt): ?>
        <div class="stat"><div class="n amount"><?= h(fmtNum($amt)) ?></div><div class="l"><?= h($cur) ?></div></div>
      <?php endforeach; ?></div><?php endif; ?>
  </div></div>

  <div class="card"><h2>✏️ دکمه‌ها و متن‌ها</h2><div class="body">
    <div class="note" style="margin:0">
      ویرایش <b>دکمه‌ها</b>، <b>متن‌ها</b>، <b>رنگ‌ها</b> و <b>متن دکمه‌های ثابت</b>
      حالا داخل <b>خود ربات</b> است — چون آنجا می‌توانید ایموجی پریمیوم و نقل‌قول
      را مستقیم تایپ کنید.<br><br>
      در ربات <code>/panel</code> را بزنید → 🎨 دکمه‌ها · 📝 متن‌ها · 💠 رنگ دکمه‌های شیشه‌ای
    </div>
  </div></div>

  <div class="card"><h2>🔗 وبهوک و کران</h2><div class="body">
    <p class="muted" style="margin-bottom:10px">وبهوک مادر: <code><?= h(baseUrl()) ?>/bot_master_membership.php</code></p>
    <form method="post" class="inline">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
      <input type="hidden" name="tab" value="dashboard">
      <input type="hidden" name="action" value="master_webhook">
      <button class="btn b">تنظیم وبهوک ربات مادر</button>
    </form>
    <p class="muted" style="margin-top:14px;line-height:1.9">
      برای اینکه حذف خودکار فایل‌ها حتی بدون فعالیت ربات هم دقیق کار کند،
      این آدرس را هر دقیقه در کران هاست صدا بزنید:
    </p>
    <?php $cronUrl = baseUrl() . '/bot_master_membership.php?cron=' . CRON_KEY; ?>
    <div class="secret-box" style="margin-bottom:10px">
      <code class="ltr"><?= h($cronUrl) ?></code>
      <button type="button" onclick="copyText('<?= h(addslashes($cronUrl)) ?>',this)">📋 کپی</button>
    </div>
    <p class="muted">کلید کران در خط ۳۰ فایل ربات قابل تغییر است.</p>
  </div></div>

<?php // ================= سفارش‌ها ================= ?>
<?php elseif ($tab === 'orders'): ?>
  <div class="crumb">فروش <span>/</span> <b>سفارش‌ها</b></div>
  <div class="card"><h2>🧾 منتظر تایید (<?= count($pending) ?>)</h2><div class="body">
    <?php if (!$pending): ?><div class="empty"><div class="ic">✓</div>موردی در انتظار نیست.</div>
    <?php else: ?><div class="scroll"><table class="responsive">
      <tr><th>کاربر</th><th>نوع</th><th>مبلغ</th><th>رسید</th><th>تاریخ</th><th>اقدام</th></tr>
      <?php foreach ($pending as $o):
        $oUser = uLabel($users, $o['user_id']);
        $oWhat = $o['type'] === 'topup' ? '➕ شارژ کیف پول' : '🛒 ' . (Product::get($o['product_id'])['name'] ?? '—');
        $oAmt  = fmtNum($o['amount']) . ' ' . $o['currency'];
      ?>
      <tr>
        <td data-label="کاربر"><?= h($oUser) ?><br><span class="muted"><?= h($o['user_id']) ?></span></td>
        <td data-label="نوع"><?= h($oWhat) ?></td>
        <td data-label="مبلغ"><b class="amount"><?= h(fmtNum($o['amount'])) ?></b> <?= h($o['currency']) ?></td>
        <td data-label="رسید"><?= $o['receipt_type'] === 'text'
              ? '<code>' . h(mb_substr((string)$o['receipt'], 0, 30)) . '</code>'
              : '<span class="muted">🖼️ عکس (در تلگرام)</span>' ?></td>
        <td data-label="تاریخ" class="muted"><?= h($o['created_at']) ?></td>
        <td data-label="اقدام" style="white-space:nowrap">
          <form method="post" class="inline">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="orders">
            <input type="hidden" name="action" value="approve_order"><input type="hidden" name="id" value="<?= h($o['id']) ?>">
            <button type="button" class="btn g sm" onclick="openConfirm(this.form,'تایید سفارش',
              [['کاربر','<?= h(addslashes($oUser)) ?>'],['نوع','<?= h(addslashes($oWhat)) ?>'],['مبلغ','<?= h(addslashes($oAmt)) ?>']],
              'این عملیات موجودی کاربر را تغییر می‌دهد یا سفارش را نهایی می‌کند.','g')">✅ تایید</button>
          </form>
          <form method="post" class="inline">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="orders">
            <input type="hidden" name="action" value="reject_order"><input type="hidden" name="id" value="<?= h($o['id']) ?>">
            <button type="button" class="btn r sm" onclick="openConfirm(this.form,'رد سفارش',
              [['کاربر','<?= h(addslashes($oUser)) ?>'],['نوع','<?= h(addslashes($oWhat)) ?>'],['مبلغ','<?= h(addslashes($oAmt)) ?>']],
              'این سفارش رد می‌شود و دیگر قابل بازگشت نیست.','r')">❌ رد</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table></div><?php endif; ?>
  </div></div>

  <?php
    // 🔎 جستجو/فیلتر/مرتب‌سازی/صفحه‌بندی — همه روی همان $ordersی که بالا خوانده شده،
    // هیچ کوئریِ تازه‌ای نمی‌رود؛ فقط چیزی که رندر می‌شود کمتر می‌شود.
    $oQ      = trim((string)($_GET['q'] ?? ''));
    $oStatus = (string)($_GET['status'] ?? 'all');
    $oSort   = (string)($_GET['sort'] ?? 'new');
    $oPerPage = 25;

    $oList = $orders;
    if ($oQ !== '') {
        $needle = mb_strtolower($oQ);
        $oList = array_filter($oList, function ($o) use ($needle, $users) {
            $hay = mb_strtolower($o['id'] . ' ' . $o['user_id'] . ' ' . uLabel($users, $o['user_id']));
            return str_contains($hay, $needle);
        });
    }
    if ($oStatus !== 'all' && $oStatus !== '') {
        $oList = array_filter($oList, fn($o) => $o['status'] === $oStatus);
    }
    $oList = array_values($oList);
    if ($oSort === 'old')      usort($oList, fn($a, $b) => strcmp($a['created_at'] ?? '', $b['created_at'] ?? ''));
    elseif ($oSort === 'amt_desc') usort($oList, fn($a, $b) => ((float)($b['amount'] ?? 0)) <=> ((float)($a['amount'] ?? 0)));
    elseif ($oSort === 'amt_asc')  usort($oList, fn($a, $b) => ((float)($a['amount'] ?? 0)) <=> ((float)($b['amount'] ?? 0)));
    // پیش‌فرض (new): همان ترتیبِ نزولیِ زمانی که بالا برای کل $orders ساخته شده

    $oTotal = count($oList);
    $oPages = max(1, (int)ceil($oTotal / $oPerPage));
    $oPage  = max(1, min((int)($_GET['page'] ?? 1), $oPages));
    $oSlice = array_slice($oList, ($oPage - 1) * $oPerPage, $oPerPage);
    $oStatusChips = ['all' => 'همه', Order::REVIEW => 'بررسی', Order::APPROVED => 'تایید شده',
                     Order::REJECTED => 'رد شده', Order::PENDING => 'منتظر رسید'];
  ?>
  <div class="card"><h2>📜 تاریخچه (<?= $oTotal ?>)</h2><div class="body">
    <form method="get" class="toolbar">
      <input type="hidden" name="tab" value="orders">
      <div class="search"><input type="text" name="q" value="<?= h($oQ) ?>" placeholder="🔎 آیدیِ کاربر، یوزرنیم یا شناسه سفارش…"></div>
      <select name="sort" onchange="this.form.submit()">
        <option value="new" <?= $oSort === 'new' ? 'selected' : '' ?>>جدیدترین</option>
        <option value="old" <?= $oSort === 'old' ? 'selected' : '' ?>>قدیمی‌ترین</option>
        <option value="amt_desc" <?= $oSort === 'amt_desc' ? 'selected' : '' ?>>مبلغ بیشتر</option>
        <option value="amt_asc" <?= $oSort === 'amt_asc' ? 'selected' : '' ?>>مبلغ کمتر</option>
      </select>
      <input type="hidden" name="status" value="<?= h($oStatus) ?>">
      <button class="btn sm">اعمال</button>
    </form>
    <div class="chiprow" style="margin-bottom:14px">
      <?php foreach ($oStatusChips as $sv => $sl): ?>
        <a href="<?= h(qsWith(['status' => $sv, 'page' => 1])) ?>" class="<?= $oStatus === $sv ? 'on' : '' ?>"><?= h($sl) ?></a>
      <?php endforeach; ?>
    </div>
    <?php if (!$oSlice): ?>
      <div class="empty"><div class="ic">🧾</div>
        <?= $oQ !== '' || $oStatus !== 'all' ? 'با این جستجو/فیلتر سفارشی پیدا نشد.' : 'سفارشی ثبت نشده.' ?>
      </div>
    <?php else: ?><div class="scroll"><table class="responsive">
      <tr><th>شناسه</th><th>کاربر</th><th>نوع</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr>
      <?php foreach ($oSlice as $o): ?>
      <tr>
        <td data-label="شناسه"><code><?= h($o['id']) ?></code></td>
        <td data-label="کاربر"><?= h(uLabel($users, $o['user_id'])) ?></td>
        <td data-label="نوع"><?= $o['type'] === 'topup' ? 'شارژ' : h(Product::get($o['product_id'])['name'] ?? '—') ?></td>
        <td data-label="مبلغ"><span class="amount"><?= h(fmtNum($o['amount'])) ?></span> <?= h($o['currency']) ?></td>
        <td data-label="وضعیت"><?= oBadge($o['status']) ?></td>
        <td data-label="تاریخ" class="muted"><?= h($o['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <?php pager($oPage, $oPages); ?>
    <?php endif; ?>
  </div></div>

<?php // ================= محصولات ================= ?>
<?php elseif ($tab === 'products'): ?>
  <?php $SM = cfg()['smm'] ?? []; ?>
  <div class="card"><h2>🤖 اتصال به پنلِ ممبر (SMM) <?= !empty($SM['on']) ? '<span class="badge green">روشن</span>' : '<span class="badge">خاموش</span>' ?></h2><div class="body">
    <div class="note">
      پنلی که ازش ممبر/فالوور می‌خری معمولا یک API استاندارد دارد: آدرس + کلید،
      و <code>action=add</code> برای ثبت سفارش. اینجا یک‌بار وصلش کن، بعد پایین برای هر
      محصول (یا هر دکمه‌ی «ممبر») سرویسِ همان پنل را انتخاب کن — از آن به بعد،
      با تاییدِ هر سفارش خودش می‌رود آنجا ثبت می‌کند.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
      <input type="hidden" name="action" value="save_smm">
      <div class="grid2">
        <div><label>آدرس API پنل</label>
          <input name="smm_base" value="<?= h($SM['base'] ?? '') ?>" placeholder="https://panel.com/api/v2" style="direction:ltr"></div>
        <div><label>کلید API</label>
          <div class="secret"><input type="password" name="smm_key" autocomplete="off" value="<?= h($SM['key'] ?? '') ?>" placeholder="کلیدِ حساب شما در آن پنل" style="direction:ltr">
            <button type="button" onclick="toggleSecret(this)">👁</button></div></div>
        <div><label>تایم‌اوت (ثانیه)</label>
          <input name="smm_timeout" type="number" min="5" value="<?= (int)($SM['timeout'] ?? 15) ?>" style="direction:ltr"></div>
      </div>
      <div style="margin-top:12px">
        <label style="font-weight:500"><input type="checkbox" name="smm_on" style="width:auto"
          <?= !empty($SM['on']) ? 'checked' : '' ?>> اتصال روشن باشد</label>
      </div>
      <div style="margin-top:14px"><button class="btn g">ذخیره اتصال</button></div>
    </form>
    <?php if (!empty($SM['on']) && trim((string)($SM['base'] ?? '')) !== '' && trim((string)($SM['key'] ?? '')) !== ''): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
        <input type="hidden" name="action" value="smm_test">
        <button class="btn b sm">🔌 تست اتصال (فقط موجودی — پولی خرج نمی‌شود)</button>
      </form>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
        <input type="hidden" name="action" value="smm_refresh_services">
        <button class="btn sm">🔄 بروزرسانی لیست سرویس‌ها</button>
      </form>
    </div>
    <?php $svcList = smmServicesCached(); if ($svcList): ?>
      <div class="muted" style="margin-top:8px">📋 <?= count($svcList) ?> سرویس گرفته‌شده — پایین همین صفحه، توی هر محصول قابل انتخاب است.</div>
    <?php endif; ?>
    <?php endif; ?>
  </div></div>

  <div class="card"><h2>🚀 راه‌اندازیِ یک‌کلیکِ «بوست تلگرام»</h2><div class="body">
    <div class="note">
      یک دکمه‌ی موجود رو انتخاب کن — همه‌چیز (متن‌ها، ۴ پلنِ ۱/۷/۱۴/۳۰ روزه، چیدمانِ ۱و۲و۱، رنگ‌ها،
      خاموش‌بودنِ «ادمین‌شدنِ ربات») خودش نوشته می‌شه. فقط بعدش برو پایینِ همین صفحه، رو همون دکمه،
      و برای هر ۴ پلن، سرویسِ واقعیِ پنل رو از دراپ‌داون انتخاب کن (چون شماره‌ی اونا مالِ حسابِ خودته).
    </div>
    <?php $sBtns = saleButtons(); ?>
    <?php if (!$sBtns): ?>
      <div class="empty"><div class="ic">🚀</div>هنوز هیچ دکمه‌ی فروشی نساخته‌اید — اول یک دکمه بسازید، بعد اینجا انتخابش کنید.</div>
    <?php else: ?>
    <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
      <input type="hidden" name="action" value="boost_quickstart">
      <div style="flex:1;min-width:240px"><label>کدوم دکمه تبدیل به «بوست تلگرام» بشه؟</label>
        <select name="target">
          <?php foreach ($sBtns as $sp): [$tbid, $tsid] = $sp['btn']; ?>
            <option value="<?= h($tbid . '|' . $tsid) ?>">
              <?= h(trim(($sp['emoji'] ?? '') . ' ' . $sp['name'])) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn b" onclick="return confirm('متن‌ها و پلن‌های این دکمه با تنظیماتِ بوست جایگزین می‌شود. مطمئنی؟')">🚀 راه‌اندازی</button>
    </form>
    <?php endif; ?>
  </div></div>

  <?php $TF = cfg()['tariff'] ?? []; ?>
  <div class="card"><h2>📋 لیست تعرفه‌ها <?= !empty($TF['on']) ? '<span class="badge green">روشن</span>' : '<span class="badge">خاموش</span>' ?></h2><div class="body">
    <div class="note">
      یک دکمه شیشه‌ای زیر بخش «خرید محصول». جدول قیمت‌ها را خودکار می‌سازد و
      زیرش یک دکمه <b>برگشت</b> دارد که به بخش ثبت سفارش برمی‌گردد.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
      <input type="hidden" name="action" value="save_tariff">

      <div style="margin:10px 0">
        <label style="font-weight:500"><input type="checkbox" name="tf_on" style="width:auto"
          <?= !empty($TF['on']) ? 'checked' : '' ?>> دکمه «لیست تعرفه‌ها» نشان داده شود</label>
        <label style="font-weight:500"><input type="checkbox" name="tf_auto" style="width:auto"
          <?= !empty($TF['auto']) ? 'checked' : '' ?>> 📊 جدول خودکار قیمت‌ها اضافه شود</label>
      </div>

      <div class="note">
        📝 متنِ لیستِ تعرفه‌ها (و متغیرِ <code>{list}</code>) فقط داخل خودِ ربات ویرایش می‌شود:
        <code>/panel</code> ← 📋 لیست تعرفه‌ها ← ✏️ متن تعرفه.
      </div>

      <div class="grid2" style="margin-top:14px">
        <div><label>🔘 متن دکمه تعرفه</label>
          <input name="tf_btn_text" value="<?= h($TF['btn']['text'] ?? 'لیست تعرفه‌ها') ?>"></div>
        <div><label>😀 ایموجی دکمه</label>
          <input name="tf_btn_emoji" value="<?= h($TF['btn']['emoji'] ?? '') ?>" style="text-align:center"></div>
        <div><label>🎨 رنگ دکمه</label><select name="tf_btn_color">
          <?php foreach (styleMap() as $sk => $sl): ?>
            <option value="<?= h($sk) ?>" <?= ($TF['btn']['color'] ?? 'none') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
          <?php endforeach; ?></select></div>
        <div><label>◀️ متن دکمه برگشت</label>
          <input name="tf_back_text" value="<?= h($TF['back']['text'] ?? 'برگشت') ?>"></div>
        <div><label>😀 ایموجی برگشت</label>
          <input name="tf_back_emoji" value="<?= h($TF['back']['emoji'] ?? '') ?>" style="text-align:center"></div>
        <div><label>🎨 رنگ برگشت</label><select name="tf_back_color">
          <?php foreach (styleMap() as $sk => $sl): ?>
            <option value="<?= h($sk) ?>" <?= ($TF['back']['color'] ?? 'none') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
          <?php endforeach; ?></select></div>
      </div>

      <div style="margin-top:14px"><button class="btn g">ذخیره لیست تعرفه‌ها</button></div>
    </form>

    <?php $tbl = tariffTable(); if (trim($tbl) !== ''): ?>
      <div style="margin-top:16px"><label>👁 پیش‌نمایش جدول</label>
        <div class="prev" style="white-space:pre-wrap;line-height:2"><?= $tbl ?></div></div>
    <?php endif; ?>
  </div></div>

  <?php
    // 🧲 چیدمانِ دستیِ خودِ دکمه‌های واقعیِ «ثبت سفارش» — همان دکمه‌هایی
    // که در ربات زیرِ خرید می‌بینید (ممبر/بوست/فیک/پریمیوم + دکمه‌های
    // مینی‌اپِ ادغام‌شده). «لیست سفارشات» و «لیست تعرفه‌ها» همیشه اول و
    // آخرِ صفحه‌اند و جزوِ این گرید نیستند، پس این‌جا هم نمی‌آیند.
    // این بخش وابسته به saleButtons() نیست — همیشه نشان داده می‌شود.
    $buyGridItems = [];
    foreach ((cfg()['buttons']['buy']['subs'] ?? []) as $sub) {
        $sid2 = (string)($sub['id'] ?? ''); if ($sid2 === '') continue;
        $buyGridItems[] = [
            'id' => $sid2,
            'label' => trim(($sub['emoji'] ?? '') . ' ' . ($sub['text'] ?? '')) ?: $sid2,
            'on' => !empty($sub['on']),
            'row' => (int)($sub['row'] ?? 0), 'order' => (int)($sub['order'] ?? 0),
        ];
    }
    if (function_exists('maMergeOn') && maMergeOn() && function_exists('maSubItems')) {
        foreach (maSubItems() as $mi) {
            $buyGridItems[] = [
                'id' => $mi['id'],
                'label' => trim(($mi['emoji'] ?? '') . ' ' . ($mi['text'] ?? '')) ?: $mi['id'],
                'on' => true,
                'row' => (int)($mi['row'] ?? 0), 'order' => (int)($mi['order'] ?? 0),
            ];
        }
    }
    $buyGridOcc = [];
    foreach ($buyGridItems as $gi) {
        $gr = $gi['row']; $go = $gi['order'];
        if ($gr >= 1 && $gr <= 7 && $go >= $gr * 10 + 1 && $go <= $gr * 10 + 3) $buyGridOcc[$gr][$go - $gr * 10] = $gi['id'];
    }
  ?>
  <div class="card"><h2>🧲 چیدمانِ دستیِ دکمه‌های ثبت سفارش — گریدِ ۷×۳</h2><div class="body">
    <div class="note">
      دقیقا همان گریدِ محصولات، ولی این‌بار روی خودِ دکمه‌های ممبر/بوست/فیک/پریمیوم و
      دکمه‌های مینی‌اپ. روی «+» بزنید و دکمه موردنظر را انتخاب کنید — یک دکمه‌ی تنها در
      یک ردیف، وسطِ همان ردیف می‌نشیند؛ دو یا سه‌تا در همان ردیف، کنارِ هم.
      «لیست سفارشات» همیشه بالای همه و «لیست تعرفه‌ها» همیشه پایینِ همه می‌ماند — این دو
      جزوِ گرید نیستند.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
      <input type="hidden" name="action" value="save_button_grid">
      <div class="layoutgrid">
        <?php for ($gr = 1; $gr <= 7; $gr++): ?>
        <div class="lrow">
          <?php for ($gc = 1; $gc <= 3; $gc++): $cur = $buyGridOcc[$gr][$gc] ?? ''; ?>
          <div class="gridslot">
            <select name="grid[<?= $gr ?>][<?= $gc ?>]" onchange="gridSlotChanged(this)" class="<?= $cur !== '' ? 'filled' : '' ?>">
              <option value="">+</option>
              <?php foreach ($buyGridItems as $gi): ?>
                <option value="<?= h($gi['id']) ?>" <?= $cur === $gi['id'] ? 'selected' : '' ?>>
                  <?= h($gi['label'] . (!$gi['on'] ? ' (خاموش)' : '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endfor; ?>
        </div>
        <?php endfor; ?>
      </div>
      <div style="margin-top:14px"><button class="btn g">💾 ذخیره چیدمانِ دکمه‌ها</button></div>
    </form>
    <?php if (!$buyGridItems): ?><div class="empty"><div class="ic">🧲</div>هنوز دکمه‌ای زیرِ «ثبت سفارش» نساخته‌اید.</div><?php endif; ?>
  </div></div>

  <?php $saleBtns = saleButtons(); ?>
  <?php if ($saleBtns): ?>
  <div class="card"><h2>📢 گروه گزارش خرید</h2><div class="body">
    <div class="note">
      یک گروه با <b>تاپیک</b> بسازید — برای هر محصول یک تاپیک. ربات را در گروه <b>ادمین</b> کنید.
      بعد لینک هر تاپیک را روی محصول خودش بگذارید (پایین‌تر، داخل کارت هر محصول).<br>
      لینک تاپیک را اینطور می‌گیرید: روی یکی از پیام‌های آن تاپیک نگه دارید ← <b>Copy Link</b>.
    </div>
    <form method="post" style="margin-top:12px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
      <input type="hidden" name="action" value="report_group_all">
      <div style="flex:1;min-width:240px"><label>لینک یا آیدی گروه — روی همه محصولات می‌نشیند</label>
        <input name="glink" required placeholder="https://t.me/c/1234567890/1 یا -1001234567890" style="direction:ltr"></div>
      <button class="btn g">اعمال روی همه</button>
    </form>
  </div></div>

  <div class="card"><h2>💰 قیمت‌گذاری دکمه‌های فروش</h2><div class="body">
    <div class="note">
      این دکمه‌ها خودشان محصول‌اند — رکورد محصول جداگانه لازم ندارند.
      مشتری که رویشان بزند، مستقیم می‌رود سراغ
      <b>لینک کانال ← تعداد ← سرعت ← ادمین کردن ربات ← فاکتور</b>.<br>
      قیمت، تعداد و ضریب سرعت‌ها را همین‌جا تنظیم کنید.
      (ایموجی، رنگ و ایموجی پریمیوم داخل خود ربات: <code>/panel</code> ← 🔘 دکمه‌ها.)
    </div>
  </div></div>

  <?php foreach ($saleBtns as $sb):
    [$bid, $sid] = $sb['btn'];
    $f = $sb['flow']; ?>
  <div class="card">
    <h2>
      <?= h(trim(($sb['emoji'] ?? '') . ' ' . $sb['name'])) ?>
      <?= (float)$sb['price'] > 0
            ? '<span class="badge green">' . h(number_format((float)$sb['price']) . ' ' . $sb['currency']) . '</span>'
            : '<span class="badge">قیمت ندارد</span>' ?>
      <?= empty($sb['active']) ? '<span class="badge">خاموش</span>' : '' ?>
    </h2>
    <div class="body">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
        <input type="hidden" name="action" value="save_btn_price">
        <input type="hidden" name="bid" value="<?= h($bid) ?>"><input type="hidden" name="sid" value="<?= h($sid) ?>">

        <?php $perNow = max(1, (int)$f['per']); $perMember = (float)$sb['price'] / $perNow; ?>
        <div class="note" style="margin-bottom:12px">
          💠 <b>قیمت هر ۱ ممبر: <?= h(rtrim(rtrim(number_format($perMember, 2), '0'), '.') ?: '0') ?>
          <?= h($sb['currency']) ?></b>
          — یعنی <?= number_format((float)($sb['price_base'] ?? $sb['price'])) ?> برای هر <?= number_format($perNow) ?> نفر.<br>
          می‌خواهید ممبری قیمت بگذارید؟ «به ازای هر چند نفر» را <code>1</code> بگذارید و
          قیمت پایه را قیمت یک ممبر بنویسید.
        </div>
        <div class="grid2">
          <div><label>قیمت پایه</label>
            <?php $sbBase = (float)($sb['price_base'] ?? $sb['price']); ?>
            <input name="price" value="<?= h($sbBase > 0 ? (0 + $sbBase) : '') ?>"
                   placeholder="5000" style="direction:ltr" required>
            <?php if (abs($sbBase - (float)$sb['price']) > 0.01): ?>
              <small class="muted">با سود: <?= number_format((float)$sb['price']) ?></small>
            <?php endif; ?></div>
          <div><label>به ازای هر چند نفر؟ (۱ = قیمت هر ممبر)</label>
            <input name="per" type="number" min="1" value="<?= (int)$f['per'] ?>" style="direction:ltr"></div>
          <div><label>واحد پول</label><select name="currency">
            <?php foreach (['تومان', 'USDT', 'TRX'] as $cu): ?>
              <option <?= $sb['currency'] === $cu ? 'selected' : '' ?>><?= h($cu) ?></option>
            <?php endforeach; ?></select></div>
          <div><label>حداقل تعداد سفارش</label>
            <input name="min" type="number" min="1" value="<?= (int)$f['min'] ?>" style="direction:ltr"></div>
          <div><label>حداکثر تعداد سفارش</label>
            <input name="max" type="number" min="2" value="<?= (int)$f['max'] ?>" style="direction:ltr"></div>
          <div><label>توضیح کوتاه (اختیاری)</label>
            <input name="desc" value="<?= h($sb['desc'] ?? '') ?>" placeholder="تحویل تدریجی"></div>
          <div><label>📂 دسته‌بندیِ سود</label><?php saleCatField((string)($sb['sale_cat'] ?? '')); ?></div>
          <div><label>🤖 سرویسِ پنلِ SMM (خالی = دستی می‌ماند)</label>
            <?php smmServiceField((string)($sb['smm_service'] ?? ''), !empty($sb['smm_auto_price'])); ?></div>
          <div><label>📐 چیدمانِ دکمه‌های سرعت/پلن (مثلا <code>1,2,1</code> یعنی یکی بالا، دوتا وسط، یکی پایین)</label>
            <input name="speed_layout" value="<?= h($f['speed_layout'] ?? '1') ?>" placeholder="1" style="direction:ltr"></div>
          <div><label>🎠 نمایشِ سرعت/پلن‌ها</label>
            <select name="speed_mode">
              <option value="grid" <?= ($f['speed_mode'] ?? 'grid') === 'grid' ? 'selected' : '' ?>>🔲 دکمه‌های جدا (طبقِ چیدمانِ بالا)</option>
              <option value="carousel" <?= ($f['speed_mode'] ?? 'grid') === 'carousel' ? 'selected' : '' ?>>🎠 اسلایدر (۱ دکمه بالا + قبلی/بعدی)</option>
            </select>
            <small class="muted">حالتِ اسلایدر برای وقتی خوبه که چندتا پلنِ زیاد داری (مثلا ۴-۵ تا مدت) —
              فقط یکی بالا نشون داده می‌شه، با ◀️ قبلی / بعدی▶️ بینشون جابه‌جا می‌شی.</small></div>
          <div><label style="font-weight:500;margin-top:22px;display:block">
            <input type="checkbox" name="ask_admin" value="1" style="width:auto"
              <?= (!isset($f['ask_admin']) || !empty($f['ask_admin'])) ? 'checked' : '' ?>>
            بعد از سفارش، ربات باید ادمینِ کانالِ مشتری بشه (برای بوست/گیفت لازم نیست، خاموشش کن)</label></div>
        </div>

        <div class="note" style="margin-top:14px">
          📝 متن‌های مخصوصِ این محصول، و متنِ دکمه‌های «قبلی/بعدی»یِ حالتِ اسلایدر، فقط داخل خودِ ربات
          ویرایش می‌شوند (با ایموجی پریمیوم و نقل‌قول): <code>/panel</code> ← 🎨 دکمه‌ها ← این دکمه ←
          📝 متن‌های اختصاصی / ⚡️ سرعت‌ها ← 🎠 حالتِ نمایش.
        </div>

        <div style="margin-top:16px"><label>⚡️ سرعت‌ها / پلن‌ها</label>
          <table style="margin-top:6px">
            <tr><th>ایموجی</th><th>متن دکمه</th><th>ضریب</th><th>نفر در روز</th>
                <th>قیمت هر <?= number_format((int)$f['per']) ?></th><th>رنگ</th><th>روشن</th><th>حذف</th></tr>
            <?php foreach ($f['speeds'] as $sp): ?>
              <tr>
                <td><input name="spemoji[<?= h($sp['id']) ?>]" value="<?= h($sp['emoji'] ?? '') ?>"
                           style="text-align:center;max-width:70px"></td>
                <td><input name="sptext[<?= h($sp['id']) ?>]" value="<?= h($sp['text'] ?? '') ?>"
                           style="min-width:130px"></td>
                <td><input name="mult[<?= h($sp['id']) ?>]" value="<?= h((string)$sp['mult']) ?>"
                           style="direction:ltr;max-width:90px"></td>
                <td><input name="perday[<?= h($sp['id']) ?>]" type="number" min="0"
                           value="<?= (int)($sp['per_day'] ?? 0) ?>" style="direction:ltr;max-width:120px"></td>
                <td class="muted"><?= h(number_format(function_exists('speedRate') ? speedRate($sb, $sp) : ((float)$sb['price'] * (float)$sp['mult'])) . ' ' . $sb['currency']) ?></td>
                <td><select name="spcolor[<?= h($sp['id']) ?>]" style="max-width:120px">
                  <?php foreach (styleMap() as $sk => $sl): ?>
                    <option value="<?= h($sk) ?>" <?= ($sp['color'] ?? 'none') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
                  <?php endforeach; ?></select></td>
                <td><input type="checkbox" name="spon[<?= h($sp['id']) ?>]" value="1" style="width:auto"
                           <?= (!isset($sp['on']) || !empty($sp['on'])) ? 'checked' : '' ?>></td>
                <td>
                  <button type="submit" form="delSpeedForm_<?= h($bid . '_' . $sid) ?>"
                    onclick="document.getElementById('del_spid_<?= h($bid . '_' . $sid) ?>').value='<?= h($sp['id']) ?>';
                             return confirm('این پلن حذف شود؟');"
                    class="btn r sm">🗑</button>
                </td>
              </tr>
              <tr>
                <td class="muted">توضیح</td>
                <td colspan="7"><input name="spdesc[<?= h($sp['id']) ?>]" value="<?= h($sp['desc'] ?? '') ?>"
                       placeholder="یک خط توضیح — زیر متن انتخاب سرعت به مشتری نشان داده می‌شود"></td>
              </tr>
              <tr>
                <td class="muted">🤖 سرویسِ این گزینه</td>
                <td colspan="7">
                  <?php
                    $svcList = function_exists('smmServicesCached') ? smmServicesCached() : [];
                    $spCur = (string)($sp['smm_service'] ?? '');
                  ?>
                  <?php if ($svcList): ?>
                    <?php smmSelectHtml('spsmm[' . $sp['id'] . ']', smmOptionsList($svcList), $spCur,
                        '— مشترک با بقیه، ضریبِ بالا حساب می‌شود —'); ?>
                  <?php else: ?>
                    <input name="spsmm[<?= h($sp['id']) ?>]" value="<?= h($spCur) ?>" placeholder="خالی = مشترک"
                           style="direction:ltr">
                  <?php endif; ?>
                  <small class="muted">اگه اینجا یه سرویس انتخاب کنی و بالا «قیمتِ خودکار از پنل» روشن باشه،
                    قیمتِ همین گزینه مستقل از بقیه، از نرخِ واقعیِ همون سرویس حساب می‌شود — مثلا برای
                    «۳۰ روزه» و «۹۰ روزه» که هرکدام سرویسِ جداگانه‌ای رو پنل دارند.</small>
                </td>
              </tr>
            <?php endforeach; ?>
          </table>
          <div class="muted" style="margin-top:6px">
            متن دکمه = ایموجی + متن + «نفر در روز». مثلا <code>🏃 نیمه‌سریع — 3,500/روز</code><br>
            ✨ ایموجی پریمیوم فقط داخل ربات تنظیم می‌شود:
            <code>/panel</code> ← 🔘 دکمه‌ها ← این دکمه ← ⚡️ سرعت‌ها
          </div>
        </div>

        <?php
          $exQty = min((int)$f['max'], max((int)$f['min'], 5000));
          $fast  = null;
          foreach ($f['speeds'] as $sp) if (!isset($sp['on']) || !empty($sp['on'])) { $fast = $sp; break; }
        ?>
        <?php $fastRate = $fast ? speedRate($sb, $fast) : 0; ?>
        <?php if ($fast && $fastRate > 0): ?>
          <div class="note" style="margin-top:14px">
            🧾 نمونه فاکتور — <?= number_format($exQty) ?> نفر با
            «<?= h(trim(($fast['emoji'] ?? '') . ' ' . $fast['text'])) ?>»:
            <b><?= h(number_format(round($fastRate * ($exQty / max(1, (int)$f['per'])))) . ' ' . $sb['currency']) ?></b>
            <?php if ((int)($fast['per_day'] ?? 0) > 0): ?>
              · ⏳ <?= h(speedEta($fast, $exQty)) ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
          <button class="btn g">ذخیره قیمت‌گذاری</button>
        </div>
      </form>

      <!-- فرم حذفِ یک پلن — جدا از فرمِ اصلی چون تو در تو کردنِ form مجاز نیست؛
           دکمه‌های 🗑 بالا با ویژگیِ form="…" مستقیم به همین فرم وصل می‌شوند -->
      <form method="post" id="delSpeedForm_<?= h($bid . '_' . $sid) ?>" style="display:none">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="tab" value="products">
        <input type="hidden" name="action" value="del_speed">
        <input type="hidden" name="bid" value="<?= h($bid) ?>">
        <input type="hidden" name="sid" value="<?= h($sid) ?>">
        <input type="hidden" name="spid" id="del_spid_<?= h($bid . '_' . $sid) ?>" value="">
      </form>

      <form method="post" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="tab" value="products">
        <input type="hidden" name="action" value="add_speed">
        <input type="hidden" name="bid" value="<?= h($bid) ?>">
        <input type="hidden" name="sid" value="<?= h($sid) ?>">
        <input name="new_sp_emoji" placeholder="ایموجی" style="text-align:center;max-width:70px">
        <input name="new_sp_text" placeholder="متنِ زمانِ جدید — مثلا «۹۰ روزه»" style="min-width:180px;flex:1">
        <button class="btn b sm">➕ افزودنِ پلن/زمانِ جدید</button>
      </form>

      <?php $rp = reportOf($sb); ?>
      <details style="margin-top:16px"<?= !empty($rp['on']) ? ' open' : '' ?>>
        <summary style="cursor:pointer;font-weight:700;padding:8px 0">
          📢 گزارش خرید در گروه <?= !empty($rp['on']) ? '<span class="badge green">روشن</span>' : '<span class="badge">خاموش</span>' ?>
        </summary>
        <form method="post" style="margin-top:10px">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
          <input type="hidden" name="action" value="save_btn_report">
          <input type="hidden" name="bid" value="<?= h($bid) ?>"><input type="hidden" name="sid" value="<?= h($sid) ?>">

          <div class="note">
            یک گروه با چند تاپیک بسازید و برای هر محصول <b>شماره تاپیک خودش</b> را بگذارید —
            گزارش خرید هر محصول در بخش خودش می‌افتد.
            ربات باید در گروه <b>ادمین</b> باشد.
          </div>

          <div style="margin:10px 0">
            <label style="font-weight:500"><input type="checkbox" name="ron" value="1" style="width:auto"
              <?= !empty($rp['on']) ? 'checked' : '' ?>> گزارش این محصول روشن باشد</label>
          </div>

          <div style="margin-bottom:10px"><label>🔗 لینک تاپیک (ساده‌ترین راه)</label>
            <input name="rlink" placeholder="https://t.me/c/1234567890/11" style="direction:ltr">
            <div class="muted" style="margin-top:6px">
              روی یکی از پیام‌های همان تاپیک نگه دارید ← <b>Copy Link</b> و اینجا بچسبانید.
              گروه و شماره تاپیک با هم پر می‌شوند و دو فیلد پایین را نادیده می‌گیرد.
            </div>
          </div>
          <div class="grid2">
            <div><label>آیدی گروه</label>
              <input name="rchat" value="<?= h($rp['chat_id']) ?>" placeholder="-1001234567890" style="direction:ltr"></div>
            <div><label>شماره تاپیک (۰ = بدون تاپیک)</label>
              <input name="rthread" type="number" min="0" value="<?= (int)$rp['thread_id'] ?>" style="direction:ltr"></div>
          </div>

          <div style="margin-top:12px"><label>متن گزارش</label>
            <textarea name="rtext" rows="7" style="direction:rtl"><?= h($rp['text']) ?></textarea>
            <div class="muted" style="margin-top:6px">
              متغیرها: <code>{product} {emoji} {qty} {speed} {per_day} {eta} {amount} {currency}
              {code} {link} {channel} {user} {user_id} {user_id_masked} {date} {delivered}</code><br>
              💡 <code>{user_id_masked}</code> آیدیِ عددیِ خریدار را با چند رقمِ وسط مخفی نشان می‌دهد
              (مثلا <code>821••••584</code>) — به‌جای یوزرنیم، برای گزارش‌های داخلی مناسب‌تر است.<br>
              HTML مجاز است: <code>&lt;b&gt;</code> <code>&lt;i&gt;</code> <code>&lt;code&gt;</code>
              <code>&lt;blockquote&gt;</code> <code>&lt;blockquote expandable&gt;</code><br>
              ✨ برای <b>ایموجی پریمیوم</b> و نقل‌قول آماده، متن را داخل ربات بنویسید:
              <code>/panel</code> ← 🔘 دکمه‌ها ← این دکمه ← 📢 گزارش خرید ← ✏️ متن گزارش
            </div>
          </div>

          <div style="margin-top:14px">
            <label style="font-weight:500"><input type="checkbox" name="brow" value="1" style="width:auto"
              <?= (!isset($rp['btn_row']) || !empty($rp['btn_row'])) ? 'checked' : '' ?>>
              دو دکمه <b>کنار هم</b> باشند (تیک بردارید = زیر هم)</label>
          </div>
          <div style="margin-top:14px"><label>🔘 دو دکمه زیر گزارش</label>
            <table style="margin-top:6px">
              <tr><th>#</th><th>متن دکمه</th><th>لینک</th><th>رنگ</th><th>روشن</th></tr>
              <?php foreach ([0, 1] as $i): $b = $rp['buttons'][$i] ?? ['text'=>'','url'=>'','color'=>'none','on'=>true]; ?>
                <tr>
                  <td><?= $i + 1 ?></td>
                  <td><input name="btext[<?= $i ?>]" value="<?= h($b['text'] ?? '') ?>" placeholder="ثبت سفارش"></td>
                  <td><input name="burl[<?= $i ?>]" value="<?= h($b['url'] ?? '') ?>"
                             placeholder="https://t.me/YourBot" style="direction:ltr"></td>
                  <td><select name="bcolor[<?= $i ?>]" style="max-width:120px">
                    <?php foreach (styleMap() as $sk => $sl): ?>
                      <option value="<?= h($sk) ?>" <?= ($b['color'] ?? 'none') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
                    <?php endforeach; ?></select></td>
                  <td><input type="checkbox" name="bon[<?= $i ?>]" value="1" style="width:auto"
                             <?= !empty($b['on']) ? 'checked' : '' ?>></td>
                </tr>
              <?php endforeach; ?>
            </table>
            <div class="muted" style="margin-top:6px">
              دکمه بدون لینک نشان داده نمی‌شود. متن‌ها عمداً بدون ایموجی‌اند —
              ✨ ایموجی پریمیوم را داخل ربات بگذارید:
              <code>/panel</code> ← 📢 گزارش خرید ← محصول ← دکمه اول/دوم ← ✨ پریمیوم
            </div>
          </div>

          <div style="margin-top:14px"><button class="btn g">ذخیره گزارش</button></div>
        </form>

        <form method="post" style="margin-top:10px">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
          <input type="hidden" name="action" value="test_btn_report">
          <input type="hidden" name="bid" value="<?= h($bid) ?>"><input type="hidden" name="sid" value="<?= h($sid) ?>">
          <button class="btn b">🧪 ارسال گزارش آزمایشی</button>
        </form>
      </details>

      <form method="post" style="margin-top:14px">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
        <input type="hidden" name="action" value="toggle_btn_product">
        <input type="hidden" name="bid" value="<?= h($bid) ?>"><input type="hidden" name="sid" value="<?= h($sid) ?>">
        <button class="btn"><?= !empty($sb['active']) ? 'خاموش کردن دکمه' : 'روشن کردن دکمه' ?></button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <div class="card"><h2>➕ ساخت محصول جداگانه (اختیاری)</h2><div class="body">
    <div class="note">
      هر محصول یک <b>دکمه</b> در بخش «خرید محصول» می‌سازد — مثلا «ممبر اخلاقی»، «ممبر فیک».
      برای هرکدام ایموجی، رنگ واقعی و ایموجی پریمیوم جدا تنظیم می‌شود.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
      <input type="hidden" name="action" value="add_product">
      <div class="grid2">
        <div><label>نام محصول</label><input name="name" required placeholder="ممبر اخلاقی"></div>
        <div><label>قیمت</label><input name="price" required placeholder="50000"></div>
        <div><label>واحد پول</label><select name="currency">
          <option>تومان</option><option>USDT</option><option>TRX</option></select></div>
        <div><label>محدودیت خرید (۰ = نامحدود)</label><input name="limit" type="number" min="0" value="0"></div>
        <div><label>ایموجی دکمه</label><input name="emoji" value="💠" style="text-align:center"></div>
        <div><label>رنگ دکمه</label><select name="color">
          <?php foreach (styleMap() as $sk => $sl): ?>
            <option value="<?= h($sk) ?>" <?= $sk === 'success' ? 'selected' : '' ?>><?= h($sl) ?></option>
          <?php endforeach; ?></select></div>
        <div><label>✨ ایموجی پریمیوم (کد)</label><input name="icon" placeholder="از /emoji در ربات" style="direction:ltr"></div>
        <div><label>ردیف (۰ = خودکار)</label><input name="row" type="number" min="0" value="0"></div>
        <div><label>ترتیب</label><input name="order" type="number" min="1" value="99"></div>
        <div><label>ربات اپلودر تحویل</label><select name="bot_id">
          <option value="">— بدون محتوا —</option>
          <?php foreach ($bots as $bb): ?><option value="<?= h($bb['id']) ?>">@<?= h($bb['username']) ?></option><?php endforeach; ?>
        </select></div>
        <div><label>کد لینک محتوا</label><input name="link_code" placeholder="از تب ربات‌ها"></div>
      </div>
      <div style="margin-top:12px"><label>توضیح</label><input name="desc" placeholder="تحویل آنی"></div>
      <div style="margin-top:14px"><button class="btn g">ساخت محصول</button></div>
    </form>
  </div></div>

  <?php
    // 🧲 خانه‌ی اشغال‌شده‌ی هر محصول را از (row, order) خودش پیدا می‌کنیم —
    // همان دو فیلدی که هر محصول از قبل دارد، فقط این‌جا به‌جای عدد تایپ‌کردن
    // با کلیک روی خانه‌ی گرید تنظیم می‌شوند.
    $gridOcc = [];
    foreach ($products as $pp) {
        $gr = (int)($pp['row'] ?? 0); $go = (int)($pp['order'] ?? 0);
        if ($gr >= 1 && $gr <= 7 && $go >= $gr * 10 + 1 && $go <= $gr * 10 + 3) $gridOcc[$gr][$go - $gr * 10] = $pp['id'];
    }
  ?>
  <div class="card"><h2>🧲 چیدمانِ دستی — گریدِ ۷×۳</h2><div class="body">
    <div class="note">
      هر خانه یک جای دکمه است. روی «+» بزنید و محصول موردنظر را انتخاب کنید —
      اگر یک محصول تنها عضوِ یک ردیف باشد، همان‌جا (وسطِ آن ردیف) تنها می‌نشیند؛
      اگر دو یا سه محصول را در خانه‌های همان ردیف بگذارید، درست کنارِ هم می‌آیند.
      برای خالی‌کردن یک خانه، دوباره روی آن بزنید و «+» را انتخاب کنید.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
      <input type="hidden" name="action" value="save_product_grid">
      <div class="layoutgrid">
        <?php for ($gr = 1; $gr <= 7; $gr++): ?>
        <div class="lrow">
          <?php for ($gc = 1; $gc <= 3; $gc++): $cur = $gridOcc[$gr][$gc] ?? ''; ?>
          <div class="gridslot">
            <select name="grid[<?= $gr ?>][<?= $gc ?>]" onchange="gridSlotChanged(this)" class="<?= $cur !== '' ? 'filled' : '' ?>">
              <option value="">+</option>
              <?php foreach ($products as $pp): ?>
                <option value="<?= h($pp['id']) ?>" <?= $cur === $pp['id'] ? 'selected' : '' ?>>
                  <?= h(trim(($pp['emoji'] ?? '') . ' ' . $pp['name'] . ' — ' . fmtNum($pp['price']))) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endfor; ?>
        </div>
        <?php endfor; ?>
      </div>
      <div style="margin-top:14px"><button class="btn g">💾 ذخیره چیدمانِ دستی</button></div>
    </form>
    <?php if (!$products): ?><div class="empty"><div class="ic">🧲</div>اول یک محصول بسازید، بعد اینجا بچینیدش.</div><?php endif; ?>
  </div></div>

  <div class="card"><h2>📐 چیدمانِ خودکار (برای محصول‌هایی که دستی جایشان را نگذاشته‌اید)</h2><div class="body">
    <div class="note" style="margin:0 0 12px">
      این الگو فقط برای محصولی اجرا می‌شود که در گریدِ بالا جایش را نگذاشته‌اید (یعنی «ردیف»‌اش هنوز صفر است).
    </div>
    <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
      <input type="hidden" name="action" value="save_product_layout">
      <div style="flex:1;min-width:200px"><label>الگو (وقتی ردیف محصول ۰ باشد)</label>
        <input name="product_layout" value="<?= h($C['ui']['product_layout'] ?? '1') ?>" placeholder="2,1,1" style="direction:ltr"></div>
      <button class="btn b">ذخیره</button>
    </form>
    <div class="prev" style="margin-top:12px">
      <?php
      $prods = activeProducts("buy");
      $hasRows = false; foreach ($prods as $pp) if (!empty($pp['row'])) { $hasRows = true; break; }
      if ($hasRows) { $g = []; foreach ($prods as $pp) $g[(int)($pp['row'] ?: 99)][] = $pp; ksort($g); $g = array_values($g); }
      else $g = layoutRows($prods, $C['ui']['product_layout'] ?? '1');
      foreach ($g as $line): ?>
        <div class="pgrid"><?php foreach ($line as $pp):
          $cls = ['primary'=>'pb-b','success'=>'pb-g','danger'=>'pb-r'][$pp['color'] ?? ''] ?? ''; ?>
          <div class="pbtn <?= $cls ?>"><?= h(trim(($pp['emoji'] ?? '') . ' ' . $pp['name'] . ' — ' . fmtNum($pp['price']) . ' ' . $pp['currency'])) ?></div>
        <?php endforeach; ?></div>
      <?php endforeach; ?>
      <?php if (!$prods): ?><div class="muted">محصولی نساخته‌اید.</div><?php endif; ?>
    </div>
  </div></div>

  <?php if ($products): ?>
  <div class="toolbar" style="margin-top:18px">
    <div class="search"><input type="text" id="prodFilter" oninput="filterProducts(this.value)" placeholder="🔎 جستجو در محصولات…"></div>
  </div>
  <?php endif; ?>
  <?php foreach ($products as $p): ?>
  <details class="card prodcard" data-pname="<?= h(mb_strtolower(($p['emoji'] ?? '') . ' ' . $p['name'])) ?>">
    <summary>
      <span><?= h(($p['emoji'] ?? '') . ' ' . $p['name']) ?></span>
      <span class="muted" style="font-weight:600;font-size:12px">
        <?= count($p['buyers']) ?> خریدار ·
        <?= (float)$p['price'] > 0 ? h(fmtNum($p['price']) . ' ' . $p['currency']) : 'بدون قیمت' ?>
        <?= !empty($p['active']) ? '<span class="badge green">فعال</span>' : '<span class="badge gray">غیرفعال</span>' ?>
      </span>
    </summary>
    <div class="body">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
      <input type="hidden" name="action" value="link_product"><input type="hidden" name="id" value="<?= h($p['id']) ?>">
      <div class="grid2">
        <div><label>نام</label><input name="name" value="<?= h($p['name']) ?>"></div>
        <div><label>قیمت (<?= h($p['currency']) ?>)</label><input name="price" value="<?= h(fmtNum($p['price_base'] ?? $p['price'])) ?>">
          <?php if (isset($p['price_base']) && abs((float)$p['price_base'] - (float)$p['price']) > 0.01): ?>
            <small class="muted">با سود: <?= h(fmtNum($p['price'])) ?></small>
          <?php endif; ?></div>
        <div><label>ایموجی</label><input name="emoji" value="<?= h($p['emoji'] ?? '') ?>" style="text-align:center"></div>
        <div><label>رنگ دکمه</label><select name="color">
          <?php foreach (styleMap() as $sk => $sl): ?>
            <option value="<?= h($sk) ?>" <?= ($p['color'] ?? '') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
          <?php endforeach; ?></select></div>
        <div><label>✨ ایموجی پریمیوم</label><input name="icon" value="<?= h($p['icon'] ?? '') ?>" style="direction:ltr"></div>
        <div><label>ردیف / ترتیب</label>
          <div style="display:flex;gap:6px">
            <input name="row" type="number" min="0" value="<?= (int)($p['row'] ?? 0) ?>">
            <input name="order" type="number" min="1" value="<?= (int)($p['order'] ?? 99) ?>">
          </div></div>
        <div><label>ربات اپلودر</label><select name="bot_id">
          <option value="">— بدون —</option>
          <?php foreach ($bots as $bb): ?>
            <option value="<?= h($bb['id']) ?>" <?= ($p['bot_id'] ?? '') === $bb['id'] ? 'selected' : '' ?>>@<?= h($bb['username']) ?></option>
          <?php endforeach; ?></select></div>
        <div><label>کد لینک محتوا</label><input name="link_code" value="<?= h($p['link_code'] ?? '') ?>"></div>
        <div><label>📂 دسته‌بندیِ سود</label><?php saleCatField((string)($p['sale_cat'] ?? '')); ?></div>
        <div><label>🤖 سرویسِ پنلِ SMM (خالی = دستی می‌ماند)</label>
          <?php smmServiceField((string)($p['smm_service'] ?? ''), !empty($p['smm_auto_price'])); ?></div>
      </div>
      <div style="margin-top:14px"><button class="btn g">ذخیره</button></div>
    </form>
    <div style="margin-top:12px;padding-top:12px;border-top:1px solid #2b2b2b">
      <form method="post" class="inline">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
        <input type="hidden" name="action" value="toggle_product"><input type="hidden" name="id" value="<?= h($p['id']) ?>">
        <button class="btn ghost sm"><?= !empty($p['active']) ? 'غیرفعال کن' : 'فعال کن' ?></button>
      </form>
      <form method="post" class="inline" onsubmit="return confirm('حذف محصول؟')">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
        <input type="hidden" name="action" value="del_product"><input type="hidden" name="id" value="<?= h($p['id']) ?>">
        <button class="btn r sm">حذف</button>
      </form>
      <span class="muted" style="margin-right:8px">
        محدودیت: <?= ((int)$p['limit']) > 0 ? (int)$p['limit'] : '∞' ?>
      </span>
    </div>
    </div>
  </details>
  <?php endforeach; ?>
  <?php if (!$products): ?><div class="card"><div class="body"><div class="empty"><div class="ic">🛒</div>محصولی نساخته‌اید.</div></div></div><?php endif; ?>

<?php // ================= سود ================= ?>
<?php elseif ($tab === 'profit'): ?>
  <?php
    $PF = function_exists('pfCfg') ? pfCfg() : ['on' => false, 'all' => ['mode' => 'pct', 'v' => 0]];
    $AXM = function_exists('axCfg') ? (axCfg()['pricing']['margin'] ?? []) : [];
    $modeSel = function ($cur, $followLbl) { ?>
      <option value="off" <?= empty($cur) ? 'selected' : '' ?>>⚪️ <?= h($followLbl) ?></option>
      <option value="pct" <?= ($cur ?? '') === 'pct' ? 'selected' : '' ?>>📊 درصدِ جدا</option>
      <option value="fixed" <?= ($cur ?? '') === 'fixed' ? 'selected' : '' ?>>💰 تومانِ ثابتِ جدا</option>
    <?php };
  ?>
  <div class="card"><h2>📈 سود — وضعیتِ کلی <?= !empty($PF['on']) ? '<span class="badge green">روشن</span>' : '<span class="badge">خاموش</span>' ?></h2><div class="body">
    <div class="note">
      هر بخش جدا از بقیه تنظیم می‌شه: ممبر فیک، بوست، شماره مجازی، ترون‌وتون، پریمیوم، استارز، گیفت —
      هرکدوم اگه عددِ خودشو نداشته باشه، از دسته‌ی مادرش (ممبر/مینی‌اپ‌ها) پیروی می‌کنه،
      و اگه اونم نداشته باشه، از سودِ عمومیِ پایین.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="profit">
      <input type="hidden" name="action" value="save_profit">
      <div style="margin:10px 0">
        <label style="font-weight:500"><input type="checkbox" name="pf_on" style="width:auto"
          <?= !empty($PF['on']) ? 'checked' : '' ?>> سود روشن باشد
          (تا روشن نشه، هیچ‌کدوم از بخش‌های زیر روی قیمت اثر نمی‌ذارن)</label>
      </div>
      <h3 style="font-size:13.5px;margin:14px 0 9px">📊 سودِ عمومی — آخرین ته‌چاه، هرجا هیچ عددِ جدا نبود همین می‌شینه</h3>
      <div class="grid2">
        <div><label>نوع</label><select name="pf_all_mode">
          <option value="pct" <?= ($PF['all']['mode'] ?? 'pct') === 'pct' ? 'selected' : '' ?>>📊 درصد</option>
          <option value="fixed" <?= ($PF['all']['mode'] ?? '') === 'fixed' ? 'selected' : '' ?>>💰 تومانِ ثابت</option>
        </select></div>
        <div><label>مقدار</label>
          <input name="pf_all_v" value="<?= h(fmtNum((float)($PF['all']['v'] ?? 0))) ?>" style="direction:ltr"></div>
      </div>

      <h3 style="font-size:13.5px;margin:18px 0 9px;color:#8a8a8a">— زیرِ اینا هرکدوم بخشِ جداگانه دارن، پایین‌تر —</h3>
      <div class="grid2">
        <div><label>🎯 پیش‌فرضِ خانواده‌ی «ممبر» (وقتی فیک/بوست خودشون عدد ندارن)</label>
          <select name="pf_member_mode"><?php $modeSel($PF['member']['mode'] ?? null, 'از سودِ عمومی پیروی کند'); ?></select></div>
        <div><label>مقدار</label>
          <input name="pf_member_v" value="<?= h(fmtNum((float)($PF['member']['v'] ?? 0))) ?>" style="direction:ltr"></div>
        <div><label>🚀 پیش‌فرضِ خانواده‌ی «مینی‌اپ‌ها» (وقتی گیفت/استارز/پریمیوم/ترون‌وتون خودشون عدد ندارن)</label>
          <select name="pf_ma_mode"><?php $modeSel($PF['ma']['mode'] ?? null, 'از سودِ عمومی پیروی کند'); ?></select></div>
        <div><label>مقدار</label>
          <input name="pf_ma_v" value="<?= h(fmtNum((float)($PF['ma']['v'] ?? 0))) ?>" style="direction:ltr"></div>
      </div>

      <?php
        $memberCats = ['fake_member' => '👤 ممبر فیک', 'boost' => '🚀 بوست'];
        foreach ($memberCats as $sec => $lbl): $s = $PF[$sec] ?? ['mode' => null, 'v' => null]; ?>
      <h3 style="font-size:13.5px;margin:18px 0 9px"><?= $lbl ?></h3>
      <div class="grid2">
        <div><label>نوع</label><select name="pf_<?= $sec ?>_mode">
          <?php $modeSel($s['mode'] ?? null, 'از «خرید ممبر» پیروی کند'); ?>
        </select></div>
        <div><label>مقدار (اگه «جدا» انتخاب شد)</label>
          <input name="pf_<?= $sec ?>_v" value="<?= h(fmtNum((float)($s['v'] ?? 0))) ?>" style="direction:ltr"></div>
      </div>
      <?php endforeach; ?>

      <h3 style="font-size:13.5px;margin:18px 0 9px">☎️ شماره مجازی — فقط درصد</h3>
      <div class="grid2">
        <div><label>درصدِ سود</label>
          <input name="num_markup" value="<?= h(fmtNum((float)(function_exists('numVal') ? numVal('markup', 0) : 0))) ?>" style="direction:ltr"></div>
      </div>

      <?php
        $maCats = ['c_coin' => '🪙 خرید ترون و تون', 'c_prem' => '💎 خرید پریمیوم',
                   'c_star' => '⭐️ خرید استارز', 'c_gift' => '🎁 گیفت'];
        foreach ($maCats as $cat => $lbl): $m = $AXM[$cat] ?? null; ?>
      <h3 style="font-size:13.5px;margin:18px 0 9px"><?= $lbl ?></h3>
      <div class="grid2">
        <div><label>نوع</label><select name="pf_<?= $cat ?>_mode">
          <?php $modeSel($m['mode'] ?? null, 'از «مینی‌اپ‌ها» پیروی کند'); ?>
        </select></div>
        <div><label>مقدار (اگه «جدا» انتخاب شد)</label>
          <input name="pf_<?= $cat ?>_v" value="<?= h(fmtNum((float)($m['v'] ?? 0))) ?>" style="direction:ltr"></div>
      </div>
      <?php endforeach; ?>

      <h3 style="font-size:13.5px;margin:18px 0 9px">💹 قیمت‌گیریِ ارز — فقط درصد</h3>
      <div class="grid2">
        <div><label>درصدِ سود</label>
          <input name="px_margin" value="<?= h(fmtNum((float)(function_exists('pxVal') ? pxVal('margin', 0) : 0))) ?>" style="direction:ltr"></div>
      </div>

      <div style="margin-top:14px"><button class="btn g">ذخیره‌ی همه</button></div>
    </form>

    <form method="post" style="margin-top:14px;padding-top:14px;border-top:1px solid #2b2b2b;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="profit">
      <input type="hidden" name="action" value="profit_every">
      <div style="flex:1;min-width:200px"><label>🎯 روی همه بنشان — یک درصد، هر ۴ بخش (شامل شماره مجازی و قیمت‌گیری)</label>
        <input name="every_pct" placeholder="مثلا 25" style="direction:ltr"></div>
      <button class="btn b">اعمال روی همه</button>
    </form>
  </div></div>

  <div class="card"><h2>👀 نمونه‌ی قیمت‌ها — قبل و بعدِ سود</h2><div class="body">
    <?php
      $peekRows = [];
      if (class_exists('Product')) {
        $pn = 0;
        foreach (Product::all() as $pp) {
          if (++$pn > 8) break;
          $base = (float)($pp['price_base'] ?? $pp['price']);
          $peekRows[] = ['🎯', mb_substr((string)$pp['name'], 0, 30), $base, (float)$pp['price'], (string)($pp['currency'] ?? '')];
        }
      }
    ?>
    <?php if ($peekRows): ?>
      <table>
        <tr><th></th><th>محصول</th><th>قیمتِ پایه</th><th>با سود</th></tr>
        <?php foreach ($peekRows as [$em, $name, $base, $withProfit, $cur]): ?>
          <tr>
            <td><?= $em ?></td><td><?= h($name) ?></td>
            <td class="muted"><?= h(fmtNum($base)) ?> <?= h($cur) ?></td>
            <td><b><?= h(fmtNum($withProfit)) ?> <?= h($cur) ?></b></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <div class="empty"><div class="ic">🛒</div>هنوز محصولِ ممبری نساخته‌اید.</div>
    <?php endif; ?>
  </div></div>

<?php // ================= پشتیبانی ================= ?>
<?php elseif ($tab === 'support'): ?>
  <div class="card"><h2>📞 دو دکمه اصلی پشتیبانی</h2><div class="body">
    <div class="note">
      کاربر فقط <b>دو دکمه</b> می‌بیند: ارتباط مستقیم و ارتباط غیر مستقیم.<br>
      <b>مستقیم</b> یک لینک است و کاربر را یک‌راست می‌برد.
      <b>غیر مستقیم</b> فهرست پایین را باز می‌کند.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="support">
      <input type="hidden" name="action" value="save_support">

      <?php foreach (['direct' => '💬 ارتباط مستقیم', 'indirect' => '📨 ارتباط غیر مستقیم'] as $mk => $mlbl):
        $m = $C['support_main'][$mk]; ?>
        <h3 style="font-size:13.5px;margin:<?= $mk === 'direct' ? '0' : '18px' ?> 0 9px"><?= $mlbl ?></h3>
        <div class="grid2">
          <div><label>ایموجی</label><input name="sm_emoji_<?= $mk ?>" value="<?= h($m['emoji']) ?>" style="text-align:center"></div>
          <div><label>متن دکمه</label><input name="sm_text_<?= $mk ?>" value="<?= h($m['text']) ?>"></div>
          <div><label>رنگ</label><select name="sm_color_<?= $mk ?>">
            <?php foreach (styleMap() as $sk => $sl): ?>
              <option value="<?= h($sk) ?>" <?= ($m['color'] ?? '') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
            <?php endforeach; ?></select></div>
          <div><label>✨ ایموجی پریمیوم</label><input name="sm_icon_<?= $mk ?>" value="<?= h($m['icon'] ?? '') ?>" style="direction:ltr"></div>
          <?php if ($mk === 'direct'): ?>
          <div style="grid-column:1/-1"><label>لینک مقصد</label>
            <input name="sm_value_direct" value="<?= h($m['value']) ?>" placeholder="https://t.me/malakeBTC" style="direction:ltr"></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <h3 style="font-size:13.5px;margin:20px 0 9px">📨 گزینه‌های زیر «ارتباط غیر مستقیم»</h3>
      <div style="display:grid;grid-template-columns:40px 100px 60px 1fr 1.4fr;gap:8px;
                  font-size:11.5px;color:#8a8a8a;font-weight:700;padding:0 9px 6px">
        <div>فعال</div><div>نوع</div><div>ایموجی</div><div>عنوان</div><div>مقدار</div>
      </div>
      <?php foreach ($C['support_methods'] as $i => $m): ?>
      <div class="srow" style="grid-template-columns:40px 100px 60px 1fr 1.4fr">
        <input type="checkbox" name="s_on_<?= $i ?>" <?= !empty($m['on']) ? 'checked' : '' ?> style="width:auto">
        <select name="s_type_<?= $i ?>">
          <?php foreach (['url' => 'لینک', 'ticket' => 'تیکت', 'text' => 'متن', 'phone' => 'تلفن'] as $tk => $tl): ?>
            <option value="<?= $tk ?>" <?= ($m['type'] ?? '') === $tk ? 'selected' : '' ?>><?= $tl ?></option>
          <?php endforeach; ?>
        </select>
        <input name="s_emoji_<?= $i ?>" value="<?= h($m['emoji'] ?? '') ?>" style="text-align:center">
        <input name="s_label_<?= $i ?>" value="<?= h($m['label'] ?? '') ?>">
        <input name="s_value_<?= $i ?>" value="<?= h($m['value'] ?? '') ?>" placeholder="https://t.me/… یا متن یا شماره">
      </div>
      <?php endforeach; ?>
      <div style="margin-top:14px"><button class="btn g">ذخیره پشتیبانی</button></div>
    </form>

    <div class="prev">
      <div class="muted" style="margin-bottom:8px">پیش‌نمایش — چیزی که کاربر می‌بیند:</div>
      <?php foreach (['direct', 'indirect'] as $mk):
        $m = $C['support_main'][$mk];
        $cls = ['primary'=>'pb-b','success'=>'pb-g','danger'=>'pb-r'][$m['color'] ?? ''] ?? ''; ?>
        <div class="pbtn <?= $cls ?>"><?= h(trim($m['emoji'] . ' ' . $m['text'])) ?></div>
      <?php endforeach; ?>
    </div>
  </div></div>

<?php // ================= ربات‌های اپلودر ================= ?>
<?php elseif ($tab === 'bots'): ?>
  <?php
    $lkActiveBots = array_filter($bots, fn($b) => !empty($b['active']));
    $lkCamps = [];
    foreach (Campaign::all() as $c) if (!empty($c['active']) && !Campaign::isDone($c)) $lkCamps[] = $c;
  ?>
  <div class="card"><h2>🔒 قفل‌های عضویت اجباری</h2><div class="body">
    <div class="note">
      هر کانال روی <b><?= (int)BOTS_PER_CAMPAIGN ?> ربات</b> قفل می‌شود، و هر کاربر حداکثر
      <b><?= (int)MAX_JOIN_PER_BOT ?> کانال</b> می‌بیند.
    </div>
    <?php if (!$lkActiveBots): ?>
      <div class="empty"><div class="ic">🔒</div>هیچ ربات اپلودرِ فعالی ندارید — تا ربات اضافه نکنید، هیچ کانالی قفل نمی‌شود.</div>
    <?php elseif (!$lkCamps): ?>
      <div class="muted">الان کمپین فعالی نیست. 🤖 ربات‌ها: <b><?= count($lkActiveBots) ?></b></div>
    <?php else: ?>
      <table>
        <tr><th>ربات</th><th>تعداد کانال</th></tr>
        <?php foreach ($lkActiveBots as $b): $mine = 0;
          foreach ($lkCamps as $c) { $on = $c['bots'] ?? []; if (!$on || in_array($b['id'], $on, true)) $mine++; }
          $fixed = count(Channels::all($b['id']));
          $tot = $mine + $fixed;
        ?>
          <tr>
            <td>@<?= h($b['username']) ?></td>
            <td><?= $tot > MAX_JOIN_PER_BOT ? '<span class="badge red">⚠️ ' . $tot . '</span>' : $tot ?>
              <?= $fixed ? '<span class="muted">(' . $fixed . ' ثابت)</span>' : '' ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
      <div class="muted" style="margin-top:8px">📣 کمپین فعال: <b><?= count($lkCamps) ?></b></div>
    <?php endif; ?>
    <form method="post" style="margin-top:12px" onsubmit="return confirm('کمپین‌های فعال دوباره بین ربات‌ها پخش شوند؟')">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
      <input type="hidden" name="action" value="locks_rebalance">
      <button class="btn b">🔄 پخش دوباره بین ربات‌ها</button>
    </form>
  </div></div>

  <div class="card"><h2>➕ افزودن ربات اپلودر</h2><div class="body">
    <div class="note">
      ربات‌های اپلودر <b>لازم نیست در هیچ کانالی عضو یا ادمین باشند</b> —
      بررسی عضویت اجباری همیشه با توکن <b>ربات مادر</b> انجام می‌شود.
      فقط ربات مادر باید در کانال ادمین باشد.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
      <input type="hidden" name="action" value="add_bot">
      <label>توکن ربات (از @BotFather)</label>
      <input name="token" required placeholder="123456:ABC-DEF..." style="direction:ltr">
      <div style="margin-top:12px"><button class="btn g">افزودن ربات</button></div>
    </form>
  </div></div>

  <?php if (!$bots): ?>
    <div class="card"><div class="body"><div class="empty"><div class="ic">🤖</div>هنوز ربات اپلودری اضافه نکرده‌اید.</div></div></div>
  <?php endif; ?>

  <?php foreach ($bots as $b):
    $bs = BotManager::settings($b['id']);
    $links = Links::all($b['id']);
    $myChans = [];
    foreach ($channels as $cid => $ch) if (in_array($b['id'], $ch['bots'] ?? [], true)) $myChans[] = $cid;
  ?>
  <div class="card">
    <h2>🤖 @<?= h($b['username']) ?> — <?= count($links) ?> لینک · <?= count(load('bots/' . $b['id'] . '/users')) ?> کاربر</h2>
    <div class="body">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
        <input type="hidden" name="action" value="save_bot_full"><input type="hidden" name="id" value="<?= h($b['id']) ?>">

        <h3 style="font-size:13.5px;margin-bottom:9px">⚙️ رفتار</h3>
        <div class="grid2">
          <div><label>⏱ حذف فایل بعد از (ثانیه)</label>
            <input name="del_sec" type="number" min="5" value="<?= (int)$bs['delete_seconds'] ?>"></div>
          <div><label>گزینه‌ها</label>
            <label style="font-weight:500"><input type="checkbox" name="force_join" style="width:auto"
              <?= !empty($bs['force_join']) ? 'checked' : '' ?>> 🔒 عضویت اجباری</label>
            <label style="font-weight:500"><input type="checkbox" name="protect" style="width:auto"
              <?= !empty($bs['protect_content']) ? 'checked' : '' ?>> 🛡 جلوگیری از فوروارد</label>
            <label style="font-weight:500"><input type="checkbox" name="inline_wait" style="width:auto"
              <?= !empty($bs['inline_wait']) ? 'checked' : '' ?>> ⏳ انتظار درون‌خطی (دقت بیشتر حذف)</label></div>
        </div>

        <h3 style="font-size:13.5px;margin:18px 0 9px">📢 کانال‌های این ربات</h3>
        <div class="note">هیچ‌کدام را نزنید = کانال‌های عمومی اعمال می‌شود. اگر بزنید، فقط همان‌ها برای این ربات چک می‌شوند.</div>
        <?php $applicable = count(Channels::forBot($b['id']));
        if (!empty($bs['force_join']) && $applicable === 0): ?>
          <div class="flash warn" style="margin:0 0 10px">
            ⚠️ عضویت اجباری روشن است ولی <b>هیچ کانالی</b> برای این ربات اعمال نمی‌شود —
            یعنی فایل‌ها بدون قفل تحویل داده می‌شوند. یک کانال انتخاب کنید یا کانالی بسازید که برای «همه» باشد.
          </div>
        <?php endif; ?>
        <?php if (!$channels): ?><div class="muted">کانالی ثبت نشده.</div>
        <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:9px;margin-bottom:6px">
          <?php foreach ($channels as $cid => $ch): ?>
            <label style="font-weight:500;background:#1e1e1e;padding:7px 12px;border-radius:9px">
              <input type="checkbox" name="bot_channels[]" value="<?= h($cid) ?>" style="width:auto"
                <?= in_array($cid, $myChans, true) ? 'checked' : '' ?>> <?= h($ch['title']) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <h3 style="font-size:13.5px;margin:18px 0 9px">🎨 دکمه‌های شیشه‌ای این ربات</h3>
        <div style="display:grid;grid-template-columns:44px 1fr 96px 90px 52px 52px 40px;gap:7px;
                    font-size:11px;color:#8a8a8a;font-weight:700;padding:0 10px 6px">
          <div>ایموجی</div><div>متن</div><div>رنگ</div><div>✨ پریمیوم</div><div>ردیف</div><div>ترتیب</div><div>فعال</div>
        </div>
        <?php foreach ($bs['buttons'] as $bk => $bb): ?>
        <div class="brow" style="grid-template-columns:44px 1fr 96px 90px 52px 52px 40px">
          <input name="b_emoji_<?= h($bk) ?>" value="<?= h($bb['emoji'] ?? '') ?>" style="text-align:center">
          <input name="b_text_<?= h($bk) ?>" value="<?= h($bb['text']) ?>">
          <select name="b_color_<?= h($bk) ?>">
            <?php foreach (styleMap() as $sk => $sl): ?>
              <option value="<?= h($sk) ?>" <?= ($bb['color'] ?? '') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
            <?php endforeach; ?></select>
          <input name="b_icon_<?= h($bk) ?>" value="<?= h($bb['icon'] ?? '') ?>" placeholder="کد" style="direction:ltr">
          <input name="b_row_<?= h($bk) ?>" type="number" min="1" value="<?= (int)($bb['row'] ?? 1) ?>">
          <input name="b_order_<?= h($bk) ?>" type="number" min="1" value="<?= (int)($bb['order'] ?? 1) ?>">
          <input type="checkbox" name="b_on_<?= h($bk) ?>" <?= !empty($bb['on']) ? 'checked' : '' ?> style="width:auto">
        </div>
        <?php endforeach; ?>

        <h3 style="font-size:13.5px;margin:18px 0 9px">💠 رنگ دکمه‌های داخل ربات</h3>
        <div class="grid2">
          <?php
          $bgLabels = ['join'=>'📢 کانال عضویت','joined'=>'✅ عضو شدم','nav'=>'◀️ بازگشت',
                       'cancel'=>'↩️ انصراف','upload'=>'📤 آپلود','info'=>'ℹ️ اطلاعات'];
          foreach ($bgLabels as $role => $lbl): ?>
            <div><label><?= $lbl ?></label><select name="bg_<?= h($role) ?>">
              <?php foreach (styleMap() as $sk => $sl): ?>
                <option value="<?= h($sk) ?>" <?= ($bs['glass_colors'][$role] ?? 'none') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
              <?php endforeach; ?></select></div>
          <?php endforeach; ?>
        </div>

        <h3 style="font-size:13.5px;margin:18px 0 9px">📝 متن‌های این ربات</h3>
        <div class="note">
          برای گذاشتن <b>ایموجی پریمیوم</b> داخل متن‌ها، از نوار ابزار ✨ استفاده کنید —
          کد را با دستور <code>/emoji</code> در ربات مادر بگیرید.
        </div>
        <div class="tgrid">
          <?php
          $tLabels = ['menu_text'=>'🤖 منوی پنل — {links} {sec} {join} {bot}',
                      'start_text'=>'👋 پیام شروع — {name}',
                      'join_text'=>'🔒 متن عضویت اجباری',
                      'warn_text'=>'⚠️ هشدار حذف — {sec}',
                      'deleted_text'=>'🗑 بعد از حذف',
                      'expired_text'=>'❌ لینک نامعتبر'];
          foreach ($tLabels as $tk => $tl):
            $fid = 'bt_' . $b['id'] . '_' . $tk; ?>
            <div><label><?= h($tl) ?></label>
              <div class="tbar">
                <button type="button" onclick="wrapSel('<?= $fid ?>','<blockquote>','</blockquote>')">❝ نقل‌قول</button>
                <button type="button" onclick="wrapSel('<?= $fid ?>','<blockquote expandable>','</blockquote>')">❝ بازشو</button>
                <button type="button" onclick="wrapSel('<?= $fid ?>','<b>','</b>')"><b>پررنگ</b></button>
                <button type="button" onclick="premEmoji('<?= $fid ?>')">✨ ایموجی پریمیوم</button>
              </div>
              <textarea id="<?= $fid ?>" name="<?= h($tk) ?>"><?= h($bs[$tk] ?? '') ?></textarea></div>
          <?php endforeach; ?>
          <div><label>متن دکمه «عضو شدم»</label><input name="joined_btn" value="<?= h($bs['joined_btn']) ?>"></div>
        </div>

        <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
          <button class="btn g">ذخیره این ربات</button>
          <label style="font-weight:500;background:#54430f;padding:9px 13px;border-radius:9px">
            <input type="checkbox" name="apply_all" style="width:auto"> 📋 اعمال روی <b>همه</b> ربات‌ها
          </label>
        </div>
      </form>

      <h3 style="font-size:13.5px;margin:18px 0 9px">👑 مدیران این ربات</h3>
      <div class="note">مدیر می‌تواند در این ربات فایل آپلود کند و لینک بسازد — ولی به پنل وب و ربات مادر دسترسی ندارد.</div>
      <?php $ad = $b['admins'] ?? []; ?>
      <?php if ($ad): ?>
      <div style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:10px">
        <?php foreach ($ad as $au): ?>
          <span class="chip"><?= h(uLabel($users, $au)) ?> <code><?= h($au) ?></code>
            <form method="post" class="inline" onsubmit="return confirm('حذف مدیر؟')">
              <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
              <input type="hidden" name="action" value="del_bot_admin"><input type="hidden" name="id" value="<?= h($b['id']) ?>">
              <input type="hidden" name="user_id" value="<?= h($au) ?>"><button title="حذف">✕</button>
            </form></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
        <input type="hidden" name="action" value="add_bot_admin"><input type="hidden" name="id" value="<?= h($b['id']) ?>">
        <div style="flex:1;min-width:170px"><label>آیدی عددی مدیر جدید</label>
          <input name="user_id" type="number" placeholder="کاربر با /id آیدیش را می‌گیرد" style="direction:ltr"></div>
        <button class="btn b">افزودن مدیر</button>
      </form>

      <div style="margin-top:16px;padding-top:14px;border-top:1px solid #2b2b2b">
        <form method="post" class="inline">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
          <input type="hidden" name="action" value="bot_webhook"><input type="hidden" name="id" value="<?= h($b['id']) ?>">
          <button class="btn b sm">تنظیم دوباره وبهوک</button>
        </form>
        <form method="post" class="inline" onsubmit="return confirm('حذف این ربات؟')">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
          <input type="hidden" name="action" value="del_bot"><input type="hidden" name="id" value="<?= h($b['id']) ?>">
          <button class="btn r sm">حذف ربات</button>
        </form>
      </div>

      <?php if ($links): ?>
      <div style="margin-top:16px"><div class="scroll"><table>
        <tr><th>عنوان</th><th>لینک</th><th>👁</th><th>📥</th><th></th></tr>
        <?php foreach (array_slice(array_reverse($links, true), 0, 30, true) as $code => $l): ?>
        <tr><td><?= h($l['title'] ?: count($l['files']) . ' فایل') ?></td>
          <td><code><?= h(Links::url($b['id'], $code)) ?></code></td>
          <td><?= (int)$l['clicks'] ?></td><td><?= (int)$l['delivered'] ?></td>
          <td><form method="post" onsubmit="return confirm('حذف لینک؟')">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
            <input type="hidden" name="action" value="del_link"><input type="hidden" name="bot" value="<?= h($b['id']) ?>">
            <input type="hidden" name="code" value="<?= h($code) ?>">
            <button class="btn r sm">حذف</button></form></td></tr>
        <?php endforeach; ?>
      </table></div></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

<?php // ================= کانال‌ها ================= ?>
<?php elseif ($tab === 'channels'): ?>
  <?php
    $fresh = []; $lost = [];
    foreach (Channels::all() as $c9) {
      if (isset($c9['seen']) && empty($c9['seen'])) $fresh[] = $c9;
      if (!empty($c9['lost_admin'])) $lost[] = $c9;
    }
  ?>
  <?php if ($fresh || $lost): ?>
  <div class="card"><h2>🆕 تغییرات تازه</h2><div class="body">
    <div class="note">
      ربات دیگر برای این‌ها پیام نمی‌فرستد تا شلوغ نشود — همه‌شان اینجا نشان داده می‌شوند.
    </div>
    <table style="margin-top:12px">
      <tr><th>کانال</th><th>آیدی</th><th>وضعیت</th></tr>
      <?php foreach ($fresh as $c9): ?>
        <tr><td><?= h($c9['title']) ?></td><td><code><?= h($c9['chat_id']) ?></code></td>
            <td><span class="badge green">تازه ثبت شد</span>
                <?= !empty($c9['added_at']) ? '<span class="muted"> · ' . h($c9['added_at']) . '</span>' : '' ?></td></tr>
      <?php endforeach; ?>
      <?php foreach ($lost as $c9): ?>
        <tr><td><?= h($c9['title']) ?></td><td><code><?= h($c9['chat_id']) ?></code></td>
            <td><span class="badge">ربات دیگر ادمین نیست</span>
                <span class="muted"> · <?= h($c9['lost_admin']) ?></span></td></tr>
      <?php endforeach; ?>
    </table>
    <form method="post" style="margin-top:12px">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="channels">
      <input type="hidden" name="action" value="seen_channels">
      <button class="btn">دیدم، پاک کن</button>
    </form>
  </div></div>
  <?php endif; ?>

  <div class="card"><h2>📢 افزودن کانال عضویت اجباری</h2><div class="body">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="channels">
      <input type="hidden" name="action" value="add_channel">
      <div class="grid2">
        <div><label>آیدی یا یوزرنیم کانال</label><input name="chat_id" required placeholder="@mychannel یا -1001234567890" style="direction:ltr"></div>
        <div><label>لینک عضویت (اختیاری)</label><input name="url" placeholder="https://t.me/..." style="direction:ltr"></div>
      </div>
      <?php if ($bots): ?>
      <div style="margin-top:12px"><label>فقط برای این ربات‌ها (خالی = همه)</label>
        <div style="display:flex;flex-wrap:wrap;gap:9px">
          <?php foreach ($bots as $bb): ?>
            <label style="font-weight:500;background:#1e1e1e;padding:7px 12px;border-radius:9px">
              <input type="checkbox" name="bots[]" value="<?= h($bb['id']) ?>" style="width:auto"> @<?= h($bb['username']) ?>
            </label>
          <?php endforeach; ?>
        </div></div>
      <?php endif; ?>
      <p class="muted" style="margin-top:10px;line-height:1.9">
        ✅ فقط <b>ربات مادر</b> باید در این کانال <b>ادمین</b> باشد.
        ربات‌های اپلودر لازم نیست عضو یا ادمین کانال باشند — بررسی عضویت همیشه با توکن ربات مادر انجام می‌شود.<br>
        اگر ربات مادر را در کانالی ادمین کنید، کانال <b>خودکار</b> همین‌جا ثبت می‌شود.
      </p>
      <div style="margin-top:12px"><button class="btn g">افزودن کانال</button></div>
    </form>
  </div></div>

  <div class="card"><h2>🩺 بررسی سلامت</h2><div class="body">
    <p class="muted" style="margin-bottom:10px;line-height:1.9">
      اگر <b>ربات مادر</b> در کانالی ادمین نباشد، <b>قفل بسته می‌ماند</b> و هیچ‌کس فایل نمی‌گیرد.
      با این دکمه دسترسی ربات مادر به همه کانال‌ها را بررسی کنید.
    </p>
    <form method="post" class="inline">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="channels">
      <input type="hidden" name="action" value="health">
      <button class="btn b">بررسی دسترسی همه ربات‌ها</button>
    </form>
    <?php if (!empty($_SESSION['health'])): ?>
      <div style="margin-top:14px;background:#1e1e1e;border-radius:10px;padding:14px;line-height:2;font-size:13px">
        <?php foreach ($_SESSION['health'] as $line): ?><div><?= h($line) ?></div><?php endforeach; ?>
      </div>
      <?php unset($_SESSION['health']); ?>
    <?php endif; ?>
  </div></div>

  <div class="card"><h2>📢 کانال‌ها (<?= count($channels) ?>)</h2><div class="body">
    <p class="muted" style="margin-bottom:12px">این کانال‌ها روی <b>همه</b> ربات‌های اپلودر اعمال می‌شوند.</p>
    <?php if (!$channels): ?><div class="empty"><div class="ic">📢</div>کانالی ثبت نشده.</div>
    <?php else: ?><div class="scroll"><table>
      <tr><th>عنوان</th><th>آیدی</th><th>ربات‌ها</th><th>وضعیت</th><th>اقدام</th></tr>
      <?php foreach ($channels as $ch): ?>
      <tr><td><?= h($ch['title']) ?></td><td><code><?= h($ch['chat_id']) ?></code></td>
        <td><?php if (empty($ch['bots'])): ?><span class="badge gray">همه</span><?php else:
          foreach ($ch['bots'] as $bid2) { $bb2 = BotManager::get($bid2);
            echo '<span class="badge green">@' . h($bb2['username'] ?? $bid2) . '</span> '; } endif; ?></td>
        <td><?= !empty($ch['on']) ? '<span class="badge green">فعال</span>' : '<span class="badge gray">خاموش</span>' ?></td>
        <td style="white-space:nowrap">
          <form method="post" class="inline">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="channels">
            <input type="hidden" name="action" value="toggle_channel"><input type="hidden" name="id" value="<?= h($ch['id']) ?>">
            <button class="btn ghost sm"><?= !empty($ch['on']) ? 'خاموش' : 'روشن' ?></button></form>
          <form method="post" class="inline" onsubmit="return confirm('حذف کانال؟')">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="channels">
            <input type="hidden" name="action" value="del_channel"><input type="hidden" name="id" value="<?= h($ch['id']) ?>">
            <button class="btn r sm">حذف</button></form>
        </td></tr>
      <?php endforeach; ?>
    </table></div><?php endif; ?>
  </div></div>

<?php // ================= سفارش ممبر (کمپین) ================= ?>
<?php elseif ($tab === 'campaigns'): ?>
  <div class="card"><h2>⚡️ خودکار است</h2><div class="body">
    <div class="note">
      <b>لازم نیست دستی چیزی بسازید.</b> به‌محض اینکه سفارش ممبری پرداخت و تایید شود،
      کانال مشتری <b>خودکار</b> در بخش عضویت اجباری همه ربات‌های اپلودر قفل می‌شود،
      و به‌محض رسیدن به تعداد سفارش <b>خودکار</b> برداشته می‌شود.<br><br>
      فرم پایین فقط برای موارد دستی است — مثلا وقتی مشتری خارج از ربات سفارش داده،
      یا ربات موقع سفارش در کانال ادمین نبوده.
    </div>
  </div></div>

  <div class="card"><h2>🎯 ثبت دستی سفارش ممبر</h2><div class="body">
    <div class="note">
      کانال مشتری تا رسیدن به تعداد سفارش، در بخش <b>عضویت اجباری</b> قفل می‌شود —
      هم در ربات‌های اپلودر خودمان، هم در ربات‌های شریک.
      به‌محض پر شدن سهمیه، کانال خودکار از قفل خارج می‌شود.<br>
      ⚠️ <b>ربات مادر</b> باید در کانال مشتری ادمین باشد تا بتواند عضویت را بشمارد.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="campaigns">
      <input type="hidden" name="action" value="add_campaign">
      <div class="grid2">
        <div><label>آیدی یا یوزرنیم کانال مشتری</label>
          <input name="chat_id" required placeholder="@customer یا -100..." style="direction:ltr"></div>
        <div><label>تعداد ممبر سفارش</label><input name="target" type="number" min="1" required placeholder="1000"></div>
        <div><label>عنوان (خالی = از خود کانال)</label><input name="title" placeholder="کانال مشتری"></div>
        <div><label>لینک عضویت (خالی = خودکار)</label><input name="url" placeholder="https://t.me/..." style="direction:ltr"></div>
      </div>
      <div style="margin-top:12px"><label>یادداشت</label><input name="note" placeholder="سفارش آقای X — فاکتور ۱۲۳"></div>

      <div style="margin-top:12px"><label>روی کدام ربات‌های اپلودر؟ (خالی = همه)</label>
        <div style="display:flex;flex-wrap:wrap;gap:9px">
          <?php foreach ($bots as $bb): ?>
            <label style="font-weight:500;background:#1e1e1e;padding:7px 12px;border-radius:9px">
              <input type="checkbox" name="bots[]" value="<?= h($bb['id']) ?>" style="width:auto"> @<?= h($bb['username']) ?></label>
          <?php endforeach; ?>
          <?php if (!$bots): ?><span class="muted">رباتی ندارید.</span><?php endif; ?>
        </div></div>

      <div style="margin-top:12px"><label>روی کدام ربات‌های شریک؟ (خالی = همه)</label>
        <div style="display:flex;flex-wrap:wrap;gap:9px">
          <?php foreach ($partners as $pp): ?>
            <label style="font-weight:500;background:#1e1e1e;padding:7px 12px;border-radius:9px">
              <input type="checkbox" name="partners[]" value="<?= h($pp['id']) ?>" style="width:auto"> <?= h($pp['name']) ?></label>
          <?php endforeach; ?>
          <?php if (!$partners): ?><span class="muted">شریکی ندارید.</span><?php endif; ?>
        </div></div>

      <div style="margin-top:14px"><button class="btn g">ثبت سفارش</button></div>
    </form>
  </div></div>

  <?php foreach ($campaigns as $c):
    $done = Campaign::isDone($c);
    $cnt  = count($c['joined']);
    $pct  = ((int)$c['target']) > 0 ? min(100, round($cnt * 100 / (int)$c['target'])) : 0; ?>
  <div class="card">
    <h2>
      <?= $done ? '✅' : (!empty($c['active']) ? '🎯' : '⏸') ?> <?= h($c['title']) ?>
      — <?= $cnt ?> / <?= (int)$c['target'] ?>
      <?= $done ? '<span class="badge green">تکمیل</span>' : '' ?>
    </h2>
    <div class="body">
      <div class="bar"><div class="bar-in" style="width:<?= $pct ?>%"></div></div>
      <div class="muted" style="margin:6px 0 14px">
        <?= $pct ?>% · باقی‌مانده: <b><?= h(Campaign::remaining($c)) ?></b> ·
        <code><?= h($c['chat_id']) ?></code>
        <?= !empty($c['note']) ? ' · ' . h($c['note']) : '' ?>
        <?= !empty($c['done_at']) ? ' · تکمیل در ' . h($c['done_at']) : '' ?>
      </div>
      <?php if (!empty($c['order_id'])): ?>
        <div class="note" style="margin-bottom:12px">
          ⚡️ خودکار از سفارش <code><?= h($c['order_id']) ?></code>
          <?php if ((int)($c['per_day'] ?? 0) > 0): ?>
            · سقف روزانه <b><?= number_format((int)$c['per_day']) ?></b> نفر
            (امروز: <?= (($c['day'] ?? '') === substr(date('Y-m-d H:i:s'), 0, 10))
                      ? (int)($c['day_count'] ?? 0) : 0 ?>)
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($c['paused_reason'])): ?>
        <div class="note" style="margin-bottom:12px;background:#5c2224;border-color:#e5484d">
          ⏸ <b>موقتا متوقف شد</b> — ربات نمی‌تواند عضویت را بررسی کند:
          <code><?= h($c['paused_reason']) ?></code><br>
          ربات مادر را دوباره در این کانال ادمین کنید، بعد از دکمه پایین روشنش کنید.
        </div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="campaigns">
        <input type="hidden" name="action" value="edit_campaign"><input type="hidden" name="id" value="<?= h($c['id']) ?>">
        <div class="grid2">
          <div><label>عنوان</label><input name="title" value="<?= h($c['title']) ?>"></div>
          <div><label>آیدی کانال<?= trim((string)($c['chat_id'] ?? '')) === '' ? ' ⚠️ خالی است' : '' ?></label>
            <input name="chat_id" value="<?= h($c['chat_id'] ?? '') ?>" placeholder="@customer یا -100..." style="direction:ltr"></div>
          <div><label>تعداد سفارش</label><input name="target" type="number" min="1" value="<?= (int)$c['target'] ?>"></div>
          <div><label>لینک عضویت</label><input name="url" value="<?= h($c['url']) ?>" style="direction:ltr"></div>
        </div>
        <div style="margin-top:10px"><label>ربات‌های اپلودر (خالی = همه)</label>
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($bots as $bb): ?>
              <label style="font-weight:500;background:#1e1e1e;padding:6px 11px;border-radius:8px">
                <input type="checkbox" name="bots[]" value="<?= h($bb['id']) ?>" style="width:auto"
                  <?= in_array($bb['id'], $c['bots'] ?? [], true) ? 'checked' : '' ?>> @<?= h($bb['username']) ?></label>
            <?php endforeach; ?>
          </div></div>
        <div style="margin-top:10px"><label>ربات‌های شریک (خالی = همه)</label>
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($partners as $pp): ?>
              <label style="font-weight:500;background:#1e1e1e;padding:6px 11px;border-radius:8px">
                <input type="checkbox" name="partners[]" value="<?= h($pp['id']) ?>" style="width:auto"
                  <?= in_array($pp['id'], $c['partners'] ?? [], true) ? 'checked' : '' ?>> <?= h($pp['name']) ?></label>
            <?php endforeach; ?>
          </div></div>
        <div style="margin-top:12px"><button class="btn g">ذخیره</button></div>
      </form>

      <div style="margin-top:12px;padding-top:12px;border-top:1px solid #2b2b2b">
        <form method="post" class="inline">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="campaigns">
          <input type="hidden" name="action" value="toggle_campaign"><input type="hidden" name="id" value="<?= h($c['id']) ?>">
          <button class="btn ghost sm"><?= !empty($c['active']) ? 'توقف' : 'ادامه' ?></button></form>
        <form method="post" class="inline" onsubmit="return confirm('حذف کمپین؟')">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="campaigns">
          <input type="hidden" name="action" value="del_campaign"><input type="hidden" name="id" value="<?= h($c['id']) ?>">
          <button class="btn r sm">حذف</button></form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$campaigns): ?><div class="card"><div class="body"><div class="empty"><div class="ic">🎯</div>هنوز سفارشی ثبت نشده.</div></div></div><?php endif; ?>

<?php // ================= ربات‌های شریک ================= ?>
<?php elseif ($tab === 'partners'):
  $apiBase = baseUrl() . '/bot_master_membership.php'; ?>
  <div class="card"><h2>🤝 افزودن ربات شریک</h2><div class="body">
    <div class="note">
      برای رباتی که <b>سورس خودش را دارد</b> و می‌خواهد فقط از بخش <b>عضویت اجباری</b> ما استفاده کند.
      توکن رباتشان را نمی‌گیریم — فقط یک کلید API می‌دهیم که با آن بپرسند
      «این کاربر باید عضو کدام کانال‌ها شود؟».
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="partners">
      <input type="hidden" name="action" value="add_partner">
      <div class="grid2">
        <div><label>نام شریک</label><input name="name" required placeholder="ربات فلانی"></div>
        <div><label>یوزرنیم رباتشان (اختیاری)</label><input name="bot_username" placeholder="their_bot" style="direction:ltr"></div>
        <div><label>آیدی عددی صاحبش (اختیاری)</label><input name="owner_id" type="number" style="direction:ltr"></div>
      </div>
      <div style="margin-top:14px"><button class="btn g">ساخت کلید API</button></div>
    </form>
  </div></div>

  <?php foreach ($partners as $pt): ?>
  <div class="card">
    <h2><?= !empty($pt['active']) ? '🟢' : '🔴' ?> <?= h($pt['name']) ?>
      <?= $pt['bot_username'] ? '— @' . h($pt['bot_username']) : '' ?></h2>
    <div class="body">
      <div class="grid2" style="margin-bottom:12px">
        <div><label>کلید API</label>
          <input value="<?= h($pt['key']) ?>" readonly onclick="this.select()" style="direction:ltr;background:#1e1e1e"></div>
        <div><label>آمار</label>
          <div class="muted" style="padding-top:8px">
            بررسی: <b><?= (int)$pt['checks'] ?></b> · موفق: <b><?= (int)$pt['passed'] ?></b>
            <?= $pt['last_seen'] ? ' · آخرین: ' . h($pt['last_seen']) : '' ?>
          </div></div>
      </div>

      <details>
        <summary style="cursor:pointer;font-weight:700;font-size:13.5px;margin-bottom:8px">
          📋 کدی که باید به شریک بدهید (کپی کنید)</summary>

        <div class="muted" style="margin:10px 0 6px"><b>۱) آدرس‌ها</b></div>
        <pre class="code">بررسی عضویت:
POST <?= h($apiBase) ?>?api=check
     key=<?= h($pt['key']) ?>&user_id=123456

فهرست کانال‌ها:
POST <?= h($apiBase) ?>?api=channels
     key=<?= h($pt['key']) ?></pre>

        <div class="muted" style="margin:12px 0 6px"><b>۲) پاسخ</b></div>
        <pre class="code">{"ok":true,"allowed":false,
 "missing":[{"title":"کانال ما","url":"https://t.me/..."}],
 "message":"برای ادامه، در کانال‌های زیر عضو شوید."}</pre>

        <div class="muted" style="margin:12px 0 6px"><b>۳) نمونه PHP</b></div>
        <pre class="code">function joinGate($userId) {
    $ch = curl_init('<?= h($apiBase) ?>?api=check');
    curl_setopt_array($ch, [
        CURLOPT_POST =&gt; true,
        CURLOPT_POSTFIELDS =&gt; http_build_query([
            'key' =&gt; '<?= h($pt['key']) ?>',
            'user_id' =&gt; $userId,
        ]),
        CURLOPT_RETURNTRANSFER =&gt; true,
        CURLOPT_TIMEOUT =&gt; 8,
    ]);
    $r = json_decode(curl_exec($ch), true);
    curl_close($ch);
    // اگر سرویس در دسترس نبود، قفل را باز نکنید
    if (empty($r['ok'])) return ['allowed' =&gt; false, 'missing' =&gt; []];
    return $r;
}

$gate = joinGate($userId);
if (!$gate['allowed']) {
    $rows = [];
    foreach ($gate['missing'] as $m)
        $rows[] = [['text' =&gt; '📢 ' . $m['title'], 'url' =&gt; $m['url']]];
    $rows[] = [['text' =&gt; '✅ عضو شدم', 'callback_data' =&gt; 'recheck']];
    // پیام «اول عضو شوید» را با این دکمه‌ها بفرستید
} else {
    // فایل/محتوا را بفرستید
}</pre>

        <div class="muted" style="margin:12px 0 6px"><b>۴) نمونه Python</b></div>
        <pre class="code">import requests

def join_gate(user_id):
    try:
        r = requests.post('<?= h($apiBase) ?>?api=check',
                          data={'key': '<?= h($pt['key']) ?>',
                                'user_id': user_id}, timeout=8).json()
    except Exception:
        return {'allowed': False, 'missing': []}
    if not r.get('ok'):
        return {'allowed': False, 'missing': []}
    return r</pre>
      </details>

      <div style="margin-top:14px;padding-top:12px;border-top:1px solid #2b2b2b">
        <form method="post" class="inline">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="partners">
          <input type="hidden" name="action" value="toggle_partner"><input type="hidden" name="id" value="<?= h($pt['id']) ?>">
          <button class="btn ghost sm"><?= !empty($pt['active']) ? 'غیرفعال کن' : 'فعال کن' ?></button></form>
        <form method="post" class="inline" onsubmit="return confirm('کلید قبلی از کار می‌افتد. مطمئنید؟')">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="partners">
          <input type="hidden" name="action" value="rotate_key"><input type="hidden" name="id" value="<?= h($pt['id']) ?>">
          <button class="btn b sm">🔄 تعویض کلید</button></form>
        <form method="post" class="inline" onsubmit="return confirm('حذف شریک؟')">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="partners">
          <input type="hidden" name="action" value="del_partner"><input type="hidden" name="id" value="<?= h($pt['id']) ?>">
          <button class="btn r sm">حذف</button></form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$partners): ?><div class="card"><div class="body"><div class="empty"><div class="ic">🤝</div>هنوز شریکی اضافه نکرده‌اید.</div></div></div><?php endif; ?>

<?php // ================= رفرال ================= ?>
<?php elseif ($tab === 'referral'):
  $refCount = []; $refEarn = [];
  foreach ($users as $u) {
      if (!empty($u['referrer'])) $refCount[(string)$u['referrer']] = ($refCount[(string)$u['referrer']] ?? 0) + 1;
      if (!empty($u['ref_earned'])) $refEarn[(string)$u['telegram_id']] = (float)$u['ref_earned'];
  }
  arsort($refCount);
  $totalEarn = array_sum($refEarn);
  $withRef = count(array_filter($users, fn($u) => !empty($u['referrer'])));
?>
  <div class="stats">
    <div class="stat"><div class="n"><?= count($refCount) ?></div><div class="l">👤 معرف فعال</div></div>
    <div class="stat"><div class="n"><?= $withRef ?></div><div class="l">👥 کاربر معرفی‌شده</div></div>
    <div class="stat"><div class="n"><?= fmtNum($totalEarn) ?></div><div class="l">💵 پورسانت پرداختی</div></div>
    <div class="stat"><div class="n"><?= h($C['referral']['percent']) ?>%</div><div class="l">📈 درصد فعلی</div></div>
  </div>

  <div class="card"><h2>⚙️ تنظیم رفرال</h2><div class="body">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="referral">
      <input type="hidden" name="action" value="save_settings">
      <input type="hidden" name="usdt" value="<?= h($C['wallets']['usdt']) ?>">
      <input type="hidden" name="trx" value="<?= h($C['wallets']['trx']) ?>">
      <input type="hidden" name="card" value="<?= h($C['wallets']['card']) ?>">
      <input type="hidden" name="card_name" value="<?= h($C['wallets']['card_name']) ?>">
      <input type="hidden" name="del_sec" value="<?= (int)$C['uploader']['delete_seconds'] ?>">
      <?php if (!empty($C['uploader']['force_join'])): ?><input type="hidden" name="force_join" value="1"><?php endif; ?>
      <?php if (!empty($C['uploader']['protect_content'])): ?><input type="hidden" name="protect" value="1"><?php endif; ?>
      <div class="grid2">
        <div><label>درصد پورسانت از هر خرید</label>
          <input name="ref_percent" type="number" min="0" max="100" step="0.5" value="<?= h($C['referral']['percent']) ?>"></div>
        <div><label>&nbsp;</label><label style="font-weight:500">
          <input type="checkbox" name="ref_on" style="width:auto" <?= !empty($C['referral']['on']) ? 'checked' : '' ?>>
          سیستم معرفی فعال باشد</label></div>
      </div>
      <div style="margin-top:14px"><button class="btn g">ذخیره</button></div>
    </form>
  </div></div>

  <div class="card"><h2>🏆 برترین معرف‌ها</h2><div class="body">
    <?php if (!$refCount): ?><div class="empty"><div class="ic">👥</div>هنوز کسی زیرمجموعه نگرفته.</div>
    <?php else: ?><div class="scroll"><table>
      <tr><th>#</th><th>معرف</th><th>آیدی</th><th>تعداد زیرمجموعه</th><th>پورسانت دریافتی</th><th>موجودی</th></tr>
      <?php $i = 1; foreach (array_slice($refCount, 0, 50, true) as $rid => $cnt): ?>
      <tr><td><?= $i++ ?></td>
        <td><?= h(uLabel($users, $rid)) ?></td>
        <td><code><?= h($rid) ?></code></td>
        <td><b><?= $cnt ?></b></td>
        <td><?= h(fmtNum($refEarn[$rid] ?? 0)) ?></td>
        <td><?= h(fmtNum($users[$rid]['balance'] ?? 0)) ?></td></tr>
      <?php endforeach; ?>
    </table></div><?php endif; ?>
  </div></div>

  <div class="card"><h2>📢 کانال اعلام فروش</h2><div class="body">
    <div class="note">
      هر خرید موفق به‌صورت خودکار در یک کانال جدا اعلام می‌شود — با
      <b>کد خرید</b>، <b>مبلغ</b> و <b>تعداد ممبر فروخته‌شده</b>.
      ربات مادر باید در آن کانال ادمین باشد.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="referral">
      <input type="hidden" name="action" value="save_sales">
      <div class="grid2">
        <div><label>آیدی کانال اعلام فروش</label>
          <input name="sales_chat" value="<?= h($C['sales']['chat_id']) ?>" placeholder="@saleschannel یا -100..." style="direction:ltr"></div>
        <div><label>گزینه‌ها</label>
          <label style="font-weight:500"><input type="checkbox" name="sales_on" style="width:auto"
            <?= !empty($C['sales']['on']) ? 'checked' : '' ?>> اعلام فروش فعال باشد</label>
          <label style="font-weight:500"><input type="checkbox" name="sales_user" style="width:auto"
            <?= !empty($C['sales']['show_user']) ? 'checked' : '' ?>> نمایش نام خریدار</label></div>
      </div>
      <div style="margin-top:12px">
        <label>قالب پیام</label>
        <div class="tbar">
          <button type="button" onclick="wrapSel('sales_tpl','<blockquote>','</blockquote>')">❝ نقل‌قول</button>
          <button type="button" onclick="wrapSel('sales_tpl','<blockquote expandable>','</blockquote>')">❝ بازشو</button>
          <button type="button" onclick="wrapSel('sales_tpl','<b>','</b>')"><b>پررنگ</b></button>
          <button type="button" onclick="wrapSel('sales_tpl','<code>','</code>')">&lt;/&gt; کد</button>
        <button type="button" onclick="premEmoji('sales_tpl')">✨ ایموجی پریمیوم</button>
        <button type="button" onclick="resetTpl('sales_tpl', <?= json_encode("<b>فروش جدید</b>\n\nمحصول: {product}\nکد خرید: <code>{code}</code>\nمبلغ: <b>{amount} {currency}</b>\nتعداد ممبر: <b>{count}</b>{limit_part}\n{date}") ?>)">🔄 پیش‌فرض</button>
        </div>
        <textarea id="sales_tpl" name="sales_tpl" style="min-height:140px"><?= h($C['sales']['template']) ?></textarea>
        <div class="muted" style="margin-top:6px;line-height:1.9">
          متغیرها: <code>{product}</code> <code>{code}</code> <code>{amount}</code> <code>{currency}</code>
          <code>{count}</code> <code>{limit}</code> <code>{remaining}</code> <code>{limit_part}</code>
          <code>{user}</code> <code>{user_id}</code> <code>{date}</code>
        </div>
      </div>
      <div style="margin-top:14px">
        <button class="btn g">ذخیره</button>
      </div>
    </form>
    <form method="post" style="margin-top:10px">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="referral">
      <input type="hidden" name="action" value="test_sales">
      <button class="btn b sm">ارسال پیام آزمایشی به کانال</button>
    </form>
  </div></div>

<?php // ================= کاربران ================= ?>
<?php elseif ($tab === 'users'): ?>
  <?php $uDetailId = trim((string)($_GET['id'] ?? '')); ?>
  <?php if ($uDetailId !== '' && isset($users[$uDetailId])): $uD = $users[$uDetailId];
    $uOrders = array_values(array_filter($orders, fn($o) => (string)$o['user_id'] === $uDetailId));
    uasort($uOrders, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    $uSpent = 0; foreach ($uOrders as $o) if ($o['status'] === Order::APPROVED && $o['type'] === 'product') $uSpent += (float)$o['amount'];
    $uTopup = 0; foreach ($uOrders as $o) if ($o['status'] === Order::APPROVED && $o['type'] === 'topup') $uTopup += (float)$o['amount'];
    $uRefs = array_filter($users, fn($x) => (int)($x['referrer'] ?? 0) === (int)$uDetailId);
  ?>
  <div class="crumb">کاربران <span>/</span> <b><?= h(uLabel($users, $uDetailId)) ?></b></div>
  <a href="?tab=users" class="btn ghost sm" style="margin-bottom:14px;display:inline-block">→ بازگشت به فهرست</a>

  <div class="card"><h2>👤 اطلاعات کاربر</h2><div class="body">
    <div class="grid2">
      <div><label>نام</label><div><?= h(!empty($uD['username']) ? '@' . $uD['username'] : ($uD['first_name'] ?? '—')) ?></div></div>
      <div><label>آیدی تلگرام</label><div><code><?= h($uDetailId) ?></code></div></div>
      <div><label>وضعیت</label><div><?= !empty($uD['banned']) ? '<span class="badge red">مسدود</span>' : '<span class="badge green">فعال</span>' ?></div></div>
      <div><label>تاریخ عضویت</label><div class="muted"><?= h($uD['joined_at'] ?? '—') ?></div></div>
    </div>
    <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap">
      <form method="post"><input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="users">
        <input type="hidden" name="action" value="ban_user"><input type="hidden" name="user_id" value="<?= h($uDetailId) ?>">
        <button type="button" class="btn <?= !empty($uD['banned']) ? 'g' : 'r' ?> sm" onclick="openConfirm(this.form,
          '<?= !empty($uD['banned']) ? 'آزادکردنِ کاربر' : 'مسدودکردنِ کاربر' ?>',
          [['کاربر','<?= h(addslashes(uLabel($users, $uDetailId))) ?>']],
          <?= !empty($uD['banned']) ? "''" : "'کاربر دیگر نمی‌تواند از ربات استفاده کند.'" ?>,
          '<?= !empty($uD['banned']) ? 'g' : 'r' ?>')"><?= !empty($uD['banned']) ? '✅ آزاد کردن' : '⛔️ مسدود کردن' ?></button>
      </form>
    </div>
  </div></div>

  <div class="card"><h2>💰 کیف پول</h2><div class="body">
    <div class="stats" style="margin-bottom:14px">
      <div class="stat"><div class="n amount"><?= h(fmtNum($uD['balance'] ?? 0)) ?></div><div class="l">موجودی فعلی</div></div>
      <div class="stat"><div class="n amount"><?= h(fmtNum($uTopup)) ?></div><div class="l">مجموع شارژ</div></div>
      <div class="stat"><div class="n amount"><?= h(fmtNum($uSpent)) ?></div><div class="l">مجموع خرید</div></div>
    </div>
    <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="users">
      <input type="hidden" name="action" value="set_balance"><input type="hidden" name="user_id" value="<?= h($uDetailId) ?>">
      <div style="flex:1;min-width:160px"><label>تغییرِ موجودی</label>
        <input id="uBalNew" name="balance" value="<?= h(fmtNum($uD['balance'] ?? 0)) ?>"></div>
      <button type="button" class="btn ghost" onclick="openConfirm(this.form,'تغییرِ موجودیِ کاربر',
        [['کاربر','<?= h(addslashes(uLabel($users, $uDetailId))) ?>'],
         ['موجودیِ فعلی','<?= h(addslashes(fmtNum($uD['balance'] ?? 0))) ?>'],
         ['موجودیِ تازه',document.getElementById('uBalNew')?document.getElementById('uBalNew').value:'']],
        'این عملیات مستقیم موجودیِ کاربر را تغییر می‌دهد.','b')">ذخیره</button>
    </form>
  </div></div>

  <div class="card"><h2>🧾 سفارش‌ها (<?= count($uOrders) ?>)</h2><div class="body">
    <?php if (!$uOrders): ?><div class="empty"><div class="ic">🧾</div>این کاربر هنوز سفارشی ثبت نکرده.</div>
    <?php else: ?><div class="scroll"><table class="responsive">
      <tr><th>شناسه</th><th>نوع</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr>
      <?php foreach (array_slice($uOrders, 0, 50) as $o): ?>
        <tr>
          <td data-label="شناسه"><code><?= h($o['id']) ?></code></td>
          <td data-label="نوع"><?= $o['type'] === 'topup' ? 'شارژ' : h(Product::get($o['product_id'])['name'] ?? '—') ?></td>
          <td data-label="مبلغ"><span class="amount"><?= h(fmtNum($o['amount'])) ?></span> <?= h($o['currency']) ?></td>
          <td data-label="وضعیت"><?= oBadge($o['status']) ?></td>
          <td data-label="تاریخ" class="muted"><?= h($o['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table></div><?php endif; ?>
  </div></div>

  <div class="card"><h2>👥 رفرال</h2><div class="body">
    <div class="grid2" style="margin-bottom:12px">
      <div><label>معرف</label><div><?= !empty($uD['referrer']) ? h(uLabel($users, $uD['referrer'])) : '<span class="muted">—</span>' ?></div></div>
      <div><label>تعداد زیرمجموعه</label><div><?= count($uRefs) ?></div></div>
    </div>
    <?php if ($uRefs): ?><div class="scroll"><table class="responsive">
      <tr><th>کاربر</th><th>آیدی</th></tr>
      <?php foreach (array_slice($uRefs, 0, 30) as $ru): ?>
        <tr><td data-label="کاربر"><a href="?tab=users&id=<?= h($ru['telegram_id']) ?>"><?= h(uLabel($users, $ru['telegram_id'])) ?></a></td>
          <td data-label="آیدی"><code><?= h($ru['telegram_id']) ?></code></td></tr>
      <?php endforeach; ?>
    </table></div><?php endif; ?>
  </div></div>

  <?php else: ?>
  <div class="card"><h2>📢 پیام همگانی</h2><div class="body">
    <form method="post" onsubmit="return confirm('ارسال به همه کاربران؟')">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="users">
      <input type="hidden" name="action" value="broadcast">
      <div class="tbar">
        <button type="button" onclick="wrapSel('bc_master','<blockquote>','</blockquote>')">❝ نقل‌قول</button>
        <button type="button" onclick="wrapSel('bc_master','<blockquote expandable>','</blockquote>')">❝ بازشو</button>
        <button type="button" onclick="wrapSel('bc_master','<b>','</b>')"><b>پررنگ</b></button>
        <button type="button" onclick="wrapSel('bc_master','<tg-spoiler>','</tg-spoiler>')">🫥 اسپویلر</button>
        <button type="button" onclick="premEmoji('bc_master')">✨ ایموجی پریمیوم</button>
      </div>
      <textarea id="bc_master" name="text" placeholder="متن پیام… (تگ HTML تلگرام مجاز است)" required></textarea>
      <div style="margin-top:12px"><button class="btn b">ارسال به <?= count($users) ?> کاربر</button></div>
    </form>
  </div></div>

  <div class="card"><h2>🤖 پیام همگانی به ربات‌های زیرمجموعه</h2><div class="body">
    <div class="note">
      این پیام با توکن <b>خود ربات اپلودر</b> فرستاده می‌شود، پس به کسانی هم می‌رسد
      که فقط با ربات فرعی چت کرده‌اند و ربات مادر را استارت نکرده‌اند.
    </div>
    <?php if (!$bots): ?><div class="empty"><div class="ic">🤖</div>هنوز ربات اپلودری ندارید.</div>
    <?php else: ?>
    <form method="post" onsubmit="return confirm('ارسال به کاربران ربات‌های انتخاب‌شده؟')">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="users">
      <input type="hidden" name="action" value="broadcast_child">
      <label>ربات‌های مقصد (هیچ‌کدام = همه)</label>
      <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px">
        <?php foreach ($bots as $b): ?>
          <label style="font-weight:500;background:#1e1e1e;padding:7px 12px;border-radius:9px">
            <input type="checkbox" name="bots[]" value="<?= h($b['id']) ?>" style="width:auto">
            @<?= h($b['username']) ?> (<?= count(load('bots/' . $b['id'] . '/users')) ?>)
          </label>
        <?php endforeach; ?>
      </div>
      <div class="tbar">
        <button type="button" onclick="wrapSel('bc_child','<blockquote>','</blockquote>')">❝ نقل‌قول</button>
        <button type="button" onclick="wrapSel('bc_child','<b>','</b>')"><b>پررنگ</b></button>
        <button type="button" onclick="premEmoji('bc_child')">✨ ایموجی پریمیوم</button>
      </div>
      <textarea id="bc_child" name="text" placeholder="متن پیام…" required></textarea>
      <div style="margin-top:12px"><button class="btn b">ارسال به ربات‌های زیرمجموعه</button></div>
    </form>
    <?php endif; ?>
  </div></div>

  <?php
    $uQ = trim((string)($_GET['q'] ?? ''));
    $uPerPage = 25;
    $uList = $users;
    if ($uQ !== '') {
        $needle = mb_strtolower($uQ);
        $uList = array_filter($uList, function ($u) use ($needle) {
            $hay = mb_strtolower(($u['username'] ?? '') . ' ' . ($u['first_name'] ?? '') . ' ' . $u['telegram_id']);
            return str_contains($hay, $needle);
        });
    }
    $uList = array_values($uList);
    $uTotalN = count($uList);
    $uPages = max(1, (int)ceil($uTotalN / $uPerPage));
    $uPage  = max(1, min((int)($_GET['page'] ?? 1), $uPages));
    $uSlice = array_slice($uList, ($uPage - 1) * $uPerPage, $uPerPage);
  ?>
  <div class="card"><h2>👥 کاربران (<?= $uTotalN ?>)</h2><div class="body">
    <form method="get" class="toolbar">
      <input type="hidden" name="tab" value="users">
      <div class="search"><input type="text" name="q" value="<?= h($uQ) ?>" placeholder="🔎 یوزرنیم، نام یا آیدی تلگرام…"></div>
      <button class="btn sm">جستجو</button>
    </form>
    <?php if (!$uSlice): ?>
      <div class="empty"><div class="ic">👥</div><?= $uQ !== '' ? 'با این جستجو کاربری پیدا نشد.' : 'هنوز کاربری ربات را استارت نکرده.' ?></div>
    <?php else: ?><div class="scroll"><table class="responsive">
      <tr><th>کاربر</th><th>آیدی</th><th>موجودی</th><th>معرف</th><th>زیرمجموعه</th><th>وضعیت</th><th>اقدام</th></tr>
      <?php foreach ($uSlice as $u): ?>
      <tr>
        <td data-label="کاربر"><a href="?tab=users&id=<?= h($u['telegram_id']) ?>"><?= h(!empty($u['username']) ? '@' . $u['username'] : ($u['first_name'] ?? '—')) ?></a></td>
        <td data-label="آیدی"><code><?= h($u['telegram_id']) ?></code></td>
        <td data-label="موجودی">
          <form method="post" style="display:flex;gap:5px">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="users">
            <input type="hidden" name="action" value="set_balance">
            <input type="hidden" name="user_id" value="<?= h($u['telegram_id']) ?>">
            <input name="balance" value="<?= h(fmtNum($u['balance'] ?? 0)) ?>" style="width:95px">
            <button class="btn ghost sm">ذخیره</button>
          </form>
        </td>
        <td data-label="معرف"><?= !empty($u['referrer']) ? h(uLabel($users, $u['referrer'])) : '<span class="muted">—</span>' ?></td>
        <td data-label="زیرمجموعه"><?= (int)($refCount[(int)$u['telegram_id']] ?? 0) ?></td>
        <td data-label="وضعیت"><?= !empty($u['banned']) ? '<span class="badge red">مسدود</span>' : '<span class="badge green">فعال</span>' ?></td>
        <td data-label="اقدام"><form method="post">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="users">
          <input type="hidden" name="action" value="ban_user"><input type="hidden" name="user_id" value="<?= h($u['telegram_id']) ?>">
          <button class="btn <?= !empty($u['banned']) ? 'g' : 'r' ?> sm"><?= !empty($u['banned']) ? 'آزاد' : 'مسدود' ?></button>
        </form></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <?php pager($uPage, $uPages); ?>
    <?php endif; ?>
  </div></div>
  <?php endif; ?>

<?php // ================= تنظیمات ================= ?>
<?php elseif ($tab === 'auto'):   // ================= ⚡ خودکارسازی =================
  $F   = maCfg()['fulfill'] ?? [];
  $W   = axCfg()['wallet'];
  $AU  = axAudit();
  $okN = 0; foreach ($AU as $r) if ($r['ok']) $okN++;
  $hasMn = trim((string)$W['mnemonic']) !== '';
  $bal   = $hasMn ? axWalletBalance() : null;

  // چهار گام تا فروش کاملا خودکار
  $step = [
    'api'    => trim((string)($F['base'] ?? '')) !== '' && trim((string)($F['auth_value'] ?? '')) !== '',
    'wallet' => $hasMn && trim((string)$W['address']) !== '',
    'verify' => (int)$W['verified'] > 0,
    'live'   => !empty($W['on']) && empty($W['dry']) && !empty($F['on']) && !empty($F['auto_pay']),
  ];
  $doneN = 0; foreach ($step as $v) if ($v) $doneN++;
?>

  <div class="card"><h2>⚡ چهار گام تا فروش کاملا خودکار</h2><div class="body">
    <div class="note">
      <b>سفارش خودکار یعنی چه؟</b> مشتری پرداخت می‌کند → ربات خودش سفارش را روی پنل فروش ثبت می‌کند →
      پنل یک <b>تراکنش امضانشده</b> برمی‌گرداند → ربات با ولت شما امضایش می‌کند و می‌فرستد →
      پنل پول را می‌بیند و محصول را تحویل مشتری می‌دهد.<br>
      <b>بدون گام سوم و چهارم، زنجیره وسط راه می‌ایستد و سفارش منتظر شما می‌ماند.</b>
    </div>

    <div class="bar" style="margin-bottom:6px"><div class="bar-in" style="width:<?= (int)($doneN/4*100) ?>%"></div></div>
    <p class="muted" style="margin-bottom:16px"><b><?= $doneN ?></b> از <b>۴</b> گام انجام شده</p>

    <div class="tgrid">
      <?php
      $labels = [
        'api'    => ['۱. اتصال به پنل فروش', 'آدرس پنل و کلید API — تا اینجا نباشد ربات اصلا نمی‌تواند سفارش بدهد'],
        'wallet' => ['۲. ثبت ولت', 'آدرس ولت و عبارت بازیابی ۲۴ کلمه‌ای — بدون این، تراکنش امضا نمی‌شود'],
        'verify' => ['۳. تایید مالکیت', 'ربات کلید عمومی روی زنجیره را با عبارت شما می‌سنجد'],
        'live'   => ['۴. خروج از حالت آزمایشی', 'تا وقتی آزمایشی روشن است، تراکنش ساخته می‌شود ولی فرستاده نمی‌شود'],
      ];
      foreach ($labels as $k => $l): ?>
        <div style="display:flex;gap:11px;align-items:flex-start">
          <span style="font-size:19px;line-height:1.3"><?= $step[$k] ? '✅' : '⚪️' ?></span>
          <div><b style="font-size:13.5px"><?= h($l[0]) ?></b>
            <div class="muted" style="line-height:1.85"><?= h($l[1]) ?></div></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div></div>

  <div class="card"><h2>گام ۱ — اتصال به پنل فروش</h2><div class="body">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="auto">
      <input type="hidden" name="action" value="auto_api">
      <div class="grid2">
        <div><label>آدرس پنل</label>
          <input name="base" dir="ltr" placeholder="https://api.marketapp.org"
                 value="<?= h((string)($F['base'] ?? '')) ?>"></div>
        <div><label>کلید API <?= trim((string)($F['auth_value'] ?? '')) !== '' ? '<span class="badge green">ثبت شده</span>' : '' ?></label>
          <input name="api_key" dir="ltr" placeholder="<?= trim((string)($F['auth_value'] ?? '')) !== '' ? 'برای تغییر، کلید تازه را بگذارید' : 'کلید را از پنل فروش بگیرید' ?>"></div>
      </div>
      <div style="margin-top:13px;display:flex;gap:18px;flex-wrap:wrap">
        <label class="inline"><input type="checkbox" name="f_on" style="width:auto" <?= !empty($F['on']) ? 'checked' : '' ?>> تحویل خودکار روشن</label>
        <label class="inline"><input type="checkbox" name="f_auto" style="width:auto" <?= !empty($F['auto_pay']) ? 'checked' : '' ?>> بلافاصله بعد از پرداخت</label>
        <label class="inline"><input type="checkbox" name="preset" style="width:auto" checked> تنظیمات آماده marketapp</label>
      </div>
      <p class="muted" style="margin-top:9px">
        «تنظیمات آماده» سه مسیر <code>/recipient/</code> و <code>/price/</code> و <code>/buy/</code> را با
        <code>currency=GRAM</code> می‌نشاند — همان قراردادی که در مستندات پنل هست.
      </p>
      <div style="margin-top:14px"><button class="btn g">ذخیره</button></div>
    </form>
  </div></div>

  <div class="card"><h2>گام ۲ — ولت TON، امضای خودکار تراکنش</h2><div class="body">
    <?php [$cOk, $cWhy] = tonCryptoReady(); if (!$cOk): ?>
      <div class="flash err" style="margin-top:0">
        🔴 <b>این هاست هنوز نمی‌تواند تراکنش امضا کند</b><br><br>
        <?= nl2br($cWhy) ?>
      </div>
      <p class="muted" style="margin-bottom:14px">
        بقیه‌ی ربات — فروش ممبر، مخزن تحویل، سفارش دستی، گزارش‌ها — بدون این هم کار می‌کند.
        فقط امضای خودکار تراکنش TON به این افزونه نیاز دارد.
      </p>
    <?php endif; ?>

    <div class="flash warn" style="margin-top:0">
      ⚠️ <b>عبارت بازیابی روی همین هاست ذخیره می‌شود.</b>
      یک ولت <b>جداگانه</b> بسازید و فقط به اندازه‌ی فروش یکی دو روز داخلش پول بگذارید.
      ولت اصلی‌تان هرگز اینجا نیاید. هرکس به هاست دسترسی پیدا کند، به این ولت هم دسترسی دارد.
    </div>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="auto">
      <input type="hidden" name="action" value="auto_wallet">

      <div class="grid2">
        <div><label>آدرس ولت <span class="muted">(هر آدرسی از همان کیف پول)</span></label>
          <input name="w_addr" dir="ltr" placeholder="UQ… یا EQ…" value="<?= h((string)$W['address']) ?>">
          <p class="muted">لازم نیست دقیقا آدرسِ همان ۲۴ کلمه باشد — هر آدرس فعالی از
          کیف پولتان کافی است. ربات از روی آن نوع ولت را می‌فهمد و
          <b>آدرس درست را خودش پیدا و ذخیره می‌کند</b>.</p></div>
        <div><label>نسخه قرارداد ولت</label>
          <select name="w_ver">
            <option value="v4r2" <?= (string)$W['version'] === 'v4r2' ? 'selected' : '' ?>>v4R2 (رایج‌ترین)</option>
            <option value="v3r2" <?= (string)$W['version'] === 'v3r2' ? 'selected' : '' ?>>v3R2 (قدیمی‌تر)</option>
          </select></div>
      </div>

      <div style="margin-top:13px">
        <label>عبارت بازیابی ۲۴ کلمه‌ای
          <?= $hasMn ? '<span class="badge green">ثبت شده</span>' : '<span class="badge red">ثبت نشده</span>' ?></label>
        <textarea name="w_mn" dir="ltr" style="min-height:70px"
          placeholder="<?= $hasMn ? 'ثبت شده — برای تعویض، ۲۴ کلمه‌ی تازه را اینجا بگذارید' : 'word1 word2 word3 … word24' ?>"></textarea>
        <p class="muted">برای امنیت، عبارت ذخیره‌شده هرگز اینجا نمایش داده نمی‌شود.</p>
      </div>

      <div style="margin-top:13px">
        <label>🔒 رمز عبارت بازیابی <span class="muted">(اگر کیف پول موقع ساخت گرفته)</span>
          <?= trim((string)($W['passphrase'] ?? '')) !== '' ? '<span class="badge amber">ثبت شده</span>' : '' ?></label>
        <input name="w_pw" dir="ltr" autocomplete="off"
               placeholder="<?= trim((string)($W['passphrase'] ?? '')) !== '' ? 'ثبت شده — برای پاک کردن یک خط تیره - بگذارید' : 'اگر رمزی نبوده، خالی بگذارید' ?>">
        <p class="muted">⚠️ این با رمز یا پینِ باز کردن برنامه <b>فرق دارد</b>. آن پین فقط قفل خود اپ است
        و کلید ولت را عوض نمی‌کند. این فیلد فقط برای کیف پول‌هایی است که هنگام ساختِ
        عبارت بازیابی یک رمز اضافه می‌گیرند.</p>
      </div>

      <div class="grid2" style="margin-top:13px">
        <div><label>آدرس API شبکه</label>
          <input name="w_api" dir="ltr" value="<?= h((string)$W['api']) ?>"></div>
        <div><label>کلید API شبکه
            <?= trim((string)$W['api_key']) !== '' ? '<span class="badge green">ثبت شده</span>'
                                                   : '<span class="badge amber">توصیه می‌شود</span>' ?></label>
          <input name="w_apikey" dir="ltr" placeholder="<?= trim((string)$W['api_key']) !== '' ? 'ثبت شده — برای تغییر، کلید تازه بگذارید' : 'از @tonapibot در تلگرام، رایگان' ?>">
          <p class="muted">بدون کلید، toncenter فقط حدود <b>یک درخواست در ثانیه</b> می‌دهد و
          خطای ۴۲۹ می‌گیرید. از <code>@tonapibot</code> در تلگرام یک کلید mainnet
          رایگان بگیرید — یک دقیقه طول می‌کشد و همه‌چیز روان می‌شود.</p></div>
      </div>

      <div class="grid2" style="margin-top:13px">
        <div><label>🚧 سقف هر تراکنش (TON)</label>
          <input name="w_max" type="number" step="0.01" min="0.01" value="<?= h((string)$W['max_ton']) ?>"></div>
        <div><label>🚧 سقف مجموع یک روز (TON)</label>
          <input name="w_day" type="number" step="0.01" min="0.01" value="<?= h((string)$W['day_ton']) ?>"></div>
      </div>
      <p class="muted" style="margin-top:7px">
        این دو سقف تنها چیزی هستند که جلوی خالی شدن ولت را می‌گیرند. پایین بگذاریدشان.
        خرج امروز: <b><?= h(((string)$W['day'] === substr(nowStr(),0,10)) ? nanoToTon((string)$W['day_spent']) : '0') ?></b> TON
      </p>

      <div style="margin-top:15px;display:flex;gap:18px;flex-wrap:wrap">
        <label class="inline"><input type="checkbox" name="w_dry" style="width:auto" <?= !empty($W['dry']) ? 'checked' : '' ?>>
          🧪 حالت آزمایشی <span class="muted">(می‌سازد و امضا می‌کند ولی <b>نمی‌فرستد</b>)</span></label>
        <label class="inline"><input type="checkbox" name="w_on" style="width:auto" <?= !empty($W['on']) ? 'checked' : '' ?>>
          روشن</label>
      </div>

      <div style="margin-top:15px;display:flex;gap:9px;flex-wrap:wrap">
        <button class="btn g">ذخیره</button>
      </div>
    </form>

    <div style="margin-top:15px;padding-top:15px;border-top:1px solid #2b2b2b;display:flex;gap:9px;flex-wrap:wrap;align-items:center">
      <form method="post" class="inline">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="auto">
        <input type="hidden" name="action" value="auto_fix">
        <button class="btn g">🎯 آدرس درست را خودت پیدا کن</button>
      </form>
      <form method="post" class="inline">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="auto">
        <input type="hidden" name="action" value="auto_diag">
        <button class="btn ghost">🩻 تشخیص لایه‌به‌لایه</button>
      </form>
      <form method="post" class="inline">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="auto">
        <input type="hidden" name="action" value="auto_verify">
        <button class="btn b">🧪 تایید مالکیت و موجودی</button>
      </form>
      <?php if ($hasMn): ?>
      <form method="post" class="inline" onsubmit="return confirm('عبارت بازیابی پاک شود؟ ولت هم خاموش می‌شود.')">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="auto">
        <input type="hidden" name="action" value="auto_wipe">
        <button class="btn r">🗑 پاک کردن عبارت بازیابی</button>
      </form>
      <?php endif; ?>
      <span class="badge <?= (int)$W['verified'] > 0 ? 'green' : 'amber' ?>">
        <?= (int)$W['verified'] > 0 ? '✅ تایید شده ' . h(date('Y-m-d H:i', (int)$W['verified'])) : '⚠️ هنوز تایید نشده' ?></span>
      <?php if ($bal !== null): ?><span class="badge gray">موجودی: <?= h($bal) ?> TON</span><?php endif; ?>
    </div>
  </div></div>

  <div class="card"><h2>📋 ترتیب راه‌اندازی امن</h2><div class="body">
    <p class="muted" style="line-height:2.1">
      ۱. یک ولت <b>تازه</b> بسازید و ۲۴ کلمه‌اش را جایی امن نگه دارید<br>
      ۲. مقدار کمی TON داخلش بگذارید — مثلا ۱ تا ۲ تا<br>
      ۳. همین بالا آدرس و عبارت بازیابی را بگذارید و <b>ذخیره</b> کنید<br>
      ۴. <b>🧪 تایید مالکیت</b> را بزنید تا تیک سبز شود<br>
      ۵. با <b>حالت آزمایشی روشن</b> یک خرید واقعی از مینی‌اپ بزنید — ربات در تلگرام
         نشانتان می‌دهد چه تراکنشی ساخته و امضا شده، ولی چیزی نمی‌فرستد<br>
      ۶. اگر مبلغ و مقصد درست بود، تیک آزمایشی را بردارید و <b>یک خرید خیلی کوچک</b> واقعی بزنید<br>
      ۷. رسید که آمد، تمام است — از این به بعد بدون شما کار می‌کند
    </p>
  </div></div>

  <div class="card"><h2>🩺 بررسی کامل — چه چیزی واقعا خودکار است؟</h2><div class="body">
    <p class="muted" style="margin-bottom:14px"><b><?= $okN ?></b> از <b><?= count($AU) ?></b> مورد سرِ جایش است.
      هر ⚠️ یعنی آن بخش منتظر شماست، نه اینکه خراب باشد.</p>
    <div class="tgrid">
      <?php foreach ($AU as $r): ?>
        <div style="display:flex;gap:11px;align-items:flex-start">
          <span style="font-size:16px;line-height:1.5"><?= $r['ok'] ? '✅' : '⚠️' ?></span>
          <div><b style="font-size:13px"><?= h($r['name']) ?></b>
            <?php if (trim((string)$r['why']) !== ''): ?>
              <div class="muted" style="line-height:1.85"><?= h($r['why']) ?></div>
            <?php endif; ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div></div>

<?php // ================= ☎️ شماره مجازی ================= ?>
<?php elseif ($tab === 'numbers'): ?>
  <?php
    $NP  = numProv(); $NPI = numProvInfo(); $NAPI = numVal('api', []);
    $numOpenCount = 0; foreach (numAll() as $na) if (($na['status'] ?? '') === 'waiting') $numOpenCount++;
    $numCatsMax = (int)(maGet('num')['cats_max'] ?? 0);
  ?>
  <div class="card"><h2>☎️ شماره مجازی تلگرام
    <?= !empty($NAPI['on']) ? '<span class="badge green">روشن</span>' : '<span class="badge">خاموش</span>' ?>
  </h2><div class="body">
    <div class="note">
      فقط شماره‌ی تلگرام فروخته می‌شود. فروشنده رو انتخاب کن، کلیدش رو بذار، بقیه خودکاره.
      متن‌ها و ظاهرِ مینی‌اپ (که تلگرامیه، نه اینجا) همچنان تو <code>/panel</code> ← ☎️ شماره مجازی تنظیم می‌شن.
    </div>
    <?php if ($numOpenCount): ?>
      <div class="flash warn">⏳ <?= $numOpenCount ?> شماره‌ی باز الان منتظرِ کد هستن — از <code>/panel</code> ← 📋 شماره‌های باز ببین.</div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="numbers">
      <input type="hidden" name="action" value="save_numbers">

      <details class="subcard" open><summary><h3>🏪 فروشنده و اتصال</h3></summary>
        <div style="margin-bottom:10px">
          <label style="font-weight:500"><input type="checkbox" name="num_on" style="width:auto"
            <?= !empty($NAPI['on']) ? 'checked' : '' ?>> روشن باشد</label>
        </div>
        <div class="grid2">
          <div><label>فروشنده</label>
            <select name="provider">
              <?php foreach (numProviders() as $pk => $pv): ?>
                <option value="<?= h($pk) ?>" <?= $NP === $pk ? 'selected' : '' ?>><?= h($pv['label']) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div><label><?= h($NPI['key']) ?> <?= h($NPI['name']) ?> (خالی = بدونِ تغییر)</label>
            <div class="secret"><input type="password" name="api_key" autocomplete="off"
              value="<?= h($NP === 'numberland' ? ($NAPI['nl_key'] ?? '') : ($NAPI['token'] ?? '')) ?>" style="direction:ltr">
              <button type="button" onclick="toggleSecret(this)">👁</button></div></div>
          <?php if ($NP === 'numberland'): ?>
          <div><label>🎯 کد سرویسِ تلگرام نزدِ نامبرلند</label>
            <input name="nl_svc" value="<?= h($NAPI['nl_svc'] ?? '1') ?>" style="direction:ltr"></div>
          <?php endif; ?>
          <div><label>🌐 آدرسِ پایه (خالی = پیش‌فرضِ فروشنده)</label>
            <input name="base" value="<?= h($NAPI['base'] ?? '') ?>" placeholder="<?= h($NPI['base']) ?>" style="direction:ltr"></div>
        </div>
        <div class="muted" style="margin-top:8px"><?= h($NPI['help']) ?></div>
      </details>

      <details class="subcard"><summary><h3>💰 قیمت و سود</h3></summary>
        <div class="grid2">
          <div><label>📈 درصد سود</label>
            <input name="markup" value="<?= h(rtrim(rtrim(number_format((float)numVal('markup', 0), 2), '0'), '.')) ?>" style="direction:ltr"></div>
          <?php if (numNeedsRate()): ?>
          <div><label>💵 نرخ دلار دستی (۰ = خودکار از بخش قیمت‌گیری)</label>
            <input name="rate" value="<?= h((float)numVal('api.rate', 0) ?: '') ?>" placeholder="خودکار" style="direction:ltr"></div>
          <?php endif; ?>
          <div><label>🧢 سقفِ قیمتِ خرید (۰ = بی‌سقف)</label>
            <input name="max" value="<?= h((float)numVal('api.max', 0) ?: '') ?>" placeholder="بی‌سقف" style="direction:ltr"></div>
          <div><label>&nbsp;</label><label style="font-weight:500">
            <input type="checkbox" name="sync_price" style="width:auto" <?= !empty(numVal('sync_price', true)) ? 'checked' : '' ?>>
            قیمت هر بار تازه از فروشنده گرفته شود</label></div>
        </div>
        <?php if (numNeedsRate() && numRate() <= 0): ?>
          <div class="flash warn" style="margin-top:10px">⚠️ بدون نرخِ دلار، قیمتی وارد نمی‌شود.</div>
        <?php endif; ?>
      </details>

      <details class="subcard"><summary><h3>⏱ زمان‌بندی</h3></summary>
        <div class="grid2">
          <div><label>⏳ مهلتِ انتظارِ کد (ثانیه)</label>
            <input name="wait" type="number" min="60" value="<?= (int)numVal('wait', 900) ?>"></div>
          <div><label>🔁 فاصله‌ی پیگیری (ثانیه)</label>
            <input name="poll" type="number" min="2" value="<?= (int)numVal('poll', 6) ?>"></div>
          <div><label>⏱ مهلتِ تماس با فروشنده (ثانیه)</label>
            <input name="timeout" type="number" min="5" value="<?= (int)($NAPI['timeout'] ?? 15) ?>"></div>
          <div><label>🌍 تعدادِ کشورِ صفحه‌ی اولِ مینی‌اپ (۰ = همه)</label>
            <input name="cats_max" type="number" min="0" value="<?= $numCatsMax ?>" placeholder="همه"></div>
        </div>
      </details>

      <div style="margin-top:14px"><button class="btn g">ذخیره تنظیماتِ شماره مجازی</button></div>
    </form>
  </div></div>

<?php // ================= 🚀 مینی‌اپ‌ها ================= ?>
<?php elseif ($tab === 'miniapps'): ?>
  <?php $MAC = maCfg(); $maBase = maBaseUrl(); ?>
  <div class="card"><h2>🚀 مینی‌اپ‌ها</h2><div class="body">
    <div class="note">
      متن‌ها، دکمه‌ی زیرِ محصولات، و دکمه‌های شیشه‌ایِ فاکتور همچنان تو <code>/panel</code> ← 🚀 تنظیماتِ مینی‌اپ‌ها می‌مونن —
      اینجا فقط آدرس، تمِ گرافیکی، دسته‌بندی‌ها و قیمتِ سرویس‌هاست.
    </div>
    <?php if ($maBase === ''): ?>
      <div class="flash warn">⚠️ آدرسِ عمومی ثبت نشده — تا وقتی ثبت نشه، دکمه‌ی مینی‌اپ نمایش داده نمی‌شه.</div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="miniapps">
      <input type="hidden" name="action" value="save_miniapps_root">
      <div class="grid2">
        <div><label>🔗 آدرسِ عمومیِ فایلِ ربات (باید https باشه)</label>
          <input name="base_url" value="<?= h($MAC['base_url'] ?? '') ?>" placeholder="https://site.com/bot_master_membership.php" style="direction:ltr"></div>
        <div><label>📐 چیدمانِ دکمه‌های مینی‌اپ زیرِ محصولات</label>
          <input name="row_layout" value="<?= h($MAC['row_layout'] ?? '1,1') ?>" style="direction:ltr"></div>
      </div>
      <div style="margin-top:14px"><button class="btn g">ذخیره</button></div>
    </form>
  </div></div>

  <?php foreach (maKeys() as $mk):
    $app = maGet($mk); $th = $app['theme'] ?? [];
    $appLbl = maAppLabels()[$mk] ?? $mk;
  ?>
  <div class="card"><h2><?= h($appLbl) ?>
    <?= !empty($app['on']) ? '<span class="badge green">روشن</span>' : '<span class="badge">خاموش</span>' ?>
  </h2><div class="body">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="miniapps">
      <input type="hidden" name="action" value="save_miniapp_app"><input type="hidden" name="key" value="<?= h($mk) ?>">
      <details class="subcard" open><summary><h3>🔌 وضعیت</h3></summary>
        <label style="font-weight:500"><input type="checkbox" name="app_on" style="width:auto"
          <?= !empty($app['on']) ? 'checked' : '' ?>> این مینی‌اپ روشن باشد</label>
        <?php $mu = maUrl($mk); if ($mu !== ''): ?>
          <div class="muted" style="margin-top:6px"><code style="direction:ltr;display:inline-block"><?= h($mu) ?></code></div>
        <?php endif; ?>
      </details>
      <details class="subcard"><summary><h3>🎨 تمِ گرافیکی</h3></summary>
        <div class="grid2">
          <div><label>رنگِ اصلی (آبی)</label><input type="color" name="theme_c1" value="<?= h($th['c1'] ?? '#2F6FED') ?>" style="padding:4px;height:42px"></div>
          <div><label>رنگِ دوم (سبز)</label><input type="color" name="theme_c2" value="<?= h($th['c2'] ?? '#17C978') ?>" style="padding:4px;height:42px"></div>
          <div><label>رنگِ تاکید (بنفش)</label><input type="color" name="theme_c3" value="<?= h($th['c3'] ?? '#8B5CF6') ?>" style="padding:4px;height:42px"></div>
          <div><label>رنگِ قرمز (خطا)</label><input type="color" name="theme_c4" value="<?= h($th['c4'] ?? '#F23557') ?>" style="padding:4px;height:42px"></div>
        </div>
        <div class="muted" style="margin-top:8px">🔒 هر سه مینی‌اپ یک پالتِ رنگی مشترک دارند و پس‌زمینه‌شان همیشه سفید است — قابل تغییر نیست.</div>
        <div style="margin-top:10px">
          <label style="font-weight:500"><input type="checkbox" name="theme_glow" style="width:auto" <?= !empty($th['glow']) ? 'checked' : '' ?>> ✨ درخشش</label>
          <label style="font-weight:500"><input type="checkbox" name="theme_grain" style="width:auto" <?= !empty($th['grain']) ? 'checked' : '' ?>> بافت</label>
        </div>
        <div style="margin-top:10px;max-width:240px"><label>سطحِ افکت (۰ خاموش تا ۲ کامل)</label>
          <input name="theme_fx" type="number" min="0" max="2" value="<?= (int)($th['fx'] ?? 2) ?>"></div>
      </details>
      <div style="margin-top:14px"><button class="btn g">ذخیره این مینی‌اپ</button></div>
    </form>

    <?php if (!empty($app['cats'])): ?>
    <form method="post" style="margin-top:16px">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="miniapps">
      <input type="hidden" name="action" value="save_miniapp_cats"><input type="hidden" name="key" value="<?= h($mk) ?>">
      <details class="subcard"><summary><h3>📂 دسته‌بندی‌ها</h3></summary>
        <div style="display:flex;flex-wrap:wrap;gap:9px">
          <?php foreach ($app['cats'] as $c): ?>
            <label style="font-weight:500;background:#1e1e1e;padding:7px 12px;border-radius:9px">
              <input type="checkbox" name="cat_on[]" value="<?= h($c['id']) ?>" style="width:auto"
                <?= !empty($c['on']) ? 'checked' : '' ?>> <?= h(trim(($c['emoji'] ?? '') . ' ' . $c['name'])) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:14px"><button class="btn g">ذخیره دسته‌بندی‌ها</button></div>
      </details>
    </form>
    <?php endif; ?>

    <?php if (!empty($app['items'])): ?>
    <form method="post" style="margin-top:16px">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="miniapps">
      <input type="hidden" name="action" value="save_miniapp_items"><input type="hidden" name="key" value="<?= h($mk) ?>">
      <details class="subcard"><summary><h3>🛒 سرویس‌ها (<?= count($app['items']) ?>)</h3></summary>
        <div class="note">
          «حداقل/حداکثر تعداد» فقط برای مواردی است که مشتری خودش تعداد وارد می‌کند (تون، ترون، استارزِ دلخواه...) —
          برای اعشار (مثلا <code>0.5</code> تون) همان‌جا با نقطه بنویسید.
        </div>
        <?php foreach ($app['items'] as $it): $iid = (string)($it['id'] ?? ''); if ($iid === '') continue;
              $isQty = in_array((string)($it['ask'] ?? ''), ['qty', 'qty_wallet', 'qty_username', 'qty_link'], true); ?>
        <details class="itemrow">
          <summary>
            <span class="ir-name"><?= h(trim(($it['emoji'] ?? '') . ' ' . $it['name'])) ?></span>
            <span class="ir-price"><?= h(number_format((float)($it['price'] ?? 0))) ?></span>
            <label class="inline" onclick="event.stopPropagation()" style="margin:0">
              <input type="checkbox" name="item_on[]" value="<?= h($iid) ?>" style="width:auto" <?= !empty($it['on']) ? 'checked' : '' ?>>
            </label>
            <span class="ir-car">▾</span>
          </summary>
          <div class="ir-body">
            <?php if (!empty($it['desc'])): ?><div class="muted" style="margin-bottom:8px"><?= h(mb_substr($it['desc'], 0, 80)) ?></div><?php endif; ?>
            <div class="grid2">
              <div><label>قیمت</label><input name="item_price[<?= h($iid) ?>]" value="<?= h(number_format((float)($it['price'] ?? 0))) ?>" style="direction:ltr"></div>
              <?php if ($isQty): ?>
              <div><label>حداقل تعداد</label><input name="item_min[<?= h($iid) ?>]" value="<?= h(rtrim(rtrim(number_format((float)($it['min'] ?? 1), 4, '.', ''), '0'), '.')) ?>" style="direction:ltr"></div>
              <div><label>حداکثر تعداد</label><input name="item_max[<?= h($iid) ?>]" value="<?= h(rtrim(rtrim(number_format((float)($it['max'] ?? 1), 4, '.', ''), '0'), '.')) ?>" style="direction:ltr"></div>
              <?php endif; ?>
            </div>
          </div>
        </details>
        <?php endforeach; ?>
        <div style="margin-top:14px"><button class="btn g">ذخیره سرویس‌ها</button></div>
      </details>
    </form>
    <?php endif; ?>
  </div></div>
  <?php endforeach; ?>

<?php // ================= 💎 الماس ================= ?>
<?php elseif ($tab === 'diamond'): ?>
  <?php $DM = dmCfg(); $DMS = function_exists('dmStats') ? dmStats() : ['users' => 0, 'points' => 0, 'total' => 0]; ?>
  <div class="card"><h2>💎 الماس
    <?= !empty($DM['on']) ? '<span class="badge green">روشن</span>' : '<span class="badge">خاموش</span>' ?>
  </h2><div class="body">
    <div class="note">
      متن‌های پیام‌ها (وقتی الماس می‌گیره، لِول‌آپ، هدیه، زندان و...) همچنان تو <code>/panel</code> ← 💎 الماس ← ✏️ متن‌ها می‌مونن.
    </div>
    <div class="stats" style="margin-bottom:16px">
      <div class="stat"><div class="n"><?= number_format($DMS['users']) ?></div><div class="l">👥 بازیکن</div></div>
      <div class="stat"><div class="n"><?= number_format($DMS['points']) ?></div><div class="l">💎 مجموعِ الماس</div></div>
      <div class="stat"><div class="n"><?= number_format($DMS['total']) ?></div><div class="l">🔁 تعدادِ دفعات</div></div>
    </div>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="diamond">
      <input type="hidden" name="action" value="save_diamond">

      <details class="subcard" open><summary><h3>🔌 وضعیت و کلمه</h3></summary>
        <div style="margin-bottom:10px">
          <label style="font-weight:500"><input type="checkbox" name="dm_on" style="width:auto" <?= !empty($DM['on']) ? 'checked' : '' ?>> روشن باشد</label>
          <label style="font-weight:500"><input type="checkbox" name="group_only" style="width:auto" <?= !empty($DM['group_only']) ? 'checked' : '' ?>> فقط داخلِ گروه کار کند</label>
        </div>
        <div class="grid2">
          <div><label>💬 کلمه</label><input name="word" value="<?= h($DM['word'] ?? 'الماس') ?>"></div>
          <div><label>➕ کلمه‌های دیگر (با ویرگول)</label><input name="aliases" value="<?= h($DM['aliases'] ?? '') ?>"></div>
        </div>
      </details>

      <details class="subcard"><summary><h3>🎁 جایزه</h3></summary>
        <div class="grid2">
          <div><label>⏳ فاصله بینِ دو الماس (ثانیه)</label><input name="cooldown" type="number" min="0" value="<?= (int)($DM['cooldown'] ?? 300) ?>"></div>
          <div><label>🎁 جایزه‌ی پایه (سطح ۱)</label><input name="base" value="<?= h($DM['base'] ?? 56.74) ?>" style="direction:ltr"></div>
          <div><label>📈 ضریبِ رشدِ جایزه با هر سطح</label><input name="ratio" value="<?= h($DM['ratio'] ?? 1.2336) ?>" style="direction:ltr"></div>
          <div><label>🔢 کفِ جایزه</label><input name="min_reward" value="<?= h($DM['min'] ?? 20) ?>" style="direction:ltr"></div>
          <div><label>🧢 سقفِ جایزه</label><input name="cap" value="<?= h($DM['cap'] ?? 1000000000) ?>" style="direction:ltr"></div>
          <div><label>⭐️ امتیاز لازم برای هر سطح</label><input name="level_step" type="number" min="1" value="<?= (int)($DM['level_step'] ?? 10000) ?>"></div>
          <div><label>🏆 تعدادِ نفراتِ لیستِ برترین‌ها</label><input name="top_n" type="number" min="1" value="<?= (int)($DM['top_n'] ?? 10) ?>"></div>
        </div>
      </details>

      <details class="subcard"><summary><h3>🔁 تبدیل به کیف پول (۰ = خاموش)</h3></summary>
        <div class="grid2">
          <div><label>هر ۱ الماس چند تومان</label><input name="to_wallet" value="<?= h($DM['to_wallet'] ?? 0) ?>" placeholder="0 = خاموش" style="direction:ltr"></div>
          <div><label>حداقلِ الماس برای تبدیل</label><input name="min_swap" value="<?= h($DM['min_swap'] ?? 10000) ?>" style="direction:ltr"></div>
        </div>
      </details>

      <details class="subcard"><summary><h3>🎁 هدیه با الماس</h3></summary>
        <div style="margin-bottom:10px">
          <label style="font-weight:500"><input type="checkbox" name="gift_on" style="width:auto" <?= !empty($DM['gift']['on']) ? 'checked' : '' ?>> روشن باشد</label>
        </div>
        <div class="grid2">
          <div><label>💎 چند الماس خرج شود</label><input name="gift_cost" value="<?= h($DM['gift']['cost'] ?? 100000) ?>" style="direction:ltr"></div>
          <div><label>🚀 کدام مینی‌اپ</label>
            <select name="gift_app">
              <option value="tg" <?= ($DM['gift']['app'] ?? 'tg') === 'tg' ? 'selected' : '' ?>>🌟 خدمات تلگرام</option>
              <option value="num" <?= ($DM['gift']['app'] ?? 'tg') === 'num' ? 'selected' : '' ?>>☎️ شماره مجازی</option>
            </select></div>
          <div><label>🛍 شناسه‌ی محصول (از کاتالوگِ همون مینی‌اپ)</label>
            <input name="gift_item" value="<?= h($DM['gift']['item'] ?? '') ?>" style="direction:ltr"></div>
          <div><label>💬 کاربر چه بنویسد</label><input name="gift_word" value="<?= h($DM['gift']['word'] ?? 'هدیه') ?>"></div>
          <div><label>🔢 سقفِ دفعات برای هر نفر (۰ = بی‌نهایت)</label>
            <input name="gift_limit" type="number" min="0" value="<?= (int)($DM['gift']['limit'] ?? 0) ?>"></div>
        </div>
      </details>

      <details class="subcard"><summary><h3>🚨 زندان</h3></summary>
        <div class="grid2">
          <div><label>کلمه‌های دام (با ویرگول)</label><input name="jail_words" value="<?= h($DM['jail']['words'] ?? '') ?>"></div>
          <div><label>چند نفرِ متفاوت باید تایید کنن</label><input name="jail_need" type="number" min="1" value="<?= (int)($DM['jail']['need'] ?? 3) ?>"></div>
          <div><label>مدتِ زندان (ثانیه)</label><input name="jail_secs" type="number" min="0" value="<?= (int)($DM['jail']['secs'] ?? 3600) ?>"></div>
          <div><label>رنگِ دکمه‌ی تایید</label>
            <select name="jail_color">
              <?php foreach (styleMap() as $sk => $sl): ?>
                <option value="<?= h($sk) ?>" <?= ($DM['jail']['color'] ?? 'danger') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
              <?php endforeach; ?>
            </select></div>
        </div>
      </details>

      <div style="margin-top:16px"><button class="btn g">ذخیره تنظیماتِ الماس</button></div>
    </form>
  </div></div>

<?php // ================= 🎮 بازی‌ها ================= ?>
<?php elseif ($tab === 'games'): ?>
  <?php $GM = gmCfg(); ?>
  <div class="card"><h2>🎮 بازی‌ها (چالش و قرعه با الماس)
    <?= !empty($GM['on']) ? '<span class="badge green">روشن</span>' : '<span class="badge">خاموش</span>' ?>
  </h2><div class="body">
    <div class="note">
      شرط‌ها با <b>الماس</b> بازی می‌شود (تبِ «💎 الماس»)، نه پول نقد. متن‌های پیام‌ها و ایموجیِ پریمیومِ دکمه‌ها
      همچنان تو <code>/panel</code> ← 🎮 بازی‌ها می‌مونن.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="games">
      <input type="hidden" name="action" value="save_games">

      <details class="subcard" open><summary><h3>🔌 وضعیت</h3></summary>
        <label style="font-weight:500"><input type="checkbox" name="gm_on" style="width:auto" <?= !empty($GM['on']) ? 'checked' : '' ?>> بازی‌ها روشن باشند</label>
        <label style="font-weight:500;margin-right:14px"><input type="checkbox" name="duel_board" style="width:auto" <?= !empty($GM['duel_board']) ? 'checked' : '' ?>> چالش با صفحه‌ی دوز (نه نتیجه‌ی درجا)</label>
      </details>

      <details class="subcard"><summary><h3>💬 کلمه‌های شروع</h3></summary>
        <div class="grid2">
          <div><label>🎯 چالش (با ویرگول)</label><input name="word_duel" value="<?= h($GM['word_duel'] ?? 'چالش,دوز') ?>"></div>
          <div><label>🎲 قرعه</label><input name="word_rand" value="<?= h($GM['word_rand'] ?? 'بازی') ?>"></div>
          <div><label>💎 موجودی</label><input name="word_bal" value="<?= h($GM['word_bal'] ?? 'موجودی') ?>"></div>
          <div><label>📤 انتقال (با ویرگول)</label><input name="word_send" value="<?= h($GM['word_send'] ?? 'انتقال') ?>"></div>
        </div>
      </details>

      <details class="subcard"><summary><h3>💰 شرط و مالیات</h3></summary>
        <div class="grid2">
          <div><label>کمترینِ شرط (الماس)</label><input name="gm_min" value="<?= h($GM['min'] ?? 10) ?>" style="direction:ltr"></div>
          <div><label>بیشترینِ شرط (الماس)</label><input name="gm_max" value="<?= h($GM['max'] ?? 1000000000) ?>" style="direction:ltr"></div>
          <div><label>🧾 مالیاتِ برد (٪)</label><input name="tax" value="<?= h($GM['tax'] ?? 10) ?>" style="direction:ltr"></div>
          <div><label>🧾 مالیاتِ انتقال (٪)</label><input name="send_tax" value="<?= h($GM['send_tax'] ?? 10) ?>" style="direction:ltr"></div>
        </div>
      </details>

      <details class="subcard"><summary><h3>⏱ محدودیت‌ها</h3></summary>
        <div class="grid2">
          <div><label>حداکثرِ بازیِ بازِ هم‌زمانِ هر نفر</label><input name="open_max" type="number" min="1" value="<?= (int)($GM['open_max'] ?? 2) ?>"></div>
          <div><label>مهلتِ بی‌حریف تا لغوِ خودکار (ثانیه)</label><input name="expire" type="number" min="10" value="<?= (int)($GM['expire'] ?? 180) ?>"></div>
          <div><label>مهلتِ انتظارِ قرعه (ثانیه)</label><input name="wait" type="number" min="1" value="<?= (int)($GM['wait'] ?? 8) ?>"></div>
          <div><label>حداکثرِ شرکت‌کننده در قرعه</label><input name="join_max" type="number" min="2" value="<?= (int)($GM['join_max'] ?? 50) ?>"></div>
        </div>
      </details>

      <div style="margin-top:16px"><button class="btn g">ذخیره تنظیماتِ بازی‌ها</button></div>
    </form>
  </div></div>

<?php // ================= 🏦 بانک ================= ?>
<?php elseif ($tab === 'bank'): ?>
  <?php $BK = bkCfg(); $BKR = $BK['rng']; ?>
  <div class="card"><h2>🏦 بانک — بازیِ سرقت/هک با همان الماس
    <?= !empty($BK['on']) ? '<span class="badge green">روشن</span>' : '<span class="badge">خاموش</span>' ?>
  </h2><div class="body">
    <div class="note">
      کیف‌پول همان الماسِ بخشِ «💎 الماس» است — بانک ارزِ جدیدی نیست، فقط بخشی از همان الماس که کاربر
      کنار گذاشته و قابلِ دزدیدن است. دستورها فقط داخلِ گروه کار می‌کنند: <code>/bank</code>،
      <code>/bankleader</code>، و هک با ریپلای‌کردنِ پیامِ هدف + نوشتنِ <code>/hack</code> یا کلمه‌ی هک.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bank">
      <input type="hidden" name="action" value="save_bank">

      <details class="subcard" open><summary><h3>🔌 وضعیت</h3></summary>
        <div style="margin-bottom:10px">
          <label style="font-weight:500"><input type="checkbox" name="bk_on" style="width:auto" <?= !empty($BK['on']) ? 'checked' : '' ?>> روشن باشد</label>
          <label style="font-weight:500"><input type="checkbox" name="bk_group_only" style="width:auto" <?= !empty($BK['group_only']) ? 'checked' : '' ?>> فقط داخلِ گروه کار کند</label>
        </div>
        <div class="grid2">
          <div><label>💬 کلمه‌ی هک (جایگزینِ /hack)</label><input name="word_hack" value="<?= h($BK['word_hack'] ?? 'هک') ?>"></div>
        </div>
      </details>

      <details class="subcard"><summary><h3>🏦 بانک و برداشت</h3></summary>
        <div class="grid2">
          <div><label>حداقلِ موجودیِ بانک برایِ باز شدنِ برداشت</label><input name="min_withdraw" value="<?= h($BK['min_withdraw'] ?? 50000) ?>" style="direction:ltr"></div>
          <div><label>⭐️ هر چند الماسِ دزدیده‌شده، یک سطحِ بانک</label><input name="level_step" type="number" min="1" value="<?= (int)($BK['level_step'] ?? 500000) ?>"></div>
          <div><label>🏆 تعدادِ نفراتِ لیستِ برترین‌ها</label><input name="top_n" type="number" min="1" value="<?= (int)($BK['top_n'] ?? 10) ?>"></div>
        </div>
      </details>

      <details class="subcard"><summary><h3>🛡 حفاظت و کول‌داون</h3></summary>
        <div class="grid2">
          <div><label>⏱ حفاظتِ دستی (ثانیه)</label><input name="manual_protect" type="number" min="60" value="<?= (int)($BK['manual_protect'] ?? 900) ?>"></div>
          <div><label>🛡 شیلدِ خودکار بعدِ هر هک (ثانیه)</label><input name="shield_after" type="number" min="0" value="<?= (int)($BK['shield_after'] ?? 300) ?>"></div>
          <div><label>⏳ کول‌داونِ هکِ همان مهاجم (ثانیه)</label><input name="hack_cooldown" type="number" min="60" value="<?= (int)($BK['hack_cooldown'] ?? 1200) ?>"></div>
        </div>
      </details>

      <details class="subcard"><summary><h3>🎲 موتورِ رند — شانس و درصدها</h3></summary>
        <div class="note">
          نتیجه‌ی هک هیچ‌وقت از رویِ یک عددِ ثابت تعیین نمی‌شود — این‌ها فقط بازه‌ها و وزن‌هایی‌اند که موتورِ
          رندِ امن (<code>random_int</code>) با جیترِ رندومِ خودش ترکیب‌شان می‌کند.
        </div>
        <div class="grid2">
          <div><label>٪ شانسِ پایه</label><input name="rng_base" value="<?= h($BKR['base_success'] ?? 42) ?>" style="direction:ltr"></div>
          <div><label>٪ کفِ شانسِ مؤثر</label><input name="rng_floor" value="<?= h($BKR['success_floor'] ?? 18) ?>" style="direction:ltr"></div>
          <div><label>٪ سقفِ شانسِ مؤثر</label><input name="rng_ceil" value="<?= h($BKR['success_ceil'] ?? 72) ?>" style="direction:ltr"></div>
          <div><label>٪ جیترِ رندومِ ± روی شانس</label><input name="rng_jitter" value="<?= h($BKR['jitter_pct'] ?? 12) ?>" style="direction:ltr"></div>
        </div>
        <div class="grid2">
          <div><label>٪ سهمِ JACKPOT</label><input name="rng_jackpot_pct" value="<?= h($BKR['jackpot_pct'] ?? 0.4) ?>" style="direction:ltr"></div>
          <div><label>٪ سهمِ PERFECT HEIST</label><input name="rng_perfect_pct" value="<?= h($BKR['perfect_pct'] ?? 7) ?>" style="direction:ltr"></div>
          <div><label>٪ سهمِ CRITICAL FAILURE</label><input name="rng_critfail_pct" value="<?= h($BKR['critfail_pct'] ?? 8) ?>" style="direction:ltr"></div>
          <div><label>سهمِ PARTIAL از موفقیت (۰ تا ۱)</label><input name="rng_partial_share" value="<?= h($BKR['partial_share'] ?? 0.35) ?>" style="direction:ltr"></div>
        </div>
        <div class="note">بازه‌ی درصدِ دزدیده‌شده از بانکِ هدف، برایِ هر تیر:</div>
        <div class="grid2">
          <div><label>🎯 Jackpot — از</label><input name="rng_jackpot_min" value="<?= h($BKR['jackpot_min'] ?? 25) ?>" style="direction:ltr"></div>
          <div><label>🎯 Jackpot — تا</label><input name="rng_jackpot_max" value="<?= h($BKR['jackpot_max'] ?? 40) ?>" style="direction:ltr"></div>
          <div><label>🟢 Perfect — از</label><input name="rng_perfect_min" value="<?= h($BKR['perfect_min'] ?? 10) ?>" style="direction:ltr"></div>
          <div><label>🟢 Perfect — تا</label><input name="rng_perfect_max" value="<?= h($BKR['perfect_max'] ?? 16) ?>" style="direction:ltr"></div>
          <div><label>🟢 Success — از</label><input name="rng_success_min" value="<?= h($BKR['success_min'] ?? 4) ?>" style="direction:ltr"></div>
          <div><label>🟢 Success — تا</label><input name="rng_success_max" value="<?= h($BKR['success_max'] ?? 10) ?>" style="direction:ltr"></div>
          <div><label>🟡 Partial — از</label><input name="rng_partial_min" value="<?= h($BKR['partial_min'] ?? 1) ?>" style="direction:ltr"></div>
          <div><label>🟡 Partial — تا</label><input name="rng_partial_max" value="<?= h($BKR['partial_max'] ?? 4) ?>" style="direction:ltr"></div>
          <div><label>💥 جریمه‌ی Critical Fail (٪ از بانکِ خودِ هکر) — از</label><input name="rng_critfail_min" value="<?= h($BKR['critfail_min'] ?? 5) ?>" style="direction:ltr"></div>
          <div><label>💥 جریمه‌ی Critical Fail — تا</label><input name="rng_critfail_max" value="<?= h($BKR['critfail_max'] ?? 15) ?>" style="direction:ltr"></div>
        </div>
      </details>

      <div style="margin-top:16px"><button class="btn g">ذخیره تنظیماتِ بانک</button></div>
    </form>

    <form method="post" style="margin-top:20px">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bank">
      <input type="hidden" name="action" value="save_bank_texts">
      <details class="subcard" open><summary><h3>✏️ متن‌ها و دکمه‌ها</h3></summary>
        <div class="note">
          داخلِ متن‌ها می‌توانید ایموجیِ پریمیوم هم بگذارید — کدش را از داخلِ خودِ ربات با
          <code>/panel</code> ← 🔘 ایموجیِ پریمیوم بگیرید و به‌شکلِ <code>&lt;tg-emoji emoji-id="..."&gt;✨&lt;/tg-emoji&gt;</code>
          داخلِ متن بچسبانید. برایِ خودِ سه‌تا دکمه، شناسه‌ی همان ایموجیِ پریمیوم را (بدونِ تگ، فقط عدد) در
          کادرِ «ایموجیِ دکمه» زیرِ همان دکمه بگذارید.
        </div>
        <?php foreach (bkDefaults()['texts'] as $k => $def): $isBtn = in_array($k, ['btn_protect','btn_deposit','btn_withdraw'], true); ?>
          <div style="margin-bottom:12px">
            <label><?= h($k) ?><?= $isBtn ? ' (متنِ دکمه)' : '' ?></label>
            <?php if ($isBtn): ?>
              <input name="txt_<?= h($k) ?>" value="<?= h($BK['texts'][$k] ?? $def) ?>">
              <div class="grid2" style="margin-top:6px">
                <input name="icon_<?= h($k) ?>" value="<?= h($BK['icons'][$k] ?? '') ?>" placeholder="شناسه‌ی ایموجیِ پریمیومِ این دکمه (اختیاری)" style="direction:ltr">
                <select name="color_<?= h($k) ?>">
                  <?php foreach (styleMap() as $sk => $sl): ?>
                    <option value="<?= h($sk) ?>" <?= ($BK['btns'][$k]['color'] ?? 'none') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php else: ?>
              <textarea name="txt_<?= h($k) ?>" rows="3" style="direction:rtl"><?= h($BK['texts'][$k] ?? $def) ?></textarea>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </details>
      <div style="margin-top:16px"><button class="btn g">ذخیره متن‌ها</button></div>
    </form>
  </div></div>

<?php // ================= 💣 مین‌یاب ================= ?>
<?php elseif ($tab === 'mine'): ?>
  <?php $MN = mnCfg(); ?>
  <div class="card"><h2>💣 مین‌یاب
    <?= !empty($MN['on']) ? '<span class="badge green">روشن</span>' : '<span class="badge">خاموش</span>' ?>
  </h2><div class="body">
    <div class="note">
      همین‌طور روی همان الماسِ بخشِ «💎 الماس» کار می‌کند. دستور: <code>مین ۵۰۰</code> (کلمه‌اش قابلِ تغییر است) —
      فقط داخلِ گروه.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="mine">
      <input type="hidden" name="action" value="save_mine">

      <details class="subcard" open><summary><h3>🔌 وضعیت</h3></summary>
        <div style="margin-bottom:10px">
          <label style="font-weight:500"><input type="checkbox" name="mn_on" style="width:auto" <?= !empty($MN['on']) ? 'checked' : '' ?>> روشن باشد</label>
          <label style="font-weight:500"><input type="checkbox" name="mn_group_only" style="width:auto" <?= !empty($MN['group_only']) ? 'checked' : '' ?>> فقط داخلِ گروه کار کند</label>
        </div>
        <div class="grid2">
          <div><label>💬 کلمه‌ی شروع (قبلِ عدد)</label><input name="mn_word" value="<?= h($MN['word'] ?? 'مین') ?>"></div>
        </div>
      </details>

      <details class="subcard"><summary><h3>💎 ورودی و بازی</h3></summary>
        <div class="grid2">
          <div><label>حداقلِ ورودی</label><input name="entry_min" value="<?= h($MN['entry_min'] ?? 100) ?>" style="direction:ltr"></div>
          <div><label>حداکثرِ ورودی</label><input name="entry_max" value="<?= h($MN['entry_max'] ?? 1000000) ?>" style="direction:ltr"></div>
          <div><label>حداقلِ خانه‌ی امن برایِ حفاظت از جریمه</label><input name="min_safe" type="number" min="0" max="8" value="<?= (int)($MN['min_safe_for_protection'] ?? 3) ?>"></div>
          <div><label>حداکثرِ بازی‌های فعالِ هم‌زمان (کلِ ربات)</label><input name="max_active" type="number" min="1" value="<?= (int)($MN['max_active_games'] ?? 200) ?>"></div>
          <div><label>مهلتِ بی‌کاری تا انقضایِ بازی (ثانیه)</label><input name="game_timeout" type="number" min="60" value="<?= (int)($MN['game_timeout'] ?? 1800) ?>"></div>
          <div><label>فاصله‌ی دو بازیِ همان کاربر (ثانیه، ۰=خاموش)</label><input name="game_cooldown" type="number" min="0" value="<?= (int)($MN['game_cooldown'] ?? 0) ?>"></div>
        </div>
        <div style="margin-top:10px">
          <label style="font-weight:500"><input type="checkbox" name="expire_refund" style="width:auto" <?= !empty($MN['expire_refund']) ? 'checked' : '' ?>> بعدِ انقضا، ورودی به کاربر برگردد</label>
        </div>
      </details>

      <details class="subcard"><summary><h3>🏆 جایزه‌ی هر خانه‌ی امن</h3></summary>
        <div class="note">هرکدام، جایزه‌ی همان شماره خانه‌ی امن است (نه تجمعی) — جمعِ رویِ‌همِ همه‌شان پرداخت می‌شود.</div>
        <div class="grid2">
          <?php $RW = $MN['rewards'] ?? mnDefaults()['rewards']; for ($i = 1; $i <= 8; $i++): ?>
            <div><label>🏆 خانه‌ی امنِ #<?= $i ?></label><input name="reward_<?= $i ?>" value="<?= h($RW[$i - 1] ?? 0) ?>" style="direction:ltr"></div>
          <?php endfor; ?>
        </div>
        <div class="grid2">
          <div><label>ضریبِ رشد برایِ فراترِ از ۸ (عملا پیش نمی‌آید)</label><input name="reward_growth" value="<?= h($MN['reward_growth'] ?? 1.5) ?>" style="direction:ltr"></div>
        </div>
      </details>

      <div style="margin-top:16px"><button class="btn g">ذخیره تنظیماتِ مین‌یاب</button></div>
    </form>

    <form method="post" style="margin-top:20px">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="mine">
      <input type="hidden" name="action" value="save_mine_texts">
      <details class="subcard" open><summary><h3>✏️ متن‌ها و دکمه‌ها</h3></summary>
        <div class="note">
          داخلِ متن‌ها ایموجیِ پریمیوم با <code>&lt;tg-emoji emoji-id="..."&gt;✨&lt;/tg-emoji&gt;</code> — کدش را از
          <code>/panel</code> ← 🔘 ایموجیِ پریمیوم بگیرید. برایِ خودِ دکمه‌ها، شناسه را (بدونِ تگ) در کادرِ زیرِ
          همان دکمه بگذارید و رنگش را هم از کنارش انتخاب کنید.
        </div>
        <?php foreach (mnDefaults()['texts'] as $k => $def): $isBtn = in_array($k, ['btn_field','btn_join','btn_cancel','btn_cash'], true); ?>
          <div style="margin-bottom:12px">
            <label><?= h($k) ?><?= $isBtn ? ' (متنِ دکمه)' : '' ?></label>
            <?php if ($isBtn): ?>
              <input name="txt_<?= h($k) ?>" value="<?= h($MN['texts'][$k] ?? $def) ?>">
              <div class="grid2" style="margin-top:6px">
                <input name="icon_<?= h($k) ?>" value="<?= h($MN['icons'][$k] ?? '') ?>" placeholder="شناسه‌ی ایموجیِ پریمیومِ این دکمه (اختیاری)" style="direction:ltr">
                <select name="color_<?= h($k) ?>">
                  <?php foreach (styleMap() as $sk => $sl): ?>
                    <option value="<?= h($sk) ?>" <?= ($MN['btns'][$k]['color'] ?? 'none') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php else: ?>
              <textarea name="txt_<?= h($k) ?>" rows="3" style="direction:rtl"><?= h($MN['texts'][$k] ?? $def) ?></textarea>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </details>
      <div style="margin-top:16px"><button class="btn g">ذخیره متن‌ها</button></div>
    </form>
  </div></div>

<?php else: ?>
  <div class="crumb">سیستم <span>/</span> <b>تنظیمات</b></div>
  <?php // 🧩 پنج بخشِ زیر همه یک فرم/یک submit هستند (همان چیزی که قبلا بود) —
        // فقط ظاهرا در کارت‌های جدا نشان داده می‌شوند تا صفحه‌ی بلندِ قبلی خرد شود.
        // این‌طور، هیچ فیلدی از فرم‌های دیگر جا نمی‌ماند و چیزی خالی ذخیره نمی‌شود. ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="settings">
    <input type="hidden" name="action" value="save_settings">
    <input type="hidden" name="adv_scope" value="1">

    <details class="card"><summary><h2 style="border:0;padding:0;background:none">💳 پرداخت</h2></summary><div class="body">
      <div class="grid2">
        <div><label>آدرس USDT (TRC20)</label><input name="usdt" value="<?= h($C['wallets']['usdt']) ?>" style="direction:ltr"></div>
        <div><label>آدرس TRX</label><input name="trx" value="<?= h($C['wallets']['trx']) ?>" style="direction:ltr"></div>
        <div><label>شماره کارت</label><input name="card" value="<?= h($C['wallets']['card']) ?>" style="direction:ltr"></div>
        <div><label>به نام</label><input name="card_name" value="<?= h($C['wallets']['card_name']) ?>"></div>
      </div>
    </div></details>

    <details class="card"><summary><h2 style="border:0;padding:0;background:none">👥 زیرمجموعه‌گیری (رفرال)</h2></summary><div class="body">
      <div class="grid2">
        <div><label>درصد پورسانت</label><input name="ref_percent" type="number" min="0" max="100" step="0.5"
             value="<?= h($C['referral']['percent']) ?>"></div>
        <div><label>&nbsp;</label><label style="font-weight:500">
          <input type="checkbox" name="ref_on" style="width:auto" <?= !empty($C['referral']['on']) ? 'checked' : '' ?>>
          سیستم معرفی فعال باشد</label></div>
      </div>
    </div></details>

    <details class="card"><summary><h2 style="border:0;padding:0;background:none">🤖 پیش‌فرض ربات‌های اپلودر</h2></summary><div class="body">
      <p class="muted" style="margin-bottom:10px">این مقادیر روی ربات‌های <b>جدید</b> اعمال می‌شود. برای ربات‌های موجود از تب «ربات‌های اپلودر» استفاده کنید.</p>
      <div class="grid2">
        <div><label>⏱ حذف فایل بعد از (ثانیه)</label>
          <input name="del_sec" type="number" min="5" value="<?= (int)$C['uploader']['delete_seconds'] ?>"></div>
        <div><label>گزینه‌ها</label>
          <label style="font-weight:500"><input type="checkbox" name="force_join" style="width:auto"
            <?= !empty($C['uploader']['force_join']) ? 'checked' : '' ?>> 🔒 عضویت اجباری</label>
          <label style="font-weight:500"><input type="checkbox" name="protect" style="width:auto"
            <?= !empty($C['uploader']['protect_content']) ? 'checked' : '' ?>> 🛡 محافظت فایل</label></div>
      </div>
    </div></details>

    <details class="card"><summary><h2 style="border:0;padding:0;background:none">🧪 تست و نمایش</h2></summary><div class="body">
      <div class="note">
        <b>حالت تست</b> اجازه می‌دهد سفارش با مبلغ <b>صفر</b> تا آخر برود — بدون پرداخت،
        خودکار تایید می‌شود و کمپین قفل کانالش هم ساخته می‌شود.
        برای امتحان کردن کل مسیر قبل از قیمت‌گذاری. <b>یادتان باشد بعد خاموشش کنید.</b>
      </div>
      <div style="margin-top:10px">
        <label style="font-weight:500"><input type="checkbox" name="test_mode" style="width:auto"
          <?= !empty($C['test_mode']) ? 'checked' : '' ?>> 🧪 حالت تست — سفارش با ۰ ریال مجاز باشد</label>
        <label style="font-weight:500"><input type="checkbox" name="speed_perday" style="width:auto"
          <?= !empty($C['ui']['speed_show_perday']) ? 'checked' : '' ?>>
          🚀 «نفر در روز» روی دکمه سرعت هم نوشته شود</label>
      </div>
    </div></details>

    <details class="card"><summary><h2 style="border:0;padding:0;background:none">🤖 کار خودکار</h2></summary><div class="body">
      <div class="note">
        با <b>درگاه پرداخت</b> همه چیز از قبل خودکار است و نیازی به حضور شما نیست.
        گزینه زیر فقط برای <b>رسید کارت به کارت</b> است: رسید که برسد، بدون بررسی تایید می‌شود.
        ⚠️ ریسک دارد — فقط اگر می‌دانید چه می‌کنید.
      </div>
      <div style="margin-top:10px">
        <label style="font-weight:500"><input type="checkbox" name="auto_approve" style="width:auto"
          <?= !empty($C['auto_approve']) ? 'checked' : '' ?>>
          🤖 تایید خودکار رسیدهای کارت به کارت</label>
      </div>
      <div style="margin-top:10px;max-width:320px"><label>🧹 پاک کردن کمپین‌های تمام‌شده بعد از (روز · ۰ = هیچ‌وقت)</label>
        <input name="keep_days" type="number" min="0" value="<?= (int)($C['campaign_keep_days'] ?? 3) ?>"></div>
    </div></details>

    <div class="card"><div class="body"><button class="btn g">💾 ذخیره تغییرات</button></div></div>
  </form>

  <details class="card"><summary><h2 style="border:0;padding:0;background:none">🩺 تشخیص و سرعت</h2></summary><div class="body">
    <div class="note">
      این ابزارها قبلا فقط داخل <code>/panel</code> ربات بودند؛ هرکدام یک گزارشِ لحظه‌ای می‌سازد —
      نتیجه همین‌جا بالای صفحه نشان داده می‌شود.
    </div>
    <div class="grid2" style="margin-top:12px">
      <form method="post"><input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="tab" value="settings"><input type="hidden" name="action" value="adm_auto_setup">
        <button class="btn">🔧 راه‌اندازی خودکار</button></form>
      <form method="post"><input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="tab" value="settings"><input type="hidden" name="action" value="adm_speed_test">
        <button class="btn">⚡️ سرعت ربات</button></form>
      <form method="post"><input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="tab" value="settings"><input type="hidden" name="action" value="adm_leak_test">
        <button class="btn">🔒 تست نشتی داده</button></form>
      <form method="post"><input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="tab" value="settings"><input type="hidden" name="action" value="adm_write_test">
        <button class="btn">🩺 تست نوشتن روی دیسک</button></form>
    </div>
  </div></details>

  <?php $G = cfg()['gateway'] ?? []; $J = cfg()['join'] ?? []; ?>

  <details class="card"><summary><h2 style="border:0;padding:0;background:none">💠 درگاه پرداخت خودکار <?= (!empty($G['on']) && trim((string)$G['api_key']) !== '' && trim((string)$G['base_url']) !== '') ? '<span class="badge green">آماده</span>' : '<span class="badge">خاموش</span>' ?></h2></summary><div class="body">
    <div class="note">
      مشتری «افزایش موجودی» می‌زند → ربات از درگاه یک <b>لینک پرداخت + آدرس ولت + مهلت</b> می‌گیرد →
      به‌محض واریز، درگاه به ربات خبر می‌دهد و کیف پول <b>خودکار</b> شارژ می‌شود.
      پول مستقیم به ولت خودتان در پنل درگاه می‌رود و از همان‌جا برداشت می‌کنید.
      <br><br>
      <b>راه‌اندازی:</b> در <a href="https://oxapay.com" target="_blank" rel="noopener">OxaPay</a> یا
      <a href="https://nowpayments.io" target="_blank" rel="noopener">NOWPayments</a> حساب بسازید،
      آدرس ولت خودتان را آنجا ثبت کنید، کلید API (Merchant Key) را بگیرید و اینجا بگذارید.
      بعد در پنل همان سایت، آدرس <b>Callback / IPN</b> را روی آدرس زیر بگذارید.
    </div>

    <?php $cbUrl = trim((string)($G['base_url'] ?? '')) !== '' ? gwCallbackUrl() : ''; ?>
    <?php if ($cbUrl): ?>
      <div class="note" style="margin-top:10px">
        📡 <b>آدرس Callback:</b> <code style="direction:ltr;display:inline-block"><?= h($cbUrl) ?></code>
      </div>
    <?php endif; ?>

    <form method="post" style="margin-top:12px">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="settings">
      <input type="hidden" name="action" value="save_gateway">
      <div style="margin-bottom:10px">
        <label style="font-weight:500"><input type="checkbox" name="gw_on" style="width:auto"
          <?= !empty($G['on']) ? 'checked' : '' ?>> درگاه خودکار روشن باشد</label>
      </div>
      <div class="grid2">
        <div><label>سرویس</label><select name="gw_prov">
          <?php foreach (['oxapay'=>'OxaPay','nowpayments'=>'NOWPayments','custom'=>'دلخواه'] as $k2=>$v2): ?>
            <option value="<?= h($k2) ?>" <?= ($G['provider'] ?? 'oxapay') === $k2 ? 'selected' : '' ?>><?= h($v2) ?></option>
          <?php endforeach; ?></select></div>
        <div><label>کلید API (Merchant Key)</label>
          <div class="secret"><input type="password" name="gw_key" autocomplete="off" value="<?= h($G['api_key'] ?? '') ?>" style="direction:ltr">
            <button type="button" onclick="toggleSecret(this)">👁</button></div></div>
        <div><label>کلید IPN Secret (فقط NOWPayments)</label>
          <div class="secret"><input type="password" name="gw_ipn" autocomplete="off" value="<?= h($G['ipn_secret'] ?? '') ?>" style="direction:ltr">
            <button type="button" onclick="toggleSecret(this)">👁</button></div></div>
        <div><label>آدرس عمومی فایل ربات</label>
          <input name="gw_base" value="<?= h($G['base_url'] ?? '') ?>" placeholder="https://site.com/bot.php" style="direction:ltr"></div>
        <div><label>ارز</label><input name="gw_coin" value="<?= h($G['coin'] ?? 'USDT') ?>" style="direction:ltr"></div>
        <div><label>شبکه</label><input name="gw_net" value="<?= h($G['network'] ?? '') ?>" placeholder="TRC20" style="direction:ltr"></div>
        <div><label>نرخ: هر ۱ واحد چند تومان؟ (۰ = تبدیل با خود درگاه)</label>
          <input name="gw_rate" value="<?= h((float)($G['rate'] ?? 0)) ?>" style="direction:ltr"></div>
        <div><label>مهلت هر فاکتور (دقیقه)</label>
          <input name="gw_exp" type="number" min="5" value="<?= (int)($G['expire'] ?? 30) ?>"></div>
        <div><label>حداقل شارژ با درگاه (تومان)</label>
          <input name="gw_min" value="<?= h((float)($G['min'] ?? 0)) ?>" style="direction:ltr"></div>
        <div><label>آدرس دلخواه (حالت custom)</label>
          <input name="gw_curl" value="<?= h($G['custom_url'] ?? '') ?>" placeholder="https://…?amount={amount}&order={order}&cb={callback}" style="direction:ltr"></div>
      </div>
      <div class="muted" style="margin-top:8px">
        زیر حداقل مبلغ، همان کارت به کارت با رسید استفاده می‌شود. اگر درگاه جواب ندهد هم خودکار به کارت به کارت برمی‌گردد.
      </div>
      <div style="margin-top:14px"><button class="btn g">ذخیره درگاه</button></div>
    </form>
  </div></details>

  <details class="card"><summary><h2 style="border:0;padding:0;background:none">🔒 عضویت اجباری ربات مادر <?= !empty($J['on']) ? '<span class="badge green">روشن</span>' : '<span class="badge">خاموش</span>' ?></h2></summary><div class="body">
    <div class="note">
      تا کاربر عضو کانال‌های زیر نشود، نمی‌تواند از ربات فروشگاه استفاده کند.
      ربات مادر باید در هر کانال <b>ادمین</b> باشد. خودتان هیچ‌وقت پشت این قفل نمی‌مانید.
    </div>

    <table style="margin-top:12px">
      <tr><th>#</th><th>کانال</th><th>آیدی</th><th>لینک</th><th></th></tr>
      <?php foreach (($J['channels'] ?? []) as $i => $c2): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= h($c2['title'] ?? '—') ?></td>
          <td><code><?= h($c2['chat_id'] ?? '') ?></code></td>
          <td><?= !empty($c2['url']) ? '<a href="' . h($c2['url']) . '" target="_blank" rel="noopener">باز کردن</a>' : '<span class="muted">—</span>' ?></td>
          <td>
            <form method="post" style="margin:0">
              <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="settings">
              <input type="hidden" name="action" value="del_join_channel"><input type="hidden" name="i" value="<?= $i ?>">
              <button class="btn r">حذف</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($J['channels'])): ?>
        <tr><td colspan="5" class="muted">هنوز کانالی اضافه نکرده‌اید.</td></tr>
      <?php endif; ?>
    </table>

    <form method="post" style="margin-top:12px">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="settings">
      <input type="hidden" name="action" value="add_join_channel">
      <div class="grid2">
        <div><label>آیدی کانال</label><input name="chat_id" required placeholder="@mychannel یا -100..." style="direction:ltr"></div>
        <div><label>عنوان (خالی = از خود کانال)</label><input name="title"></div>
        <div><label>لینک عضویت (خالی = خودکار)</label><input name="url" style="direction:ltr"></div>
      </div>
      <div style="margin-top:12px"><button class="btn g">افزودن کانال</button></div>
    </form>

    <form method="post" style="margin-top:16px">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="settings">
      <input type="hidden" name="action" value="save_join">
      <div style="margin-bottom:10px">
        <label style="font-weight:500"><input type="checkbox" name="jn_on" style="width:auto"
          <?= !empty($J['on']) ? 'checked' : '' ?>> قفل عضویت روشن باشد</label>
      </div>
      <div class="muted">📝 متنِ قفل و متنِ دکمه فقط داخل خودِ ربات ویرایش می‌شوند: <code>/panel</code> ← 🔒 عضویت اجباری</div>
      <div style="margin-top:14px"><button class="btn g">ذخیره</button></div>
    </form>
  </div></details>

  <details class="card"><summary><h2 style="border:0;padding:0;background:none">🔐 امنیت</h2></summary><div class="body">
    <p class="muted" style="line-height:2">
      • رمز پنل در خط ۱۰ فایل <code>admin_panel.php</code><br>
      • آیدی مدیرها: <code><?= h(implode('، ', ADMIN_IDS)) ?></code> — کنار BOT_TOKEN در config.local.php<br>
      • کلید کران:
        <span class="secret-box">
          <code data-shown="0" data-masked="••••••••••••"><?= h(str_repeat('•', 12)) ?></code>
          <button type="button" onclick="revealBox(this,'<?= h(addslashes(CRON_KEY)) ?>')">👁 نمایش</button>
          <button type="button" onclick="copyText('<?= h(addslashes(CRON_KEY)) ?>',this)">📋 کپی</button>
        </span>
        — خط ۲۸ فایل ربات، حتما عوضش کنید<br>
      • پوشه <code>data_master/</code> شامل توکن ربات‌هاست؛ دسترسی عمومی به آن را ببندید
    </p>
  </div></details>
<?php endif; ?>

  </div></main>
</div>

<div class="modal-backdrop" id="confirmModal" role="dialog" aria-modal="true" aria-labelledby="cmTitle">
  <div class="modal">
    <h3 id="cmTitle"></h3>
    <div id="cmBody"></div>
    <div class="mact">
      <button type="button" class="btn ghost" onclick="closeConfirm()">انصراف</button>
      <button type="button" class="btn g" id="cmOk">تایید</button>
    </div>
  </div>
</div>

<script>
// ---- 🪟 مودالِ تاییدِ عملیاتِ مهم — فقط UI؛ خودِ فرم همچنان با POST و CSRF می‌رود ----
var _cmForm = null;
function openConfirm(form, title, rows, warnText, okClass) {
  _cmForm = form;
  document.getElementById('cmTitle').textContent = title;
  var html = '';
  (rows || []).forEach(function (r) {
    html += '<div class="mrow"><span>' + r[0] + '</span><b>' + r[1] + '</b></div>';
  });
  if (warnText) html += '<div class="mwarn">⚠️ ' + warnText + '</div>';
  document.getElementById('cmBody').innerHTML = html;
  var ok = document.getElementById('cmOk');
  ok.className = 'btn ' + (okClass || 'g');
  document.getElementById('confirmModal').classList.add('on');
}
function closeConfirm() {
  document.getElementById('confirmModal').classList.remove('on');
  _cmForm = null;
}
document.getElementById('confirmModal').addEventListener('click', function (e) {
  if (e.target === this) closeConfirm();
});
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') closeConfirm();
});
document.getElementById('cmOk').onclick = function () {
  if (!_cmForm) return;
  this.disabled = true; this.textContent = '🔄 در حال انجام...';
  _cmForm.submit();
};

// ---- 🧲 گریدِ چیدمانِ دستی — رنگِ خانه بسته به خالی/پر بودن ----
function gridSlotChanged(sel) {
  sel.classList.toggle('filled', sel.value !== '');
}

// ---- 🔎 جستجوی زنده‌ی محصولات — فقط نمایش/عدم‌نمایش، بدون درخواستِ تازه ----
function filterProducts(q) {
  q = q.trim().toLowerCase();
  document.querySelectorAll('.prodcard').forEach(function (c) {
    var name = c.getAttribute('data-pname') || '';
    c.classList.toggle('filtered-out', q !== '' && name.indexOf(q) === -1);
  });
}

// ---- 🔐 نمایش/مخفیِ فیلدهای سکرت ----
function toggleSecret(btn) {
  var inp = btn.previousElementSibling;
  if (!inp) return;
  inp.type = inp.type === 'password' ? 'text' : 'password';
  btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}
function revealBox(btn, full) {
  var code = btn.previousElementSibling;
  var showing = code.getAttribute('data-shown') === '1';
  code.textContent = showing ? code.getAttribute('data-masked') : full;
  code.setAttribute('data-shown', showing ? '0' : '1');
  btn.textContent = showing ? '👁 نمایش' : '🙈 مخفی';
}
function copyText(text, btn) {
  var done = function () { var old = btn.textContent; btn.textContent = '✓ کپی شد'; setTimeout(function () { btn.textContent = old; }, 1400); };
  if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done); return; }
  var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
  document.body.appendChild(ta); ta.select();
  try { document.execCommand('copy'); done(); } catch (e) {}
  document.body.removeChild(ta);
}

// ---- 🔄 وضعیتِ «در حالِ انجام» روی دکمه‌های فرم — از دوبار کلیک جلوگیری می‌کند ----
// اگر همان فرم یک onsubmit قدیمی (confirm بومی) دارد و کاربر «لغو» بزند،
// آن رویداد را preventDefault می‌کند و اینجا دیگر دکمه را قفل نمی‌کنیم.
document.querySelectorAll('form').forEach(function (f) {
  f.addEventListener('submit', function (ev) {
    if (ev.defaultPrevented) return;
    var btn = f.querySelector('button[type="submit"], button:not([type])');
    if (!btn || btn.disabled) return;
    btn.disabled = true;
    btn.innerHTML = '🔄 در حال انجام...';
  });
});

// ---- 🍞 بستنِ خودکارِ پیامِ فلش (فقط توستِ لحظه‌ای، نه هشدارهای همیشگی مثل رمزِ ضعیف) ----
(function () {
  var f = document.getElementById('flashToast');
  if (!f) return;
  setTimeout(function () { if (f) f.style.display = 'none'; }, 7000);
})();

// انتخاب متن را داخل تگ می‌پیچد (نقل‌قول، پررنگ، ...)
function premEmoji(id) {
  var code = prompt('کد ایموجی پریمیوم را بگذارید:\n(با دستور /emoji در ربات مادر می‌گیرید)');
  if (!code) return;
  code = code.trim();
  if (!/^[0-9]+$/.test(code)) { alert('کد باید فقط عدد باشد.'); return; }
  wrapSel(id, '<tg-emoji emoji-id="' + code + '">', '</tg-emoji>');
}
function resetTpl(id, text) {
  if (!confirm('متنِ فعلی پاک و با پیش‌فرض جایگزین شود؟')) return;
  var el = document.getElementById(id);
  if (!el) return;
  el.value = text;
  el.focus();
}
// فیلترِ زنده‌ی سلکت‌های سرویسِ پنلِ SMM — پنل‌ها معمولاً هزاران سرویس دارند،
// پس سلکت از رویِ data-options دوباره ساخته می‌شود تا فقط موارد منطبق بمانند.
function smmFilterSelect(input) {
  var sel = input.nextElementSibling;
  if (!sel || sel.tagName !== 'SELECT') return;
  var opts;
  try { opts = JSON.parse(sel.getAttribute('data-options') || '[]'); } catch (e) { opts = []; }
  var q = input.value.trim().toLowerCase();
  var cur = sel.value;
  var html = '<option value="">' + (sel.getAttribute('data-empty') || '') + '</option>';
  var foundCur = (cur === '');
  opts.forEach(function (o) {
    var v = String(o.v), t = String(o.t);
    if (q !== '' && t.toLowerCase().indexOf(q) === -1 && v.indexOf(q) === -1) return;
    if (v === cur) foundCur = true;
    html += '<option value="' + v.replace(/"/g, '&quot;') + '"' + (v === cur ? ' selected' : '') + '>' +
            t.replace(/</g, '&lt;') + '</option>';
  });
  // انتخابِ فعلی هیچ‌وقت با فیلترکردن گم نشود، حتی اگر با متنِ جستجو جور نباشد
  if (!foundCur) {
    var match = opts.find(function (o) { return String(o.v) === cur; });
    html += '<option value="' + cur.replace(/"/g, '&quot;') + '" selected>' +
            (match ? String(match.t).replace(/</g, '&lt;') : 'سرویسِ فعلی (' + cur + ') — دیگر در لیست نیست') +
            '</option>';
  }
  sel.innerHTML = html;
}
function wrapSel(id, open, close) {
  var el = document.getElementById(id);
  if (!el) return;
  var s = el.selectionStart, e = el.selectionEnd, v = el.value;
  var sel = v.substring(s, e) || 'متن';
  el.value = v.substring(0, s) + open + sel + close + v.substring(e);
  el.focus();
  el.selectionStart = s + open.length;
  el.selectionEnd   = s + open.length + sel.length;
}
</script>
</body>
</html>
