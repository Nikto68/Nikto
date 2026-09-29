<?php
// خطاهای PHP به کاربر نشان داده نشود (مسیرِ سرور و جزئیات لو نرود) — فقط در لاگِ سرور
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

if (is_file(__DIR__ . '/config.local.php')) require_once __DIR__ . '/config.local.php';

if (!defined('ADMIN_PASSWORD'))
    define('ADMIN_PASSWORD', defined('ADMIN_PANEL_PASS')
        ? (string)ADMIN_PANEL_PASS : (string)getenv('ADMIN_PANEL_PASS'));

if (strlen(ADMIN_PASSWORD) < 6) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("رمزِ پنل تنظیم نشده است.\n\nدر config.local.php:\ndefine('ADMIN_PANEL_PASS', 'رمز شما');\n");
}

define('MEMBERSHIP_LIB_ONLY', true);

require_once __DIR__ . '/bot_master_membership.php';

session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true,
    'samesite' => 'Strict', 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')]);
session_name('mybot_panel');
session_start();
// پنل نباید داخلِ iframeِ سایتِ دیگری باز شود (clickjacking) و آدرسش به سایت‌های بیرونی لو نرود
header('X-Frame-Options: DENY');
header("Content-Security-Policy: frame-ancestors 'none'");
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

$setupErr = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['password'])) {
    // همان قفلِ پنلِ وب: چند تلاشِ ناموفق = بسته شدنِ ورود (این‌جا راهِ دورزدنِ قفل نیست)
    if (($left = panelLockLeft()) > 0) {
        $setupErr = 'به‌خاطر تلاش‌های ناموفق، ورود تا ' . ceil($left / 60) . ' دقیقه دیگر بسته است.';
    } elseif (hash_equals(ADMIN_PASSWORD, panelPassIn($_POST['password']))) {
        panelClearFails();
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['seen'] = time();
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
    } else {
        panelNoteFail();
        usleep(400000);
        $setupErr = panelLockLeft() > 0 ? 'رمز اشتباه بود؛ ورود چند دقیقه بسته شد.' : 'رمز اشتباه است.';
    }
}
if (empty($_SESSION['logged_in'])) {
    ?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1"><title>راه‌اندازی</title>
    <style>body{background:#0b0f17;color:#e8edf7;font-family:system-ui,Tahoma;display:grid;
    place-items:center;min-height:100vh;margin:0}form{background:#141a26;padding:28px;border-radius:18px;
    border:1px solid #232c3d;width:min(92vw,340px)}h1{font-size:17px;margin:0 0 16px}
    input{width:100%;padding:12px;border-radius:10px;border:1px solid #2b3648;background:#0e131d;
    color:#fff;box-sizing:border-box;font-family:inherit}button{width:100%;margin-top:12px;padding:12px;
    border:0;border-radius:10px;background:#3b82f6;color:#fff;font-weight:700;font-family:inherit;
    font-size:14px;cursor:pointer}</style></head><body>
    <form method="post"><h1>🚑 راه‌اندازی و عیب‌یابی</h1>
    <?php if ($setupErr !== ''): ?><p style="color:#fca5a5;font-size:13px"><?= htmlspecialchars($setupErr, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <input type="password" name="password" placeholder="رمز پنل" autofocus>
    <button>ورود</button></form></body></html><?php
    exit;
}

$CSRF = $_SESSION['csrf'] ?? '';
function ok_($b) { return $b ? '<span class="ok">✅</span>' : '<span class="no">🔴</span>'; }
function esc_($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function setupBase() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    return $scheme . '://' . $host . $dir;
}

$flash = '';
$BASE  = setupBase();
$MAIN  = $BASE . '/bot_master_membership.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('csrf'); }
    $a = (string)($_POST['a'] ?? '');

    if ($a === 'hook_main') {
        if (WEBHOOK_SECRET === '') {
            $flash = '🔴 اول <code>WEBHOOK_SECRET</code> را در config.local.php بگذارید — بدونش ربات هیچ پیامی را قبول نمی‌کند.';
        } else {
            $r = tg(BOT_TOKEN, 'setWebhook', [
                'url' => $MAIN, 'drop_pending_updates' => 'true',
                'allowed_updates' => json_encode(['message', 'callback_query', 'my_chat_member']),
                'secret_token' => WEBHOOK_SECRET,
            ]);
            if (!empty($r['ok'])) {
                $healed = maHealUrls(true);
                maMenuSync();
                $flash  = '✅ وبهوکِ ربات ست شد. حالا در تلگرام <code>/start</code> بزنید.'
                        . ($healed ? '<br>🩹 آدرسِ ' . esc_(implode(' و ', $healed)) . ' هم با همین پوشه یکی شد.' : '');
            } else {
                $flash = '🔴 ست نشد: ' . esc_($r['description'] ?? 'خطای ناشناخته');
            }
        }
    }

    if ($a === 'heal') {
        $healed = maHealUrls(true);
        maMenuSync();
        $flash  = $healed
            ? '✅ آدرسِ ' . esc_(implode(' و ', $healed)) . ' با همین پوشه یکی شد.'
            : '✅ همه‌ی آدرس‌ها از قبل درست بودند.';
    }

    if ($a === 'hook_del') {
        $r = tg(BOT_TOKEN, 'deleteWebhook', ['drop_pending_updates' => 'true']);
        $flash = !empty($r['ok']) ? '✅ وبهوک برداشته شد.' : '🔴 ' . esc_($r['description'] ?? '');
    }
}

$me   = tg(BOT_TOKEN, 'getMe', [], 10);
$meOk = !empty($me['ok']);
$meUn = (string)($me['result']['username'] ?? '');

$wh   = tg(BOT_TOKEN, 'getWebhookInfo', [], 10);
$whR  = is_array($wh['result'] ?? null) ? $wh['result'] : [];
$whUrl = (string)($whR['url'] ?? '');
$whErr = trim((string)($whR['last_error_message'] ?? ''));
$whPend = (int)($whR['pending_update_count'] ?? 0);
$whMatch = ($whUrl !== '' && rtrim($whUrl, '/') === rtrim($MAIN, '/'));

$catalog = maCatalogPublic();
$maCats  = count($catalog['cats']);
$maItems = count($catalog['items']);

$apiUrl  = $MAIN . (str_contains($MAIN, '?') ? '&' : '?') . 'mapi=1';
$apiCode = 0; $apiJson = false; $apiSnip = '';
$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode(['action' => 'me', 'initData' => 'x']),
]);
$apiBody = (string)curl_exec($ch);
$apiCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$apiDec  = json_decode($apiBody, true);
$apiJson = is_array($apiDec);
$apiSnip = mb_substr(trim($apiBody), 0, 120);

