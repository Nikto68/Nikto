<?php
$NS_CONFIG = [
    'admin_password' => 'change-me',
    'site_url' => '',
    'db' => ['driver' => 'sqlite', 'host' => 'localhost', 'name' => '', 'user' => '', 'pass' => ''],
    'storage' => __DIR__ . '/nikstore-data',
    'secret' => '',
    'debug' => false,
];

define('NS_ROOT', __DIR__);
define('NS_SELF', basename(__FILE__));
define('NS_VERSION', '1.1.0');

if (isset($_GET['asset']) && PHP_SAPI !== 'cli') ns_asset((string)$_GET['asset']);

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('Nik Store به PHP 8.0 یا بالاتر نیاز دارد. نسخه‌ی فعلی: ' . PHP_VERSION);
}
if (is_file(NS_ROOT . '/nikstore-config.php')) $NS_CONFIG = array_replace($NS_CONFIG, (array)(require NS_ROOT . '/nikstore-config.php'));
$GLOBALS['NS_CFG'] = $NS_CONFIG;
define('NS_STORAGE', rtrim((string)($GLOBALS['NS_CFG']['storage'] ?? NS_ROOT . '/nikstore-data'), '/\\'));

date_default_timezone_set('Asia/Tehran');
mb_internal_encoding('UTF-8');

if (!empty($GLOBALS['NS_CFG']['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

set_exception_handler(function (Throwable $e) {
    error_log('[nikstore] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) http_response_code(500);
    $dbg = !empty($GLOBALS['NS_CFG']['debug']);
    if (str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || isset($_GET['json'])) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'server', 'message' => 'خطای سرور — دوباره تلاش کنید.'], JSON_UNESCAPED_UNICODE);
        return;
    }
    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><body style="font-family:Tahoma,sans-serif;background:#0b0e1a;color:#e6e9f5;display:grid;place-items:center;min-height:100vh;margin:0">'
       . '<div style="max-width:520px;padding:32px;text-align:center"><h2>مشکلی پیش آمد</h2><p>لطفا چند لحظه بعد دوباره تلاش کنید.</p>'
       . ($dbg ? '<pre dir="ltr" style="text-align:left;white-space:pre-wrap;color:#fda4af">' . htmlspecialchars($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()) . '</pre>' : '')
       . '</div></body>';
});

ns_storage_ready();
ns_secret_boot();

function cfg($key, $default = null) {
    return $GLOBALS['NS_CFG'][$key] ?? $default;
}

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ns_digits($s) {
    return strtr((string)$s, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}

function fa_digits($s) {
    return strtr((string)$s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
                              '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

function fa($n, $dec = 0) {
    $s = number_format((float)$n, $dec, '.', ',');
    if ($dec > 0 && str_contains($s, '.')) $s = rtrim(rtrim($s, '0'), '.');
    return strtr(fa_digits($s), [',' => '٬', '.' => '٫']);
}

function toman($n) { return fa($n) . ' تومان'; }

function ns_g2j($gy, $gm, $gd) {
    $gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $gdm[$gm - 1];
    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) { $jm = 1 + intdiv($days, 31); $jd = 1 + ($days % 31); }
    else             { $jm = 7 + intdiv($days - 186, 30); $jd = 1 + (($days - 186) % 30); }
    return [$jy, $jm, $jd];
}

function jdate($ts, $time = true) {
    $ts = (int)$ts;
    if ($ts <= 0) return '—';
    [$y, $m, $d] = ns_g2j((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
    $s = sprintf('%04d/%02d/%02d', $y, $m, $d) . ($time ? ' — ' . date('H:i', $ts) : '');
    return fa_digits($s);
}

function ago($ts) {
    $d = time() - (int)$ts;
    if ($d < 60) return 'همین حالا';
    if ($d < 3600) return fa(intdiv($d, 60)) . ' دقیقه پیش';
    if ($d < 86400) return fa(intdiv($d, 3600)) . ' ساعت پیش';
    return fa(intdiv($d, 86400)) . ' روز پیش';
}

function is_https() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https' && setting('trust_proxy') === '1');
}

function base_url() {
    $u = trim((string)cfg('site_url', ''));
    if ($u !== '') return rtrim($u, '/');
    $host = preg_replace('/[^A-Za-z0-9.:\-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $dir  = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    return (is_https() ? 'https' : 'http') . '://' . $host . $dir;
}

function url($page = 'home', array $q = []) {
    $q = ['p' => $page] + $q;
    if ($page === 'home' && count($q) === 1) return NS_SELF;
    return NS_SELF . '?' . http_build_query($q);
}

function aurl($a = 'dashboard', array $q = []) {
    return NS_SELF . '?' . http_build_query(['a' => $a] + $q);
}

function redirect($to) {
    header('Location: ' . $to, true, 303);
    exit;
}

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input($k, $default = '') {
    $v = $_POST[$k] ?? $_GET[$k] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function is_post() { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }

function view($name, array $vars = [], $layout = 'layout') {
    ob_start();
    $scope = ns_tpl($name, $vars);
    $content = ob_get_clean();
    if ($layout) { $scope['content'] = $content; ns_tpl($layout, $scope); }
    else echo $content;
}

function ns_tpl($name, array $vars) {
    $fn = 'nsv_' . str_replace('/', '_', $name);
    $r = $fn($vars);
    return is_array($r) ? $r : $vars;
}

function ic($name, $cls = '') {
    return '<svg class="ic' . ($cls !== '' ? ' ' . e($cls) : '') . '" aria-hidden="true"><use href="#i-' . e($name) . '"/></svg>';
}

function theme_boot_js() {
    return "(function(){var d=document.documentElement,m=window.matchMedia,n=navigator,t='dark';try{t=localStorage.getItem('ns-theme')||'dark'}catch(e){}d.setAttribute('data-theme',t==='light'?'light':'dark');d.classList.add('js');if((m&&m('(hover:none),(pointer:coarse),(max-width:760px)').matches)||(n.hardwareConcurrency||8)<=2||(n.deviceMemory||8)<=2)d.classList.add('lite')})();";
}

function security_headers() {
    $h = base64_encode(hash('sha256', theme_boot_js(), true));
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), usb=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'sha256-{$h}'; " .
           "style-src 'self' 'unsafe-inline'; font-src 'self' data:; " .
           "img-src 'self' data:; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'; object-src 'none'");
    if (is_https()) header('Strict-Transport-Security: max-age=15552000');
}

function ns_storage_ready() {
    foreach (['', '/uploads', '/sessions'] as $sub) {
        $d = NS_STORAGE . $sub;
        if (!is_dir($d)) @mkdir($d, 0750, true);
        if (is_dir($d) && !is_file($d . '/.htaccess')) {
            @file_put_contents($d . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
            @file_put_contents($d . '/index.html', '');
        }
    }
}

function ns_secret_boot() {
    $s = (string)cfg('secret', '');
    if (strlen($s) >= 16 && $s !== 'change-this-to-a-long-random-string') return;
    $f = NS_STORAGE . '/secret.php';
    $v = is_file($f) ? (string)(include $f) : '';
    if (strlen($v) < 32) {
        $v = bin2hex(random_bytes(32));
        if (@file_put_contents($f, "<?php return '" . $v . "';\n", LOCK_EX) === false)
            throw new RuntimeException('پوشه‌ی ' . basename(NS_STORAGE) . ' قابلِ نوشتن نیست؛ دسترسیِ پوشه‌ی سایت را ۷۵۵ کنید.');
    }
    $GLOBALS['NS_CFG']['secret'] = $v;
}

function session_boot() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $life = 30 * 86400;
    $dir = NS_STORAGE . '/sessions';
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir);
        if (random_int(1, 300) === 1) {
            foreach ((array)glob($dir . '/sess_*') as $f) {
                if (is_file($f) && time() - (int)@filemtime($f) > $life) @unlink($f);
            }
        }
    }
    ini_set('session.gc_maxlifetime', (string)$life);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('nsid');
    session_set_cookie_params(['lifetime' => $life, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https()]);
    session_start();
}

function flash($type, $msg) { $_SESSION['flash'][] = [$type, $msg]; }
function flashes() { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }

function csrf_check() {
    $t = (string)($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '');
    if ($t === '' || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        exit('<!doctype html><meta charset="utf-8"><p style="font-family:Tahoma;padding:40px;text-align:center">نشست منقضی شد. صفحه را تازه کنید و دوباره امتحان کنید. <a href="./">بازگشت</a></p>');
    }
}

function client_ip() {
    if (setting('trust_proxy') === '1') {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $h) {
            $v = trim(explode(',', (string)($_SERVER[$h] ?? ''))[0]);
            if ($v !== '' && filter_var($v, FILTER_VALIDATE_IP)) return $v;
        }
    }
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function rand_code($len = 10) {
    $a = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < $len; $i++) $s .= $a[random_int(0, strlen($a) - 1)];
    return $s;
}

function norm_mobile($m) {
    $m = preg_replace('/[\s\-()]/', '', ns_digits($m));
    if (str_starts_with($m, '+98')) $m = '0' . substr($m, 3);
    elseif (str_starts_with($m, '0098')) $m = '0' . substr($m, 4);
    elseif (str_starts_with($m, '98') && strlen($m) === 12) $m = '0' . substr($m, 2);
    elseif (str_starts_with($m, '9') && strlen($m) === 10) $m = '0' . $m;
    return preg_match('/^09\d{9}$/', $m) ? $m : '';
}

function http_req($method, $url, $body = null, array $headers = [], $timeout = 20) {
    if (!function_exists('curl_init')) return [0, '', 'افزونه‌ی curl روی هاست فعال نیست'];
    $ch = curl_init($url);
    $opt = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_USERAGENT => 'NikStore/' . NS_VERSION,
    ];
    if ($method === 'POST') {
        $opt[CURLOPT_POST] = true;
        $opt[CURLOPT_POSTFIELDS] = is_array($body) ? http_build_query($body) : (string)$body;
    }
    curl_setopt_array($ch, $opt);
    $res  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($res === false) return [0, '', $err ?: 'اتصال برقرار نشد'];
    return [$code, (string)$res, ''];
}

function cron_key() {
    return substr(hash_hmac('sha256', 'cron', (string)cfg('secret', '')), 0, 24);
}

