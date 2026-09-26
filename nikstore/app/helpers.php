<?php
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
    if ($page === 'home' && count($q) === 1) return './';
    return 'index.php?' . http_build_query($q);
}

function aurl($a = 'dashboard', array $q = []) {
    return 'admin.php?' . http_build_query(['a' => $a] + $q);
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
    require_once NS_APP . '/views/_ui.php';
    extract($vars, EXTR_SKIP);
    ob_start();
    require NS_APP . '/views/' . $name . '.php';
    $content = ob_get_clean();
    if ($layout) require NS_APP . '/views/' . $layout . '.php';
    else echo $content;
}

function ic($name, $cls = '') {
    return '<svg class="ic' . ($cls !== '' ? ' ' . e($cls) : '') . '" aria-hidden="true"><use href="#i-' . e($name) . '"/></svg>';
}

function theme_boot_js() {
    return "(function(){var t='dark';try{t=localStorage.getItem('ns-theme')||'dark'}catch(e){}document.documentElement.setAttribute('data-theme',t==='light'?'light':'dark');document.documentElement.classList.add('js')})();";
}

function security_headers() {
    $h = base64_encode(hash('sha256', theme_boot_js(), true));
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), usb=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'sha256-{$h}'; " .
           "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; " .
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
