<?php

if (is_file(__DIR__ . '/config.local.php')) require_once __DIR__ . '/config.local.php';
if (!defined('ADMIN_PASSWORD'))
    define('ADMIN_PASSWORD', defined('ADMIN_PANEL_PASS')
        ? (string)ADMIN_PANEL_PASS
        : (string)getenv('ADMIN_PANEL_PASS'));

if (strlen(ADMIN_PASSWORD) < 6) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("رمز پنل تنظیم نشده است.\n\n" .
         "کنار همین فایل در config.local.php بنویسید:\n\n" .
         "define('ADMIN_PANEL_PASS', 'رمز شما');\n");
}

define('MEMBERSHIP_LIB_ONLY', true);
require_once __DIR__ . '/bot_master_membership.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_name('mybot_panel');
session_start();

define('PANEL_MAX_TRIES', 6);
define('PANEL_MAX_TRIES_ALL', 30);
define('PANEL_LOCK_SECONDS', 900);
define('PANEL_IDLE_SECONDS', 7200);

function panelIp() {
    return substr(hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '0')), 0, 24);
}

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
    mutate('panel_lock', function (&$a) use ($k) { unset($a[$k], $a['_all']); });
}

function panelPassIn($s) {
    $s = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}\x{00A0}]/u', '', (string)$s);
    return trim(norm_fa_digits((string)$s));
}

function panelFontCss() {
    $dir  = __DIR__ . '/fonts';

    if (function_exists('faVarFontCss')) {
        $var = faVarFontCss($dir);
        if ($var !== '') return $var;
    }

    $out  = '';
    $faces = [
        400 => ['Vazirmatn-Regular', 'Vazirmatn', 'Vazir'],
        600 => ['Vazirmatn-SemiBold', 'Vazirmatn-Medium'],
        700 => ['Vazirmatn-Bold', 'Vazir-Bold'],
        800 => ['Vazirmatn-ExtraBold', 'Vazirmatn-Black'],
    ];
    foreach ($faces as $w => $names) {
        foreach ($names as $n) {
            foreach (['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype'] as $ext => $fmt) {
                $f = $dir . '/' . $n . '.' . $ext;
                if (!is_file($f)) continue;
                $out .= "@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:{$w};"
                      . "font-display:swap;src:url('fonts/" . rawurlencode($n . '.' . $ext) . "') format('{$fmt}')}\n";
                continue 3;
            }
        }
    }
    return $out;
}