const NS_SCHEMA = 1;

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = (array)cfg('db', []);
    $driver = ($c['driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
    $opt = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try {
        if ($driver === 'mysql') {
            $dsn = 'mysql:host=' . ($c['host'] ?? 'localhost') . ';dbname=' . ($c['name'] ?? '') . ';charset=utf8mb4';
            if (!empty($c['port'])) $dsn .= ';port=' . (int)$c['port'];
            $pdo = new PDO($dsn, (string)($c['user'] ?? ''), (string)($c['pass'] ?? ''), $opt);
        } else {
            if (!in_array('sqlite', PDO::getAvailableDrivers(), true))
                throw new RuntimeException('افزونه‌ی pdo_sqlite روی هاست فعال نیست — از پنل هاست فعالش کنید یا به MySQL بروید.');
            $path = (string)($c['path'] ?? ns_db_file());
            $pdo = new PDO('sqlite:' . $path, null, null, $opt);
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    } catch (PDOException $e) {
        throw new RuntimeException('اتصال به دیتابیس برقرار نشد: ' . $e->getMessage());
    }
    $GLOBALS['NS_DRIVER'] = $driver;
    ns_migrate($pdo);
    return $pdo;
}

function ns_db_file() {
    if (is_file(NS_STORAGE . '/nikstore.sqlite')) return NS_STORAGE . '/nikstore.sqlite';
    $f = NS_STORAGE . '/db-' . substr(hash_hmac('sha256', 'db', (string)cfg('secret', '')), 0, 16) . '.sqlite';
    if (!is_file($f)) {
        $any = glob(NS_STORAGE . '/db-*.sqlite') ?: [];
        if ($any) return $any[0];
    }
    return $f;
}

function db_driver() { db(); return $GLOBALS['NS_DRIVER']; }

function q($sql, array $p = []) {
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st;
}
function row($sql, array $p = []) { $r = q($sql, $p)->fetch(); return $r ?: null; }
function rows($sql, array $p = []) { return q($sql, $p)->fetchAll(); }
function val($sql, array $p = []) { $v = q($sql, $p)->fetchColumn(); return $v === false ? null : $v; }

function insert($table, array $d) {
    $cols = array_keys($d);
    q('INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($d));
    return (int)db()->lastInsertId();
}

function update($table, array $d, $where, array $wp = []) {
    $set = implode(',', array_map(fn($k) => $k . ' = ?', array_keys($d)));
    return q('UPDATE ' . $table . ' SET ' . $set . ' WHERE ' . $where, array_merge(array_values($d), $wp))->rowCount();
}

function tx(callable $fn) {
    $pdo = db();
    $pdo->beginTransaction();
    try { $r = $fn(); $pdo->commit(); return $r; }
    catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function ns_migrate(PDO $pdo) {
    $driver = $GLOBALS['NS_DRIVER'];
    try {
        $v = (int)$pdo->query("SELECT v FROM settings WHERE k = 'schema'")->fetchColumn();
        if ($v >= NS_SCHEMA) return;
    } catch (PDOException $e) {  }

    $map = $driver === 'mysql'
        ? ['{PK}' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', '{S}' => 'VARCHAR(191)', '{T}' => 'TEXT',
           '{I}' => 'INT', '{B}' => 'BIGINT', '{E}' => ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci']
        : ['{PK}' => 'INTEGER PRIMARY KEY AUTOINCREMENT', '{S}' => 'TEXT', '{T}' => 'TEXT',
           '{I}' => 'INTEGER', '{B}' => 'INTEGER', '{E}' => ')'];

    $ddl = [
        "CREATE TABLE IF NOT EXISTS settings (k {S} NOT NULL PRIMARY KEY, v {T} {E}",
        "CREATE TABLE IF NOT EXISTS users (
            id {PK}, name {S} NOT NULL, mobile {S} NOT NULL, pass {S} NOT NULL,
            balance {B} NOT NULL DEFAULT 0, blocked {I} NOT NULL DEFAULT 0,
            created {I} NOT NULL DEFAULT 0, last_login {I} NOT NULL DEFAULT 0 {E}",
        "CREATE UNIQUE INDEX IF NOT EXISTS ux_users_mobile ON users (mobile)",
        "CREATE TABLE IF NOT EXISTS categories (
            id {PK}, slug {S} NOT NULL, kind {S} NOT NULL, title {S} NOT NULL, subtitle {S},
            icon {S}, sort {I} NOT NULL DEFAULT 0, active {I} NOT NULL DEFAULT 1 {E}",
        "CREATE UNIQUE INDEX IF NOT EXISTS ux_cat_slug ON categories (slug)",
        "CREATE TABLE IF NOT EXISTS products (
            id {PK}, cat_id {I} NOT NULL, title {S} NOT NULL, descr {T}, emoji {S}, badge {S},
            pricing {S} NOT NULL DEFAULT 'fixed', price {B} NOT NULL DEFAULT 0, per {I} NOT NULL DEFAULT 1,
            unit {S}, qmin {B} NOT NULL DEFAULT 1, qmax {B} NOT NULL DEFAULT 1, qstep {I} NOT NULL DEFAULT 1,
            field {S} NOT NULL DEFAULT 'none', provider {S} NOT NULL DEFAULT 'manual', provider_ref {S},
            sort {I} NOT NULL DEFAULT 0, active {I} NOT NULL DEFAULT 1, created {I} NOT NULL DEFAULT 0 {E}",
        "CREATE INDEX IF NOT EXISTS ix_prod_cat ON products (cat_id)",
        "CREATE TABLE IF NOT EXISTS orders (
            id {PK}, code {S} NOT NULL, user_id {I}, product_id {I} NOT NULL DEFAULT 0, kind {S} NOT NULL DEFAULT 'product',
            title {S} NOT NULL, qty {B} NOT NULL DEFAULT 1, amount {B} NOT NULL DEFAULT 0,
            target {S}, note {T}, name {S}, contact {S},
            status {S} NOT NULL DEFAULT 'pending', method {S}, authority {S}, ref {S}, receipt {S}, receipt_note {S},
            delivery {T}, provider {S}, provider_id {S}, provider_state {T}, admin_note {T},
            created {I} NOT NULL DEFAULT 0, updated {I} NOT NULL DEFAULT 0, paid_at {I} NOT NULL DEFAULT 0 {E}",
        "CREATE UNIQUE INDEX IF NOT EXISTS ux_orders_code ON orders (code)",
        "CREATE INDEX IF NOT EXISTS ix_orders_status ON orders (status)",
        "CREATE INDEX IF NOT EXISTS ix_orders_user ON orders (user_id)",
        "CREATE TABLE IF NOT EXISTS wallet_tx (
            id {PK}, user_id {I} NOT NULL, amount {B} NOT NULL, reason {S}, order_id {I}, created {I} NOT NULL DEFAULT 0 {E}",
        "CREATE INDEX IF NOT EXISTS ix_wtx_user ON wallet_tx (user_id)",
        "CREATE TABLE IF NOT EXISTS throttle (k {S} NOT NULL PRIMARY KEY, n {I} NOT NULL DEFAULT 0, t {I} NOT NULL DEFAULT 0 {E}",
    ];
    foreach ($ddl as $sql) {
        $sql = strtr($sql, $map);

        if ($driver === 'mysql' && str_starts_with($sql, 'CREATE ') && str_contains($sql, ' INDEX IF NOT EXISTS ')) {
            $sql = str_replace(' INDEX IF NOT EXISTS ', ' INDEX ', $sql);
            try { $pdo->exec($sql); } catch (PDOException $e) { if (($e->errorInfo[1] ?? 0) != 1061) throw $e; }
            continue;
        }
        $pdo->exec($sql);
    }

    $seeded = false;
    try { $seeded = (bool)$pdo->query("SELECT v FROM settings WHERE k = 'seeded'")->fetchColumn(); } catch (PDOException $e) {}
    if (!$seeded) {
        ns_seed($pdo);
    }
    $pdo->prepare("DELETE FROM settings WHERE k = 'schema'")->execute();
    $pdo->prepare("INSERT INTO settings (k, v) VALUES ('schema', ?)")->execute([(string)NS_SCHEMA]);
}

function setting_defaults() {
    return [
        'site_name'     => 'Nik Store',
        'tagline'       => 'فروشگاه خدمات تلگرام و اینستاگرام',
        'notice'        => '',
        'support'       => 'whaleQT',
        'channel'       => 'Kong_Trade',
        'contact_text'  => '',
        'card_on'       => '1',
        'card_number'   => '',
        'card_holder'   => '',
        'card_bank'     => '',
        'zp_on'         => '0',
        'zp_merchant'   => '',
        'zp_sandbox'    => '0',
        'wallet_on'     => '1',
        'topup_min'     => '50000',
        'smm_url'       => '',
        'smm_key'       => '',
        'fivesim_key'   => '',
        'pending_hours' => '24',
        'trust_proxy'   => '0',
        'admin_hash'    => '',
        'terms'         => "• خرید فقط با اطلاعاتِ درست (آیدی یا لینکِ عمومی) انجام می‌شود؛ مسئولیتِ اطلاعاتِ اشتباه با خریدار است.\n• برای هیچ خدمتی رمز یا کدِ ورودِ حساب از شما خواسته نمی‌شود.\n• اگر سفارشی قابلِ انجام نباشد، مبلغ به کیف پول یا حسابِ شما برگردانده می‌شود.\n• سفارش‌های تکمیل‌شده قابلِ بازگشت نیستند.",
        'about'         => 'ما خدماتِ تلگرام (استارز، پریمیوم، گیفت)، شماره مجازی و خدماتِ اینستاگرام را با قیمتِ منصفانه، پرداختِ امن و تحویلِ سریع ارائه می‌دهیم.',
        'seed_review'   => '1',
    ];
}

function setting($k, $default = null) {
    static $cache = null;
    if ($k === null) { $cache = null; return null; }
    if ($cache === null) {
        $cache = setting_defaults();
        foreach (rows('SELECT k, v FROM settings') as $r) $cache[$r['k']] = (string)$r['v'];
    }
    return $cache[$k] ?? $default;
}

function settings_save(array $kv) {
    tx(function () use ($kv) {
        foreach ($kv as $k => $v) {
            q('DELETE FROM settings WHERE k = ?', [$k]);
            q('INSERT INTO settings (k, v) VALUES (?, ?)', [$k, (string)$v]);
        }
    });
    setting(null);
}

function throttle_ok($key, $max, $window) {
    $now = time();
    $k = substr(hash('sha256', $key), 0, 40);
    $r = row('SELECT n, t FROM throttle WHERE k = ?', [$k]);
    if (!$r || $now - (int)$r['t'] > $window) {
        q('DELETE FROM throttle WHERE k = ?', [$k]);
        q('INSERT INTO throttle (k, n, t) VALUES (?, 1, ?)', [$k, $now]);
        if (random_int(1, 100) === 1) q('DELETE FROM throttle WHERE t < ?', [$now - 86400]);
        return true;
    }
    if ((int)$r['n'] >= $max) return false;
    q('UPDATE throttle SET n = n + 1 WHERE k = ?', [$k]);
    return true;
}
function throttle_clear($key) { q('DELETE FROM throttle WHERE k = ?', [substr(hash('sha256', $key), 0, 40)]); }

function categories($activeOnly = true) {
    return rows('SELECT * FROM categories' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY sort, id');
}
function category($slugOrId) {
    return is_numeric($slugOrId)
        ? row('SELECT * FROM categories WHERE id = ?', [(int)$slugOrId])
        : row('SELECT * FROM categories WHERE slug = ?', [(string)$slugOrId]);
}
function products($catId, $activeOnly = true) {
    return rows('SELECT * FROM products WHERE cat_id = ?' . ($activeOnly ? ' AND active = 1' : '') . ' ORDER BY sort, id', [(int)$catId]);
}
function product($id, $activeOnly = true) {
    $p = row('SELECT p.*, c.slug AS cat_slug, c.kind AS cat_kind, c.title AS cat_title, c.active AS cat_active
              FROM products p JOIN categories c ON c.id = p.cat_id WHERE p.id = ?', [(int)$id]);
    if ($p && $activeOnly && (!(int)$p['active'] || !(int)$p['cat_active'])) return null;
    return $p;
}

function category_from_price($catId) {
    $min = 0;
    foreach (products($catId) as $p) {
        $v = $p['pricing'] === 'unit' ? product_total($p, max((int)$p['qmin'], 1)) : (int)$p['price'];
        if ($v > 0 && ($min === 0 || $v < $min)) $min = $v;
    }
    return $min;
}

function product_total($p, $qty) {
    if ($p['pricing'] === 'unit') return (int)ceil((int)$p['price'] * (float)$qty / max(1, (int)$p['per']));
    return (int)$p['price'];
}

function product_price_label($p) {
    if ((int)$p['price'] <= 0) return 'استعلام قیمت';
    if ($p['pricing'] !== 'unit') return toman($p['price']);
    return 'هر ' . fa($p['per']) . ' ' . ($p['unit'] ?: 'عدد') . ' ' . toman($p['price']);
}

function field_meta($field) {
    return [
        'tg_user' => ['label' => 'آیدی تلگرام گیرنده', 'ph' => '@username', 'hint' => 'آیدیِ عمومیِ تلگرام. رمز یا کدِ ورود لازم نیست — هرگز به کسی ندهید.'],
        'tg_link' => ['label' => 'لینک کانال یا پست تلگرام', 'ph' => 'https://t.me/…', 'hint' => 'کانال یا گروه باید عمومی باشد.'],
        'ig_link' => ['label' => 'لینک پیج یا پست اینستاگرام', 'ph' => 'https://instagram.com/…', 'hint' => 'پیج باید عمومی (Public) باشد. رمزِ اینستاگرام هرگز لازم نیست.'],
        'link'    => ['label' => 'لینک', 'ph' => 'https://…', 'hint' => ''],
        'text'    => ['label' => 'مشخصاتِ سفارش', 'ph' => 'توضیح دهید…', 'hint' => ''],
    ][$field] ?? null;
}
function field_types() {
    return ['none' => '— هیچ (فقط پرداخت)', 'tg_user' => 'آیدی تلگرام', 'tg_link' => 'لینک تلگرام',
            'ig_link' => 'لینک/آیدی اینستاگرام', 'link' => 'لینک دلخواه', 'text' => 'متن دلخواه'];
}

function field_clean($field, $v, &$err) {
    $v = trim(ns_digits((string)$v));
    $err = '';
    switch ($field) {
        case 'none': return '';
        case 'tg_user':
            $u = ltrim(preg_replace('#^(https?://)?(t\.me|telegram\.me)/#i', '', $v), '@');
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{3,31}$/', $u)) { $err = 'آیدی تلگرام معتبر نیست (مثلا @username).'; return ''; }
            return '@' . $u;
        case 'tg_link':
            if (preg_match('/^@([A-Za-z][A-Za-z0-9_]{3,31})$/', $v, $m)) return 'https://t.me/' . $m[1];
            if (preg_match('#^(https?://)?(t\.me|telegram\.me)/([A-Za-z0-9_+/\-?=&%.]{2,250})$#i', $v, $m)) return 'https://t.me/' . $m[3];
            $err = 'لینک تلگرام معتبر نیست (مثلا https://t.me/channel).'; return '';
        case 'ig_link':
            if (preg_match('/^@?([A-Za-z0-9._]{2,30})$/', $v, $m)) return 'https://instagram.com/' . $m[1];
            if (preg_match('#^(https?://)?(www\.)?(instagram\.com|instagr\.am)/([^\s]{1,250})$#i', $v, $m)) return 'https://instagram.com/' . $m[4];
            $err = 'لینک یا آیدی اینستاگرام معتبر نیست.'; return '';
        case 'link':
            if (preg_match('#^https?://[^\s<>"]{4,300}$#i', $v)) return $v;
            $err = 'لینک معتبر نیست (باید با https:// شروع شود).'; return '';
        case 'text':
            if (mb_strlen($v) >= 2 && mb_strlen($v) <= 500) return $v;
            $err = 'مشخصاتِ سفارش را بنویسید (حداکثر ۵۰۰ نویسه).'; return '';
    }
    $err = 'نوعِ فیلد ناشناخته است.';
    return '';
}

function qty_clean($p, $qty, &$err) {
    $err = '';
    if ($p['pricing'] !== 'unit') return 1;
    $q = (int)floor((float)ns_digits((string)$qty));
    $mn = max(1, (int)$p['qmin']); $mx = (int)$p['qmax']; $st = max(1, (int)$p['qstep']);
    if ($q < $mn) { $err = 'حداقل تعداد ' . fa($mn) . ' است.'; return 0; }
    if ($mx > 0 && $q > $mx) { $err = 'حداکثر تعداد ' . fa($mx) . ' است.'; return 0; }
    if ($st > 1 && $q % $st !== 0) { $err = 'تعداد باید مضربِ ' . fa($st) . ' باشد.'; return 0; }
    return $q;
}

function order_statuses() {
    return [
        'pending'    => ['در انتظار پرداخت', 'st-wait', 'clock'],
        'review'     => ['در انتظار تایید رسید', 'st-review', 'eye'],
        'paid'       => ['پرداخت شد — در صف انجام', 'st-paid', 'check'],
        'processing' => ['در حال انجام', 'st-proc', 'refresh'],
        'completed'  => ['تکمیل شد', 'st-done', 'check'],
        'canceled'   => ['لغو شد', 'st-off', 'close'],
        'failed'     => ['انجام نشد', 'st-bad', 'alert'],
        'refunded'   => ['مبلغ برگشت داده شد', 'st-off', 'wallet'],
    ];
}
function status_label($s) { return order_statuses()[$s][0] ?? $s; }
function status_cls($s)   { return order_statuses()[$s][1] ?? ''; }

function order_by_code($code) {
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$code));
    return $code === '' ? null : row('SELECT * FROM orders WHERE code = ?', [$code]);
}
function order_get($id) { return row('SELECT * FROM orders WHERE id = ?', [(int)$id]); }

function order_create(array $d) {
    $now = time();
    for ($i = 0; $i < 5; $i++) {
        $code = rand_code(10);
        if (!val('SELECT 1 FROM orders WHERE code = ?', [$code])) break;
    }
    $id = insert('orders', $d + ['code' => $code, 'status' => 'pending', 'created' => $now, 'updated' => $now]);
    return order_get($id);
}

function order_set($id, array $f) {
    $f['updated'] = time();
    update('orders', $f, 'id = ?', [(int)$id]);
    return order_get($id);
}

function order_mark_paid($order, $method, $ref = '') {
    $n = q("UPDATE orders SET status = 'paid', method = ?, ref = ?, paid_at = ?, updated = ? WHERE id = ? AND status IN ('pending', 'review')",
           [$method, (string)$ref, time(), time(), (int)$order['id']])->rowCount();
    if ($n !== 1) return false;
    $o = order_get($order['id']);
    if ($o['kind'] === 'topup') {
        wallet_credit((int)$o['user_id'], (int)$o['amount'], 'شارژ کیف پول', (int)$o['id']);
        order_set($o['id'], ['status' => 'completed', 'delivery' => 'کیف پول شما ' . toman($o['amount']) . ' شارژ شد.']);
        return true;
    }
    provider_dispatch($o);
    return true;
}

function order_refund_wallet($order, $why = '') {
    if ((int)$order['user_id'] <= 0 || $order['kind'] === 'topup') return false;

    $n = q("UPDATE orders SET status = 'refunded', updated = ? WHERE id = ? AND status IN ('paid', 'processing', 'failed') AND paid_at > 0",
           [time(), (int)$order['id']])->rowCount();
    if ($n !== 1) return false;
    wallet_credit((int)$order['user_id'], (int)$order['amount'], 'برگشتِ وجهِ سفارش ' . $order['code'] . ($why !== '' ? ' — ' . $why : ''), (int)$order['id']);
    return true;
}

function orders_expire() {
    $h = max(1, (int)setting('pending_hours', '24'));
    return q("UPDATE orders SET status = 'canceled', updated = ? WHERE status = 'pending' AND created < ?", [time(), time() - $h * 3600])->rowCount();
}

function user_current() {
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    $id = (int)($_SESSION['uid'] ?? 0);
    if ($id > 0) {
        $r = row('SELECT * FROM users WHERE id = ?', [$id]);
        if ($r && !(int)$r['blocked'] && hash_equals((string)($_SESSION['uph'] ?? ''), substr(hash('sha256', $r['pass']), 0, 16))) $u = $r;
        else unset($_SESSION['uid'], $_SESSION['uph']);
    }
    return $u;
}

function user_login_as($u) {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['uph'] = substr(hash('sha256', $u['pass']), 0, 16);
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
    update('users', ['last_login' => time()], 'id = ?', [(int)$u['id']]);
}

function user_logout() {
    unset($_SESSION['uid'], $_SESSION['uph']);
    session_regenerate_id(true);
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function wallet_credit($uid, $amount, $reason, $orderId = null) {
    $amount = (int)$amount;
    if ($uid <= 0 || $amount === 0) return;
    tx(function () use ($uid, $amount, $reason, $orderId) {
        q('UPDATE users SET balance = balance + ? WHERE id = ?', [$amount, $uid]);
        insert('wallet_tx', ['user_id' => $uid, 'amount' => $amount, 'reason' => mb_substr((string)$reason, 0, 180), 'order_id' => $orderId, 'created' => time()]);
    });
}

function wallet_debit($uid, $amount, $reason, $orderId = null) {
    $amount = (int)$amount;
    if ($uid <= 0 || $amount <= 0) return false;
    return tx(function () use ($uid, $amount, $reason, $orderId) {
        $n = q('UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?', [$amount, $uid, $amount])->rowCount();
        if ($n !== 1) return false;
        insert('wallet_tx', ['user_id' => $uid, 'amount' => -$amount, 'reason' => mb_substr((string)$reason, 0, 180), 'order_id' => $orderId, 'created' => time()]);
        return true;
    });
}

function zp_on()     { return setting('zp_on') === '1' && trim((string)setting('zp_merchant')) !== ''; }
function card_on()   { return setting('card_on') === '1' && trim((string)setting('card_number')) !== ''; }
function wallet_on() { return setting('wallet_on') === '1'; }

function zp_base() { return setting('zp_sandbox') === '1' ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com'; }

function zp_start($order) {
    if (!zp_on()) return [false, 'درگاه آنلاین فعال نیست.'];
    $body = json_encode([
        'merchant_id'  => trim((string)setting('zp_merchant')),
        'amount'       => (int)$order['amount'] * 10,
        'callback_url' => base_url() . '/' . NS_SELF . '?p=zp&code=' . rawurlencode($order['code']),
        'description'  => mb_substr($order['title'] . ' — ' . setting('site_name'), 0, 200),
        'metadata'     => array_filter(['mobile' => preg_match('/^09\d{9}$/', (string)$order['contact']) ? $order['contact'] : null]),
    ], JSON_UNESCAPED_UNICODE);
    [$code, $res, $err] = http_req('POST', zp_base() . '/pg/v4/payment/request.json', $body,
                                   ['Content-Type: application/json', 'Accept: application/json']);
    if ($err !== '') return [false, 'اتصال به درگاه برقرار نشد: ' . $err];
    $j = json_decode($res, true);
    $auth = (string)($j['data']['authority'] ?? '');
    if ((int)($j['data']['code'] ?? 0) !== 100 || $auth === '') {
        $msg = is_array($j['errors'] ?? null) ? (string)($j['errors']['message'] ?? '') : '';
        return [false, 'درگاه درخواست را نپذیرفت' . ($msg !== '' ? ': ' . $msg : '') . '.'];
    }
    order_set($order['id'], ['method' => 'zarinpal', 'authority' => $auth]);
    return [true, zp_base() . '/pg/StartPay/' . rawurlencode($auth)];
}

function zp_verify($order, $authority) {
    if ($authority === '' || !hash_equals((string)$order['authority'], $authority)) return [false, 'کدِ پرداخت با این سفارش نمی‌خواند.'];
    $body = json_encode(['merchant_id' => trim((string)setting('zp_merchant')), 'amount' => (int)$order['amount'] * 10, 'authority' => $authority]);
    [$code, $res, $err] = http_req('POST', zp_base() . '/pg/v4/payment/verify.json', $body,
                                   ['Content-Type: application/json', 'Accept: application/json']);
    if ($err !== '') return [false, 'اتصال به درگاه برقرار نشد؛ اگر مبلغ کم شده، تا چند دقیقه‌ی دیگر خودکار تایید یا برگشت داده می‌شود.'];
    $j = json_decode($res, true);
    $c = (int)($j['data']['code'] ?? 0);
    if ($c === 100 || $c === 101) return [true, (string)($j['data']['ref_id'] ?? '')];
    return [false, 'پرداخت تایید نشد.'];
}

function pay_wallet($order, $user) {
    if (!wallet_on()) return [false, 'پرداخت از کیف پول فعال نیست.'];
    if ((int)$order['user_id'] !== (int)$user['id']) return [false, 'این سفارش مالِ حسابِ شما نیست.'];
    if ($order['status'] !== 'pending') return [false, 'این سفارش در انتظارِ پرداخت نیست.'];
    if (!wallet_debit((int)$user['id'], (int)$order['amount'], 'پرداختِ سفارش ' . $order['code'], (int)$order['id']))
        return [false, 'موجودی کیف پول کافی نیست.'];
    if (!order_mark_paid($order, 'wallet', 'WALLET')) {

        wallet_credit((int)$user['id'], (int)$order['amount'], 'برگشتِ کسرِ تکراری ' . $order['code'], (int)$order['id']);
        return [false, 'وضعیتِ سفارش عوض شده بود؛ مبلغ به کیف پول برگشت.'];
    }
    return [true, ''];
}

function receipt_save($order, $file, $note) {
    if (!card_on()) return [false, 'کارت‌به‌کارت فعال نیست.'];
    if (!in_array($order['status'], ['pending', 'review'], true)) return [false, 'این سفارش دیگر رسید نمی‌پذیرد.'];
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return [false, 'تصویرِ رسید را انتخاب کنید.'];
    if ((int)$file['size'] > 5 * 1024 * 1024) return [false, 'حجمِ تصویر باید کمتر از ۵ مگابایت باشد.'];
    $info = @getimagesize($file['tmp_name']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? '';
    if ($ext === '') return [false, 'فقط تصویرِ JPG، PNG یا WEBP قبول می‌شود.'];
    $name = date('Ym') . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
    if (!@move_uploaded_file($file['tmp_name'], NS_STORAGE . '/uploads/' . $name)) return [false, 'ذخیره‌ی رسید ممکن نشد — دوباره امتحان کنید.'];
    if (!empty($order['receipt'])) @unlink(NS_STORAGE . '/uploads/' . basename($order['receipt']));
    order_set($order['id'], ['status' => 'review', 'method' => 'card', 'receipt' => $name,
                             'receipt_note' => mb_substr(trim(ns_digits((string)$note)), 0, 120)]);
    return [true, ''];
}

function provider_effective($p) {
    $prov = (string)($p['provider'] ?? 'manual');
    $ref  = trim((string)($p['provider_ref'] ?? ''));
    if ($prov === 'smm' && $ref !== '' && trim((string)setting('smm_url')) !== '' && trim((string)setting('smm_key')) !== '') return 'smm';
    if ($prov === '5sim' && $ref !== '' && trim((string)setting('fivesim_key')) !== '') return '5sim';
    return 'manual';
}

function provider_state($o) {
    $s = json_decode((string)($o['provider_state'] ?? ''), true);
    return is_array($s) ? $s : [];
}

function provider_dispatch($o) {
    if ($o['kind'] !== 'product' || $o['status'] !== 'paid') return;
    $p = product($o['product_id'], false);
    if (!$p) return;
    $mode = provider_effective($p);
    if ($mode === 'smm') {
        [$ok, $res] = smm_call(['action' => 'add', 'service' => $p['provider_ref'], 'link' => $o['target'], 'quantity' => (int)$o['qty']]);
        if ($ok && !empty($res['order'])) {
            order_set($o['id'], ['status' => 'processing', 'provider' => 'smm', 'provider_id' => (string)$res['order'],
                                 'provider_state' => json_encode(['at' => time()]), 'delivery' => 'سفارش ثبت شد و در حال انجام است.']);
        } else {
            order_set($o['id'], ['provider' => 'smm', 'admin_note' => trim($o['admin_note'] . "\nخطای ثبتِ خودکار: " . (is_string($res) ? $res : ($res['error'] ?? 'نامشخص')))]);
        }
        return;
    }
    if ($mode === '5sim') {
        [$ok, $res] = fivesim_call('/user/buy/activation/' . implode('/', array_map('rawurlencode', explode('/', $p['provider_ref']))));
        if ($ok && !empty($res['id']) && !empty($res['phone'])) {
            order_set($o['id'], ['status' => 'processing', 'provider' => '5sim', 'provider_id' => (string)$res['id'],
                                 'provider_state' => json_encode(['phone' => $res['phone'], 'status' => $res['status'] ?? 'PENDING', 'code' => '', 'at' => time(), 'bought' => time()]),
                                 'delivery' => 'شماره: ' . $res['phone']]);
        } else {
            $why = is_string($res) ? $res : 'شماره‌ای در دسترس نبود';
            $o = order_set($o['id'], ['status' => 'failed', 'provider' => '5sim', 'admin_note' => trim($o['admin_note'] . "\nخرید شماره ناموفق: " . $why),
                                      'delivery' => 'در حالِ حاضر شماره‌ای برای این کشور موجود نیست.']);
            if (order_refund_wallet($o, 'شماره موجود نبود')) order_set($o['id'], ['delivery' => 'شماره‌ای موجود نبود؛ مبلغ به کیف پول شما برگشت.']);
        }
        return;
    }
    order_set($o['id'], ['provider' => 'manual']);
}

function provider_refresh($o, $force = false) {
    if ($o['status'] !== 'processing') return $o;
    $st = provider_state($o);
    $gap = $o['provider'] === '5sim' ? 5 : 60;
    if (!$force && time() - (int)($st['at'] ?? 0) < $gap) return $o;
    $st['at'] = time();

    if ($o['provider'] === 'smm' && $o['provider_id'] !== '') {
        [$ok, $res] = smm_call(['action' => 'status', 'order' => $o['provider_id']]);
        if (!$ok) return order_set($o['id'], ['provider_state' => json_encode($st)]);
        $s = strtolower((string)($res['status'] ?? ''));
        $st['remains'] = $res['remains'] ?? null;
        if ($s === 'completed') return order_set($o['id'], ['status' => 'completed', 'provider_state' => json_encode($st), 'delivery' => 'سفارش کامل انجام شد.']);
        if ($s === 'partial') return order_set($o['id'], ['status' => 'completed', 'provider_state' => json_encode($st),
            'delivery' => 'سفارش بخشی انجام شد' . ($st['remains'] ? ' (' . fa((int)$st['remains']) . ' عدد باقی ماند)' : '') . '.',
            'admin_note' => trim($o['admin_note'] . "\nانجامِ ناقص — باقی‌مانده: " . (int)$st['remains'])]);
        if ($s === 'canceled' || $s === 'cancelled') {
            $o = order_set($o['id'], ['status' => 'failed', 'provider_state' => json_encode($st), 'delivery' => 'سفارش توسطِ سرویس‌دهنده لغو شد.']);
            if (order_refund_wallet($o, 'لغو توسط سرویس‌دهنده')) $o = order_set($o['id'], ['delivery' => 'سفارش انجام نشد؛ مبلغ به کیف پول شما برگشت.']);
            return $o;
        }
        return order_set($o['id'], ['provider_state' => json_encode($st)]);
    }

    if ($o['provider'] === '5sim' && $o['provider_id'] !== '') {
        [$ok, $res] = fivesim_call('/user/check/' . rawurlencode($o['provider_id']));
        if (!$ok) return order_set($o['id'], ['provider_state' => json_encode($st)]);
        $s = strtoupper((string)($res['status'] ?? ''));
        $st['status'] = $s;
        $code = '';
        foreach ((array)($res['sms'] ?? []) as $sms) if (!empty($sms['code'])) $code = (string)$sms['code'];
        if ($code !== '') {
            $st['code'] = $code;
            fivesim_call('/user/finish/' . rawurlencode($o['provider_id']));
            return order_set($o['id'], ['status' => 'completed', 'provider_state' => json_encode($st),
                                        'delivery' => 'شماره: ' . ($st['phone'] ?? '') . "\nکد تایید: " . $code]);
        }
        if (in_array($s, ['CANCELED', 'TIMEOUT', 'BANNED'], true)) {
            $o = order_set($o['id'], ['status' => 'failed', 'provider_state' => json_encode($st), 'delivery' => 'کدی دریافت نشد و شماره منقضی شد.']);
            if (order_refund_wallet($o, 'کد دریافت نشد')) $o = order_set($o['id'], ['delivery' => 'کدی دریافت نشد؛ مبلغ به کیف پول شما برگشت.']);
            return $o;
        }
        return order_set($o['id'], ['provider_state' => json_encode($st)]);
    }
    return $o;
}

function number_cancel($o) {
    if ($o['provider'] !== '5sim' || $o['status'] !== 'processing') return [false, 'این شماره قابلِ لغو نیست.'];
    $st = provider_state($o);
    if (!empty($st['code'])) return [false, 'کد دریافت شده؛ لغو ممکن نیست.'];
    if (time() - (int)($st['bought'] ?? 0) < 120) return [false, 'دو دقیقه بعد از خرید می‌توانید لغو کنید.'];
    [$ok, $res] = fivesim_call('/user/cancel/' . rawurlencode($o['provider_id']));
    if (!$ok) return [false, 'لغو ممکن نشد: ' . (is_string($res) ? $res : 'خطای سرویس‌دهنده')];
    $o = order_set($o['id'], ['status' => 'failed', 'delivery' => 'شماره لغو شد.']);
    if (order_refund_wallet($o, 'لغو شماره')) order_set($o['id'], ['delivery' => 'شماره لغو شد؛ مبلغ به کیف پول شما برگشت.']);
    return [true, ''];
}

function smm_call(array $params) {
    $url = trim((string)setting('smm_url'));
    [$code, $res, $err] = http_req('POST', $url, ['key' => trim((string)setting('smm_key'))] + $params);
    if ($err !== '') return [false, $err];
    $j = json_decode($res, true);
    if (!is_array($j)) return [false, 'پاسخِ نامعتبر از پنل'];
    if (!empty($j['error'])) return [false, (string)$j['error']];
    return [true, $j];
}

function fivesim_call($path) {
    [$code, $res, $err] = http_req('GET', 'https://5sim.net/v1' . $path, null,
                                   ['Authorization: Bearer ' . trim((string)setting('fivesim_key')), 'Accept: application/json']);
    if ($err !== '') return [false, $err];
    $j = json_decode($res, true);
    if ($code >= 400 || !is_array($j)) return [false, trim(mb_substr(strip_tags($res), 0, 120)) ?: ('کد ' . $code)];
    return [true, $j];
}

function ns_seed(PDO $pdo) {
    $now = time();
    $cats = [

        ['stars',     'stars',     'استارز تلگرام',       'برای گیفت، ری‌اکشنِ پولی و پرداخت در ربات‌ها و مینی‌اپ‌ها',         'star'],
        ['premium',   'premium',   'تلگرام پریمیوم',      'فعال‌سازی مستقیم روی آیدی شما — بدون رمز و کدِ ورود',              'crown'],
        ['gifts',     'gifts',     'گیفت تلگرام',         'گیفت‌های استارزی برای هر مناسبت، مستقیم روی پروفایلِ گیرنده',      'gift'],
        ['numbers',   'numbers',   'شماره مجازی',         'شماره‌ی کشورهای مختلف برای ساختِ اکانتِ تلگرام',                  'phone'],
        ['instagram', 'instagram', 'خدمات اینستاگرام',    'فالوور، لایک، بازدید و کامنت — فقط با لینک، بدون رمز',            'insta'],
        ['telegram',  'telegram',  'رشد کانال تلگرام',    'ممبر، بازدید و ری‌اکشن برای کانال و پست‌ها',                       'megaphone'],
    ];
    $ins = $pdo->prepare('INSERT INTO categories (slug, kind, title, subtitle, icon, sort, active) VALUES (?, ?, ?, ?, ?, ?, 1)');
    $ids = [];
    foreach ($cats as $i => [$slug, $kind, $title, $sub, $icon]) {
        $ins->execute([$slug, $kind, $title, $sub, $icon, $i + 1]);
        $ids[$slug] = (int)$pdo->lastInsertId();
    }

    $P = [];
    $P[] = ['stars', 'استارز — مقدار دلخواه', 'هر تعداد که بخواهید؛ حداقل ۵۰ استارز', '⭐️', 'دلخواه', 'unit', 1900, 1, 'استارز', 50, 100000, 1, 'tg_user', 'manual', ''];
    foreach ([[50, 95000, ''], [100, 190000, 'پرفروش'], [250, 475000, ''], [500, 950000, 'اقتصادی'],
              [1000, 1900000, 'ویژه'], [2500, 4750000, ''], [5000, 9500000, 'عمده'], [10000, 19000000, 'عمده']] as [$n, $pr, $b]) {
        $P[] = ['stars', fa($n) . ' استارز', 'واریزِ مستقیم به آیدیِ گیرنده', '⭐️', $b, 'fixed', $pr, 1, '', 1, 1, 1, 'tg_user', 'manual', ''];
    }
    $P[] = ['premium', 'پریمیوم ۳ ماهه', 'فعال‌سازی روی آیدیِ شما — بدون نیاز به رمز', '💎', '', 'fixed', 690000, 1, '', 1, 1, 1, 'tg_user', 'manual', ''];
    $P[] = ['premium', 'پریمیوم ۶ ماهه', 'فعال‌سازی روی آیدیِ شما — بدون نیاز به رمز', '💎', 'اقتصادی', 'fixed', 990000, 1, '', 1, 1, 1, 'tg_user', 'manual', ''];
    $P[] = ['premium', 'پریمیوم ۱۲ ماهه', 'یک سالِ کامل — بهترین قیمتِ ماهانه', '👑', 'بهترین انتخاب', 'fixed', 1690000, 1, '', 1, 1, 1, 'tg_user', 'manual', ''];
    foreach ([['🧸', 'تدی', 15, 33000], ['💝', 'قلب', 15, 33000], ['🌹', 'گل رز', 25, 54000], ['🎁', 'جعبه کادو', 25, 54000],
              ['🎂', 'کیک تولد', 50, 105000], ['💐', 'دسته گل', 50, 105000], ['🚀', 'موشک', 50, 105000], ['🍾', 'شامپاین', 50, 105000],
              ['🏆', 'جام قهرمانی', 100, 205000], ['💍', 'حلقه', 100, 205000], ['💎', 'الماس', 100, 205000]] as [$em, $nm, $st, $pr]) {
        $P[] = ['gifts', 'گیفت ' . $nm, fa($st) . ' استارز', $em, $st >= 100 ? 'لاکچری' : '', 'fixed', $pr, 1, '', 1, 1, 1, 'tg_user', 'manual', ''];
    }
    foreach ([['🇷🇺', 'روسیه', 'russia', 45000, 'پرفروش'], ['🇺🇸', 'آمریکا', 'usa', 145000, ''], ['🇬🇧', 'انگلیس', 'england', 120000, ''],
              ['🇰🇿', 'قزاقستان', 'kazakhstan', 55000, ''], ['🇺🇦', 'اوکراین', 'ukraine', 62000, ''], ['🇮🇩', 'اندونزی', 'indonesia', 38000, 'ارزان'],
              ['🇮🇳', 'هند', 'india', 40000, ''], ['🇧🇷', 'برزیل', 'brazil', 70000, ''], ['🇨🇦', 'کانادا', 'canada', 90000, ''],
              ['🇩🇪', 'آلمان', 'germany', 160000, ''], ['🇫🇷', 'فرانسه', 'france', 150000, ''], ['🇳🇱', 'هلند', 'netherlands', 130000, '']] as [$fl, $nm, $code, $pr, $b]) {
        $P[] = ['numbers', $nm, 'شماره مجازی برای ساختِ اکانتِ تلگرام', $fl, $b, 'fixed', $pr, 1, '', 1, 1, 1, 'none', '5sim', $code . '/any/telegram'];
    }
    $P[] = ['instagram', 'فالوور اینستاگرام ایرانی', 'فالوور با پروفایلِ کامل — مناسبِ پیج‌های فارسی', '👥', 'پرفروش', 'unit', 420000, 1000, 'فالوور', 100, 50000, 100, 'ig_link', 'smm', ''];
    $P[] = ['instagram', 'فالوور اینستاگرام خارجی', 'شروعِ سریع، مناسبِ افزایشِ عددِ فالوور', '🌍', 'اقتصادی', 'unit', 210000, 1000, 'فالوور', 100, 200000, 100, 'ig_link', 'smm', ''];
    $P[] = ['instagram', 'لایک پست و ریلز', 'لایک برای پست، ریلز و کاروسل', '❤️', '', 'unit', 90000, 1000, 'لایک', 50, 100000, 50, 'ig_link', 'smm', ''];
    $P[] = ['instagram', 'بازدید ریلز', 'افزایشِ بازدیدِ ویدیو و ریلز', '▶️', 'ارزان', 'unit', 12000, 1000, 'بازدید', 500, 5000000, 500, 'ig_link', 'smm', ''];
    $P[] = ['instagram', 'بازدید استوری', 'بازدید برای همه‌ی استوری‌های فعال', '👁', '', 'unit', 35000, 1000, 'بازدید', 100, 100000, 100, 'ig_link', 'smm', ''];
    $P[] = ['instagram', 'کامنت فارسی دلخواه', 'متنِ کامنت‌ها را در توضیحات بنویسید', '💬', 'ویژه', 'unit', 1900000, 1000, 'کامنت', 10, 2000, 5, 'ig_link', 'manual', ''];
    $P[] = ['telegram', 'ممبر کانال تلگرام', 'افزایشِ عضوِ کانال یا گروهِ عمومی', '👥', 'پرفروش', 'unit', 250000, 1000, 'ممبر', 100, 100000, 100, 'tg_link', 'smm', ''];
    $P[] = ['telegram', 'بازدید پست تلگرام', 'افزایشِ بازدیدِ پست‌های کانال', '👁', 'ارزان', 'unit', 8000, 1000, 'بازدید', 500, 1000000, 500, 'tg_link', 'smm', ''];
    $P[] = ['telegram', 'ری‌اکشن پست', 'ری‌اکشن‌های مثبتِ ترکیبی روی پست', '🔥', '', 'unit', 140000, 1000, 'ری‌اکشن', 50, 100000, 50, 'tg_link', 'smm', ''];
    $P[] = ['telegram', 'بازدید استوری تلگرام', 'بازدید برای استوریِ کانال یا کاربر', '📖', '', 'unit', 90000, 1000, 'بازدید', 100, 500000, 100, 'tg_link', 'smm', ''];

    $ip = $pdo->prepare('INSERT INTO products (cat_id, title, descr, emoji, badge, pricing, price, per, unit, qmin, qmax, qstep, field, provider, provider_ref, sort, active, created)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
    $sort = [];
    foreach ($P as $p) {
        [$c, $t, $d, $em, $b, $pricing, $pr, $per, $unit, $mn, $mx, $st, $field, $prov, $ref] = $p;
        $sort[$c] = ($sort[$c] ?? 0) + 1;
        $ip->execute([$ids[$c], $t, $d, $em, $b, $pricing, $pr, $per, $unit, $mn, $mx, $st, $field, $prov, $ref, $sort[$c], $now]);
    }

    $pdo->prepare("INSERT INTO settings (k, v) VALUES ('seeded', ?)")->execute([(string)$now]);
}

function ui_amt($n) {
    if ((int)$n <= 0) return '<span class="ask">استعلام قیمت</span>';
    return '<b class="amt">' . fa($n) . '</b> <small class="cur">تومان</small>';
}

function ui_orb($kind) {
    return ['stars' => 'o-gold', 'premium' => 'o-vio', 'gifts' => 'o-pink', 'numbers' => 'o-grn', 'instagram' => 'o-ig', 'telegram' => 'o-tg'][$kind] ?? 'o-tg';
}

function ui_buy($p, $label = 'خرید', $cls = 'b-glass') {
    return '<a class="btn ' . $cls . ' b-block" href="' . e(url('buy', ['id' => $p['id']])) . '">' . e($label) . ' ' . ic('arrow') . '</a>';
}

function ui_card($p, $kind) {
    $badge = $p['badge'] !== '' ? '<span class="tag' . ($kind === 'stars' ? ' gold' : '') . '">' . e($p['badge']) . '</span>' : '';
    if ($kind === 'stars') {
        if ($p['pricing'] === 'unit') return '';
        return '<article class="pk g lift rv"><div class="pk-top"><span class="pk-star">' . ic('star') . '</span>' . $badge . '</div>'
             . '<div class="pk-q">' . e($p['title']) . '</div><div>' . ui_amt($p['price']) . '</div>'
             . '<div class="muted sm">' . e($p['descr']) . '</div>' . ui_buy($p) . '</article>';
    }
    if ($kind === 'premium') {
        return '<article class="plan g lift rv' . ($p['badge'] !== '' && str_contains($p['badge'], 'بهترین') ? ' hot' : '') . '">'
             . ($p['badge'] !== '' && str_contains($p['badge'], 'بهترین') ? '<i class="ring"></i><span class="plan-badge">' . e($p['badge']) . '</span>' : ($badge !== '' ? '<div class="plan-tag">' . $badge . '</div>' : ''))
             . '<span class="orb o-vio">' . ic('crown') . '</span><h3>' . e($p['title']) . '</h3><p class="muted">' . e($p['descr']) . '</p>'
             . '<div class="plan-price">' . ui_amt($p['price']) . '</div>'
             . '<ul><li>' . ic('check') . ' فعال‌سازی با آیدی، بدون رمز</li><li>' . ic('check') . ' قابل خرید برای دوستان</li><li>' . ic('check') . ' همه‌ی امکاناتِ پریمیوم</li></ul>'
             . ui_buy($p, 'خرید اشتراک', str_contains($p['badge'], 'بهترین') ? 'b-pri' : 'b-glass') . '</article>';
    }
    if ($kind === 'gifts') {
        return '<article class="gift g lift rv"><div class="gift-e">' . e($p['emoji'] ?: '🎁') . '</div><h3>' . e($p['title']) . '</h3>'
             . '<span class="st">' . ic('star') . e($p['descr']) . '</span><div class="gp">' . ui_amt($p['price']) . '</div>' . ui_buy($p, 'ارسال گیفت') . '</article>';
    }
    if ($kind === 'numbers') {
        return '<a class="ctry g lift" data-name="' . e(mb_strtolower($p['title'])) . '" href="' . e(url('buy', ['id' => $p['id']])) . '">'
             . '<span class="flag">' . e($p['emoji'] ?: '🌍') . '</span><span class="ctry-t"><b>' . e($p['title']) . '</b><span>' . e($p['descr']) . '</span></span>'
             . '<span class="ctry-p">' . ((int)$p['price'] > 0 ? '<b class="amt">' . fa($p['price']) . '</b>تومان' : '<span class="ask">استعلام</span>') . '</span></a>';
    }
    $orb = ui_orb($kind);
    return '<article class="svc g lift rv"><div class="svc-top"><span class="emo">' . e($p['emoji'] ?: '✨') . '</span>' . $badge . '</div>'
         . '<h3>' . e($p['title']) . '</h3><p class="muted">' . e($p['descr']) . '</p>'
         . '<div class="svc-price">' . ui_amt($p['price']) . ($p['pricing'] === 'unit' ? '<span class="per">هر ' . fa($p['per']) . ' ' . e($p['unit'] ?: 'عدد') . '</span>' : '') . '</div>'
         . ($p['pricing'] === 'unit' ? '<div class="range">' . ic('tag') . ' حداقل ' . fa($p['qmin']) . ((int)$p['qmax'] > (int)$p['qmin'] ? ' · حداکثر ' . fa($p['qmax']) : '') . '</div>' : '')
         . ui_buy($p, 'ثبت سفارش', $kind === 'instagram' ? 'b-ig' : 'b-glass') . '</article>';
}

function ui_star_calc($p, $big = true) {
    if (!$p || $p['pricing'] !== 'unit') return '';
    $v = max((int)$p['qmin'], 500);
    ob_start(); ?>
    <form class="calc g blur rv" method="get" action="index.php" data-calc data-price="<?= (int)$p['price'] ?>" data-per="<?= max(1, (int)$p['per']) ?>" data-min="<?= (int)$p['qmin'] ?>" data-max="<?= (int)$p['qmax'] ?>">
      <i class="ring"></i>
      <input type="hidden" name="p" value="buy"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
      <h3><?= ic('star') ?> استارز به مقدارِ دلخواه</h3>
      <label class="lbl" for="calcQty">چند استارز می‌خواهید؟</label>
      <div class="qrow">
        <button type="button" data-step="50" aria-label="بیشتر">+</button>
        <input id="calcQty" class="inp num" type="number" name="qty" inputmode="numeric" min="<?= (int)$p['qmin'] ?>" max="<?= (int)$p['qmax'] ?>" value="<?= $v ?>">
        <button type="button" data-step="-50" aria-label="کمتر">−</button>
      </div>
      <input type="range" min="<?= (int)$p['qmin'] ?>" max="10000" step="50" value="<?= $v ?>" data-range aria-label="تعداد">
      <div class="chips"><?php foreach ([50, 100, 250, 500, 1000, 5000] as $q): if ($q < (int)$p['qmin']) continue; ?><button type="button" class="chip" data-q="<?= $q ?>"><?= fa($q) ?></button><?php endforeach; ?></div>
      <div class="calc-total"><span class="muted">مبلغ</span><b><span data-total>—</span> <small>تومان</small></b><span class="muted sm">هر استارز <?= toman($p['price'] / max(1, (int)$p['per'])) ?></span></div>
      <button class="btn b-gold b-lg b-block" type="submit"><?= ic('bolt') ?> ادامه‌ی خرید</button>
    </form>
    <?php return ob_get_clean();
}

function remember_order($code) {
    $_SESSION['my_orders'] = array_slice(array_unique(array_merge([(string)$code], (array)($_SESSION['my_orders'] ?? []))), 0, 50);
}
function can_view_order($o, $me) {
    if (!$o) return false;
    if ((int)$o['user_id'] > 0) return $me && (int)$me['id'] === (int)$o['user_id'];
    return in_array($o['code'], (array)($_SESSION['my_orders'] ?? []), true);
}
function pay_methods($me) {
    $m = [];
    if ($me && wallet_on()) $m['wallet'] = 'کیف پول';
    if (zp_on()) $m['zarinpal'] = 'درگاه آنلاین';
    if (card_on()) $m['card'] = 'کارت به کارت';
    return $m;
}
function start_payment($o, $method, $me) {
    if ($method === 'wallet') {
        if (!$me) { flash('err', 'برای پرداخت از کیف پول وارد حسابتان شوید.'); redirect(url('login')); }
        [$ok, $err] = pay_wallet($o, $me);
        flash($ok ? 'ok' : 'err', $ok ? 'پرداخت از کیف پول انجام شد.' : $err);
        redirect(url('order', ['code' => $o['code']]));
    }
    if ($method === 'zarinpal') {
        [$ok, $res] = zp_start($o);
        if ($ok) redirect($res);
        flash('err', $res);
        redirect(url('order', ['code' => $o['code']]));
    }
    order_set($o['id'], ['method' => 'card']);
    redirect(url('order', ['code' => $o['code']]));
}

function ns_site() {
    session_boot();
    security_headers();
    header('Content-Type: text/html; charset=utf-8');

    $page = preg_replace('/[^a-z_]/', '', (string)($_GET['p'] ?? 'home')) ?: 'home';
    $me = user_current();

    switch ($page) {

    case 'home':
        $cats = categories();
        $byKind = [];
        foreach ($cats as $c) $byKind[$c['kind']] = $c + ['items' => products($c['id']), 'from' => category_from_price($c['id'])];
        view('home', ['title' => '', 'cats' => $cats, 'K' => $byKind, 'me' => $me]);
        break;

    case 'cat':
        $c = category((string)($_GET['slug'] ?? ''));
        if (!$c || !(int)$c['active']) { http_response_code(404); view('404', ['title' => 'پیدا نشد', 'me' => $me]); break; }
        view('cat', ['title' => $c['title'], 'c' => $c, 'items' => products($c['id']), 'me' => $me]);
        break;

    case 'buy':
        $p = product((int)($_GET['id'] ?? 0));
        if (!$p) { http_response_code(404); view('404', ['title' => 'پیدا نشد', 'me' => $me]); break; }
        $methods = pay_methods($me);
        $errors = [];
        $old = ['qty' => (string)($_GET['qty'] ?? $p['qmin']), 'target' => '', 'note' => '', 'mobile' => '', 'name' => '', 'method' => array_key_first($methods) ?? ''];
        if (is_post()) {
            csrf_check();
            foreach ($old as $k => $v) $old[$k] = input($k, $v);
            if (!throttle_ok('buy:' . client_ip(), 30, 600)) $errors[] = 'تعدادِ سفارش‌ها زیاد است؛ چند دقیقه بعد امتحان کنید.';
            $qty = qty_clean($p, $old['qty'], $e1); if ($e1) $errors[] = $e1;
            $target = field_clean($p['field'], $old['target'], $e2); if ($e2) $errors[] = $e2;
            $contact = $me ? $me['mobile'] : norm_mobile($old['mobile']);
            if ($contact === '') $errors[] = 'شماره موبایل معتبر وارد کنید (مثلا ۰۹۱۲۳۴۵۶۷۸۹).';
            if (!isset($methods[$old['method']])) $errors[] = 'روشِ پرداخت را انتخاب کنید.';
            $amount = product_total($p, $qty ?: 1);
            if ($amount <= 0) $errors[] = 'قیمتِ این خدمت هنوز تعیین نشده؛ با پشتیبانی تماس بگیرید.';
            elseif ($old['method'] === 'wallet' && $me && (int)$me['balance'] < $amount)
                $errors[] = 'موجودیِ کیف پول (' . toman($me['balance']) . ') کافی نیست؛ روشِ دیگری انتخاب کنید یا از «حساب من» کیف پول را شارژ کنید.';
            if (!$errors) {
                $o = order_create([
                    'user_id' => $me ? (int)$me['id'] : null, 'product_id' => (int)$p['id'], 'kind' => 'product',
                    'title' => $p['title'], 'qty' => $qty, 'amount' => $amount, 'target' => $target,
                    'note' => mb_substr($old['note'], 0, 500), 'name' => mb_substr($me ? $me['name'] : $old['name'], 0, 80),
                    'contact' => $contact, 'method' => $old['method'],
                ]);
                remember_order($o['code']);
                start_payment($o, $old['method'], $me);
            }
        }
        view('buy', ['title' => 'خرید ' . $p['title'], 'p' => $p, 'methods' => $methods, 'errors' => $errors, 'old' => $old, 'me' => $me]);
        break;

    case 'order':
        $o = order_by_code((string)($_GET['code'] ?? ''));
        if (!can_view_order($o, $me)) {
            if ($o && (int)$o['user_id'] > 0 && !$me) { flash('err', 'این سفارش مالِ یک حساب است؛ اول وارد شوید.'); redirect(url('login', ['next' => 'order:' . $o['code']])); }
            flash('err', 'سفارش پیدا نشد. کدِ سفارش و شماره موبایل را وارد کنید.');
            redirect(url('track'));
        }
        if (is_post()) {
            csrf_check();
            $op = input('op');
            if ($op === 'pay' && $o['status'] === 'pending') start_payment($o, input('method'), $me);
            if ($op === 'receipt') {
                if (!throttle_ok('rcpt:' . $o['id'], 10, 3600)) flash('err', 'تعدادِ آپلود زیاد است؛ کمی بعد امتحان کنید.');
                else { [$ok, $err] = receipt_save($o, $_FILES['receipt'] ?? null, input('rnote')); flash($ok ? 'ok' : 'err', $ok ? 'رسید ثبت شد؛ بعد از بررسی، سفارش انجام می‌شود.' : $err); }
            }
            if ($op === 'cancel' && $o['status'] === 'pending') { order_set($o['id'], ['status' => 'canceled']); flash('ok', 'سفارش لغو شد.'); }
            if ($op === 'numcancel') { [$ok, $err] = number_cancel($o); flash($ok ? 'ok' : 'err', $ok ? 'شماره لغو شد.' : $err); }
            redirect(url('order', ['code' => $o['code']]));
        }
        $o = provider_refresh($o);
        view('order', ['title' => 'سفارش ' . $o['code'], 'o' => $o, 'p' => $o['product_id'] ? product($o['product_id'], false) : null,
                       'methods' => pay_methods($me), 'me' => $me]);
        break;

    case 'state':
        $o = order_by_code((string)($_GET['code'] ?? ''));
        if (!can_view_order($o, $me)) json_out(['ok' => false], 404);
        $o = provider_refresh($o);
        $st = provider_state($o);
        json_out(['ok' => true, 'status' => $o['status'], 'label' => status_label($o['status']), 'cls' => status_cls($o['status']),
                  'delivery' => (string)$o['delivery'], 'phone' => (string)($st['phone'] ?? ''), 'code' => (string)($st['code'] ?? '')]);

    case 'zp':
        $o = order_by_code((string)($_GET['code'] ?? ''));
        if (!$o) redirect(url());
        remember_order($o['code']);
        if ($o['status'] === 'pending' && $o['method'] === 'zarinpal') {
            if (($_GET['Status'] ?? '') !== 'OK') flash('err', 'پرداخت انجام نشد یا لغو شد. دوباره امتحان کنید.');
            else {
                [$ok, $ref] = zp_verify($o, (string)($_GET['Authority'] ?? ''));
                if ($ok && order_mark_paid($o, 'zarinpal', $ref)) flash('ok', 'پرداخت با موفقیت انجام شد. کد پیگیری: ' . fa_digits($ref));
                elseif (!$ok) flash('err', $ref);
            }
        }
        redirect(url('order', ['code' => $o['code']]));

    case 'track':
        $err = '';
        if (is_post()) {
            csrf_check();
            if (!throttle_ok('track:' . client_ip(), 20, 600)) $err = 'تعدادِ تلاش زیاد است؛ کمی بعد امتحان کنید.';
            else {
                $o = order_by_code(input('code'));
                $m = norm_mobile(input('mobile'));
                if ($o && (int)$o['user_id'] > 0) { flash('err', 'این سفارش با حساب ثبت شده؛ وارد حسابتان شوید.'); redirect(url('login', ['next' => 'order:' . $o['code']])); }
                if ($o && $m !== '' && hash_equals((string)$o['contact'], $m)) { remember_order($o['code']); redirect(url('order', ['code' => $o['code']])); }
                $err = 'سفارشی با این کد و شماره پیدا نشد.';
            }
        }
        view('track', ['title' => 'پیگیری سفارش', 'err' => $err, 'me' => $me]);
        break;

    case 'login':
    case 'register':
        if ($me) redirect(url('account'));
        $err = ''; $old = ['name' => input('name'), 'mobile' => input('mobile')];
        $next = preg_replace('/[^A-Za-z0-9:]/', '', input('next'));
        if (is_post()) {
            csrf_check();
            $mobile = norm_mobile(input('mobile'));
            $pass = (string)($_POST['pass'] ?? '');
            if (!throttle_ok('auth:' . client_ip(), 15, 900)) $err = 'تعدادِ تلاش زیاد است؛ ۱۵ دقیقه بعد امتحان کنید.';
            elseif ($mobile === '') $err = 'شماره موبایل معتبر نیست.';
            elseif ($page === 'register') {
                $name = mb_substr(trim(input('name')), 0, 60);
                if (mb_strlen($name) < 2) $err = 'نامتان را وارد کنید.';
                elseif (mb_strlen($pass) < 6) $err = 'رمز باید حداقل ۶ نویسه باشد.';
                elseif ($pass !== (string)($_POST['pass2'] ?? '')) $err = 'تکرارِ رمز یکی نیست.';
                elseif (val('SELECT 1 FROM users WHERE mobile = ?', [$mobile])) $err = 'با این شماره قبلا ثبت‌نام شده؛ وارد شوید.';
                else {
                    $id = insert('users', ['name' => $name, 'mobile' => $mobile, 'pass' => password_hash($pass, PASSWORD_DEFAULT), 'created' => time()]);
                    user_login_as(row('SELECT * FROM users WHERE id = ?', [$id]));
                    flash('ok', 'خوش آمدید، ' . $name . '!');
                }
            } else {
                $u = row('SELECT * FROM users WHERE mobile = ?', [$mobile]);
                if (!$u || !password_verify($pass, $u['pass'])) $err = 'شماره یا رمز اشتباه است.';
                elseif ((int)$u['blocked']) $err = 'دسترسیِ این حساب مسدود است.';
                else { user_login_as($u); throttle_clear('auth:' . client_ip()); }
            }
            if ($err === '') {
                if (str_starts_with($next, 'order:')) redirect(url('order', ['code' => substr($next, 6)]));
                redirect(url('account'));
            }
        }
        view('auth', ['title' => $page === 'register' ? 'ثبت‌نام' : 'ورود', 'mode' => $page, 'err' => $err, 'old' => $old, 'next' => $next, 'me' => $me]);
        break;

    case 'logout':
        if (is_post()) { csrf_check(); user_logout(); flash('ok', 'از حسابتان خارج شدید.'); }
        redirect(url());

    case 'account':
        if (!$me) redirect(url('login'));
        $err = '';
        if (is_post()) {
            csrf_check();
            $op = input('op');
            if ($op === 'topup') {
                $amt = (int)ns_digits(str_replace([',', '٬'], '', input('amount')));
                $min = max(1000, (int)setting('topup_min', '50000'));
                $methods = array_diff_key(pay_methods($me), ['wallet' => 1]);
                $method = input('method');
                if (!wallet_on()) $err = 'کیف پول فعال نیست.';
                elseif ($amt < $min) $err = 'حداقلِ شارژ ' . toman($min) . ' است.';
                elseif ($amt > 200000000) $err = 'مبلغ خیلی زیاد است.';
                elseif (!isset($methods[$method])) $err = 'روشِ پرداخت را انتخاب کنید.';
                else {
                    $o = order_create(['user_id' => (int)$me['id'], 'kind' => 'topup', 'title' => 'شارژ کیف پول', 'qty' => 1, 'amount' => $amt,
                                       'name' => $me['name'], 'contact' => $me['mobile'], 'method' => $method]);
                    start_payment($o, $method, $me);
                }
            }
            if ($op === 'password') {
                $cur = (string)($_POST['cur'] ?? ''); $new = (string)($_POST['new'] ?? '');
                if (!password_verify($cur, $me['pass'])) $err = 'رمزِ فعلی اشتباه است.';
                elseif (mb_strlen($new) < 6) $err = 'رمزِ تازه باید حداقل ۶ نویسه باشد.';
                else {
                    update('users', ['pass' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [(int)$me['id']]);
                    user_login_as(row('SELECT * FROM users WHERE id = ?', [(int)$me['id']]));
                    flash('ok', 'رمز عوض شد.'); redirect(url('account'));
                }
            }
        }
        view('account', ['title' => 'حساب من', 'me' => $me, 'err' => $err,
            'orders' => rows('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 50', [(int)$me['id']]),
            'txs' => rows('SELECT * FROM wallet_tx WHERE user_id = ? ORDER BY id DESC LIMIT 30', [(int)$me['id']]),
            'methods' => array_diff_key(pay_methods($me), ['wallet' => 1])]);
        break;

    case 'page':
        $k = (string)($_GET['k'] ?? '');
        $pages = ['terms' => 'قوانین و مقررات', 'about' => 'درباره ما', 'contact' => 'تماس با ما'];
        if (!isset($pages[$k])) { http_response_code(404); view('404', ['title' => 'پیدا نشد', 'me' => $me]); break; }
        view('page', ['title' => $pages[$k], 'k' => $k, 'me' => $me]);
        break;

    default:
        http_response_code(404);
        view('404', ['title' => 'پیدا نشد', 'me' => $me]);
    }
}

function admin_secret() {
    $h = (string)setting('admin_hash');
    return $h !== '' ? $h : 'cfg:' . hash('sha256', (string)cfg('admin_password', ''));
}
function admin_ok() {
    return !empty($_SESSION['adm']) && hash_equals((string)$_SESSION['adm'], hash('sha256', admin_secret() . '|' . cfg('secret', '')));
}
function admin_check_pass($p) {
    $h = (string)setting('admin_hash');
    if ($h !== '') return password_verify($p, $h);
    $c = (string)cfg('admin_password', '');
    return $c !== '' && $c !== 'change-me' && hash_equals($c, $p);
}
function aview($name, array $vars = []) {
    $vars['A'] = $GLOBALS['a'];
    $vars['attn'] = (int)val("SELECT COUNT(*) FROM orders WHERE status IN ('review', 'paid', 'failed')");
    view('admin/' . $name, $vars, 'admin/layout');
}

function ns_admin() {
    session_boot();
    security_headers();
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');

    $a = preg_replace('/[^a-z_]/', '', (string)($_GET['a'] ?? 'dashboard')) ?: 'dashboard';
    $GLOBALS['a'] = $a;

    if ($a === 'login') {
        $err = '';
        $ready = (string)setting('admin_hash') !== '' || !in_array((string)cfg('admin_password', ''), ['', 'change-me'], true);
        if (!$ready) $err = 'رمزِ پنل تنظیم نشده است؛ بالای فایلِ ' . NS_SELF . ' مقدارِ admin_password را عوض کنید.';
        elseif (is_post()) {
            csrf_check();
            if (!throttle_ok('adm:' . client_ip(), 8, 900)) $err = 'تلاشِ زیاد؛ ۱۵ دقیقه بعد امتحان کنید.';
            elseif (!admin_check_pass((string)($_POST['pass'] ?? ''))) { usleep(400000); $err = 'رمز اشتباه است.'; }
            else {
                session_regenerate_id(true);
                $_SESSION['adm'] = hash('sha256', admin_secret() . '|' . cfg('secret', ''));
                $_SESSION['csrf'] = bin2hex(random_bytes(16));
                throttle_clear('adm:' . client_ip());
                redirect(aurl());
            }
        }
        view('admin/login', ['title' => 'ورود مدیر', 'err' => $err], null);
        exit;
    }
    if (!admin_ok()) redirect(aurl('login'));
    if (is_post()) csrf_check();

    switch ($a) {

    case 'logout':
        if (is_post()) { unset($_SESSION['adm']); session_regenerate_id(true); }
        redirect(aurl('login'));

    case 'dashboard':
        if (is_post() && input('op') === 'seed_ok') { settings_save(['seed_review' => '0']); redirect(aurl()); }
        if (is_post() && input('op') === 'expire') { $n = orders_expire(); flash('ok', fa($n) . ' سفارشِ پرداخت‌نشده‌ی کهنه لغو شد.'); redirect(aurl()); }
        $day = strtotime('today');
        $sum = fn($from) => (int)val("SELECT COALESCE(SUM(amount), 0) FROM orders WHERE kind = 'product' AND paid_at >= ? AND status IN ('paid','processing','completed')", [$from]);
        $stats = [
            'today' => $sum($day), 'week' => $sum($day - 6 * 86400), 'month' => $sum($day - 29 * 86400),
            'orders_today' => (int)val("SELECT COUNT(*) FROM orders WHERE kind = 'product' AND paid_at >= ?", [$day]),
            'users' => (int)val('SELECT COUNT(*) FROM users'),
            'review' => (int)val("SELECT COUNT(*) FROM orders WHERE status = 'review'"),
            'queue' => (int)val("SELECT COUNT(*) FROM orders WHERE status = 'paid'"),
            'proc' => (int)val("SELECT COUNT(*) FROM orders WHERE status = 'processing'"),
            'failed' => (int)val("SELECT COUNT(*) FROM orders WHERE status = 'failed'"),
            'wallets' => (int)val('SELECT COALESCE(SUM(balance), 0) FROM users'),
        ];
        $chart = [];
        for ($i = 13; $i >= 0; $i--) { $d0 = $day - $i * 86400; $chart[] = [$d0, (int)val("SELECT COALESCE(SUM(amount), 0) FROM orders WHERE kind = 'product' AND paid_at >= ? AND paid_at < ? AND status IN ('paid','processing','completed')", [$d0, $d0 + 86400])]; }
        $recent = rows('SELECT * FROM orders ORDER BY id DESC LIMIT 8');
        aview('dashboard', ['title' => 'داشبورد', 'S' => $stats, 'chart' => $chart, 'recent' => $recent]);
        break;

    case 'orders':
        $st = (string)($_GET['st'] ?? '');
        $qq = trim((string)($_GET['q'] ?? ''));
        $pg = max(1, (int)($_GET['pg'] ?? 1));
        $w = []; $p = [];
        if ($st === 'attn') $w[] = "status IN ('review', 'paid', 'failed')";
        elseif (isset(order_statuses()[$st])) { $w[] = 'status = ?'; $p[] = $st; }
        if ($qq !== '') { $w[] = '(code LIKE ? OR target LIKE ? OR contact LIKE ? OR title LIKE ?)'; $like = '%' . ns_digits($qq) . '%'; array_push($p, $like, $like, $like, $like); }
        $where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
        $total = (int)val('SELECT COUNT(*) FROM orders' . $where, $p);
        $list = rows('SELECT * FROM orders' . $where . ' ORDER BY id DESC LIMIT 30 OFFSET ' . (($pg - 1) * 30), $p);
        aview('orders', ['title' => 'سفارش‌ها', 'list' => $list, 'st' => $st, 'qq' => $qq, 'pg' => $pg, 'pages' => max(1, (int)ceil($total / 30)), 'total' => $total]);
        break;

    case 'order':
        $o = order_get((int)($_GET['id'] ?? 0));
        if (!$o) { flash('err', 'سفارش پیدا نشد.'); redirect(aurl('orders')); }
        if (is_post()) {
            $op = input('op');
            if ($op === 'approve' && $o['status'] === 'review') { order_mark_paid($o, 'card', 'CARD'); flash('ok', 'رسید تایید شد.'); }
            if ($op === 'reject' && $o['status'] === 'review') { order_set($o['id'], ['status' => 'pending', 'delivery' => 'رسید تایید نشد: ' . mb_substr(input('why') ?: 'رسید نامعتبر بود', 0, 200)]); flash('ok', 'رسید رد شد.'); }
            if ($op === 'save') { order_set($o['id'], ['delivery' => mb_substr(input('delivery'), 0, 2000), 'admin_note' => mb_substr(input('admin_note'), 0, 2000)]); flash('ok', 'ذخیره شد.'); }
            if ($op === 'processing' && in_array($o['status'], ['paid', 'failed'], true)) { order_set($o['id'], ['status' => 'processing']); flash('ok', 'در حالِ انجام.'); }
            if ($op === 'complete' && in_array($o['status'], ['paid', 'processing', 'failed'], true)) { order_set($o['id'], ['status' => 'completed', 'delivery' => input('delivery') !== '' ? mb_substr(input('delivery'), 0, 2000) : ($o['delivery'] ?: 'سفارش انجام شد.')]); flash('ok', 'تکمیل شد.'); }
            if ($op === 'cancel' && in_array($o['status'], ['pending', 'review'], true)) { order_set($o['id'], ['status' => 'canceled']); flash('ok', 'لغو شد.'); }
            if ($op === 'refund') {
                $ok = order_refund_wallet($o, input('why'));
                flash($ok ? 'ok' : 'err', $ok ? 'مبلغ به کیف پولِ کاربر برگشت.'
                    : ((int)$o['user_id'] <= 0 ? 'خریدار حساب ندارد؛ وجه را دستی برگردانید و «ثبتِ برگشتِ دستی» را بزنید.' : 'برگشت ممکن نبود؛ قبلا برگشت داده شده یا وضعیتِ سفارش اجازه نمی‌دهد.'));
            }
            if ($op === 'manual_refund' && in_array($o['status'], ['paid', 'processing', 'failed'], true)) { order_set($o['id'], ['status' => 'refunded', 'admin_note' => trim($o['admin_note'] . "\nبرگشتِ وجهِ دستی: " . input('why'))]); flash('ok', 'به‌عنوانِ «برگشت داده شد» ثبت شد.'); }
            if ($op === 'dispatch' && $o['status'] === 'paid') { provider_dispatch($o); flash('ok', 'ارسال به سرویس‌دهنده انجام شد — نتیجه را ببینید.'); }
            if ($op === 'refresh') { provider_refresh($o, true); flash('ok', 'وضعیت از سرویس‌دهنده پرسیده شد.'); }
            redirect(aurl('order', ['id' => $o['id']]));
        }
        $u = $o['user_id'] ? row('SELECT * FROM users WHERE id = ?', [(int)$o['user_id']]) : null;
        aview('order', ['title' => 'سفارش ' . $o['code'], 'o' => $o, 'u' => $u, 'p' => $o['product_id'] ? product($o['product_id'], false) : null]);
        break;

    case 'receipt':
        $o = order_get((int)($_GET['id'] ?? 0));
        $f = $o && $o['receipt'] ? NS_STORAGE . '/uploads/' . basename($o['receipt']) : '';
        if ($f === '' || !is_file($f)) { http_response_code(404); exit('not found'); }
        $mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][strtolower(pathinfo($f, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($f));
        header('Cache-Control: private, max-age=600');
        readfile($f);
        exit;

    case 'products':
        if (is_post()) {
            $id = (int)input('id');
            if (input('op') === 'toggle') { q('UPDATE products SET active = 1 - active WHERE id = ?', [$id]); }
            if (input('op') === 'delete') {
                if (val('SELECT 1 FROM orders WHERE product_id = ?', [$id])) { q('UPDATE products SET active = 0 WHERE id = ?', [$id]); flash('ok', 'این محصول سفارش دارد؛ به‌جای حذف، غیرفعال شد.'); }
                else { q('DELETE FROM products WHERE id = ?', [$id]); flash('ok', 'حذف شد.'); }
            }
            redirect(aurl('products', ['cat' => (int)($_GET['cat'] ?? 0)]));
        }
        $cats = categories(false);
        $cf = (int)($_GET['cat'] ?? 0);
        $list = [];
        foreach ($cats as $c) if (!$cf || (int)$c['id'] === $cf) $list[] = $c + ['items' => products($c['id'], false)];
        aview('products', ['title' => 'محصولات', 'cats' => $cats, 'list' => $list, 'cf' => $cf]);
        break;

    case 'product':
        $id = (int)($_GET['id'] ?? 0);
        $p = $id ? row('SELECT * FROM products WHERE id = ?', [$id]) : null;
        $cats = categories(false);
        $err = '';
        $d = $p ?: ['cat_id' => (int)($_GET['cat'] ?? ($cats[0]['id'] ?? 0)), 'title' => '', 'descr' => '', 'emoji' => '', 'badge' => '', 'pricing' => 'fixed', 'price' => 0, 'per' => 1,
                    'unit' => '', 'qmin' => 1, 'qmax' => 1, 'qstep' => 1, 'field' => 'tg_user', 'provider' => 'manual', 'provider_ref' => '', 'sort' => 0, 'active' => 1];
        if (is_post()) {
            $n = fn($k) => (int)ns_digits(str_replace([',', '٬'], '', input($k, '0')));
            $d = [
                'cat_id' => $n('cat_id'), 'title' => mb_substr(input('title'), 0, 120), 'descr' => mb_substr(input('descr'), 0, 400),
                'emoji' => mb_substr(input('emoji'), 0, 8), 'badge' => mb_substr(input('badge'), 0, 30),
                'pricing' => input('pricing') === 'unit' ? 'unit' : 'fixed', 'price' => $n('price'), 'per' => max(1, $n('per')),
                'unit' => mb_substr(input('unit'), 0, 30), 'qmin' => max(1, $n('qmin')), 'qmax' => max(1, $n('qmax')), 'qstep' => max(1, $n('qstep')),
                'field' => array_key_exists(input('field'), field_types()) ? input('field') : 'none',
                'provider' => in_array(input('provider'), ['manual', 'smm', '5sim'], true) ? input('provider') : 'manual',
                'provider_ref' => mb_substr(input('provider_ref'), 0, 120), 'sort' => $n('sort'), 'active' => input('active') === '1' ? 1 : 0,
            ];
            if ($d['title'] === '') $err = 'عنوان را بنویسید.';
            elseif (!category($d['cat_id'])) $err = 'دسته را انتخاب کنید.';
            elseif ($d['price'] < 0) $err = 'قیمت نامعتبر است.';
            elseif ($d['pricing'] === 'unit' && $d['qmax'] < $d['qmin']) $err = 'حداکثر نباید از حداقل کمتر باشد.';
            else {
                if ($p) update('products', $d, 'id = ?', [$p['id']]);
                else { $d['created'] = time(); $id = insert('products', $d); }
                flash('ok', 'محصول ذخیره شد.');
                redirect(aurl('products', ['cat' => $d['cat_id']]));
            }
        }
        aview('product', ['title' => $p ? 'ویرایشِ محصول' : 'محصولِ تازه', 'd' => $d, 'p' => $p, 'cats' => $cats, 'err' => $err]);
        break;

    case 'cats':
        if (is_post()) {
            if (input('op') === 'save') {
                foreach ((array)($_POST['c'] ?? []) as $cid => $c) {
                    if (!is_array($c)) continue;
                    update('categories', ['title' => mb_substr(trim((string)($c['title'] ?? '')), 0, 80) ?: 'بی‌نام', 'subtitle' => mb_substr(trim((string)($c['subtitle'] ?? '')), 0, 200),
                                          'sort' => (int)ns_digits((string)($c['sort'] ?? 0)), 'active' => !empty($c['active']) ? 1 : 0], 'id = ?', [(int)$cid]);
                }
                flash('ok', 'دسته‌ها ذخیره شد.');
            }
            if (input('op') === 'add') {
                $slug = strtolower(preg_replace('/[^a-z0-9\-]/i', '', input('slug')));
                if ($slug === '' || category($slug)) flash('err', 'نامکِ لاتینِ یکتا لازم است (مثلا youtube).');
                else { insert('categories', ['slug' => $slug, 'kind' => 'other', 'title' => mb_substr(input('title') ?: $slug, 0, 80), 'subtitle' => '', 'icon' => 'bag', 'sort' => 99, 'active' => 1]); flash('ok', 'دسته اضافه شد.'); }
            }
            redirect(aurl('cats'));
        }
        aview('cats', ['title' => 'دسته‌ها', 'cats' => categories(false)]);
        break;

    case 'users':
        $qq = trim((string)($_GET['q'] ?? ''));
        $list = $qq !== '' ? rows('SELECT * FROM users WHERE name LIKE ? OR mobile LIKE ? ORDER BY id DESC LIMIT 100', ['%' . $qq . '%', '%' . ns_digits($qq) . '%'])
                           : rows('SELECT * FROM users ORDER BY id DESC LIMIT 100');
        aview('users', ['title' => 'کاربران', 'list' => $list, 'qq' => $qq]);
        break;

    case 'user':
        $u = row('SELECT * FROM users WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
        if (!$u) { flash('err', 'کاربر پیدا نشد.'); redirect(aurl('users')); }
        if (is_post()) {
            $op = input('op');
            if ($op === 'block') { update('users', ['blocked' => (int)$u['blocked'] ? 0 : 1], 'id = ?', [$u['id']]); flash('ok', (int)$u['blocked'] ? 'رفعِ مسدودی شد.' : 'مسدود شد.'); }
            if ($op === 'balance') {
                $amt = (int)ns_digits(str_replace([',', '٬'], '', input('amount')));
                if (input('dir') === 'sub') $amt = -$amt;
                if ($amt === 0) flash('err', 'مبلغ را وارد کنید.');
                elseif ($amt < 0 && !wallet_debit((int)$u['id'], -$amt, 'کسرِ مدیر: ' . input('why'))) flash('err', 'موجودی کافی نیست.');
                else { if ($amt > 0) wallet_credit((int)$u['id'], $amt, 'افزایشِ مدیر: ' . input('why')); flash('ok', 'موجودی به‌روز شد.'); }
            }
            if ($op === 'pass') {
                $np = (string)($_POST['np'] ?? '');
                if (mb_strlen($np) < 6) flash('err', 'رمز حداقل ۶ نویسه.');
                else { update('users', ['pass' => password_hash($np, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]); flash('ok', 'رمزِ کاربر عوض شد.'); }
            }
            redirect(aurl('user', ['id' => $u['id']]));
        }
        aview('user', ['title' => $u['name'], 'u' => $u,
            'orders' => rows('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 30', [(int)$u['id']]),
            'txs' => rows('SELECT * FROM wallet_tx WHERE user_id = ? ORDER BY id DESC LIMIT 30', [(int)$u['id']])]);
        break;

    case 'settings':
        $err = '';
        if (is_post()) {
            $keys = ['site_name', 'tagline', 'notice', 'support', 'channel', 'contact_text', 'card_number', 'card_holder', 'card_bank', 'zp_merchant',
                     'topup_min', 'smm_url', 'smm_key', 'fivesim_key', 'pending_hours', 'terms', 'about'];
            $save = [];
            foreach ($keys as $k) $save[$k] = mb_substr(trim((string)($_POST[$k] ?? '')), 0, $k === 'terms' || $k === 'about' || $k === 'contact_text' ? 5000 : 300);
            foreach (['card_on', 'zp_on', 'zp_sandbox', 'wallet_on', 'trust_proxy'] as $k) $save[$k] = !empty($_POST[$k]) ? '1' : '0';
            $save['support'] = ltrim($save['support'], '@');
            $save['channel'] = ltrim($save['channel'], '@');
            $save['topup_min'] = (string)max(1000, (int)ns_digits($save['topup_min']));
            $save['pending_hours'] = (string)max(1, (int)ns_digits($save['pending_hours']));
            if ($save['smm_url'] !== '' && !preg_match('#^https://#i', $save['smm_url'])) $err = 'آدرسِ API پنلِ SMM باید با https:// شروع شود.';
            $np = (string)($_POST['new_pass'] ?? '');
            if ($err === '' && $np !== '') {
                if (mb_strlen($np) < 8) $err = 'رمزِ تازه‌ی مدیر باید حداقل ۸ نویسه باشد.';
                else $save['admin_hash'] = password_hash($np, PASSWORD_DEFAULT);
            }
            if ($err === '') {
                settings_save($save);
                if (isset($save['admin_hash'])) $_SESSION['adm'] = hash('sha256', admin_secret() . '|' . cfg('secret', ''));
                flash('ok', 'تنظیمات ذخیره شد.');
                redirect(aurl('settings'));
            }
        }
        aview('settings', ['title' => 'تنظیمات', 'err' => $err, 'cron' => base_url() . '/' . NS_SELF . '?cron=' . cron_key()]);
        break;

    default:
        redirect(aurl());
    }
}


function ns_cron() {
    header('Content-Type: text/plain; charset=utf-8');
    if (!hash_equals(cron_key(), (string)($_GET['cron'] ?? ($_SERVER['argv'][1] ?? '')))) {
        http_response_code(403);
        exit('forbidden');
    }
    @set_time_limit(120);
    $expired = orders_expire();
    $n = 0;
    foreach (rows("SELECT * FROM orders WHERE status = 'processing' AND provider IN ('smm', '5sim') ORDER BY updated LIMIT 40") as $o) {
        provider_refresh($o, true);
        $n++;
    }
    echo 'ok expired=' . $expired . ' refreshed=' . $n . "\n";
}

function nsv_404(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><section class="sec">
  <div class="wrap narrow center">
    <div class="panel g blur nf">
      <div class="nf-code shine">۴۰۴</div>
      <h1>صفحه پیدا نشد</h1>
      <p class="muted">آدرس را بررسی کنید یا از صفحه‌ی اصلی ادامه دهید.</p>
      <a class="btn b-pri b-lg" href="<?= e(url()) ?>"><?= ic('home') ?> صفحه‌ی اصلی</a>
    </div>
  </div>
</section>
<?php
    return get_defined_vars();
}

function nsv__sprite(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-star" viewBox="0 0 24 24"><path d="M12 2.8l2.8 5.7 6.3.9-4.55 4.43 1.07 6.27L12 17.13 6.38 20.1l1.07-6.27L2.9 9.4l6.3-.9z"/></symbol>
  <symbol id="i-crown" viewBox="0 0 24 24"><path d="M3 7.5l4.5 4L12 5l4.5 6.5L21 7.5 19 18H5z"/><path d="M5 21h14"/></symbol>
  <symbol id="i-gift" viewBox="0 0 24 24"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C10 3 12 8 12 8s2-5 4.5-5a2.5 2.5 0 0 1 0 5"/></symbol>
  <symbol id="i-phone" viewBox="0 0 24 24"><rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M11 18h2"/></symbol>
  <symbol id="i-insta" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".6"/></symbol>
  <symbol id="i-megaphone" viewBox="0 0 24 24"><path d="M3 11v2a1 1 0 0 0 1 1h3l6 5V5L7 10H4a1 1 0 0 0-1 1z"/><path d="M16.5 8.5a5 5 0 0 1 0 7M19 6a8.5 8.5 0 0 1 0 12"/></symbol>
  <symbol id="i-heart" viewBox="0 0 24 24"><path d="M12 20.5s-7.5-4.6-9.2-9.3C1.6 7.8 3.8 4.5 7.2 4.5c2 0 3.6 1.1 4.8 2.8 1.2-1.7 2.8-2.8 4.8-2.8 3.4 0 5.6 3.3 4.4 6.7-1.7 4.7-9.2 9.3-9.2 9.3z"/></symbol>
  <symbol id="i-bag" viewBox="0 0 24 24"><path d="M5 8h14l-1 12.5H6z"/><path d="M9 10V6.5a3 3 0 0 1 6 0V10"/></symbol>
  <symbol id="i-bolt" viewBox="0 0 24 24"><path d="M13 2L4 14h7l-1 8 9-12h-7z"/></symbol>
  <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 2.5l8 3v6c0 5-3.4 8.7-8 10-4.6-1.3-8-5-8-10v-6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/></symbol>
  <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
  <symbol id="i-headset" viewBox="0 0 24 24"><path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="2.5" y="13" width="4" height="7" rx="1.5"/><rect x="17.5" y="13" width="4" height="7" rx="1.5"/><path d="M20 20c0 1.1-1.3 2-3 2h-3"/></symbol>
  <symbol id="i-wallet" viewBox="0 0 24 24"><path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H18v3"/><rect x="3" y="8" width="18" height="12" rx="2.5"/><circle cx="16.5" cy="14" r=".8"/></symbol>
  <symbol id="i-card" viewBox="0 0 24 24"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6.5 15h4"/></symbol>
  <symbol id="i-coin" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M14.5 9.5c-.4-1-1.4-1.5-2.5-1.5-1.5 0-2.5.8-2.5 2s1 1.6 2.5 2 2.5.8 2.5 2-1 2-2.5 2c-1.1 0-2.1-.5-2.5-1.5M12 6.5V8M12 16v1.5"/></symbol>
  <symbol id="i-check" viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></symbol>
  <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M19 12H5M11 6l-6 6 6 6"/></symbol>
  <symbol id="i-back" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
  <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></symbol>
  <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
  <symbol id="i-close" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></symbol>
  <symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></symbol>
  <symbol id="i-moon" viewBox="0 0 24 24"><path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11z"/></symbol>
  <symbol id="i-send" viewBox="0 0 24 24"><path d="M21.2 3.6L2.9 10.8c-.8.3-.8 1.5.1 1.8l4.6 1.5 1.8 5.5c.3.8 1.3 1 1.9.4l2.6-2.6 4.8 3.5c.7.5 1.6.1 1.8-.7l2.9-14.3c.2-.9-.7-1.7-1.6-1.3z"/><path d="M7.6 14.1L17.5 7.5l-7.4 8.1"/></symbol>
  <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></symbol>
  <symbol id="i-chat" viewBox="0 0 24 24"><path d="M4 5h16v11H9l-5 4z"/><path d="M8 9.5h8M8 12.5h5"/></symbol>
  <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/></symbol>
  <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></symbol>
  <symbol id="i-globe" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3z"/></symbol>
  <symbol id="i-lock" viewBox="0 0 24 24"><rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></symbol>
  <symbol id="i-refresh" viewBox="0 0 24 24"><path d="M20 11a8 8 0 0 0-14.6-4.5L4 8M4 4v4h4M4 13a8 8 0 0 0 14.6 4.5L20 16M20 20v-4h-4"/></symbol>
  <symbol id="i-up" viewBox="0 0 24 24"><path d="M12 19V5M6 11l6-6 6 6"/></symbol>
  <symbol id="i-spark" viewBox="0 0 24 24"><path d="M12 3l1.8 5.4L19 10l-5.2 1.6L12 17l-1.8-5.4L5 10l5.2-1.6z"/><path d="M19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8z"/></symbol>
  <symbol id="i-tag" viewBox="0 0 24 24"><path d="M3 12V4.5A1.5 1.5 0 0 1 4.5 3H12l9 9-9 9z"/><circle cx="8" cy="8" r="1.5"/></symbol>
  <symbol id="i-chev" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></symbol>
  <symbol id="i-help" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6v.6M12 17h.01"/></symbol>
  <symbol id="i-copy" viewBox="0 0 24 24"><rect x="8" y="8" width="12" height="12" rx="2.5"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></symbol>
  <symbol id="i-logout" viewBox="0 0 24 24"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 8l-4 4 4 4M6 12h10"/></symbol>
  <symbol id="i-list" viewBox="0 0 24 24"><path d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01"/></symbol>
  <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
  <symbol id="i-alert" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5h.01"/></symbol>
  <symbol id="i-upload" viewBox="0 0 24 24"><path d="M12 16V4M7 9l5-5 5 5M4 20h16"/></symbol>
  <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/></symbol>
  <symbol id="i-box" viewBox="0 0 24 24"><path d="M3.5 7.5L12 3l8.5 4.5v9L12 21l-8.5-4.5z"/><path d="M3.5 7.5L12 12l8.5-4.5M12 12v9"/></symbol>
  <symbol id="i-grid" viewBox="0 0 24 24"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></symbol>
  <symbol id="i-gear" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></symbol>
  <symbol id="i-chart" viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></symbol>
  <symbol id="i-edit" viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13.5 6.5l4 4"/></symbol>
  <symbol id="i-trash" viewBox="0 0 24 24"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></symbol>
</svg>
<?php
    return get_defined_vars();
}

function nsv_account(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><section class="page-head">
  <div class="wrap">
    <div class="ph-in">
      <span class="orb big o-tg av-big"><?= e(mb_substr($me['name'], 0, 1)) ?></span>
      <div><h1><?= e($me['name']) ?></h1><p class="muted" dir="ltr"><?= e(fa_digits($me['mobile'])) ?></p></div>
      <form method="post" action="<?= e(url('logout')) ?>" class="ph-act"><?= csrf_field() ?><button class="btn b-glass" type="submit"><?= ic('logout') ?> خروج</button></form>
    </div>
  </div>
</section>
<section class="sec tight">
  <div class="wrap acc-lay">
    <div class="acc-side">
      <div class="bal-card g blur">
        <i class="ring"></i>
        <span class="muted">موجودیِ کیف پول</span>
        <b><?= toman($me['balance']) ?></b>
        <?php if (wallet_on() && $methods): ?>
        <form method="post" class="topup"><?= csrf_field() ?><input type="hidden" name="op" value="topup">
          <?php if ($err !== ''): ?><div class="alert a-err"><?= ic('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>
          <label class="field"><span>مبلغِ شارژ (تومان)</span><input class="inp num" type="number" name="amount" min="<?= (int)setting('topup_min') ?>" step="1000" value="<?= max(100000, (int)setting('topup_min')) ?>" required></label>
          <div class="chips"><?php foreach ([100000, 200000, 500000, 1000000] as $v): ?><button type="button" class="chip" data-set="amount" data-v="<?= $v ?>"><?= fa($v) ?></button><?php endforeach; ?></div>
          <div class="pms compact">
            <?php $first = true; foreach ($methods as $k => $lbl): ?>
            <label class="pm"><input type="radio" name="method" value="<?= e($k) ?>"<?= $first ? ' checked' : '' ?>><span class="pm-in g"><span class="orb sm <?= $k === 'zarinpal' ? 'o-tg' : 'o-gold' ?>"><?= ic($k === 'zarinpal' ? 'card' : 'coin') ?></span><span><b><?= e($lbl) ?></b></span><i class="dot"></i></span></label>
            <?php $first = false; endforeach; ?>
          </div>
          <button class="btn b-grn b-block" type="submit"><?= ic('plus') ?> شارژِ کیف پول</button>
        </form>
        <?php endif; ?>
      </div>
      <details class="g pass-box"><summary><?= ic('lock') ?> تغییرِ رمز</summary>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="password">
          <label class="field"><span>رمزِ فعلی</span><input class="inp ltr" type="password" name="cur" required autocomplete="current-password"></label>
          <label class="field"><span>رمزِ تازه</span><input class="inp ltr" type="password" name="new" minlength="6" required autocomplete="new-password"></label>
          <button class="btn b-glass b-block" type="submit">ذخیره</button>
        </form>
      </details>
    </div>
    <div class="acc-main">
      <div class="panel g">
        <h3><?= ic('list') ?> سفارش‌های من</h3>
        <?php if (!$orders): ?><p class="muted empty-in">هنوز سفارشی ثبت نکرده‌اید. <a href="<?= e(url()) ?>#services">شروعِ خرید</a></p>
        <?php else: ?>
        <div class="tbl-wrap"><table class="tbl">
          <thead><tr><th>سفارش</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th><th></th></tr></thead>
          <tbody><?php foreach ($orders as $o): ?>
            <tr><td><b><?= e($o['title']) ?></b><small class="muted" dir="ltr"><?= e($o['code']) ?></small></td><td><?= toman($o['amount']) ?></td>
            <td><span class="badge sm <?= status_cls($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td><td class="muted"><?= jdate($o['created'], false) ?></td>
            <td><a class="btn b-glass b-sm" href="<?= e(url('order', ['code' => $o['code']])) ?>">مشاهده</a></td></tr>
          <?php endforeach; ?></tbody>
        </table></div>
        <?php endif; ?>
      </div>
      <?php if ($txs): ?>
      <div class="panel g">
        <h3><?= ic('wallet') ?> گردشِ کیف پول</h3>
        <div class="tbl-wrap"><table class="tbl">
          <thead><tr><th>شرح</th><th>مبلغ</th><th>تاریخ</th></tr></thead>
          <tbody><?php foreach ($txs as $t): ?><tr><td><?= e($t['reason']) ?></td><td class="<?= (int)$t['amount'] >= 0 ? 'pos' : 'neg' ?>"><?= ((int)$t['amount'] >= 0 ? '+' : '−') . toman(abs((int)$t['amount'])) ?></td><td class="muted"><?= jdate($t['created']) ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php
    return get_defined_vars();
}

function nsv_admin__orders_table(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><?php $rowsList = $recent ?? $list ?? []; ?>
<?php if (!$rowsList): ?><p class="muted empty-in">سفارشی نیست.</p><?php else: ?>
<div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>کد</th><th>خدمت</th><th>مقصد</th><th>مبلغ</th><th>پرداخت</th><th>وضعیت</th><th>زمان</th></tr></thead>
  <tbody>
  <?php foreach ($rowsList as $o): ?>
    <tr class="click" data-href="<?= e(aurl('order', ['id' => $o['id']])) ?>">
      <td dir="ltr"><a href="<?= e(aurl('order', ['id' => $o['id']])) ?>"><b><?= e($o['code']) ?></b></a></td>
      <td><?= e($o['title']) ?><?= (int)$o['qty'] > 1 ? ' <small class="muted">× ' . fa($o['qty']) . '</small>' : '' ?></td>
      <td dir="ltr" class="brk sm"><?= e($o['target'] ?: $o['contact']) ?></td>
      <td><?= toman($o['amount']) ?></td>
      <td class="muted sm"><?= e(['wallet' => 'کیف پول', 'zarinpal' => 'درگاه', 'card' => 'کارت'][$o['method']] ?? '—') ?></td>
      <td><span class="badge sm <?= status_cls($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
      <td class="muted sm"><?= e(ago($o['created'])) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
<?php
    return get_defined_vars();
}

function nsv_admin_cats(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><form class="panel g" method="post"><?= csrf_field() ?><input type="hidden" name="op" value="save">
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th></th><th>عنوان</th><th>زیرعنوان</th><th>ترتیب</th><th>فعال</th><th>محصولات</th></tr></thead>
    <tbody><?php foreach ($cats as $c): ?>
      <tr>
        <td><span class="orb sm <?= ['stars' => 'o-gold', 'premium' => 'o-vio', 'gifts' => 'o-pink', 'numbers' => 'o-grn', 'instagram' => 'o-ig'][$c['kind']] ?? 'o-tg' ?>"><?= ic($c['icon'] ?: 'bag') ?></span></td>
        <td><input class="inp" name="c[<?= (int)$c['id'] ?>][title]" value="<?= e($c['title']) ?>" maxlength="80"></td>
        <td><input class="inp" name="c[<?= (int)$c['id'] ?>][subtitle]" value="<?= e($c['subtitle']) ?>" maxlength="200"></td>
        <td><input class="inp num xs" name="c[<?= (int)$c['id'] ?>][sort]" value="<?= (int)$c['sort'] ?>"></td>
        <td><input type="checkbox" name="c[<?= (int)$c['id'] ?>][active]" value="1"<?= (int)$c['active'] ? ' checked' : '' ?>></td>
        <td><a class="btn b-glass b-sm" href="<?= e(aurl('products', ['cat' => $c['id']])) ?>"><?= fa((int)val('SELECT COUNT(*) FROM products WHERE cat_id = ?', [(int)$c['id']])) ?></a></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <button class="btn b-pri" type="submit"><?= ic('check') ?> ذخیره‌ی دسته‌ها</button>
</form>
<form class="panel g form-grid" method="post"><?= csrf_field() ?><input type="hidden" name="op" value="add">
  <h3 class="span2"><?= ic('plus') ?> دسته‌ی تازه</h3>
  <label class="field"><span>عنوان</span><input class="inp" name="title" maxlength="80" required></label>
  <label class="field"><span>نامکِ لاتین (در آدرس)</span><input class="inp ltr" name="slug" maxlength="40" placeholder="youtube" required></label>
  <div class="span2"><button class="btn b-glass" type="submit"><?= ic('plus') ?> افزودن</button></div>
</form>
<?php
    return get_defined_vars();
}

function nsv_admin_dashboard(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><?php $max = max(1, max(array_column($chart, 1))); ?>
<?php if (setting('seed_review') === '1'): ?>
<div class="alert a-warn g"><?= ic('alert') ?><span>قیمت‌های محصولات نمونه‌اند. قبل از شروعِ فروش، از «محصولات» همه را بازبینی کنید و در «تنظیمات» شماره کارت یا درگاه را وارد کنید.</span>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="seed_ok"><button class="btn b-glass b-sm" type="submit">بازبینی کردم</button></form></div>
<?php endif; ?>
<?php if (!card_on() && !zp_on()): ?>
<div class="alert a-err g"><?= ic('alert') ?><span>هیچ روشِ پرداختی فعال نیست — مشتری نمی‌تواند پرداخت کند. <a href="<?= e(aurl('settings')) ?>">تنظیمات</a></span></div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat g"><span class="orb sm o-grn"><?= ic('coin') ?></span><div><span>فروشِ امروز</span><b><?= toman($S['today']) ?></b><small><?= fa($S['orders_today']) ?> سفارش</small></div></div>
  <div class="stat g"><span class="orb sm o-tg"><?= ic('chart') ?></span><div><span>۷ روزِ اخیر</span><b><?= toman($S['week']) ?></b></div></div>
  <div class="stat g"><span class="orb sm o-vio"><?= ic('chart') ?></span><div><span>۳۰ روزِ اخیر</span><b><?= toman($S['month']) ?></b></div></div>
  <div class="stat g"><span class="orb sm o-pink"><?= ic('users') ?></span><div><span>کاربران</span><b><?= fa($S['users']) ?></b><small>موجودیِ کیف‌پول‌ها: <?= toman($S['wallets']) ?></small></div></div>
</div>

<div class="attn-grid">
  <a class="attn g <?= $S['review'] ? 'hot' : '' ?>" href="<?= e(aurl('orders', ['st' => 'review'])) ?>"><b><?= fa($S['review']) ?></b><span>رسید در انتظارِ تایید</span></a>
  <a class="attn g <?= $S['queue'] ? 'hot' : '' ?>" href="<?= e(aurl('orders', ['st' => 'paid'])) ?>"><b><?= fa($S['queue']) ?></b><span>پرداخت‌شده — منتظرِ انجام</span></a>
  <a class="attn g" href="<?= e(aurl('orders', ['st' => 'processing'])) ?>"><b><?= fa($S['proc']) ?></b><span>در حالِ انجام</span></a>
  <a class="attn g <?= $S['failed'] ? 'bad' : '' ?>" href="<?= e(aurl('orders', ['st' => 'failed'])) ?>"><b><?= fa($S['failed']) ?></b><span>ناموفق — نیازِ بررسی</span></a>
</div>

<div class="panel g">
  <h3><?= ic('chart') ?> فروشِ ۱۴ روزِ اخیر</h3>
  <div class="bars">
    <?php foreach ($chart as [$d0, $v]): ?>
    <div class="bar" title="<?= e(jdate($d0, false) . ' — ' . toman($v)) ?>"><i style="height:<?= max(2, round($v / $max * 100)) ?>%"></i><span><?= fa_digits(date('j', $d0)) ?></span></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel g">
  <div class="panel-h"><h3><?= ic('list') ?> آخرین سفارش‌ها</h3>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="expire"><button class="btn b-glass b-sm" type="submit">لغوِ پرداخت‌نشده‌های کهنه</button></form></div>
  <?php nsv_admin__orders_table(get_defined_vars()); ?>
</div>
<?php
    return get_defined_vars();
}

function nsv_admin_layout(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><!doctype html>
<html lang="fa" dir="rtl" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> — مدیریت <?= e(setting('site_name')) ?></title>
<link rel="icon" href="<?= e(NS_SELF) ?>?asset=icon" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(NS_SELF) ?>?asset=css&amp;v=<?= NS_VERSION ?>">
<script><?= theme_boot_js() ?></script>
</head>
<body class="adm">
<?php nsv__sprite([]); ?>
<div class="bg" aria-hidden="true"><div class="aur a1"></div><div class="aur a2"></div><div class="aur a3"></div><canvas id="sky"></canvas><div class="bg-noise"></div></div>
<div class="adm-lay">
  <aside class="side g blur" id="side">
    <a href="<?= e(aurl()) ?>" class="logo"><span class="logo-mark"><?= ic('star') ?></span><span><?= e(setting('site_name')) ?><small>پنلِ مدیریت</small></span></a>
    <nav>
      <?php foreach ([['dashboard', 'home', 'داشبورد'], ['orders', 'list', 'سفارش‌ها'], ['products', 'box', 'محصولات'], ['cats', 'grid', 'دسته‌ها'], ['users', 'users', 'کاربران'], ['settings', 'gear', 'تنظیمات']] as [$k, $i, $l]): ?>
      <a href="<?= e(aurl($k, $k === 'orders' && $attn ? ['st' => 'attn'] : [])) ?>" class="<?= $A === $k || ($k === 'orders' && $A === 'order') || ($k === 'products' && $A === 'product') || ($k === 'users' && $A === 'user') ? 'on' : '' ?>"><?= ic($i) ?><?= e($l) ?><?php if ($k === 'orders' && $attn): ?><span class="cnt"><?= fa($attn) ?></span><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="<?= e(url()) ?>" target="_blank" rel="noopener"><?= ic('globe') ?>مشاهده‌ی سایت</a>
      <button type="button" id="theme" class="side-btn"><?= ic('sun', 'i-sun') ?><?= ic('moon', 'i-moon') ?>تغییرِ تم</button>
      <form method="post" action="<?= e(aurl('logout')) ?>"><?= csrf_field() ?><button type="submit" class="side-btn"><?= ic('logout') ?>خروج</button></form>
    </div>
  </aside>
  <div class="adm-main">
    <div class="adm-top">
      <button type="button" class="icon-btn menu-btn" id="sideBtn" aria-label="منو"><?= ic('menu') ?></button>
      <h1><?= e($title) ?></h1>
    </div>
    <?php foreach (flashes() as [$t, $m]): ?><div class="alert <?= $t === 'ok' ? 'a-ok' : 'a-err' ?> g"><?= ic($t === 'ok' ? 'check' : 'alert') ?><span><?= e($m) ?></span></div><?php endforeach; ?>
    <?= $content ?>
  </div>
</div>
<script src="<?= e(NS_SELF) ?>?asset=js&amp;v=<?= NS_VERSION ?>"></script>
</body>
</html>
<?php
    return get_defined_vars();
}

function nsv_admin_login(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><!doctype html>
<html lang="fa" dir="rtl" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>ورود مدیر — <?= e(setting('site_name')) ?></title>
<link rel="icon" href="<?= e(NS_SELF) ?>?asset=icon" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(NS_SELF) ?>?asset=css&amp;v=<?= NS_VERSION ?>">
<script><?= theme_boot_js() ?></script>
</head>
<body>
<?php nsv__sprite([]); ?>
<div class="bg" aria-hidden="true"><div class="aur a1"></div><div class="aur a2"></div><div class="aur a3"></div><canvas id="sky"></canvas><div class="bg-noise"></div></div>
<main class="login-wrap">
  <form class="form-card g blur auth" method="post">
    <?= csrf_field() ?>
    <span class="orb big o-tg"><?= ic('lock') ?></span>
    <h1>پنلِ مدیریت</h1>
    <p class="muted"><?= e(setting('site_name')) ?></p>
    <?php if ($err !== ''): ?><div class="alert a-err"><?= ic('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>
    <label class="field"><span>رمزِ مدیر</span><input class="inp ltr" type="password" name="pass" required autofocus autocomplete="current-password"></label>
    <button class="btn b-pri b-lg b-block" type="submit"><?= ic('lock') ?> ورود</button>
  </form>
</main>
<script src="<?= e(NS_SELF) ?>?asset=js&amp;v=<?= NS_VERSION ?>"></script>
</body>
</html>
<?php
    return get_defined_vars();
}

function nsv_admin_order(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><?php
$st = $o['status'];
$ps = provider_state($o);
$f = $p ? field_meta($p['field']) : null;
$prov = $p ? provider_effective($p) : 'manual';
?>
<div class="ord-admin">
  <div class="panel g">
    <div class="panel-h"><h3 dir="ltr"><?= e($o['code']) ?></h3><span class="badge <?= status_cls($st) ?>"><?= e(status_label($st)) ?></span></div>
    <div class="kv two-col">
      <div><span>خدمت</span><b><?= e($o['title']) ?><?= $o['kind'] === 'topup' ? ' (شارژ)' : '' ?></b></div>
      <div><span>تعداد</span><b><?= fa($o['qty']) ?></b></div>
      <div><span>مبلغ</span><b><?= toman($o['amount']) ?></b></div>
      <div><span><?= e($f['label'] ?? 'مقصد') ?></span><b dir="ltr" class="brk"><?= e($o['target'] ?: '—') ?> <?php if ($o['target']): ?><button type="button" class="icon-btn sm" data-copy="<?= e($o['target']) ?>"><?= ic('copy') ?></button><?php endif; ?></b></div>
      <div><span>خریدار</span><b><?= $u ? '<a href="' . e(aurl('user', ['id' => $u['id']])) . '">' . e($u['name']) . '</a>' : e($o['name'] ?: 'مهمان') ?></b></div>
      <div><span>موبایل</span><b dir="ltr"><?= e($o['contact']) ?></b></div>
      <div><span>روشِ پرداخت</span><b><?= e(['wallet' => 'کیف پول', 'zarinpal' => 'درگاه زرین‌پال', 'card' => 'کارت به کارت'][$o['method']] ?? '—') ?></b></div>
      <div><span>کدِ پیگیری</span><b dir="ltr"><?= e($o['ref'] ?: '—') ?></b></div>
      <div><span>ثبت</span><b><?= jdate($o['created']) ?></b></div>
      <div><span>پرداخت</span><b><?= jdate($o['paid_at']) ?></b></div>
      <div><span>تحویل</span><b><?= e(['manual' => 'دستی', 'smm' => 'پنل SMM', '5sim' => '5sim'][$o['provider'] ?: $prov] ?? 'دستی') ?><?= $o['provider_id'] ? ' — #' . e($o['provider_id']) : '' ?></b></div>
      <?php if (!empty($ps['phone'])): ?><div><span>شماره / کد</span><b dir="ltr"><?= e($ps['phone']) ?> — <?= e($ps['code'] ?: '…') ?></b></div><?php endif; ?>
    </div>
    <?php if ((string)$o['note'] !== ''): ?><div class="note-box"><b>توضیحِ خریدار:</b><p><?= nl2br(e($o['note'])) ?></p></div><?php endif; ?>
  </div>

  <?php if ($o['receipt']): ?>
  <div class="panel g">
    <h3><?= ic('coin') ?> رسیدِ کارت‌به‌کارت</h3>
    <?php if ((string)$o['receipt_note'] !== ''): ?><p class="muted">یادداشتِ خریدار: <?= e($o['receipt_note']) ?></p><?php endif; ?>
    <a href="<?= e(aurl('receipt', ['id' => $o['id']])) ?>" target="_blank" rel="noopener"><img class="rcpt-img" src="<?= e(aurl('receipt', ['id' => $o['id']])) ?>" alt="رسید"></a>
    <?php if ($st === 'review'): ?>
    <div class="acts">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="approve"><button class="btn b-grn" type="submit"><?= ic('check') ?> تاییدِ رسید (<?= toman($o['amount']) ?>)</button></form>
      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="op" value="reject"><input class="inp" name="why" placeholder="دلیلِ رد (به خریدار نشان داده می‌شود)"><button class="btn b-danger" type="submit"><?= ic('close') ?> رد</button></form>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="panel g">
    <h3><?= ic('bolt') ?> تحویل و یادداشت</h3>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="save">
      <label class="field"><span>متنِ تحویل (به خریدار نشان داده می‌شود)</span><textarea class="inp" name="delivery" rows="3"><?= e($o['delivery']) ?></textarea></label>
      <label class="field"><span>یادداشتِ داخلی (فقط مدیر)</span><textarea class="inp" name="admin_note" rows="3"><?= e($o['admin_note']) ?></textarea></label>
      <button class="btn b-glass" type="submit"><?= ic('check') ?> ذخیره</button>
    </form>
    <div class="acts">
      <?php if (in_array($st, ['paid', 'failed'], true)): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="processing"><button class="btn b-glass" type="submit"><?= ic('refresh') ?> در حالِ انجام</button></form><?php endif; ?>
      <?php if (in_array($st, ['paid', 'processing', 'failed'], true)): ?><form method="post" data-confirm="سفارش «تکمیل‌شده» شود؟"><?= csrf_field() ?><input type="hidden" name="op" value="complete"><button class="btn b-grn" type="submit"><?= ic('check') ?> تکمیل شد</button></form><?php endif; ?>
      <?php if ($st === 'paid' && $prov !== 'manual'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="dispatch"><button class="btn b-pri" type="submit"><?= ic('send') ?> ارسالِ خودکار به <?= $prov === 'smm' ? 'پنل SMM' : '5sim' ?></button></form><?php endif; ?>
      <?php if ($st === 'processing' && in_array($o['provider'], ['smm', '5sim'], true)): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="refresh"><button class="btn b-glass" type="submit"><?= ic('refresh') ?> پرسیدنِ وضعیت</button></form><?php endif; ?>
      <?php if (in_array($st, ['pending', 'review'], true)): ?><form method="post" data-confirm="سفارش لغو شود؟"><?= csrf_field() ?><input type="hidden" name="op" value="cancel"><button class="btn b-danger" type="submit"><?= ic('close') ?> لغو</button></form><?php endif; ?>
    </div>
    <?php if (in_array($st, ['paid', 'processing', 'failed'], true) && (int)$o['paid_at'] > 0 && $o['kind'] !== 'topup'): ?>
    <div class="acts refund">
      <form method="post" class="inline" data-confirm="مبلغ برگشت داده شود؟"><?= csrf_field() ?>
        <input class="inp" name="why" placeholder="دلیل (اختیاری)">
        <?php if ((int)$o['user_id'] > 0): ?><button class="btn b-gold" name="op" value="refund" type="submit"><?= ic('wallet') ?> برگشت به کیف پول</button><?php endif; ?>
        <button class="btn b-glass" name="op" value="manual_refund" type="submit">ثبتِ برگشتِ دستی</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php
    return get_defined_vars();
}

function nsv_admin_orders(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><div class="toolbar g">
  <div class="tabs">
    <?php foreach (['' => 'همه', 'attn' => 'نیازِ اقدام'] + array_map(fn($x) => $x[0], order_statuses()) as $k => $l): ?>
    <a class="tab <?= $st === (string)$k ? 'on' : '' ?>" href="<?= e(aurl('orders', array_filter(['st' => $k, 'q' => $qq]))) ?>"><?= e($l) ?></a>
    <?php endforeach; ?>
  </div>
  <form method="get" class="srch"><input type="hidden" name="a" value="orders"><input type="hidden" name="st" value="<?= e($st) ?>">
    <input class="inp" type="search" name="q" value="<?= e($qq) ?>" placeholder="کد، آیدی، لینک یا موبایل…"><button class="btn b-glass" type="submit"><?= ic('search') ?></button></form>
</div>
<div class="panel g">
  <div class="panel-h"><h3><?= fa($total) ?> سفارش</h3></div>
  <?php nsv_admin__orders_table(get_defined_vars()); ?>
  <?php if ($pages > 1): ?>
  <div class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $pg ? 'on' : '' ?>" href="<?= e(aurl('orders', array_filter(['st' => $st, 'q' => $qq, 'pg' => $i]))) ?>"><?= fa($i) ?></a><?php endfor; ?></div>
  <?php endif; ?>
</div>
<?php
    return get_defined_vars();
}

function nsv_admin_product(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><form class="panel g form-grid" method="post" data-pform>
  <?= csrf_field() ?>
  <?php if ($err !== ''): ?><div class="alert a-err span2"><?= ic('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>
  <label class="field"><span>دسته</span><select class="inp" name="cat_id"><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)$d['cat_id'] === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?></select></label>
  <label class="field"><span>عنوان</span><input class="inp" name="title" value="<?= e($d['title']) ?>" required maxlength="120"></label>
  <label class="field span2"><span>توضیح</span><input class="inp" name="descr" value="<?= e($d['descr']) ?>" maxlength="400"></label>
  <label class="field"><span>ایموجی / پرچم</span><input class="inp" name="emoji" value="<?= e($d['emoji']) ?>" maxlength="8"></label>
  <label class="field"><span>برچسب (مثلا پرفروش)</span><input class="inp" name="badge" value="<?= e($d['badge']) ?>" maxlength="30"></label>

  <label class="field"><span>نوعِ قیمت</span><select class="inp" name="pricing" data-pricing>
    <option value="fixed"<?= $d['pricing'] === 'fixed' ? ' selected' : '' ?>>بسته‌ی ثابت</option>
    <option value="unit"<?= $d['pricing'] === 'unit' ? ' selected' : '' ?>>تعدادی (قیمت × تعداد)</option></select></label>
  <label class="field"><span>قیمت (تومان)</span><input class="inp num" name="price" value="<?= (int)$d['price'] ?>" inputmode="numeric" required></label>
  <div class="unit-only span2 form-grid inner">
    <label class="field"><span>قیمت برای هر چند عدد</span><input class="inp num" name="per" value="<?= (int)$d['per'] ?>" inputmode="numeric"></label>
    <label class="field"><span>واحد (مثلا فالوور)</span><input class="inp" name="unit" value="<?= e($d['unit']) ?>" maxlength="30"></label>
    <label class="field"><span>حداقل تعداد</span><input class="inp num" name="qmin" value="<?= (int)$d['qmin'] ?>" inputmode="numeric"></label>
    <label class="field"><span>حداکثر تعداد</span><input class="inp num" name="qmax" value="<?= (int)$d['qmax'] ?>" inputmode="numeric"></label>
    <label class="field"><span>گامِ تعداد</span><input class="inp num" name="qstep" value="<?= (int)$d['qstep'] ?>" inputmode="numeric"></label>
  </div>

  <label class="field"><span>از خریدار بپرس</span><select class="inp" name="field"><?php foreach (field_types() as $k => $l): ?><option value="<?= e($k) ?>"<?= $d['field'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
  <label class="field"><span>روشِ تحویل</span><select class="inp" name="provider">
    <option value="manual"<?= $d['provider'] === 'manual' ? ' selected' : '' ?>>دستی (مدیر انجام می‌دهد)</option>
    <option value="smm"<?= $d['provider'] === 'smm' ? ' selected' : '' ?>>خودکار — پنل SMM</option>
    <option value="5sim"<?= $d['provider'] === '5sim' ? ' selected' : '' ?>>خودکار — 5sim (شماره مجازی)</option></select></label>
  <label class="field span2"><span>شناسه‌ی سرویس (SMM: شماره‌ی سرویس — 5sim: کشور/اپراتور/سرویس مثل russia/any/telegram)</span><input class="inp ltr" name="provider_ref" value="<?= e($d['provider_ref']) ?>" maxlength="120"></label>
  <label class="field"><span>ترتیب</span><input class="inp num" name="sort" value="<?= (int)$d['sort'] ?>" inputmode="numeric"></label>
  <label class="field chk"><input type="checkbox" name="active" value="1"<?= (int)$d['active'] ? ' checked' : '' ?>><span>فعال — در سایت نمایش داده شود</span></label>
  <div class="span2 form-foot"><button class="btn b-pri b-lg" type="submit"><?= ic('check') ?> ذخیره</button><a class="btn b-glass b-lg" href="<?= e(aurl('products', ['cat' => $d['cat_id']])) ?>">انصراف</a></div>
</form>
<?php
    return get_defined_vars();
}

function nsv_admin_products(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><div class="toolbar g">
  <div class="tabs">
    <a class="tab <?= !$cf ? 'on' : '' ?>" href="<?= e(aurl('products')) ?>">همه</a>
    <?php foreach ($cats as $c): ?><a class="tab <?= $cf === (int)$c['id'] ? 'on' : '' ?>" href="<?= e(aurl('products', ['cat' => $c['id']])) ?>"><?= e($c['title']) ?></a><?php endforeach; ?>
  </div>
  <a class="btn b-pri" href="<?= e(aurl('product', $cf ? ['cat' => $cf] : [])) ?>"><?= ic('plus') ?> محصولِ تازه</a>
</div>
<?php foreach ($list as $c): ?>
<div class="panel g">
  <div class="panel-h"><h3><?= ic($c['icon'] ?: 'bag') ?> <?= e($c['title']) ?> <small class="muted">(<?= fa(count($c['items'])) ?>)</small></h3><a class="btn b-glass b-sm" href="<?= e(aurl('product', ['cat' => $c['id']])) ?>"><?= ic('plus') ?> افزودن</a></div>
  <?php if (!$c['items']): ?><p class="muted empty-in">محصولی نیست.</p><?php else: ?>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th></th><th>عنوان</th><th>قیمت</th><th>ورودی</th><th>تحویل</th><th>وضعیت</th><th></th></tr></thead>
    <tbody><?php foreach ($c['items'] as $p): ?>
      <tr class="<?= (int)$p['active'] ? '' : 'off' ?>">
        <td class="emo-cell"><?= e($p['emoji']) ?></td>
        <td><b><?= e($p['title']) ?></b><?= $p['badge'] !== '' ? ' <span class="tag">' . e($p['badge']) . '</span>' : '' ?></td>
        <td><?= e(product_price_label($p)) ?></td>
        <td class="muted sm"><?= e(field_types()[$p['field']] ?? $p['field']) ?></td>
        <td class="muted sm"><?= e(['manual' => 'دستی', 'smm' => 'SMM', '5sim' => '5sim'][$p['provider']] ?? '') ?><?= provider_effective($p) !== $p['provider'] ? ' <span class="tag">تنظیم‌نشده</span>' : '' ?></td>
        <td><form method="post" action="<?= e(aurl('products', ['cat' => $cf])) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="pill-t <?= (int)$p['active'] ? 'on' : '' ?>" type="submit"><?= (int)$p['active'] ? 'فعال' : 'غیرفعال' ?></button></form></td>
        <td class="row-acts"><a class="icon-btn sm" href="<?= e(aurl('product', ['id' => $p['id']])) ?>" aria-label="ویرایش"><?= ic('edit') ?></a>
          <form method="post" action="<?= e(aurl('products', ['cat' => $cf])) ?>" data-confirm="حذف شود؟"><?= csrf_field() ?><input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="icon-btn sm" type="submit" aria-label="حذف"><?= ic('trash') ?></button></form></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php
    return get_defined_vars();
}

function nsv_admin_settings(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><?php $s = fn($k) => e(setting($k)); $c = fn($k) => setting($k) === '1' ? ' checked' : ''; ?>
<form method="post" class="set-form">
  <?= csrf_field() ?>
  <?php if ($err !== ''): ?><div class="alert a-err g"><?= ic('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>

  <div class="panel g form-grid">
    <h3 class="span2"><?= ic('star') ?> فروشگاه</h3>
    <label class="field"><span>نامِ فروشگاه</span><input class="inp" name="site_name" value="<?= $s('site_name') ?>"></label>
    <label class="field"><span>شعار</span><input class="inp" name="tagline" value="<?= $s('tagline') ?>"></label>
    <label class="field span2"><span>نوارِ اطلاعیه‌ی بالای سایت (خالی = پنهان)</span><input class="inp" name="notice" value="<?= $s('notice') ?>"></label>
    <label class="field"><span>آیدیِ پشتیبانیِ تلگرام</span><input class="inp ltr" name="support" value="<?= $s('support') ?>" placeholder="username"></label>
    <label class="field"><span>آیدیِ کانالِ تلگرام</span><input class="inp ltr" name="channel" value="<?= $s('channel') ?>" placeholder="channel"></label>
  </div>

  <div class="panel g form-grid">
    <h3 class="span2"><?= ic('card') ?> پرداخت</h3>
    <label class="field chk span2"><input type="checkbox" name="card_on" value="1"<?= $c('card_on') ?>><span>کارت‌به‌کارت با آپلودِ رسید</span></label>
    <label class="field"><span>شماره کارت</span><input class="inp ltr" name="card_number" value="<?= $s('card_number') ?>" placeholder="6037 9900 0000 0000"></label>
    <label class="field"><span>به نامِ</span><input class="inp" name="card_holder" value="<?= $s('card_holder') ?>"></label>
    <label class="field"><span>بانک</span><input class="inp" name="card_bank" value="<?= $s('card_bank') ?>"></label>
    <span></span>
    <label class="field chk span2"><input type="checkbox" name="zp_on" value="1"<?= $c('zp_on') ?>><span>درگاهِ زرین‌پال</span></label>
    <label class="field"><span>مرچنت‌کدِ زرین‌پال</span><input class="inp ltr" name="zp_merchant" value="<?= $s('zp_merchant') ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></label>
    <label class="field chk"><input type="checkbox" name="zp_sandbox" value="1"<?= $c('zp_sandbox') ?>><span>حالتِ آزمایشی (sandbox)</span></label>
    <label class="field chk"><input type="checkbox" name="wallet_on" value="1"<?= $c('wallet_on') ?>><span>کیف پول برای کاربرانِ عضو</span></label>
    <label class="field"><span>حداقلِ شارژ (تومان)</span><input class="inp num" name="topup_min" value="<?= $s('topup_min') ?>"></label>
    <label class="field"><span>لغوِ سفارشِ پرداخت‌نشده بعد از (ساعت)</span><input class="inp num" name="pending_hours" value="<?= $s('pending_hours') ?>"></label>
  </div>

  <div class="panel g form-grid">
    <h3 class="span2"><?= ic('bolt') ?> تحویلِ خودکار</h3>
    <label class="field"><span>آدرسِ API پنلِ SMM</span><input class="inp ltr" name="smm_url" value="<?= $s('smm_url') ?>" placeholder="https://panel.example/api/v2"></label>
    <label class="field"><span>کلیدِ API پنلِ SMM</span><input class="inp ltr" name="smm_key" value="<?= $s('smm_key') ?>"></label>
    <label class="field span2"><span>توکنِ API سایتِ 5sim (شماره مجازی)</span><input class="inp ltr" name="fivesim_key" value="<?= $s('fivesim_key') ?>"></label>
    <div class="field span2"><span>آدرسِ کران (هر ۵ دقیقه)</span><div class="copy"><span dir="ltr" class="brk"><?= e($cron) ?></span><button type="button" class="btn b-glass b-sm" data-copy="<?= e($cron) ?>"><?= ic('copy') ?></button></div></div>
  </div>

  <div class="panel g form-grid">
    <h3 class="span2"><?= ic('list') ?> متن‌ها</h3>
    <label class="field span2"><span>درباره ما</span><textarea class="inp" name="about" rows="3"><?= $s('about') ?></textarea></label>
    <label class="field span2"><span>قوانین</span><textarea class="inp" name="terms" rows="6"><?= $s('terms') ?></textarea></label>
    <label class="field span2"><span>متنِ صفحه‌ی تماس</span><textarea class="inp" name="contact_text" rows="3"><?= $s('contact_text') ?></textarea></label>
  </div>

  <div class="panel g form-grid">
    <h3 class="span2"><?= ic('lock') ?> امنیت</h3>
    <label class="field"><span>رمزِ تازه‌ی مدیر (خالی = بدونِ تغییر)</span><input class="inp ltr" type="password" name="new_pass" minlength="8" autocomplete="new-password"></label>
    <label class="field chk"><input type="checkbox" name="trust_proxy" value="1"<?= $c('trust_proxy') ?>><span>سایت پشتِ کلادفلر/پروکسی است</span></label>
  </div>

  <div class="sticky-save"><button class="btn b-pri b-lg" type="submit"><?= ic('check') ?> ذخیره‌ی تنظیمات</button></div>
</form>
<?php
    return get_defined_vars();
}

function nsv_admin_user(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><div class="stat-grid">
  <div class="stat g"><span class="orb sm o-tg"><?= ic('user') ?></span><div><span><?= e($u['name']) ?></span><b dir="ltr"><?= e($u['mobile']) ?></b><small>عضو از <?= jdate($u['created'], false) ?></small></div></div>
  <div class="stat g"><span class="orb sm o-grn"><?= ic('wallet') ?></span><div><span>موجودی</span><b><?= toman($u['balance']) ?></b></div></div>
</div>
<div class="grid2">
  <form class="panel g" method="post"><?= csrf_field() ?><input type="hidden" name="op" value="balance">
    <h3><?= ic('wallet') ?> تغییرِ موجودی</h3>
    <div class="two">
      <label class="field"><span>مبلغ (تومان)</span><input class="inp num" name="amount" inputmode="numeric" required></label>
      <label class="field"><span>نوع</span><select class="inp" name="dir"><option value="add">افزایش</option><option value="sub">کسر</option></select></label>
    </div>
    <label class="field"><span>دلیل</span><input class="inp" name="why" maxlength="120"></label>
    <button class="btn b-grn" type="submit">اعمال</button>
  </form>
  <div class="panel g">
    <h3><?= ic('lock') ?> حساب</h3>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="op" value="pass"><input class="inp ltr" type="text" name="np" placeholder="رمزِ تازه (حداقل ۶)" minlength="6"><button class="btn b-glass" type="submit">تنظیمِ رمز</button></form>
    <form method="post" data-confirm="<?= (int)$u['blocked'] ? 'رفعِ مسدودی؟' : 'کاربر مسدود شود؟' ?>"><?= csrf_field() ?><input type="hidden" name="op" value="block"><button class="btn <?= (int)$u['blocked'] ? 'b-grn' : 'b-danger' ?>" type="submit"><?= (int)$u['blocked'] ? 'رفعِ مسدودی' : 'مسدود کردن' ?></button></form>
  </div>
</div>
<div class="panel g"><h3><?= ic('list') ?> سفارش‌ها</h3><?php $list = $orders; nsv_admin__orders_table(get_defined_vars()); ?></div>
<?php if ($txs): ?>
<div class="panel g"><h3><?= ic('wallet') ?> گردشِ کیف پول</h3>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>شرح</th><th>مبلغ</th><th>تاریخ</th></tr></thead><tbody>
  <?php foreach ($txs as $t): ?><tr><td><?= e($t['reason']) ?></td><td class="<?= (int)$t['amount'] >= 0 ? 'pos' : 'neg' ?>"><?= ((int)$t['amount'] >= 0 ? '+' : '−') . toman(abs((int)$t['amount'])) ?></td><td class="muted sm"><?= jdate($t['created']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>
<?php
    return get_defined_vars();
}

function nsv_admin_users(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><div class="toolbar g">
  <form method="get" class="srch"><input type="hidden" name="a" value="users"><input class="inp" type="search" name="q" value="<?= e($qq) ?>" placeholder="نام یا موبایل…"><button class="btn b-glass" type="submit"><?= ic('search') ?></button></form>
</div>
<div class="panel g">
  <?php if (!$list): ?><p class="muted empty-in">کاربری نیست.</p><?php else: ?>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>نام</th><th>موبایل</th><th>موجودی</th><th>سفارش‌ها</th><th>عضویت</th><th></th></tr></thead>
    <tbody><?php foreach ($list as $u): ?>
      <tr class="<?= (int)$u['blocked'] ? 'off' : '' ?>">
        <td><b><?= e($u['name']) ?></b><?= (int)$u['blocked'] ? ' <span class="tag">مسدود</span>' : '' ?></td>
        <td dir="ltr"><?= e($u['mobile']) ?></td>
        <td><?= toman($u['balance']) ?></td>
        <td><?= fa((int)val('SELECT COUNT(*) FROM orders WHERE user_id = ?', [(int)$u['id']])) ?></td>
        <td class="muted sm"><?= jdate($u['created'], false) ?></td>
        <td><a class="btn b-glass b-sm" href="<?= e(aurl('user', ['id' => $u['id']])) ?>">مدیریت</a></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php
    return get_defined_vars();
}

function nsv_auth(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><?php $reg = $mode === 'register'; ?>
<section class="sec tight">
  <div class="wrap narrow">
    <form class="form-card g blur auth" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <span class="orb big <?= $reg ? 'o-grn' : 'o-tg' ?>"><?= ic($reg ? 'plus' : 'user') ?></span>
      <h1><?= $reg ? 'ساختِ حساب' : 'ورود به حساب' ?></h1>
      <p class="muted"><?= $reg ? 'با حساب، کیف پول و تاریخچه‌ی همه‌ی سفارش‌ها را دارید.' : 'با شماره موبایل و رمزتان وارد شوید.' ?></p>
      <?php if ($err !== ''): ?><div class="alert a-err"><?= ic('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>
      <?php if ($reg): ?><label class="field"><span>نام</span><input class="inp" type="text" name="name" value="<?= e($old['name']) ?>" maxlength="60" required autocomplete="name"></label><?php endif; ?>
      <label class="field"><span>شماره موبایل</span><input class="inp ltr" type="tel" name="mobile" value="<?= e($old['mobile']) ?>" placeholder="09123456789" required autocomplete="tel"></label>
      <label class="field"><span>رمز عبور</span><input class="inp ltr" type="password" name="pass" minlength="<?= $reg ? 6 : 1 ?>" required autocomplete="<?= $reg ? 'new-password' : 'current-password' ?>"></label>
      <?php if ($reg): ?><label class="field"><span>تکرارِ رمز</span><input class="inp ltr" type="password" name="pass2" minlength="6" required autocomplete="new-password"></label><?php endif; ?>
      <button class="btn b-pri b-lg b-block" type="submit"><?= ic($reg ? 'check' : 'lock') ?> <?= $reg ? 'ثبت‌نام' : 'ورود' ?></button>
      <p class="hint center"><?= $reg ? 'حساب دارید؟ <a href="' . e(url('login')) . '">ورود</a>' : 'حساب ندارید؟ <a href="' . e(url('register')) . '">ثبت‌نام</a>' ?></p>
    </form>
  </div>
</section>
<?php
    return get_defined_vars();
}

function nsv_buy(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><?php
$f = field_meta($p['field']);
$unit = $p['pricing'] === 'unit';
$q0 = $unit ? max((int)$p['qmin'], (int)ns_digits($old['qty'])) : 1;
$pmIcons = ['wallet' => 'wallet', 'zarinpal' => 'card', 'card' => 'coin'];
$pmSub = ['wallet' => $me ? 'موجودی: ' . toman($me['balance']) : '', 'zarinpal' => 'پرداختِ آنلاین با همه‌ی کارت‌های عضوِ شتاب', 'card' => 'واریز و آپلودِ تصویرِ رسید'];
?>
<section class="page-head">
  <div class="wrap">
    <nav class="crumbs"><a href="<?= e(url()) ?>">خانه</a><?= ic('arrow') ?><a href="<?= e(url('cat', ['slug' => $p['cat_slug']])) ?>"><?= e($p['cat_title']) ?></a><?= ic('arrow') ?><span><?= e($p['title']) ?></span></nav>
  </div>
</section>
<section class="sec tight">
  <div class="wrap buy-lay">
    <aside class="sum g blur rv">
      <div class="sum-top"><span class="emo xl"><?= e($p['emoji'] ?: '✨') ?></span><div><span class="muted sm"><?= e($p['cat_title']) ?></span><h1><?= e($p['title']) ?></h1></div></div>
      <?php if ($p['descr'] !== ''): ?><p class="muted"><?= e($p['descr']) ?></p><?php endif; ?>
      <div class="kv">
        <div><span>قیمت</span><b><?= e(product_price_label($p)) ?></b></div>
        <?php if ($unit): ?><div><span>حداقل / حداکثر</span><b><?= fa($p['qmin']) ?> / <?= fa($p['qmax']) ?></b></div><?php endif; ?>
        <div><span>تحویل</span><b><?= $p['cat_kind'] === 'numbers' ? 'نمایشِ شماره و کد در صفحه‌ی سفارش' : 'پس از تاییدِ پرداخت' ?></b></div>
      </div>
      <ul class="ticks">
        <li><?= ic('check') ?> بدونِ نیاز به رمز یا کدِ ورود</li>
        <li><?= ic('check') ?> پیگیریِ لحظه‌ای با کدِ سفارش</li>
        <li><?= ic('check') ?> بازگشتِ وجه در صورتِ عدمِ انجام</li>
      </ul>
    </aside>

    <form class="form-card g blur rv" method="post" data-buy data-price="<?= (int)$p['price'] ?>" data-per="<?= max(1, (int)$p['per']) ?>" data-unit="<?= $unit ? 1 : 0 ?>">
      <?= csrf_field() ?>
      <h2>ثبتِ سفارش</h2>
      <?php foreach ($errors as $er): ?><div class="alert a-err"><?= ic('alert') ?><span><?= e($er) ?></span></div><?php endforeach; ?>

      <?php if ($unit): ?>
      <label class="field"><span>تعداد (<?= e($p['unit'] ?: 'عدد') ?>)</span>
        <div class="qrow">
          <button type="button" data-step="<?= max(1, (int)$p['qstep']) ?>" aria-label="بیشتر">+</button>
          <input class="inp num" type="number" name="qty" inputmode="numeric" min="<?= (int)$p['qmin'] ?>" max="<?= (int)$p['qmax'] ?>" step="<?= max(1, (int)$p['qstep']) ?>" value="<?= (int)$q0 ?>" required>
          <button type="button" data-step="-<?= max(1, (int)$p['qstep']) ?>" aria-label="کمتر">−</button>
        </div>
        <small class="hint">حداقل <?= fa($p['qmin']) ?> — حداکثر <?= fa($p['qmax']) ?><?= (int)$p['qstep'] > 1 ? ' — مضربِ ' . fa($p['qstep']) : '' ?></small>
      </label>
      <?php endif; ?>

      <?php if ($f): ?>
      <label class="field"><span><?= e($f['label']) ?></span>
        <?php if ($p['field'] === 'text'): ?>
        <textarea class="inp" name="target" maxlength="500" placeholder="<?= e($f['ph']) ?>" required><?= e($old['target']) ?></textarea>
        <?php else: ?>
        <input class="inp ltr" type="text" name="target" value="<?= e($old['target']) ?>" placeholder="<?= e($f['ph']) ?>" autocomplete="off" spellcheck="false" required>
        <?php endif; ?>
        <?php if ($f['hint'] !== ''): ?><small class="hint"><?= e($f['hint']) ?></small><?php endif; ?>
      </label>
      <?php endif; ?>

      <label class="field"><span>توضیحات <em class="muted">(اختیاری)</em></span>
        <textarea class="inp sm" name="note" maxlength="500" placeholder="<?= $p['cat_kind'] === 'instagram' && str_contains($p['title'], 'کامنت') ? 'متنِ کامنت‌ها را اینجا بنویسید، هر خط یک کامنت' : 'اگر نکته‌ای هست بنویسید' ?>"><?= e($old['note']) ?></textarea>
      </label>

      <?php if (!$me): ?>
      <div class="two">
        <label class="field"><span>شماره موبایل</span><input class="inp ltr" type="tel" name="mobile" value="<?= e($old['mobile']) ?>" placeholder="09123456789" inputmode="tel" required></label>
        <label class="field"><span>نام <em class="muted">(اختیاری)</em></span><input class="inp" type="text" name="name" value="<?= e($old['name']) ?>" maxlength="60"></label>
      </div>
      <p class="hint">برای پیگیریِ سفارش لازم است. <a href="<?= e(url('login')) ?>">ورود</a> یا <a href="<?= e(url('register')) ?>">ثبت‌نام</a> برای کیف پول و تاریخچه‌ی سفارش‌ها.</p>
      <?php endif; ?>

      <div class="field"><span>روشِ پرداخت</span>
        <?php if (!$methods): ?>
          <div class="alert a-err"><?= ic('alert') ?><span>هنوز هیچ روشِ پرداختی فعال نشده؛ با پشتیبانی تماس بگیرید.</span></div>
        <?php else: ?>
        <div class="pms">
          <?php foreach ($methods as $k => $lbl): ?>
          <label class="pm"><input type="radio" name="method" value="<?= e($k) ?>"<?= $old['method'] === $k ? ' checked' : '' ?>>
            <span class="pm-in g"><span class="orb sm <?= $k === 'wallet' ? 'o-grn' : ($k === 'zarinpal' ? 'o-tg' : 'o-gold') ?>"><?= ic($pmIcons[$k]) ?></span><span><b><?= e($lbl) ?></b><small><?= e($pmSub[$k]) ?></small></span><i class="dot"></i></span>
          </label>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="total g"><span>مبلغِ قابلِ پرداخت</span><b><span data-total><?= fa(product_total($p, $q0)) ?></span> <small>تومان</small></b></div>
      <button class="btn b-pri b-lg b-block" type="submit"<?= $methods ? '' : ' disabled' ?>><?= ic('shield') ?> ثبت و پرداخت</button>
      <p class="hint center">با ثبتِ سفارش، <a href="<?= e(url('page', ['k' => 'terms'])) ?>">قوانین</a> را می‌پذیرید.</p>
    </form>
  </div>
</section>
<?php
    return get_defined_vars();
}

function nsv_cat(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><?php
$kind = $c['kind'];
$custom = null; $list = [];
foreach ($items as $p) { if ($kind === 'stars' && $p['pricing'] === 'unit') $custom = $custom ?: $p; else $list[] = $p; }
$grid = ['stars' => 'pk-grid', 'premium' => 'plans', 'gifts' => 'gift-grid', 'numbers' => 'ctry-grid'][$kind] ?? 'svc-grid';
?>
<section class="page-head">
  <div class="wrap">
    <nav class="crumbs"><a href="<?= e(url()) ?>">خانه</a><?= ic('arrow') ?><span><?= e($c['title']) ?></span></nav>
    <div class="ph-in">
      <span class="orb big <?= ui_orb($kind) ?>"><?= ic($c['icon'] ?: 'bag') ?></span>
      <div><h1><?= e($c['title']) ?></h1><p class="muted"><?= e($c['subtitle']) ?></p></div>
    </div>
  </div>
</section>
<section class="sec tight">
  <div class="wrap">
    <?php if (!$items): ?>
      <div class="empty g">فعلا خدمتی در این بخش نیست.</div>
    <?php else: ?>
      <?php if ($kind === 'numbers'): ?>
      <label class="search g"><?= ic('search') ?><input type="search" data-filter="#list" placeholder="جستجوی کشور… مثلا آمریکا" aria-label="جستجو"></label>
      <?php endif; ?>
      <?php if ($custom): ?><div class="stars-lay"><?= ui_star_calc($custom) ?><div class="<?= $grid ?>" id="list"><?php foreach ($list as $p) echo ui_card($p, $kind); ?></div></div>
      <?php else: ?><div class="<?= $grid ?>" id="list"><?php foreach ($list as $p) echo ui_card($p, $kind); ?></div><?php endif; ?>
      <div class="empty g" id="noResult" hidden>نتیجه‌ای پیدا نشد.</div>
    <?php endif; ?>
    <div class="info-row">
      <div class="info g"><?= ic('lock') ?><div><b>بدونِ رمز</b><span>فقط آیدی یا لینک؛ هیچ اطلاعاتِ ورودی لازم نیست.</span></div></div>
      <div class="info g"><?= ic('shield') ?><div><b>پرداختِ امن</b><span>درگاهِ آنلاین، کارت‌به‌کارت یا کیف پول.</span></div></div>
      <div class="info g"><?= ic('refresh') ?><div><b>پیگیریِ لحظه‌ای</b><span>وضعیتِ سفارش در صفحه‌ی سفارش به‌روز می‌شود.</span></div></div>
    </div>
  </div>
</section>
<?php
    return get_defined_vars();
}

function nsv_home(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><?php
$S = $K['stars'] ?? null; $PR = $K['premium'] ?? null; $G = $K['gifts'] ?? null;
$N = $K['numbers'] ?? null; $IG = $K['instagram'] ?? null; $TG = $K['telegram'] ?? null;
$starCustom = null; $starPk = [];
foreach ($S['items'] ?? [] as $p) { if ($p['pricing'] === 'unit') $starCustom = $starCustom ?: $p; else $starPk[] = $p; }
$hero500 = null; foreach ($starPk as $p) if (str_contains($p['title'], '۵۰۰')) $hero500 = $p;
$hero500 = $hero500 ?: ($starPk[3] ?? $starPk[0] ?? null);
$heroPrem = !empty($PR['items']) ? $PR['items'][count($PR['items']) - 1] : null;
$heroGift = $G['items'][0] ?? null;
$heroNum = $N['items'][0] ?? null;
$words = array_values(array_filter(['استارز', $PR ? 'پریمیوم' : null, $G ? 'گیفت' : null, $N ? 'شماره مجازی' : null, $IG ? 'فالوور' : null]));
$ticker = [];
if ($starCustom) $ticker[] = ['star', 'هر ۱ استارز', toman($starCustom['price'] / max(1, (int)$starCustom['per']))];
foreach ($PR['items'] ?? [] as $p) $ticker[] = ['crown', $p['title'], toman($p['price'])];
if ($N && $N['from']) $ticker[] = ['phone', 'شماره مجازی از', toman($N['from'])];
if ($G && $G['from']) $ticker[] = ['gift', 'گیفت تلگرام از', toman($G['from'])];
foreach (array_slice($IG['items'] ?? [], 0, 3) as $p) $ticker[] = ['insta', $p['title'], product_price_label($p)];
?>
<section class="hero">
  <div class="wrap hero-in">
    <div>
      <span class="pill g"><span class="dot"></span>پرداختِ امن · تحویلِ سریع · پشتیبانیِ واقعی</span>
      <h1>خرید <span class="shine rot" data-words="<?= e(json_encode($words, JSON_UNESCAPED_UNICODE)) ?>"><?= e($words[0]) ?></span><br>در چند ثانیه</h1>
      <p class="hero-sub">استارز، پریمیوم و گیفت تلگرام، شماره مجازیِ کشورهای مختلف و خدماتِ اینستاگرام — با قیمتِ شفاف، پرداختِ آنلاین یا کارت‌به‌کارت و پیگیریِ لحظه‌ای. بدون رمز، فقط با آیدی یا لینک.</p>
      <div class="hero-cta">
        <a class="btn b-pri b-lg" href="#services"><?= ic('bag') ?> شروع خرید</a>
        <a class="btn b-glass b-lg" href="<?= e(url('track')) ?>"><?= ic('search') ?> پیگیری سفارش</a>
      </div>
      <div class="trust">
        <span class="g"><?= ic('check') ?>بدون نیاز به رمز</span>
        <span class="g"><?= ic('check') ?>درگاهِ امن و کارت‌به‌کارت</span>
        <span class="g"><?= ic('check') ?>بازگشتِ وجه در صورتِ خطا</span>
      </div>
    </div>
    <div class="bento" aria-hidden="true">
      <a class="bt bt-star g blur" href="<?= $hero500 ? e(url('buy', ['id' => $hero500['id']])) : '#stars' ?>">
        <i class="ring"></i>
        <div class="big-ic"><?= ic('star') ?></div>
        <div class="bt-k">استارز تلگرام</div>
        <div class="bt-t"><?= e($hero500['title'] ?? 'استارز') ?></div>
        <div class="bt-k">واریزِ مستقیم به آیدیِ شما</div>
        <div class="bt-p"><span class="muted">قیمت</span><span><?= ui_amt($hero500['price'] ?? 0) ?></span></div>
        <span class="btn b-gold b-block"><?= ic('bolt') ?> خرید فوری</span>
      </a>
      <a class="bt bt-f1 g blur" href="<?= e(url('cat', ['slug' => 'premium'])) ?>">
        <div class="bt-row"><span class="orb o-vio"><?= ic('crown') ?></span><div><div class="bt-k">تلگرام پریمیوم</div><div class="bt-t"><?= e($heroPrem['title'] ?? 'اشتراک پریمیوم') ?></div></div></div>
        <div class="bt-p"><span class="muted">قیمت</span><span><?= ui_amt($heroPrem['price'] ?? 0) ?></span></div>
      </a>
      <a class="bt g blur" href="<?= e(url('cat', ['slug' => 'gifts'])) ?>">
        <div class="bt-row"><span class="emo"><?= e($heroGift['emoji'] ?? '🎁') ?></span><div><div class="bt-k">گیفت تلگرام</div><div class="bt-t"><?= e($heroGift['title'] ?? 'گیفت') ?></div></div></div>
        <div class="bt-p"><span class="muted">قیمت</span><span><?= ui_amt($heroGift['price'] ?? 0) ?></span></div>
      </a>
      <a class="bt g blur" href="<?= e(url('cat', ['slug' => 'numbers'])) ?>">
        <div class="bt-row"><span class="emo"><?= e($heroNum['emoji'] ?? '🌍') ?></span><div><div class="bt-k">شماره مجازی</div><div class="bt-t"><?= e($heroNum['title'] ?? 'کشورهای مختلف') ?></div></div></div>
        <div class="bt-p"><span class="muted">از</span><span><?= ui_amt($N['from'] ?? 0) ?></span></div>
      </a>
      <a class="bt bt-f2 g blur" href="<?= e(url('cat', ['slug' => 'instagram'])) ?>">
        <div class="bt-row"><span class="orb o-ig"><?= ic('insta') ?></span><div><div class="bt-k">اینستاگرام</div><div class="bt-t">فالوور · لایک · بازدید</div></div></div>
        <div class="bt-p"><span class="muted">از</span><span><?= ui_amt($IG['from'] ?? 0) ?></span></div>
      </a>
    </div>
  </div>
  <?php if ($ticker): ?>
  <div class="ticker g"><div class="ticker-track">
    <?php for ($r = 0; $r < 2; $r++): foreach ($ticker as [$i, $l, $v]): ?>
    <div class="tk"<?= $r ? ' aria-hidden="true"' : '' ?>><?= ic($i) ?><span class="muted"><?= e($l) ?></span><b><?= e($v) ?></b><span class="live"></span></div>
    <?php endforeach; endfor; ?>
  </div></div>
  <?php endif; ?>
</section>

<section class="sec" id="services">
  <div class="wrap">
    <div class="sec-head center rv">
      <span class="kicker g"><?= ic('bag') ?> همه‌ی خدمات</span>
      <h2>هرچه برای <span class="gt">تلگرام و اینستاگرام</span> لازم دارید</h2>
      <p>خدمت را انتخاب کنید، مشخصات را وارد کنید، پرداخت کنید — سفارش در صفِ انجام قرار می‌گیرد و وضعیتش را لحظه‌ای می‌بینید.</p>
    </div>
    <div class="cat-grid">
      <?php foreach ($cats as $c): $kk = $K[$c['kind']] ?? null; ?>
      <a class="catc g lift rv" href="<?= e(url('cat', ['slug' => $c['slug']])) ?>">
        <span class="halo <?= ui_orb($c['kind']) ?>"></span>
        <span class="orb <?= ui_orb($c['kind']) ?>"><?= ic($c['icon'] ?: 'bag') ?></span>
        <h3><?= e($c['title']) ?></h3>
        <p><?= e($c['subtitle']) ?></p>
        <div class="catc-foot"><span><?php if (!empty($kk['from'])): ?><span class="muted">از</span> <?= ui_amt($kk['from']) ?><?php endif; ?></span><span class="go">مشاهده <?= ic('arrow') ?></span></div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($S): ?>
<section class="sec" id="stars">
  <div class="wrap">
    <div class="sec-head rv">
      <div><span class="kicker gold g"><?= ic('star') ?> <?= e($S['title']) ?></span>
      <h2>خرید <span class="gt-gold">استارز تلگرام</span></h2>
      <p><?= e($S['subtitle']) ?>. فقط آیدیِ گیرنده لازم است.</p></div>
      <a class="btn b-glass" href="<?= e(url('cat', ['slug' => $S['slug']])) ?>">همه‌ی بسته‌ها <?= ic('arrow') ?></a>
    </div>
    <div class="stars-lay">
      <?= ui_star_calc($starCustom) ?>
      <div class="pk-grid"><?php foreach (array_slice($starPk, 0, 6) as $p) echo ui_card($p, 'stars'); ?></div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($PR && $PR['items']): ?>
<section class="sec" id="premium">
  <div class="wrap">
    <div class="sec-head center rv">
      <span class="kicker vio g"><?= ic('crown') ?> <?= e($PR['title']) ?></span>
      <h2>اشتراکِ <span class="gt">تلگرام پریمیوم</span></h2>
      <p><?= e($PR['subtitle']) ?></p>
    </div>
    <div class="plans"><?php foreach ($PR['items'] as $p) echo ui_card($p, 'premium'); ?></div>
    <div class="feats">
      <div class="feat g rv"><?= ic('upload') ?><b>آپلود تا ۴ گیگابایت</b><span>فایل‌های حجیم را بدونِ تقسیم بفرستید.</span></div>
      <div class="feat g rv"><?= ic('bolt') ?><b>دانلود با حداکثر سرعت</b><span>بدونِ محدودیتِ سرعت.</span></div>
      <div class="feat g rv"><?= ic('chat') ?><b>تبدیل ویس به متن</b><span>پیامِ صوتی را بخوانید.</span></div>
      <div class="feat g rv"><?= ic('star') ?><b>نشان و ایموجیِ ویژه</b><span>نشانِ پریمیوم و استیکرهای اختصاصی.</span></div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($G && $G['items']): ?>
<section class="sec" id="gifts">
  <div class="wrap">
    <div class="sec-head rv">
      <div><span class="kicker pink g"><?= ic('gift') ?> <?= e($G['title']) ?></span>
      <h2>گیفت برای <span class="gt">هر مناسبتی</span></h2>
      <p><?= e($G['subtitle']) ?></p></div>
      <a class="btn b-glass" href="<?= e(url('cat', ['slug' => $G['slug']])) ?>">همه‌ی گیفت‌ها <?= ic('arrow') ?></a>
    </div>
    <div class="gift-grid"><?php foreach (array_slice($G['items'], 0, 6) as $p) echo ui_card($p, 'gifts'); ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($N && $N['items']): ?>
<section class="sec" id="numbers">
  <div class="wrap">
    <div class="sec-head rv">
      <div><span class="kicker grn g"><?= ic('phone') ?> <?= e($N['title']) ?></span>
      <h2>شماره مجازیِ <span class="gt">کشورهای مختلف</span></h2>
      <p><?= e($N['subtitle']) ?>. شماره و کدِ تایید در صفحه‌ی سفارش نمایش داده می‌شود.</p></div>
      <a class="btn b-glass" href="<?= e(url('cat', ['slug' => $N['slug']])) ?>">همه‌ی کشورها <?= ic('arrow') ?></a>
    </div>
    <div class="ctry-grid"><?php foreach (array_slice($N['items'], 0, 8) as $p) echo ui_card($p, 'numbers'); ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($IG && $IG['items']): ?>
<section class="sec" id="instagram">
  <div class="wrap">
    <div class="ig-band g">
      <span class="halo h1"></span><span class="halo h2"></span>
      <div class="sec-head rv">
        <div><span class="ig-logo"><?= ic('insta') ?></span>
        <h2>افزایشِ <span class="gt-ig">فالوور، لایک و بازدید</span></h2>
        <p><?= e($IG['subtitle']) ?>. پیج باید عمومی (Public) باشد.</p></div>
        <a class="btn b-glass" href="<?= e(url('cat', ['slug' => $IG['slug']])) ?>">همه‌ی خدمات <?= ic('arrow') ?></a>
      </div>
      <div class="svc-grid"><?php foreach (array_slice($IG['items'], 0, 4) as $p) echo ui_card($p, 'instagram'); ?></div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($TG && $TG['items']): ?>
<section class="sec" id="telegram">
  <div class="wrap">
    <div class="sec-head rv">
      <div><span class="kicker g"><?= ic('megaphone') ?> <?= e($TG['title']) ?></span>
      <h2>ممبر، بازدید و <span class="gt">ری‌اکشنِ کانال</span></h2>
      <p><?= e($TG['subtitle']) ?></p></div>
      <a class="btn b-glass" href="<?= e(url('cat', ['slug' => $TG['slug']])) ?>">همه‌ی خدمات <?= ic('arrow') ?></a>
    </div>
    <div class="svc-grid"><?php foreach (array_slice($TG['items'], 0, 4) as $p) echo ui_card($p, 'telegram'); ?></div>
  </div>
</section>
<?php endif; ?>

<section class="sec" id="how">
  <div class="wrap">
    <div class="sec-head center rv">
      <span class="kicker g"><?= ic('bolt') ?> مراحلِ خرید</span>
      <h2>خرید در <span class="gt">چهار قدم</span></h2>
    </div>
    <div class="steps">
      <div class="step rv"><div class="step-n g blur"><?= ic('bag') ?><i>۱</i></div><b>انتخابِ خدمت</b><span>بسته یا سرویسِ موردنظر را انتخاب کنید.</span></div>
      <div class="step rv"><div class="step-n g blur"><?= ic('edit') ?><i>۲</i></div><b>ثبتِ مشخصات</b><span>آیدی، لینک یا تعداد را وارد کنید.</span></div>
      <div class="step rv"><div class="step-n g blur"><?= ic('card') ?><i>۳</i></div><b>پرداختِ امن</b><span>درگاهِ آنلاین، کارت‌به‌کارت یا کیف پول.</span></div>
      <div class="step rv"><div class="step-n g blur"><?= ic('check') ?><i>۴</i></div><b>تحویل و پیگیری</b><span>وضعیتِ سفارش را لحظه‌ای ببینید.</span></div>
    </div>
  </div>
</section>

<section class="sec" id="faq">
  <div class="wrap">
    <div class="sec-head center rv">
      <span class="kicker g"><?= ic('help') ?> سوالاتِ متداول</span>
      <h2>جوابِ سوال‌های <span class="gt">پرتکرار</span></h2>
    </div>
    <div class="faq">
      <?php foreach ([
        ['برای خرید باید ثبت‌نام کنم؟', 'نه. بدونِ ثبت‌نام هم می‌توانید بخرید؛ فقط شماره موبایل برای پیگیری لازم است. با ثبت‌نام، کیف پول و تاریخچه‌ی سفارش‌ها را هم دارید.'],
        ['رمز یا کدِ ورودِ حسابم را می‌خواهید؟', 'هرگز. برای تلگرام فقط آیدی و برای اینستاگرام فقط لینکِ پیج یا پست لازم است. این اطلاعات را به هیچ‌کس ندهید.'],
        ['پرداخت چطور است؟', 'درگاهِ آنلاین، کارت‌به‌کارت با آپلودِ رسید، یا کیف پولِ حساب. بعد از تاییدِ پرداخت، سفارش در صفِ انجام قرار می‌گیرد.'],
        ['سفارشم را چطور پیگیری کنم؟', 'بعد از ثبت، به صفحه‌ی سفارش می‌روید و وضعیت لحظه‌ای به‌روز می‌شود. بعدا هم با کدِ سفارش و شماره موبایل از «پیگیری سفارش» پیدایش می‌کنید.'],
        ['شماره مجازی چطور تحویل می‌شود؟', 'بعد از پرداخت، شماره در صفحه‌ی سفارش نمایش داده می‌شود؛ آن را در تلگرام وارد کنید تا کدِ تایید همان‌جا ظاهر شود.'],
        ['اگر سفارش انجام نشود؟', 'مبلغ به کیف پولِ حسابتان برمی‌گردد، یا اگر بدونِ حساب خریده‌اید، پشتیبانی با شما هماهنگ و وجه را برمی‌گرداند.'],
      ] as $i => [$q, $a]): ?>
      <details class="g rv"<?= $i === 0 ? ' open' : '' ?>><summary><span class="q"><?= ic('help') ?></span><?= e($q) ?><?= ic('chev', 'chev') ?></summary><p><?= e($a) ?></p></details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="wrap rv">
  <div class="cta">
    <div><h2>همین حالا اولین سفارشتان را ثبت کنید</h2><p>پرداختِ امن، تحویلِ سریع و پیگیریِ لحظه‌ای — بدونِ رمز و دردسر.</p></div>
    <div class="cta-acts">
      <a class="btn b-white b-lg" href="#services"><?= ic('bag') ?> شروع خرید</a>
      <a class="btn b-line b-lg" href="<?= e(url($me ? 'account' : 'register')) ?>"><?= $me ? 'حساب من' : 'ساختِ حساب' ?></a>
    </div>
  </div>
</section>
<?php
    return get_defined_vars();
}

function nsv_layout(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><?php
$site = setting('site_name');
$cats = $cats ?? categories();
$sup = trim((string)setting('support'), '@ ');
$chan = trim((string)setting('channel'), '@ ');
?><!doctype html>
<html lang="fa" dir="rtl" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(($title ?? '') !== '' ? $title . ' — ' . $site : $site . ' — ' . setting('tagline')) ?></title>
<meta name="description" content="<?= e($site . ' — خرید استارز، پریمیوم و گیفت تلگرام، شماره مجازی و خدمات اینستاگرام با پرداخت امن و تحویل سریع.') ?>">
<meta name="theme-color" content="#05060F">
<link rel="icon" href="<?= e(NS_SELF) ?>?asset=icon" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(NS_SELF) ?>?asset=css&amp;v=<?= NS_VERSION ?>">
<script><?= theme_boot_js() ?></script>
</head>
<body>
<?php nsv__sprite([]); ?>
<div class="bg" aria-hidden="true">
  <div class="aur a1"></div><div class="aur a2"></div><div class="aur a3"></div><div class="aur a4"></div><div class="aur a5"></div>
  <canvas id="sky"></canvas><div class="bg-grid"></div><div class="bg-spot" id="spot"></div><div class="bg-noise"></div>
</div>

<?php if (trim((string)setting('notice')) !== ''): ?>
<div class="notice"><?= ic('bolt') ?><?= e(setting('notice')) ?></div>
<?php endif; ?>

<header class="hdr">
  <div class="hdr-in g blur">
    <a href="<?= e(url()) ?>" class="logo"><span class="logo-mark"><?= ic('star') ?></span><span><?= e($site) ?><small><?= e(setting('tagline')) ?></small></span></a>
    <nav class="nav">
      <?php foreach ($cats as $c): ?><a href="<?= e(url('cat', ['slug' => $c['slug']])) ?>"><?= e(preg_replace('/^(خدمات |تلگرام )/u', '', $c['title'])) ?></a><?php endforeach; ?>
      <a href="<?= e(url('track')) ?>">پیگیری سفارش</a>
    </nav>
    <div class="hdr-act">
      <button type="button" class="icon-btn" id="theme" aria-label="تغییر تم"><?= ic('sun', 'i-sun') ?><?= ic('moon', 'i-moon') ?></button>
      <?php if ($me): ?>
        <a class="acct" href="<?= e(url('account')) ?>"><span class="t"><b><?= e($me['name']) ?></b><span><?= toman($me['balance']) ?></span></span><span class="av"><?= e(mb_substr($me['name'], 0, 1)) ?></span></a>
      <?php else: ?>
        <a class="btn b-pri hide-sm" href="<?= e(url('login')) ?>"><?= ic('user') ?> ورود / ثبت‌نام</a>
        <a class="icon-btn show-sm" href="<?= e(url('login')) ?>" aria-label="ورود"><?= ic('user') ?></a>
      <?php endif; ?>
      <button type="button" class="icon-btn menu-btn" id="menuBtn" aria-label="منو" aria-expanded="false"><?= ic('menu') ?></button>
    </div>
  </div>
</header>
<nav class="drawer g blur" id="drawer">
  <?php foreach ($cats as $c): ?><a href="<?= e(url('cat', ['slug' => $c['slug']])) ?>"><?= ic($c['icon'] ?: 'bag') ?><?= e($c['title']) ?></a><?php endforeach; ?>
  <a href="<?= e(url('track')) ?>"><?= ic('search') ?>پیگیری سفارش</a>
  <a href="<?= e(url($me ? 'account' : 'login')) ?>"><?= ic('user') ?><?= $me ? 'حساب من' : 'ورود / ثبت‌نام' ?></a>
</nav>

<?php $fl = flashes(); if ($fl): ?>
<div class="wrap flashes">
  <?php foreach ($fl as [$t, $m]): ?><div class="alert <?= $t === 'ok' ? 'a-ok' : 'a-err' ?> g"><?= ic($t === 'ok' ? 'check' : 'alert') ?><span><?= e($m) ?></span></div><?php endforeach; ?>
</div>
<?php endif; ?>

<main id="main"><?= $content ?></main>

<footer class="ftr g">
  <div class="wrap">
    <div class="ftr-grid">
      <div>
        <a href="<?= e(url()) ?>" class="logo"><span class="logo-mark"><?= ic('star') ?></span><span><?= e($site) ?></span></a>
        <p><?= e(setting('about')) ?></p>
      </div>
      <div>
        <h4>خدمات</h4>
        <ul><?php foreach ($cats as $c): ?><li><a href="<?= e(url('cat', ['slug' => $c['slug']])) ?>"><?= e($c['title']) ?></a></li><?php endforeach; ?></ul>
      </div>
      <div>
        <h4>راهنما</h4>
        <ul>
          <li><a href="<?= e(url('track')) ?>">پیگیری سفارش</a></li>
          <li><a href="<?= e(url('page', ['k' => 'terms'])) ?>">قوانین و مقررات</a></li>
          <li><a href="<?= e(url('page', ['k' => 'about'])) ?>">درباره ما</a></li>
          <li><a href="<?= e(url('page', ['k' => 'contact'])) ?>">تماس با ما</a></li>
        </ul>
      </div>
      <div>
        <h4>ارتباط با ما</h4>
        <ul>
          <?php if ($sup !== ''): ?><li><a href="https://t.me/<?= e(rawurlencode($sup)) ?>" target="_blank" rel="noopener"><?= ic('headset') ?> پشتیبانی: <span dir="ltr">@<?= e($sup) ?></span></a></li><?php endif; ?>
          <?php if ($chan !== ''): ?><li><a href="https://t.me/<?= e(rawurlencode($chan)) ?>" target="_blank" rel="noopener"><?= ic('send') ?> کانال: <span dir="ltr">@<?= e($chan) ?></span></a></li><?php endif; ?>
        </ul>
      </div>
    </div>
    <div class="ftr-bot">
      <span>© <?= fa_digits(ns_g2j((int)date('Y'), (int)date('n'), (int)date('j'))[0]) ?> <?= e($site) ?> — همه‌ی حقوق محفوظ است.</span>
      <span>پرداختِ امن · تحویلِ سریع · پشتیبانیِ واقعی</span>
    </div>
  </div>
</footer>

<div class="fab">
  <button type="button" class="top g blur" id="toTop" aria-label="بالا"><?= ic('up') ?></button>
  <?php if ($sup !== ''): ?><a class="sup" href="https://t.me/<?= e(rawurlencode($sup)) ?>" target="_blank" rel="noopener" aria-label="پشتیبانی"><?= ic('headset') ?></a><?php endif; ?>
</div>
<script src="<?= e(NS_SELF) ?>?asset=js&amp;v=<?= NS_VERSION ?>"></script>
</body>
</html>
<?php
    return get_defined_vars();
}

function nsv_order(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><?php
$st = $o['status'];
$ps = provider_state($o);
$isNum = $o['provider'] === '5sim';
$live = in_array($st, ['review', 'paid', 'processing'], true);
$paid = (int)$o['paid_at'] > 0;
$steps = [
    ['ثبتِ سفارش', true],
    ['پرداخت', $paid],
    ['در حالِ انجام', in_array($st, ['processing', 'completed'], true)],
    ['تکمیل', $st === 'completed'],
];
$methodLbl = ['wallet' => 'کیف پول', 'zarinpal' => 'درگاه آنلاین', 'card' => 'کارت به کارت'];
$f = $p ? field_meta($p['field']) : null;
?>
<section class="page-head">
  <div class="wrap">
    <nav class="crumbs"><a href="<?= e(url()) ?>">خانه</a><?= ic('arrow') ?><span>سفارش</span></nav>
    <div class="ord-head">
      <div><span class="muted sm">کدِ سفارش</span><h1 class="code" dir="ltr"><?= e($o['code']) ?> <button type="button" class="icon-btn sm" data-copy="<?= e($o['code']) ?>" aria-label="کپی"><?= ic('copy') ?></button></h1></div>
      <span class="badge <?= status_cls($st) ?>" <?= $live ? 'data-poll="' . e(url('state', ['code' => $o['code']])) . '" data-state="' . e($st . '|' . md5((string)$o['delivery'])) . '"' : '' ?>><?= ic(order_statuses()[$st][2] ?? 'clock') ?><?= e(status_label($st)) ?></span>
    </div>
  </div>
</section>

<section class="sec tight">
  <div class="wrap ord-lay">
    <div class="ord-main">
      <?php if (!in_array($st, ['canceled', 'failed', 'refunded'], true)): ?>
      <div class="timeline g">
        <?php foreach ($steps as $i => [$l, $done]): ?><div class="tl<?= $done ? ' done' : '' ?>"><i><?= $done ? ic('check') : fa($i + 1) ?></i><span><?= e($l) ?></span></div><?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($isNum && in_array($st, ['processing', 'completed'], true)): ?>
      <div class="deliv g blur num-box">
        <span class="muted sm">شماره‌ی شما</span>
        <div class="num-phone" dir="ltr"><?= e($ps['phone'] ?? '—') ?></div>
        <?php if (!empty($ps['phone'])): ?><button type="button" class="btn b-glass b-sm" data-copy="<?= e(preg_replace('/\s+/', '', (string)$ps['phone'])) ?>"><?= ic('copy') ?> کپی شماره</button><?php endif; ?>
        <?php if (!empty($ps['code'])): ?>
          <div class="code-box"><span class="muted sm">کدِ تایید</span><div class="big-code" dir="ltr"><?= e($ps['code']) ?></div>
          <button type="button" class="btn b-grn b-sm" data-copy="<?= e($ps['code']) ?>"><?= ic('copy') ?> کپی کد</button></div>
        <?php else: ?>
          <div class="wait"><span class="spin"></span>شماره را در تلگرام وارد کنید؛ کد به‌محضِ رسیدن همین‌جا نمایش داده می‌شود…</div>
          <form method="post" data-confirm="شماره لغو شود؟ فقط تا پیش از رسیدنِ کد ممکن است."><?= csrf_field() ?><input type="hidden" name="op" value="numcancel">
            <button class="btn b-glass b-sm" type="submit"><?= ic('close') ?> لغوِ شماره</button></form>
        <?php endif; ?>
      </div>
      <?php elseif (trim((string)$o['delivery']) !== ''): ?>
      <div class="deliv g blur"><h3><?= ic('bolt') ?> وضعیتِ تحویل</h3><p><?= nl2br(e($o['delivery'])) ?></p></div>
      <?php endif; ?>

      <?php if ($st === 'pending'): ?>
      <div class="pay-box g blur">
        <h3><?= ic('card') ?> پرداختِ سفارش</h3>
        <p class="muted">مبلغِ <?= toman($o['amount']) ?> را با یکی از روش‌های زیر پرداخت کنید.</p>
        <div class="pay-acts">
          <?php if (isset($methods['zarinpal'])): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="pay"><input type="hidden" name="method" value="zarinpal">
            <button class="btn b-pri b-lg b-block" type="submit"><?= ic('card') ?> پرداختِ آنلاین</button></form>
          <?php endif; ?>
          <?php if (isset($methods['wallet']) && $o['kind'] !== 'topup'): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="pay"><input type="hidden" name="method" value="wallet">
            <button class="btn b-grn b-lg b-block" type="submit"><?= ic('wallet') ?> پرداخت از کیف پول (<?= toman($me['balance'] ?? 0) ?>)</button></form>
          <?php endif; ?>
        </div>
        <?php if (card_on()): ?>
        <div class="card-pay">
          <h4><?= ic('coin') ?> کارت به کارت</h4>
          <div class="bank-card">
            <span class="muted sm"><?= e(setting('card_bank') ?: 'شماره کارت') ?></span>
            <div class="cc" dir="ltr"><?= e(setting('card_number')) ?></div>
            <div class="bank-foot"><span><?= e(setting('card_holder')) ?></span><button type="button" class="btn b-glass b-sm" data-copy="<?= e(preg_replace('/\D/', '', ns_digits(setting('card_number')))) ?>"><?= ic('copy') ?> کپی</button></div>
          </div>
          <p class="hint">دقیقا <?= toman($o['amount']) ?> واریز کنید، بعد تصویرِ رسید را بفرستید.</p>
          <form method="post" enctype="multipart/form-data" class="rcpt"><?= csrf_field() ?><input type="hidden" name="op" value="receipt">
            <label class="file g"><input type="file" name="receipt" accept="image/jpeg,image/png,image/webp" required><?= ic('upload') ?><span data-file>انتخابِ تصویرِ رسید</span></label>
            <input class="inp" type="text" name="rnote" maxlength="120" placeholder="چهار رقمِ آخرِ کارت یا شماره پیگیری (اختیاری)">
            <button class="btn b-gold b-block" type="submit"><?= ic('upload') ?> ارسالِ رسید</button>
          </form>
        </div>
        <?php endif; ?>
        <form method="post" class="cancel-f" data-confirm="سفارش لغو شود؟"><?= csrf_field() ?><input type="hidden" name="op" value="cancel"><button class="link" type="submit">لغوِ سفارش</button></form>
      </div>
      <?php elseif ($st === 'review'): ?>
      <div class="deliv g blur"><h3><?= ic('eye') ?> رسید در حالِ بررسی است</h3><p>رسیدِ شما ثبت شد. بعد از تایید، سفارش خودکار وارد صفِ انجام می‌شود — همین صفحه به‌روز می‌شود.</p></div>
      <?php elseif ($st === 'paid'): ?>
      <div class="deliv g blur"><h3><?= ic('clock') ?> در صفِ انجام</h3><p>پرداخت تایید شد و سفارش به‌زودی انجام می‌شود. همین صفحه خودکار به‌روز می‌شود.</p></div>
      <?php endif; ?>
    </div>

    <aside class="sum g blur">
      <h3>جزئیاتِ سفارش</h3>
      <div class="kv">
        <div><span>خدمت</span><b><?= e($o['title']) ?></b></div>
        <?php if ((int)$o['qty'] > 1): ?><div><span>تعداد</span><b><?= fa($o['qty']) ?></b></div><?php endif; ?>
        <?php if ((string)$o['target'] !== ''): ?><div><span><?= e($f['label'] ?? 'مقصد') ?></span><b dir="ltr" class="brk"><?= e($o['target']) ?></b></div><?php endif; ?>
        <div><span>مبلغ</span><b><?= toman($o['amount']) ?></b></div>
        <div><span>تاریخ</span><b><?= jdate($o['created']) ?></b></div>
        <?php if ($paid): ?><div><span>روشِ پرداخت</span><b><?= e($methodLbl[$o['method']] ?? '—') ?></b></div><?php endif; ?>
        <?php if ((string)$o['ref'] !== '' && $o['ref'] !== 'WALLET'): ?><div><span>کدِ پیگیریِ پرداخت</span><b dir="ltr"><?= e($o['ref']) ?></b></div><?php endif; ?>
      </div>
      <?php if ((string)$o['note'] !== ''): ?><p class="muted sm">توضیحات: <?= e($o['note']) ?></p><?php endif; ?>
      <p class="hint">این کد را نگه دارید؛ با کدِ سفارش و شماره موبایل همیشه از «پیگیری سفارش» پیدایش می‌کنید.</p>
    </aside>
  </div>
</section>
<?php
    return get_defined_vars();
}

function nsv_page(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><?php
$sup = trim((string)setting('support'), '@ ');
$chan = trim((string)setting('channel'), '@ ');
$text = $k === 'terms' ? setting('terms') : ($k === 'about' ? setting('about') : setting('contact_text'));
?>
<section class="page-head">
  <div class="wrap">
    <nav class="crumbs"><a href="<?= e(url()) ?>">خانه</a><?= ic('arrow') ?><span><?= e($title) ?></span></nav>
    <div class="ph-in"><span class="orb big o-tg"><?= ic($k === 'terms' ? 'shield' : ($k === 'about' ? 'star' : 'headset')) ?></span><div><h1><?= e($title) ?></h1></div></div>
  </div>
</section>
<section class="sec tight">
  <div class="wrap narrow">
    <div class="panel g prose">
      <?php if (trim((string)$text) !== ''): ?><p><?= nl2br(e($text)) ?></p><?php endif; ?>
      <?php if ($k === 'contact'): ?>
      <div class="contact-list">
        <?php if ($sup !== ''): ?><a class="info g" href="https://t.me/<?= e(rawurlencode($sup)) ?>" target="_blank" rel="noopener"><?= ic('headset') ?><div><b>پشتیبانی در تلگرام</b><span dir="ltr">@<?= e($sup) ?></span></div></a><?php endif; ?>
        <?php if ($chan !== ''): ?><a class="info g" href="https://t.me/<?= e(rawurlencode($chan)) ?>" target="_blank" rel="noopener"><?= ic('send') ?><div><b>کانالِ اطلاع‌رسانی</b><span dir="ltr">@<?= e($chan) ?></span></div></a><?php endif; ?>
        <a class="info g" href="<?= e(url('track')) ?>"><?= ic('search') ?><div><b>پیگیریِ سفارش</b><span>با کدِ سفارش و شماره موبایل</span></div></a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php
    return get_defined_vars();
}

function nsv_track(array $__v) {
    extract($__v, EXTR_SKIP);
    ?><section class="sec tight">
  <div class="wrap narrow">
    <form class="form-card g blur auth" method="post">
      <?= csrf_field() ?>
      <span class="orb big o-tg"><?= ic('search') ?></span>
      <h1>پیگیریِ سفارش</h1>
      <p class="muted">کدِ سفارش و شماره موبایلی که موقعِ خرید وارد کردید را بنویسید.</p>
      <?php if ($err !== ''): ?><div class="alert a-err"><?= ic('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>
      <label class="field"><span>کدِ سفارش</span><input class="inp ltr" type="text" name="code" maxlength="20" placeholder="مثلا 7KQ2MX9HTA" required autocomplete="off"></label>
      <label class="field"><span>شماره موبایل</span><input class="inp ltr" type="tel" name="mobile" placeholder="09123456789" required></label>
      <button class="btn b-pri b-lg b-block" type="submit"><?= ic('search') ?> نمایشِ سفارش</button>
      <p class="hint center">با حساب خرید کرده‌اید؟ <a href="<?= e(url($me ? 'account' : 'login')) ?>">سفارش‌ها در حسابِ شما</a></p>
    </form>
  </div>
</section>
<?php
    return get_defined_vars();
}

function ns_asset($k) {
    $types = ['css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8', 'icon' => 'image/svg+xml', 'font' => 'font/woff2'];
    if (!isset($types[$k])) { http_response_code(404); exit; }
    $etag = '"' . NS_VERSION . '-' . $k . '"';
    header('Cache-Control: public, max-age=31536000, immutable');
    header('ETag: ' . $etag);
    header('X-Content-Type-Options: nosniff');
    if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) { http_response_code(304); exit; }
    header('Content-Type: ' . $types[$k]);
    $body = ('ns_asset_' . $k)();
    if ($k !== 'font' && !ini_get('zlib.output_compression') && function_exists('ob_gzhandler')) ob_start('ob_gzhandler');
    echo $body;
    exit;
}
function ns_asset_css() {
    return <<<'NSCSS'
@property --ang{syntax:'<angle>';inherits:false;initial-value:0deg}
@font-face{font-family:Vazirmatn;src:url("?asset=font") format("woff2");font-weight:100 900;font-style:normal;font-display:swap}
:root,:root[data-theme=dark]{
  --bg0:#04050D;--bg1:#0B0E26;
  --glass:rgba(255,255,255,.045);--glass2:rgba(255,255,255,.085);--glass3:rgba(255,255,255,.13);
  --line:rgba(255,255,255,.10);--line2:rgba(255,255,255,.18);--hi:rgba(255,255,255,.22);
  --ink:#F4F6FF;--ink2:#CDD3EE;--dim:#8F98BD;--field:rgba(255,255,255,.06);
  --sh:0 24px 60px -30px rgba(0,0,0,.85);--sh2:0 40px 90px -40px rgba(0,0,0,.95);
  --aur-o:.9;--aur-blend:normal;--grid:rgba(255,255,255,.05);--noise:.06;--glow:rgba(120,140,255,.16);--star:255,255,255;
  --sheet:linear-gradient(160deg,rgba(30,34,70,.8),rgba(14,16,40,.85));
  color-scheme:dark
}
:root[data-theme=light]{
  --bg0:#EEF1FB;--bg1:#F8F9FE;
  --glass:rgba(255,255,255,.55);--glass2:rgba(255,255,255,.75);--glass3:rgba(255,255,255,.92);
  --line:rgba(30,40,90,.09);--line2:rgba(30,40,90,.16);--hi:rgba(255,255,255,.95);
  --ink:#0A0F26;--ink2:#29314F;--dim:#5E6887;--field:rgba(255,255,255,.75);
  --sh:0 20px 50px -28px rgba(40,50,110,.35);--sh2:0 34px 80px -36px rgba(40,50,110,.45);
  --aur-o:.55;--aur-blend:normal;--grid:rgba(30,40,90,.06);--noise:.035;--glow:rgba(99,102,241,.10);--star:70,80,160;
  --sheet:linear-gradient(160deg,rgba(255,255,255,.92),rgba(255,255,255,.8));
  color-scheme:light
}
:root{
  --sky:#38BDF8;--indigo:#6366F1;--violet:#A855F7;--pink:#EC4899;--gold:#FBBF24;--green:#22C55E;--red:#F43F5E;
  --grad:linear-gradient(135deg,#38BDF8 0%,#6366F1 52%,#A855F7 100%);
  --gold-g:linear-gradient(135deg,#FDE68A 0%,#FBBF24 40%,#F97316 100%);
  --vio-g:linear-gradient(135deg,#C4B5FD 0%,#A855F7 45%,#6366F1 100%);
  --pink-g:linear-gradient(135deg,#FDA4AF 0%,#EC4899 50%,#A855F7 100%);
  --grn-g:linear-gradient(135deg,#6EE7B7 0%,#22C55E 50%,#0D9488 100%);
  --ig:linear-gradient(45deg,#F58529 0%,#DD2A7B 40%,#8134AF 75%,#515BD4 100%);
  --r:20px;--r2:28px
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{background:var(--bg0);color:var(--ink);font-family:Vazirmatn,Vazir,Tahoma,system-ui,sans-serif;font-size:15.5px;line-height:1.85;overflow-x:hidden;-webkit-font-smoothing:antialiased;min-height:100vh}
a{color:inherit;text-decoration:none}
p a,.hint a,.alert a{color:var(--sky);font-weight:700}
button,input,select,textarea{font:inherit;color:inherit}
img,svg{display:block}
[hidden]{display:none!important}
.ic{width:1.15em;height:1.15em;flex:none;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.wrap{width:100%;max-width:1240px;margin:0 auto;padding:0 20px}
.wrap.narrow{max-width:560px}
.muted{color:var(--dim)}
.sm{font-size:12.5px}
.center{text-align:center}
.brk{word-break:break-all}
.gt{background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent}
.gt-gold{background:var(--gold-g);-webkit-background-clip:text;background-clip:text;color:transparent}
.gt-ig{background:var(--ig);-webkit-background-clip:text;background-clip:text;color:transparent}
.shine{background:linear-gradient(90deg,#60A5FA,#A78BFA,#F472B6,#FBBF24,#34D399,#60A5FA);background-size:300% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;animation:hue 9s linear infinite}
@keyframes hue{to{background-position:300% 0}}
:focus-visible{outline:2px solid var(--sky);outline-offset:3px;border-radius:10px}
::selection{background:rgba(99,102,241,.4)}

.bg{position:fixed;inset:0;z-index:-1;overflow:hidden;background:radial-gradient(120% 90% at 50% 0%,var(--bg1),var(--bg0) 70%)}
.aur{position:absolute;width:64vmax;height:64vmax;border-radius:50%;opacity:var(--aur-o);mix-blend-mode:var(--aur-blend);will-change:transform}
.a1{background:radial-gradient(closest-side,rgba(124,58,237,.55),transparent);top:-22vmax;right:-14vmax;animation:d1 28s ease-in-out infinite alternate}
.a2{background:radial-gradient(closest-side,rgba(37,99,235,.5),transparent);top:10vmax;left:-24vmax;animation:d2 34s ease-in-out infinite alternate}
.a3{background:radial-gradient(closest-side,rgba(6,182,212,.36),transparent);bottom:-30vmax;right:4vmax;animation:d3 30s ease-in-out infinite alternate}
.a4{background:radial-gradient(closest-side,rgba(236,72,153,.32),transparent);bottom:-10vmax;left:10vmax;width:48vmax;height:48vmax;animation:d4 38s ease-in-out infinite alternate}
.a5{background:radial-gradient(closest-side,rgba(251,191,36,.2),transparent);top:30vmax;right:24vmax;width:40vmax;height:40vmax;animation:d5 26s ease-in-out infinite alternate}
@keyframes d1{to{transform:translate3d(-20vmax,16vmax,0) scale(1.18)}}
@keyframes d2{to{transform:translate3d(18vmax,-8vmax,0) scale(.9)}}
@keyframes d3{to{transform:translate3d(-14vmax,-18vmax,0) scale(1.2)}}
@keyframes d4{to{transform:translate3d(22vmax,-14vmax,0) scale(1.1)}}
@keyframes d5{to{transform:translate3d(-16vmax,10vmax,0) scale(1.25)}}
#sky{position:absolute;inset:0;width:100%;height:100%}
.bg-grid{position:absolute;inset:0;background-image:linear-gradient(var(--grid) 1px,transparent 1px),linear-gradient(90deg,var(--grid) 1px,transparent 1px);background-size:64px 64px;-webkit-mask-image:radial-gradient(ellipse 80% 60% at 50% 0%,#000 20%,transparent 75%);mask-image:radial-gradient(ellipse 80% 60% at 50% 0%,#000 20%,transparent 75%)}
.bg-noise{position:absolute;inset:-50%;opacity:var(--noise);pointer-events:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E")}
.bg-spot{position:absolute;left:0;top:0;width:640px;height:640px;margin:-320px 0 0 -320px;border-radius:50%;pointer-events:none;background:radial-gradient(closest-side,var(--glow),transparent);transform:translate3d(var(--cx,-999px),var(--cy,-999px),0)}

.g{position:relative;isolation:isolate;background:linear-gradient(150deg,var(--glass2),var(--glass));border:1px solid var(--line);border-radius:var(--r);box-shadow:inset 0 1px 0 var(--hi),var(--sh)}
.g::before{content:"";position:absolute;inset:0;border-radius:inherit;z-index:-1;pointer-events:none;opacity:0;transition:opacity .35s;background:radial-gradient(380px circle at var(--mx,50%) var(--my,50%),rgba(255,255,255,.13),transparent 45%)}
.g:hover::before{opacity:1}
.g::after{content:"";position:absolute;inset:-1px;border-radius:inherit;padding:1px;pointer-events:none;background:linear-gradient(140deg,rgba(255,255,255,.42),rgba(255,255,255,0) 28%,rgba(255,255,255,0) 72%,rgba(255,255,255,.2));-webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;mask-composite:exclude}
:root[data-theme=light] .g::after{background:linear-gradient(140deg,#fff,rgba(255,255,255,0) 30%,rgba(255,255,255,0) 70%,rgba(255,255,255,.8))}
:root[data-theme=light] .g::before{background:radial-gradient(380px circle at var(--mx,50%) var(--my,50%),rgba(99,102,241,.1),transparent 45%)}
.blur{backdrop-filter:blur(14px) saturate(160%);-webkit-backdrop-filter:blur(14px) saturate(160%)}
.lift{transition:transform .28s cubic-bezier(.2,.8,.2,1),border-color .28s,box-shadow .28s}
.lift:hover{transform:translateY(-6px);border-color:var(--line2);box-shadow:inset 0 1px 0 var(--hi),var(--sh2)}
.ring{position:absolute;inset:-1px;border-radius:inherit;padding:1.6px;pointer-events:none;z-index:2;background:conic-gradient(from var(--ang),transparent 0 55%,var(--r1,#FBBF24),var(--r2,#F472B6),var(--r3,#60A5FA),transparent 96%);-webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;mask-composite:exclude;animation:spin 5s linear infinite}
@keyframes spin{to{--ang:360deg}}

.btn{position:relative;overflow:hidden;display:inline-flex;align-items:center;justify-content:center;gap:9px;height:48px;padding:0 22px;border-radius:15px;border:1px solid transparent;font-weight:800;font-size:14.5px;cursor:pointer;white-space:nowrap;transition:transform .2s,box-shadow .2s,background .2s,border-color .2s,opacity .2s;background:none}
.btn::after{content:"";position:absolute;inset:0;background:linear-gradient(110deg,transparent 30%,rgba(255,255,255,.45) 50%,transparent 70%);transform:translateX(130%);transition:transform .75s;pointer-events:none}
.btn:hover::after{transform:translateX(-130%)}
.btn:hover{transform:translateY(-2px)}
.btn:active{transform:translateY(0) scale(.98)}
.btn[disabled]{opacity:.5;pointer-events:none}
.btn .ic{width:18px;height:18px}
.b-pri{background:var(--grad);color:#fff;box-shadow:0 14px 34px -12px rgba(99,102,241,.85),inset 0 1px 0 rgba(255,255,255,.35)}
.b-gold{background:var(--gold-g);color:#2A1300;box-shadow:0 14px 34px -14px rgba(249,115,22,.85),inset 0 1px 0 rgba(255,255,255,.5)}
.b-ig{background:var(--ig);color:#fff;box-shadow:0 14px 34px -14px rgba(221,42,123,.85),inset 0 1px 0 rgba(255,255,255,.3)}
.b-grn{background:var(--grn-g);color:#fff;box-shadow:0 14px 34px -14px rgba(34,197,94,.8),inset 0 1px 0 rgba(255,255,255,.35)}
.b-danger{background:linear-gradient(135deg,#FB7185,#E11D48);color:#fff;box-shadow:0 14px 30px -14px rgba(225,29,72,.8)}
.b-glass{background:var(--glass2);border-color:var(--line2);color:var(--ink);box-shadow:inset 0 1px 0 var(--hi)}
.b-glass:hover{border-color:rgba(56,189,248,.55);color:var(--sky)}
.b-white{background:#fff;color:#3730A3;box-shadow:0 16px 34px -14px rgba(0,0,0,.5)}
.b-line{background:rgba(255,255,255,.14);color:#fff;border-color:rgba(255,255,255,.4)}
.b-lg{height:56px;padding:0 28px;font-size:15.5px;border-radius:17px}
.b-sm{height:36px;padding:0 13px;font-size:12.5px;border-radius:11px}
.b-block{width:100%}
.link{background:none;border:0;color:var(--dim);font-size:13px;font-weight:700;cursor:pointer;text-decoration:underline;text-underline-offset:4px}
.link:hover{color:var(--red)}
.spin{width:18px;height:18px;border-radius:50%;border:2.5px solid rgba(127,127,160,.3);border-top-color:var(--sky);animation:rot .8s linear infinite;flex:none}
@keyframes rot{to{transform:rotate(360deg)}}

.notice{text-align:center;font-size:13px;font-weight:700;padding:9px 16px;color:#fff;background:linear-gradient(90deg,rgba(56,189,248,.85),rgba(99,102,241,.85),rgba(168,85,247,.85))}
.notice .ic{display:inline-block;vertical-align:-3px;margin-left:6px;width:15px;height:15px}
.hdr{position:sticky;top:12px;z-index:50;padding:0 20px;margin-top:12px}
.hdr-in{max-width:1200px;margin:0 auto;display:flex;align-items:center;gap:18px;height:68px;padding:0 12px 0 14px;border-radius:22px}
.logo{display:flex;align-items:center;gap:11px;font-weight:900;font-size:18.5px;letter-spacing:-.2px;line-height:1.3}
.logo-mark{width:42px;height:42px;border-radius:14px;background:var(--grad);display:grid;place-items:center;color:#fff;box-shadow:0 10px 26px -10px rgba(99,102,241,.95),inset 0 1px 0 rgba(255,255,255,.4);flex:none}
.logo-mark .ic{width:22px;height:22px;fill:#fff;stroke:none}
.logo small{display:block;font-size:11px;font-weight:600;color:var(--dim);letter-spacing:0}
.nav{display:flex;align-items:center;gap:2px;margin-inline-start:auto}
.nav a{padding:8px 12px;border-radius:12px;font-size:14px;font-weight:700;color:var(--ink2);transition:background .15s,color .15s;white-space:nowrap}
.nav a:hover{background:var(--glass2);color:var(--ink)}
.hdr-act{display:flex;align-items:center;gap:8px}
.icon-btn{width:46px;height:46px;border-radius:14px;border:1px solid var(--line2);background:var(--glass);display:inline-grid;place-items:center;cursor:pointer;color:var(--ink);transition:background .15s;flex:none;vertical-align:middle}
.icon-btn:hover{background:var(--glass3)}
.icon-btn .ic{width:20px;height:20px}
.icon-btn.sm{width:32px;height:32px;border-radius:10px}
.icon-btn.sm .ic{width:16px;height:16px}
.i-sun{display:none}
:root[data-theme=light] .i-sun{display:block}
:root[data-theme=light] .i-moon{display:none}
.acct{display:flex;align-items:center;gap:10px;height:46px;padding:0 8px 0 14px;border-radius:14px;border:1px solid var(--line2);background:var(--glass)}
.acct:hover{background:var(--glass3)}
.acct .av{width:32px;height:32px;border-radius:11px;background:var(--grad);display:grid;place-items:center;color:#fff;font-weight:900}
.acct .t{display:flex;flex-direction:column;line-height:1.35}
.acct .t b{font-size:13px;max-width:110px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.acct .t span{font-size:11.5px;color:var(--dim);font-weight:700}
.menu-btn,.show-sm{display:none}
.drawer{display:none}
.flashes{margin-top:18px;display:grid;gap:10px}

.alert{display:flex;align-items:flex-start;gap:10px;padding:13px 16px;border-radius:15px;font-size:14px;font-weight:700;line-height:1.8;margin-bottom:12px}
.alert .ic{width:19px;height:19px;margin-top:3px}
.alert form{margin-inline-start:auto}
.a-ok{background:rgba(34,197,94,.14);border-color:rgba(34,197,94,.35);color:#86EFAC}
.a-err{background:rgba(244,63,94,.12);border:1px solid rgba(244,63,94,.35);color:#FDA4AF}
.a-warn{background:rgba(251,191,36,.12);border-color:rgba(251,191,36,.35);color:#FDE68A;align-items:center}
:root[data-theme=light] .a-ok{color:#15803D}
:root[data-theme=light] .a-err{color:#BE123C}
:root[data-theme=light] .a-warn{color:#A16207}

.hero{padding:64px 0 34px}
.hero-in{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:52px;align-items:center}
.pill{display:inline-flex;align-items:center;gap:9px;padding:6px 16px 6px 8px;border-radius:99px;font-size:13px;font-weight:700;color:var(--ink2)}
.pill .dot{width:24px;height:24px;border-radius:99px;background:rgba(34,197,94,.16);display:grid;place-items:center}
.pill .dot::after{content:"";width:8px;height:8px;border-radius:99px;background:var(--green);animation:ping 2s infinite}
@keyframes ping{0%{box-shadow:0 0 0 0 rgba(34,197,94,.6)}80%,100%{box-shadow:0 0 0 10px rgba(34,197,94,0)}}
.hero h1{font-size:clamp(34px,5vw,62px);line-height:1.26;font-weight:900;letter-spacing:-1px;margin:24px 0 18px}
.rot{display:inline-block;transition:opacity .35s,transform .35s,filter .35s}
.rot.out{opacity:0;transform:translateY(-18px);filter:blur(6px)}
.rot.pre{opacity:0;transform:translateY(18px);filter:blur(6px);transition:none}
.hero-sub{font-size:clamp(15px,1.4vw,18px);color:var(--ink2);max-width:600px;line-height:2}
.hero-cta{display:flex;flex-wrap:wrap;gap:12px;margin:32px 0 28px}
.trust{display:flex;flex-wrap:wrap;gap:10px}
.trust span{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:700;color:var(--ink2);padding:7px 14px;border-radius:99px}
.trust .ic{width:17px;height:17px;color:var(--green)}
.bento{display:grid;grid-template-columns:minmax(0,1.12fr) minmax(0,1fr);gap:14px}
.bt{display:flex;flex-direction:column;padding:18px;border-radius:24px;transition:transform .25s}
.bt:hover{transform:translateY(-4px)}
.bt-row{display:flex;align-items:center;gap:13px;margin-bottom:14px}
.bt-k{font-size:12px;color:var(--dim);font-weight:700;line-height:1.6}
.bt-t{font-size:15px;font-weight:800;line-height:1.6}
.bt-p{margin-top:auto;display:flex;align-items:baseline;justify-content:space-between;gap:8px;padding-top:12px;border-top:1px dashed var(--line2);font-size:12.5px}
.bt-p .amt{font-size:16px}
.bt-star{grid-row:1/3;padding:24px;overflow:hidden;animation:float 7s ease-in-out infinite}
.bt-star::before{opacity:1;background:radial-gradient(260px circle at 85% 0%,rgba(251,191,36,.28),transparent 70%)}
.bt-star .bt-t{font-size:32px;font-weight:900;line-height:1.35;margin:2px 0 4px}
.bt-star .bt-p{margin:22px 0 16px;padding:14px;border:1px solid var(--line);border-radius:15px;background:var(--glass)}
.bt-star .bt-p .amt{font-size:22px}
.bt-f1{animation:float 8s ease-in-out -2s infinite}
.bt-f2{animation:float 9s ease-in-out -4s infinite}
@keyframes float{0%,100%{translate:0 0}50%{translate:0 -9px}}
.big-ic{width:68px;height:68px;border-radius:22px;background:var(--gold-g);display:grid;place-items:center;margin-bottom:18px;box-shadow:0 18px 36px -12px rgba(249,115,22,.9),inset 0 1px 0 rgba(255,255,255,.6)}
.big-ic .ic{width:36px;height:36px;fill:#fff;stroke:none}
.orb{position:relative;width:48px;height:48px;border-radius:16px;display:grid;place-items:center;color:#fff;flex:none;box-shadow:0 12px 26px -12px rgba(0,0,0,.6),inset 0 1px 0 rgba(255,255,255,.4)}
.orb .ic{width:24px;height:24px}
.orb.sm{width:40px;height:40px;border-radius:13px}
.orb.sm .ic{width:20px;height:20px}
.orb.big{width:64px;height:64px;border-radius:20px}
.orb.big .ic{width:32px;height:32px}
.o-gold{background:var(--gold-g)}.o-vio{background:var(--vio-g)}.o-pink{background:var(--pink-g)}.o-grn{background:var(--grn-g)}.o-ig{background:var(--ig)}.o-tg{background:var(--grad)}
.emo{font-size:30px;line-height:1;width:48px;height:48px;border-radius:16px;background:var(--glass2);border:1px solid var(--line);display:grid;place-items:center;flex:none}
.emo.xl{font-size:44px;width:76px;height:76px;border-radius:24px}

.ticker{margin-top:38px;overflow:hidden;border-radius:0;border-inline:0;-webkit-mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent)}
.ticker-track{display:flex;width:max-content;animation:tick 46s linear infinite}
.ticker:hover .ticker-track{animation-play-state:paused}
.tk{display:flex;align-items:center;gap:10px;padding:15px 28px;font-size:14px;white-space:nowrap;border-inline-end:1px solid var(--line)}
.tk .ic{width:18px;height:18px;color:var(--sky)}
.tk b{font-weight:900}
.tk .live{width:7px;height:7px;border-radius:99px;background:var(--green);box-shadow:0 0 10px var(--green)}
@keyframes tick{from{transform:translateX(0)}to{transform:translateX(50%)}}

.sec{padding:96px 0}
.sec.tight{padding:34px 0 80px}
.sec-head{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:40px;flex-wrap:wrap}
.sec-head.center{flex-direction:column;align-items:center;text-align:center}
.kicker{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:800;padding:7px 15px;border-radius:99px;margin-bottom:16px;color:var(--sky)}
.kicker .ic{width:16px;height:16px}
.kicker.gold{color:var(--gold)}.kicker.vio{color:#C084FC}.kicker.pink{color:#F472B6}.kicker.grn{color:#4ADE80}
.sec h2{font-size:clamp(27px,3.4vw,44px);line-height:1.35;font-weight:900;letter-spacing:-.6px}
.sec-head p{color:var(--dim);max-width:600px;margin-top:10px}
.sec-head.center p{margin-inline:auto}
.amt{font-weight:900;font-variant-numeric:tabular-nums}
.cur{font-size:.78em;color:var(--dim);font-weight:700}
.ask{font-weight:700;color:var(--dim);font-size:.92em}
.tag{display:inline-block;font-size:11.5px;font-weight:800;padding:2px 11px;border-radius:99px;background:rgba(244,63,94,.14);color:#FB7185;white-space:nowrap;border:1px solid rgba(244,63,94,.22);line-height:1.8}
.tag.gold{background:rgba(251,191,36,.14);color:var(--gold);border-color:rgba(251,191,36,.25)}

.cat-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
.catc{display:flex;flex-direction:column;gap:14px;padding:28px;border-radius:var(--r2);overflow:hidden}
.catc .halo{position:absolute;inset:auto -60px -80px auto;width:240px;height:240px;border-radius:50%;opacity:.22;filter:blur(40px);z-index:-1}
.catc h3{font-size:19.5px;font-weight:900}
.catc p{color:var(--dim);font-size:14px;line-height:1.95;flex:1}
.catc-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;padding-top:15px;border-top:1px dashed var(--line2);font-size:13.5px}
.catc-foot .amt{font-size:16px}
.go{display:inline-flex;align-items:center;gap:6px;font-weight:800;color:var(--sky)}
.go .ic{width:16px;height:16px;transition:transform .2s}
.catc:hover .go .ic{transform:translateX(-5px)}

.stars-lay{display:grid;grid-template-columns:380px minmax(0,1fr);gap:24px;align-items:start}
.calc{position:sticky;top:100px;padding:26px;border-radius:var(--r2);overflow:hidden}
.calc h3{font-size:19px;font-weight:900;display:flex;align-items:center;gap:10px}
.calc h3 .ic{color:var(--gold);fill:var(--gold);stroke:none;width:24px;height:24px}
.lbl{display:block;font-size:13px;font-weight:700;color:var(--dim);margin:18px 0 8px}
input[type=range]{width:100%;margin-top:16px;accent-color:var(--gold);height:6px}
.chips{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px}
.chip{padding:6px 13px;border-radius:11px;border:1px solid var(--line2);background:var(--glass);cursor:pointer;font-size:13px;font-weight:800}
.chip:hover,.chip.on{border-color:var(--gold);color:var(--gold);background:rgba(251,191,36,.1)}
.calc-total{display:flex;flex-direction:column;margin:20px 0 16px;padding:16px 18px;border-radius:17px;background:linear-gradient(135deg,rgba(251,191,36,.16),rgba(249,115,22,.06));border:1px solid rgba(251,191,36,.3)}
.calc-total b{font-size:30px;font-weight:900;line-height:1.5}
.calc-total b small{font-size:14px;color:var(--dim)}
.pk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px}
.pk{display:flex;flex-direction:column;gap:6px;padding:20px}
.pk:hover{border-color:rgba(251,191,36,.45)}
.pk-top{display:flex;align-items:center;justify-content:space-between}
.pk-star{width:42px;height:42px;border-radius:14px;background:rgba(251,191,36,.14);border:1px solid rgba(251,191,36,.25);display:grid;place-items:center}
.pk-star .ic{width:22px;height:22px;fill:var(--gold);stroke:none}
.pk-q{font-size:24px;font-weight:900;line-height:1.4;margin-top:8px}
.pk .amt{font-size:17px}
.pk .btn{margin-top:10px;height:42px}

.plans{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px;align-items:stretch}
.plan{display:flex;flex-direction:column;padding:32px 26px;border-radius:var(--r2)}
.plan.hot{--r1:#C084FC;--r2:#F472B6;--r3:#60A5FA;box-shadow:inset 0 1px 0 var(--hi),0 40px 80px -40px rgba(168,85,247,.8)}
.plan-badge{position:absolute;top:-15px;inset-inline-start:50%;transform:translateX(50%);background:var(--vio-g);color:#fff;font-size:12px;font-weight:800;padding:5px 16px;border-radius:99px;white-space:nowrap;z-index:3;box-shadow:0 10px 20px -8px rgba(168,85,247,.9)}
.plan-tag{position:absolute;top:22px;inset-inline-end:22px}
.plan h3{font-size:21px;font-weight:900;margin-top:18px}
.plan .muted{font-size:13.5px;min-height:48px}
.plan-price{margin:14px 0 18px}
.plan-price .amt{font-size:34px}
.plan ul{list-style:none;display:grid;gap:11px;margin-bottom:26px;flex:1}
.plan li{display:flex;gap:10px;font-size:14px;color:var(--ink2)}
.plan li .ic{width:19px;height:19px;color:#C084FC;margin-top:4px}
.feats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-top:30px}
.feat{padding:20px}
.feat .ic{width:26px;height:26px;color:#C084FC;margin-bottom:12px}
.feat b{display:block;font-size:15px;font-weight:800;margin-bottom:4px}
.feat span{font-size:13px;color:var(--dim)}

.gift-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:14px}
.gift{text-align:center;padding:20px 12px 16px;display:flex;flex-direction:column;align-items:center;gap:4px}
.gift:hover{border-color:rgba(236,72,153,.45)}
.gift-e{font-size:48px;line-height:1;width:88px;height:88px;border-radius:28px;display:grid;place-items:center;margin-bottom:10px;transition:transform .35s cubic-bezier(.2,.9,.3,1.4);background:radial-gradient(circle at 30% 25%,rgba(236,72,153,.28),rgba(168,85,247,.14) 60%,transparent 75%)}
.gift:hover .gift-e{transform:scale(1.12) rotate(-6deg)}
.gift h3{font-size:14.5px;font-weight:800}
.gift .st{display:inline-flex;align-items:center;gap:4px;font-size:12px;color:var(--gold);font-weight:800}
.gift .st .ic{width:13px;height:13px;fill:var(--gold);stroke:none}
.gift .gp{margin-bottom:10px}
.gift .amt{font-size:15px}
.gift .btn{margin-top:auto;height:38px;font-size:13px}

.search{display:flex;align-items:center;gap:10px;height:56px;padding:0 18px;border-radius:18px;margin-bottom:18px}
.search:focus-within{border-color:rgba(34,197,94,.6)}
.search .ic{width:20px;height:20px;color:var(--dim)}
.search input{flex:1;min-width:0;border:0;outline:0;background:transparent;font-size:15px}
.ctry-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.ctry{display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:18px}
.ctry:hover{border-color:rgba(34,197,94,.5)}
.flag{font-size:30px;line-height:1;width:52px;height:52px;border-radius:15px;background:var(--glass2);border:1px solid var(--line);display:grid;place-items:center;flex:none}
.ctry-t{flex:1;min-width:0}
.ctry-t b{display:block;font-size:15px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ctry-t span{font-size:12px;color:var(--dim);display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ctry-p{text-align:end;font-size:12px;color:var(--dim);white-space:nowrap}
.ctry-p .amt{display:block;font-size:15px;color:var(--ink)}

.ig-band{border-radius:34px;padding:44px;overflow:hidden}
.ig-band .halo{position:absolute;width:520px;height:520px;border-radius:50%;background:var(--ig);filter:blur(90px);z-index:-1}
.ig-band .h1{inset-inline-end:-160px;top:-220px;opacity:.35}
.ig-band .h2{inset-inline-start:-200px;bottom:-260px;opacity:.22}
.ig-logo{width:68px;height:68px;border-radius:22px;background:var(--ig);display:grid;place-items:center;color:#fff;margin-bottom:16px;box-shadow:0 18px 38px -14px rgba(221,42,123,.95)}
.ig-logo .ic{width:36px;height:36px}
.svc-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.svc{display:flex;flex-direction:column;gap:8px;padding:20px}
.svc-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px}
.svc h3{font-size:15.5px;font-weight:900;line-height:1.7}
.svc .muted{font-size:13px;line-height:1.8;flex:1}
.svc-price{display:flex;align-items:baseline;flex-wrap:wrap;gap:4px 8px}
.svc-price .amt{font-size:20px}
.per{font-size:12px;color:var(--dim);font-weight:700}
.range{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--dim)}
.range .ic{width:14px;height:14px}
.svc .btn{margin-top:8px;height:44px}

.steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px;position:relative}
.steps::before{content:"";position:absolute;top:40px;inset-inline:12%;height:2px;background:linear-gradient(90deg,transparent,var(--sky),var(--violet),var(--pink),transparent);opacity:.5}
.step{position:relative;text-align:center;padding:0 10px}
.step-n{width:80px;height:80px;margin:0 auto 18px;border-radius:26px;display:grid;place-items:center}
.step-n .ic{width:34px;height:34px;color:var(--sky)}
.step-n i{position:absolute;top:-9px;inset-inline-end:-9px;width:30px;height:30px;border-radius:99px;background:var(--grad);color:#fff;font-style:normal;font-weight:900;font-size:13px;display:grid;place-items:center;z-index:3}
.step b{display:block;font-size:17px;font-weight:900;margin-bottom:6px}
.step span{font-size:14px;color:var(--dim)}
.faq{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:14px;align-items:start}
.faq summary{list-style:none;cursor:pointer;display:flex;align-items:center;gap:14px;padding:18px 20px;font-weight:800;font-size:15px}
.faq summary::-webkit-details-marker{display:none}
.faq .q{width:36px;height:36px;border-radius:12px;background:rgba(99,102,241,.16);color:#A5B4FC;display:grid;place-items:center;flex:none}
.faq .q .ic{width:18px;height:18px}
.faq .chev{margin-inline-start:auto;width:18px;height:18px;color:var(--dim);transition:transform .25s}
.faq details[open] .chev{transform:rotate(180deg)}
.faq details p{padding:0 70px 20px 20px;color:var(--ink2);font-size:14.5px;line-height:2}
.cta{position:relative;border-radius:36px;padding:60px 50px;overflow:hidden;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:30px;align-items:center;isolation:isolate;background:linear-gradient(135deg,rgba(56,189,248,.9),rgba(99,102,241,.9) 50%,rgba(168,85,247,.9));color:#fff;box-shadow:0 40px 90px -40px rgba(99,102,241,.9),inset 0 1px 0 rgba(255,255,255,.4)}
.cta::before{content:"";position:absolute;inset:0;z-index:-1;background:radial-gradient(420px 300px at 8% 120%,rgba(255,255,255,.3),transparent 70%),radial-gradient(320px 240px at 96% -10%,rgba(253,230,138,.55),transparent 70%)}
.cta h2{font-size:clamp(26px,3vw,38px);font-weight:900;line-height:1.4}
.cta p{opacity:.92;margin-top:8px}
.cta-acts{display:flex;gap:12px;flex-wrap:wrap}

.ftr{margin:96px 20px 20px;border-radius:32px;padding:56px 0 24px}
.ftr-grid{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr) minmax(0,1fr) minmax(0,1fr);gap:34px}
.ftr p{color:var(--dim);font-size:14px;margin-top:14px;max-width:360px}
.ftr h4{font-size:15px;font-weight:900;margin-bottom:14px}
.ftr ul{list-style:none;display:grid;gap:9px}
.ftr li a{color:var(--dim);font-size:14px;display:inline-flex;align-items:center;gap:8px}
.ftr li a:hover{color:var(--ink)}
.ftr li .ic{width:16px;height:16px}
.ftr-bot{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-top:44px;padding-top:22px;border-top:1px solid var(--line);color:var(--dim);font-size:13px}
.fab{position:fixed;bottom:22px;inset-inline-start:22px;z-index:60;display:flex;flex-direction:column;gap:10px}
.fab a,.fab button{width:54px;height:54px;border-radius:18px;display:grid;place-items:center;border:0;cursor:pointer}
.fab .sup{background:var(--grad);color:#fff;box-shadow:0 16px 34px -12px rgba(99,102,241,.9)}
.fab .sup .ic{width:26px;height:26px}
.fab .top{color:var(--ink);opacity:0;pointer-events:none;transform:translateY(10px);transition:opacity .2s,transform .2s}
.fab .top.show{opacity:1;pointer-events:auto;transform:none}

.page-head{padding:40px 0 6px}
.crumbs{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--dim);margin-bottom:18px;flex-wrap:wrap}
.crumbs a:hover{color:var(--ink)}
.crumbs .ic{width:14px;height:14px}
.ph-in{display:flex;align-items:center;gap:18px;flex-wrap:wrap}
.ph-in h1{font-size:clamp(26px,3vw,38px);font-weight:900;line-height:1.35}
.ph-act{margin-inline-start:auto}
.av-big{font-size:28px;font-weight:900}
.empty{padding:40px;text-align:center;color:var(--dim)}
.info-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:34px}
.info{display:flex;align-items:center;gap:14px;padding:18px}
.info .ic{width:26px;height:26px;color:var(--sky);flex:none}
.info b{display:block;font-size:14.5px}
.info span{font-size:12.5px;color:var(--dim)}

.field{display:block;margin-bottom:14px}
.field>span{display:block;font-size:13px;font-weight:800;color:var(--ink2);margin-bottom:7px}
.field>span em{font-style:normal;font-weight:600}
.inp{width:100%;height:50px;border-radius:14px;border:1px solid var(--line2);background:var(--field);padding:0 14px;font-size:14.5px;font-weight:600;outline:0;transition:border-color .15s,box-shadow .15s}
textarea.inp{height:auto;min-height:96px;padding:12px 14px;resize:vertical;line-height:1.8}
textarea.inp.sm{min-height:70px}
.inp:focus{border-color:var(--indigo);box-shadow:0 0 0 4px rgba(99,102,241,.16)}
.inp.ltr{direction:ltr;text-align:left}
.inp.num{direction:ltr;text-align:center;font-weight:800}
.inp.xs{width:80px;height:40px}
select.inp option{background:#0E1230;color:#fff}
:root[data-theme=light] select.inp option{background:#fff;color:#0A0F26}
.hint{display:block;font-size:12.5px;color:var(--dim);margin-top:6px;line-height:1.8}
.qrow{display:flex;gap:8px;align-items:center}
.qrow .inp{font-size:19px}
.qrow button{width:50px;height:50px;border-radius:14px;border:1px solid var(--line2);background:var(--glass2);cursor:pointer;font-size:22px;font-weight:800;flex:none}
.qrow button:hover{border-color:var(--sky);color:var(--sky)}
.two{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px}
.form-card{padding:30px;border-radius:var(--r2)}
.form-card h2{font-size:22px;font-weight:900;margin-bottom:18px}
.auth{text-align:center}
.auth .orb{margin:0 auto 14px}
.auth h1{font-size:25px;font-weight:900}
.auth>.muted{margin-bottom:20px}
.auth .field{text-align:start}
.pms{display:grid;gap:10px}
.pm{display:block;cursor:pointer}
.pm input{position:absolute;opacity:0;pointer-events:none}
.pm-in{display:flex;align-items:center;gap:14px;padding:13px 16px;border-radius:17px;transition:border-color .2s,box-shadow .2s}
.pm-in b{display:block;font-size:14.5px}
.pm-in small{display:block;font-size:12px;color:var(--dim)}
.pm-in .dot{margin-inline-start:auto;width:22px;height:22px;border-radius:99px;border:2px solid var(--line2);flex:none;position:relative}
.pm input:checked+.pm-in{border-color:rgba(99,102,241,.8);box-shadow:0 0 0 4px rgba(99,102,241,.16)}
.pm input:checked+.pm-in .dot{border-color:var(--indigo);background:radial-gradient(circle,var(--indigo) 45%,transparent 50%)}
.pm input:focus-visible+.pm-in{outline:2px solid var(--sky)}
.pms.compact .pm-in{padding:9px 12px}
.total{display:flex;align-items:center;justify-content:space-between;padding:16px 18px;border-radius:17px;margin:18px 0 14px}
.total b{font-size:26px;font-weight:900}
.total b small{font-size:13px;color:var(--dim)}

.buy-lay{display:grid;grid-template-columns:400px minmax(0,1fr);gap:24px;align-items:start}
.sum{padding:26px;border-radius:var(--r2);position:sticky;top:100px}
.sum-top{display:flex;align-items:center;gap:16px;margin-bottom:12px}
.sum h1{font-size:22px;font-weight:900;line-height:1.5}
.sum h3{font-size:17px;font-weight:900;margin-bottom:10px}
.kv{display:grid;gap:2px;margin:14px 0}
.kv>div{display:flex;justify-content:space-between;align-items:baseline;gap:14px;padding:9px 0;border-bottom:1px dashed var(--line);font-size:14px}
.kv>div:last-child{border-bottom:0}
.kv span{color:var(--dim);white-space:nowrap}
.kv b{text-align:end;font-weight:800}
.kv.two-col{grid-template-columns:minmax(0,1fr) minmax(0,1fr);column-gap:28px}
.ticks{list-style:none;display:grid;gap:9px;margin-top:12px}
.ticks li{display:flex;gap:9px;font-size:13.5px;color:var(--ink2)}
.ticks .ic{width:18px;height:18px;color:var(--green);margin-top:4px}

.ord-head{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.ord-head .code{font-size:clamp(24px,3vw,34px);font-weight:900;letter-spacing:2px;display:flex;align-items:center;gap:10px}
.badge{display:inline-flex;align-items:center;gap:8px;padding:9px 16px;border-radius:99px;font-weight:800;font-size:14px;border:1px solid transparent;white-space:nowrap}
.badge .ic{width:17px;height:17px}
.badge.sm{padding:3px 11px;font-size:12px}
.st-wait{background:rgba(251,191,36,.14);color:#FCD34D;border-color:rgba(251,191,36,.3)}
.st-review{background:rgba(56,189,248,.14);color:#7DD3FC;border-color:rgba(56,189,248,.3)}
.st-paid{background:rgba(99,102,241,.16);color:#A5B4FC;border-color:rgba(99,102,241,.35)}
.st-proc{background:rgba(168,85,247,.16);color:#D8B4FE;border-color:rgba(168,85,247,.35)}
.st-done{background:rgba(34,197,94,.15);color:#86EFAC;border-color:rgba(34,197,94,.35)}
.st-off{background:rgba(148,163,184,.14);color:#CBD5E1;border-color:rgba(148,163,184,.3)}
.st-bad{background:rgba(244,63,94,.14);color:#FDA4AF;border-color:rgba(244,63,94,.35)}
:root[data-theme=light] .st-wait{color:#A16207}:root[data-theme=light] .st-review{color:#0369A1}:root[data-theme=light] .st-paid{color:#4338CA}
:root[data-theme=light] .st-proc{color:#7E22CE}:root[data-theme=light] .st-done{color:#15803D}:root[data-theme=light] .st-off{color:#475569}:root[data-theme=light] .st-bad{color:#BE123C}
.ord-lay{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:24px;align-items:start}
.ord-main{display:grid;gap:18px}
.timeline{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));padding:22px;border-radius:var(--r2);position:relative}
.tl{display:flex;flex-direction:column;align-items:center;gap:8px;text-align:center;font-size:13px;font-weight:800;color:var(--dim);position:relative}
.tl::before{content:"";position:absolute;top:19px;inset-inline-start:50%;width:100%;height:2px;background:var(--line2);z-index:-1}
.tl:last-child::before{display:none}
.tl i{width:40px;height:40px;border-radius:14px;display:grid;place-items:center;font-style:normal;background:var(--glass2);border:1px solid var(--line2)}
.tl i .ic{width:20px;height:20px}
.tl.done{color:var(--ink)}
.tl.done i{background:var(--grn-g);color:#fff;border-color:transparent;box-shadow:0 10px 22px -10px rgba(34,197,94,.9)}
.tl.done::before{background:linear-gradient(90deg,var(--green),var(--line2))}
.deliv,.pay-box{padding:26px;border-radius:var(--r2)}
.deliv h3,.pay-box h3{display:flex;align-items:center;gap:9px;font-size:18px;font-weight:900;margin-bottom:8px}
.deliv h3 .ic,.pay-box h3 .ic{color:var(--sky)}
.deliv p{color:var(--ink2);line-height:2}
.num-box{text-align:center;display:flex;flex-direction:column;align-items:center;gap:10px}
.num-phone{font-size:34px;font-weight:900;letter-spacing:1px}
.code-box{margin-top:6px;padding:18px 26px;border-radius:20px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);display:flex;flex-direction:column;align-items:center;gap:6px}
.big-code{font-size:46px;font-weight:900;letter-spacing:10px;font-variant-numeric:tabular-nums}
.wait{display:flex;align-items:center;justify-content:center;gap:10px;color:var(--dim);font-size:13.5px;font-weight:700;margin:8px 0}
.pay-acts{display:grid;gap:10px;margin:14px 0}
.card-pay{margin-top:18px;padding-top:18px;border-top:1px dashed var(--line2)}
.card-pay h4{display:flex;align-items:center;gap:8px;font-size:15.5px;font-weight:900;margin-bottom:12px}
.bank-card{position:relative;padding:22px;border-radius:22px;color:#fff;overflow:hidden;background:linear-gradient(135deg,#1E3A8A,#6D28D9 60%,#DB2777);box-shadow:0 24px 50px -24px rgba(109,40,217,.9),inset 0 1px 0 rgba(255,255,255,.35)}
.bank-card::after{content:"";position:absolute;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.12);top:-120px;inset-inline-end:-80px}
.bank-card .muted{color:rgba(255,255,255,.75)}
.cc{font-size:24px;font-weight:900;letter-spacing:3px;margin:16px 0}
.bank-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;font-weight:700;position:relative;z-index:1}
.bank-foot .b-glass{background:rgba(255,255,255,.18);color:#fff;border-color:rgba(255,255,255,.35)}
.rcpt{display:grid;gap:10px;margin-top:12px}
.file{display:flex;align-items:center;justify-content:center;gap:10px;height:62px;border-radius:16px;border-style:dashed!important;cursor:pointer;font-weight:800;color:var(--ink2)}
.file input{position:absolute;opacity:0;width:1px;height:1px}
.file .ic{width:22px;height:22px;color:var(--gold)}
.cancel-f{text-align:center;margin-top:12px}
.copy{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 14px;border-radius:14px;background:var(--glass2);border:1px solid var(--line2);font-weight:700}

.acc-lay{display:grid;grid-template-columns:380px minmax(0,1fr);gap:24px;align-items:start}
.acc-side,.acc-main{display:grid;gap:18px}
.bal-card{padding:26px;border-radius:var(--r2);overflow:hidden;display:flex;flex-direction:column;gap:6px}
.bal-card>b{font-size:30px;font-weight:900;margin-bottom:10px}
.topup{margin-top:6px}
.panel{padding:24px;border-radius:var(--r2)}
.panel h3{display:flex;align-items:center;gap:9px;font-size:17px;font-weight:900;margin-bottom:14px}
.panel h3 .ic{color:var(--sky)}
.panel-h{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;flex-wrap:wrap}
.panel-h h3{margin:0}
.pass-box summary{padding:16px 20px;cursor:pointer;font-weight:800;display:flex;align-items:center;gap:9px;list-style:none}
.pass-box summary::-webkit-details-marker{display:none}
.pass-box form{padding:0 20px 20px}
.tbl-wrap{overflow-x:auto;margin:0 -6px}
.tbl{width:100%;border-collapse:collapse;font-size:14px}
.tbl th{text-align:start;font-size:12.5px;color:var(--dim);font-weight:800;padding:10px 12px;border-bottom:1px solid var(--line2);white-space:nowrap}
.tbl td{padding:12px;border-bottom:1px solid var(--line);vertical-align:middle}
.tbl td small{display:block}
.tbl tr:last-child td{border-bottom:0}
.tbl tr.off td{opacity:.5}
.tbl tr.click{cursor:pointer}
.tbl tr.click:hover td{background:var(--glass)}
.pos{color:#4ADE80;font-weight:800}
.neg{color:#FB7185;font-weight:800}
:root[data-theme=light] .pos{color:#15803D}:root[data-theme=light] .neg{color:#BE123C}
.empty-in{padding:20px 0}
.prose p{line-height:2.1;color:var(--ink2)}
.contact-list{display:grid;gap:12px;margin-top:18px}
.nf{padding:48px 30px;display:flex;flex-direction:column;align-items:center;gap:12px}
.nf-code{font-size:90px;font-weight:900;line-height:1.1}

.adm .adm-lay{display:grid;grid-template-columns:260px minmax(0,1fr);gap:20px;padding:16px;min-height:100vh}
.side{position:sticky;top:16px;height:calc(100vh - 32px);padding:18px 14px;border-radius:24px;display:flex;flex-direction:column;gap:18px;overflow:auto}
.side nav{display:grid;gap:4px}
.side nav a,.side-btn,.side-foot a{display:flex;align-items:center;gap:11px;padding:11px 13px;border-radius:13px;font-weight:700;font-size:14.5px;color:var(--ink2);background:none;border:0;cursor:pointer;width:100%;text-align:start}
.side nav a:hover,.side-btn:hover,.side-foot a:hover{background:var(--glass2);color:var(--ink)}
.side nav a.on{background:var(--grad);color:#fff;box-shadow:0 10px 24px -12px rgba(99,102,241,.9)}
.side .ic{width:19px;height:19px}
.side .cnt{margin-inline-start:auto;background:var(--red);color:#fff;font-size:11.5px;font-weight:900;padding:1px 8px;border-radius:99px}
.side-foot{margin-top:auto;display:grid;gap:2px;border-top:1px solid var(--line);padding-top:12px}
.adm-main{min-width:0;display:grid;gap:18px;align-content:start;padding-bottom:40px}
.flashes,.pms,.pay-acts,.rcpt,.ord-main,.acc-side,.acc-main,.adm-main,.ord-admin,.set-form,.contact-list{grid-template-columns:minmax(0,1fr)}
.adm-top{display:flex;align-items:center;gap:12px;padding:6px 4px}
.adm-top h1{font-size:24px;font-weight:900}
.stat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.stat{display:flex;align-items:center;gap:14px;padding:18px}
.stat span{display:block;font-size:12.5px;color:var(--dim);font-weight:700}
.stat b{display:block;font-size:19px;font-weight:900}
.stat small{font-size:11.5px;color:var(--dim)}
.attn-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.attn{padding:18px;display:flex;flex-direction:column;gap:2px}
.attn b{font-size:30px;font-weight:900}
.attn span{font-size:13px;color:var(--dim);font-weight:700}
.attn.hot{border-color:rgba(251,191,36,.5)}
.attn.hot b{color:var(--gold)}
.attn.bad{border-color:rgba(244,63,94,.5)}
.attn.bad b{color:#FB7185}
.bars{display:flex;align-items:flex-end;gap:8px;height:180px;padding-top:10px}
.bar{flex:1;height:100%;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:6px}
.bar i{width:100%;max-width:36px;border-radius:9px 9px 4px 4px;background:var(--grad);box-shadow:0 10px 20px -10px rgba(99,102,241,.9)}
.bar span{font-size:11px;color:var(--dim)}
.toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px;border-radius:20px;flex-wrap:wrap}
.tabs{display:flex;gap:6px;flex-wrap:wrap}
.tab{padding:7px 13px;border-radius:11px;font-size:13px;font-weight:800;color:var(--ink2);border:1px solid transparent}
.tab:hover{background:var(--glass2)}
.tab.on{background:var(--ink);color:var(--bg0)}
.srch{display:flex;gap:8px}
.srch .inp{height:44px;width:260px}
.srch .btn{height:44px;padding:0 14px}
.pager{display:flex;gap:6px;justify-content:center;margin-top:16px;flex-wrap:wrap}
.pager a{min-width:38px;height:38px;border-radius:11px;display:grid;place-items:center;border:1px solid var(--line2);font-weight:800;font-size:13px}
.pager a.on{background:var(--grad);color:#fff;border-color:transparent}
.form-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:4px 16px;align-items:start}
.form-grid.inner{padding:0;border:0;background:none;box-shadow:none}
.span2{grid-column:1/-1}
.form-foot{display:flex;gap:10px;margin-top:8px}
.chk{display:flex!important;align-items:center;gap:10px;cursor:pointer;margin-top:30px}
.chk input{width:20px;height:20px;accent-color:var(--indigo)}
.chk span{font-weight:700;font-size:14px}
.set-form{display:grid;gap:18px}
.sticky-save{position:sticky;bottom:14px;display:flex;justify-content:flex-end}
.emo-cell{font-size:24px;width:40px}
.row-acts{display:flex;gap:6px;align-items:center}
.pill-t{padding:4px 12px;border-radius:99px;border:1px solid var(--line2);background:var(--glass);font-size:12px;font-weight:800;cursor:pointer;color:var(--dim)}
.pill-t.on{background:rgba(34,197,94,.16);color:#4ADE80;border-color:rgba(34,197,94,.4)}
.ord-admin{display:grid;gap:18px}
.acts{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}
.acts.refund{padding-top:16px;border-top:1px dashed var(--line2)}
.inline{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.inline .inp{height:48px;flex:1;min-width:200px}
.note-box{margin-top:14px;padding:14px;border-radius:14px;background:var(--glass);border:1px solid var(--line)}
.rcpt-img{max-width:100%;max-height:520px;border-radius:16px;border:1px solid var(--line2)}
.grid2{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px}
.grid2 .panel form+form{margin-top:12px}
.login-wrap{min-height:100vh;display:grid;place-items:center;padding:20px}
.login-wrap .form-card{width:100%;max-width:420px}

.js .rv{opacity:0;transform:translateY(26px) scale(.98);transition:opacity .7s ease,transform .7s cubic-bezier(.2,.8,.2,1)}
.js .rv.in{opacity:1;transform:none}
@media (prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}.js .rv{opacity:1;transform:none}}

@media (max-width:1180px){
  .nav{display:none}
  .menu-btn{display:inline-grid}
  .hdr-act{margin-inline-start:auto}
  .drawer{position:fixed;top:92px;left:20px;right:20px;z-index:49;border-radius:22px;padding:12px;display:none;flex-direction:column;gap:4px}
  .drawer.open{display:flex}
  .drawer a{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:13px;font-weight:800}
  .drawer a:hover{background:var(--glass2)}
  .drawer .ic{width:18px;height:18px;color:var(--sky)}
  .gift-grid{grid-template-columns:repeat(4,minmax(0,1fr))}
  .svc-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
  .ctry-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
  .stat-grid,.attn-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media (max-width:980px){
  .hero-in,.buy-lay,.ord-lay,.acc-lay,.stars-lay{grid-template-columns:minmax(0,1fr)}
  .bento{grid-template-columns:minmax(0,1.2fr) minmax(0,1fr) minmax(0,1fr)}
  .calc,.sum{position:relative;top:0}
  .cat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
  .plans{grid-template-columns:minmax(0,1fr);max-width:460px;margin:0 auto}
  .feats{grid-template-columns:repeat(2,minmax(0,1fr))}
  .steps{grid-template-columns:repeat(2,minmax(0,1fr));row-gap:34px}
  .steps::before{display:none}
  .faq,.grid2{grid-template-columns:minmax(0,1fr)}
  .cta{grid-template-columns:minmax(0,1fr);padding:44px 30px}
  .ftr-grid{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}
  .info-row{grid-template-columns:minmax(0,1fr)}
  .adm .adm-lay{grid-template-columns:minmax(0,1fr);padding:12px}
  .side{position:fixed;inset:12px auto 12px 12px;width:270px;height:auto;z-index:80;transform:translateX(-120%);transition:transform .3s}
  [dir=rtl] .side{inset:12px 12px 12px auto;transform:translateX(120%)}
  .side.open{transform:none!important}
  .adm-top .menu-btn{display:inline-grid}
  .kv.two-col{grid-template-columns:minmax(0,1fr)}
}
@media (max-width:640px){
  body{font-size:15px}
  .wrap{padding:0 16px}
  .hdr{padding:0 12px;top:8px;margin-top:8px}
  .hdr-in{height:62px;gap:10px;border-radius:19px}
  .drawer{top:78px;left:12px;right:12px}
  .hide-sm,.acct .t{display:none}
  .show-sm{display:inline-grid}
  .acct{padding:0 7px}
  .logo small{display:none}
  .hero{padding:36px 0 20px}
  .hero-cta .btn{flex:1}
  .bento{gap:10px;grid-template-columns:minmax(0,1.12fr) minmax(0,1fr)}
  .bt{padding:14px;border-radius:20px}
  .bt-star{padding:18px}
  .bt-star .bt-t{font-size:23px}
  .big-ic{width:54px;height:54px;border-radius:17px;margin-bottom:12px}
  .bt-row{flex-direction:column;align-items:flex-start;gap:8px}
  .bt-t{font-size:13.5px}
  .bt-p{flex-direction:column;gap:0}
  .sec{padding:70px 0}
  .cat-grid,.svc-grid,.ctry-grid,.stat-grid{grid-template-columns:minmax(0,1fr)}
  .pk-grid,.gift-grid,.attn-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
  .ig-band{padding:26px 16px;border-radius:26px}
  .steps{grid-template-columns:minmax(0,1fr)}
  .ftr{margin:72px 12px 12px;border-radius:26px}
  .ftr-grid{grid-template-columns:minmax(0,1fr)}
  .faq details p{padding:0 20px 20px}
  .fab{bottom:14px;inset-inline-start:14px}
  .fab a,.fab button{width:48px;height:48px;border-radius:16px}
  .two,.form-grid{grid-template-columns:minmax(0,1fr)}
  .timeline{padding:16px 8px}
  .tl{font-size:11.5px}
  .form-card,.sum,.deliv,.pay-box,.panel{padding:20px 16px}
  .srch .inp{width:100%}
  .srch{flex:1}
  .chk{margin-top:4px}
  .big-code{font-size:36px;letter-spacing:6px}
  .num-phone{font-size:26px}
}
.lite .blur{-webkit-backdrop-filter:none;backdrop-filter:none}
.lite .g.blur{background:linear-gradient(150deg,rgba(255,255,255,.1),rgba(255,255,255,.04)),rgba(12,14,38,.6)}
.lite .hdr-in,.lite .drawer,.lite .side,.lite .top{background:var(--sheet)}
:root.lite[data-theme=light] .g.blur{background:linear-gradient(150deg,rgba(255,255,255,.9),rgba(255,255,255,.74))}
:root.lite[data-theme=light] .hdr-in,:root.lite[data-theme=light] .drawer,:root.lite[data-theme=light] .side,:root.lite[data-theme=light] .top{background:var(--sheet)}
.lite .aur{mix-blend-mode:normal;animation:none;will-change:auto}
.lite .a4,.lite .a5,.lite .bg-noise,.lite .bg-spot,.lite .g::before{display:none}
.lite .bt-star,.lite .bt-f1,.lite .bt-f2,.lite .ring,.lite .shine,.lite .pill .dot::after{animation:none}
.lite .halo{filter:none;-webkit-mask-image:radial-gradient(closest-side,#000,transparent);mask-image:radial-gradient(closest-side,#000,transparent)}
.lite .rot{transition:opacity .3s,transform .3s}
.lite .rot.out,.lite .rot.pre{filter:none}
.lite .ticker{-webkit-mask-image:none;mask-image:none}
.lite .lift:hover,.lite .bt:hover{transform:none}
.lite main>.sec{content-visibility:auto;contain-intrinsic-size:auto 900px}
NSCSS;
}

function ns_asset_js() {
    return <<<'NSJS'
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var root = document.documentElement;
  var REDUCE = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var LITE = root.classList.contains('lite');
  function fa(n) { n = Number(n) || 0; try { return n.toLocaleString('fa-IR'); } catch (e) { return String(Math.round(n)); } }
  function num(v) { return Number(String(v == null ? '' : v).replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[^\d.]/g, '')) || 0; }

  var toastEl = null, toastT = 0;
  function toast(msg) {
    if (!toastEl) { toastEl = document.createElement('div'); toastEl.className = 'alert a-ok g toast-pop'; document.body.appendChild(toastEl);
      toastEl.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:200;margin:0;transition:opacity .3s;opacity:0;pointer-events:none'; }
    toastEl.textContent = msg; toastEl.style.opacity = '1';
    clearTimeout(toastT); toastT = setTimeout(function () { toastEl.style.opacity = '0'; }, 2400);
  }

  var sky = (function () {
    var c = $('#sky'), ctx = c && c.getContext ? c.getContext('2d') : null, api = { theme: function () {}, mouse: function () {} };
    if (!ctx) return api;
    var dpr = LITE ? 1 : Math.min(1.5, window.devicePixelRatio || 1), W = 0, H = 0, stars = [], shoots = [], mx = 0, my = 0, tx = 0, ty = 0, rgb = '255,255,255', raf = 0, next = 0, last = 0, scrolling = 0, gap = LITE ? 40 : 15;
    var cols = ['255,255,255', '255,214,120', '147,197,253', '216,180,254'];
    function readTheme() { rgb = getComputedStyle(root).getPropertyValue('--star').trim() || '255,255,255'; }
    function size() {
      W = window.innerWidth; H = window.innerHeight;
      c.width = W * dpr; c.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      var n = Math.round(LITE ? Math.min(70, W * H / 9000) : Math.min(170, W * H / 7500)); stars = [];
      for (var i = 0; i < n; i++) stars.push({ x: Math.random() * W, y: Math.random() * H, r: Math.random() * 1.25 + .25, z: Math.random() * .9 + .1, p: Math.random() * 6.28, s: Math.random() * 1.6 + .4, c: cols[(Math.random() * cols.length) | 0] });
    }
    function draw(t) {
      tx += (mx - tx) * .04; ty += (my - ty) * .04;
      ctx.clearRect(0, 0, W, H);
      var light = rgb !== '255,255,255';
      for (var i = 0; i < stars.length; i++) {
        var s = stars[i], a = .25 + .75 * (.5 + .5 * Math.sin(s.p + t * .001 * s.s));
        var x = s.x + tx * s.z * 14, y = s.y + ty * s.z * 10;
        ctx.globalAlpha = a * (light ? .35 : 1);
        ctx.fillStyle = 'rgb(' + (light ? rgb : s.c) + ')';
        ctx.beginPath(); ctx.arc(x, y, s.r, 0, 6.2832); ctx.fill();
        if (!LITE && s.r > 1.2 && !light) { ctx.globalAlpha = a * .18; ctx.beginPath(); ctx.arc(x, y, s.r * 4, 0, 6.2832); ctx.fill(); }
      }
      if (!light && !REDUCE && t > next) {
        next = t + 2600 + Math.random() * 4200;
        shoots.push({ x: Math.random() * W * .8 + W * .2, y: Math.random() * H * .4, vx: -(6 + Math.random() * 4), vy: 2.2 + Math.random() * 1.5, l: 1 });
      }
      for (var j = shoots.length - 1; j >= 0; j--) {
        var m = shoots[j]; m.x += m.vx; m.y += m.vy; m.l -= .012;
        if (m.l <= 0) { shoots.splice(j, 1); continue; }
        var gr = ctx.createLinearGradient(m.x, m.y, m.x - m.vx * 16, m.y - m.vy * 16);
        gr.addColorStop(0, 'rgba(255,255,255,' + m.l + ')'); gr.addColorStop(1, 'rgba(255,255,255,0)');
        ctx.globalAlpha = 1; ctx.strokeStyle = gr; ctx.lineWidth = 1.6;
        ctx.beginPath(); ctx.moveTo(m.x, m.y); ctx.lineTo(m.x - m.vx * 16, m.y - m.vy * 16); ctx.stroke();
      }
      ctx.globalAlpha = 1;
    }
    function loop(t) { raf = requestAnimationFrame(loop); if (scrolling || t - last < gap) return; last = t; draw(t); }
    readTheme(); size();
    window.addEventListener('scroll', function () { clearTimeout(scrolling); scrolling = setTimeout(function () { scrolling = 0; }, 160); }, { passive: true });
    var rt = 0;
    window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(function () { size(); if (REDUCE) draw(0); }, 150); });
    if (REDUCE) draw(0);
    else {
      raf = requestAnimationFrame(loop);
      document.addEventListener('visibilitychange', function () { cancelAnimationFrame(raf); if (!document.hidden) raf = requestAnimationFrame(loop); });
    }
    api.theme = function () { readTheme(); if (REDUCE) draw(0); };
    api.mouse = function (x, y) { mx = (x / W - .5) * 2; my = (y / H - .5) * 2; };
    return api;
  })();

  var th = $('#theme');
  if (th) th.addEventListener('click', function () {
    var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('ns-theme', next); } catch (e) {}
    sky.theme();
  });

  function toggler(btnSel, boxSel) {
    var b = $(btnSel), box = $(boxSel);
    if (!b || !box) return;
    b.addEventListener('click', function (e) { e.stopPropagation(); var o = box.classList.toggle('open'); b.setAttribute('aria-expanded', o ? 'true' : 'false'); });
    document.addEventListener('click', function (e) { if (box.classList.contains('open') && !box.contains(e.target)) box.classList.remove('open'); });
  }
  toggler('#menuBtn', '#drawer');
  toggler('#sideBtn', '#side');

  var topBtn = $('#toTop');
  if (topBtn) {
    window.addEventListener('scroll', function () { topBtn.classList.toggle('show', (window.scrollY || 0) > 700); }, { passive: true });
    topBtn.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
  }

  var rv = $$('.rv');
  if ('IntersectionObserver' in window && !REDUCE) {
    var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }); }, { rootMargin: '0px 0px -40px 0px', threshold: .06 });
    rv.forEach(function (el) { io.observe(el); });
  } else rv.forEach(function (el) { el.classList.add('in'); });

  var spot = $('#spot'), pend = 0;
  if (window.matchMedia && window.matchMedia('(hover: hover)').matches && !REDUCE) {
    document.addEventListener('pointermove', function (e) {
      if (pend) return;
      pend = requestAnimationFrame(function () {
        pend = 0;
        if (spot) { spot.style.setProperty('--cx', e.clientX + 'px'); spot.style.setProperty('--cy', e.clientY + 'px'); }
        var g = e.target && e.target.closest ? e.target.closest('.g') : null;
        if (g) { var r = g.getBoundingClientRect(); g.style.setProperty('--mx', (e.clientX - r.left) + 'px'); g.style.setProperty('--my', (e.clientY - r.top) + 'px'); }
        sky.mouse(e.clientX, e.clientY);
      });
    }, { passive: true });
  }

  $$('.rot[data-words]').forEach(function (el) {
    if (REDUCE) return;
    var words = []; try { words = JSON.parse(el.getAttribute('data-words')) || []; } catch (e) {}
    if (words.length < 2) return;
    var i = 0;
    setInterval(function () {
      el.classList.add('out');
      setTimeout(function () { i = (i + 1) % words.length; el.textContent = words[i]; el.classList.remove('out'); el.classList.add('pre'); void el.offsetWidth; el.classList.remove('pre'); }, 350);
    }, 2600);
  });

  function qtyForm(f, onChange) {
    var inp = $('input[name=qty]', f); if (!inp) return;
    var min = Number(inp.min) || 1, max = Number(inp.max) || 1e9, rng = $('[data-range]', f);
    function set(v, fromInput) {
      v = Math.max(min, Math.min(max, Math.round(v) || min));
      if (!fromInput) inp.value = v;
      if (rng) rng.value = Math.min(Number(rng.max), v);
      $$('[data-q]', f).forEach(function (b) { b.classList.toggle('on', Number(b.getAttribute('data-q')) === v); });
      onChange(v);
    }
    inp.addEventListener('input', function () { set(num(inp.value), true); });
    inp.addEventListener('blur', function () { set(num(inp.value)); });
    if (rng) rng.addEventListener('input', function () { set(Number(rng.value)); });
    $$('[data-step]', f).forEach(function (b) { b.addEventListener('click', function () { set(num(inp.value) + Number(b.getAttribute('data-step'))); }); });
    $$('[data-q]', f).forEach(function (b) { b.addEventListener('click', function () { set(Number(b.getAttribute('data-q'))); }); });
    set(num(inp.value), true);
  }
  $$('[data-calc],[data-buy]').forEach(function (f) {
    var price = Number(f.getAttribute('data-price')) || 0, per = Number(f.getAttribute('data-per')) || 1, out = $('[data-total]', f);
    if (f.hasAttribute('data-buy') && f.getAttribute('data-unit') !== '1') return;
    qtyForm(f, function (q) { if (out) out.textContent = fa(Math.ceil(price * q / per)); });
  });

  document.addEventListener('click', function (e) {
    var c = e.target.closest('[data-copy]');
    if (c) {
      var t = c.getAttribute('data-copy');
      var ok = function () { toast('کپی شد'); };
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(t).then(ok, function () {});
      else { var ta = document.createElement('textarea'); ta.value = t; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); ok(); } catch (x) {} ta.remove(); }
      return;
    }
    var s = e.target.closest('[data-set]');
    if (s) { var f = s.closest('form'), i = f && f.querySelector('[name="' + s.getAttribute('data-set') + '"]'); if (i) i.value = s.getAttribute('data-v'); return; }
    var tr = e.target.closest('tr[data-href]');
    if (tr && !e.target.closest('a,button,input,form')) location.href = tr.getAttribute('data-href');
  });

  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault(); });
  });
  $$('form').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (e.defaultPrevented) return;
      var b = f.querySelector('button[type=submit]:not([name])');
      if (b && !f.hasAttribute('data-noblock')) setTimeout(function () { b.disabled = true; b.setAttribute('data-busy', '1'); }, 0);
    });
  });
  window.addEventListener('pageshow', function () { $$('[data-busy]').forEach(function (b) { b.disabled = false; b.removeAttribute('data-busy'); }); });

  $$('.file input[type=file]').forEach(function (inp) {
    inp.addEventListener('change', function () { var l = inp.parentNode.querySelector('[data-file]'); if (l && inp.files[0]) l.textContent = inp.files[0].name; });
  });

  $$('[data-filter]').forEach(function (inp) {
    var list = $(inp.getAttribute('data-filter')), none = $('#noResult');
    if (!list) return;
    inp.addEventListener('input', function () {
      var q = inp.value.trim().toLowerCase(), n = 0;
      Array.prototype.forEach.call(list.children, function (it) { var ok = !q || (it.getAttribute('data-name') || it.textContent).toLowerCase().indexOf(q) !== -1; it.style.display = ok ? '' : 'none'; if (ok) n++; });
      if (none) none.hidden = n > 0;
    });
  });

  var pf = $('[data-pform]');
  if (pf) {
    var sel = $('[data-pricing]', pf), box = $('.unit-only', pf);
    var sync = function () { if (box) box.style.display = sel.value === 'unit' ? '' : 'none'; };
    sel.addEventListener('change', sync); sync();
  }

  var poll = $('[data-poll]');
  if (poll) {
    var state = poll.getAttribute('data-state') || '', url = poll.getAttribute('data-poll'), tries = 0, hadCode = !!$('.code-box');
    var tick = function () {
      if (document.hidden) { setTimeout(tick, 4000); return; }
      fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (j) {
        if (!j || !j.ok) return;
        if (state.split('|')[0] !== j.status || (j.code && !hadCode)) { location.reload(); return; }
        if (++tries < 400) setTimeout(tick, j.status === 'processing' && j.phone ? 5000 : 8000);
      }).catch(function () { if (++tries < 400) setTimeout(tick, 10000); });
    };
    setTimeout(tick, 5000);
  }
})();
NSJS;
}

function ns_asset_icon() {
    return <<<'NSSVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#38BDF8"/><stop offset=".55" stop-color="#6366F1"/><stop offset="1" stop-color="#A855F7"/></linearGradient></defs><rect width="64" height="64" rx="18" fill="url(#g)"/><path d="M32 13l5.6 11.4 12.6 1.8-9.1 8.9 2.1 12.5L32 41.7l-11.2 5.9 2.1-12.5-9.1-8.9 12.6-1.8z" fill="#fff"/></svg>
NSSVG;
}

function ns_asset_font() {
    return base64_decode('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');
}

if (PHP_SAPI === 'cli' || isset($_GET['cron'])) ns_cron();
elseif (isset($_GET['a']) || isset($_GET['admin'])) ns_admin();
else ns_site();