$supTo = maSupChat();
$supIsAdmin = ($supTo !== '' && defined('ADMIN_ID') && (string)$supTo === (string)ADMIN_ID);

$dataOk = is_dir(DATA_DIR) && is_writable(DATA_DIR);
$files  = ['admin_panel.php', 'miniapps.php', 'miniapp_view.php', 'numbers.php', 'prices.php'];

$leak = null;
$probe = DATA_DIR . '/.leaktest';
@file_put_contents($probe, 'x');
if (is_file($probe)) {
    $rel = @realpath(DATA_DIR);
    $doc = @realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($rel && $doc && str_starts_with($rel, $doc)) {
        $u = $BASE . '/' . basename(DATA_DIR) . '/.leaktest';
        $ch = curl_init($u);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6,
                                CURLOPT_SSL_VERIFYPEER => false]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $leak = ($code === 200 && trim((string)$body) === 'x');
    }
    @unlink($probe);
}
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>راه‌اندازی — نامبیکس</title>
<style>
*{box-sizing:border-box}
body{background:#0b0f17;color:#e8edf7;font-family:system-ui,Tahoma;margin:0;padding:18px;line-height:1.85}
.wrap{max-width:820px;margin:0 auto}
h1{font-size:19px;margin:0 0 4px}
.sub{color:#8b97ad;font-size:12.5px;margin-bottom:18px}
.card{background:#141a26;border:1px solid #232c3d;border-radius:16px;padding:18px;margin-bottom:14px}
.card h2{font-size:15px;margin:0 0 12px;display:flex;align-items:center;gap:8px}
table{width:100%;border-collapse:collapse;font-size:13px}
td{padding:7px 4px;border-bottom:1px solid #1d2532;vertical-align:top}
td:first-child{color:#8b97ad;width:42%}
.ok{color:#22c55e}.no{color:#ef4444}.warn{color:#f59e0b}
code{background:#0e131d;padding:2px 6px;border-radius:6px;font-size:11.5px;
  word-break:break-all;direction:ltr;display:inline-block;font-family:ui-monospace,monospace}
.btn{display:inline-block;padding:11px 16px;border:0;border-radius:11px;background:#3b82f6;color:#fff;
  font-weight:700;cursor:pointer;font-family:inherit;font-size:13px;margin:4px 4px 0 0}
.btn.g{background:#22c55e}.btn.r{background:#ef4444}
.flash{background:#132033;border:1px solid #2b4a6d;border-radius:12px;padding:13px;margin-bottom:14px;font-size:13.5px}
.fix{background:#1a1408;border:1px solid #573f06;border-radius:12px;padding:13px;margin-top:12px;font-size:13px}
a{color:#60a5fa}
</style></head><body><div class="wrap">
<h1>🚑 راه‌اندازی و عیب‌یابی</h1>
<div class="sub">هرچه ربات لازم دارد تا کار کند، همین‌جا چک و درست می‌شود.</div>

<?php if ($flash): ?><div class="flash"><?= $flash ?></div><?php endif; ?>

<div class="card" style="border-color:<?= $meOk ? '#1e5e3a' : '#7f1d1d' ?>;
     background:<?= $meOk ? '#0f1f17' : '#1f1010' ?>">
  <h2 style="margin:0 0 8px">🤖 توکنِ داخلِ config.local.php مالِ این ربات است:</h2>
  <?php if ($meOk): ?>
    <div style="font-size:22px;font-weight:800;direction:ltr;text-align:left;margin:6px 0 10px">
      @<?= esc_($meUn) ?>
    </div>
    <div style="font-size:13.5px;color:#cbd5e1">
      ⚠️ در تلگرام <b>فقط با همین ربات</b> حرف بزن. اگر داری به رباتِ دیگری
      <code>/start</code> می‌زنی، هرچقدر هم بزنی جواب نمی‌گیری — چون این تنظیمات
      مالِ <b>@<?= esc_($meUn) ?></b> است، نه آن یکی.
      <br><br>
      رباتِ دیگری می‌خواهی؟ توکنش را در <code>config.local.php</code> خطِ
      <code>BOT_TOKEN</code> بگذار، بعد همین صفحه را تازه کن و دکمه‌ی
      «ست کردنِ وبهوک» را بزن.
    </div>
  <?php else: ?>
    <div style="font-size:15px;font-weight:700;color:#fca5a5">
      🔴 تلگرام این توکن را نشناخت.
    </div>
    <div style="font-size:13.5px;color:#cbd5e1;margin-top:8px">
      یعنی توکنِ داخلِ <code>config.local.php</code> یا اشتباه کپی شده، یا
      از BotFather باطل (revoke) شده. یک توکنِ درست بگذار و صفحه را تازه کن.
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>۱) خودِ ربات</h2>
  <table>
    <tr><td>توکن معتبر است؟</td><td><?= ok_($meOk) ?> <?= $meOk ? '@' . esc_($meUn) : 'تلگرام توکن را نشناخت' ?></td></tr>
    <tr><td>SQLite3</td><td><?= ok_(class_exists('SQLite3')) ?></td></tr>
    <tr><td>GD (ساخت کارت)</td><td><?= ok_(function_exists('imagecreatetruecolor')) ?></td></tr>
    <tr><td>WEBHOOK_SECRET</td><td><?= ok_(WEBHOOK_SECRET !== '') ?> <?= WEBHOOK_SECRET !== '' ? 'تنظیم شده' : 'تنظیم نشده — ربات هیچ پیامی را قبول نمی‌کند' ?></td></tr>
    <?php foreach ($files as $f): ?>
      <tr><td><?= esc_($f) ?></td><td><?= ok_(is_file(__DIR__ . '/' . $f)) ?></td></tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="card">
  <h2>۲) پوشه‌ی داده</h2>
  <table>
    <tr><td>مسیر</td><td><code><?= esc_(DATA_DIR) ?></code></td></tr>
    <tr><td>هست و نوشتنی است؟</td><td><?= ok_($dataOk) ?> <?= $dataOk ? '' : 'دسترسی را ۷۵۵ یا ۷۷۵ کنید' ?></td></tr>
    <tr><td>از اینترنت باز می‌شود؟</td><td>
      <?php if ($leak === null): ?><span class="warn">➖</span> قابل بررسی نبود
      <?php elseif ($leak): ?><span class="no">🔴</span> بله — خطرناک است
      <?php else: ?><span class="ok">✅</span> نه، بسته است<?php endif; ?></td></tr>
    <tr><td>کاربران</td><td><?= is_file(DATA_DIR . '/users.sqlite') ? '✅ هست' : '➖ هنوز ساخته نشده' ?></td></tr>
    <tr><td>سفارش‌ها</td><td><?= is_file(DATA_DIR . '/orders.sqlite') ? '✅ هست' : '➖ هنوز ساخته نشده' ?></td></tr>
  </table>
  <div class="fix">
    💡 پوشه‌ی داده <b>خودکار</b> کنارِ همین فایل‌ها ساخته می‌شود و اسمش از روی همین پوشه می‌آید،
    پس با هیچ رباتِ دیگری قاطی نمی‌شود. اگر می‌خواهی بیرونِ دسترسِ وب باشد، یک پله بالاتر
    پوشه‌ای به اسم <code>numbix_data</code> بساز و محتویاتِ این پوشه را داخلش منتقل کن —
    کد خودش پیدایش می‌کند.
  </div>
</div>

<div class="card">
  <h2>۳) وبهوکِ ربات</h2>
  <table>
    <tr><td>آدرسی که باید باشد</td><td><code><?= esc_($MAIN) ?></code></td></tr>
    <tr><td>آدرسی که تلگرام دارد</td><td><?= $whUrl === '' ? '<span class="no">🔴</span> هیچی — ست نشده' : '<code>' . esc_($whUrl) . '</code>' ?></td></tr>
    <tr><td>درست است؟</td><td><?= ok_($whMatch) ?> <?= $whMatch ? '' : 'با دکمه‌ی پایین درستش کن' ?></td></tr>
    <tr><td>پیام‌های معطل</td><td><?= $whPend > 0 ? '<span class="warn">⚠️</span> ' . $whPend : '<span class="ok">✅</span> ۰' ?></td></tr>
    <tr><td>آخرین خطای تلگرام</td><td><?= $whErr === '' ? '<span class="ok">✅</span> ندارد' : '<span class="no">🔴</span> ' . esc_($whErr) ?></td></tr>
  </table>

  <?php if ($whErr !== '' && stripos($whErr, '401') !== false): ?>
  <div class="fix">
    🔴 <b>همین مشکلِ توست.</b> خطای ۴۰۱ یعنی وبهوک <b>بدونِ کلیدِ امنیتی</b> ست شده —
    ربات هر پیامی را که آن کلید را نداشته باشد دور می‌ریزد، برای همین <code>/start</code>
    هیچ جوابی نمی‌دهد. دکمه‌ی زیر همین را درست می‌کند.
  </div>
  <?php endif; ?>

  <form method="post" style="margin-top:12px">
    <input type="hidden" name="csrf" value="<?= esc_($CSRF) ?>">
    <input type="hidden" name="a" value="hook_main">
    <button class="btn g">🔗 ست کردنِ وبهوکِ ربات</button>
  </form>
</div>

<?php
  $maStored = trim((string)(maCfg()['base_url'] ?? ''));
  $maOk = ($maStored !== '' && rtrim($maStored, '/') === rtrim($MAIN, '/'));
?>
<div class="card" style="<?= $maOk ? '' : 'border-color:#7f1d1d;background:#1f1010' ?>">
  <h2>۴) مینی‌اپ و فروش شماره</h2>
  <table>
    <tr><td>آدرسی که باید باشد</td><td><code><?= esc_($MAIN) ?></code></td></tr>
    <tr><td>آدرسی که ذخیره شده</td><td><?= $maStored === '' ? '<span class="no">🔴</span> خالی' : '<code>' . esc_($maStored) . '</code>' ?></td></tr>
    <tr><td>یکی هستند؟</td><td><?= ok_($maOk) ?></td></tr>
    <tr><td>فروشنده‌ی شماره</td><td><?= ok_(numReady()) ?> <?= numReady() ? esc_(numProvName()) : 'وصل نیست' ?></td></tr>
    <tr><td>شماره‌های فروشی</td><td>
      <?= ok_($maItems > 0) ?> <?= (int)$maItems ?> اپراتور در <?= (int)$maCats ?> کشور
    </td></tr>
    <tr><td>API جواب می‌دهد؟</td><td>
      <?= ok_($apiJson) ?> کدِ <?= (int)$apiCode ?>
      <?= $apiJson ? '<span style="color:#8b97ad">(JSON سالم)</span>'
                   : ('<br><code>' . esc_($apiSnip === '' ? 'جوابی نیامد' : $apiSnip) . '</code>') ?>
    </td></tr>
    <tr><td>تیکتِ پشتیبانی کجا می‌رود</td><td>
      <?= ok_($supTo !== '') ?>
      <?= $supTo === '' ? 'هیچ‌جا' : ('<code>' . esc_($supTo) . '</code>' . ($supIsAdmin ? ' <span style="color:#8b97ad">(پیویِ مدیر)</span>' : ' <span style="color:#8b97ad">(گروهِ تنظیم‌شده)</span>')) ?>
    </td></tr>
  </table>
  <?php if (!$apiJson): ?>
  <div class="fix">
    🔴 <b>این دلیلِ «همه‌چیز در حال خواندن می‌ماند» است.</b> صفحه‌ی مینی‌اپ باز می‌شود
    ولی هیچ دکمه‌ای جواب نمی‌گیرد، چون آدرسِ بالا JSON برنمی‌گرداند.
    <br>• کدِ ۴۰۴ → آدرسِ ذخیره‌شده به پوشه‌ی قبلی اشاره می‌کند؛ دکمه‌ی پایین را بزنید.
    <br>• کدِ ۵۰۰ یا متنِ HTML → یک خطای PHP؛ لاگِ خطای هاست را ببینید.
    <br>• کدِ ۴۰۱ ولی JSON → سالم است، نگران نباشید.
  </div>
  <?php endif; ?>
  <?php if ($maItems === 0): ?>
  <div class="fix">
    🔴 <b>هنوز شماره‌ای برای فروش نیست.</b> در ربات: <code>/panel</code> ← ☎️ شماره مجازی ←
    اتصالِ فروشنده را تنظیم کنید، بعد «📥 وارد کردن» را بزنید تا کشورها و اپراتورها بیایند.
  </div>
  <?php endif; ?>
  <?php if (!$maOk): ?>
  <div class="fix">
    🔴 <b>این دلیلِ ۴۰۴ شدنِ مینی‌اپ است.</b> آدرسِ ذخیره‌شده به پوشه‌ی قبلی اشاره می‌کند.
    دکمه‌ی زیر همه‌ی آدرس‌ها (مینی‌اپ و درگاه) را با همین پوشه یکی می‌کند.
  </div>
  <?php endif; ?>
  <form method="post" style="margin-top:12px">
    <input type="hidden" name="csrf" value="<?= esc_($CSRF) ?>">
    <input type="hidden" name="a" value="heal">
    <button class="btn g">🩹 یکی کردنِ همه‌ی آدرس‌ها با همین پوشه</button>
  </form>
</div>

<div class="card">
  <h2>۵) لینک‌ها</h2>
  <table>
    <tr><td>پنلِ مدیریت</td><td><a href="admin_panel.php">admin_panel.php</a></td></tr>
    <tr><td>ربات</td><td><code><?= esc_($MAIN) ?></code></td></tr>
    <tr><td>آدرسِ مینی‌اپ</td><td><code><?= esc_($MAIN) ?></code></td></tr>
  </table>
  <form method="post" style="margin-top:12px" onsubmit="return confirm('وبهوک برداشته شود؟ تا دوباره ست نکنی ربات جواب نمی‌دهد.')">
    <input type="hidden" name="csrf" value="<?= esc_($CSRF) ?>">
    <input type="hidden" name="a" value="hook_del">
    <button class="btn r">برداشتنِ وبهوک</button>
  </form>
  <div class="fix">
    🔒 بعد از اینکه همه‌چیز درست شد، <b>این فایل را از هاست پاک کن</b> —
    دیگر لازمش نداری و یک درِ کمتر بهتر است.
  </div>
</div>

</div></body></html>