function renderLogin($error) { ?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" media="print" onload="this.media='all'"
      href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;800&display=swap">
<link rel="icon" href="data:,">
<title>ورود — پنل مدیریت</title><style>
<?= panelFontCss() ?>
*{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;display:grid;place-items:center;padding:20px;background:#070a11;color:#eef2f8;
font-family:'Vazirmatn','Vazir',Tahoma,system-ui,'Segoe UI',sans-serif;-webkit-font-smoothing:antialiased}
body::before{content:'';position:fixed;inset:0;z-index:-1;pointer-events:none;
background:
  radial-gradient(760px 560px at 85% -6%, rgba(59,130,246,.24), transparent 62%),
  radial-gradient(680px 520px at 8% 8%,   rgba(139,92,246,.20), transparent 60%),
  radial-gradient(820px 640px at 50% 108%,rgba(16,185,129,.13), transparent 62%),
  linear-gradient(180deg,#070a11,#05070c)}
.card{width:100%;max-width:380px;text-align:center;padding:40px 32px;border-radius:20px;
background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);
backdrop-filter:blur(24px) saturate(170%);-webkit-backdrop-filter:blur(24px) saturate(170%);
box-shadow:0 24px 70px -20px rgba(0,0,0,.8), inset 0 1px 0 rgba(255,255,255,.12)}
h1{font-size:22px;margin-bottom:6px;font-weight:800}
p.sub{color:#9aa7bd;font-size:13px;margin-bottom:24px}
input{width:100%;padding:14px 16px;border:1px solid rgba(255,255,255,.14);border-radius:12px;font-size:15px;
font-family:inherit;margin-bottom:14px;text-align:center;color:#eef2f8;background:rgba(255,255,255,.06);
backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}
input::placeholder{color:#7c8aa3}
input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.18)}
button{width:100%;padding:14px;border:1px solid rgba(59,130,246,.9);border-radius:12px;
background:linear-gradient(180deg,#3b82f6,#1d4ed8);color:#fff;font-size:16px;font-weight:800;
cursor:pointer;font-family:inherit;box-shadow:inset 0 1px 0 rgba(255,255,255,.18)}
button:hover{filter:brightness(1.1)}
button.eye{width:auto;padding:0 4px 14px;border:0;background:none;box-shadow:none;color:#9aa7bd;font-size:13px;font-weight:600}
.err{background:rgba(239,68,68,.16);color:#ffb4bb;border:1px solid rgba(239,68,68,.4);padding:10px;
border-radius:12px;font-size:13px;margin-bottom:14px;
backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}
@supports not ((backdrop-filter:blur(1px)) or (-webkit-backdrop-filter:blur(1px))){
  .card{background:#141a27} input{background:#1b2333}
}
</style></head><body>
<form class="card" method="post">
  <div style="font-size:44px">👑</div><h1>پنل مدیریت</h1><p class="sub">نامبیکس | Numbix</p>
  <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
  <input id="pw" type="password" name="password" placeholder="رمز عبور" dir="ltr" autocomplete="current-password"
         autocapitalize="off" autocorrect="off" spellcheck="false" autofocus required>
  <button type="button" class="eye" onclick="var p=document.getElementById('pw');p.type=p.type==='password'?'text':'password';this.textContent=p.type==='password'?'👁 نمایش رمز':'🙈 پنهان کردن رمز'">👁 نمایش رمز</button>
  <button type="submit">ورود</button>
</form></body></html>
<?php }

if (isset($_GET['logout'])) {
    $_SESSION = []; session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
}

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
        if (hash_equals(ADMIN_PASSWORD, panelPassIn($_POST['password']))) {
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

function flashSafeHtml($s) {
    $s = h((string)$s);
    return strtr($s, [
        '&lt;b&gt;' => '<b>', '&lt;/b&gt;' => '</b>',
        '&lt;code&gt;' => '<code>', '&lt;/code&gt;' => '</code>',
        '&lt;br&gt;' => '<br>',
    ]);
}

function go($flash = null, $type = 'ok') {
    if ($flash !== null) $_SESSION['flash'] = ['msg' => $flash, 'type' => $type];
    $q = ['tab' => (string)($_POST['tab'] ?? $_GET['tab'] ?? 'dashboard')];
    parse_str((string)($_POST['ret'] ?? ''), $r);
    foreach (['s', 'st', 'f', 'q', 'page', 'id', 'banned'] as $k)
        if (isset($r[$k]) && is_scalar($r[$k]) && (string)$r[$k] !== '') $q[$k] = (string)$r[$k];
    $open = preg_replace('/[^A-Za-z0-9_]/', '', (string)($r['open'] ?? ''));
    if ($open !== '') $q['open'] = $open;
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($q) . ($open !== '' ? '#c-' . $open : ''));
    exit;
}

function baseUrl() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    return $scheme . '://' . $host . $dir;
}

function pWeakPass() {
    return strlen(ADMIN_PASSWORD) < 12 || !preg_match('/[^A-Za-z0-9]/', ADMIN_PASSWORD);
}

function pCached($key, $maxAge, callable $fn) {
    $f = rtrim(DATA_DIR, '/') . '/.panel_' . preg_replace('/[^a-z0-9_]/', '', $key);
    if ($maxAge > 0 && is_file($f) && time() - (int)@filemtime($f) < $maxAge) {
        $raw = @file_get_contents($f);
        if ($raw !== false && $raw !== '') {
            $v = json_decode($raw, true);
            if ($v !== null) return $v;
        }
    }
    $v = $fn();
    @file_put_contents($f, json_encode($v, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $v;
}

function pUsersDb() {
    static $db = false;
    if ($db === false) $db = function_exists('usersDb') ? usersDb() : null;
    return $db;
}

function pUserCount($where = '', $bind = []) {
    $db = pUsersDb(); if (!$db) return 0;
    $sql = 'SELECT COUNT(*) FROM users' . ($where !== '' ? ' WHERE ' . $where : '');
    $st = $db->prepare($sql);
    if (!$st) return 0;
    foreach ($bind as $k => $v) $st->bindValue($k, $v);
    $r = $st->execute();
    return $r ? (int)$r->fetchArray(SQLITE3_NUM)[0] : 0;
}

function pWalletTotal($maxAge = 60) {
    $f = rtrim(DATA_DIR, '/') . '/.panel_wallet_sum';
    if ($maxAge > 0 && is_file($f) && time() - (int)@filemtime($f) < $maxAge) {
        $v = @file_get_contents($f);
        if ($v !== false && $v !== '') return (float)$v;
    }
    $db = pUsersDb(); if (!$db) return 0.0;
    $v = (float)(@$db->querySingle("SELECT SUM(CAST(json_extract(data,'$.balance') AS REAL)) FROM users") ?: 0);
    @file_put_contents($f, (string)$v, LOCK_EX);
    return $v;
}

function pUsersPage($page, $per, $q = '', $onlyBanned = false) {
    $db = pUsersDb(); if (!$db) return [[], 0];
    $w = []; $b = [];
    $q = trim($q);
    if ($q !== '') {
        if (ctype_digit($q)) { $w[] = 'id = :id'; $b[':id'] = (int)$q; }
        else {
            $w[] = "(lower(json_extract(data,'$.username')) LIKE :q OR lower(json_extract(data,'$.first_name')) LIKE :q)";
            $b[':q'] = '%' . strtolower($q) . '%';
        }
    }
    if ($onlyBanned) $w[] = "json_extract(data,'$.banned') IN (1,'1','true')";
    $where = $w ? implode(' AND ', $w) : '';
    $page  = max(1, (int)$page);
    $off   = ($page - 1) * $per;

    $filtered = ($where !== '');
    $fetch = $filtered ? $per + 1 : $per;

    $sql = 'SELECT id, data FROM users' . ($where !== '' ? ' WHERE ' . $where : '')
         . ' ORDER BY id DESC LIMIT :lim OFFSET :off';
    $st = $db->prepare($sql);
    if (!$st) return [[], 0];
    foreach ($b as $k => $v) $st->bindValue($k, $v);
    $st->bindValue(':lim', (int)$fetch, SQLITE3_INTEGER);
    $st->bindValue(':off', (int)$off, SQLITE3_INTEGER);
    $res = $st->execute();
    $out = [];
    while ($res && $row = $res->fetchArray(SQLITE3_ASSOC)) {
        $d = json_decode($row['data'], true);
        if (is_array($d)) $out[(string)$row['id']] = $d;
    }

    if (!$filtered) return [$out, pUserCount()];

    $more = count($out) > $per;
    if ($more) array_pop($out);
    $total = $off + count($out) + ($more ? 1 : 0);
    return [$out, $total, $more];
}

function pUser($id) {
    $db = pUsersDb(); if (!$db) return null;
    $st = $db->prepare('SELECT data FROM users WHERE id = :i');
    if (!$st) return null;
    $st->bindValue(':i', (int)$id, SQLITE3_INTEGER);
    $r = $st->execute();
    $row = $r ? $r->fetchArray(SQLITE3_ASSOC) : null;
    if (!$row) return null;
    $d = json_decode($row['data'], true);
    return is_array($d) ? $d : null;
}

function pRefCount($id) {
    return pUserCount("CAST(json_extract(data,'$.referrer') AS INTEGER) = :r", [':r' => (int)$id]);
}

function pTopReferrers($limit = 20) {
    $db = pUsersDb(); if (!$db) return [];
    $sql = "SELECT CAST(json_extract(data,'$.referrer') AS INTEGER) AS r, COUNT(*) AS n
            FROM users WHERE r > 0 GROUP BY r ORDER BY n DESC LIMIT " . max(1, (int)$limit);
    $res = @$db->query($sql);
    $out = [];
    while ($res && $row = $res->fetchArray(SQLITE3_ASSOC)) $out[] = ['id' => (int)$row['r'], 'n' => (int)$row['n']];
    return $out;
}

function atU($name) {
    return "\u{2066}@" . ltrim((string)$name, '@') . "\u{2069}";
}

function pPendingCount() {
    static $n = null;
    if ($n === null) $n = Order::countBy(Order::REVIEW);
    return $n;
}

function pLabel($id, $u = null) {
    $u = $u ?: pUser($id);
    if (!$u) return (string)$id;
    if (!empty($u['username']))   return atU($u['username']);
    if (!empty($u['first_name'])) return $u['first_name'];
    return (string)$id;
}

function pOrdersDb() {
    static $db = false;
    if ($db === false) $db = function_exists('ordersDb') ? ordersDb() : null;
    return $db;
}

function pOrdersPage($status, $q, $page, $per) {
    $db = pOrdersDb(); if (!$db) return [[], 0, 1, 1];
    $w = []; $b = [];
    if ($status !== 'all') { $w[] = 'status = :st'; $b[':st'] = (string)$status; }
    $q = trim((string)$q);
    if ($q !== '') {
        $w[] = '(id LIKE :q OR CAST(user_id AS TEXT) LIKE :q)';
        $b[':q'] = '%' . $q . '%';
    }
    $where = $w ? ' WHERE ' . implode(' AND ', $w) : '';

    $cs = $db->prepare('SELECT COUNT(*) FROM orders' . $where);
    foreach ($b as $k => $v) $cs->bindValue($k, $v);
    $cr = $cs->execute();
    $total = $cr ? (int)$cr->fetchArray(SQLITE3_NUM)[0] : 0;

    $pages = max(1, (int)ceil($total / $per));
    $page  = max(1, min($pages, (int)$page));

    $st = $db->prepare('SELECT id, data FROM orders' . $where
        . ' ORDER BY created_at DESC LIMIT :lim OFFSET :off');
    if (!$st) return [[], 0, 1, 1];
    foreach ($b as $k => $v) $st->bindValue($k, $v);
    $st->bindValue(':lim', (int)$per, SQLITE3_INTEGER);
    $st->bindValue(':off', ($page - 1) * $per, SQLITE3_INTEGER);
    $res = $st->execute();
    $out = [];
    while ($res && $row = $res->fetchArray(SQLITE3_ASSOC)) {
        $d = json_decode($row['data'], true);
        if (is_array($d)) $out[$row['id']] = $d;
    }
    return [$out, $total, $pages, $page];
}

function pOrdersOfUser($uid, $limit = 40) {
    $db = pOrdersDb(); if (!$db) return [[], 0, 0.0];
    $st = $db->prepare('SELECT data FROM orders WHERE user_id = :u ORDER BY created_at DESC LIMIT :l');
    if (!$st) return [[], 0, 0.0];
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $st->bindValue(':l', (int)$limit, SQLITE3_INTEGER);
    $res = $st->execute();
    $rows = [];
    while ($res && $row = $res->fetchArray(SQLITE3_ASSOC)) {
        $d = json_decode($row['data'], true);
        if (is_array($d)) $rows[] = $d;
    }
    $cs = $db->prepare('SELECT COUNT(*) FROM orders WHERE user_id = :u');
    $cs->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $cr = $cs->execute();
    $total = $cr ? (int)$cr->fetchArray(SQLITE3_NUM)[0] : 0;

    $s = $db->prepare("SELECT SUM(CAST(json_extract(data,'$.amount') AS REAL)) FROM orders
                       WHERE user_id = :u AND status = 'approved'");
    $s->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $r = $s->execute();
    return [$rows, $total, $r ? (float)($r->fetchArray(SQLITE3_NUM)[0] ?? 0) : 0.0];
}

function pNumSpent($uid) {
    $db = maOrdersDb(); if (!$db) return 0.0;
    $s = $db->prepare("SELECT SUM(CAST(json_extract(data,'$.total') AS REAL)) FROM orders
                       WHERE user_id = :u AND app = 'num' AND status = 'done'");
    $s->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $r = $s->execute();
    return $r ? (float)($r->fetchArray(SQLITE3_NUM)[0] ?? 0) : 0.0;
}

function pNumStats() {
    return pCached('numstats', 60, function () {
        $out = ['done' => 0, 'revenue' => 0.0, 'today' => 0, 'today_rev' => 0.0];
        $db = maOrdersDb(); if (!$db) return $out;
        $res = @$db->query("SELECT COUNT(*) AS n, SUM(CAST(json_extract(data,'\$.total') AS REAL)) AS s,
                                   SUM(CASE WHEN substr(created_at,1,10) = '" . date('Y-m-d') . "' THEN 1 ELSE 0 END) AS tn,
                                   SUM(CASE WHEN substr(created_at,1,10) = '" . date('Y-m-d') . "'
                                            THEN CAST(json_extract(data,'\$.total') AS REAL) ELSE 0 END) AS ts
                            FROM orders WHERE app = 'num' AND status = 'done'");
        $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
        if ($row) $out = ['done' => (int)$row['n'], 'revenue' => (float)$row['s'],
                          'today' => (int)$row['tn'], 'today_rev' => (float)$row['ts']];
        return $out;
    });
}

function pNumByPhone($q, $status, $limit) {
    $d  = preg_replace('/\D/', '', (string)$q);
    $db = strlen($d) >= 6 ? numActsDb() : null;
    if (!$db) return [[], 0];
    $st = $db->prepare("SELECT id FROM num_acts WHERE json_extract(data,'$.phone') LIKE :p ORDER BY created DESC LIMIT :n");
    $st->bindValue(':p', '%' . $d . '%', SQLITE3_TEXT);
    $st->bindValue(':n', (int)$limit, SQLITE3_INTEGER);
    $res = $st->execute();
    $out = [];
    while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
        $o = MaOrder::get((string)$row['id']);
        if ($o && ($status === '' || ($o['status'] ?? '') === $status)) $out[] = $o;
    }
    return [$out, count($out)];
}

function pSparkBuckets($days = 7) {
    return pCached('spark' . (int)$days, 60, function () use ($days) {
    $b = [];
    for ($i = $days - 1; $i >= 0; $i--) $b[date('Y-m-d', strtotime("-$i day"))] = 0;
    $db = maOrdersDb(); if (!$db) return $b;
    $st = $db->prepare("SELECT substr(created_at,1,10) AS d, COUNT(*) AS n
                        FROM orders WHERE app = 'num' AND status = 'done' AND substr(created_at,1,10) >= :from
                        GROUP BY d");
    if (!$st) return $b;
    $st->bindValue(':from', array_key_first($b), SQLITE3_TEXT);
    $res = $st->execute();
    while ($res && $row = $res->fetchArray(SQLITE3_ASSOC))
        if (isset($b[$row['d']])) $b[$row['d']] = (int)$row['n'];
    return $b;
    });
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $a = $_POST['action'] ?? '';

    if (in_array($a, ['adm_write_test', 'adm_leak_test', 'adm_speed_test'], true)) {
        $report = $a === 'adm_write_test' ? admWriteTestText()
                : ($a === 'adm_leak_test' ? admLeakTestText() : admSpeedText());
        go($report, (str_contains($report, '🔴') || str_contains($report, '🚨')) ? 'err' : 'ok');
    }

    if ($a === 'approve_order') {
        [$ok, $res] = Order::approve($_POST['id'] ?? '', ADMIN_ID);
        if (!$ok) go($res, 'err');
        completeApprovedOrder($res);
        go('شارژ تایید شد و به کاربر اطلاع داده شد.');
    }

    if ($a === 'reject_order') {
        [$ok, $res] = Order::reject($_POST['id'] ?? '', ADMIN_ID);
        if (!$ok) go($res, 'err');
        sendMsg(BOT_TOKEN, $res['user_id'], T('rejected'));
        go('شارژ رد شد و به کاربر اطلاع داده شد.');
    }

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

    if ($a === 'save_sup_group') {
        $link = trim((string)($_POST['sg_link'] ?? ''));
        $chat = trim((string)($_POST['sg_chat'] ?? ''));
        $th   = max(0, (int)($_POST['sg_thread'] ?? 0));
        if ($link !== '') {
            [$lc, $lt] = parseChatLink($link);
            if ($lc === null) go('لینکِ تاپیک شناخته نشد. روی یکی از پیام‌های همان تاپیک نگه دارید ← Copy Link.', 'err');
            $chat = (string)$lc;
            $th   = (int)$lt;
        }
        if ($chat !== '' && !preg_match('/^(-\d{5,20}|@[A-Za-z0-9_]{4,32})$/', $chat)) go('آیدیِ گروه نامعتبر است (مثلا -1001234567890).', 'err');
        $on = !empty($_POST['sg_on']);
        maSetRoot(function (&$m) use ($chat, $th, $on) {
            $m['sup']['chat']   = $chat;
            $m['sup']['thread'] = $th;
            $m['sup']['on']     = $on ? 1 : 0;
        });
        go($on && $chat !== '' ? 'تیکت‌ها از این به بعد به همان گروه/تاپیک می‌روند.' : 'تیکت‌ها به پیویِ مدیر می‌روند.');
    }
    if ($a === 'sup_group_test') {
        [$ok, $err] = maSupSend((int)ADMIN_ID, '', 'تستِ پنلِ وب', '', 'این یک پیامِ آزمایشی است. روی همین پیام ریپلای کنید تا پاسخ به شما برگردد.');
        go($ok ? 'پیامِ آزمایشی فرستاده شد.' : $err, $ok ? 'ok' : 'err');
    }
    if ($a === 'save_support') {
        $post = $_POST;
        cfgSet(function (&$c) use ($post) {
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
                $c['support_methods'][$i]['value'] = trim(str_replace("\r\n", "\n", (string)($post["s_value_$i"] ?? '')));
            }
        });
        go('روش‌های پشتیبانی ذخیره شد.');
    }

    if ($a === 'master_webhook') {
        $r = tg(BOT_TOKEN, 'setWebhook', [
            'url' => baseUrl() . '/bot_master_membership.php',
            'drop_pending_updates' => 'true',
            'allowed_updates' => json_encode(['message', 'callback_query', 'my_chat_member']),
            'secret_token' => WEBHOOK_SECRET,
        ]);
        if (empty($r['ok'])) go('خطا: ' . ($r['description'] ?? '—'), 'err');
        maCachePut('menu_sig', '');
        maMenuSync();
        go('وبهوکِ ربات تنظیم شد.');
    }

    if ($a === 'api_base') {
        $u = rtrim(trim((string)($_POST['base_url'] ?? '')), '/');
        if ($u !== '' && !preg_match('#^https://[^\s]+$#i', $u)) go('آدرس باید با https:// شروع شود و فاصله نداشته باشد.', 'err');
        maSetRoot(function (&$m) use ($u) { $m['base_url'] = $u; });
        maMenuSync();
        go($u === '' ? 'آدرسِ مینی‌اپ خالی شد و از آدرسِ وبهوک حدس زده می‌شود.' : 'آدرسِ مینی‌اپ ذخیره شد.');
    }

    if ($a === 'api_num') {
        if (!function_exists('numSet')) go('بخشِ شماره مجازی در دسترس نیست.', 'err');
        $p = $_POST;
        $sw = false;
        numSet(function (&$n) use ($p, &$sw) {
            $n['provider']   = in_array($p['provider'] ?? '', array_keys(numProviders()), true) ? $p['provider'] : '5sim';
            $n['wait']       = max(60, (int)($p['wait'] ?? 900));
            $n['poll']       = max(3,  (int)($p['poll'] ?? 6));
            $n['markup']     = max(0, (float)str_replace(',', '', (string)($p['markup'] ?? 0)));
            $n['sync_price'] = !empty($p['sync_price']);
            if (!is_array($n['api'] ?? null)) $n['api'] = [];
            $n['api']['on']      = !empty($p['on']);
            $n['api']['base']    = rtrim(trim((string)($p['base'] ?? '')), '/');
            if (numBaseForeign($n['api']['base'], $n['provider'])) $n['api']['base'] = '';
            $n['api']['nl_svc']  = trim((string)($p['nl_svc'] ?? '1'));
            $n['api']['timeout'] = max(3, min(60, (int)($p['timeout'] ?? 15)));
            $n['api']['rate']    = max(0, (float)str_replace([',', '،'], '', (string)($p['rate'] ?? 0)));
            $n['api']['max']     = max(0, (float)str_replace([',', '،'], '', (string)($p['max'] ?? 0)));
            $t = preg_replace('/\s+/', '', (string)($p['token'] ?? ''));  if ($t !== '') $n['api']['token']  = $t;
            $k = preg_replace('/\s+/', '', (string)($p['nl_key'] ?? '')); if ($k !== '') $n['api']['nl_key'] = $k;
            if ($n['provider'] !== '5sim' && $k === '' && $t !== '' && numLooks5sim($t)) {
                $n['provider'] = '5sim';
                if (numBaseForeign($n['api']['base'], '5sim')) $n['api']['base'] = '';
                $sw = true;
            }
        });
        go('تنظیماتِ شماره مجازی ذخیره شد.' . ($sw ? ' توکنِ ۵سیم بود؛ فروشنده روی ۵سیم رفت. یک بار «📥 وارد کردن» را بزنید.' : ''));
    }

    if ($a === 'api_num_test') {
        $t0 = microtime(true);
        [$amt, $cur, $err] = numBalance();
        $ms = round((microtime(true) - $t0) * 1000);
        if ($err !== '') go('🔴 ' . numProvName() . ': ' . $err . ' — مقصد: ' . numBase() . ' (' . $ms . 'ms)', 'err');
        $rate = numNeedsRate() ? numRate() : 0;
        $pr = numProv() === '5sim' ? (num5User('profile')[0] ?? null) : null;
        go('✅ ' . numProvName() . ' وصل است — موجودی: ' . fmtNum($amt) . ' ' . $cur .
           ($rate > 0 ? ' ≈ ' . fmtNum(numRound100($amt * $rate)) . ' تومان' : '') .
           (is_array($pr) ? ' — بلوکه‌شده: ' . fmtNum((float)($pr['frozen_balance'] ?? 0)) . ' — امتیاز: ' . fmtNum((float)($pr['rating'] ?? 0)) . ' از ۹۶' : '') .
           ' (' . $ms . 'ms)');
    }

    if ($a === 'save_referral') {
        $on  = !empty($_POST['ref_on']);
        $pct = max(0, min(100, (float)($_POST['ref_percent'] ?? 0)));
        cfgSet(function (&$c) use ($on, $pct) {
            $c['referral']['on']      = $on;
            $c['referral']['percent'] = $pct;
        });
        go('رفرال ذخیره شد.');
    }
    if ($a === 'save_gateway') {
        $post = $_POST;
        $u = trim($post['gw_base'] ?? '');
        if ($u !== '' && !preg_match('#^https://#i', $u)) go('آدرس ربات باید با https:// شروع شود.', 'err');
        cfgSet(function (&$c) use ($post, $u) {
            $c['gateway']['on']       = !empty($post['gw_on']);
            $c['gateway']['provider'] = in_array($post['gw_prov'] ?? '', ['oxapay','nowpayments','custom'], true)
                                        ? $post['gw_prov'] : 'oxapay';
            $gwKey = trim($post['gw_key'] ?? '');
            $gwIpn = trim($post['gw_ipn'] ?? '');
            if ($gwKey !== '') $c['gateway']['api_key']    = $gwKey;
            if ($gwIpn !== '') $c['gateway']['ipn_secret'] = $gwIpn;
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
    if ($a === 'save_join') {
        $post = $_POST;
        cfgSet(function (&$c) use ($post) {
            $c['join']['on'] = !empty($post['jn_on']);
        });
        go('عضویت اجباری ذخیره شد.');
    }
    if ($a === 'add_join_channel') {
        $cid = trim($_POST['chat_id'] ?? '');
        if ($cid === '') go('آیدی کانال لازم است.', 'err');
        [$ok, $msg] = joinAddChannel($cid, (string)($_POST['title'] ?? ''), (string)($_POST['url'] ?? ''));
        go($ok ? 'کانال «' . $msg . '» اضافه شد.' : $msg, $ok ? 'ok' : 'err');
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

    if ($a === 'num_import') {
        if (!numReady()) go('اول در «API و اتصال‌ها» کلیدِ ' . numProvName() . ' را ثبت و فروش را روشن کنید.', 'err');
        @set_time_limit(120);
        [$countries, $rows, $err] = numCatalog();
        if ($err !== '') go('فهرست از ' . numProvName() . ' نیامد: ' . mb_substr($err, 0, 240), 'err');
        [$nc, $ni, $up, $off] = numImport($countries, $rows, (float)numVal('markup', 0));
        [$delI] = numPruneCatalog(array_column($rows, 'sid'));
        go('وارد شد — کشورِ تازه: ' . fmtNum($nc) . ' · شماره‌ی تازه: ' . fmtNum($ni) .
           ' · به‌روزشده: ' . fmtNum($up) . ' · خاموش‌شده: ' . fmtNum($off) .
           ($delI ? ' · حذف‌شده: ' . fmtNum($delI) : ''));
    }

    if ($a === 'num_cat_save') {
        $cid = (string)($_POST['cid'] ?? '');
        $cat = maFindCat($cid);
        if (!$cat) go('این کشور پیدا نشد.', 'err');
        $p   = $_POST;
        $its = is_array($p['it'] ?? null) ? $p['it'] : [];
        maSetRoot(function (&$m) use ($cid, $p, $its) {
            foreach ((array)($m['cats'] ?? []) as $k => $c) {
                if ((string)($c['id'] ?? '') !== $cid) continue;
                $nm = mb_substr(trim((string)($p['name'] ?? '')), 0, 40);
                $em = mb_substr(trim((string)($p['emoji'] ?? '')), 0, 16);
                if ($nm !== '') $m['cats'][$k]['name'] = $nm;
                $m['cats'][$k]['emoji'] = $em !== '' ? $em : '🌍';
                $m['cats'][$k]['on']    = !empty($p['on']);
            }
            foreach ((array)($m['items'] ?? []) as $k => $i) {
                $iid = (string)($i['id'] ?? '');
                if ((string)($i['cat'] ?? '') !== $cid || !is_array($its[$iid] ?? null)) continue;
                $r  = $its[$iid];
                $nm = mb_substr(trim((string)($r['name'] ?? '')), 0, 40);
                $pr = maNum($r['price'] ?? 0);
                if ($nm !== '') $m['items'][$k]['name']  = $nm;
                if ($pr > 0)    $m['items'][$k]['price'] = $pr;
                $m['items'][$k]['badge'] = mb_substr(trim((string)($r['badge'] ?? '')), 0, 16);
                $m['items'][$k]['on']    = !empty($r['on']);
            }
        });
        go('«' . ($cat['name'] ?? '') . '» ذخیره شد.');
    }

    if ($a === 'num_bulk') {
        $pct = maNum($_POST['pct'] ?? 0);
        $dir = ($_POST['dir'] ?? '') === 'down' ? -1 : 1;
        $cid = (string)($_POST['cid'] ?? '');
        $cap = $dir < 0 ? 90 : 500;
        if ($pct <= 0 || $pct > $cap) go('درصد باید بیشتر از صفر و حداکثر ' . $cap . ' باشد.', 'err');
        if ($cid !== '' && !maFindCat($cid)) go('این کشور پیدا نشد.', 'err');
        $f = 1 + $dir * $pct / 100;
        $n = 0;
        maSetRoot(function (&$m) use ($cid, $f, &$n) {
            foreach ((array)($m['items'] ?? []) as $k => $i) {
                if ($cid !== '' && (string)($i['cat'] ?? '') !== $cid) continue;
                $old = (float)($i['price'] ?? 0);
                if ($old <= 0) continue;
                $m['items'][$k]['price'] = max(100.0, numRound100($old * $f));
                $n++;
            }
        });
        go('قیمتِ ' . fmtNum($n) . ' شماره ' . ($dir > 0 ? 'گران‌تر' : 'ارزان‌تر') . ' شد.');
    }

    if ($a === 'num_cancel') {
        [$ok, $err] = numFinish((string)($_POST['id'] ?? ''), 'cancel');
        go($ok ? 'شماره لغو شد و مبلغ به کیف پولِ کاربر برگشت.' : $err, $ok ? 'ok' : 'err');
    }

    if ($a === 'save_topup_min') {
        $v = maNum($_POST['topup_min'] ?? 0);
        if ($v < 1000) go('کمترین مبلغ باید دست‌کم ۱٬۰۰۰ تومان باشد.', 'err');
        cfgSet(function (&$c) use ($v) { $c['topup_min'] = $v; });
        go('کمترین مبلغِ شارژ ذخیره شد.');
    }

    go();
}

function qsWith($params) {
    $q = array_merge($_GET, $params);
    unset($q['bot']);
    return '?' . http_build_query($q);
}

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

function oBadge($s) {
    $m = ['pending' => ['⏳ منتظر رسید', 'gray'], 'review' => ['🧾 بررسی', 'amber'],
          'approved' => ['✅ تایید', 'green'], 'rejected' => ['❌ رد', 'red']];
    [$l, $c] = $m[$s] ?? ['—', 'gray'];
    return '<span class="badge ' . $c . '">' . $l . '</span>';
}

function fk($action, $ret = null) {
    global $CSRF, $tab;
    $o = '<input type="hidden" name="csrf" value="' . h($CSRF) . '">'
       . '<input type="hidden" name="tab" value="' . h($tab) . '">'
       . '<input type="hidden" name="action" value="' . h($action) . '">';
    if ($ret !== null)
        $o .= '<input type="hidden" name="ret" value="'
            . h(http_build_query(array_merge(array_diff_key($_GET, ['tab' => 1]), $ret))) . '">';
    return $o;
}

function uLink($o) {
    $id = (int)($o['user_id'] ?? 0);
    $n  = ltrim(trim((string)($o['username'] ?? '')), '@');
    return '<a href="?tab=users&amp;id=' . $id . '">' . h($n !== '' ? atU($n) : pLabel($id)) . '</a>';
}

function nBadge($o) {
    $s = (string)($o['status'] ?? '');
    $c = [MaOrder::PAID => 'blue', MaOrder::DONE => 'green', MaOrder::REJECT => 'red'][$s] ?? 'gray';
    return '<span class="badge ' . $c . '">' . h(MaOrder::statusLabel($s)) . '</span>';
}

function topupMethod($o) {
    if (!empty($o['gw'])) return '💠 درگاه';
    $t = (string)($o['receipt_type'] ?? '');
    if ($t === 'photo') return '🧾 عکسِ رسید';
    if ($t === 'text')  return '🧾 ' . mb_substr(trim((string)($o['receipt'] ?? '')), 0, 40);
    return '—';
}

function dashSparkline($buckets) {
    $vals = array_values($buckets);
    $max = max(1, max($vals));
    $w = 280; $h = 52; $step = $w / max(1, count($vals) - 1);
    $pts = [];
    foreach ($vals as $i => $v) $pts[] = round($i * $step, 1) . ',' . round($h - ($v / $max) * ($h - 6) - 3, 1);
    $line = implode(' ', $pts);
    $fillPts = '0,' . $h . ' ' . $line . ' ' . $w . ',' . $h;
    $total = array_sum($vals);
    $out = '<svg class="sparkline" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img" ' .
           'aria-label="شماره‌های تحویل‌شده در ۷ روزِ اخیر: مجموعا ' . $total . '">' .
           '<polygon points="' . h($fillPts) . '" fill="var(--primary)" opacity=".18"></polygon>' .
           '<polyline points="' . h($line) . '" fill="none" stroke="var(--primary)" stroke-width="2.2" ' .
           'stroke-linecap="round" stroke-linejoin="round"></polyline></svg>';
    return [$out, $total];
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$C      = cfg();
$tab    = $_GET['tab'] ?? 'dashboard';
$pg     = max(1, (int)($_GET['page'] ?? 1));
$qs     = trim((string)($_GET['q'] ?? ''));

function icon($name, $cls = '') {
    static $p = [
        'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
        'gift'     => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/><path d="M12 8S10.5 3 8 3a2.5 2.5 0 0 0 0 5M12 8s1.5-5 4-5a2.5 2.5 0 0 1 0 5"/>',
        'headset'  => '<path d="M3 14v-2a9 9 0 0 1 18 0v2"/><path d="M21 15a2 2 0 0 1-2 2h-1v-5h1a2 2 0 0 1 2 2zM3 15a2 2 0 0 0 2 2h1v-5H5a2 2 0 0 0-2 2z"/><path d="M19 17v1a3 3 0 0 1-3 3h-3"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 7.9 19.4a1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0-1.1-2.7H2a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 3.6 7.9a1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H8a1.6 1.6 0 0 0 1-1.5V2a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V8a1.6 1.6 0 0 0 1.5 1H22a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'menu'     => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18z"/>',
        'sim'      => '<path d="M7 3h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><rect x="8.5" y="11" width="7" height="6" rx="1"/><path d="M12 11v6M8.5 14h7"/>',
        'wallet'   => '<rect x="3" y="6" width="18" height="14" rx="2.5"/><path d="M3 10h18M16 15h2M6 6V5a2 2 0 0 1 2-2h9"/>',
        'plug'     => '<path d="M9 2v5M15 2v5M6 7h12v4a6 6 0 0 1-12 0zM12 17v5"/>',
        'panelL'   => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M15 3v18"/>',
    ];
    $d = $p[$name] ?? $p['grid'];
    return '<svg class="ic ' . h($cls) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
         . 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

$TABS = [
  'dashboard' => ['grid',     'داشبورد'],
  'numorders' => ['sim',      'سفارش‌های شماره'],
  'numbers'   => ['globe',    'کشورها و قیمت‌ها'],
  'orders'    => ['wallet',   'شارژ کیف پول'],
  'users'     => ['users',    'کاربران'],
  'referral'  => ['gift',     'رفرال'],
  'support'   => ['headset',  'پشتیبانی'],
  'apis'      => ['plug',     'API و اتصال‌ها'],
  'settings'  => ['settings', 'تنظیمات'],
];
if (!isset($TABS[$tab])) $tab = 'dashboard';

$NAV = [
  'نمای کلی' => [
    ['dashboard', []],
  ],
  'شماره مجازی' => [
    ['numorders', [
      ['همه',        'st=all',      fn() => (($_GET['st'] ?? 'all') === 'all')],
      ['منتظرِ کد',   'st=paid',     fn() => (($_GET['st'] ?? '') === 'paid')],
      ['تحویل‌شده',   'st=done',     fn() => (($_GET['st'] ?? '') === 'done')],
      ['برگشتِ وجه',  'st=rejected', fn() => (($_GET['st'] ?? '') === 'rejected')],
    ]],
    ['numbers', [
      ['همه‌ی کشورها', 'f=all', fn() => (($_GET['f'] ?? 'all') === 'all')],
      ['فعال',         'f=on',  fn() => (($_GET['f'] ?? '') === 'on')],
      ['خاموش',        'f=off', fn() => (($_GET['f'] ?? '') === 'off')],
    ]],
  ],
  'مالی و کاربران' => [
    ['orders', [
      ['در انتظار', 'st=review',   fn() => (($_GET['st'] ?? 'review') === 'review')],
      ['تاییدشده',  'st=approved', fn() => (($_GET['st'] ?? '') === 'approved')],
      ['ردشده',     'st=rejected', fn() => (($_GET['st'] ?? '') === 'rejected')],
      ['همه',       'st=all',      fn() => (($_GET['st'] ?? '') === 'all')],
    ]],
    ['users', [
      ['همه کاربران', '',         fn() => empty($_GET['banned'])],
      ['مسدودها',     'banned=1', fn() => !empty($_GET['banned'])],
    ]],
    ['referral', []],
    ['support',  []],
  ],
  'سیستم' => [
    ['apis',     []],
    ['settings', [
      ['همه',          's=all',   fn() => (($_GET['s'] ?? 'all') === 'all')],
      ['تشخیص و سرعت', 's=speed', fn() => (($_GET['s'] ?? '') === 'speed')],
      ['درگاه پرداخت', 's=gw',    fn() => (($_GET['s'] ?? '') === 'gw')],
      ['عضویت اجباری', 's=join',  fn() => (($_GET['s'] ?? '') === 'join')],
      ['امنیت',        's=sec',   fn() => (($_GET['s'] ?? '') === 'sec')],
    ]],
  ],
];

$SEC_KEYS = ['all', 'speed', 'gw', 'join', 'sec'];
$sec = (string)($_GET['s'] ?? 'all');
if (!in_array($sec, $SEC_KEYS, true)) $sec = 'all';

$PER = 25;

$pendingN = pPendingCount();
$waitN    = MaOrder::countBy(MaOrder::PAID);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark">
<?php if (trim(panelFontCss()) === ''): ?>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" media="print" onload="this.media='all'"
      href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap">
<?php endif; ?>
<link rel="icon" href="data:,">
<title><?= h($TABS[$tab][1]) ?> — پنل مدیریت</title>
<style>
<?= panelFontCss() ?>
:root{
  --primary:#3b82f6; --primary-dim:#1d4ed8; --primary-soft:rgba(59,130,246,.12);
  --success:#10b981; --success-soft:rgba(16,185,129,.12);
  --warning:#f59e0b; --warning-soft:rgba(245,158,11,.12);
  --danger:#ef4444;  --danger-soft:rgba(239,68,68,.12);

  --bg:#070a11;
  --surface:rgba(255,255,255,.055);
  --surface-2:rgba(255,255,255,.09);
  --surface-3:rgba(255,255,255,.135);
  --border:rgba(255,255,255,.14); --border-soft:rgba(255,255,255,.075);
  --blur:blur(20px) saturate(170%);
  --blur-sm:blur(12px) saturate(150%);
  --shine:inset 0 1px 0 rgba(255,255,255,.10);

  --text:#eef2f8; --text-2:#9aa7bd; --text-3:#7c8aa3;

  --r-sm:8px; --r:12px; --r-lg:16px;
  --sp:16px;
  --shadow:0 1px 2px rgba(0,0,0,.22), 0 14px 40px -20px rgba(0,0,0,.75);
  --sidebar-w:250px; --header-h:56px;
  --font:'Vazirmatn','Vazir',Tahoma,system-ui,-apple-system,'Segoe UI',sans-serif;
}

*{box-sizing:border-box;margin:0;padding:0}
html{-webkit-text-size-adjust:100%}
body{background:var(--bg);color:var(--text);font-family:var(--font);
  font-size:14px;font-weight:400;line-height:1.75;min-height:100vh;-webkit-font-smoothing:antialiased}
body::before{content:'';position:fixed;inset:0;z-index:-1;pointer-events:none;
  background:
    radial-gradient(900px 620px at 88% -8%,  rgba(59,130,246,.20), transparent 62%),
    radial-gradient(760px 560px at 6%  4%,   rgba(14,165,233,.13), transparent 60%),
    radial-gradient(880px 700px at 50% 106%, rgba(16,185,129,.11), transparent 62%),
    linear-gradient(180deg,#070a11 0%,#080c15 55%,#05070c 100%)}
a{color:var(--primary);text-decoration:none}
a:hover{color:#60a5fa}
b,strong{font-weight:700}
::selection{background:var(--primary);color:#fff}
svg.ic{width:18px;height:18px;flex:0 0 18px;display:block}

header.top{position:sticky;top:0;z-index:40;height:var(--header-h);
  background:rgba(255,255,255,.045);backdrop-filter:var(--blur);-webkit-backdrop-filter:var(--blur);
  border-bottom:1px solid var(--border);box-shadow:var(--shine);
  display:flex;align-items:center;gap:12px;padding:0 16px}
header.top .brand{display:flex;align-items:center;gap:9px;font-weight:800;font-size:15px;white-space:nowrap}
header.top .brand svg.ic{width:20px;height:20px;color:var(--primary)}
header.top .sp{flex:1}
header.top .who{color:var(--text-3);font-size:12px;white-space:nowrap}
.iconbtn{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;
  border-radius:var(--r-sm);border:1px solid var(--border);background:var(--surface);
  color:var(--text-2);cursor:pointer;flex:0 0 auto}
.iconbtn:hover{color:var(--text);border-color:var(--border);background:var(--surface-2)}

.psearch{position:relative;flex:0 1 300px;min-width:0}
.psearch svg.ic{position:absolute;inset-inline-start:10px;top:50%;transform:translateY(-50%);
  color:var(--text-3);pointer-events:none;width:16px;height:16px}
.psearch input{width:100%;height:34px;padding:0 34px 0 12px;background:var(--surface);
  border:1px solid var(--border);border-radius:var(--r-sm);color:var(--text);font:inherit;font-size:13px}
.psearch input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-soft)}
.presults{position:absolute;top:calc(100% + 6px);inset-inline-start:0;inset-inline-end:0;z-index:60;
  background:var(--surface-2);border:1px solid var(--border);border-radius:var(--r);
  box-shadow:var(--shadow);padding:5px;max-height:60vh;overflow:auto}
.presults a{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);
  color:var(--text-2);font-size:13px}
.presults a:hover,.presults a.hi{background:var(--surface-3);color:var(--text)}
.presults .none{padding:10px;color:var(--text-3);font-size:12.5px;text-align:center}

.shell{display:flex;align-items:flex-start}
.sidebar{width:var(--sidebar-w);flex:0 0 var(--sidebar-w);position:sticky;top:var(--header-h);
  height:calc(100vh - var(--header-h));overflow-y:auto;border-inline-start:1px solid var(--border);
  padding:12px 10px 28px;scrollbar-width:thin;overscroll-behavior:contain;
  background:rgba(255,255,255,.032);backdrop-filter:var(--blur);-webkit-backdrop-filter:var(--blur)}
.content{flex:1;min-width:0}
.wrap{max-width:1200px;margin:0 auto;padding:22px 20px 64px}
body.nav-collapsed .sidebar{display:none}

.navgroup{margin-bottom:14px}
.navgroup>.t{padding:6px 10px;color:var(--text-3);font-size:11px;font-weight:700;letter-spacing:.4px}
.navlink{display:flex;align-items:center;gap:10px;padding:9px 11px;border-radius:var(--r-sm);
  color:var(--text-2);font-size:13.5px;font-weight:500;margin-bottom:2px;transition:background .13s,color .13s}
.navlink:hover{background:var(--surface);color:var(--text)}
.navlink.on{background:var(--primary);color:#fff;font-weight:700}
.navlink.on svg.ic{color:#fff}
.navlink svg.ic{color:var(--text-3)}
.navlink:hover svg.ic,.navlink.on svg.ic{color:currentColor}
.navlink .nbadge{margin-inline-start:auto;background:var(--danger);color:#fff;font-size:10.5px;
  font-weight:800;padding:1px 7px;border-radius:20px;min-width:20px;text-align:center}
.navlink.on .nbadge{background:rgba(255,255,255,.26)}
.subnav{margin:2px 0 6px;padding-inline-start:28px;display:flex;flex-direction:column;gap:1px}
.subnav a{padding:6px 10px;border-radius:6px;color:var(--text-3);font-size:12.5px}
.subnav a:hover{background:var(--surface);color:var(--text-2)}
.subnav a.on{color:var(--primary);font-weight:700;background:var(--primary-soft)}

.nav-toggle-cb,.nav-backdrop{display:none}

.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);
  margin-bottom:16px;overflow:hidden;box-shadow:var(--shadow)}
.card>h2,.card>summary>h2{font-size:14.5px;font-weight:700;padding:14px 17px;
  border-bottom:1px solid var(--border-soft);background:var(--surface-2);
  display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.card>h2 .sub,.card .sub{font-weight:400;color:var(--text-3);font-size:12px}
.card>.body{padding:17px}
.card>.body>:last-child{margin-bottom:0}
details.card>summary{cursor:pointer;list-style:none}
details.card>summary::-webkit-details-marker{display:none}
details.card>summary h2{border-bottom:0}
details.card[open]>summary h2{border-bottom:1px solid var(--border-soft)}
details.card>summary>h2:after{content:'';margin-inline-start:auto;width:7px;height:7px;flex:0 0 7px;
  border:solid var(--text-3);border-width:0 0 2px 2px;transform:rotate(-45deg);transition:transform .15s}
details.card[open]>summary>h2:after{transform:rotate(135deg)}
input[type=number]{direction:ltr;text-align:left}

.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:18px}
.stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);
  padding:15px 16px;box-shadow:var(--shadow)}
.stat .n{font-size:23px;font-weight:800;letter-spacing:-.4px;line-height:1.25}
.stat .n.amount{font-size:19px}
.stat .l{color:var(--text-3);font-size:12px;margin-top:3px}
.stat.acc .n{color:var(--primary)} .stat.ok .n{color:var(--success)}
.stat.warn .n{color:var(--warning)}

.tw{overflow-x:auto;overscroll-behavior-x:contain;-webkit-overflow-scrolling:touch}
table{width:100%;border-collapse:collapse;font-size:13.5px;min-width:540px}
th{text-align:right;padding:11px 13px;color:var(--text-3);font-weight:600;font-size:11.5px;
  border-bottom:1px solid var(--border);white-space:nowrap;background:var(--surface-2)}
td{padding:11px 13px;border-bottom:1px solid var(--border-soft);vertical-align:middle}
tbody tr:last-child td{border-bottom:none}
tbody tr:hover{background:var(--surface-2)}
td.num,.num{font-variant-numeric:tabular-nums;white-space:nowrap}
.empty{text-align:center;color:var(--text-3);padding:40px 18px;font-size:13.5px}
.empty .ic{font-size:30px;line-height:1.45;display:block;margin:0 auto 10px;opacity:.55}
.empty svg.ic{width:30px;height:30px;opacity:.45}

.badge{display:inline-block;padding:2.5px 9px;border-radius:20px;font-size:11.5px;font-weight:700;
  border:1px solid transparent;white-space:nowrap}
.badge.green{background:var(--success-soft);color:var(--success);border-color:rgba(16,185,129,.3)}
.badge.red{background:var(--danger-soft);color:var(--danger);border-color:rgba(239,68,68,.3)}
.badge.amber{background:var(--warning-soft);color:var(--warning);border-color:rgba(245,158,11,.3)}
.badge.gray{background:var(--surface-3);color:var(--text-2);border-color:var(--border)}
.badge.blue{background:var(--primary-soft);color:var(--primary);border-color:rgba(59,130,246,.3)}

label{display:block;color:var(--text-3);font-size:12px;margin-bottom:5px;font-weight:600}
input,select,textarea{width:100%;padding:9.5px 12px;background:var(--surface-2);color:var(--text);
  border:1px solid var(--border);border-radius:var(--r-sm);font:inherit;font-size:13.5px}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--primary);
  box-shadow:0 0 0 3px var(--primary-soft)}
textarea{resize:vertical;min-height:84px;line-height:1.85}
select{cursor:pointer}
input[type=checkbox],input[type=radio]{width:16px;height:16px;accent-color:var(--primary);cursor:pointer}
.grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:13px}

.fld{margin-bottom:13px}
.hint{color:var(--text-3);font-size:11.5px;margin-top:4px;line-height:1.7}
.chk{display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--text);font-size:13.5px;
  font-weight:500;margin-bottom:0}
.chk input{width:16px;height:16px;margin:0}
.row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.srow,.ghead{display:grid;gap:7px;align-items:center;min-width:600px}
.srow{margin-bottom:7px}
.ghead{font-size:11.5px;color:var(--text-3);font-weight:700;padding:0 2px 6px}

.btn,.pbtn{display:inline-flex;align-items:center;justify-content:center;gap:6px;
  padding:9.5px 16px;border-radius:var(--r-sm);border:1px solid var(--primary);background:var(--primary);
  color:#fff;font:inherit;font-size:13.5px;font-weight:700;cursor:pointer;white-space:nowrap;
  transition:filter .13s,transform .07s}
.btn:hover,.pbtn:hover{filter:brightness(1.1);color:#fff}
.btn:active{transform:translateY(1px)}
.btn.sm,.pbtn{padding:6px 12px;font-size:12.5px}
.btn:not(.ghost) .muted{color:rgba(255,255,255,.78)}
.btn.g{background:var(--success);border-color:var(--success);color:#04150f}
.btn.r{background:var(--danger);border-color:var(--danger)}
.btn.ghost,.pbtn{background:transparent;color:var(--text-2);border-color:var(--border)}
.btn.ghost:hover,.pbtn:hover{color:var(--text);border-color:var(--text-3);filter:none;background:var(--surface-2)}
.pbtn.pb-b{background:var(--primary);border-color:var(--primary);color:#fff}
.pbtn.pb-g{background:var(--success);border-color:var(--success);color:#04150f}
.pbtn.pb-r{background:var(--danger);border-color:var(--danger);color:#fff}
.btn[disabled]{opacity:.45;cursor:not-allowed}

.flash{position:relative;padding:12px 40px 12px 15px;border-radius:var(--r);margin-bottom:16px;
  font-size:13.5px;border:1px solid}
.flash.ok{background:var(--success-soft);color:#7fe3bd;border-color:rgba(16,185,129,.35)}
.flash.err{background:var(--danger-soft);color:#ffa9b4;border-color:rgba(239,68,68,.35)}
.flash .fx{position:absolute;inset-inline-end:9px;top:8px;background:none;border:none;color:inherit;
  opacity:.55;cursor:pointer;font-size:15px;width:auto;padding:2px 6px}
.flash .fx:hover{opacity:1}
.note{background:var(--surface-2);border:1px solid var(--border-soft);border-inline-start:3px solid var(--primary);
  border-radius:var(--r-sm);padding:11px 13px;color:var(--text-2);font-size:12.5px;line-height:1.85;margin-bottom:12px}
.note.warn{border-inline-start-color:var(--warning)}

.crumb{color:var(--text-3);font-size:12.5px;margin-bottom:13px}
.crumb b{color:var(--text)} .crumb span{margin:0 6px;opacity:.5}
.muted{color:var(--text-3)}
code,.secret-box{font-family:ui-monospace,'SF Mono',Menlo,monospace;font-size:.9em;
  background:var(--surface-3);padding:2px 6px;border-radius:6px;border:1px solid var(--border);
  overflow-wrap:anywhere;color:var(--text)}
.secret-box{display:block;padding:10px 12px;direction:ltr;text-align:left}
code{unicode-bidi:plaintext}
.secretin{display:flex;gap:6px;align-items:center}
.secretin input{flex:1;min-width:0}
.pager{display:flex;gap:5px;justify-content:center;margin-top:16px;flex-wrap:wrap}
.pager a,.pager span{padding:6px 12px;border-radius:var(--r-sm);border:1px solid var(--border);
  color:var(--text-2);font-size:12.5px}
.pager a:hover{border-color:var(--primary);color:var(--text)}
.pager .cur{background:var(--primary);border-color:var(--primary);color:#fff;font-weight:700}
.searchbar{display:flex;gap:8px;margin-bottom:15px;flex-wrap:wrap}
.searchbar input{flex:1;min-width:190px}
.sparkline{display:block;width:100%;height:56px;margin-top:10px}
.prev{background:var(--surface-2);border:1px dashed var(--border);border-radius:var(--r);
  padding:12px 14px;color:var(--text-2);font-size:13px;line-height:1.9}
hr{border:none;border-top:1px solid var(--border-soft);margin:15px 0}
h3{font-size:14px;font-weight:700;margin-bottom:10px}
p{margin-bottom:10px}
p:last-child{margin-bottom:0}

.card,.stat,.presults,.flash,.note,.prev,.badge,code,.secret-box,.iconbtn,.pager a,.pager span,input,select,textarea,.btn.ghost,.pbtn,.psearch input{
  backdrop-filter:var(--blur-sm);-webkit-backdrop-filter:var(--blur-sm)}

.card,.stat{
  backdrop-filter:var(--blur);-webkit-backdrop-filter:var(--blur);
  box-shadow:var(--shadow),var(--shine)}

.card>h2,.card>summary>h2{box-shadow:var(--shine)}

tbody tr{transition:background .12s}
tbody tr:hover{background:rgba(255,255,255,.06)}
th{backdrop-filter:var(--blur-sm);-webkit-backdrop-filter:var(--blur-sm)}

.btn,.pbtn{box-shadow:var(--shine)}
.navlink.on{box-shadow:var(--shine);
  background:linear-gradient(180deg,var(--primary),var(--primary-dim))}

.flash.ok{background:rgba(16,185,129,.14)}
.flash.err{background:rgba(239,68,68,.14)}

@supports not ((backdrop-filter:blur(1px)) or (-webkit-backdrop-filter:blur(1px))){
  :root{--surface:#141a27;--surface-2:#1b2333;--surface-3:#232d40;
        --border:#2a3548;--border-soft:#1f2839}
  .sidebar,header.top{background:#0d121c}
}

@media(max-width:900px){
  .psearch{flex:1 1 auto}
  header.top .who{display:none}
  .sidebar{position:fixed;top:0;bottom:0;right:0;left:auto;z-index:70;height:100vh;height:100dvh;
    width:270px;flex-basis:270px;max-width:86vw;padding-top:16px;
    background:rgba(13,18,28,.92);backdrop-filter:var(--blur);-webkit-backdrop-filter:var(--blur);
    transform:translateX(100%);visibility:hidden;
    transition:transform .22s cubic-bezier(.2,.8,.3,1),visibility 0s linear .22s;
    box-shadow:-10px 0 34px rgba(0,0,0,.5)}
  body.nav-collapsed .sidebar{display:block}
  .nav-toggle-cb:checked ~ .shell .sidebar{transform:none;visibility:visible;
    transition:transform .22s cubic-bezier(.2,.8,.3,1)}
  .nav-toggle-cb:checked ~ .nav-backdrop{display:block;position:fixed;inset:0;z-index:65;
    background:rgba(5,8,14,.5);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px)}
  .badge,code,.pager a,.pager span,input,select,textarea,.iconbtn{
    backdrop-filter:none;-webkit-backdrop-filter:none}
  .wrap{padding:16px 14px 60px}
  .stat .n{font-size:20px}
}
a.stat{display:block;color:inherit;transition:border-color .15s,transform .15s}
a.stat:hover{border-color:var(--primary);color:inherit;transform:translateY(-1px)}
.stat .n small{font-size:13px;font-weight:600}
.stat .n.sm{font-size:17px}
.stat.live .n{color:var(--primary)}
.stat.live .l::before{content:'';display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--success);
  margin-inline-end:6px;vertical-align:middle;animation:pulse 1.6s ease-in-out infinite}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(16,185,129,.55)}60%{box-shadow:0 0 0 6px rgba(16,185,129,0)}}
.navlink .nbadge.b{background:var(--primary)}
.steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px}
.step{display:flex;align-items:center;gap:10px;padding:11px 13px;border-radius:var(--r);border:1px solid var(--border);
  background:var(--surface-2);color:var(--text-2);font-size:13px;line-height:1.6}
a.step:hover{border-color:var(--primary);color:var(--text)}
.step b{flex:0 0 28px;height:28px;border-radius:50%;display:grid;place-items:center;background:var(--surface-3);color:var(--text);font-size:13px}
.step small{display:block;color:var(--text-3);font-size:11px}
.step.ok{border-color:rgba(16,185,129,.35);color:var(--text)}
.step.ok b{background:var(--success);color:#04150f}
.grid3{display:grid;grid-template-columns:2fr 1fr 1.4fr;gap:13px;align-items:end}
.grid2>.chk,.grid3>.chk{align-self:end;min-height:42px}
.row.fe{align-items:flex-end}
.kc{margin-top:3px;color:var(--success);font-size:12.5px}
span.kc{margin:0 6px 0 0}
.card>h2 .more{margin-inline-start:auto;color:var(--primary)}
details.cat>summary h2{font-size:14px}
details.cat>summary h2 .sub{margin-inline-start:auto}
details.cat>summary>h2:after{margin-inline-start:12px}
details.cat .flag{font-size:19px;line-height:1}
details.cat .cc{font-size:11px;color:var(--text-3)}
table.its{min-width:640px}
table.its td{padding:7px 9px}
table.its input:not([type=checkbox]){padding:7px 10px}
table.its td:nth-child(3) input{max-width:150px}
tr.grp td{background:var(--surface-2);font-weight:800}
.srow textarea{min-height:40px;height:40px;padding:8px 12px;line-height:1.6}
@media(max-width:700px){.grid3{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
</head>
<body>

<input type="checkbox" id="navToggle" class="nav-toggle-cb">

<header class="top">
  <label for="navToggle" class="iconbtn m-only" aria-label="منو"><?= icon('menu') ?></label>
  <div class="brand"><?= icon('panelL') ?> <span>نامبیکس</span></div>

  <div class="psearch">
    <?= icon('search') ?>
    <input id="pq" type="search" autocomplete="off" placeholder="جست‌وجو در پنل…"
           aria-label="جست‌وجو در بخش‌های پنل">
    <div class="presults" id="pres" hidden></div>
  </div>

  <div class="sp"></div>
  <span class="who">تنظیم‌های بازی و مینی‌اپ در خودِ ربات</span>
  <a class="iconbtn" href="?logout=1" aria-label="خروج" title="خروج"><?= icon('logout') ?></a>
</header>

<label for="navToggle" class="nav-backdrop"></label>

<div class="shell">
  <aside class="sidebar" id="sb">
    <?php foreach ($NAV as $gTitle => $items): ?>
      <nav class="navgroup">
        <div class="t"><?= h($gTitle) ?></div>
        <?php foreach ($items as [$k, $subs]):
          [$ico, $lbl] = $TABS[$k];
          $isOn = ($tab === $k);
          $n    = ['orders' => $pendingN, 'numorders' => $waitN][$k] ?? 0; ?>
          <a class="navlink<?= $isOn ? ' on' : '' ?>" href="?tab=<?= h($k) ?>">
            <?= icon($ico) ?><span><?= h($lbl) ?></span>
            <?php if ($n > 0): ?><span class="nbadge<?= $k === 'numorders' ? ' b' : '' ?>"><?= (int)$n ?></span><?php endif; ?>
          </a>
          <?php if ($isOn && $subs): ?>
            <div class="subnav">
              <?php foreach ($subs as [$vlbl, $vq, $vOn]):
                $href = '?tab=' . urlencode($k) . ($vq !== '' ? '&amp;' . $vq : ''); ?>
                <a class="<?= $vOn() ? 'on' : '' ?>" href="<?= $href ?>"><?= h($vlbl) ?></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
    <?php endforeach; ?>
  </aside>

  <main class="content"><div class="wrap">

<?php if ($flash): ?>
  <div class="flash <?= h($flash['type']) ?>">
    <button type="button" class="fx" aria-label="بستن" onclick="this.parentElement.remove()">✕</button>
    <?= nl2br(flashSafeHtml($flash['msg'])) ?>
  </div>
<?php endif; ?>

<?php if (pWeakPass()): ?>
  <div class="flash err">
    <b>رمزِ پنل ضعیف است.</b> از این پنل می‌شود به موجودیِ کاربران و کلیدِ فروشنده رسید —
    رمز را در <code>config.local.php</code> به دست‌کم ۱۲ نویسه با حرف و عدد و علامت عوض کنید.
  </div>
<?php endif; ?>

<?php if ($tab === 'dashboard'):
  $NS    = pNumStats();
  $iOn   = count(array_filter(maItems(), fn($x) => !empty($x['on'])));
  $ready = numReady();
  $base  = maBaseUrl();
  $maOn  = !empty(maCfg()['on']);
?>
  <?php if (!$ready || $iOn === 0 || $base === '' || !$maOn): ?>
  <div class="card"><h2>🚦 تا شروعِ فروش چه مانده؟</h2><div class="body">
    <div class="steps">
      <a class="step<?= $ready ? ' ok' : '' ?>" href="?tab=apis"><b><?= $ready ? '✓' : '۱' ?></b><span>اتصال به <?= h(numProvName()) ?></span></a>
      <a class="step<?= $iOn > 0 ? ' ok' : '' ?>" href="?tab=numbers"><b><?= $iOn > 0 ? '✓' : '۲' ?></b><span>وارد کردنِ کشورها و قیمت‌ها</span></a>
      <a class="step<?= $base !== '' ? ' ok' : '' ?>" href="?tab=apis"><b><?= $base !== '' ? '✓' : '۳' ?></b><span>آدرسِ عمومیِ مینی‌اپ</span></a>
      <div class="step<?= $maOn ? ' ok' : '' ?>"><b><?= $maOn ? '✓' : '۴' ?></b><span>باز بودنِ مینی‌اپ <small>داخلِ ربات ← 🚀 مینی‌اپ</small></span></div>
    </div>
  </div></div>
  <?php endif; ?>

  <div class="stats">
    <div class="stat acc"><div class="n"><?= h(fmtNum(pUserCount())) ?></div><div class="l">کاربر</div></div>
    <a class="stat<?= $waitN > 0 ? ' live' : '' ?>" href="?tab=numorders&amp;st=paid"><div class="n"><?= h(fmtNum($waitN)) ?></div><div class="l">منتظرِ کد، همین حالا</div></a>
    <div class="stat ok"><div class="n"><?= h(fmtNum($NS['today'])) ?></div><div class="l">شماره‌ی تحویل‌شده‌ی امروز</div></div>
    <div class="stat ok"><div class="n amount"><?= h(fmtNum($NS['today_rev'])) ?></div><div class="l">فروشِ امروز (تومان)</div></div>
    <div class="stat"><div class="n"><?= h(fmtNum($NS['done'])) ?></div><div class="l">کلِ شماره‌های تحویل‌شده</div></div>
    <div class="stat"><div class="n amount"><?= h(fmtNum($NS['revenue'])) ?></div><div class="l">کلِ فروش (تومان)</div></div>
    <div class="stat"><div class="n amount"><?= h(fmtNum(pWalletTotal())) ?></div><div class="l">مجموعِ کیف‌پول‌ها</div></div>
    <a class="stat<?= $pendingN > 0 ? ' warn' : '' ?>" href="?tab=orders"><div class="n"><?= h(fmtNum($pendingN)) ?></div><div class="l">شارژِ منتظرِ تایید</div></a>
  </div>

  <?php [$sparkSvg, $sparkTotal] = dashSparkline(pSparkBuckets(7)); ?>
  <div class="card"><h2>📈 شماره‌های تحویل‌شده در ۷ روزِ گذشته <span class="sub">— مجموعا <?= h(fmtNum($sparkTotal)) ?></span></h2><div class="body">
    <?= $sparkSvg ?>
  </div></div>

  <?php [$recent] = MaOrder::page('', '', 0, 8); ?>
  <div class="card"><h2>🧾 آخرین سفارش‌های شماره <a class="sub more" href="?tab=numorders">همه ←</a></h2>
    <?php if (!$recent): ?>
      <div class="empty"><span class="ic">☎️</span>هنوز شماره‌ای فروخته نشده.</div>
    <?php else: ?>
    <div class="tw"><table>
      <thead><tr><th>کاربر</th><th>شماره</th><th>تلفن</th><th>مبلغ</th><th>وضعیت</th><th>زمان</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $o): $act = numGet((string)$o['id']); ?>
        <tr>
          <td><?= uLink($o) ?></td>
          <td><?= h(trim(($o['item_emoji'] ?? '') . ' ' . ($o['item_name'] ?? ''))) ?></td>
          <td class="num"><?= !empty($act['phone']) ? '<code>' . h((string)$act['phone']) . '</code>' : '<span class="muted">—</span>' ?></td>
          <td class="num"><?= h(fmtNum($o['total'] ?? 0)) ?></td>
          <td><?= nBadge($o) ?></td>
          <td class="muted num"><?= h((string)($o['created_at'] ?? '—')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>

  <div class="card"><h2>🔗 وبهوکِ ربات</h2><div class="body">
    <p class="hint" style="margin-bottom:11px">اگر ربات جواب نمی‌دهد، یک بار این را بزنید. دکمه‌ی منوی ربات هم دوباره روی مینی‌اپ تنظیم می‌شود.</p>
    <form method="post" class="row"><?= fk('master_webhook') ?><button class="btn">ثبتِ دوباره‌ی وبهوک</button></form>
  </div></div>


<?php elseif ($tab === 'numorders'):
  $NST = ['all' => ['📋 همه', ''], 'paid' => ['📲 منتظرِ کد', MaOrder::PAID],
          'done' => ['✅ تحویل‌شده', MaOrder::DONE], 'rejected' => ['↩️ برگشتِ وجه', MaOrder::REJECT]];
  $nst = (string)($_GET['st'] ?? 'all');
  if (!isset($NST[$nst])) $nst = 'all';
  $cnt = [];
  foreach ([MaOrder::PENDING, MaOrder::PAID, MaOrder::DONE, MaOrder::REJECT] as $sv) $cnt[$sv] = MaOrder::countBy($sv);
  $cnt[''] = array_sum($cnt);
  [$rows, $total] = MaOrder::page($NST[$nst][1], $qs, ($pg - 1) * $PER, $PER);
  if (!$rows && $qs !== '') [$rows, $total] = pNumByPhone($qs, $NST[$nst][1], $PER);
  $pages = max(1, (int)ceil($total / $PER));
  $now   = time();
?>
  <div class="card"><h2>☎️ سفارش‌های شماره <span class="sub">— <?= h(fmtNum($total)) ?> ردیف</span></h2><div class="body">
    <form method="get" class="searchbar">
      <input type="hidden" name="tab" value="numorders">
      <input type="hidden" name="st" value="<?= h($nst) ?>">
      <input name="q" value="<?= h($qs) ?>" placeholder="آیدیِ عددیِ کاربر، شماره‌ی سفارش یا شماره تلفن…">
      <button class="btn">جست‌وجو</button>
      <?php if ($qs !== ''): ?><a class="btn ghost" href="?tab=numorders&amp;st=<?= h($nst) ?>">پاک کردن</a><?php endif; ?>
    </form>
    <div class="row">
      <?php foreach ($NST as $k => [$lbl, $sv]): ?>
        <a class="btn <?= $nst === $k ? '' : 'ghost' ?> sm"
           href="?tab=numorders&amp;st=<?= h($k) ?><?= $qs !== '' ? '&amp;q=' . urlencode($qs) : '' ?>">
          <?= h($lbl) ?> <span class="muted"><?= h(fmtNum($cnt[$sv])) ?></span></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="empty"><span class="ic">☎️</span><?= $qs !== '' ? 'چیزی با این جست‌وجو پیدا نشد.' : 'سفارشی با این فیلتر نیست.' ?></div>
  <?php else: ?>
  <div class="tw"><table>
    <thead><tr><th>کاربر</th><th>شماره</th><th>تلفن و کد</th><th>مبلغ</th><th>وضعیت</th><th>زمان</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $o):
      $act = numGet((string)$o['id']);
      $ast = (string)($act['status'] ?? ''); ?>
      <tr>
        <td><?= uLink($o) ?></td>
        <td><?= h(trim(($o['item_emoji'] ?? '') . ' ' . ($o['item_name'] ?? ''))) ?>
          <div class="hint"><code><?= h((string)$o['id']) ?></code><?= !empty($o['coupon']) ? ' · 🎟 ' . h((string)$o['coupon']) : '' ?><?= !empty($o['gift']) ? ' · 💎 هدیه' : '' ?></div></td>
        <td class="num">
          <?php if (!empty($act['phone'])): ?>
            <code><?= h((string)$act['phone']) ?></code>
            <?php if ((string)($act['code'] ?? '') !== ''): ?>
              <div class="kc">🔑 <b><?= h((string)$act['code']) ?></b></div>
            <?php elseif ($ast === 'waiting'): $left = max(0, numWaitFor($act) - ($now - (int)($act['created'] ?? $now))); ?>
              <div class="hint">⏳ <?= intdiv($left, 60) ?>:<?= str_pad((string)($left % 60), 2, '0', STR_PAD_LEFT) ?> مانده</div>
            <?php endif; ?>
          <?php else: ?><span class="muted">—</span><?php endif; ?>
        </td>
        <td class="num"><?= h(fmtNum($o['total'] ?? 0)) ?></td>
        <td><?= nBadge($o) ?>
          <?php if (!empty($o['last_error']) && ($o['status'] ?? '') === MaOrder::REJECT): ?><div class="hint"><?= h(mb_substr((string)$o['last_error'], 0, 80)) ?></div><?php endif; ?></td>
        <td class="muted num"><?= h((string)($o['created_at'] ?? '—')) ?></td>
        <td>
          <?php if ($ast === 'waiting' || $ast === 'buying'): ?>
          <form method="post" onsubmit="return confirm('این شماره لغو و مبلغ به کیف پولِ کاربر برگردانده شود؟')">
            <?= fk('num_cancel', []) ?><input type="hidden" name="id" value="<?= h((string)$o['id']) ?>">
            <button class="btn r sm">لغو و برگشتِ وجه</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php pager($pg, $pages); ?>
  <?php endif; ?>
  </div>


<?php elseif ($tab === 'numbers'):
  $f = (string)($_GET['f'] ?? 'all');
  if (!in_array($f, ['all', 'on', 'off'], true)) $f = 'all';
  $cats  = maCats();
  $items = maItems();
  $byCat = [];
  foreach ($items as $it) $byCat[(string)($it['cat'] ?? '')][] = $it;
  $sold = maSoldByCat();
  $cOn  = count(array_filter($cats,  fn($x) => !empty($x['on'])));
  $iOn  = count(array_filter($items, fn($x) => !empty($x['on'])));
  $ql   = mb_strtolower($qs);
  $list = array_values(array_filter($cats, function ($c) use ($f, $ql) {
      if ($f === 'on'  && empty($c['on']))  return false;
      if ($f === 'off' && !empty($c['on'])) return false;
      return $ql === '' || str_contains(mb_strtolower((string)($c['name'] ?? '') . ' ' . ($c['code'] ?? '')), $ql);
  }));
  $pages = max(1, (int)ceil(count($list) / 20));
  $cpg   = min($pg, $pages);
  $list  = array_slice($list, ($cpg - 1) * 20, 20);
  $open  = (string)($_GET['open'] ?? '');
  $pi    = numProvInfo();
  $mkS   = rtrim(rtrim(number_format((float)numVal('markup', 0), 1), '0'), '.');
  $sync  = !empty(numVal('sync_price', true));
?>
  <div class="stats">
    <div class="stat acc"><div class="n"><?= h(fmtNum($cOn)) ?> <small class="muted">/ <?= h(fmtNum(count($cats))) ?></small></div><div class="l">🌍 کشورِ فعال</div></div>
    <div class="stat ok"><div class="n"><?= h(fmtNum($iOn)) ?> <small class="muted">/ <?= h(fmtNum(count($items))) ?></small></div><div class="l">☎️ شماره‌ی فعال</div></div>
    <div class="stat"><div class="n sm"><?= h($pi['name']) ?></div><div class="l">🏪 فروشنده · <?= numReady() ? 'وصل' : 'وصل نیست' ?></div></div>
    <div class="stat"><div class="n"><?= h($mkS) ?>٪</div><div class="l">📈 سود روی قیمتِ فروشنده</div></div>
  </div>

  <div class="card"><h2>📥 وارد کردن از <?= h($pi['name']) ?> <?= numReady() ? '<span class="badge green">آماده</span>' : '<span class="badge red">وصل نیست</span>' ?></h2><div class="body">
    <div class="note">
      فهرستِ کشورها و قیمتِ روزِ شماره‌های <b>تلگرام</b> از <?= h($pi['name']) ?> گرفته می‌شود و سودِ
      <b><?= h($mkS) ?>٪</b> رویش می‌نشیند. کشورِ تازه ساخته می‌شود، ردیفی که دیگر روی فروشنده نیست حذف می‌شود
      و نام، ایموجی و برچسبی که خودتان نوشته‌اید دست نمی‌خورد.
      <?= $sync ? 'قیمتِ ردیف‌های موجود هم <b>از فروشنده به‌روز می‌شود</b>.' : 'قیمتِ ردیف‌های موجود <b>دست نمی‌خورد</b>.' ?>
      <br>درصدِ سود و «قیمت از فروشنده» در <a href="?tab=apis">API و اتصال‌ها ← ☎️ شماره مجازی</a> است.
    </div>
    <?php if (numReady()): ?>
    <form method="post" onsubmit="return confirm('فهرست از فروشنده گرفته و کاتالوگ به‌روز شود؟')">
      <?= fk('num_import', []) ?><button class="btn g">📥 وارد کردن و به‌روزرسانی</button>
    </form>
    <?php else: ?>
      <a class="btn" href="?tab=apis">اول فروشنده را وصل کنید</a>
    <?php endif; ?>
  </div></div>

  <?php if ($items): ?>
  <details class="card"><summary><h2>🧮 تغییرِ گروهیِ قیمت</h2></summary><div class="body">
    <?php if ($sync): ?>
      <div class="note warn">«قیمت از فروشنده» روشن است؛ با ورودِ بعدی قیمت‌ها دوباره از فروشنده ساخته می‌شوند.
        برای سودِ ماندگار، درصدِ سود را در <a href="?tab=apis">API و اتصال‌ها</a> عوض کنید.</div>
    <?php endif; ?>
    <form method="post" class="row fe" onsubmit="return confirm('قیمت‌ها عوض شوند؟')">
      <?= fk('num_bulk', []) ?>
      <div style="flex:2;min-width:190px"><label>کدام شماره‌ها</label><select name="cid">
        <option value="">همه‌ی کشورها</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= h((string)$c['id']) ?>"><?= h(trim(($c['emoji'] ?? '') . ' ' . ($c['name'] ?? ''))) ?></option>
        <?php endforeach; ?></select></div>
      <div style="flex:1;min-width:120px"><label>جهت</label><select name="dir">
        <option value="up">گران‌تر</option><option value="down">ارزان‌تر</option></select></div>
      <div style="flex:1;min-width:110px"><label>چند درصد</label>
        <input name="pct" type="number" min="0.1" max="500" step="0.1" required></div>
      <button class="btn">اعمال</button>
    </form>
    <div class="hint">قیمتِ تازه به نزدیک‌ترین ۱۰۰ تومان گِرد می‌شود.</div>
  </div></details>
  <?php endif; ?>

  <div class="card"><div class="body">
    <form method="get" class="searchbar">
      <input type="hidden" name="tab" value="numbers">
      <input type="hidden" name="f" value="<?= h($f) ?>">
      <input name="q" value="<?= h($qs) ?>" placeholder="نامِ کشور یا کدش، مثلا روسیه…">
      <button class="btn">جست‌وجو</button>
      <?php if ($qs !== ''): ?><a class="btn ghost" href="?tab=numbers&amp;f=<?= h($f) ?>">پاک کردن</a><?php endif; ?>
    </form>
    <div class="row">
      <?php foreach (['all' => 'همه', 'on' => 'فعال', 'off' => 'خاموش'] as $k => $lbl): ?>
        <a class="btn <?= $f === $k ? '' : 'ghost' ?> sm"
           href="?tab=numbers&amp;f=<?= $k ?><?= $qs !== '' ? '&amp;q=' . urlencode($qs) : '' ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>
  </div></div>

  <?php if (!$cats): ?>
    <div class="empty"><span class="ic">🌍</span>هنوز کشوری نیست — «📥 وارد کردن» را بزنید.</div>
  <?php elseif (!$list): ?>
    <div class="empty"><span class="ic">🔍</span>کشوری با این فیلتر نیست.</div>
  <?php else: ?>
    <?php foreach ($list as $c):
      $cid = (string)($c['id'] ?? '');
      $its = $byCat[$cid] ?? [];
      $ion = array_filter($its, fn($x) => !empty($x['on']));
      $min = $ion ? min(array_map('maItemPrice', $ion)) : 0; ?>
    <details class="card cat" id="c-<?= h($cid) ?>"<?= $open === $cid ? ' open' : '' ?>>
      <summary><h2><span class="flag"><?= h((string)($c['emoji'] ?? '🌍')) ?></span> <?= h((string)($c['name'] ?? '')) ?>
        <?php if (!empty($c['code'])): ?><code class="cc"><?= h((string)$c['code']) ?></code><?php endif; ?>
        <?= !empty($c['on']) ? '<span class="badge green">فعال</span>' : '<span class="badge">خاموش</span>' ?>
        <span class="sub"><?= h(fmtNum(count($ion))) ?> از <?= h(fmtNum(count($its))) ?> شماره<?= $min > 0 ? ' · از ' . h(fmtNum($min)) . ' تومان' : '' ?><?= !empty($sold[$cid]) ? ' · ' . h(fmtNum($sold[$cid])) . ' فروش' : '' ?></span></h2></summary>
      <div class="body">
        <form method="post">
          <?= fk('num_cat_save', ['open' => $cid]) ?><input type="hidden" name="cid" value="<?= h($cid) ?>">
          <div class="grid3">
            <div><label>نامِ کشور</label><input name="name" value="<?= h((string)($c['name'] ?? '')) ?>" maxlength="40" required></div>
            <div><label>ایموجی / پرچم</label><input name="emoji" value="<?= h((string)($c['emoji'] ?? '')) ?>" style="text-align:center"></div>
            <label class="chk"><input type="checkbox" name="on" value="1" <?= !empty($c['on']) ? 'checked' : '' ?>> در مینی‌اپ نمایش داده شود</label>
          </div>
          <?php if ($its): ?>
          <div class="tw" style="margin-top:14px"><table class="its">
            <thead><tr><th>فعال</th><th>اپراتور</th><th>قیمت (تومان)</th><th>برچسب</th><th>شناسه</th></tr></thead>
            <tbody>
            <?php foreach ($its as $it): $iid = h((string)($it['id'] ?? '')); ?>
              <tr>
                <td><input type="checkbox" name="it[<?= $iid ?>][on]" value="1" <?= !empty($it['on']) ? 'checked' : '' ?>></td>
                <td><input name="it[<?= $iid ?>][name]" value="<?= h((string)($it['name'] ?? '')) ?>" maxlength="40"></td>
                <td><input name="it[<?= $iid ?>][price]" value="<?= h(fmtNum($it['price'] ?? 0)) ?>" inputmode="numeric" style="direction:ltr"></td>
                <td><input name="it[<?= $iid ?>][badge]" value="<?= h((string)($it['badge'] ?? '')) ?>" maxlength="16" placeholder="مثلا 🔥 پرفروش"></td>
                <td class="muted"><code><?= h((string)($it['svc'] ?? '') ?: '—') ?></code></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
          <?php else: ?>
            <div class="hint" style="margin-top:10px">این کشور هنوز شماره‌ای ندارد.</div>
          <?php endif; ?>
          <div style="margin-top:14px"><button class="btn g">ذخیره‌ی <?= h((string)($c['name'] ?? '')) ?></button></div>
        </form>
      </div>
    </details>
    <?php endforeach; ?>
    <?php pager($cpg, $pages); ?>
  <?php endif; ?>


<?php elseif ($tab === 'orders'):
  $st = (string)($_GET['st'] ?? 'review');
  if (!in_array($st, ['review', 'approved', 'rejected', 'all'], true)) $st = 'review';
  [$rows, $total, $pages, $opg] = pOrdersPage($st, $qs, $pg, $PER);
  $counts = [
    'review'   => $pendingN,
    'approved' => Order::countBy(Order::APPROVED),
    'rejected' => Order::countBy(Order::REJECTED),
  ];
  $counts['all'] = $counts['review'] + $counts['approved'] + $counts['rejected'] + Order::countBy(Order::PENDING);
  $tmin = max(1000.0, (float)($C['topup_min'] ?? 10000));
?>
  <div class="card"><h2>💳 شارژِ کیف پول <span class="sub">— <?= h(fmtNum($total)) ?> ردیف</span></h2><div class="body">
    <form method="get" class="searchbar">
      <input type="hidden" name="tab" value="orders">
      <input type="hidden" name="st" value="<?= h($st) ?>">
      <input name="q" value="<?= h($qs) ?>" placeholder="جست‌وجو با آیدیِ کاربر یا شماره‌ی سفارش…">
      <button class="btn">جست‌وجو</button>
      <?php if ($qs !== ''): ?><a class="btn ghost" href="?tab=orders&amp;st=<?= h($st) ?>">پاک کردن</a><?php endif; ?>
    </form>
    <div class="row">
      <?php foreach (['review' => '⏳ منتظرِ تایید', 'approved' => '✅ تاییدشده',
                      'rejected' => '❌ ردشده', 'all' => '📋 همه'] as $k => $lbl): ?>
        <a class="btn <?= $st === $k ? '' : 'ghost' ?> sm"
           href="?tab=orders&amp;st=<?= h($k) ?><?= $qs !== '' ? '&amp;q=' . urlencode($qs) : '' ?>">
          <?= h($lbl) ?> <span class="muted"><?= h(fmtNum($counts[$k])) ?></span></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="empty"><span class="ic">🗂</span>شارژی با این فیلتر نیست.</div>
  <?php else: ?>
  <div class="tw"><table>
    <thead><tr><th>کاربر</th><th>مبلغ</th><th>روش</th><th>وضعیت</th><th>زمان</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $oid => $o): ?>
      <tr>
        <td><?= uLink($o) ?><div class="hint"><code><?= h((string)$oid) ?></code></div></td>
        <td class="num"><b><?= h(fmtNum($o['amount'] ?? 0)) ?></b> <span class="muted"><?= h($o['currency'] ?? '') ?></span></td>
        <td><?= h(topupMethod($o)) ?></td>
        <td><?= oBadge($o['status'] ?? '') ?></td>
        <td class="muted num"><?= h($o['created_at'] ?? '—') ?></td>
        <td>
          <?php if (($o['status'] ?? '') === Order::REVIEW): ?>
          <div class="row">
            <form method="post" onsubmit="return confirm('این شارژ تایید و مبلغ به کیف پول اضافه شود؟')">
              <?= fk('approve_order', []) ?><input type="hidden" name="id" value="<?= h((string)$oid) ?>">
              <button class="btn g sm">تایید</button>
            </form>
            <form method="post" onsubmit="return confirm('این شارژ رد شود؟')">
              <?= fk('reject_order', []) ?><input type="hidden" name="id" value="<?= h((string)$oid) ?>">
              <button class="btn r sm">رد</button>
            </form>
          </div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php pager($opg, $pages); ?>
  <?php endif; ?>
  </div>

  <div class="card"><h2>⚖️ کمترین مبلغِ شارژ</h2><div class="body">
    <form method="post" class="row fe">
      <?= fk('save_topup_min', ['st' => $st]) ?>
      <div style="flex:1;min-width:200px"><label>کمترین مبلغی که کاربر می‌تواند شارژ کند (تومان)</label>
        <input name="topup_min" value="<?= h(fmtNum($tmin)) ?>" inputmode="numeric" style="direction:ltr"></div>
      <button class="btn g">ذخیره</button>
    </form>
    <div class="hint">در ربات و مینی‌اپ، مبلغِ کمتر از این پذیرفته نمی‌شود.</div>
  </div></div>


<?php elseif ($tab === 'users'):
  $uid = trim((string)($_GET['id'] ?? ''));
?>
  <?php if ($uid !== '' && ($uD = pUser($uid))):
    [$uTopups, $uTopupsN, $uTopup] = pOrdersOfUser($uid, 20);
    $uSpent = pNumSpent($uid);
    $uNums  = MaOrder::forUser((int)$uid, 30);
  ?>
  <div class="crumb">کاربران <span>/</span> <b><?= h(pLabel($uid, $uD)) ?></b></div>
  <a href="?tab=users" class="btn ghost sm" style="margin-bottom:14px">بازگشت به فهرست</a>

  <div class="card"><h2>👤 اطلاعاتِ کاربر</h2><div class="body">
    <div class="grid2">
      <div><label>نام</label><div><?= h(!empty($uD['username']) ? atU($uD['username']) : ($uD['first_name'] ?? '—')) ?></div></div>
      <div><label>آیدیِ تلگرام</label><div><code><?= h($uid) ?></code></div></div>
      <div><label>وضعیت</label><div><?= !empty($uD['banned'])
        ? '<span class="badge red">مسدود</span>' : '<span class="badge green">فعال</span>' ?></div></div>
      <div><label>تاریخِ عضویت</label><div class="muted"><?= h($uD['joined_at'] ?? '—') ?></div></div>
      <div><label>زیرمجموعه‌ها</label><div><?= h(fmtNum(pRefCount($uid))) ?> نفر</div></div>
      <div><label>معرف</label><div>
        <?php $rf = (int)($uD['referrer'] ?? 0); ?>
        <?= $rf > 0 ? '<a href="?tab=users&amp;id=' . $rf . '">' . h(pLabel($rf)) . '</a>' : '<span class="muted">—</span>' ?>
      </div></div>
    </div>
    <hr>
    <form method="post" onsubmit="return confirm('<?= !empty($uD['banned']) ? 'این کاربر آزاد شود؟' : 'این کاربر مسدود شود؟ دیگر نمی‌تواند از ربات استفاده کند.' ?>')">
      <?= fk('ban_user', []) ?><input type="hidden" name="user_id" value="<?= h($uid) ?>">
      <button class="btn <?= !empty($uD['banned']) ? 'g' : 'r' ?> sm"><?= !empty($uD['banned']) ? 'آزاد کردن' : 'مسدود کردن' ?></button>
    </form>
  </div></div>

  <div class="card"><h2>💰 کیف پول</h2><div class="body">
    <div class="stats" style="margin-bottom:15px">
      <div class="stat acc"><div class="n amount"><?= h(fmtNum($uD['balance'] ?? 0)) ?></div><div class="l">موجودیِ فعلی</div></div>
      <div class="stat ok"><div class="n amount"><?= h(fmtNum($uTopup)) ?></div><div class="l">مجموعِ شارژ</div></div>
      <div class="stat"><div class="n amount"><?= h(fmtNum($uSpent)) ?></div><div class="l">مجموعِ خریدِ شماره</div></div>
    </div>
    <form method="post" class="row fe" onsubmit="return confirm('موجودیِ این کاربر عوض شود؟')">
      <?= fk('set_balance', []) ?><input type="hidden" name="user_id" value="<?= h($uid) ?>">
      <div style="flex:1;min-width:170px"><label>موجودیِ تازه (تومان)</label>
        <input name="balance" value="<?= h(fmtNum($uD['balance'] ?? 0)) ?>" inputmode="numeric" style="direction:ltr"></div>
      <button class="btn">ذخیره</button>
    </form>
  </div></div>

  <div class="card"><h2>☎️ شماره‌های این کاربر <span class="sub">— ۳۰ تای آخر</span></h2>
    <?php if (!$uNums): ?>
      <div class="empty"><span class="ic">☎️</span>هنوز شماره‌ای نخریده.</div>
    <?php else: ?>
    <div class="tw"><table>
      <thead><tr><th>شماره</th><th>تلفن و کد</th><th>مبلغ</th><th>وضعیت</th><th>زمان</th></tr></thead>
      <tbody>
      <?php foreach ($uNums as $o): $act = numGet((string)$o['id']); ?>
        <tr>
          <td><?= h(trim(($o['item_emoji'] ?? '') . ' ' . ($o['item_name'] ?? ''))) ?></td>
          <td class="num"><?php if (!empty($act['phone'])): ?><code><?= h((string)$act['phone']) ?></code><?php if ((string)($act['code'] ?? '') !== ''): ?> <span class="kc">🔑 <b><?= h((string)$act['code']) ?></b></span><?php endif; ?><?php else: ?><span class="muted">—</span><?php endif; ?></td>
          <td class="num"><?= h(fmtNum($o['total'] ?? 0)) ?></td>
          <td><?= nBadge($o) ?></td>
          <td class="muted num"><?= h((string)($o['created_at'] ?? '—')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>

  <div class="card"><h2>💳 شارژهای این کاربر <span class="sub">— <?= h(fmtNum($uTopupsN)) ?> ردیف<?= $uTopupsN > 20 ? '، ۲۰ تای آخر' : '' ?></span></h2>
    <?php if (!$uTopups): ?>
      <div class="empty"><span class="ic">🗂</span>هنوز شارژی ندارد.</div>
    <?php else: ?>
    <div class="tw"><table>
      <thead><tr><th>مبلغ</th><th>روش</th><th>وضعیت</th><th>زمان</th></tr></thead>
      <tbody>
      <?php foreach ($uTopups as $o): ?>
        <tr>
          <td class="num"><?= h(fmtNum($o['amount'] ?? 0)) ?> <span class="muted"><?= h($o['currency'] ?? '') ?></span></td>
          <td><?= h(topupMethod($o)) ?></td>
          <td><?= oBadge($o['status'] ?? '') ?></td>
          <td class="muted num"><?= h($o['created_at'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>

  <?php else:
    $onlyBanned = !empty($_GET['banned']);
    [$rows, $total] = pUsersPage($pg, $PER, $qs, $onlyBanned);
    $pages = max(1, (int)ceil($total / $PER));
  ?>
  <?php if ($uid !== ''): ?><div class="flash err">کاربری با آیدیِ <code><?= h($uid) ?></code> پیدا نشد.</div><?php endif; ?>
  <div class="card"><h2>👥 کاربران <span class="sub">— <?= h(fmtNum(pUserCount())) ?> نفر</span></h2><div class="body">
    <form method="get" class="searchbar">
      <input type="hidden" name="tab" value="users">
      <?php if ($onlyBanned): ?><input type="hidden" name="banned" value="1"><?php endif; ?>
      <input name="q" value="<?= h($qs) ?>" placeholder="نامِ کاربری، نام، یا آیدیِ عددی…">
      <button class="btn">جست‌وجو</button>
      <?php if ($qs !== ''): ?><a class="btn ghost" href="?tab=users<?= $onlyBanned ? '&amp;banned=1' : '' ?>">پاک کردن</a><?php endif; ?>
    </form>
    <div class="row">
      <a class="btn <?= $onlyBanned ? 'ghost' : '' ?> sm" href="?tab=users<?= $qs !== '' ? '&amp;q=' . urlencode($qs) : '' ?>">همه</a>
      <a class="btn <?= $onlyBanned ? '' : 'ghost' ?> sm" href="?tab=users&amp;banned=1<?= $qs !== '' ? '&amp;q=' . urlencode($qs) : '' ?>">فقط مسدودها</a>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="empty"><span class="ic">🔍</span><?= $qs !== '' ? 'کسی با این مشخصات پیدا نشد.' : 'هنوز کاربری نیست.' ?></div>
  <?php else: ?>
  <div class="tw"><table>
    <thead><tr><th>کاربر</th><th>آیدی</th><th>موجودی</th><th>وضعیت</th><th>عضویت</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $rid => $u): ?>
      <tr>
        <td><a href="?tab=users&amp;id=<?= h((string)$rid) ?>"><?= h(pLabel($rid, $u)) ?></a></td>
        <td><code><?= h((string)$rid) ?></code></td>
        <td class="num"><?= h(fmtNum($u['balance'] ?? 0)) ?></td>
        <td><?= !empty($u['banned']) ? '<span class="badge red">مسدود</span>' : '<span class="badge green">فعال</span>' ?></td>
        <td class="muted num"><?= h($u['joined_at'] ?? '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php pager($pg, $pages); ?>
  <?php endif; ?>
  </div>
  <?php endif; ?>


<?php elseif ($tab === 'referral'):
  $topRefs = pTopReferrers(50);
  $withRef = pUserCount("CAST(json_extract(data,'$.referrer') AS INTEGER) > 0");
?>
  <div class="stats">
    <div class="stat"><div class="n"><?= h(fmtNum(count($topRefs))) ?></div><div class="l">👤 معرفِ فعال</div></div>
    <div class="stat"><div class="n"><?= h(fmtNum($withRef)) ?></div><div class="l">👥 کاربرِ معرفی‌شده</div></div>
    <div class="stat acc"><div class="n"><?= h(fmtNum($C['referral']['percent'])) ?>٪</div><div class="l">📈 پورسانت از هر خرید</div></div>
    <div class="stat<?= !empty($C['referral']['on']) ? ' ok' : '' ?>"><div class="n sm"><?= !empty($C['referral']['on']) ? 'روشن' : 'خاموش' ?></div><div class="l">🎁 سیستمِ معرفی</div></div>
  </div>

  <div class="card"><h2>🎁 تنظیمِ رفرال</h2><div class="body">
    <div class="note">از هر شماره‌ای که زیرمجموعه می‌خرد، این درصد از مبلغ به کیف پولِ معرف اضافه می‌شود.</div>
    <form method="post">
      <?= fk('save_referral') ?>
      <div class="grid2">
        <div><label>درصدِ پورسانت از هر خرید</label>
          <input name="ref_percent" type="number" min="0" max="100" step="0.5" value="<?= h((string)$C['referral']['percent']) ?>"></div>
        <label class="chk"><input type="checkbox" name="ref_on" value="1" <?= !empty($C['referral']['on']) ? 'checked' : '' ?>> سیستمِ معرفی روشن باشد</label>
      </div>
      <div style="margin-top:14px"><button class="btn g">ذخیره</button></div>
    </form>
  </div></div>

  <div class="card"><h2>🏆 برترین معرف‌ها</h2>
    <?php if (!$topRefs): ?><div class="empty"><span class="ic">👥</span>هنوز کسی زیرمجموعه نگرفته.</div>
    <?php else: ?><div class="tw"><table>
      <thead><tr><th>#</th><th>معرف</th><th>آیدی</th><th>زیرمجموعه</th><th>موجودی</th></tr></thead>
      <tbody>
      <?php foreach ($topRefs as $i => $r): $ru = pUser($r['id']); ?>
      <tr><td><?= $i + 1 ?></td>
        <td><a href="?tab=users&amp;id=<?= (int)$r['id'] ?>"><?= h(pLabel($r['id'], $ru)) ?></a></td>
        <td><code><?= (int)$r['id'] ?></code></td>
        <td class="num"><b><?= h(fmtNum($r['n'])) ?></b></td>
        <td class="num"><?= h(fmtNum($ru['balance'] ?? 0)) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div><?php endif; ?>
  </div>


<?php elseif ($tab === 'support'):
  $SG = maCfg()['sup'] ?? [];
  $sgChat = trim((string)($SG['chat'] ?? ''));
  $sgOn = !empty($SG['on']) && $sgChat !== ''; ?>
  <div class="card"><h2>💬 تیکت‌ها و چتِ مینی‌اپ <?= $sgOn ? '<span class="badge green">گروه</span>' : '<span class="badge">پیویِ مدیر</span>' ?></h2><div class="body">
    <div class="note">
      پشتیبانیِ مینی‌اپ یک تیکت می‌سازد. اگر گروه بدهید، تیکت‌ها داخلِ همان گروه/تاپیک می‌افتند
      و هر ادمینِ گروه با <b>ریپلای روی تیکت</b> یا دکمه‌ی «💬 پاسخ به کاربر» جواب می‌دهد؛ متن، عکس، ویس یا فایل
      مستقیم به کاربر می‌رسد و داخلِ چتِ مینی‌اپ هم دیده می‌شود. ربات باید در گروه <b>ادمین</b> باشد.
    </div>
    <form method="post" style="margin-top:12px">
      <?= fk('save_sup_group') ?>
      <label class="chk" style="margin-bottom:12px"><input type="checkbox" name="sg_on" value="1" <?= !empty($SG['on']) ? 'checked' : '' ?>> تیکت‌ها به گروه بروند</label>
      <div class="fld"><label>لینکِ تاپیک (ساده‌ترین راه)</label>
        <input name="sg_link" placeholder="https://t.me/c/1234567890/11" style="direction:ltr">
        <div class="hint">روی یکی از پیام‌های همان تاپیک نگه دارید ← Copy Link. گروه و تاپیک با هم پر می‌شوند.</div></div>
      <div class="grid2">
        <div><label>آیدیِ گروه</label><input name="sg_chat" value="<?= h($sgChat) ?>" placeholder="-1001234567890" style="direction:ltr"></div>
        <div><label>شماره‌ی تاپیک (۰ = بدونِ تاپیک)</label><input name="sg_thread" type="number" min="0" value="<?= (int)($SG['thread'] ?? 0) ?>"></div>
      </div>
      <div class="row" style="margin-top:14px">
        <button class="btn g">ذخیره</button>
        <button type="submit" class="btn ghost" form="supGroupTest">🧪 ارسالِ آزمایشی</button>
      </div>
    </form>
    <form method="post" id="supGroupTest" hidden><?= fk('sup_group_test') ?></form>
  </div></div>

  <div class="card"><h2>☎️ دو دکمه‌ی اصلیِ پشتیبانی در ربات</h2><div class="body">
    <div class="note">
      کاربر در ربات فقط <b>دو دکمه</b> می‌بیند: ارتباطِ مستقیم و ارتباطِ غیرمستقیم.<br>
      <b>مستقیم</b> یک لینک است و کاربر را یک‌راست می‌برد. <b>غیرمستقیم</b> فهرستِ پایین را باز می‌کند.
    </div>
    <form method="post">
      <?= fk('save_support') ?>
      <?php foreach (['direct' => '💬 ارتباط مستقیم', 'indirect' => '📨 ارتباط غیر مستقیم'] as $mk => $mlbl):
        $m = $C['support_main'][$mk]; ?>
        <h3 style="font-size:13.5px;margin:<?= $mk === 'direct' ? '0' : '18px' ?> 0 9px"><?= $mlbl ?></h3>
        <div class="grid2">
          <div><label>ایموجی</label><input name="sm_emoji_<?= $mk ?>" value="<?= h($m['emoji']) ?>" style="text-align:center"></div>
          <div><label>متنِ دکمه</label><input name="sm_text_<?= $mk ?>" value="<?= h($m['text']) ?>"></div>
          <div><label>رنگ</label><select name="sm_color_<?= $mk ?>">
            <?php foreach (styleMap() as $sk => $sl): ?>
              <option value="<?= h($sk) ?>" <?= ($m['color'] ?? '') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
            <?php endforeach; ?></select></div>
          <div><label>ایموجی پریمیوم</label><input name="sm_icon_<?= $mk ?>" value="<?= h($m['icon'] ?? '') ?>" style="direction:ltr"></div>
          <?php if ($mk === 'direct'): ?>
          <div style="grid-column:1/-1"><label>لینکِ مقصد</label>
            <input name="sm_value_direct" value="<?= h($m['value']) ?>" placeholder="https://t.me/username" style="direction:ltr"></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <h3 style="font-size:13.5px;margin:20px 0 9px">گزینه‌های زیرِ «ارتباط غیر مستقیم»</h3>
      <div class="tw">
      <div class="ghead" style="grid-template-columns:40px 100px 60px 1fr 1.4fr">
        <div>فعال</div><div>نوع</div><div>ایموجی</div><div>عنوان</div><div>مقدار</div>
      </div>
      <?php foreach ($C['support_methods'] as $i => $m): ?>
      <div class="srow" style="grid-template-columns:40px 100px 60px 1fr 1.4fr">
        <input type="checkbox" name="s_on_<?= $i ?>" value="1" <?= !empty($m['on']) ? 'checked' : '' ?>>
        <select name="s_type_<?= $i ?>">
          <?php foreach (['url' => 'لینک', 'ticket' => 'تیکت', 'text' => 'متن', 'phone' => 'تلفن'] as $tk => $tl): ?>
            <option value="<?= $tk ?>" <?= ($m['type'] ?? '') === $tk ? 'selected' : '' ?>><?= $tl ?></option>
          <?php endforeach; ?>
        </select>
        <input name="s_emoji_<?= $i ?>" value="<?= h($m['emoji'] ?? '') ?>" style="text-align:center">
        <input name="s_label_<?= $i ?>" value="<?= h($m['label'] ?? '') ?>">
        <textarea name="s_value_<?= $i ?>" rows="1" placeholder="https://t.me/… یا متن یا شماره"><?= h($m['value'] ?? '') ?></textarea>
      </div>
      <?php endforeach; ?>
      </div>
      <div style="margin-top:14px"><button class="btn g">ذخیره‌ی پشتیبانی</button></div>
    </form>

    <div class="prev">
      <div class="muted" style="margin-bottom:8px">پیش‌نمایش — چیزی که کاربر می‌بیند:</div>
      <?php foreach (['direct', 'indirect'] as $mk):
        $m = $C['support_main'][$mk];
        $cls = ['primary' => 'pb-b', 'success' => 'pb-g', 'danger' => 'pb-r'][$m['color'] ?? ''] ?? ''; ?>
        <div class="pbtn <?= $cls ?>"><?= h(trim($m['emoji'] . ' ' . $m['text'])) ?></div>
      <?php endforeach; ?>
    </div>
  </div></div>


<?php elseif ($tab === 'apis'):
  $MA  = maCfg();
  $NUM = numCfg();
  $pi  = numProvInfo();
  $set = fn($v) => trim((string)$v) !== '' ? '••••••••  (ست شده)' : 'هنوز ست نشده';
?>
  <div class="card"><h2>🌐 آدرسِ عمومیِ مینی‌اپ <?= trim((string)$MA['base_url']) !== '' ? '<span class="badge green">ست شده</span>' : '<span class="badge">خودکار</span>' ?></h2><div class="body">
    <div class="note">
      آدرسِ کاملِ فایلِ ربات روی دامنه‌ی خودتان (https). دکمه‌ی مینی‌اپ، دکمه‌ی منوی ربات و عکسِ پروفایلِ کاربر
      از همین ساخته می‌شوند. خالی بماند، ربات از آدرسِ وبهوک حدس می‌زند.
      <br>آدرسِ فعلی: <code><?= h(maBaseUrl() ?: '—') ?></code>
      <br>حدسِ این سرور: <code><?= h(baseUrl() . '/bot_master_membership.php') ?></code>
    </div>
    <form method="post" class="row fe" style="margin-top:12px">
      <?= fk('api_base') ?>
      <div style="flex:1;min-width:260px"><label>آدرس (https)</label>
        <input name="base_url" value="<?= h((string)$MA['base_url']) ?>"
               placeholder="https://example.com/bot_master_membership.php" style="direction:ltr"></div>
      <button class="btn g">ذخیره</button>
    </form>
  </div></div>

  <div class="card"><h2>☎️ فروشنده‌ی شماره مجازی <?= !empty($NUM['api']['on'])
      ? (numKey() !== '' ? '<span class="badge green">روشن</span>' : '<span class="badge amber">روشن ولی بی‌کلید</span>')
      : '<span class="badge">خاموش</span>' ?></h2><div class="body">
    <div class="note">
      <b>۵سیم</b> دلاری می‌فروشد (نرخِ تبدیل لازم است)، <b>نامبرلند</b> تومانی.
      «مهلتِ انتظارِ کد» یعنی بعدِ این مدت شماره بسته و پولِ کاربر برگردانده می‌شود.
      <b>کلیدها اگر خالی بفرستید پاک نمی‌شوند.</b>
      <br>کلیدِ <?= h($pi['name']) ?>: <?= h($pi['help']) ?>
    </div>
    <form method="post" style="margin-top:12px">
      <?= fk('api_num') ?>
      <label class="chk" style="margin-bottom:12px"><input type="checkbox" name="on" value="1" <?= !empty($NUM['api']['on']) ? 'checked' : '' ?>> فروش روشن باشد</label>
      <div class="grid2">
        <div><label>فروشنده</label><select name="provider">
          <?php foreach (numProviders() as $pv => $pinfo): ?>
            <option value="<?= h($pv) ?>" <?= $NUM['provider'] === $pv ? 'selected' : '' ?>><?= h($pinfo['label']) ?></option>
          <?php endforeach; ?></select></div>
        <div><label>آدرسِ پایه <small class="muted">(خالی = آدرسِ رسمیِ فروشنده)</small></label>
          <input name="base" value="<?= h((string)$NUM['api']['base']) ?>" style="direction:ltr"></div>
        <div><label>توکنِ ۵سیم <small class="muted">(خالی = دست‌نخورده)</small></label>
          <div class="secretin"><input type="password" name="token" value="" autocomplete="off" placeholder="<?= h($set($NUM['api']['token'])) ?>" style="direction:ltr">
            <button type="button" class="btn ghost sm" onclick="toggleSecret(this)">نمایش</button></div></div>
        <div><label>کلیدِ نامبرلند <small class="muted">(خالی = دست‌نخورده)</small></label>
          <div class="secretin"><input type="password" name="nl_key" value="" autocomplete="off" placeholder="<?= h($set($NUM['api']['nl_key'])) ?>" style="direction:ltr">
            <button type="button" class="btn ghost sm" onclick="toggleSecret(this)">نمایش</button></div></div>
        <div><label>کدِ سرویسِ تلگرام نزدِ نامبرلند</label>
          <input name="nl_svc" value="<?= h((string)$NUM['api']['nl_svc']) ?>" style="direction:ltr"></div>
        <div><label>نرخِ هر دلارِ ۵سیم به تومان <small class="muted">(۰ = از بخشِ 💹 قیمت لحظه‌ای)</small></label>
          <input name="rate" value="<?= h(fmtNum($NUM['api']['rate'])) ?>" inputmode="numeric" style="direction:ltr"></div>
        <div><label>سود روی قیمتِ فروشنده (٪)</label>
          <input name="markup" value="<?= h((string)$NUM['markup']) ?>" inputmode="decimal" style="direction:ltr"></div>
        <div><label>سقفِ قیمتِ هر خرید روی ۵سیم، به دلار <small class="muted">(۰ = بی‌سقف)</small></label>
          <input name="max" value="<?= h((string)$NUM['api']['max']) ?>" inputmode="decimal" style="direction:ltr"></div>
        <div><label>مهلتِ انتظارِ کد (ثانیه)</label>
          <input name="wait" type="number" min="60" value="<?= (int)$NUM['wait'] ?>"></div>
        <div><label>فاصله‌ی دو پرسش از فروشنده (ثانیه)</label>
          <input name="poll" type="number" min="3" value="<?= (int)$NUM['poll'] ?>"></div>
        <div><label>مهلتِ هر تماس (ثانیه)</label>
          <input name="timeout" type="number" min="3" max="60" value="<?= (int)$NUM['api']['timeout'] ?>"></div>
        <label class="chk"><input type="checkbox" name="sync_price" value="1" <?= !empty($NUM['sync_price']) ? 'checked' : '' ?>>
          قیمت از فروشنده: هر بار «وارد کردن»، قیمت‌ها تازه شوند</label>
      </div>
      <div class="row" style="margin-top:14px">
        <button class="btn g">ذخیره</button>
        <button type="submit" class="btn ghost" form="numTest">🧪 خواندنِ موجودیِ حساب</button>
      </div>
    </form>
    <form method="post" id="numTest" hidden><?= fk('api_num_test') ?></form>
  </div></div>


<?php elseif ($tab === 'settings'):
  $secOpen = $sec !== 'all' ? ' open' : '';
  $G = $C['gateway'] ?? [];
  $J = $C['join'] ?? [];
?>
  <div class="crumb">سیستم <span>/</span> <b>تنظیمات</b></div>

  <?php if ($sec !== 'all'): ?>
    <style>.psec{display:none}.psec[data-s~="<?= h($sec) ?>"]{display:block}</style>
    <div class="note" style="margin-bottom:12px">
      فقط بخشِ «<?= h(['speed' => 'تشخیص و سرعت', 'gw' => 'درگاه پرداخت', 'join' => 'عضویت اجباری', 'sec' => 'امنیت'][$sec]) ?>»
      نشان داده می‌شود. <a href="?tab=settings&amp;s=all">نمایشِ همه</a>
    </div>
  <?php else: ?>
  <div class="card"><h2>🧭 کجا چه چیزی را تنظیم کنم؟</h2><div class="body">
    <div class="note">
      <b>هر تنظیم دقیقا یک خانه دارد.</b> هرچه صفحه‌ی بزرگ می‌خواهد (کلید، آدرس، جدول، کاتالوگ) این‌جاست؛
      هرچه وسطِ کار و با گوشی لازم می‌شود، داخلِ خودِ ربات (<code>/panel</code>).
    </div>
    <div class="tw" style="margin-top:12px"><table>
      <tr><th>می‌خواهم…</th><th>خانه‌اش</th></tr>

      <tr class="grp"><td colspan="2">☎️ فروشِ شماره</td></tr>
      <tr><td>فروشنده، کلید، سود و مهلتِ کد</td><td><a href="?tab=apis">API و اتصال‌ها ← ☎️ فروشنده</a></td></tr>
      <tr><td>وارد کردنِ کشورها و قیمت‌ها</td><td><a href="?tab=numbers">کشورها و قیمت‌ها ← 📥 وارد کردن</a></td></tr>
      <tr><td>قیمت، نام، برچسب و روشن/خاموشِ هر شماره</td><td><a href="?tab=numbers">کشورها و قیمت‌ها ← کارتِ همان کشور</a></td></tr>
      <tr><td>دیدنِ سفارش‌ها و لغوِ شماره‌ی باز</td><td><a href="?tab=numorders">سفارش‌های شماره</a></td></tr>
      <tr><td>آدرسِ عمومیِ مینی‌اپ</td><td><a href="?tab=apis">API و اتصال‌ها ← 🌐 آدرس</a></td></tr>

      <tr class="grp"><td colspan="2">💳 پول و کاربران</td></tr>
      <tr><td>تاییدِ رسیدِ شارژ و کمترین مبلغِ شارژ</td><td><a href="?tab=orders">شارژ کیف پول</a></td></tr>
      <tr><td>درگاهِ پرداختِ رمزارز</td><td><a href="?tab=settings&amp;s=gw">همین صفحه ← درگاه پرداخت</a></td></tr>
      <tr><td>موجودی یا مسدود کردنِ یک کاربر</td><td><a href="?tab=users">کاربران ← همان کاربر</a></td></tr>
      <tr><td>درصدِ پورسانتِ رفرال</td><td><a href="?tab=referral">رفرال</a></td></tr>
      <tr><td>گروه و تاپیکِ تیکت‌ها، دکمه‌های پشتیبانی</td><td><a href="?tab=support">پشتیبانی</a></td></tr>
      <tr><td>کانال‌های عضویت اجباری</td><td><a href="?tab=settings&amp;s=join">همین صفحه ← عضویت اجباری</a></td></tr>

      <tr class="grp"><td colspan="2">📱 داخلِ خودِ ربات (<code>/panel</code>)</td></tr>
      <tr><td>باز/بسته کردن، عنوان، شعار و لوگوی مینی‌اپ</td><td>🚀 مینی‌اپ</td></tr>
      <tr><td>شماره کارت و مقصدِ پول</td><td>💳 پرداخت</td></tr>
      <tr><td>کانال‌های گزارشِ خرید و شارژ</td><td>📡 کانال‌های گزارش</td></tr>
      <tr><td>کارتِ عکسِ همراهِ گزارشِ خرید</td><td>📡 کانال‌های گزارش ← 🖼 کارتِ گزارشِ خرید</td></tr>
      <tr><td>متنِ دکمه‌ها، رنگ‌ها، ایموجی پریمیوم</td><td>🎨 ظاهر و متن‌ها</td></tr>
      <tr><td>بازی‌ها و الماس</td><td>🎮 بازی‌ها · 💎 الماس</td></tr>
      <tr><td>قیمتِ لحظه‌ای در گروه</td><td>💹 قیمت لحظه‌ای</td></tr>
      <tr><td>پیامِ همگانی</td><td>📢 پیام همگانی</td></tr>
      <tr><td>سلامتِ همه‌ی بخش‌ها</td><td>🩺 چکاپِ بخش‌ها</td></tr>
    </table></div>
  </div></div>
  <?php endif; ?>

  <details class="card psec" data-s="speed"<?= $secOpen ?>><summary><h2>🩺 تشخیص و سرعت</h2></summary><div class="body">
    <div class="note">هرکدام یک گزارشِ لحظه‌ای می‌سازد و نتیجه بالای همین صفحه می‌آید.</div>
    <div class="row" style="margin-top:12px">
      <form method="post"><?= fk('adm_speed_test', []) ?><button class="btn">⚡ سرعتِ ربات</button></form>
      <form method="post"><?= fk('adm_leak_test', []) ?><button class="btn">🛡 تستِ نشتیِ داده</button></form>
      <form method="post"><?= fk('adm_write_test', []) ?><button class="btn">💾 تستِ نوشتن روی دیسک</button></form>
    </div>
  </div></details>

  <details class="card psec" data-s="gw"<?= $secOpen ?>><summary><h2>💠 درگاه پرداخت خودکار <?= gwOn() ? '<span class="badge green">آماده</span>' : '<span class="badge">خاموش</span>' ?></h2></summary><div class="body">
    <div class="note">
      کاربر «افزایش موجودی» می‌زند ← ربات از درگاه یک <b>لینکِ پرداخت + آدرسِ ولت + مهلت</b> می‌گیرد ←
      به‌محضِ واریز، درگاه به ربات خبر می‌دهد و کیف پول <b>خودکار</b> شارژ می‌شود.
      پول مستقیم به ولتِ خودتان در پنلِ درگاه می‌رود.<br><br>
      <b>راه‌اندازی:</b> در <a href="https://oxapay.com" target="_blank" rel="noopener">OxaPay</a> یا
      <a href="https://nowpayments.io" target="_blank" rel="noopener">NOWPayments</a> حساب بسازید،
      کلیدِ API (Merchant Key) را بگیرید و این‌جا بگذارید؛ بعد در پنلِ همان سایت، آدرسِ <b>Callback / IPN</b> را روی آدرسِ زیر بگذارید.
    </div>
    <?php if (trim((string)($G['base_url'] ?? '')) !== ''): ?>
      <div class="note" style="margin-top:10px">📡 <b>آدرسِ Callback:</b> <code style="direction:ltr;display:inline-block"><?= h(gwCallbackUrl()) ?></code></div>
    <?php endif; ?>

    <form method="post" style="margin-top:12px">
      <?= fk('save_gateway', []) ?>
      <label class="chk" style="margin-bottom:12px"><input type="checkbox" name="gw_on" value="1" <?= !empty($G['on']) ? 'checked' : '' ?>> درگاهِ خودکار روشن باشد</label>
      <div class="grid2">
        <div><label>سرویس</label><select name="gw_prov">
          <?php foreach (['oxapay' => 'OxaPay', 'nowpayments' => 'NOWPayments', 'custom' => 'دلخواه'] as $k2 => $v2): ?>
            <option value="<?= h($k2) ?>" <?= ($G['provider'] ?? 'oxapay') === $k2 ? 'selected' : '' ?>><?= h($v2) ?></option>
          <?php endforeach; ?></select></div>
        <div><label>کلیدِ API (Merchant Key)
          <?= trim((string)($G['api_key'] ?? '')) !== '' ? '<span class="badge green">ثبت شده</span>' : '<span class="badge">ثبت نشده</span>' ?></label>
          <div class="secretin"><input type="password" name="gw_key" autocomplete="off" value=""
            placeholder="<?= trim((string)($G['api_key'] ?? '')) !== '' ? 'ثبت شده — برای تعویض، کلیدِ تازه بگذارید' : 'کلیدِ Merchant' ?>" style="direction:ltr">
            <button type="button" class="btn ghost sm" onclick="toggleSecret(this)">نمایش</button></div></div>
        <div><label>کلیدِ IPN Secret (فقط NOWPayments)
          <?= trim((string)($G['ipn_secret'] ?? '')) !== '' ? '<span class="badge green">ثبت شده</span>' : '<span class="badge">ثبت نشده</span>' ?></label>
          <div class="secretin"><input type="password" name="gw_ipn" autocomplete="off" value=""
            placeholder="<?= trim((string)($G['ipn_secret'] ?? '')) !== '' ? 'ثبت شده — برای تعویض، مقدارِ تازه بگذارید' : 'IPN Secret' ?>" style="direction:ltr">
            <button type="button" class="btn ghost sm" onclick="toggleSecret(this)">نمایش</button></div></div>
        <div><label>آدرسِ عمومیِ فایلِ ربات</label>
          <input name="gw_base" value="<?= h($G['base_url'] ?? '') ?>" placeholder="https://site.com/bot_master_membership.php" style="direction:ltr"></div>
        <div><label>ارز</label><input name="gw_coin" value="<?= h($G['coin'] ?? 'USDT') ?>" style="direction:ltr"></div>
        <div><label>شبکه</label><input name="gw_net" value="<?= h($G['network'] ?? '') ?>" placeholder="TRC20" style="direction:ltr"></div>
        <div><label>نرخ: هر ۱ واحد چند تومان؟ (۰ = تبدیل با خودِ درگاه)</label>
          <input name="gw_rate" value="<?= h((string)(float)($G['rate'] ?? 0)) ?>" style="direction:ltr"></div>
        <div><label>مهلتِ هر فاکتور (دقیقه)</label>
          <input name="gw_exp" type="number" min="5" value="<?= (int)($G['expire'] ?? 30) ?>"></div>
        <div><label>از این مبلغ به بالا با درگاه (تومان)</label>
          <input name="gw_min" value="<?= h((string)(float)($G['min'] ?? 0)) ?>" style="direction:ltr"></div>
        <div><label>آدرسِ دلخواه (حالتِ custom)</label>
          <input name="gw_curl" value="<?= h($G['custom_url'] ?? '') ?>" placeholder="https://…?amount={amount}&order={order}&cb={callback}" style="direction:ltr"></div>
      </div>
      <div class="hint" style="margin-top:8px">
        زیرِ این مبلغ، همان کارت‌به‌کارت با رسید استفاده می‌شود. اگر درگاه جواب ندهد هم خودکار به کارت‌به‌کارت برمی‌گردد.
      </div>
      <div style="margin-top:14px"><button class="btn g">ذخیره‌ی درگاه</button></div>
    </form>
  </div></details>

  <details class="card psec" data-s="join"<?= $secOpen ?>><summary><h2>📣 عضویت اجباری <?= !empty($J['on']) ? '<span class="badge green">روشن</span>' : '<span class="badge">خاموش</span>' ?></h2></summary><div class="body">
    <div class="note">
      تا کاربر عضوِ کانال‌های زیر نشود، نمی‌تواند از ربات و مینی‌اپ استفاده کند.
      ربات باید در هر کانال <b>ادمین</b> باشد. مدیرها هیچ‌وقت پشتِ این قفل نمی‌مانند.
    </div>

    <div class="tw" style="margin-top:12px"><table>
      <tr><th>#</th><th>کانال</th><th>آیدی</th><th>لینک</th><th></th></tr>
      <?php foreach (($J['channels'] ?? []) as $i => $c2): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= h($c2['title'] ?? '—') ?></td>
          <td><code><?= h((string)($c2['chat_id'] ?? '')) ?></code></td>
          <td><?= !empty($c2['url']) ? '<a href="' . h($c2['url']) . '" target="_blank" rel="noopener">باز کردن</a>' : '<span class="muted">—</span>' ?></td>
          <td>
            <form method="post" onsubmit="return confirm('این کانال از عضویت اجباری حذف شود؟')">
              <?= fk('del_join_channel', []) ?><input type="hidden" name="i" value="<?= (int)$i ?>">
              <button class="btn r sm">حذف</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($J['channels'])): ?>
        <tr><td colspan="5" class="muted">هنوز کانالی اضافه نکرده‌اید.</td></tr>
      <?php endif; ?>
    </table></div>

    <form method="post" style="margin-top:12px">
      <?= fk('add_join_channel', []) ?>
      <div class="grid2">
        <div><label>آیدیِ کانال</label><input name="chat_id" required placeholder="@mychannel یا -100..." style="direction:ltr"></div>
        <div><label>عنوان (خالی = از خودِ کانال)</label><input name="title"></div>
        <div><label>لینکِ عضویت (خالی = خودکار)</label><input name="url" style="direction:ltr"></div>
      </div>
      <div style="margin-top:12px"><button class="btn g">افزودنِ کانال</button></div>
    </form>

    <form method="post" style="margin-top:16px">
      <?= fk('save_join', []) ?>
      <label class="chk" style="margin-bottom:10px"><input type="checkbox" name="jn_on" value="1" <?= !empty($J['on']) ? 'checked' : '' ?>> قفلِ عضویت روشن باشد</label>
      <div class="hint">متنِ قفل و متنِ دکمه داخلِ خودِ ربات ویرایش می‌شوند: <code>/panel</code> ← 🎨 ظاهر و متن‌ها ← 🔒 عضویت اجباری</div>
      <div style="margin-top:14px"><button class="btn g">ذخیره</button></div>
    </form>
  </div></details>

  <details class="card psec" data-s="sec"<?= $secOpen ?>><summary><h2>🔐 امنیت</h2></summary><div class="body">
    <p class="muted" style="line-height:2.1">
      • رمزِ پنل: <code>ADMIN_PANEL_PASS</code> در <code>config.local.php</code>
        <?php if (pWeakPass()): ?><span class="badge red">ضعیف است — دست‌کم ۱۲ نویسه با حرف، عدد و نماد</span><?php endif; ?><br>
      • آیدیِ مدیرها: <code><?= h(implode('، ', ADMIN_IDS)) ?></code> — کنارِ BOT_TOKEN در <code>config.local.php</code><br>
      • کلیدِ کران:
        <span class="secret-box">
          <code data-shown="0" data-masked="••••••••••••">••••••••••••</code>
          <button type="button" class="btn ghost sm" onclick="revealBox(this, <?= h(json_encode(CRON_KEY)) ?>)">👁 نمایش</button>
          <button type="button" class="btn ghost sm" onclick="copyText(<?= h(json_encode(CRON_KEY)) ?>, this)">کپی</button>
        </span>
        — <code>CRON_KEY</code> در <code>config.local.php</code><br>
      • پوشه‌ی <code>data_master/</code> تنظیمات، کلیدها و دیتابیس‌ها را دارد؛ دسترسیِ عمومی به آن را ببندید.<br>
      • فایلِ <code>setup.php</code>:
        <?= is_file(__DIR__ . '/setup.php') ? '<span class="badge red">هنوز روی هاست است — بعد از راه‌اندازی پاکش کنید</span>' : '<span class="badge green">پاک شده</span>' ?>
    </p>
  </div></details>

<?php endif; ?>


  </div></main>
</div>

<script>
function toggleSecret(btn) {
  var inp = btn.previousElementSibling;
  if (!inp) return;
  inp.type = inp.type === 'password' ? 'text' : 'password';
  btn.textContent = inp.type === 'password' ? 'نمایش' : 'مخفی';
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

document.querySelectorAll('form').forEach(function (f) {
  f.addEventListener('submit', function (ev) {
    if (ev.defaultPrevented || f.method.toLowerCase() !== 'post') return;
    var btn = ev.submitter || f.querySelector('button:not([type]), button[type="submit"]');
    if (!btn || btn.disabled) return;
    setTimeout(function () { btn.disabled = true; btn.textContent = 'در حال انجام…'; }, 0);
  });
});

(function () {
  var box = document.getElementById('pq'), out = document.getElementById('pres');
  if (!box || !out) return;
  var PAGES = [];
  document.querySelectorAll('.sidebar .navlink').forEach(function (a) {
    PAGES.push({ t: (a.querySelector('span') || {}).textContent || a.textContent, h: a.getAttribute('href'), i: (a.querySelector('svg') || {}).outerHTML || '' });
  });
  document.querySelectorAll('.sidebar .subnav a').forEach(function (a) {
    var g = a.closest('.navgroup').querySelector('.navlink.on span');
    PAGES.push({ t: (g ? g.textContent + ' — ' : '') + a.textContent, h: a.getAttribute('href'), i: '' });
  });
  var hi = -1;
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function draw(list) {
    hi = -1;
    if (!box.value.trim()) { out.hidden = true; return; }
    out.innerHTML = list.length
      ? list.map(function (p, n) { return '<a href="' + esc(p.h) + '" data-n="' + n + '">' + p.i + '<span>' + esc(p.t) + '</span></a>'; }).join('')
      : '<div class="none">چیزی پیدا نشد</div>';
    out.hidden = false;
  }
  function run() {
    var q = box.value.trim().toLowerCase();
    if (!q) return draw([]);
    draw(PAGES.filter(function (p) { return p.t.toLowerCase().indexOf(q) !== -1; }).slice(0, 8));
  }
  box.addEventListener('input', run);
  box.addEventListener('focus', run);
  box.addEventListener('keydown', function (e) {
    var links = out.querySelectorAll('a');
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault(); if (!links.length) return;
      hi = (hi + (e.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
      links.forEach(function (l, n) { l.classList.toggle('hi', n === hi); });
    } else if (e.key === 'Enter') {
      if (!links.length) return;
      e.preventDefault();
      location.href = links[hi >= 0 ? hi : 0].getAttribute('href');
    } else if (e.key === 'Escape') { box.value = ''; out.hidden = true; box.blur(); }
  });
  document.addEventListener('click', function (e) { if (!e.target.closest('.psearch')) out.hidden = true; });
  document.addEventListener('keydown', function (e) {
    if (e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) { e.preventDefault(); box.focus(); }
  });
})();

(function () {
  try { if (localStorage.getItem('nsNav') === '0') document.body.classList.add('nav-collapsed'); } catch (e) {}
  var btn = document.querySelector('header.top .iconbtn.m-only');
  if (!btn) return;
  btn.addEventListener('click', function (e) {
    if (window.matchMedia('(min-width:901px)').matches) {
      e.preventDefault();
      document.body.classList.toggle('nav-collapsed');
      try { localStorage.setItem('nsNav', document.body.classList.contains('nav-collapsed') ? '0' : '1'); } catch (e2) {}
    }
  });
})();

(function () {
  var t = location.hash && document.getElementById(location.hash.slice(1));
  if (t && t.tagName === 'DETAILS') { t.open = true; t.scrollIntoView({ block: 'start' }); }
})();
</script>

</body>
</html>
