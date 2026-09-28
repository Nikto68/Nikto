<?php

if (is_file(__DIR__ . '/config.local.php')) require_once __DIR__ . '/config.local.php';

if (!defined('BOT_TOKEN')) define('BOT_TOKEN', (string)getenv('BOT_TOKEN'));
if (!defined('ADMIN_ID'))  define('ADMIN_ID',  (int)getenv('ADMIN_ID'));
if (!defined('ADMIN_IDS')) define('ADMIN_IDS', ADMIN_ID > 0 ? [ADMIN_ID] : []);

function isAdmin($uid) {
    if (is_string($uid)) {
        if (!preg_match('~^\d{1,19}$~', trim($uid))) return false;
    } elseif (!is_int($uid)) {
        return false;
    }
    $id = (int)$uid;
    if ($id <= 0) return false;
    return in_array($id, array_map('intval', ADMIN_IDS), true);
}

function notifyAdmins($text, $kb = null, $extra = []) {
    $sent = false;
    foreach (ADMIN_IDS as $aid) {
        $r = sendMsg(BOT_TOKEN, $aid, $text, $kb, $extra);
        if (!empty($r['ok'])) $sent = true;
    }
    return $sent;
}

if (!defined('DATA_DIR'))
    define('DATA_DIR',  getenv('DATA_DIR') ?: __DIR__ . '/data_master');
if (!defined('CRON_KEY'))
    define('CRON_KEY',  getenv('CRON_KEY') ?: '');

if (!defined('WEBHOOK_SECRET'))
    define('WEBHOOK_SECRET', getenv('WEBHOOK_SECRET') ?: '');

function webhookSecretOk() {
    if (WEBHOOK_SECRET === '') {
        if (function_exists('adminAlertOnce')) {
            adminAlertOnce('webhook_secret_missing',
                "🔴 WEBHOOK_SECRET تنظیم نشده — تا وقتی تنظیم نشود، هیچ آپدیتی از تلگرام پذیرفته نمی‌شود.\n" .
                "در config.local.php مقدارش را بگذارید، بعد یک‌بار «تنظیم وبهوک» را از پنل یا ربات بزنید.", 3600);
        }
        return false;
    }
    $got = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    return is_string($got) && $got !== '' && hash_equals(WEBHOOK_SECRET, $got);
}

function ssrfSafeUrl($url, &$reason = null) {
    $url = trim((string)$url);
    $p = parse_url($url);
    if (!$p || empty($p['scheme']) || empty($p['host']) || !in_array(strtolower($p['scheme']), ['http', 'https'], true)) {
        $reason = 'آدرس نامعتبر است';
        return false;
    }
    $host = $p['host'];
    $ips = [];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips = [$host];
    } else {
        $ips = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
        $ips = array_filter(array_map(fn($r) => $r['ip'] ?? ($r['ipv6'] ?? null), $ips));
        if (!$ips) $ips = [gethostbyname($host)];
    }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) continue;
        $isPublic = filter_var($ip, FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        if ($isPublic === false || $ip === '169.254.169.254') {
            $reason = 'این آدرس به شبکه‌ی داخلی/محلی اشاره می‌کند — اجازه‌ی درخواست به آن نیست';
            return false;
        }
    }
    return true;
}

if (!class_exists('SQLite3')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("افزونه‌ی SQLite3 در PHP این هاست خاموش است.\n\n" .
         "کاربرانِ ربات (موجودی، رفرال، سفارش) روی این افزونه ذخیره می‌شوند —\n" .
         "بدونش ربات بالا نمی‌آید تا داده‌ی کسی گم نشود.\n\n" .
         "در cPanel: Select PHP Version ← Extensions ← تیکِ sqlite3 را بزنید ← Save.\n" .
         "چیزی دستی ساخته نمی‌شود؛ فایلِ دیتابیس را خودِ ربات داخلِ data_master می‌سازد.\n" .
         "بعد همین صفحه را دوباره باز کنید.\n");
}

if (!defined('MEMBERSHIP_LIB_ONLY') && (BOT_TOKEN === '' || ADMIN_ID <= 0)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("پیکربندی ناقص است.\n\n" .
         "کنار همین فایل یک config.local.php بسازید:\n\n" .
         "<?php\n" .
         "define('BOT_TOKEN', 'توکن ربات از BotFather');\n" .
         "define('ADMIN_ID', 123456789);\n" .
         "define('CRON_KEY', 'یک رشته تصادفی بلند');\n" .
         "define('ADMIN_PANEL_PASS', 'رمز پنل وب');\n" .
         "define('HEALTH_KEY', 'یک رشته تصادفی بلند دیگر');\n");
}

if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0755, true);
dataDirLockdown();

function dataDirLockdown() {
    $d = rtrim(DATA_DIR, '/');
    if (!is_dir($d)) return;

    $files = [
        '.htaccess' => "Require all denied\n" .
                       "<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n",
        'web.config' => '<?xml version="1.0"?><configuration><system.webServer><security>' .
                        '<authorization><deny users="*" /></authorization>' .
                        '</security></system.webServer></configuration>',
        'index.html' => '',
    ];
    foreach ($files as $name => $content) {
        $f = $d . '/' . $name;
        if (!file_exists($f)) @file_put_contents($f, $content);
    }
}

@ignore_user_abort(true);

require_once __DIR__ . '/miniapps.php';
require_once __DIR__ . '/numbers.php';
require_once __DIR__ . '/prices.php';
require_once __DIR__ . '/diamond.php';
require_once __DIR__ . '/channels.php';
require_once __DIR__ . '/games.php';
require_once __DIR__ . '/airdrop.php';
require_once __DIR__ . '/coupons.php';
require_once __DIR__ . '/bank.php';
require_once __DIR__ . '/mine.php';

migrateOnce('vault_refund', function () {
    $f = DATA_DIR . '/vault.sqlite';
    if (!is_file($f) || !class_exists('SQLite3')) return;
    if (!function_exists('gmAdd')) throw new RuntimeException('gmAdd هنوز بارگذاری نشده');
    $db = new SQLite3($f);
    $db->busyTimeout(5000);
    $res = @$db->query('SELECT id, locked FROM vault_users WHERE locked > 0');
    if (!$res) { $db->close(); return; }
    $n = 0; $sum = 0.0;
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $uid = (int)$row['id'];
        $amt = (float)$row['locked'];
        if ($uid <= 0 || $amt <= 0) continue;
        if (function_exists('gmAdd') && gmAdd($uid, $amt)) {
            $up = $db->prepare('UPDATE vault_users SET locked = 0 WHERE id = :i');
            $up->bindValue(':i', $uid, SQLITE3_INTEGER);
            $up->execute();
            $n++; $sum += $amt;
        }
    }
    $db->close();
    if ($n > 0) error_log(sprintf('[shop-bot] بانکِ الماسی بسته شد — %s الماس به %d کیف‌پول برگشت.', number_format($sum), $n));
});

migrateOnce('arcade_refund', function () {
    $f = DATA_DIR . '/arcade_games.sqlite';
    if (!is_file($f) || !class_exists('SQLite3')) return;
    if (!function_exists('gmAdd')) throw new RuntimeException('gmAdd هنوز بارگذاری نشده');

    $db = new SQLite3($f);
    $db->busyTimeout(5000);
    $res = @$db->query("SELECT id, data FROM arcade_games WHERE status IN ('open','playing')");
    if (!$res) { $db->close(); return; }
    $n = 0; $sum = 0.0;
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $g = json_decode((string)$row['data'], true);
        if (!is_array($g)) continue;
        $stake = (float)($g['stake'] ?? 0);
        if ($stake <= 0) continue;
        foreach ([(int)($g['host'] ?? 0), (int)($g['guest'] ?? 0)] as $who) {
            if ($who <= 0) continue;
            if (gmAdd($who, $stake)) { $n++; $sum += $stake; }
        }
        $up = $db->prepare("UPDATE arcade_games SET status = 'cancelled' WHERE id = :i");
        $up->bindValue(':i', (string)$row['id'], SQLITE3_TEXT);
        $up->execute();
    }
    $db->close();
    if ($n > 0) error_log(sprintf('[shop-bot] بازی‌های سنگ‌کاغذقیچی/بسکتبال بسته شدند — %s الماس به %d نفر برگشت.', number_format($sum), $n));
});
migrateOnce('numbers_only', function () {
    cfgSet(function (&$c) {
        $old = is_array($c['miniapps'] ?? null) ? $c['miniapps'] : [];
        if (is_array($old['apps'] ?? null) || !isset($old['items'])) {
            $num = is_array($old['apps']['num'] ?? null) ? $old['apps']['num'] : [];
            $m = maDefaultConfig();
            foreach (['base_url', 'init_max_age', 'rate_ip', 'rate_user', 'trust_proxy'] as $k)
                if (array_key_exists($k, $old)) $m[$k] = $old[$k];
            if (is_array($old['sup'] ?? null)) $m['sup'] = array_replace($m['sup'], $old['sup']);
            if (array_key_exists('on', $num)) $m['on'] = !empty($num['on']);
            foreach (['title', 'note'] as $k)
                if (trim((string)($num[$k] ?? '')) !== '') $m[$k] = (string)$num[$k];
            $keep = array_flip(['id', 'cat', 'svc', 'prov', 'emoji', 'name', 'price', 'badge', 'on', 'order']);
            $m['cats'] = array_values(array_filter((array)($num['cats'] ?? []), 'is_array'));
            foreach ((array)($num['items'] ?? []) as $it)
                if (is_array($it)) $m['items'][] = array_intersect_key($it, $keep);
            $c['miniapps'] = $m;
        }

        foreach (['tariff', 'orders_list', 'sales', 'smm', 'uploader', 'campaign_keep_days', 'ops', 'profit',
                  'gift_rent', 'test_mode', 'auto_approve', 'currency', 'arcade', 'vault', 'factory', 'reactions'] as $k)
            unset($c[$k]);
        foreach (['product_layout', 'show_dot', 'speed_show_perday'] as $k) unset($c['ui'][$k]);
        unset($c['wallets']['usdt'], $c['wallets']['trx']);
        foreach (['buy', 'redeliver', 'full', 'wallet_pay', 'direct_pay', 'enter_bot', 'get_link', 'did_admin',
                  'sup_direct', 'sup_indirect'] as $k) unset($c['ui_texts'][$k], $c['ui_icons'][$k], $c['ui_colors'][$k]);
        foreach (['support', 'join', 'joined', 'upload'] as $k) unset($c['glass_colors'][$k]);
        foreach (['buy_empty', 'buy_head', 'pay_info', 'approved', 'quote_hint', 'order_done', 'order_status',
                  'track_ask', 'track_bad', 'flow_link', 'flow_link_bad', 'flow_qty', 'flow_qty_bad', 'flow_speed',
                  'flow_rate', 'flow_addbot', 'flow_admin_ok', 'flow_admin_no', 'flow_invoice'] as $k)
            unset($c['texts'][$k]);
        $was = ['welcome' => '7fedf8364276b899d04de2e2a43af799', 'account' => '5a9a818d63a9a2cde1ee7a62c6939a6a',
                'orders_empty' => 'c0c5d24b4415e57de7a3e169d2b4aa90', 'orders_head' => '37038608f21e5e4e58637e3a83d6fde6',
                'orders_item' => '2e6534dd4cd520e491dc19ade840871f', 'rejected' => 'c39dae5d77fd4dc840344ecd09b46a2b'];
        foreach ($was as $k => $sum)
            if (isset($c['texts'][$k]) && md5((string)$c['texts'][$k]) === $sum) unset($c['texts'][$k]);

        foreach ((array)($c['buttons'] ?? []) as $id => $b) {
            if (!is_array($b)) continue;
            unset($b['subs'], $b['subs_mode'], $b['carousel_texts'], $b['carousel'], $b['sub_layout'], $b['dot']);
            $dead = ($b['action'] ?? '') === 'product'
                 || (($b['action'] ?? '') === 'text' && str_starts_with((string)$id, 'c_')
                     && in_array(trim(strip_tags((string)($b['value'] ?? ''))),
                                 ['', 'متن این دکمه را تنظیم کنید.', 'این متن را از پنل عوض کنید.'], true));
            if ($dead && str_starts_with((string)$id, 'c_')) { unset($c['buttons'][$id]); continue; }
            if (($b['action'] ?? '') === 'product') { $b['action'] = ''; unset($b['value']); }
            $c['buttons'][$id] = $b;
        }
        foreach (['buy' => ['🛒', 'خرید محصول', '☎️', 'خرید شماره مجازی'],
                  'orders' => ['📊', 'پیگیری سفارش', '📊', 'شماره‌های من']] as $id => [$e0, $t0, $e1, $t1]) {
            if (!isset($c['buttons'][$id])) continue;
            if (($c['buttons'][$id]['emoji'] ?? '') === $e0 && ($c['buttons'][$id]['text'] ?? '') === $t0) {
                $c['buttons'][$id]['emoji'] = $e1;
                $c['buttons'][$id]['text']  = $t1;
            }
        }

        if (is_array($c['channels'] ?? null)) {
            foreach (array_keys($c['channels']) as $k)
                if (!in_array($k, ['topup', 'mini_num', 'tech', 'ticket'], true)) unset($c['channels'][$k]);
            if (is_string($c['channels']['mini_num']['text'] ?? null))
                $c['channels']['mini_num']['text'] = preg_replace('/^[^\n]*\{qty\}[^\n]*\n?/mu', '',
                                                                  $c['channels']['mini_num']['text']);
        }

        if (is_array($c['diamond']['gift'] ?? null)) {
            if (($c['diamond']['gift']['app'] ?? 'num') !== 'num') {
                $c['diamond']['gift']['item'] = '';
                $c['diamond']['gift']['on'] = false;
            }
            unset($c['diamond']['gift']['app']);
            $ok = (string)($c['diamond']['texts']['gift_ok'] ?? '');
            if (str_contains($ok, '🛍 {item}')) unset($c['diamond']['texts']['gift_ok']);
        }
    });
});

require_once __DIR__ . '/quiz.php';
require_once __DIR__ . '/translate.php';
require_once __DIR__ . '/services.php';
require_once __DIR__ . '/bankcard.php';

migrateOnce('rand_refund', function () {
    $f = DATA_DIR . '/games.sqlite';
    if (!is_file($f) || !class_exists('SQLite3')) return;
    if (!function_exists('gmAdd')) throw new RuntimeException('gmAdd هنوز بارگذاری نشده');

    $db = new SQLite3($f);
    $db->busyTimeout(5000);
    $res = @$db->query("SELECT id, data FROM games WHERE status IN ('open','playing')");
    if (!$res) { $db->close(); return; }
    $n = 0; $sum = 0.0;
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $g = json_decode((string)$row['data'], true);
        if (!is_array($g) || ($g['kind'] ?? '') !== 'rand') continue;
        $stake = (float)($g['stake'] ?? 0);
        foreach ((array)($g['players'] ?? []) as $pl) {
            $pid = (int)($pl['id'] ?? 0);
            if ($pid > 0 && $stake > 0 && gmAdd($pid, $stake)) { $n++; $sum += $stake; }
        }
        $up = $db->prepare("UPDATE games SET status = 'cancelled' WHERE id = :i");
        $up->bindValue(':i', (string)$row['id'], SQLITE3_TEXT);
        $up->execute();
    }
    $db->close();
    if ($n > 0) error_log(sprintf('[shop-bot] قرعه‌کشی حذف شد — %s الماس به %d شرکت‌کننده برگشت.', number_format($sum), $n));
});

migrateOnce('factory_payout', function () {
    $f = DATA_DIR . '/factory_users.sqlite';
    if (!is_file($f) || !class_exists('SQLite3')) return;
    if (!function_exists('gmAdd')) throw new RuntimeException('gmAdd هنوز بارگذاری نشده');

    $db = new SQLite3($f);
    $db->busyTimeout(5000);
    $res = @$db->query('SELECT id, data FROM factory_users');
    if (!$res) { $db->close(); return; }
    $n = 0; $sum = 0.0;
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $u = json_decode((string)$row['data'], true);
        if (!is_array($u)) continue;
        $stored = (float)($u['stored'] ?? 0);
        $uid    = (int)$row['id'];
        if ($uid <= 0 || $stored <= 0) continue;
        if (gmAdd($uid, $stored)) {
            $u['stored'] = 0.0;
            $up = $db->prepare('UPDATE factory_users SET data = :d WHERE id = :i');
            $up->bindValue(':d', json_encode($u, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
            $up->bindValue(':i', $uid, SQLITE3_INTEGER);
            $up->execute();
            $n++; $sum += $stored;
        }
    }
    $db->close();
    if ($n > 0) error_log(sprintf('[shop-bot] کارخانه بسته شد — %s الماسِ انبار به %d کیف‌پول ریخت.', number_format($sum), $n));
});

foreach (['fonts', 'toplist'] as $__m) {
    $__p = __DIR__ . '/' . $__m . '.php';
    if (is_file($__p)) require_once $__p;
    else error_log('[shop-bot] ماژولِ ' . $__m . '.php روی سرور نیست — آن بخش خاموش می‌ماند.');
}
unset($__m, $__p);

function dataPath($file) { return DATA_DIR . '/' . $file . '.json'; }

function dataCache($file, $mode = 'has', $data = null, $raw = null) {
    static $v = [], $r = [];
    switch ($mode) {
        case 'has': return array_key_exists($file, $v);
        case 'get': return $v[$file] ?? [];
        case 'raw': return $r[$file] ?? null;
        case 'put': $v[$file] = $data; $r[$file] = $raw; return $data;
        case 'drop': unset($v[$file], $r[$file]); return null;
    }
    return null;
}

function load($file, $fresh = false) {
    if (!$fresh && dataCache($file, 'has')) return dataCache($file, 'get');

    $path = dataPath($file);
    if (!is_file($path)) return dataCache($file, 'put', [], '');
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') return dataCache($file, 'put', [], '');

    if (dataCache($file, 'raw') === $raw) return dataCache($file, 'get');

    $out = json_decode($raw, true);
    return dataCache($file, 'put', is_array($out) ? $out : [], $raw);
}

function save($file, $data) {
    $path = dataPath($file);
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;

    if (dataCache($file, 'raw') === $json && is_file($path)) {
        dataCache($file, 'put', $data, $json);
        return true;
    }

    $tmp = $path . '.' . getmypid() . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    $ok = rename($tmp, $path);
    if ($ok) {
        dataCache($file, 'put', $data, $json);

        if (function_exists('maForget')) maForget();
    }
    return $ok;
}

function mutate($file, callable $fn) {
    $lockPath = dataPath($file) . '.lock';
    $dir = dirname($lockPath);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fp = @fopen($lockPath, 'c');
    if ($fp) {
        flock($fp, LOCK_EX);
    } else {
        static $warned = [];
        if (empty($warned[$file])) {
            $warned[$file] = true;
            error_log('[shop-bot] قفل ساخته نشد (پوشه‌ی داده قابل نوشتن نیست؟): ' . $lockPath);
        }
    }
    $data   = load($file, true);
    $result = $fn($data);
    save($file, $data);
    if ($fp) { flock($fp, LOCK_UN); fclose($fp); }
    return $result;
}

function uid($p) { return $p . '_' . base_convert((string)time(), 10, 36) . bin2hex(random_bytes(3)); }
function h($s)      { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function nowStr()   { return date('Y-m-d H:i:s'); }
function fmtNum($n) { return rtrim(rtrim(number_format((float)$n, 2, '.', ','), '0'), '.'); }

if (!defined('TG_API_BASE')) define('TG_API_BASE', 'https://api.telegram.org');

function tg($token, $method, $data = [], $timeout = 20) {
    if (function_exists('__tgHook')) return __tgHook($token, $method, $data);

    $hasFile = false;
    foreach ($data as $v) if ($v instanceof CURLFile) { $hasFile = true; break; }

    $ch = curl_init(TG_API_BASE . "/bot{$token}/{$method}");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $hasFile ? $data : http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(3, (int)$timeout) * ($hasFile ? 3 : 1),
        CURLOPT_CONNECTTIMEOUT => min(10, max(3, (int)$timeout)),
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) return ['ok' => false, 'description' => 'curl error: ' . $err];
    $out = json_decode($res, true);
    return is_array($out) ? $out : ['ok' => false, 'description' => 'bad response'];
}

function tgMulti($token, $method, array $items, $timeout = 6) {
    if (!$items) return [];
    if (function_exists('__tgHook') || !function_exists('curl_multi_init')) {
        $out = [];
        foreach ($items as $k => $d) $out[$k] = tg($token, $method, $d, $timeout);
        return $out;
    }

    $url = TG_API_BASE . "/bot{$token}/{$method}";
    $mh  = curl_multi_init();
    $hs  = [];
    foreach ($items as $k => $d) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($d),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => max(3, (int)$timeout),
            CURLOPT_CONNECTTIMEOUT => min(10, max(3, (int)$timeout)),
        ]);
        curl_multi_add_handle($mh, $ch);
        $hs[$k] = $ch;
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 0.5);
    } while ($running > 0);

    $out = [];
    foreach ($hs as $k => $ch) {
        $res = curl_multi_getcontent($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        $j = is_string($res) ? json_decode($res, true) : null;
        $out[$k] = is_array($j) ? $j : ['ok' => false, 'description' => 'curl error'];
    }
    curl_multi_close($mh);
    return $out;
}

function cleanRows($rows) {
    $out = [];
    foreach ($rows as $row) {
        if (empty($row)) continue;
        $line = [];
        foreach ($row as $btn) {
            if (!is_array($btn) || empty($btn['text'])) continue;
            $line[] = array_filter($btn, fn($v) => $v !== null && $v !== '');
        }
        if ($line) $out[] = $line;
    }
    return $out;
}

function inlineKb($rows) {
    $rows = cleanRows($rows);
    return $rows ? ['inline_keyboard' => $rows] : null;
}

function txMeta() {
    return [
        'g' => ['📢 پیام‌های داخلِ گروه', 'هرچه ربات توی گروه می‌فرستد یا ویرایش می‌کند — همه می‌بینند.'],
        'p' => ['⚠️ هشدارهای کلیکی',    'پنجره‌ی کوچکی که فقط خودِ کلیک‌کننده می‌بیند.'],
        'b' => ['🔘 برچسبِ دکمه‌ها',      'متنی که روی دکمه‌های شیشه‌ای می‌نشیند.'],
    ];
}

function txSplit($cfg) {
    $out = ['g' => [], 'p' => [], 'b' => []];
    $popup = (array)($cfg['popup'] ?? []);
    $btns  = (array)($cfg['btns']  ?? []);
    foreach ((array)($cfg['keys'] ?? []) as $k) {
        if (in_array($k, $btns, true))       $out['b'][] = $k;
        elseif (in_array($k, $popup, true))  $out['p'][] = $k;
        else                                 $out['g'][] = $k;
    }
    return $out;
}

function txHome($cfg, $chatId, $msgId) {
    $split = txSplit($cfg);
    $t  = '✏️ <b>' . $cfg['title'] . "</b>\n\n";
    $t .= "کدام دسته را می‌خواهی ویرایش کنی؟\n\n";
    $rows = [];
    foreach (txMeta() as $sec => [$name, $desc]) {
        $n = count($split[$sec]);
        if (!$n) continue;
        $t .= '• <b>' . $name . "</b> (<b>{$n}</b>)\n   " . $desc . "\n\n";
        $rows[] = [btnCb($name . ' — ' . $n, $cfg['cb'] . $sec . '0', 'admin')];
    }
    $rows[] = [btnCb(UT('back'), $cfg['back'], 'nav')];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function txList($cfg, $chatId, $msgId, $sec, $page = 0) {
    $meta = txMeta();
    if (!isset($meta[$sec])) { txHome($cfg, $chatId, $msgId); return; }
    $keys = txSplit($cfg)[$sec];
    if (!$keys) { txHome($cfg, $chatId, $msgId); return; }

    $per   = 10;
    $tot   = max(1, (int)ceil(count($keys) / $per));
    $page  = max(0, min($tot - 1, (int)$page));
    $slice = array_slice($keys, $page * $per, $per);

    $label = $cfg['label'];
    $value = $cfg['value'];

    $t  = $meta[$sec][0] . ' — <b>' . $cfg['title'] . "</b>\n";
    $t .= '<i>' . $meta[$sec][1] . "</i>\n";
    if ($tot > 1) $t .= 'صفحه ' . ($page + 1) . " از {$tot}\n";
    $t .= "\nهرچه بنویسی عینا همان می‌رود: ایموجیِ پریمیوم و quote سالم می‌مانند.\n\n";

    $rows = [];
    foreach ($slice as $k) {
        $v = trim(str_replace("\n", ' ', strip_tags((string)$value($k))));
        $t .= '• <b>' . h($label($k)) . '</b>: <code>' . h(mb_substr($v, 0, 34)) . "</code>\n";
        $rows[] = [btnCb($label($k), $cfg['edit'] . $k, 'admin')];
    }
    $nav = [];
    if ($page > 0)        $nav[] = btnCb('◀️', $cfg['cb'] . $sec . ($page - 1), 'nav');
    if ($page < $tot - 1) $nav[] = btnCb('▶️', $cfg['cb'] . $sec . ($page + 1), 'nav');
    if ($nav) $rows[] = $nav;
    $rows[] = [btnCb('🗂 دسته‌ها', $cfg['cb'] . 'home', 'nav'), btnCb(UT('back'), $cfg['back'], 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3800), inlineKb($rows));
}

function txRoute($cfg, $data, $chatId, $msgId, $cbId) {
    $pre = (string)$cfg['cb'];
    if (!str_starts_with((string)$data, $pre)) return false;
    $rest = substr((string)$data, strlen($pre));
    if ($rest === 'home') { answerCb(BOT_TOKEN, $cbId); txHome($cfg, $chatId, $msgId); return true; }
    if (preg_match('/^([gpb])(\d+)$/', $rest, $m)) {
        answerCb(BOT_TOKEN, $cbId); txList($cfg, $chatId, $msgId, $m[1], (int)$m[2]); return true;
    }
    if (preg_match('/^\d+$/', $rest)) { answerCb(BOT_TOKEN, $cbId); txHome($cfg, $chatId, $msgId); return true; }
    return false;
}

function menuKb($rows) {
    $rows = cleanRows($rows);
    if (!$rows) return ['remove_keyboard' => true];
    $kb = [
        'keyboard' => $rows,
        'resize_keyboard' => true,
        'input_field_placeholder' => cfg()['ui']['placeholder'] ?? '',
    ];
    if (!empty(cfg()['ui']['persistent'])) $kb['is_persistent'] = true;
    if ($kb['input_field_placeholder'] === '') unset($kb['input_field_placeholder']);
    return $kb;
}

function isStyleError($res) {
    $d = strtolower($res['description'] ?? '');
    if ($d === '') return false;
    return str_contains($d, 'style')
        || str_contains($d, 'icon_custom_emoji_id')
        || str_contains($d, 'button_text_empty')
        || str_contains($d, 'button text is empty');
}

if (!defined('BTN_BLANK')) define('BTN_BLANK', "\u{2060}");

function textIsOnlyEmoji($t) {
    $t = trim((string)$t);
    return $t !== '' && !preg_match('/[\p{L}\p{N}]/u', $t);
}

function kbHideDupEmoji($markup) {
    if (!is_array($markup)) return $markup;
    foreach (['inline_keyboard', 'keyboard'] as $k) {
        if (empty($markup[$k]) || !is_array($markup[$k])) continue;
        foreach ($markup[$k] as $i => $row) {
            if (!is_array($row)) continue;
            foreach ($row as $j => $btn) {
                if (!is_array($btn)) continue;
                if (trim((string)($btn['icon_custom_emoji_id'] ?? '')) === '') continue;
                if (!textIsOnlyEmoji($btn['text'] ?? '')) continue;
                $markup[$k][$i][$j]['text'] = BTN_BLANK;
            }
        }
    }
    return $markup;
}

function kbJson($markup) {
    return json_encode(kbHideDupEmoji($markup));
}

function sendMsg($token, $chatId, $text, $markup = null, $extra = []) {
    $data = array_merge([
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => 'true',
    ], $extra);
    if ($markup) $data['reply_markup'] = is_string($markup) ? $markup : kbJson($markup);
    $res = tg($token, 'sendMessage', $data);
    if (empty($res['ok']) && $markup && !is_string($markup) && isStyleError($res)) {
        $data['reply_markup'] = json_encode(stripStyles($markup));
        $res = tg($token, 'sendMessage', $data);
    }
    return $res;
}

function editMsg($token, $chatId, $msgId, $text, $markup = null) {
    $data = [
        'chat_id' => $chatId, 'message_id' => $msgId, 'text' => $text,
        'parse_mode' => 'HTML', 'disable_web_page_preview' => 'true',
    ];
    if ($markup) $data['reply_markup'] = is_string($markup) ? $markup : kbJson($markup);
    $res = tg($token, 'editMessageText', $data);
    if (empty($res['ok']) && $markup && !is_string($markup) && isStyleError($res)) {
        $data['reply_markup'] = json_encode(stripStyles($markup));
        $res = tg($token, 'editMessageText', $data);
    }
    if (empty($res['ok']) && !isNotModified($res)) sendMsg($token, $chatId, $text, $markup);
    return $res;
}

function isNotModified($res) {
    $d = strtolower((string)($res['description'] ?? ''));
    return $d !== '' && str_contains($d, 'not modified');
}

function editKb($token, $chatId, $msgId, $markup = null) {
    $data = ['chat_id' => $chatId, 'message_id' => $msgId];
    if ($markup) $data['reply_markup'] = is_string($markup) ? $markup : kbJson($markup);
    $res = tg($token, 'editMessageReplyMarkup', $data);
    if (empty($res['ok']) && $markup && !is_string($markup) && isStyleError($res)) {
        $data['reply_markup'] = json_encode(stripStyles($markup));
        $res = tg($token, 'editMessageReplyMarkup', $data);
    }
    return $res;
}

function plainAlert($text) {
    $t = (string)$text;
    if ($t === '') return '';
    $t = preg_replace('~<br\s*/?>|</(p|div|blockquote|pre)>~i', "\n", $t);
    $t = strip_tags($t);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace("/[ \t]+/u", ' ', $t);
    $t = preg_replace("/\n{3,}/u", "\n\n", $t);
    $t = trim($t);
    return mb_strlen($t, 'UTF-8') > 200 ? mb_substr($t, 0, 199, 'UTF-8') . '…' : $t;
}

function answerCb($token, $cbId, $text = '', $alert = false) {
    return tg($token, 'answerCallbackQuery', [
        'callback_query_id' => $cbId, 'text' => plainAlert($text),
        'show_alert' => $alert ? 'true' : 'false',
    ]);
}

function delMsg($token, $chatId, $msgId) {
    return tg($token, 'deleteMessage', ['chat_id' => $chatId, 'message_id' => $msgId]);
}

function sendFile($token, $chatId, $type, $fileId, $caption = '', $protect = false, $markup = null) {
    $map = [
        'document' => ['sendDocument', 'document'], 'photo' => ['sendPhoto', 'photo'],
        'video' => ['sendVideo', 'video'],          'audio' => ['sendAudio', 'audio'],
        'voice' => ['sendVoice', 'voice'],          'animation' => ['sendAnimation', 'animation'],
        'sticker' => ['sendSticker', 'sticker'],    'video_note' => ['sendVideoNote', 'video_note'],
    ];
    [$method, $field] = $map[$type] ?? $map['document'];
    $data = ['chat_id' => $chatId, $field => $fileId];
    if ($caption !== '' && !in_array($type, ['sticker', 'video_note'], true)) {
        $data['caption'] = $caption;
        $data['parse_mode'] = 'HTML';
    }
    if ($protect) $data['protect_content'] = 'true';
    if ($markup) $data['reply_markup'] = is_string($markup) ? $markup : kbJson($markup);
    $res = tg($token, $method, $data);
    if (empty($res['ok']) && $markup && !is_string($markup) && isStyleError($res)) {
        $data['reply_markup'] = json_encode(stripStyles($markup));
        $res = tg($token, $method, $data);
    }
    return $res;
}

function entitiesToHtml($text, $entities = null) {
    if ($text === null || $text === '') return '';
    if (empty($entities)) return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');

    $u16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
    $len = (int)(strlen($u16) / 2);

    $open = array_fill(0, $len + 1, []);
    $close = array_fill(0, $len + 1, []);

    usort($entities, function ($a, $b) {
        if ($a['offset'] !== $b['offset']) return $a['offset'] <=> $b['offset'];
        return $b['length'] <=> $a['length'];
    });

    foreach ($entities as $e) {
        $o = (int)$e['offset'];
        $l = (int)$e['length'];
        if ($o < 0 || $l <= 0 || $o + $l > $len) continue;

        $ot = null; $ct = null;
        switch ($e['type']) {
            case 'bold':          $ot = '<b>';  $ct = '</b>';  break;
            case 'italic':        $ot = '<i>';  $ct = '</i>';  break;
            case 'underline':     $ot = '<u>';  $ct = '</u>';  break;
            case 'strikethrough': $ot = '<s>';  $ct = '</s>';  break;
            case 'spoiler':       $ot = '<tg-spoiler>'; $ct = '</tg-spoiler>'; break;
            case 'code':          $ot = '<code>'; $ct = '</code>'; break;
            case 'pre':
                $lang = !empty($e['language']) ? ' class="language-' . htmlspecialchars($e['language'], ENT_QUOTES, 'UTF-8') . '"' : '';
                $ot = '<pre><code' . $lang . '>'; $ct = '</code></pre>'; break;
            case 'blockquote':            $ot = '<blockquote>'; $ct = '</blockquote>'; break;
            case 'expandable_blockquote': $ot = '<blockquote expandable>'; $ct = '</blockquote>'; break;
            case 'text_link':
                if (empty($e['url'])) break;
                $ot = '<a href="' . htmlspecialchars($e['url'], ENT_QUOTES, 'UTF-8') . '">'; $ct = '</a>'; break;
            case 'text_mention':
                if (empty($e['user']['id'])) break;
                $ot = '<a href="tg://user?id=' . (int)$e['user']['id'] . '">'; $ct = '</a>'; break;
            case 'custom_emoji':
                if (empty($e['custom_emoji_id'])) break;
                $ot = '<tg-emoji emoji-id="' . htmlspecialchars($e['custom_emoji_id'], ENT_QUOTES, 'UTF-8') . '">';
                $ct = '</tg-emoji>'; break;
        }
        if ($ot === null) continue;
        $open[$o][]  = $ot;
        array_unshift($close[$o + $l], $ct);
    }

    $out = '';
    for ($i = 0; $i < $len; $i++) {
        foreach ($close[$i] as $t) $out .= $t;
        foreach ($open[$i]  as $t) $out .= $t;
        $ch = mb_convert_encoding(substr($u16, $i * 2, 2), 'UTF-8', 'UTF-16LE');
        $cp = unpack('v', substr($u16, $i * 2, 2))[1];
        if ($cp >= 0xD800 && $cp <= 0xDBFF && $i + 1 < $len) {
            $ch = mb_convert_encoding(substr($u16, $i * 2, 4), 'UTF-8', 'UTF-16LE');
            $i++;
        }
        $out .= htmlspecialchars($ch, ENT_NOQUOTES, 'UTF-8');
    }
    foreach ($close[$len] as $t) $out .= $t;
    return $out;
}

function msgHtml($msg) {
    $t = $msg['text'] ?? $msg['caption'] ?? '';
    $e = $msg['entities'] ?? $msg['caption_entities'] ?? null;
    return entitiesToHtml($t, $e);
}

function btnLabelEmoji($raw) {
    $raw = (string)$raw;
    $id  = '';
    if (preg_match('/<tg-emoji\s+emoji-id\s*=\s*["\']?(\d+)["\']?\s*>/i', $raw, $m))
        $id = $m[1];
    $txt = trim(preg_replace('/\s+/u', ' ', strip_tags(preg_replace('#<tg-emoji\b[^>]*>.*?</tg-emoji>#isu', ' ', $raw))));
    if ($txt === '') $txt = trim(strip_tags($raw));
    if ($txt === '') $txt = ' ';
    return [$txt, $id];
}

function btnApplyLabel(array $b, $raw, $fallbackIconId = '') {
    [$txt, $id] = btnLabelEmoji($raw);
    $b['text'] = $txt;
    $id = $id !== '' ? $id : trim((string)$fallbackIconId);
    if ($id !== '') $b['icon_custom_emoji_id'] = $id;
    return $b;
}

function customEmojiIds($msg) {
    $out = [];
    foreach (($msg['entities'] ?? $msg['caption_entities'] ?? []) as $e) {
        if (($e['type'] ?? '') !== 'custom_emoji') continue;
        $id = (string)($e['custom_emoji_id'] ?? '');
        if ($id !== '' && !in_array($id, $out, true)) $out[] = $id;
    }
    return $out;
}

function textWithoutCustomEmoji($msg) {
    $text = (string)($msg['text'] ?? $msg['caption'] ?? '');
    $cuts = [];
    foreach (($msg['entities'] ?? $msg['caption_entities'] ?? []) as $e) {
        if (($e['type'] ?? '') !== 'custom_emoji') continue;
        $cuts[] = [(int)($e['offset'] ?? 0), (int)($e['length'] ?? 0)];
    }
    if (!$cuts) return trim($text);

    usort($cuts, fn($a, $b) => $b[0] <=> $a[0]);
    $u16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
    foreach ($cuts as [$off, $len]) {
        $n = strlen($u16) >> 1;
        if ($len <= 0 || $off < 0 || $off >= $n) continue;
        $u16 = substr($u16, 0, $off * 2) . substr($u16, min($off + $len, $n) * 2);
    }
    $out = mb_convert_encoding($u16, 'UTF-8', 'UTF-16LE');
    return trim(preg_replace('/\s{2,}/u', ' ', $out));
}

function styleMap() {
    return [
        'none'    => '— بدون رنگ',
        'primary' => '🔵 آبی',
        'success' => '🟢 سبز',
        'danger'  => '🔴 قرمز',
    ];
}
function isStyle($s) { return in_array($s, ['primary', 'success', 'danger'], true); }

function gs($role) {
    if (function_exists('panelMode') && panelMode()) return null;
    if ($role === 'admin') return null;
    $c = cfg()['glass_colors'][$role] ?? 'none';
    return isStyle($c) ? $c : null;
}

function btnCb($label, $data, $role = null, $style = null) {
    if (function_exists('panelMode') && panelMode()) $label = panelLabel($label);
    $b = ['text' => $label, 'callback_data' => $data];
    $st = $style ?: ($role ? gs($role) : null);
    if (isStyle($st)) $b['style'] = $st;
    return $b;
}
function btnUrl($label, $url, $role = null, $style = null) {
    if (function_exists('panelMode') && panelMode()) $label = panelLabel($label);
    $b = ['text' => $label, 'url' => $url];
    $st = $style ?: ($role ? gs($role) : null);
    if (isStyle($st)) $b['style'] = $st;
    return $b;
}

function stripStyles($markup) {
    if (!is_array($markup)) return $markup;
    foreach (['inline_keyboard', 'keyboard'] as $k) {
        if (empty($markup[$k])) continue;
        foreach ($markup[$k] as $i => $row)
            foreach ($row as $j => $btn)
                unset($markup[$k][$i][$j]['style'], $markup[$k][$i][$j]['icon_custom_emoji_id']);
    }
    return $markup;
}

function defaultConfig() {
    return [
        'ui' => ['mode' => 'menu', 'layout' => '1,2,1,2,1',
                 'persistent' => false,
                 'placeholder' => 'یک گزینه را انتخاب کنید…'],

        'gateway' => [
            'on'        => false,
            'provider'  => 'oxapay',
            'api_key'   => '',
            'ipn_secret'=> '',
            'base_url'  => '',
            'coin'      => 'USDT',
            'network'   => 'TRC20',
            'rate'      => 0,
            'expire'    => 30,
            'min'       => 50000,
            'custom_url'=> '',
        ],

        'join' => [
            'on'       => false,
            'channels' => [],
            'text'     => "🔒 <b>برای استفاده از ربات، اول در کانال‌های زیر عضو شوید:</b>\n\n{channels}\n\n👇 بعد از عضویت، دکمه پایین را بزنید.",
            'btn'      => ['emoji' => '✅', 'text' => 'عضو شدم', 'color' => 'success', 'icon' => ''],
        ],

        'buttons' => [
            'buy'      => ['emoji' => '☎️', 'text' => 'خرید شماره مجازی',               'color' => 'success', 'icon' => '', 'row' => 1, 'order' => 1, 'on' => true, 'action' => ''],
            'account'  => ['emoji' => '👤', 'text' => 'حساب کاربری',                    'color' => 'primary', 'icon' => '', 'row' => 2, 'order' => 2, 'on' => true, 'action' => ''],
            'topup'    => ['emoji' => '➕', 'text' => 'افزایش موجودی',                  'color' => 'primary', 'icon' => '', 'row' => 2, 'order' => 3, 'on' => true, 'action' => ''],
            'referral' => ['emoji' => '👥', 'text' => 'زیر مجموعه گیری',                'color' => 'danger',  'icon' => '', 'row' => 3, 'order' => 4, 'on' => true, 'action' => ''],
            'orders'   => ['emoji' => '📊', 'text' => 'شماره‌های من',                   'color' => 'primary', 'icon' => '', 'row' => 4, 'order' => 5, 'on' => true, 'action' => ''],
            'support'  => ['emoji' => '📞', 'text' => 'پشتیبانی',                       'color' => 'primary', 'icon' => '', 'row' => 4, 'order' => 6, 'on' => true, 'action' => ''],
            'trust'    => ['emoji' => '💚', 'text' => 'چطوری میتوانم به شما اعتماد کنم', 'color' => 'danger',  'icon' => '', 'row' => 5, 'order' => 7, 'on' => true, 'action' => ''],
        ],

        'ui_texts' => [
            'back'      => '◀️ بازگشت',
            'home'      => '🏠 منوی اصلی',
            'cancel'    => '🔴 انصراف',
            'confirm'   => '🟢 تایید',
            'reject'    => '🔴 رد',
            'panel'     => '👑 پنل',
            'receipt'   => '🟢 ارسال رسید',
            'topup'     => '➕ افزایش موجودی',
            'my_orders' => '📊 شماره‌های من',
            'open_app'  => '☎️ شماره مجازی تلگرام',
            'open'      => '🔗 باز کردن',
            'shop_orders' => '📦 مشاهده‌ی سفارش‌های ثبت‌شده',
            'open_tgs'  => '✈️ خدمات تلگرام',
            'open_igs'  => '📸 خدمات اینستاگرام',
        ],

        'ui_icons' => [],
        'ui_colors' => ['shop_orders' => 'primary', 'open_tgs' => 'primary', 'open_igs' => 'danger'],

        'glass_colors' => [
            'buy'     => 'success',
            'confirm' => 'success',
            'cancel'  => 'danger',
            'reject'  => 'danger',
            'nav'     => 'primary',
            'info'    => 'primary',
            'admin'   => 'primary',
            'link'    => 'success',
        ],

        'texts' => [
            'welcome'      => "👋 سلام {name} عزیز\nبه فروشگاه شماره مجازی ما خوش آمدید.\n\n☎️ شماره‌ی تلگرام آنی تحویل می‌شود و کد همین‌جا می‌رسد.\nاز منوی پایین یکی از گزینه‌ها را انتخاب کنید.",
            'account'      => "👤 <b>حساب کاربری</b>\n\n🆔 آیدی: <code>{id}</code>\n👤 نام: {name}\n📛 یوزرنیم: {username}\n\n💰 موجودی: <b>{balance}</b> تومان\n☎️ شماره‌های تحویل‌شده: {orders}\n👥 زیرمجموعه: {referrals}\n💵 درآمد معرفی: {ref_earned} تومان\n\n📅 عضویت: {joined}",
            'trust'        => "💚 <b>چرا می‌توانید به ما اعتماد کنید؟</b>\n\n✅ سال‌ها سابقه فعالیت\n✅ تحویل آنی و خودکار\n✅ پشتیبانی ۲۴ ساعته\n✅ ضمانت بازگشت وجه\n✅ هزاران مشتری راضی\n\nبرای مشاهده نظرات مشتریان به کانال ما مراجعه کنید.",
            'support'      => "📞 <b>پشتیبانی</b>\n\nاز روش‌های زیر می‌توانید با ما در ارتباط باشید:",
            'orders_empty' => "📊 هنوز شماره‌ای نگرفته‌اید.\n\nاز فروشگاه، کشور و اپراتور را انتخاب کنید؛ شماره آنی تحویل می‌شود.",
            'orders_head'  => "📊 <b>شماره‌های شما</b>\n",
            'referral'     => "👥 <b>داشبورد زیرمجموعه</b>\n\nبا دعوت دوستان خود <b>{percent}%</b> از هر خرید آن‌ها را دریافت کنید.\n\n👥 تعداد زیرمجموعه: <b>{referrals}</b>\n💵 کل درآمد: <b>{ref_earned}</b> تومان\n💰 قابل برداشت: <b>{ref_pending}</b> تومان",
            'referral_link'=> "🔗 <b>لینک دعوت شما</b>\n\n{link}\n\nاین لینک را با دوستانتان به اشتراک بگذارید.",
            'referral_share'=> "🎁 با این لینک وارد ربات شو و شماره‌ی مجازی تلگرام آنی بگیر 👇",
            'referral_wallet_none' => "💼 <b>برداشت پورسانت</b>\n\nهنوز چیزی برای برداشت جمع نشده.",
            'referral_wallet_ok'   => "✅ <b>{amount}</b> تومان به کیف‌پولِ شما (داخلِ ربات) واریز شد.",
            'referral_hist_head' => "🧾 <b>تاریخچه‌ی پورسانت</b>\n",
            'referral_hist_row'  => "▪️ {date} — از خرید <b>{amount}</b> تومانی: <b>+{commission}</b> تومان\n",
            'referral_hist_none' => "هنوز پورسانتی ثبت نشده است.",
            'topup'        => "➕ <b>افزایش موجودی</b>\n\nمبلغ مورد نظر را به تومان وارد کنید (فقط عدد):",
            'topup_ok'     => "✅ <b>حساب شما شارژ شد</b>\n\n<blockquote>➕ مبلغ: <b>{amount}</b> تومان\n💰 موجودی: <b>{balance}</b> تومان</blockquote>",
            'shop'         => "🛍 <b>ثبت سفارش</b>\n\nبخشِ موردنظرتان را از دکمه‌های زیر باز کنید؛ سفارش‌های قبلی را هم با دکمه‌ی بالا ببینید.\n\n💰 موجودی شما: <b>{balance}</b> تومان",
            'shop_closed'  => "🔒 فروش موقتا بسته است — کمی بعد دوباره سر بزنید.",
            'receipt_ask'  => "🧾 لطفا رسید پرداخت را بفرستید.\n\nمی‌توانید <b>عکس رسید</b> یا <b>کد تراکنش</b> ارسال کنید.",
            'receipt_ok'   => "✅ رسید شما ثبت شد.\n\n⏳ پس از تایید ادمین اطلاع داده می‌شود.",
            'rejected'     => "❌ درخواست شارژ شما تایید نشد.\nدر صورت نیاز با پشتیبانی تماس بگیرید.",
            'no_balance'   => "❌ موجودی شما کافی نیست.\nموجودی فعلی: {balance} تومان",
            'banned'       => "🚫 دسترسی شما مسدود شده است.",

            'sup_ticket'   => "💬 <b>ارتباط غیر مستقیم</b>\n\nپیام خود را ارسال کنید، ادمین بررسی می‌کند.",
            'sup_sent'     => "✅ پیام شما برای پشتیبانی ارسال شد.\nبه زودی پاسخ داده می‌شود.",
            'orders_item'  => "{status}\n   {title}\n   ☎️ <code>{phone}</code>{code_line}\n   💰 {amount} تومان\n   🧾 <code>{id}</code>\n   📅 {date}\n",
        ],

        'support_main' => [
            'direct' => ['emoji' => '💬', 'text' => 'ارتباط مستقیم', 'color' => 'success',
                         'icon' => '', 'value' => ''],
            'indirect' => ['emoji' => '📨', 'text' => 'ارتباط غیر مستقیم', 'color' => 'primary',
                           'icon' => '', 'value' => ''],
            'group' => ['emoji' => '👥', 'text' => 'گروه ما', 'color' => 'primary',
                        'icon' => '', 'value' => ''],
        ],

        'support_methods' => [
            ['on' => true,  'kind' => 'indirect', 'type' => 'ticket', 'emoji' => '🎫', 'label' => 'ارسال تیکت در ربات', 'value' => ''],
            ['on' => true,  'kind' => 'indirect', 'type' => 'url',    'emoji' => '📢', 'label' => 'کانال اطلاع‌رسانی',  'value' => ''],
            ['on' => true,  'kind' => 'indirect', 'type' => 'url',    'emoji' => '👥', 'label' => 'گروه گفتگو',         'value' => ''],
            ['on' => true,  'kind' => 'indirect', 'type' => 'text',   'emoji' => '❓', 'label' => 'سوالات متداول',      'value' => "❓ <b>سوالات متداول</b>\n\nهنوز متنی تنظیم نشده است."],
            ['on' => false, 'kind' => 'indirect', 'type' => 'phone',  'emoji' => '☎️', 'label' => 'تماس تلفنی',         'value' => ''],
            ['on' => false, 'kind' => 'indirect', 'type' => 'url',    'emoji' => '📱', 'label' => 'واتساپ',             'value' => ''],
            ['on' => false, 'kind' => 'indirect', 'type' => 'url',    'emoji' => '🌐', 'label' => 'وب‌سایت',            'value' => ''],
            ['on' => false, 'kind' => 'indirect', 'type' => 'text',   'emoji' => '📧', 'label' => 'ایمیل',              'value' => ''],
            ['on' => false, 'kind' => 'indirect', 'type' => 'url',    'emoji' => '✈️', 'label' => 'ایتا',               'value' => ''],
            ['on' => false, 'kind' => 'indirect', 'type' => 'text',   'emoji' => '📋', 'label' => 'قوانین',             'value' => ''],
        ],

        'trust_btn' => ['text' => '📢 ورود به کانال گزارشات', 'url' => '', 'color' => 'primary', 'icon' => ''],

        'referral' => [
            'on' => true, 'percent' => 2,
            'btns' => [
                'link'   => ['emoji' => '🔗', 'text' => 'ساخت لینک دعوت',   'color' => 'success', 'icon' => ''],
                'hist'   => ['emoji' => '🧾', 'text' => 'تاریخچه پورسانت', 'color' => 'primary', 'icon' => ''],
                'wallet' => ['emoji' => '💼', 'text' => 'برداشت پورسانت', 'color' => 'primary', 'icon' => ''],
                'share'  => ['emoji' => '📤', 'text' => 'ارسال برای دوستان', 'color' => 'success', 'icon' => ''],
            ],
        ],

        'topup_rules' => [
            'on'   => true,
            'text' => "📋 <b>قوانین افزایش موجودی</b>\n\nبا ادامه دادن، شرایط زیر را می‌پذیرید:\n\n" .
                      "▪️ موجودیِ شارژشده فقط برای خرید از همین ربات قابل استفاده است.\n" .
                      "▪️ درست واردکردن مبلغ و ارسال رسیدِ صحیح با شماست.\n" .
                      "▪️ بازگشت وجه فقط طبق قوانینِ پشتیبانی انجام می‌شود.",
            'btns' => [
                'ok'     => ['emoji' => '✅', 'text' => 'تایید',       'color' => 'success', 'icon' => ''],
                'cancel' => ['emoji' => '❌', 'text' => 'لغو',         'color' => 'danger',  'icon' => ''],
                'done'   => ['emoji' => '✅', 'text' => 'تایید شده',  'color' => 'primary', 'icon' => ''],
            ],
        ],

        'wallets' => [
            'card' => '', 'card_name' => '',
        ],
    ];
}

function cfg($refresh = false) {
    static $c = null;
    if ($c === null || $refresh) {
        $saved = load('config');
        $c = array_replace_recursive(defaultConfig(), is_array($saved) ? $saved : []);
        if (!empty($saved['support_methods'])) $c['support_methods'] = $saved['support_methods'];
        if (!empty($saved['buttons']))         $c['buttons'] = array_replace_recursive(defaultConfig()['buttons'], $saved['buttons']);
    }
    return $c;
}

function cfgSet(callable $fn) {
    mutate('config', function (&$c) use ($fn) {
        if (!is_array($c) || !$c) $c = defaultConfig();
        $fn($c);
    });
    cfg(true);

    if (function_exists('maForget')) maForget();
}

function UI($key) { return cfg()['ui_icons'][$key] ?? ''; }

function UC($key) {
    $c = cfg()['ui_colors'][$key] ?? '';
    return isStyle($c) ? $c : null;
}

function btnUI($key, $data, $role = null) {
    $b = ['text' => UT($key), 'callback_data' => $data];
    $st = UC($key) ?: ($role ? gs($role) : null);
    if (isStyle($st)) $b['style'] = $st;
    $ic = (string)UI($key);
    if ($ic !== '') $b['icon_custom_emoji_id'] = $ic;
    return $b;
}

function UT($key) {
    $d = defaultConfig()['ui_texts'];
    $v = cfg()['ui_texts'][$key] ?? ($d[$key] ?? $key);
    return $v !== '' ? $v : ($d[$key] ?? $key);
}

function T($key, $vars = []) {
    $t = cfg()['texts'][$key] ?? '';
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}

function btnLabel($b, $withDot = null) {
    return trim(($b['emoji'] ?? '') . ' ' . ($b['text'] ?? ''));
}

function activeButtons() {
    $list = [];
    foreach (cfg()['buttons'] as $id => $b) {
        if (empty($b['on'])) continue;
        $b['id'] = $id;
        $list[] = $b;
    }
    usort($list, fn($x, $y) => ((int)($x['order'] ?? 99)) <=> ((int)($y['order'] ?? 99)));
    return $list;
}

function parseLayout($str) {
    $out = [];
    foreach (preg_split('/[^0-9]+/', (string)$str) as $n) {
        if ($n === '') continue;
        $n = (int)$n;
        if ($n >= 1 && $n <= 8) $out[] = $n;
    }
    return $out;
}

function layoutRows(array $items, $layoutStr) {
    $layout = parseLayout($layoutStr);
    $rows = [];
    $i = 0; $n = count($items); $k = 0;
    while ($i < $n) {
        $take = ($k < count($layout)) ? $layout[$k] : 1;
        $rows[] = array_slice($items, $i, $take);
        $i += $take; $k++;
    }
    return $rows;
}

function applyLayoutToRows($layoutStr) {
    $items = activeButtons();
    $rows = layoutRows($items, $layoutStr);
    $map = [];
    foreach ($rows as $r => $line) foreach ($line as $b) $map[$b['id']] = $r + 1;
    return $map;
}

function menuRows() {
    $items = activeButtons();
    $hasRows = false;
    foreach ($items as $b) if (!empty($b['row'])) { $hasRows = true; break; }
    if (!$hasRows) return layoutRows($items, cfg()['ui']['layout'] ?? '');

    $byRow = [];
    foreach ($items as $b) $byRow[(int)($b['row'] ?: 99)][] = $b;
    ksort($byRow);
    return array_values($byRow);
}

function mainKeyboard() {
    $c = cfg();
    $glass = ($c['ui']['mode'] === 'glass');
    $rows = menuRows();

    $out = [];
    foreach ($rows as $r) {
        $line = [];
        foreach ($r as $b) {
            $btn = ['text' => btnLabel($b)];
            if ($glass) $btn['callback_data'] = 'menu_' . $b['id'];
            if (isStyle($b['color'] ?? '')) $btn['style'] = $b['color'];
            if (!empty($b['icon'])) $btn['icon_custom_emoji_id'] = (string)$b['icon'];
            $line[] = $btn;
        }
        if ($line) $out[] = $line;
    }
    return $glass ? inlineKb($out) : menuKb($out);
}

function findMenuAction($text) {
    $text = trim($text);
    if ($text === '') return null;
    foreach (cfg()['buttons'] as $id => $b) {
        if (empty($b['on'])) continue;
        if (btnLabel($b, true) === $text)  return $id;
        if (btnLabel($b, false) === $text) return $id;
        if (trim($b['text']) === $text)    return $id;
    }
    return null;
}

function usersDbPath() { return DATA_DIR . '/users.sqlite'; }

function usersDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3')) return null;

    $path = usersDbPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fresh = !is_file($path);

    try {
        $db = new SQLite3($path);
    } catch (Throwable $e) {
        error_log('[shop-bot] users.sqlite باز نشد: ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, data TEXT NOT NULL)');

    if ($fresh) usersImportFromJson($db);
    return $db;
}

function usersImportFromJson($db) {
    $old = dataPath('users');
    if (!is_file($old)) return;
    $raw = @file_get_contents($old);
    $arr = $raw ? json_decode($raw, true) : null;

    if (is_array($arr) && $arr) {
        $db->exec('BEGIN');
        $stmt = $db->prepare('INSERT OR REPLACE INTO users (id, data) VALUES (:id, :data)');
        foreach ($arr as $k => $v) {
            $id = (int)$k;
            if ($id <= 0 || !is_array($v)) continue;
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->bindValue(':data', json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
            $stmt->execute();
            $stmt->reset();
        }
        $db->exec('COMMIT');
    }
    @rename($old, $old . '.migrated');
}

function getUser($id) {
    $db = usersDb();
    if (!$db) return null;
    $stmt = $db->prepare('SELECT data FROM users WHERE id = :id');
    $stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $d = json_decode($row['data'], true);
    return is_array($d) ? $d : null;
}

function mutateUser($id, callable $fn) {
    $db = usersDb();
    if (!$db) return null;
    $id = (int)$id;

    $db->exec('BEGIN IMMEDIATE');
    try {
        $stmt = $db->prepare('SELECT data FROM users WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $u = $row ? json_decode($row['data'], true) : null;
        if (!is_array($u)) $u = null;

        $result = $fn($u);

        if (is_array($u)) {
            $up = $db->prepare('INSERT OR REPLACE INTO users (id, data) VALUES (:id, :data)');
            $up->bindValue(':id', $id, SQLITE3_INTEGER);
            $up->bindValue(':data', json_encode($u, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
            $up->execute();
        }
        $db->exec('COMMIT');
        return $result;
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        error_log('[shop-bot] mutateUser خطا: ' . $e->getMessage());
        return null;
    }
}

function allUsers() {
    $db = usersDb();
    if (!$db) return [];
    $out = [];
    $res = $db->query('SELECT id, data FROM users');
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $d = json_decode($row['data'], true);
        if (is_array($d)) $out[(string)$row['id']] = $d;
    }
    return $out;
}

function touchUser($id, $username = '', $firstName = '', $referrer = null) {
    $cur = getUser($id);
    $whole = is_array($cur)
          && array_key_exists('balance', $cur) && array_key_exists('banned', $cur)
          && array_key_exists('referrer', $cur) && array_key_exists('joined_at', $cur);
    if ($whole && $referrer === null
        && (string)($cur['username'] ?? '')   === (string)$username
        && (string)($cur['first_name'] ?? '') === (string)$firstName) {
        $seen = strtotime((string)($cur['seen_at'] ?? '')) ?: 0;
        if ($seen > 0 && (time() - $seen) < 60) return $cur;
    }

    $referrerOk = $referrer && (int)$referrer !== (int)$id && getUser($referrer) !== null;

    $didSetReferrer = false;
    $out = mutateUser($id, function (&$user) use ($id, $username, $firstName, $referrerOk, $referrer, &$didSetReferrer) {
        $isNew = ($user === null);
        $user = array_merge([
            'telegram_id' => (int)$id,
            'balance'     => 0,
            'referrer'    => null,
            'ref_earned'  => 0,
            'banned'      => false,
            'joined_at'   => nowStr(),
        ], is_array($user) ? $user : [], [
            'username'   => $username,
            'first_name' => $firstName,
            'seen_at'    => nowStr(),
        ]);
        if ($isNew && $referrerOk) {
            $user['referrer'] = (int)$referrer;
            $didSetReferrer = true;
        }
        return $user;
    });

    if ($didSetReferrer) {
        mutateUser($referrer, function (&$r) {
            if ($r !== null) $r['ref_count'] = (int)($r['ref_count'] ?? 0) + 1;
        });
    }

    return $out;
}

function addBalance($userId, $amount) {
    mutateUser($userId, function (&$user) use ($amount) {
        if ($user === null) return;
        $user['balance'] = (float)round((float)$user['balance'] + (float)$amount);
    });
}

function debitBalance($userId, $amount) {
    $amount = (float)round((float)$amount);
    if ($amount <= 0) return true;
    return (bool)mutateUser($userId, function (&$user) use ($amount) {
        if ($user === null) return false;
        $bal = (float)round((float)($user['balance'] ?? 0));
        if ($bal < $amount) return false;
        $user['balance'] = round($bal - $amount);
        return true;
    });
}

function countReferrals($userId) {
    return (int)(getUser($userId)['ref_count'] ?? 0);
}

function backfillRefCounts() {
    $all = allUsers();
    $counts = [];
    foreach ($all as $u) {
        $r = (int)($u['referrer'] ?? 0);
        if ($r > 0) $counts[$r] = ($counts[$r] ?? 0) + 1;
    }
    foreach ($all as $id => $u) {
        $n = $counts[(int)$id] ?? 0;
        mutateUser($id, function (&$user) use ($n) { if ($user !== null) $user['ref_count'] = $n; });
    }
}

function payReferralCommission($buyerId, $amount) {
    $u = getUser($buyerId);
    if (!$u || empty($u['referrer'])) return;
    $c = cfg()['referral'];
    if (empty($c['on'])) return;
    $commission = round((float)$amount * ((float)$c['percent'] / 100), 2);
    if ($commission <= 0) return;
    mutateUser($u['referrer'], function (&$user) use ($commission) {
        if ($user === null) return;
        $user['ref_earned']  = round((float)($user['ref_earned'] ?? 0) + $commission, 2);
        $user['ref_pending'] = round((float)($user['ref_pending'] ?? 0) + $commission, 2);
    });
    mutate('ref_log', function (&$a) use ($u, $amount, $commission) {
        $k = (string)$u['referrer'];
        if (!isset($a[$k]) || !is_array($a[$k])) $a[$k] = [];
        array_unshift($a[$k], ['amount' => (float)$amount, 'commission' => $commission, 'at' => nowStr()]);
        $a[$k] = array_slice($a[$k], 0, 50);
    });
    sendMsg(BOT_TOKEN, $u['referrer'],
        "🎉 یکی از زیرمجموعه‌های شما خرید کرد!\n💵 پورسانت شما: <b>" . fmtNum($commission) . "</b> تومان\n\n" .
        "برای واریز به کیف‌پولتان، از 👥 زیرمجموعه ← 💼 برداشت پورسانت استفاده کنید.");
}

function slotGet($uid, $slot) {
    $u = getUser($uid);
    $v = $u['slots'][$slot] ?? null;
    return $v ? (int)$v : null;
}

function slotSet($uid, $slot, $mid) {
    mutateUser($uid, function (&$user) use ($slot, $mid) {
        if ($user === null) return;
        if (!is_array($user['slots'] ?? null)) $user['slots'] = [];
        if ($mid) $user['slots'][$slot] = (int)$mid;
        else unset($user['slots'][$slot]);
    });
}

function slotClear($uid, $slot = null) {
    mutateUser($uid, function (&$user) use ($slot) {
        if ($user === null) return;
        if ($slot === null) $user['slots'] = array_intersect_key((array)($user['slots'] ?? []), ['umsg' => 1]);
        else unset($user['slots'][$slot]);
    });
}

function panelShow($uid, $chatId, $slot, $text, $markup = null, $replyTo = null) {
    if ($replyTo && (string)$chatId === (string)$uid) {
        $slots = (array)(getUser($uid)['slots'] ?? []);
        unset($slots['umsg']);
        $old = array_unique(array_filter(array_map('intval', $slots)));
        $r = sendMsg(BOT_TOKEN, $chatId, $text, $markup);
        $nid = (int)($r['result']['message_id'] ?? 0);
        mutateUser($uid, function (&$user) use ($slot, $nid) {
            if ($user === null) return;
            $keep = array_intersect_key((array)($user['slots'] ?? []), ['umsg' => 1]);
            $user['slots'] = $nid ? $keep + [$slot => $nid] : $keep;
        });
        foreach ($old as $o) if ($o !== $nid) delMsg(BOT_TOKEN, $chatId, $o);
        return $nid ?: null;
    }
    $mid = slotGet($uid, $slot);
    if ($replyTo) {
        if ($mid) delMsg(BOT_TOKEN, $chatId, $mid);
        $r = sendMsg(BOT_TOKEN, $chatId, $text, $markup, [
            'reply_to_message_id' => $replyTo,
            'allow_sending_without_reply' => 'true',
        ]);
        $nid = $r['result']['message_id'] ?? null;
        slotSet($uid, $slot, $nid);
        return $nid;
    }

    if ($mid) {
        $data = [
            'chat_id' => $chatId, 'message_id' => $mid, 'text' => $text,
            'parse_mode' => 'HTML', 'disable_web_page_preview' => 'true',
        ];
        if ($markup) $data['reply_markup'] = is_string($markup) ? $markup : kbJson($markup);
        $r = tg(BOT_TOKEN, 'editMessageText', $data);

        if (empty($r['ok']) && $markup && !is_string($markup) && isStyleError($r)) {
            $data['reply_markup'] = json_encode(stripStyles($markup));
            $r = tg(BOT_TOKEN, 'editMessageText', $data);
        }
        if (!empty($r['ok'])) return $mid;

        if (str_contains(strtolower($r['description'] ?? ''), 'not modified')) return $mid;
    }

    $r = sendMsg(BOT_TOKEN, $chatId, $text, $markup);
    $nid = $r['result']['message_id'] ?? null;
    slotSet($uid, $slot, $nid);
    return $nid;
}

function statesDb() {
    static $db = null;
    if ($db !== null) return $db ?: null;
    if (!class_exists('SQLite3')) return $db = false;

    $path = DATA_DIR . '/states.sqlite';
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    try { $db = new SQLite3($path); }
    catch (Throwable $e) { error_log('[states] باز نشد: ' . $e->getMessage()); return $db = false; }

    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS states (
        id INTEGER PRIMARY KEY, action TEXT, data TEXT, at INTEGER)');
    $db->exec('CREATE INDEX IF NOT EXISTS states_at ON states (at)');

    stateMigrateOnce($db);
    return $db;
}

function stateMigrateOnce($db) {
    $old = dataPath('states');
    if (!is_file($old)) return;
    $raw = @file_get_contents($old);
    $a = $raw ? json_decode($raw, true) : null;
    if (is_array($a) && $a) {
        $ins = $db->prepare('INSERT OR REPLACE INTO states (id, action, data, at) VALUES (:i,:a,:d,:t)');
        $db->exec('BEGIN');
        foreach ($a as $uid => $st) {
            if (!is_array($st)) continue;
            $ins->bindValue(':i', (int)$uid, SQLITE3_INTEGER);
            $ins->bindValue(':a', (string)($st['action'] ?? ''), SQLITE3_TEXT);
            $ins->bindValue(':d', json_encode((array)($st['data'] ?? []), JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
            $ins->bindValue(':t', strtotime((string)($st['at'] ?? '')) ?: time(), SQLITE3_INTEGER);
            $ins->execute(); $ins->reset();
        }
        $db->exec('COMMIT');
    }
    @rename($old, $old . '.migrated');
}

function getState($uid) {
    $db = statesDb();
    if (!$db) return null;
    $st = $db->prepare('SELECT action, data, at FROM states WHERE id = :i');
    $st->bindValue(':i', (int)$uid, SQLITE3_INTEGER);
    $row = $st->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $d = json_decode((string)$row['data'], true);
    return [
        'action' => (string)$row['action'],
        'data'   => is_array($d) ? $d : [],
        'at'     => date('Y-m-d H:i:s', (int)$row['at']),
    ];
}

function setState($uid, $action, $data = []) {
    $db = statesDb();
    if (!$db) return;
    $st = $db->prepare('INSERT OR REPLACE INTO states (id, action, data, at) VALUES (:i,:a,:d,:t)');
    $st->bindValue(':i', (int)$uid, SQLITE3_INTEGER);
    $st->bindValue(':a', (string)$action, SQLITE3_TEXT);
    $st->bindValue(':d', json_encode((array)$data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
    $st->bindValue(':t', time(), SQLITE3_INTEGER);
    $st->execute();
}

function clearState($uid) {
    $db = statesDb();
    if (!$db) return;
    $st = $db->prepare('DELETE FROM states WHERE id = :i');
    $st->bindValue(':i', (int)$uid, SQLITE3_INTEGER);
    $st->execute();
}

function stateSweep($ttl = 86400, $limit = 500) {
    $db = statesDb();
    if (!$db) return 0;
    $cut = time() - max(60, (int)$ttl);
    $st = $db->prepare('DELETE FROM states WHERE at < :c');
    $st->bindValue(':c', $cut, SQLITE3_INTEGER);
    $st->execute();
    return $db->changes();
}

function parseChatLink($s) {
    $s = trim((string)$s);
    if ($s === '') return [null, 0];

    if (preg_match('/^-?\d{5,}$/', $s)) return [$s, 0];
    if (preg_match('/^@[A-Za-z0-9_]{4,}$/', $s)) return [$s, 0];

    $s = preg_replace('#^https?://#i', '', $s);
    $s = preg_replace('#^t\.me/#i', '', $s, 1, $n);
    if (!$n) return [null, 0];
    $s = trim($s, '/');
    $parts = array_values(array_filter(explode('/', $s), fn($x) => $x !== ''));
    if (!$parts) return [null, 0];

    if (strtolower($parts[0]) === 'c') {
        if (!isset($parts[1]) || !ctype_digit($parts[1])) return [null, 0];
        $chat = '-100' . $parts[1];
        $th   = (isset($parts[2]) && ctype_digit($parts[2])) ? (int)$parts[2] : 0;
        return [$chat, $th];
    }

    if (str_starts_with($parts[0], '+') || strtolower($parts[0]) === 'joinchat') return [null, 0];

    if (!preg_match('/^[A-Za-z0-9_]{4,}$/', $parts[0])) return [null, 0];
    $chat = '@' . $parts[0];
    $th   = (isset($parts[1]) && ctype_digit($parts[1])) ? (int)$parts[1] : 0;
    return [$chat, $th];
}

function ordersDbPath() { return DATA_DIR . '/orders.sqlite'; }

function ordersDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3')) return null;

    $path = ordersDbPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fresh = !is_file($path);

    try {
        $db = new SQLite3($path);
    } catch (Throwable $e) {
        error_log('[shop-bot] orders.sqlite باز نشد: ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS orders (
        id TEXT PRIMARY KEY, user_id INTEGER NOT NULL, type TEXT NOT NULL,
        status TEXT NOT NULL, created_at TEXT NOT NULL, data TEXT NOT NULL
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_orders_user   ON orders(user_id, created_at)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status)');
    $db->exec('CREATE TABLE IF NOT EXISTS orders_old (id TEXT PRIMARY KEY, data TEXT NOT NULL)');

    if ($fresh) ordersImportFromJson($db);
    return $db;
}

function ordersImportFromJson($db) {
    $map = ['orders' => 'orders', 'orders_old' => 'orders_old'];
    foreach ($map as $file => $table) {
        $old = dataPath($file);
        if (!is_file($old)) continue;
        $raw = @file_get_contents($old);
        $arr = $raw ? json_decode($raw, true) : null;
        if (is_array($arr) && $arr) {
            $db->exec('BEGIN');
            if ($table === 'orders') {
                $stmt = $db->prepare('INSERT OR REPLACE INTO orders (id, user_id, type, status, created_at, data) VALUES (:id, :uid, :type, :status, :created, :data)');
                foreach ($arr as $k => $v) {
                    if ($k === '' || !is_array($v)) continue;
                    $stmt->bindValue(':id', (string)$k, SQLITE3_TEXT);
                    $stmt->bindValue(':uid', (int)($v['user_id'] ?? 0), SQLITE3_INTEGER);
                    $stmt->bindValue(':type', (string)($v['type'] ?? ''), SQLITE3_TEXT);
                    $stmt->bindValue(':status', (string)($v['status'] ?? ''), SQLITE3_TEXT);
                    $stmt->bindValue(':created', (string)($v['created_at'] ?? ''), SQLITE3_TEXT);
                    $stmt->bindValue(':data', json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
                    $stmt->execute();
                    $stmt->reset();
                }
            } else {
                $stmt = $db->prepare('INSERT OR REPLACE INTO orders_old (id, data) VALUES (:id, :data)');
                foreach ($arr as $k => $v) {
                    if ($k === '' || !is_array($v)) continue;
                    $stmt->bindValue(':id', (string)$k, SQLITE3_TEXT);
                    $stmt->bindValue(':data', json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
                    $stmt->execute();
                    $stmt->reset();
                }
            }
            $db->exec('COMMIT');
        }
        @rename($old, $old . '.migrated');
    }
}

class Order
{
    const PENDING = 'pending', REVIEW = 'review', APPROVED = 'approved', REJECTED = 'rejected';

    public static function pendingGw($limit = 10) {
        $db = ordersDb();
        if (!$db) return [];
        $stmt = $db->prepare("SELECT data FROM orders WHERE status = :s AND type = 'topup' ORDER BY created_at DESC LIMIT :n");
        $stmt->bindValue(':s', self::PENDING, SQLITE3_TEXT);
        $stmt->bindValue(':n', max(1, (int)$limit) * 4, SQLITE3_INTEGER);
        $out = [];
        $res = $stmt->execute();
        while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
            $d = json_decode($row['data'], true);
            if (is_array($d) && !empty($d['gw']['invoice'])) $out[] = $d;
        }
        return $out;
    }

    public static function get($id) {
        $db = ordersDb();
        if (!$db) return null;
        $id = (string)$id;

        $stmt = $db->prepare('SELECT data FROM orders WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row) { $d = json_decode($row['data'], true); if (is_array($d)) return $d; }

        $stmt = $db->prepare('SELECT data FROM orders_old WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row) { $d = json_decode($row['data'], true); if (is_array($d)) return $d; }

        return null;
    }

    public static function create($userId, $username, $amount) {
        $id = uid('or');
        $o = [
            'id' => $id, 'user_id' => (int)$userId, 'username' => $username,
            'type' => 'topup',
            'amount' => (float)$amount, 'currency' => 'تومان',
            'status' => self::PENDING,
            'receipt_type' => null, 'receipt' => null,
            'created_at' => nowStr(), 'decided_at' => null, 'decided_by' => null,
        ];
        $db = ordersDb();
        if ($db) {
            $stmt = $db->prepare('INSERT INTO orders (id, user_id, type, status, created_at, data) VALUES (:id, :uid, :type, :status, :created, :data)');
            $stmt->bindValue(':id', $id, SQLITE3_TEXT);
            $stmt->bindValue(':uid', (int)$userId, SQLITE3_INTEGER);
            $stmt->bindValue(':type', 'topup', SQLITE3_TEXT);
            $stmt->bindValue(':status', self::PENDING, SQLITE3_TEXT);
            $stmt->bindValue(':created', $o['created_at'], SQLITE3_TEXT);
            $stmt->bindValue(':data', json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
            $stmt->execute();
        }
        return $id;
    }

    public static function delete($id) {
        $db = ordersDb();
        if (!$db) return;
        $stmt = $db->prepare('DELETE FROM orders WHERE id = :id');
        $stmt->bindValue(':id', (string)$id, SQLITE3_TEXT);
        $stmt->execute();
    }

    public static function set($id, callable $fn) {
        $db = ordersDb();
        if (!$db) return false;
        $id = (string)$id;

        $db->exec('BEGIN IMMEDIATE');
        try {
            $stmt = $db->prepare('SELECT data FROM orders WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_TEXT);
            $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            $o = $row ? json_decode($row['data'], true) : null;
            if (!is_array($o)) { $db->exec('ROLLBACK'); return false; }

            $r = $fn($o);

            $up = $db->prepare('UPDATE orders SET user_id = :uid, type = :type, status = :status, created_at = :created, data = :data WHERE id = :id');
            $up->bindValue(':id', $id, SQLITE3_TEXT);
            $up->bindValue(':uid', (int)($o['user_id'] ?? 0), SQLITE3_INTEGER);
            $up->bindValue(':type', (string)($o['type'] ?? ''), SQLITE3_TEXT);
            $up->bindValue(':status', (string)($o['status'] ?? ''), SQLITE3_TEXT);
            $up->bindValue(':created', (string)($o['created_at'] ?? ''), SQLITE3_TEXT);
            $up->bindValue(':data', json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
            $up->execute();
            $db->exec('COMMIT');
            return $r === null ? true : $r;
        } catch (Throwable $e) {
            $db->exec('ROLLBACK');
            error_log('[shop-bot] Order::set خطا: ' . $e->getMessage());
            return false;
        }
    }

    public static function attachReceipt($id, $type, $value) {
        return self::set($id, function (&$x) use ($type, $value) {
            if ($x['status'] !== self::PENDING) return false;
            $x['receipt_type'] = $type;
            $x['receipt'] = $value;
            $x['status'] = self::REVIEW;
            return true;
        });
    }

    public static function approve($id, $adminId) {
        $o0 = self::get($id);
        if (!$o0) return [false, 'سفارش پیدا نشد.'];
        if (($o0['currency'] ?? 'تومان') !== 'تومان')
            return [false, 'مبلغ این سفارشِ قدیمی تومانی نیست — دستی رسیدگی کنید.'];

        $r = self::set($id, function (&$x) use ($adminId) {
            if (in_array($x['status'], [self::APPROVED, self::REJECTED], true)) return 'done';
            $x['status'] = self::APPROVED;
            $x['decided_at'] = nowStr();
            $x['decided_by'] = (int)$adminId;
            return 'ok';
        });
        if ($r === false)  return [false, 'سفارش پیدا نشد.'];
        if ($r === 'done') return [false, 'این سفارش قبلا بررسی شده است.'];

        $o = self::get($id);
        addBalance($o['user_id'], $o['amount']);
        return [true, $o];
    }

    public static function reject($id, $adminId) {
        $r = self::set($id, function (&$x) use ($adminId) {
            if (in_array($x['status'], [self::APPROVED, self::REJECTED], true)) return 'done';
            $x['status'] = self::REJECTED;
            $x['decided_at'] = nowStr();
            $x['decided_by'] = (int)$adminId;
            return 'ok';
        });
        if ($r === false)  return [false, 'سفارش پیدا نشد.'];
        if ($r === 'done') return [false, 'این سفارش قبلا بررسی شده است.'];
        return [true, self::get($id)];
    }

    public static function countBy($status) {
        $db = ordersDb();
        if (!$db) return 0;
        $stmt = $db->prepare('SELECT COUNT(*) c FROM orders WHERE status = :status');
        $stmt->bindValue(':status', (string)$status, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        return (int)($row['c'] ?? 0);
    }

    public static function statusLabel($s) {
        return [
            'pending'  => '⏳ منتظر رسید',
            'review'   => '🧾 در حال بررسی',
            'approved' => '✅ تایید شده',
            'rejected' => '❌ رد شده',
        ][$s] ?? '—';
    }
}

class JoinCheck
{
    private static $memCache = [];
    private static $memFresh = [];

    private static function takeFresh($k) {
        if (!isset(self::$memFresh[$k])) return false;
        unset(self::$memFresh[$k]);
        return true;
    }

    private static function joinCacheShardFile($k) {
        return 'mjoin_cache_' . (hexdec(substr(md5((string)$k), 0, 4)) % 16);
    }
    private static function joinCacheGet($k) {
        if (function_exists('apcu_fetch')) {
            $v = @apcu_fetch('mjoin_' . $k, $ok);
            if ($ok) return $v;
        }
        $bag = load(self::joinCacheShardFile($k));
        $e = $bag[$k] ?? null;
        if (!is_array($e) || (int)($e['exp'] ?? 0) < time()) return null;
        return $e['v'];
    }
    private static function joinCachePut($k, $v) {
        if (function_exists('apcu_store')) { @apcu_store('mjoin_' . $k, $v, 45); return; }
        mutate(self::joinCacheShardFile($k), function (&$bag) use ($k, $v) {
            $bag[$k] = ['v' => $v, 'exp' => time() + 45];
            if (count($bag) > 400) {
                $now = time();
                foreach ($bag as $kk => $e) if ((int)($e['exp'] ?? 0) < $now) unset($bag[$kk]);
            }
        });
    }

    private static function memberResult($r) {
        if (empty($r['ok'])) {
            $desc = strtolower($r['description'] ?? '');
            $reallyNotMember = str_contains($desc, 'user not found')
                            || str_contains($desc, 'participant_id_invalid');
            return ['ok' => $reallyNotMember, 'member' => false, 'error' => $r['description'] ?? ''];
        }
        $st = $r['result']['status'] ?? '';
        return ['ok' => true,
                'member' => in_array($st, ['member', 'administrator', 'creator'], true),
                'error' => ''];
    }

    public static function warmMembership($chatIds, $userId, $fresh = false) {
        $need = [];
        foreach ($chatIds as $cid) {
            $cid = (string)$cid;
            if ($cid === '') continue;
            $k = $cid . ':' . $userId;
            if ($fresh && !self::takeFresh($k)) unset(self::$memCache[$k]);
            if (isset(self::$memCache[$k]) || isset($need[$k])) continue;
            if (!$fresh) {
                $cached = self::joinCacheGet($k);
                if ($cached !== null) { self::$memCache[$k] = $cached; continue; }
            }
            $need[$k] = ['chat_id' => $cid, 'user_id' => $userId];
        }
        if (count($need) < 2) return;
        foreach (tgMulti(BOT_TOKEN, 'getChatMember', $need, 6) as $k => $r) {
            self::$memCache[$k] = self::memberResult($r);
            self::$memFresh[$k] = true;
            self::joinCachePut($k, self::$memCache[$k]);
        }
    }

    public static function isMemberOf($chatId, $userId, $fresh = false) {
        $k = $chatId . ':' . $userId;
        if ($fresh && !self::takeFresh($k)) unset(self::$memCache[$k]);
        if (isset(self::$memCache[$k])) return self::$memCache[$k];

        if (!$fresh) {
            $cached = self::joinCacheGet($k);
            if ($cached !== null) return self::$memCache[$k] = $cached;
        }

        $r = tg(BOT_TOKEN, 'getChatMember',
                ['chat_id' => $chatId, 'user_id' => $userId], 6);
        unset(self::$memFresh[$k]);
        $res = self::memberResult($r);
        self::joinCachePut($k, $res);
        return self::$memCache[$k] = $res;
    }

}

function showHome($uid, $chatId, $firstName) {
    sendMsg(BOT_TOKEN, $chatId, T('welcome', ['name' => h($firstName)]), mainKeyboard());
}

function countApprovedOrders($uid) {
    return (int)(getUser($uid)['approved_orders'] ?? 0);
}

function showAccount($uid, $chatId, $replyTo = null) {
    $u = getUser($uid) ?: [];

    $text = T('account', [
        'id'         => $uid,
        'name'       => h($u['first_name'] ?? '—'),
        'username'   => !empty($u['username']) ? '@' . h($u['username']) : '—',
        'balance'    => fmtNum($u['balance'] ?? 0),
        'orders'     => countApprovedOrders($uid),
        'referrals'  => countReferrals($uid),
        'ref_earned' => fmtNum($u['ref_earned'] ?? 0),
        'joined'     => h($u['joined_at'] ?? '—'),
    ]);
    $rows = [[btnUI('topup', 'menu_topup', 'buy')], [btnUI('my_orders', 'menu_orders', 'info')]];
    panelShow($uid, $chatId, 'menu', $text, inlineKb($rows), $replyTo);
}

function refBtn($which, $cb) {
    $m = cfg()['referral']['btns'][$which] ?? [];
    $b = ['text' => trim(($m['emoji'] ?? '') . ' ' . ($m['text'] ?? '')), 'callback_data' => $cb];
    if (isStyle($m['color'] ?? '')) $b['style'] = $m['color'];
    if (!empty($m['icon'])) $b['icon_custom_emoji_id'] = (string)$m['icon'];
    return $b;
}

function refInviteLink($uid) {
    $un = botUsername();
    return $un !== '' ? "https://t.me/{$un}?start=ref{$uid}" : '';
}

function showReferral($uid, $chatId, $replyTo = null) {
    $cardMid = slotGet($uid, 'refcard');
    if ($cardMid) { delMsg(BOT_TOKEN, $chatId, $cardMid); slotClear($uid, 'refcard'); }

    $u = getUser($uid) ?: [];
    $refText = T('referral', [
        'percent'     => cfg()['referral']['percent'],
        'referrals'   => countReferrals($uid),
        'ref_earned'  => fmtNum($u['ref_earned'] ?? 0),
        'ref_pending' => fmtNum($u['ref_pending'] ?? 0),
    ]);
    $rows = [
        [refBtn('link', 'ref_link')],
        [refBtn('hist', 'ref_hist'), refBtn('wallet', 'ref_wallet')],
    ];
    panelShow($uid, $chatId, 'menu', $refText, inlineKb($rows), $replyTo);
}

function showReferralLink($uid, $chatId) {
    $link = refInviteLink($uid);
    if ($link === '') {
        panelShow($uid, $chatId, 'menu', '⚠️ لینک هنوز آماده نیست.',
            inlineKb([[btnUI('back', 'menu_referral', 'nav')]]));
        return;
    }
    $oldMid = slotGet($uid, 'menu');
    if ($oldMid) { delMsg(BOT_TOKEN, $chatId, $oldMid); slotClear($uid, 'menu'); }

    $share = refBtn('share', '');
    unset($share['callback_data']);
    $share['url'] = 'https://t.me/share/url?url=' . rawurlencode($link) .
                    '&text=' . rawurlencode(strip_tags(T('referral_share')));
    $rows = [[$share], [btnUI('back', 'menu_referral', 'nav')]];
    $caption = T('referral_link', ['link' => $link]);
    $ok = refCardShow($uid, $chatId, $caption, inlineKb($rows));
    if (!$ok) panelShow($uid, $chatId, 'menu', $caption, inlineKb($rows));
}

function refCardBytes($link, $uid) {
    if (!function_exists('pxCardReady') || !pxCardReady() || !function_exists('pxBase')) return '';
    $q = pxQrMatrix($link);
    if (!$q) return '';
    $S     = PX_SS;
    $im    = pxBase('12C488', '2F72DF');
    $white = pxCol($im, 'F5F5F7');
    $gray  = pxCol($im, '9A9AA0');
    $lat   = pxLat(600);
    $pct   = (float)(cfg()['referral']['percent'] ?? 0);

    [, $tr] = pxTag($im, 232, 172, 'INVITE & EARN', 'green', 'l');
    $title = 'دعوت از دوستان';
    pxSay($im, pxSayFit(38, $title, 966 - $tr - 40, 18, ''), 966, 186, $white, $title, 'r');

    pxRRect($im, 226 * $S, 226 * $S, 486 * $S, 486 * $S, 26 * $S, pxCol($im, 'FFFFFF'));
    pxQrDraw($im, $q, 244, 244, 224, '0B0B0C');
    pxSay($im, 17, 356, 522, $gray, 'اسکن کن و عضو شو', 'c');

    if ($pct > 0) {
        pxSay($im, 19.5, 966, 240, $gray, 'پورسانت از هر خرید دوستانت', 'r');
        $big  = rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.') . '%';
        $size = pxFit($lat, 58, $big, 380, 30);
        pxPut($im, $lat, $size, 966, 334 - (58 - $size) * 0.3, $white, $big, 'r');
    } else {
        pxSay($im, 19.5, 966, 240, $gray, 'لینک اختصاصی تو', 'r');
        pxSay($im, 40, 966, 330, $white, 'دعوت کن', 'r');
    }

    pxRRect($im, 530 * $S, 402 * $S, 966 * $S, 454 * $S, 16 * $S, pxCol($im, 'FFFFFF', 0.12));
    pxRRect($im, 531.5 * $S, 403.5 * $S, 964.5 * $S, 452.5 * $S, 14.5 * $S, pxCol($im, '18191C'));
    $short = preg_replace('#^https?://#', '', (string)$link);
    pxPut($im, $lat, pxFit($lat, 17, $short, 400, 11), 748, 434, pxCol($im, 'D7DAE0'), $short, 'c');

    $code = (string)(int)$uid;
    $lab  = 'کد دعوت';
    $lw   = pxSayW(17, $lab);
    pxSay($im, 17, 966, 512, $gray, $lab, 'r');
    pxPut($im, $lat, 21, 966 - $lw - 20, 512, pxCol($im, '86EBC9'), $code, 'r');

    pxFoot($im, 'INVITE & EARN');
    return pxJpg($im);
}

function refCardPhotoIdKey($uid) {
    return 'refcard3_fid_' . (int)$uid . '_' . substr(md5(refInviteLink($uid) . '|' . (cfg()['referral']['percent'] ?? '')), 0, 10);
}

function refCardRemember($uid, $fid, $nid) {
    if ($fid !== '' && function_exists('maCachePut')) {
        maCachePut(refCardPhotoIdKey($uid), $fid);
        $check = function_exists('maCacheGet') ? (string)(maCacheGet(refCardPhotoIdKey($uid), 2592000) ?? '') : $fid;
        if ($check !== $fid && function_exists('adminAlertOnce')) {
            adminAlertOnce('refcard_cache_fail',
                '🖼 <b>ذخیره‌ی فایل‌شناسه‌ی کارتِ دعوت شکست خورد</b>' . "\n\n" .
                'یعنی هر بار کارت از نو ساخته می‌شود، نه اینکه یک‌بار بسازد و دوباره بفرستد. ' .
                'دسترسیِ نوشتن روی پوشه‌ی data_master را چک کنید.');
        }
    }
    if ($nid) {
        slotSet($uid, 'refcard', $nid);
        $checkMid = slotGet($uid, 'refcard');
        if ((int)$checkMid !== (int)$nid && function_exists('adminAlertOnce')) {
            adminAlertOnce('refcard_slot_fail',
                '🖼 <b>ذخیره‌ی شناسه‌ی پیامِ کارتِ دعوت شکست خورد</b>' . "\n\n" .
                'دسترسیِ نوشتن روی پوشه‌ی data_master را چک کنید.');
        }
    }
}

function refCardShow($uid, $chatId, $caption = '', $markup = null) {
    $link = refInviteLink($uid);
    if ($link === '') return false;
    $mid = slotGet($uid, 'refcard');
    $fid = function_exists('maCacheGet') ? (string)(maCacheGet(refCardPhotoIdKey($uid), 2592000) ?? '') : '';
    $rm  = $markup ? (is_string($markup) ? $markup : json_encode($markup)) : null;

    if (function_exists('__tgHook')) {
        if ($mid && $fid !== '') {
            $media = ['type' => 'photo', 'media' => $fid, 'caption' => $caption, 'parse_mode' => 'HTML'];
            $data = ['chat_id' => $chatId, 'message_id' => $mid, 'media' => json_encode($media)];
            if ($rm !== null) $data['reply_markup'] = $rm;
            $out = __tgHook(BOT_TOKEN, 'editMessageMedia', $data);
            if (!empty($out['ok']) || isNotModified($out)) return true;
        }
        $data = ['chat_id' => $chatId, 'caption' => $caption, 'photo_len' => 999];
        if ($rm !== null) $data['reply_markup'] = $rm;
        if ($fid !== '') $data['reused_fid'] = $fid;
        $out = __tgHook(BOT_TOKEN, 'sendPhoto', $data);
        if (empty($out['ok'])) return false;
        $nid = $out['result']['message_id'] ?? null;
        $newFid = $out['result']['photo'][0]['file_id'] ?? ('TESTFID_' . $uid);
        refCardRemember($uid, $newFid, $nid);
        return true;
    }

    if ($mid && $fid !== '') {
        $media = ['type' => 'photo', 'media' => $fid, 'caption' => $caption, 'parse_mode' => 'HTML'];
        $data = ['chat_id' => $chatId, 'message_id' => $mid, 'media' => json_encode($media)];
        if ($rm !== null) $data['reply_markup'] = $rm;
        $r = tg(BOT_TOKEN, 'editMessageMedia', $data, 8);
        if (!empty($r['ok']) || isNotModified($r)) return true;

        if (function_exists('pxSendPhotoById')) {
            $resend = pxSendPhotoById($chatId, $fid, $caption, $rm, null, 8);
            if (!empty($resend['ok'])) {
                $nid = $resend['result']['message_id'] ?? null;
                refCardRemember($uid, '', $nid);
                return true;
            }
        }
    }

    $bytes = refCardBytes($link, $uid);
    if ($bytes === '') {
        if (function_exists('adminAlertOnce') && function_exists('pxCardWhy')) {
            $why = pxCardWhy();
            if ($why !== '') adminAlertOnce('refcard_broken', '🖼 <b>کارتِ دعوت ساخته نمی‌شود</b>' . "\n\n" . $why);
        }
        return false;
    }

    $dir = rtrim(DATA_DIR, '/') . '/tmp';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $base = defined('TG_API_BASE') ? TG_API_BASE : 'https://api.telegram.org';
    $upload = function ($asEdit) use ($base, $chatId, $mid, $caption, $rm, $dir, $bytes) {
        $tmp = $dir . '/refcard_' . bin2hex(random_bytes(6)) . '.jpg';
        if (@file_put_contents($tmp, $bytes) === false) return [false, '', ''];
        if ($asEdit) {
            $post = ['chat_id' => $chatId, 'message_id' => $mid,
                'media' => json_encode(['type' => 'photo', 'media' => 'attach://photo',
                    'caption' => $caption, 'parse_mode' => 'HTML']),
                'photo' => new CURLFile($tmp, 'image/jpeg', 'card.jpg')];
            if ($rm !== null) $post['reply_markup'] = $rm;
            $ch = curl_init($base . '/bot' . BOT_TOKEN . '/editMessageMedia');
        } else {
            $post = ['chat_id' => $chatId, 'caption' => $caption, 'parse_mode' => 'HTML',
                'photo' => new CURLFile($tmp, 'image/jpeg', 'card.jpg')];
            if ($rm !== null) $post['reply_markup'] = $rm;
            $ch = curl_init($base . '/bot' . BOT_TOKEN . '/sendPhoto');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        @unlink($tmp);
        return [true, $res, $err];
    };

    $applyResult = function ($j) use ($uid) {
        $ph = $j['result']['photo'] ?? [];
        $newFid = '';
        if ($ph) {
            $last = end($ph);
            $newFid = (string)($last['file_id'] ?? '');
        }
        $nid = $j['result']['message_id'] ?? null;
        refCardRemember($uid, $newFid, $nid);
    };

    [$wrote, $res, $curlErr] = $upload((bool)$mid);
    if (!$wrote) return false;
    $j = json_decode((string)$res, true);

    if (!empty($j['ok'])) { $applyResult($j); return true; }
    if (isNotModified($j)) return true;

    if ($mid) {
        [$wrote2, $res2] = $upload(false);
        if ($wrote2) {
            $j2 = json_decode((string)$res2, true);
            if (!empty($j2['ok'])) { $applyResult($j2); return true; }
        }
    }

    if (function_exists('adminAlertOnce')) {
        $desc = $curlErr !== '' ? $curlErr : (string)($j['description'] ?? 'پاسخ نامعتبر از تلگرام');
        adminAlertOnce('refcard_upload_fail_' . substr(md5($desc), 0, 10),
            '🖼 <b>آپلودِ کارتِ دعوت رد شد</b>' . "\n\n" . h($desc));
    }
    return false;
}

function showReferralHistory($uid, $chatId) {
    $rows = (array)(load('ref_log')[(string)$uid] ?? []);
    $t = T('referral_hist_head');
    if (!$rows) {
        $t .= "\n" . T('referral_hist_none');
    } else {
        foreach (array_slice($rows, 0, 20) as $r) {
            $t .= T('referral_hist_row', [
                'date'       => (string)($r['at'] ?? ''),
                'amount'     => fmtNum((float)($r['amount'] ?? 0)),
                'commission' => fmtNum((float)($r['commission'] ?? 0)),
            ]);
        }
    }
    panelShow($uid, $chatId, 'menu', $t, inlineKb([[btnUI('back', 'menu_referral', 'nav')]]));
}

function refWithdraw($uid, $chatId) {
    $u = getUser($uid) ?: [];
    $pending = round((float)($u['ref_pending'] ?? 0), 2);
    if ($pending <= 0) {
        panelShow($uid, $chatId, 'menu', T('referral_wallet_none'),
            inlineKb([[btnUI('back', 'menu_referral', 'nav')]]));
        return;
    }
    addBalance($uid, $pending);
    mutateUser($uid, function (&$user) {
        if ($user !== null) $user['ref_pending'] = 0;
    });
    panelShow($uid, $chatId, 'menu', T('referral_wallet_ok', ['amount' => fmtNum($pending)]),
        inlineKb([[btnUI('back', 'menu_referral', 'nav')]]));
}

function supMainBtn($which, $cb) {
    $m = cfg()['support_main'][$which] ?? [];
    $b = ['text' => trim(($m['emoji'] ?? '') . ' ' . ($m['text'] ?? ''))];
    if (in_array($which, ['direct', 'group'], true) && !empty($m['value'])) $b['url'] = $m['value'];
    else $b['callback_data'] = $cb;
    if (isStyle($m['color'] ?? '')) $b['style'] = $m['color'];
    if (!empty($m['icon'])) $b['icon_custom_emoji_id'] = (string)$m['icon'];
    return $b;
}

function showSupport($uid, $chatId, $replyTo = null) {
    $d = supMainBtn('direct', 'sup_direct');
    $i = supMainBtn('indirect', 'sup_list');
    panelShow($uid, $chatId, 'menu', T('support'), inlineKb([[$d, $i], [supMainBtn('group', 'sup_group')]]), $replyTo);
}

function showSupportIndirect($uid, $chatId, $msgId = null) {
    setState($uid, 'ticket');
    panelShow($uid, $chatId, 'menu', T('sup_ticket'),
        inlineKb([[btnUI('cancel', 'menu_support', 'cancel')]]));
}

function trBtnLabel($which) { return ['ok' => 'تایید', 'cancel' => 'لغو', 'done' => 'تایید شده'][$which] ?? $which; }

function trBtn($which, $cb = null) {
    $m = cfg()['topup_rules']['btns'][$which] ?? [];
    $b = ['text' => trim(($m['emoji'] ?? '') . ' ' . ($m['text'] ?? trBtnLabel($which)))];
    if ($cb !== null) $b['callback_data'] = $cb;
    if (isStyle($m['color'] ?? '')) $b['style'] = $m['color'];
    if (!empty($m['icon'])) $b['icon_custom_emoji_id'] = (string)$m['icon'];
    return $b;
}

function topupRulesGate($uid, $chatId) {
    if (empty(cfg()['topup_rules']['on'])) return true;
    $u = getUser($uid);
    if (!empty($u['topup_rules_ok'])) return true;

    if (!empty($u['topup_rules_msg'])) {
        sendMsg(BOT_TOKEN, $chatId,
            '📌 برای افزایش موجودی، اول باید قوانین را در پیامِ پین‌شده تایید کنید.');
        return false;
    }

    $rows = [[trBtn('cancel', 'tr_cancel'), trBtn('ok', 'tr_ok')]];
    $res = sendMsg(BOT_TOKEN, $chatId, (string)cfg()['topup_rules']['text'], inlineKb($rows));
    $mid = (int)($res['result']['message_id'] ?? 0);
    if ($mid) {
        tg(BOT_TOKEN, 'pinChatMessage', ['chat_id' => $chatId, 'message_id' => $mid, 'disable_notification' => true]);
        mutateUser($uid, function (&$user) use ($mid) {
            if ($user !== null) $user['topup_rules_msg'] = $mid;
        });
    }
    return false;
}

function startTopup($uid, $chatId, $replyTo = null) {
    if (!topupRulesGate($uid, $chatId)) return;
    setState($uid, 'topup_amount');
    panelShow($uid, $chatId, 'wallet', T('topup'), inlineKb([[btnUI('cancel', 'cancel', 'cancel')]]), $replyTo);
}

function topupAmountError($amt) {
    $min = max(1000.0, (float)(cfg()['topup_min'] ?? 10000));
    if ($amt < $min) return 'حداقل مبلغ شارژ ' . fmtNum($min) . ' تومان است.';
    if ($amt > 500000000) return 'مبلغ خیلی بزرگ است.';
    return '';
}

function gwOn() {
    $g = cfg()['gateway'] ?? [];
    return !empty($g['on']) && trim((string)$g['api_key']) !== '' && trim((string)$g['base_url']) !== '';
}

function gwCallbackUrl() {
    $g = cfg()['gateway'] ?? [];
    $b = rtrim(trim((string)$g['base_url']), '/');
    if ($b === '') return '';
    return $b . (str_contains($b, '?') ? '&' : '?') . 'ipn=1';
}

function gwHttp($url, $headers = [], $body = null, $timeout = 20) {
    $ch = curl_init($url);
    $opt = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
    ];
    if ($body !== null) { $opt[CURLOPT_POST] = true; $opt[CURLOPT_POSTFIELDS] = json_encode($body); }
    curl_setopt_array($ch, $opt);
    $res  = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($res === false) return ['ok' => false, 'error' => $err ?: 'curl error'];
    $j = json_decode($res, true);
    return ['ok' => true, 'data' => is_array($j) ? $j : [], 'raw' => $res];
}

function gwCryptoAmount($toman) {
    $g = cfg()['gateway'] ?? [];
    $rate = (float)($g['rate'] ?? 0);
    if ($rate <= 0) return null;
    return round($toman / $rate, 6);
}

function gwCreateInvoice($orderId, $toman) {
    $g   = cfg()['gateway'] ?? [];
    $cb  = gwCallbackUrl();
    $exp = max(5, (int)($g['expire'] ?? 30));
    $amt = gwCryptoAmount($toman);
    $coin = strtoupper(trim((string)($g['coin'] ?? 'USDT')));
    $net  = strtoupper(trim((string)($g['network'] ?? '')));
    $prov = strtolower(trim((string)($g['provider'] ?? 'oxapay')));

    if ($prov === 'custom') {
        $u = strtr((string)($g['custom_url'] ?? ''), [
            '{amount}'   => (string)$toman,
            '{order}'    => rawurlencode($orderId),
            '{callback}' => rawurlencode($cb),
        ]);
        if (trim($u) === '') return [false, null, 'آدرس درگاه دلخواه تنظیم نشده'];
        return [true, ['url' => $u, 'address' => '', 'amount' => $amt, 'coin' => $coin,
                       'expires_at' => time() + $exp * 60, 'invoice' => $orderId], ''];
    }

    if ($prov === 'nowpayments') {
        $body = [
            'price_amount'      => $amt !== null ? $amt : ($toman / 100000),
            'price_currency'    => $amt !== null ? strtolower($coin) : 'usd',
            'pay_currency'      => strtolower($coin . ($net === 'TRC20' ? 'trc20' : '')),
            'order_id'          => $orderId,
            'order_description' => 'Wallet top-up',
            'ipn_callback_url'  => $cb,
            'is_fixed_rate'     => true,
        ];
        $r = gwHttp('https://api.nowpayments.io/v1/invoice',
                    ['x-api-key: ' . trim((string)$g['api_key'])], $body);
        if (empty($r['ok'])) return [false, null, $r['error']];
        $d = $r['data'];
        if (empty($d['invoice_url'])) return [false, null, $d['message'] ?? 'پاسخ نامعتبر درگاه'];
        return [true, ['url' => $d['invoice_url'], 'address' => $d['pay_address'] ?? '',
                       'amount' => $d['pay_amount'] ?? $amt, 'coin' => $coin,
                       'expires_at' => time() + $exp * 60,
                       'invoice' => (string)($d['id'] ?? $orderId)], ''];
    }

    $body = [
        'merchant'    => trim((string)$g['api_key']),
        'amount'      => $amt !== null ? $amt : $toman,
        'currency'    => $amt !== null ? $coin : 'IRT',
        'lifeTime'    => $exp,
        'feePaidByPayer' => 1,
        'orderId'     => $orderId,
        'description' => 'Wallet top-up',
        'callbackUrl' => $cb,
    ];
    if ($net !== '') $body['network'] = $net;
    $r = gwHttp('https://api.oxapay.com/merchants/request', [], $body);
    if (empty($r['ok'])) return [false, null, $r['error']];
    $d = $r['data'];
    if ((string)($d['result'] ?? '') !== '100' || empty($d['payLink']))
        return [false, null, $d['message'] ?? 'پاسخ نامعتبر درگاه'];
    return [true, ['url' => $d['payLink'], 'address' => $d['address'] ?? '',
                   'amount' => $amt, 'coin' => $coin,
                   'expires_at' => time() + $exp * 60,
                   'invoice' => (string)($d['trackId'] ?? $orderId)], ''];
}

function gwCheck($order) {
    $g  = cfg()['gateway'] ?? [];
    $gw = $order['gw'] ?? null;
    if (!$gw || empty($gw['invoice'])) return [false, 'بدون فاکتور'];
    $prov = strtolower(trim((string)($g['provider'] ?? 'oxapay')));

    if ($prov === 'nowpayments') {
        $r = gwHttp('https://api.nowpayments.io/v1/payment/' . rawurlencode($gw['invoice']),
                    ['x-api-key: ' . trim((string)$g['api_key'])]);
        if (empty($r['ok'])) return [false, $r['error']];
        $st = strtolower((string)($r['data']['payment_status'] ?? ''));
        return [in_array($st, ['finished', 'confirmed'], true), $st ?: 'نامشخص'];
    }
    if ($prov === 'custom') return [false, 'در حالت دلخواه، تایید فقط با IPN انجام می‌شود'];

    $r = gwHttp('https://api.oxapay.com/merchants/inquiry', [], [
        'merchant' => trim((string)$g['api_key']),
        'trackId'  => $gw['invoice'],
    ]);
    if (empty($r['ok'])) return [false, $r['error']];
    $st = strtolower((string)($r['data']['status'] ?? ''));
    return [in_array($st, ['paid', 'confirming'], true) && $st === 'paid', $st ?: 'نامشخص'];
}

function gwSettle($orderId, $note = 'پرداخت خودکار درگاه') {
    $o = Order::get($orderId);
    if (!$o) return false;
    if (in_array($o['status'], [Order::APPROVED, Order::REJECTED], true)) return false;

    Order::attachReceipt($orderId, 'text', $note);
    [$ok, ] = Order::approve($orderId, ADMIN_ID);
    if (!$ok) return false;

    completeApprovedOrder(Order::get($orderId));
    return true;
}

function handleIpn() {
    $g   = cfg()['gateway'] ?? [];
    $raw = file_get_contents('php://input');
    $d   = json_decode($raw, true);
    if (!is_array($d)) { http_response_code(400); echo 'bad'; return; }

    $prov = strtolower(trim((string)($g['provider'] ?? 'oxapay')));
    $orderId = ''; $paid = false;

    if ($prov === 'nowpayments') {
        $sig = $_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ?? '';
        $sorted = $d; ksort($sorted);
        $calc = hash_hmac('sha512', json_encode($sorted, JSON_UNESCAPED_SLASHES),
                          trim((string)$g['ipn_secret']));
        if (!$sig || !hash_equals($calc, $sig)) { http_response_code(403); echo 'sig'; return; }
        $orderId = (string)($d['order_id'] ?? '');
        $paid = in_array(strtolower((string)($d['payment_status'] ?? '')), ['finished', 'confirmed'], true);
    } else {
        $sig = $_SERVER['HTTP_HMAC'] ?? '';
        $calc = hash_hmac('sha512', $raw, trim((string)$g['api_key']));
        if (!$sig || !hash_equals($calc, $sig)) { http_response_code(403); echo 'sig'; return; }
        $orderId = (string)($d['orderId'] ?? '');
        $paid = strtolower((string)($d['status'] ?? '')) === 'paid';
    }

    if ($orderId === '') { http_response_code(400); echo 'no order'; return; }
    if ($paid) gwSettle($orderId);
    http_response_code(200);
    echo 'ok';
}

function createOrderAndAsk($uid, $chatId, $username, $amount) {
    $g = cfg()['gateway'] ?? [];

    if (gwOn() && $amount >= (float)($g['min'] ?? 0)) {
        $oid = Order::create($uid, $username, $amount);
        [$ok, $inv, $err] = gwCreateInvoice($oid, $amount);
        if ($ok) {
            Order::set($oid, function (&$x) use ($inv) { $x['gw'] = $inv; });
            sendMsg(BOT_TOKEN, $chatId, gwInvoiceText(Order::get($oid)), gwInvoiceKb(Order::get($oid)));
            return $oid;
        }
        Order::delete($oid);
        chTechAlert(
            "⚠️ <b>درگاه پرداخت جواب نداد</b>\n\n<code>" . h($err) . "</code>\n\n" .
            "فعلا کارت به کارت استفاده می‌شود. /panel ← 💠 درگاه پرداخت");
    }

    $w = cfg()['wallets'] ?? [];
    $card = trim((string)($w['card'] ?? ''));
    if ($card === '') {
        sendMsg(BOT_TOKEN, $chatId,
            "⚠️ روش پرداخت هنوز تنظیم نشده است.\nلطفا با پشتیبانی تماس بگیرید.");
        chTechAlert(
            "🔴 <b>مقصد پرداخت خالی است!</b>\n\n" .
            "کاربر <code>{$uid}</code> می‌خواست <b>" . fmtNum($amount) . "</b> تومان شارژ کند.\n\n" .
            "پنل وب ← ⚙️ تنظیمات ← شماره کارت را پر کنید.");
        return null;
    }
    if (trim((string)($w['card_name'] ?? '')) !== '') $card .= "\nبه نام: " . $w['card_name'];

    $oid = Order::create($uid, $username, $amount);

    $t  = "💳 <b>درخواست شارژ ثبت شد</b>\n\n";
    $t .= "💰 مبلغ: <b>" . fmtNum($amount) . " تومان</b>\n";
    $t .= "🏦 روش: کارت به کارت\n\n";
    $t .= "💠 مقصد پرداخت:\n<code>" . h($card) . "</code>\n\n";
    $t .= "🧾 کد پیگیری: <code>" . h($oid) . "</code>\n\n";
    $t .= "👇 مبلغ را واریز کنید، بعد دکمه «ارسال رسید» را بزنید.";

    sendMsg(BOT_TOKEN, $chatId, $t, inlineKb([
        [['text' => UT('receipt'), 'callback_data' => 'rcpt_' . $oid, 'style' => gs('confirm') ?: null]],
        [['text' => UT('cancel'), 'callback_data' => 'ocancel_' . $oid, 'style' => gs('cancel') ?: null]],
    ]));
    return $oid;
}

function ordersArchive($days = 0, $limit = 4000) {
    $days = $days > 0 ? $days : (int)(cfg()['orders_keep_days'] ?? 14);
    if ($days <= 0) return 0;
    $cut = time() - $days * 86400;

    $db = ordersDb();
    if (!$db) return 0;

    $stmt = $db->prepare('SELECT id, data FROM orders WHERE status = :s1 OR status = :s2');
    $stmt->bindValue(':s1', Order::APPROVED, SQLITE3_TEXT);
    $stmt->bindValue(':s2', Order::REJECTED, SQLITE3_TEXT);
    $res = $stmt->execute();

    $moved = [];
    while (count($moved) < $limit && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
        $o = json_decode($row['data'], true);
        if (!is_array($o)) continue;
        $when = strtotime((string)($o['decided_at'] ?: $o['created_at'] ?? '')) ?: 0;
        if ($when === 0 || $when > $cut) continue;
        $moved[(string)$row['id']] = $o;
    }
    if (!$moved) return 0;

    $db->exec('BEGIN');
    $del = $db->prepare('DELETE FROM orders WHERE id = :id');
    $ins = $db->prepare('INSERT OR REPLACE INTO orders_old (id, data) VALUES (:id, :data)');
    foreach ($moved as $id => $o) {
        $del->bindValue(':id', $id, SQLITE3_TEXT); $del->execute(); $del->reset();
        $ins->bindValue(':id', $id, SQLITE3_TEXT);
        $ins->bindValue(':data', json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        $ins->execute(); $ins->reset();
    }
    $db->exec('COMMIT');
    return count($moved);
}

function gwPoll($limit = 10) {
    if (!gwOn()) return 0;
    $n = 0;
    foreach (Order::pendingGw($limit) as $o) {
        if ($n >= $limit) break;
        if (time() > (int)($o['gw']['expires_at'] ?? 0) + 3600) continue;
        $n++;
        [$paid, ] = gwCheck($o);
        if ($paid) gwSettle($o['id']);
    }
    return $n;
}

function gwInvoiceText($order) {
    $gw = $order['gw'] ?? [];
    $left = max(0, (int)($gw['expires_at'] ?? 0) - time());
    $mm = (int)floor($left / 60); $ss = $left % 60;

    $t  = "💠 <b>پرداخت خودکار</b>\n\n";
    $t .= "💰 مبلغ: <b>" . fmtNum($order['amount']) . " تومان</b>\n";
    if (!empty($gw['amount'])) $t .= "🪙 معادل: <b>" . $gw['amount'] . ' ' . h($gw['coin'] ?? '') . "</b>\n";
    if (!empty($gw['address'])) {
        $t .= "\n📥 آدرس واریز" . (!empty(cfg()['gateway']['network'])
              ? ' (' . h(cfg()['gateway']['network']) . ')' : '') . ":\n";
        $t .= "<code>" . h($gw['address']) . "</code>\n";
    }
    $t .= "\n⏳ مهلت: <b>" . ($left > 0 ? sprintf('%02d:%02d', $mm, $ss) : 'تمام شد') . "</b>\n";
    $t .= "🧾 کد پیگیری: <code>" . h($order['id']) . "</code>\n\n";
    $t .= $left > 0
        ? "به‌محض واریز، کیف پول شما <b>خودکار</b> شارژ می‌شود و همین‌جا خبرش را می‌دهیم.\n" .
          "نیازی به فرستادن رسید نیست."
        : "⌛️ مهلت این فاکتور تمام شد. دوباره درخواست شارژ بدهید.";
    return $t;
}

function gwInvoiceKb($order) {
    $gw = $order['gw'] ?? [];
    $rows = [];
    if (!empty($gw['url'])) $rows[] = [['text' => '💳 صفحه پرداخت', 'url' => $gw['url'],
                                        'style' => gs('buy') ?: null]];
    $rows[] = [btnCb('🔄 بررسی پرداخت', 'gwchk_' . $order['id'], 'confirm')];
    $rows[] = [btnCb(UT('cancel'), 'ocancel_' . $order['id'], 'cancel')];
    return inlineKb($rows);
}

function botUsername() {
    static $u = null;
    if ($u !== null) return $u;
    $c = load('config');
    $tokHash = md5(BOT_TOKEN);
    if (!empty($c['bot_username']) && ($c['bot_username_tok'] ?? '') === $tokHash) return $u = $c['bot_username'];
    $r = tg(BOT_TOKEN, 'getMe', [], 8);
    $u = $r['result']['username'] ?? '';
    if ($u !== '') cfgSet(function (&$x) use ($u, $tokHash) { $x['bot_username'] = $u; $x['bot_username_tok'] = $tokHash; });
    return $u;
}

function adminAlertOnce($key, $text, $everySeconds = 3600) {
    $fresh = mutate('alerts', function (&$a) use ($key, $everySeconds) {
        $last = (int)($a[$key] ?? 0);
        if (time() - $last < $everySeconds) return false;
        $a[$key] = time();
        foreach ($a as $k => $t) if (time() - (int)$t > 86400 * 7) unset($a[$k]);
        return true;
    });
    if ($fresh) {
        if (function_exists('chTechAlert')) chTechAlert($text);
        else notifyAdmins($text);
    }
    return (bool)$fresh;
}

function completeApprovedOrder($order) {
    $uid = (int)$order['user_id'];
    $t = T('topup_ok', ['amount' => fmtNum($order['amount']),
                        'balance' => fmtNum((float)(getUser($uid)['balance'] ?? 0))]);
    if (trim($t) === '') $t = '✅ حساب شما <b>' . fmtNum($order['amount']) . '</b> تومان شارژ شد.';
    sendMsg(BOT_TOKEN, $uid, $t, maOpenKb());
}

function notifyAdminOrder($orderId) {
    $o = Order::get($orderId);
    if (!$o) return;
    $uname = $o['username'] ? '@' . $o['username'] : '—';

    $text  = "🧾 <b>درخواست شارژ — منتظر تایید</b>\n\n";
    $text .= "👤 کاربر: " . h($uname) . " (<code>{$o['user_id']}</code>)\n";
    $text .= "💰 " . fmtNum($o['amount']) . ' ' . h($o['currency']) . "\n";
    $text .= "🧾 <code>" . h($o['id']) . "</code>\n";
    $text .= "📅 " . h($o['created_at']) . "\n\n";
    $text .= $o['receipt_type'] === 'text'
        ? "رسید:\n<code>" . h($o['receipt']) . "</code>"
        : "رسید: تصویر ↓";

    $rows = [[
        ['text' => UT('confirm'), 'callback_data' => 'aok_' . $o['id']],
        ['text' => UT('reject'),   'callback_data' => 'ano_' . $o['id']],
    ]];

    if ($o['receipt_type'] === 'photo') {
        tg(BOT_TOKEN, 'sendPhoto', [
            'chat_id' => ADMIN_ID, 'photo' => $o['receipt'],
            'caption' => $text, 'parse_mode' => 'HTML',
            'reply_markup' => json_encode(inlineKb($rows)),
        ]);
    } else {
        sendMsg(BOT_TOKEN, ADMIN_ID, $text, inlineKb($rows));
    }
}

function admHome($chatId, $msgId = null) {
    $text  = "👑 <b>پنل مدیریت</b>\n\n";
    $text .= "☎️ فروش: <b>" . (maReady() && numReady() ? '🟢 باز' : '🔴 بسته') . "</b>";
    $text .= " · باز: <b>" . number_format(MaOrder::countBy(MaOrder::PAID)) . "</b>";
    $text .= " · تحویل‌شده: <b>" . number_format(MaOrder::countBy(MaOrder::DONE)) . "</b>\n\n";
    $text .= "تنظیم‌های لحظه‌ای همین‌جا. کشورها، قیمت‌ها، سفارش‌ها و کاربران در پنلِ وب.\n";

    $rows = [
        [btnCb('☎️ شماره مجازی', 'num_home', 'admin'),  btnCb('🚀 مینی‌اپ', 'maadm_home', 'admin')],
        [btnCb('💳 پرداخت', 'ag_pay', 'admin'),         btnCb('📡 کانال‌های گزارش', 'ch_home', 'admin')],
        [btnCb('🎮 بازی‌ها', 'ag_games', 'admin'),      btnCb('💎 الماس', 'dm_home', 'admin')],
        [btnCb('💹 قیمت لحظه‌ای', 'px_home', 'admin'),  btnCb('🎨 ظاهر و متن‌ها', 'ag_look', 'admin')],
        [btnCb('📢 پیام همگانی', 'adm_bc', 'admin'),    btnCb('🩺 چکاپِ بخش‌ها', 'adm_check', 'info')],
        [btnCb('🌐 پنل وب — کشورها، قیمت‌ها، کاربران، سفارش‌ها', 'adm_web', 'info')],
        [btnCb(UT('home'), 'home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $text, inlineKb($rows));
}

function admWriteTestText() {
    $d = rtrim(DATA_DIR, '/');
    $t = "🩺 <b>تست نوشتن روی دیسک</b>\n\n";
    $t .= '📁 پوشه: <code>' . h($d) . "</code>\n";
    $t .= (is_dir($d) ? '✅' : '🔴') . " پوشه هست\n";
    $t .= (is_writable($d) ? '✅' : '🔴') . " قابل نوشتن است\n\n";

    $f1 = $d . '/.wtest';
    $ok1 = @file_put_contents($f1, 'x') !== false;
    $t .= ($ok1 ? '✅' : '🔴') . " نوشتن فایل\n";
    @unlink($f1);

    $f2 = $d . '/.wtest.lock';
    $fp = @fopen($f2, 'c');
    $ok2 = ($fp !== false) && flock($fp, LOCK_EX);
    if ($fp) { @flock($fp, LOCK_UN); @fclose($fp); }
    @unlink($f2);
    $t .= ($ok2 ? '✅' : '🔴') . " قفل انحصاری (flock)\n";

    $dir = $d . '/.upd';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $f3 = $dir . '/.wtest';
    @unlink($f3);
    $h1 = @fopen($f3, 'x');
    $h2 = @fopen($f3, 'x');
    $ok3 = ($h1 !== false) && ($h2 === false);
    if ($h1) fclose($h1);
    if ($h2) fclose($h2);
    @unlink($f3);
    $t .= ($ok3 ? '✅' : '🔴') . " جلوگیری از پیام تکراری\n";

    $n = count(glob($dir . '/*') ?: []);
    $t .= "\n📊 نشانه‌های ثبت‌شده‌ی آپدیت: <b>" . $n . "</b>\n";

    if ($ok1 && $ok2 && $ok3) {
        $t .= "\n✅ <b>همه‌چیز سالم است.</b>\n";
        $t .= 'اگر باز پیام تکراری دیدید، یعنی وبهوک دوبار ثبت شده — ' .
              "از پنل وب ← 🩺 تشخیص ← «🔗 تنظیم وبهوک» یک بار دوباره ست کنید.";
    } else {
        $t .= "\n🔴 <b>پوشه‌ی داده قابل نوشتن نیست.</b>\n";
        $t .= "در cPanel → File Manager روی پوشه‌ی <code>data_master</code> راست‌کلیک کنید،\n" .
              "Change Permissions را بزنید و دسترسی را روی <b>755</b> (یا اگر نشد ۷۷۵) بگذارید.\n\n" .
              "تا این درست نشود، پیام تکراری و گم شدن موجودی ادامه دارد.";
    }
    return $t;
}

function admLeakTestText() {
    $base = function_exists('maBaseUrl') ? maBaseUrl() : '';
    if ($base === '') {
        return "⚠️ اول آدرس عمومی را ثبت کنید تا بشود از بیرون امتحان کرد.\n\n" .
               "پنل ← 🚀 مینی‌اپ ← 🔗 آدرس عمومی";
    }
    $dir  = basename(rtrim(DATA_DIR, '/'));
    $root = preg_replace('#/[^/]+$#', '', $base);

    $t = "🔒 <b>تست نشتی داده</b>\n\nاز بیرون امتحان می‌کنم که فایل‌های حساس خوانده می‌شوند یا نه…\n\n";
    $leaks = [];
    foreach (['config.json', 'users.sqlite', 'orders.sqlite'] as $f) {
        $url = $root . '/' . $dir . '/' . $f;
        [$body, $err] = maHttpRaw($url, 8);
        $open = is_string($body) && strlen($body) > 2 &&
                (str_starts_with(ltrim($body), '{') || str_starts_with(ltrim($body), '[') ||
                 str_starts_with($body, 'SQLite format 3'));
        if ($open) $leaks[] = $f;
        $t .= ($open ? '🔴' : '✅') . ' <code>' . h($dir . '/' . $f) . '</code>' .
              ($open ? ' — <b>از بیرون باز است!</b>' : ' — بسته') . "\n";
    }

    if ($leaks) {
        $t .= "\n🚨 <b>همین حالا باید بسته شود.</b>\n";
        $t .= "این فایل‌ها شماره کارت، کلید API، و موجودی همه‌ی کاربران را دارند.\n\n";
        $t .= "<b>اگر سرورتان nginx است</b>، این را به کانفیگ اضافه کنید:\n";
        $t .= "<code>location ~ /" . h($dir) . "/ { deny all; return 404; }</code>\n\n";
        $t .= "<b>اگر آپاچی است</b> و باز هم باز مانده، یعنی <code>AllowOverride</code> " .
              "خاموش است — از پشتیبانی هاست بخواهید روشنش کند.\n\n";
        $t .= "<b>راه مطمئن‌تر:</b> پوشه‌ی داده را کلا بیرون از ریشه‌ی وب ببرید و در " .
              "<code>config.local.php</code> بنویسید:\n" .
              "<code>define('DATA_DIR', '/home/user/private/data_master');</code>";
    } else {
        $t .= "\n✅ هیچ‌کدام از بیرون خوانده نمی‌شوند. پوشه‌ی داده امن است.";
    }
    return $t;
}

function admGroups() {
    return [
        'pay' => ['💳 <b>پرداخت</b>', 'مقصد پول و درگاه خودکار.', [
            [['💳 مقصد پرداخت — شماره کارت', 'adm_pay']],
            [['💠 درگاه پرداخت', 'adm_gw']],
        ]],
        'look' => ['🎨 <b>ظاهر و متن‌ها</b>', 'هرچه کاربر می‌بیند: دکمه‌ها، متن‌ها، رنگ‌ها.', [
            [['🎨 دکمه‌ها', 'ebuttons'], ['📝 متن‌ها', 'etexts']],
            [['🛍 پیام و دکمه‌ی فروشگاه', 'eshop']],
            [['💠 رنگ دکمه‌های شیشه‌ای', 'eglass']],
            [['🔤 فونت‌ها', 'fnt_home']],
            [['📞 دکمه‌های پشتیبانی', 'esup'], ['🔒 عضویت اجباری', 'adm_join']],
            [['📋 قوانین شارژ', 'etop_home'], ['🔗 دکمه‌ی زیرِ «اعتماد»', 'etrust']],
            [['👥 دکمه‌های زیرمجموعه', 'eref_home']],
        ]],
        'games' => ['🎮 <b>بازی‌ها</b>', 'همه‌ی بازی‌ها و ابزارهای گروه و تنظیماتشان.', [
            [['🎮 چالش و دوز', 'gm_home'], ['💣 مین‌یاب', 'mn_home']],
            [['🏆 تاپ الماسی', 'tp_home']],
            [['🏦 بانک الماس', 'bk_home']],
            [['🧠 چالش روزانه', 'qz_home'], ['🌐 ترجمه', 'tl_home']],
        ]],
    ];
}

function admGroup($chatId, $msgId, $key) {
    $g = admGroups()[$key] ?? null;
    if (!$g) { admHome($chatId, $msgId); return; }
    [$title, $desc, $rows] = $g;

    $kb = [];
    foreach ($rows as $row) {
        $line = [];
        foreach ($row as [$label, $data]) $line[] = btnCb($label, $data, 'admin');
        $kb[] = $line;
    }
    $kb[] = [btnCb(UT('back'), 'adm_home', 'nav')];

    $text = $title . "\n\n" . $desc;
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($kb));
    else sendMsg(BOT_TOKEN, $chatId, $text, inlineKb($kb));
}

function admPay($chatId, $msgId = null) {
    $w = cfg()['wallets'];
    $card = trim((string)($w['card'] ?? ''));

    $text  = "💳 <b>مقصد پرداخت</b>\n\n";
    $text .= "این همان چیزی است که هنگام شارژ کارت‌به‌کارت، در ربات و مینی‌اپ به مشتری نشان داده می‌شود.\n\n";
    $text .= "💳 شماره کارت: " . ($card !== '' ? "<code>" . h($card) . "</code>" : "<b>خالی</b>") . "\n";
    $text .= "👤 به نام: " . (trim((string)($w['card_name'] ?? '')) !== '' ? h($w['card_name']) : '—') . "\n";
    if ($card === '') $text .= "\n⚠️ تا شماره کارت خالی است، هیچ فاکتور کارت‌به‌کارتی صادر نمی‌شود.";

    $rows = [
        [btnCb('💳 شماره کارت', 'payc', 'admin'), btnCb('👤 به نام', 'payn', 'admin')],
        [btnCb(UT('back'), 'adm_home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $text, inlineKb($rows));
}

function admGateway($chatId, $msgId) {
    $g = cfg()['gateway'] ?? [];
    $prov = ['oxapay' => 'OxaPay', 'nowpayments' => 'NOWPayments', 'custom' => 'دلخواه'][$g['provider'] ?? 'oxapay'] ?? '—';

    $text  = "💠 <b>درگاه پرداخت خودکار</b>\n\n";
    $text .= "وضعیت: " . (gwOn() ? '✅ آماده' : (!empty($g['on']) ? '⚠️ روشن ولی ناقص' : '❌ خاموش')) . "\n";
    $text .= "درگاه: <b>{$prov}</b>\n";
    $text .= "کلید API: " . (trim((string)$g['api_key']) !== ''
             ? '✅ ثبت شده (' . mb_substr($g['api_key'], 0, 4) . '…)' : '<b>خالی</b>') . "\n";
    $text .= "آدرس بازگشت: " . (trim((string)$g['base_url']) !== ''
             ? '<code>' . h(gwCallbackUrl()) . '</code>' : '<b>خالی</b>') . "\n";
    $text .= "ارز: <b>" . h($g['coin'] ?? '—') . "</b>" .
             (!empty($g['network']) ? ' · ' . h($g['network']) : '') . "\n";
    $text .= "نرخ: " . ((float)($g['rate'] ?? 0) > 0
             ? fmtNum($g['rate']) . ' تومان به ازای هر ۱ ' . h($g['coin'] ?? '')
             : 'تبدیل با خود درگاه') . "\n";
    $text .= "مهلت هر فاکتور: <b>" . (int)($g['expire'] ?? 30) . "</b> دقیقه\n";
    $text .= "حداقل شارژ با درگاه: <b>" . fmtNum($g['min'] ?? 0) . "</b> تومان\n\n";

    if (!gwOn()) {
        $text .= "برای راه‌اندازی:\n";
        $text .= "۱) در OxaPay یا NOWPayments حساب بسازید و آدرس ولت خودتان را آنجا ثبت کنید\n";
        $text .= "۲) کلید API (Merchant Key) را بگیرید و اینجا بگذارید\n";
        $text .= "۳) آدرس عمومی همین فایل ربات را بگذارید\n";
        $text .= "۴) در پنل درگاه، آدرس بازگشت (Callback/IPN) را همان چیزی بگذارید که اینجا نشان داده می‌شود\n\n";
        $text .= "بعد از آن، پول مستقیم به ولت خودتان می‌رود و کیف پول مشتری خودکار شارژ می‌شود.";
    } else {
        $text .= "✅ مشتری «افزایش موجودی» بزند، لینک پرداخت و آدرس ولت می‌گیرد.\n";
        $text .= "به‌محض واریز، کیف پولش خودکار شارژ می‌شود.";
    }

    $rows = [
        [btnCb(!empty($g['on']) ? '❌ خاموش کردن' : '✅ روشن کردن', 'gwx', 'info'),
         btnCb('🔀 درگاه: ' . $prov, 'gwp', 'admin')],
        [btnCb('🔑 کلید API', 'gwk', 'admin'), btnCb('🌐 آدرس ربات', 'gwu', 'admin')],
        [btnCb('🪙 ارز', 'gwc', 'admin'), btnCb('🔗 شبکه', 'gwn', 'admin')],
        [btnCb('💱 نرخ تومان', 'gwr', 'admin'), btnCb('⏳ مهلت', 'gwe', 'admin')],
        [btnCb('🔢 حداقل مبلغ', 'gwm', 'admin')],
    ];
    if (($g['provider'] ?? '') === 'nowpayments') $rows[] = [btnCb('🔐 کلید IPN', 'gws', 'admin')];
    if (($g['provider'] ?? '') === 'custom')      $rows[] = [btnCb('🔗 آدرس دلخواه', 'gwcu', 'admin')];
    $rows[] = [btnCb('🧪 تست ساخت فاکتور', 'gwtest', 'confirm')];
    $rows[] = [btnUI('back', 'adm_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function admJoin($chatId, $msgId) {
    $j  = cfg()['join'] ?? [];
    $ch = array_values((array)($j['channels'] ?? []));
    $text  = "🔒 <b>عضویت اجباری</b>\n\n";
    $text .= "وضعیت: " . (!empty($j['on']) ? '✅ روشن' : '❌ خاموش') . " · کانال‌ها: <b>" . count($ch) . "</b>\n\n";
    foreach ($ch as $i => $c)
        $text .= ($i + 1) . '. <b>' . h((string)($c['title'] ?? $c['chat_id'])) . '</b> — <code>' . h((string)$c['chat_id']) . "</code>\n";
    if ($ch) $text .= "\n";
    $text .= "🔘 فقط <b>یک دکمه</b> نشان داده می‌شود؛ لینکِ کانال‌ها داخلِ خودِ متن است.\n";
    $text .= "با <code>{channels}</code> فهرستِ کانال‌ها خودکار می‌آید.";
    $rows = [[btnCb(!empty($j['on']) ? '✅ روشن است' : '❌ خاموش است', 'jno', 'info'),
              btnCb('➕ افزودن کانال', 'jna', 'confirm')]];
    foreach ($ch as $i => $c)
        $rows[] = [btnCb('🗑 ' . mb_substr((string)($c['title'] ?? $c['chat_id']), 0, 28), 'jnd_' . $i, 'reject')];
    $rows[] = [btnCb('✏️ متن قفل', 'jnt', 'admin'), btnCb('🔘 متن دکمه', 'jnb', 'admin')];
    $rows[] = [btnUI('back', 'ag_look', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function joinChannelRef($msg) {
    $f = $msg['forward_origin']['chat'] ?? ($msg['forward_from_chat'] ?? null);
    if (is_array($f) && !empty($f['id'])) return (string)$f['id'];
    $t = trim((string)($msg['text'] ?? ''));
    if (preg_match('#^(?:https?://)?(?:t\.me|telegram\.me)/([A-Za-z][A-Za-z0-9_]{3,31})/?$#i', $t, $m)) return '@' . $m[1];
    if (preg_match('/^@?([A-Za-z][A-Za-z0-9_]{3,31})$/', $t, $m)) return '@' . $m[1];
    if (preg_match('/^-100\d{5,15}$/', $t)) return $t;
    return '';
}

function joinAddChannel($cid, $title = '', $url = '') {
    $info = tg(BOT_TOKEN, 'getChat', ['chat_id' => $cid], 8);
    if (empty($info['ok'])) return [false, 'ربات به این کانال دسترسی ندارد: ' . ($info['description'] ?? '—')];
    $r  = $info['result'];
    $id = (string)($r['id'] ?? $cid);
    $me = tg(BOT_TOKEN, 'getChatMember', ['chat_id' => $id, 'user_id' => (int)strtok(BOT_TOKEN, ':')], 8);
    if (!in_array($me['result']['status'] ?? '', ['administrator', 'creator'], true))
        return [false, 'اول ربات را در این کانال ادمین کنید تا بتواند عضویت را چک کند.'];
    $title = trim((string)$title) ?: (string)($r['title'] ?? $id);
    $url   = trim((string)$url);
    if ($url === '' && !empty($r['username'])) $url = 'https://t.me/' . $r['username'];
    if ($url === '') {
        $inv = tg(BOT_TOKEN, 'exportChatInviteLink', ['chat_id' => $id], 8);
        $url = (string)($inv['result'] ?? '');
    }
    $keys = [$id, (string)$cid];
    if (!empty($r['username'])) $keys[] = '@' . $r['username'];
    $dup = false;
    cfgSet(function (&$c) use ($id, $title, $url, $keys, &$dup) {
        if (!is_array($c['join']['channels'] ?? null)) $c['join']['channels'] = [];
        foreach ($c['join']['channels'] as $x) if (in_array((string)($x['chat_id'] ?? ''), $keys, true)) { $dup = true; return; }
        $c['join']['channels'][] = ['chat_id' => $id, 'title' => $title, 'url' => $url];
        $c['join']['on'] = true;
    });
    return $dup ? [false, 'این کانال از قبل در فهرست است.'] : [true, $title];
}

function textLabels() {
    return [
        'welcome' => '👋 خوش‌آمد', 'account' => '👤 حساب کاربری',
        'trust' => '💚 اعتماد', 'support' => '📞 سربرگ پشتیبانی',
        'referral' => '👥 زیرمجموعه', 'referral_share' => '📤 متن ارسال لینک دعوت',
        'topup' => '➕ افزایش موجودی',
        'shop' => '☎️ متنِ دکمه‌ی خرید (ثبت سفارش)', 'shop_closed' => '🔒 فروش بسته است',
        'orders_head' => '📊 سربرگ شماره‌های من', 'orders_empty' => '📊 هنوز شماره‌ای نگرفته',
        'orders_item' => '📊 قالب هر شماره',
        'receipt_ask' => '🧾 درخواست رسید',
        'receipt_ok' => '✅ رسید ثبت شد',
        'rejected' => '❌ رد درخواست شارژ', 'no_balance' => '❌ موجودی کم',
        'banned' => '🚫 کاربر مسدود', 'topup_ok' => '✅ متن شارژ شدن حساب',
        'sup_ticket' => '💬 پیام ارتباط غیر مستقیم', 'sup_sent' => '✅ پیام ارسال شد',
    ];
}

function uiTextLabels() {
    return [
        'back' => 'بازگشت', 'home' => 'منوی اصلی', 'cancel' => 'انصراف',
        'confirm' => 'تایید', 'reject' => 'رد', 'panel' => 'پنل',
        'receipt' => 'ارسال رسید', 'topup' => 'افزایش موجودی',
        'my_orders' => 'شماره‌های من', 'open_app' => 'شماره مجازی (پایینِ ثبت سفارش)',
        'open' => 'باز کردن',
        'shop_orders' => 'مشاهده‌ی سفارش‌ها (بالای ثبت سفارش)',
        'open_tgs' => 'خدمات تلگرام (وسطِ ثبت سفارش)',
        'open_igs' => 'خدمات اینستاگرام (وسطِ ثبت سفارش)',
    ];
}

function glassRoleLabels() {
    return [
        'buy' => '☎️ خرید و شارژ', 'confirm' => '✅ تایید', 'cancel' => '↩️ انصراف',
        'reject' => '🗑 رد و حذف', 'nav' => '◀️ بازگشت و منو', 'info' => 'ℹ️ اطلاعات',
        'link' => '📱 باز کردن فروشگاه و لینک‌ها',
    ];
}

function nextStyle($cur) {
    $keys = array_keys(styleMap());
    $i = array_search($cur, $keys, true);
    return $keys[(($i === false ? 0 : $i) + 1) % count($keys)];
}

function edButtons($chatId, $msgId) {
    $c = cfg();
    $text  = "🎨 <b>ویرایش دکمه‌ها</b>\n\n";
    $text .= "حالت: <b>" . ($c['ui']['mode'] === 'glass' ? 'شیشه‌ای' : 'منو') . "</b>\n";
    $text .= "کیبورد چسبان: <b>" . (!empty($c['ui']['persistent']) ? 'روشن' : 'خاموش') . "</b>\n\n";
    $text .= "روی هر دکمه بزنید تا ویرایشش کنید:";

    $rows = [];
    foreach ($c['buttons'] as $id => $b) {
        $col = styleMap()[$b['color'] ?? 'none'] ?? '';
        $rows[] = [btnCb((!empty($b['on']) ? '✅ ' : '❌ ') . btnLabel($b, false) . '  ' . mb_substr($col, 0, 2),
                         'eb_' . $id, 'info')];
    }
    $rows[] = [
        btnCb($c['ui']['mode'] === 'glass' ? '🔄 به منو' : '🔄 به شیشه‌ای', 'ebmode', 'admin'),
        btnCb('📐 چیدمان', 'eblay', 'admin'),
    ];
    $rows[] = [
        btnCb(!empty($c['ui']['persistent']) ? '📌 چسبان: روشن' : '📌 چسبان: خاموش', 'ebpin', 'admin'),
        btnCb('➕ دکمه جدید', 'ebnew', 'confirm'),
    ];
    $rows[] = [btnCb(UT('back'), 'adm_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function edButton($chatId, $msgId, $id) {
    $b = cfg()['buttons'][$id] ?? null;
    if (!$b) { edButtons($chatId, $msgId); return; }

    $text  = "🎨 <b>ویرایش دکمه</b>\n\n";
    $text .= "نمایش: " . h(btnLabel($b, true)) . "\n";
    $text .= "متن: <code>" . h($b['text']) . "</code>\n";
    $text .= "ایموجی: " . h($b['emoji'] ?: '—') . "\n";
    $text .= "رنگ: " . (styleMap()[$b['color'] ?? 'none'] ?? '—') . "\n";
    $text .= "✨ پریمیوم: " . (!empty($b['icon']) ? '<code>' . h($b['icon']) . '</code>' : '—') . "\n";
    $text .= "ردیف: " . (int)($b['row'] ?? 0) . "  |  ترتیب: " . (int)($b['order'] ?? 0) . "\n";
    $text .= "وضعیت: " . (!empty($b['on']) ? '✅ روشن' : '❌ خاموش');
    if (!empty($b['action'])) {
        $text .= "\nنوع: " . h(['text' => 'متن', 'url' => 'لینک'][$b['action']] ?? $b['action']);
    }

    $rows = [
        [btnCb('✏️ متن', 'ebt_' . $id, 'admin'), btnCb('😀 ایموجی', 'ebe_' . $id, 'admin')],
        [btnCb('🎨 رنگ', 'ebc_' . $id, 'admin'), btnCb('✨ پریمیوم', 'ebi_' . $id, 'admin')],
        [btnCb('📐 ردیف', 'ebr_' . $id, 'admin'), btnCb('🔢 ترتیب', 'ebo_' . $id, 'admin')],
        [btnCb(!empty($b['on']) ? '❌ خاموش کن' : '✅ روشن کن', 'ebx_' . $id, 'info')],
    ];
    if (!empty($b['action'])) {
        $rows[] = [btnCb('📝 مقدار', 'ebv_' . $id, 'admin'), btnCb('🗑 حذف دکمه', 'ebd_' . $id, 'reject')];
    }
    $tk = btnTextKey($id);
    if ($tk !== '') $rows[] = [btnCb('📝 متنی که این دکمه نشان می‌دهد', 'et_' . $tk, 'confirm')];
    if ($id === 'buy' || $id === 'orders') $rows[] = [btnCb('📱 متن دکمه‌ی ورود به مینی‌اپ', 'eu_open_app', 'confirm')];
    $rows[] = [btnCb(UT('back'), 'ebuttons', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function btnTextKey($id) {
    return [
        'buy' => 'shop', 'account' => 'account', 'topup' => 'topup', 'referral' => 'referral',
        'orders' => 'orders_head', 'support' => 'support', 'trust' => 'trust',
    ][$id] ?? '';
}

function edSupMain($chatId, $msgId) {
    $c = cfg()['support_main'] ?? [];
    $t = "📞 <b>دکمه‌های پشتیبانی</b>\n\n";
    foreach (['direct' => 'ارتباط مستقیم', 'indirect' => 'ارتباط غیر مستقیم', 'group' => 'گروه'] as $k => $lbl) {
        $m = $c[$k] ?? [];
        $t .= '🔘 <b>' . $lbl . "</b>\n";
        $t .= '   نمایش: ' . h(trim((string)($m['emoji'] ?? '') . ' ' . (string)($m['text'] ?? ''))) . "\n";
        $t .= '   ایموجی معمولی: ' . (trim((string)($m['emoji'] ?? '')) !== ''
              ? h($m['emoji']) : '<b>ندارد</b>') . "\n";
        $t .= '   ✨ پریمیوم: ' . (!empty($m['icon']) ? '<code>' . h($m['icon']) . '</code>' : '—') . "\n";
        $t .= '   رنگ: ' . (styleMap()[$m['color'] ?? 'none'] ?? '—') . "\n";
        if ($k === 'direct' || $k === 'group')
            $t .= '   لینک: ' . (trim((string)($m['value'] ?? '')) !== ''
                  ? '<code>' . h($m['value']) . '</code>' : '<b>ثبت نشده' .
                    ($k === 'group' ? ' — تا لینک نگذارید، زدنش فقط یک یادآوری می‌دهد' : '') . '</b>') . "\n";
        $t .= "\n";
    }
    $t .= "💡 برای ایموجی پریمیوم، خودِ ایموجی را بفرستید — شناسه‌اش خودکار خوانده می‌شود.\n";
    $t .= "برچسبِ دکمه HTML نمی‌پذیرد، پس ایموجیِ معمولی و پریمیوم با هم جا نمی‌شوند: " .
          "اگر پریمیوم گذاشتید، معمولی را پاک کنید.";

    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb([
        [btnCb('🔘 ارتباط مستقیم', 'esup_direct', 'admin')],
        [btnCb('🔘 ارتباط غیر مستقیم', 'esup_indirect', 'admin')],
        [btnCb('🔘 گروه', 'esup_group', 'admin')],
        [btnCb('🧹 پاک کردن همه‌ی ایموجی‌های معمولی', 'esupclr', 'reject')],
        [btnUI('back', 'ag_look', 'nav')],
    ]));
}

function edSupOne($chatId, $msgId, $which) {
    if (!in_array($which, ['direct', 'indirect', 'group'], true)) { edSupMain($chatId, $msgId); return; }
    $m   = cfg()['support_main'][$which] ?? [];
    $lbl = ['direct' => 'ارتباط مستقیم', 'indirect' => 'ارتباط غیر مستقیم', 'group' => 'گروه'][$which];

    $t  = "🔘 <b>" . $lbl . "</b>\n\n";
    $t .= 'متن: ' . h(trim((string)($m['text'] ?? '')) ?: '—') . "\n";
    $t .= 'ایموجی معمولی: ' . (trim((string)($m['emoji'] ?? '')) !== ''
          ? h($m['emoji']) : '<b>ندارد</b>') . "\n";
    $t .= '✨ پریمیوم: ' . (!empty($m['icon']) ? '<code>' . h($m['icon']) . '</code>' : '—') . "\n";
    $t .= 'رنگ: ' . (styleMap()[$m['color'] ?? 'none'] ?? '—') . "\n";
    if ($which === 'direct' || $which === 'group')
        $t .= 'لینک: ' . (trim((string)($m['value'] ?? '')) !== ''
              ? '<code>' . h($m['value']) . '</code>' : '<b>ثبت نشده</b>') . "\n";

    $rows = [
        [btnCb('✏️ متن', 'esupt_' . $which, 'admin'),
         btnCb('😀 ایموجی معمولی', 'esupe_' . $which, 'admin')],
        [btnCb('✨ ایموجی پریمیوم', 'esupi_' . $which, 'admin'),
         btnCb('🎨 رنگ', 'esupc_' . $which, 'admin')],
    ];
    if ($which === 'direct' || $which === 'group') $rows[] = [btnCb('🔗 لینک', 'esupu_' . $which, 'admin')];
    $rows[] = [btnUI('back', 'esup', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function edRefButtons($chatId, $msgId) {
    $t = "👥 <b>دکمه‌های زیرمجموعه</b>\n\nهرکدام را ویرایش کنید — متن، ایموجی معمولی، ایموجی پریمیوم، رنگ.";
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb([
        [btnCb('🔗 ساخت لینک دعوت', 'eref_link', 'admin')],
        [btnCb('🧾 تاریخچه پورسانت', 'eref_hist', 'admin')],
        [btnCb('💠 اتصال به کیف پول', 'eref_wallet', 'admin')],
        [btnCb('📤 ارسال برای دوستان', 'eref_share', 'admin')],
        [btnUI('back', 'ag_look', 'nav')],
    ]));
}

function edRefButtonOne($chatId, $msgId, $which) {
    if (!in_array($which, ['link', 'hist', 'wallet', 'share'], true)) { edRefButtons($chatId, $msgId); return; }
    $m   = cfg()['referral']['btns'][$which] ?? [];
    $lbl = ['link' => 'ساخت لینک دعوت', 'hist' => 'تاریخچه پورسانت', 'wallet' => 'برداشت پورسانت',
            'share' => 'ارسال برای دوستان'][$which];

    $t  = "🔘 <b>" . h($lbl) . "</b>\n\n";
    $t .= 'متن: ' . h(trim((string)($m['text'] ?? '')) ?: '—') . "\n";
    $t .= 'ایموجی معمولی: ' . (trim((string)($m['emoji'] ?? '')) !== ''
          ? h($m['emoji']) : '<b>ندارد</b>') . "\n";
    $t .= '✨ پریمیوم: ' . (!empty($m['icon']) ? '<code>' . h($m['icon']) . '</code>' : '—') . "\n";
    $t .= 'رنگ: ' . (styleMap()[$m['color'] ?? 'none'] ?? '—') . "\n";

    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb([
        [btnCb('✏️ متن', 'erefbt_' . $which, 'admin'),
         btnCb('😀 ایموجی معمولی', 'erefbe_' . $which, 'admin')],
        [btnCb('✨ ایموجی پریمیوم', 'erefbi_' . $which, 'admin'),
         btnCb('🎨 رنگ', 'erefbc_' . $which, 'admin')],
        [btnUI('back', 'eref_home', 'nav')],
    ]));
}

function edTopupRules($chatId, $msgId) {
    $c = cfg()['topup_rules'] ?? [];
    $t  = "📋 <b>قوانین افزایش موجودی</b>\n\n";
    $t .= "اولین‌باری که کاربر می‌رود «افزایش موجودی»، این متن با دکمه‌های لغو/تایید پین می‌شود — " .
          "فقط همان یک‌بار؛ بعد از تایید دیگر نشان داده نمی‌شود.\n\n";
    $t .= 'وضعیت: ' . (!empty($c['on']) ? '✅ روشن' : '❌ خاموش') . "\n\n";
    $t .= "متن فعلی:\n" . (string)($c['text'] ?? '');

    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3800), inlineKb([
        [btnCb(!empty($c['on']) ? '✅ روشن' : '❌ خاموش', 'etopx', 'info')],
        [btnCb('✏️ متن قوانین', 'etopm', 'admin')],
        [btnCb('🔘 دکمه‌ی لغو', 'etopb_cancel', 'admin'), btnCb('🔘 دکمه‌ی تایید', 'etopb_ok', 'admin')],
        [btnCb('🔘 دکمه‌ی «تایید شده»', 'etopb_done', 'admin')],
        [btnUI('back', 'ag_look', 'nav')],
    ]));
}

function edTopupRulesBtn($chatId, $msgId, $which) {
    if (!in_array($which, ['ok', 'cancel', 'done'], true)) { edTopupRules($chatId, $msgId); return; }
    $m   = cfg()['topup_rules']['btns'][$which] ?? [];
    $lbl = trBtnLabel($which);

    $t  = "🔘 <b>" . h($lbl) . "</b>\n\n";
    $t .= 'متن: ' . h(trim((string)($m['text'] ?? '')) ?: '—') . "\n";
    $t .= 'ایموجی معمولی: ' . (trim((string)($m['emoji'] ?? '')) !== ''
          ? h($m['emoji']) : '<b>ندارد</b>') . "\n";
    $t .= '✨ پریمیوم: ' . (!empty($m['icon']) ? '<code>' . h($m['icon']) . '</code>' : '—') . "\n";
    $t .= 'رنگ: ' . (styleMap()[$m['color'] ?? 'none'] ?? '—') . "\n";

    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb([
        [btnCb('✏️ متن', 'etopbt_' . $which, 'admin'),
         btnCb('😀 ایموجی معمولی', 'etopbe_' . $which, 'admin')],
        [btnCb('✨ ایموجی پریمیوم', 'etopbi_' . $which, 'admin'),
         btnCb('🎨 رنگ', 'etopbc_' . $which, 'admin')],
        [btnUI('back', 'etop_home', 'nav')],
    ]));
}

function stripPlainEmoji($s) {
    $s = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}' .
                      '\x{FE00}-\x{FE0F}\x{1F1E6}-\x{1F1FF}\x{200D}\x{20E3}\x{2190}-\x{21FF}' .
                      '\x{2300}-\x{23FF}\x{25A0}-\x{25FF}\x{2900}-\x{297F}]/u', '', (string)$s);
    $s = preg_replace('/[ \t]{2,}/u', ' ', $s);
    $s = preg_replace('/^[ \t]+|[ \t]+$/mu', '', $s);
    return trim($s);
}

function edTexts($chatId, $msgId, $page = 0) {
    $labels = textLabels();
    $keys = array_keys($labels);
    $per = 8;
    $slice = array_slice($keys, $page * $per, $per);

    $rows = [];
    foreach ($slice as $k) $rows[] = [btnCb($labels[$k], 'et_' . $k, 'info')];

    $nav = [];
    if ($page > 0) $nav[] = btnCb('⬅️ قبلی', 'ets_' . ($page - 1), 'nav');
    if (($page + 1) * $per < count($keys)) $nav[] = btnCb('بعدی ➡️', 'ets_' . ($page + 1), 'nav');
    if ($nav) $rows[] = $nav;
    $rows[] = [btnCb('🔤 متن دکمه‌های ثابت', 'euis', 'admin')];
    $rows[] = [btnCb(UT('back'), 'adm_home', 'nav')];

    editMsg(BOT_TOKEN, $chatId, $msgId,
        "📝 <b>ویرایش متن‌های ربات</b>\n\nمتن را انتخاب کنید. هنگام فرستادن متن جدید می‌توانید " .
        "<b>ایموجی پریمیوم</b>، نقل‌قول و قالب‌بندی بگذارید — همه حفظ می‌شود.",
        inlineKb($rows));
}

function textVars($key) {
    return [
        'welcome'      => '{name}',
        'account'      => '{id} {name} {username} {balance} {orders} {referrals} {ref_earned} {joined}',
        'referral'     => '{percent} {referrals} {ref_earned} {ref_pending}',
        'shop'         => '{balance}',
        'no_balance'   => '{balance}',
        'orders_item'  => '{status} {title} {phone} {code_line} {amount} {id} {date}',
    ][$key] ?? '';
}

function edText($chatId, $msgId, $key) {
    $labels = textLabels();
    if (!isset($labels[$key])) { edTexts($chatId, $msgId); return; }
    $cur = cfg()['texts'][$key] ?? '';
    $isQuoted = str_starts_with(trim($cur), '<blockquote');

    $text  = "📝 <b>" . h($labels[$key]) . "</b>\n\n";
    $text .= "<b>پیش‌نمایش:</b>\n" . ($cur !== '' ? $cur : '<i>خالی</i>') . "\n\n";
    $text .= "<b>کد:</b>\n<code>" . h(mb_substr($cur, 0, 700)) . "</code>";
    if ($v = textVars($key)) $text .= "\n\n<b>متغیرها:</b>\n<code>" . h($v) . "</code>";

    $rows = [
        [btnCb('✏️ تغییر متن', 'ete_' . $key, 'confirm')],
        [btnCb($isQuoted ? '❝ حذف نقل‌قول' : '❝ نقل‌قول', 'etq_' . $key, 'admin'),
         btnCb('❝ نقل‌قول بازشو', 'etx_' . $key, 'admin')],
        [btnCb('♻️ بازگردانی پیش‌فرض', 'etr_' . $key, 'reject')],
        [btnCb(UT('back'), 'etexts', 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function edUiTexts($chatId, $msgId, $page = 0) {
    $labels = uiTextLabels();
    $keys = array_keys($labels);
    $per = 9;
    $slice = array_slice($keys, $page * $per, $per);

    $rows = [];
    foreach ($slice as $k) {
        $col = UC($k) ? (styleMap()[UC($k)] ?? '') : '—';
        $rows[] = [
            btnCb(UT($k), 'eu_' . $k, 'info'),
            btnCb('🎨 ' . mb_substr($col, 0, 2), 'euc_' . $k, 'admin'),
        ];
    }
    $nav = [];
    if ($page > 0) $nav[] = btnCb('⬅️ قبلی', 'eus_' . ($page - 1), 'nav');
    if (($page + 1) * $per < count($keys)) $nav[] = btnCb('بعدی ➡️', 'eus_' . ($page + 1), 'nav');
    if ($nav) $rows[] = $nav;
    $rows[] = [btnCb(UT('back'), 'etexts', 'nav')];

    editMsg(BOT_TOKEN, $chatId, $msgId,
        "🔤 <b>متن دکمه‌های ثابت</b>\n\nمتن دکمه‌هایی که همه‌جای ربات تکرار می‌شوند.\n" .
        "هنگام فرستادن متن، <b>ایموجی پریمیوم</b> هم بگذارید تا روی دکمه بنشیند.",
        inlineKb($rows));
}

function shopBtnKeys() {
    return ['shop_orders' => 'بالا', 'open_tgs' => 'وسط (تلگرام)', 'open_igs' => 'وسط (اینستاگرام)', 'open_app' => 'پایین'];
}

function edShop($chatId, $msgId) {
    $text = "🛍 <b>پیام و دکمه‌های «ثبت سفارش»</b>\n\n" .
        "چیدمان: یک دکمه بالا (سفارش‌های ثبت‌شده)، دو دکمه وسط (خدمات تلگرام و اینستاگرام) و یک دکمه پایین (شماره مجازی).\n" .
        "دکمه‌ی هر مینی‌اپی که بسته یا بی‌سرویس باشد، خودکار پنهان می‌شود.\n\n" .
        "<b>پیش‌نمایش پیام:</b>\n" . T('shop', ['balance' => fmtNum(0)]) . "\n\n";
    foreach (shopBtnKeys() as $k => $pos) {
        $st = UC($k);
        $text .= '• <b>' . h($pos) . ':</b> ' . h(UT($k)) . ' — ' . h($st ? (string)(styleMap()[$st] ?? $st) : 'هم‌رنگ لینک‌ها') .
                 (UI($k) !== '' ? ' ✨' : '') . "\n";
    }
    $rows = [];
    $prev = function_exists('svShopKb') ? svShopKb(ADMIN_ID) : null;
    if ($prev) {
        $text .= "\n👇 چند ردیفِ اول، خودِ دکمه‌ها هستند همان‌طور که کاربر می‌بیند.";
        foreach ($prev['inline_keyboard'] as $r) $rows[] = $r;
    }
    $rows[] = [btnCb('✏️ متن پیام', 'et_shop', 'confirm')];
    foreach (shopBtnKeys() as $k => $pos)
        $rows[] = [btnCb('🔤 ' . $pos . ': متن', 'eshopb_' . $k, 'confirm'), btnCb('🎨 ' . $pos . ': رنگ', 'eshopc_' . $k, 'admin')];
    $rows[] = [btnUI('back', 'ag_look', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function edGlass($chatId, $msgId) {
    $c = cfg()['glass_colors'];
    $rows = [];
    foreach (glassRoleLabels() as $role => $lbl) {
        $rows[] = [btnCb($lbl . ' — ' . (styleMap()[$c[$role] ?? 'none'] ?? ''), 'egc_' . $role, 'info')];
    }
    $rows[] = [btnCb(UT('back'), 'adm_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId,
        "💠 <b>رنگ دکمه‌های شیشه‌ای</b>\n\nروی هرکدام بزنید تا رنگش عوض شود:", inlineKb($rows));
}

function trustBtn() {
    $b   = cfg()['trust_btn'] ?? [];
    $url = trim((string)($b['url'] ?? ''));
    if ($url === '') return null;
    $btn = ['text' => trim((string)($b['text'] ?? '')) ?: '📢 کانال گزارشات', 'url' => $url];
    if (isStyle($b['color'] ?? '')) $btn['style'] = $b['color'];
    if (trim((string)($b['icon'] ?? '')) !== '') $btn['icon_custom_emoji_id'] = (string)$b['icon'];
    return $btn;
}

function trustKb() {
    $b = trustBtn();
    return $b ? inlineKb([[$b]]) : null;
}

function trustUrl($raw) {
    $u = trim((string)$raw);
    if (preg_match('/^@([A-Za-z0-9_]{4,32})$/', $u, $m)) return 'https://t.me/' . $m[1];
    if (preg_match('#^(?:https?://)?(?:t\.me|telegram\.me)/(\S+)$#i', $u, $m)) return 'https://t.me/' . $m[1];
    return preg_match('#^https://\S+$#i', $u) ? $u : '';
}

function edTrustBtn($chatId, $msgId) {
    $b   = cfg()['trust_btn'] ?? [];
    $url = trim((string)($b['url'] ?? ''));
    $t  = "🔗 <b>دکمه‌ی زیرِ «اعتماد»</b>\n\n";
    $t .= "زیرِ متنِ «چطوری می‌توانم به شما اعتماد کنم» یک دکمه‌ی شیشه‌ای می‌نشیند که کاربر را " .
          "به کانالِ گزارش‌ها می‌برد.\n\n";
    $t .= '🔤 متن: <b>' . h((string)($b['text'] ?? '')) . "</b>\n";
    $t .= '🔗 لینک: ' . ($url !== '' ? '<code>' . h($url) . '</code>' : '<b>ثبت نشده</b> — تا لینک ندهید دکمه دیده نمی‌شود') . "\n";
    $t .= '🎨 رنگ: ' . (styleMap()[$b['color'] ?? 'none'] ?? styleMap()['none']) . "\n";
    $t .= '✨ ایموجی پریمیوم: ' . (trim((string)($b['icon'] ?? '')) !== '' ? 'دارد' : 'ندارد');
    if ($url !== '') $t .= "\n\n👇 پیش‌نمایش:";

    $rows = [];
    if ($prev = trustBtn()) $rows[] = [$prev];
    $rows[] = [btnCb('✏️ متن و ایموجی پریمیوم', 'etrust_t', 'admin'), btnCb('🔗 لینک', 'etrust_u', 'admin')];
    $rows[] = [btnCb('🎨 رنگ: ' . (styleMap()[$b['color'] ?? 'none'] ?? ''), 'etrust_c', 'info')];
    if ($url !== '') $rows[] = [btnCb('🗑 برداشتنِ دکمه', 'etrust_x', 'reject')];
    $rows[] = [btnUI('back', 'ag_look', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function masterJoinMissing($uid, $fresh = false) {
    $j = cfg()['join'] ?? [];
    if (empty($j['on']) || empty($j['channels'])) return [];
    if (isAdmin($uid)) return [];

    $ids = [];
    foreach ($j['channels'] as $ch) {
        $cid = trim((string)($ch['chat_id'] ?? ''));
        if ($cid !== '') $ids[] = $cid;
    }
    JoinCheck::warmMembership($ids, $uid, $fresh);

    $missing = [];
    foreach ($j['channels'] as $ch) {
        $cid = trim((string)($ch['chat_id'] ?? ''));
        if ($cid === '') continue;
        $r = JoinCheck::isMemberOf($cid, $uid, $fresh);
        if (!$r['ok'] || !$r['member']) {
            $missing[] = ['title' => $ch['title'] ?? $cid, 'url' => $ch['url'] ?? '', 'chat_id' => $cid];
        }
    }
    return $missing;
}

function masterJoinGate($uid, $chatId, $missing) {
    $j = cfg()['join'] ?? [];

    $b = ['text' => trim(($j['btn']['emoji'] ?? '✅') . ' ' . ($j['btn']['text'] ?? 'عضو شدم')),
          'callback_data' => 'mjchk'];
    if (isStyle($j['btn']['color'] ?? '')) $b['style'] = $j['btn']['color'];
    if (!empty($j['btn']['icon'])) $b['icon_custom_emoji_id'] = (string)$j['btn']['icon'];

    $text = (string)($j['text'] ?? '🔒 اول عضو شوید:');

    if (str_contains($text, '{channels}')) {
        $list = '';
        foreach ($missing as $m) {
            $url = trim((string)($m['url'] ?? ''));
            if ($url === '' && str_starts_with((string)$m['chat_id'], '@'))
                $url = 'https://t.me/' . ltrim((string)$m['chat_id'], '@');
            $list .= "\n📣 " . ($url !== ''
                     ? '<a href="' . h($url) . '">' . h((string)$m['title']) . '</a>'
                     : h((string)$m['title']));
        }
        $text = str_replace('{channels}', ltrim($list, "\n"), $text);
    }

    sendMsg(BOT_TOKEN, $chatId, $text, inlineKb([[$b]]));
}

function masterHandle($update) {
    maHealQuick();

    if (isset($update['callback_query'])) {
        $cb     = $update['callback_query'];
        $uid    = (int)$cb['from']['id'];
        $chatId = $cb['message']['chat']['id'] ?? $uid;
        $msgId  = $cb['message']['message_id'] ?? null;
        $data   = $cb['data'] ?? '';
        $uname  = $cb['from']['username'] ?? '';
        $fname  = $cb['from']['first_name'] ?? '';
        $cbId   = $cb['id'];
        $isAdmin = isAdmin($uid);

        if (gmCallback($data, $uid, $chatId, $msgId, $cbId, $cb['from'] ?? [])) return;

        if (function_exists('dmCallback') && dmCallback($data, $uid, $chatId, $msgId, $cbId, $cb['from'] ?? [])) return;

        if (function_exists('bkCallback') && bkCallback($data, $uid, $chatId, $msgId, $cbId, $cb['from'] ?? [])) return;

        if (function_exists('mnCallback') && mnCallback($data, $uid, $chatId, $msgId, $cbId, $cb['from'] ?? [])) return;

        if (function_exists('qzCallback') && qzCallback($data, $uid, $chatId, $msgId, $cbId, $cb['from'] ?? [])) return;

        if (tlCallback($cb)) return;

        if (function_exists('tpCallback') && tpCallback($data, $uid, $chatId, $msgId, $cbId, $cb['from'] ?? [])) return;

        if (($cb['message']['chat']['type'] ?? 'private') !== 'private' && str_starts_with($data, 'reply_')
            && function_exists('maSupPrompt') && maSupPrompt($cb, $uid, $cbId)) return;

        if (($cb['message']['chat']['type'] ?? 'private') !== 'private'
            && preg_match('/^(aok_|ano_)/', $data)) {
            if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, '🔒 دسترسی ندارید.', true); return; }

            if (str_starts_with($data, 'aok_')) {
                $oid = substr($data, 4);
                [$ok, $res] = Order::approve($oid, $uid);
                if (!$ok) { answerCb(BOT_TOKEN, $cbId, $res, true); return; }
                completeApprovedOrder($res);
                answerCb(BOT_TOKEN, $cbId, '✅ تایید شد');
                if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, "✅ سفارش <code>" . h($oid) . "</code> تایید شد.");
                return;
            }
            if (str_starts_with($data, 'ano_')) {
                $oid = substr($data, 4);
                [$ok, $res] = Order::reject($oid, $uid);
                if (!$ok) { answerCb(BOT_TOKEN, $cbId, $res, true); return; }
                sendMsg(BOT_TOKEN, $res['user_id'], T('rejected'));
                answerCb(BOT_TOKEN, $cbId, 'رد شد');
                if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, "❌ سفارش <code>" . h($oid) . "</code> رد شد.");
                return;
            }
            answerCb(BOT_TOKEN, $cbId);
            return;
        }

        if (($cb['message']['chat']['type'] ?? 'private') !== 'private') {
            answerCb(BOT_TOKEN, $cbId);
            return;
        }

        $u = getUser($uid);
        if ($u && !empty($u['banned'])) { answerCb(BOT_TOKEN, $cbId, T('banned'), true); return; }

        if ($data === 'mjchk') {
            $miss = masterJoinMissing($uid, true);
            if ($miss) { answerCb(BOT_TOKEN, $cbId, '❌ هنوز در همه کانال‌ها عضو نشده‌اید.', true); return; }
            answerCb(BOT_TOKEN, $cbId, '✅ تایید شد');
            if ($msgId) delMsg(BOT_TOKEN, $chatId, $msgId);
            showHome($uid, $chatId, $fname);
            return;
        }
        if (!$isAdmin && ($miss = masterJoinMissing($uid))) {
            answerCb(BOT_TOKEN, $cbId, '🔒 اول در کانال‌ها عضو شوید.', true);
            masterJoinGate($uid, $chatId, $miss);
            return;
        }

        if (maCallback($data, $uid, $chatId, $msgId, $cbId, $isAdmin)) return;
        if (numCallback($data, $uid, $chatId, $msgId, $cbId, $isAdmin)) return;
        if (function_exists('svCallback') && svCallback($data, $uid, $chatId, $msgId, $cbId, $isAdmin)) return;

        if (str_starts_with($data, 'menu_')) {
            $act = substr($data, 5);
            answerCb(BOT_TOKEN, $cbId);
            runMenuAction($act, $uid, $chatId, $uname, $fname);
            return;
        }

        if ($data === 'cancel') {
            clearState($uid);
            answerCb(BOT_TOKEN, $cbId, 'لغو شد');
            if ($msgId) { delMsg(BOT_TOKEN, $chatId, $msgId); slotClear($uid); }
            showHome($uid, $chatId, $fname);
            return;
        }

        if ($data === 'sup_list') {
            answerCb(BOT_TOKEN, $cbId);
            showSupportIndirect($uid, $chatId, $msgId);
            return;
        }
        if ($data === 'sup_direct') { answerCb(BOT_TOKEN, $cbId); return; }
        if ($data === 'sup_group')  {
            answerCb(BOT_TOKEN, $cbId, 'لینکِ گروه هنوز تنظیم نشده.', true);
            return;
        }

        if ($data === 'ref_link')   { answerCb(BOT_TOKEN, $cbId); showReferralLink($uid, $chatId); return; }
        if ($data === 'ref_hist')   { answerCb(BOT_TOKEN, $cbId); showReferralHistory($uid, $chatId); return; }
        if ($data === 'ref_wallet') { answerCb(BOT_TOKEN, $cbId); refWithdraw($uid, $chatId); return; }

        if ($data === 'trnop') { answerCb(BOT_TOKEN, $cbId); return; }
        if ($data === 'tr_cancel') {
            answerCb(BOT_TOKEN, $cbId, strip_tags(trBtnLabel('cancel')) . ' — هر وقت خواستید، بزنید تایید.', true);
            return;
        }
        if ($data === 'tr_ok') {
            mutateUser($uid, function (&$user) {
                if ($user !== null) $user['topup_rules_ok'] = true;
            });
            if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, (string)cfg()['topup_rules']['text'],
                inlineKb([[trBtn('done', 'trnop')]]));
            answerCb(BOT_TOKEN, $cbId, '✅');
            startTopup($uid, $chatId);
            return;
        }

        if (str_starts_with($data, 'sup_')) {
            $i = (int)substr($data, 4);
            $m = cfg()['support_methods'][$i] ?? null;
            answerCb(BOT_TOKEN, $cbId);
            if (!$m) return;
            if ($m['type'] === 'ticket') {
                setState($uid, 'ticket');
                sendMsg(BOT_TOKEN, $chatId, "🎫 پیام خود را بنویسید تا برای پشتیبانی ارسال شود:",
                    inlineKb([[['text' => UT('cancel'), 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]]]));
                return;
            }
            if ($m['type'] === 'phone') {
                sendMsg(BOT_TOKEN, $chatId, "☎️ <b>" . h($m['label']) . "</b>\n\n<code>" . h($m['value'] ?: 'تنظیم نشده') . "</code>");
                return;
            }
            sendMsg(BOT_TOKEN, $chatId, $m['value'] ?: 'متنی تنظیم نشده است.');
            return;
        }

        if (str_starts_with($data, 'gwchk_')) {
            $oid = substr($data, 6);
            $o = Order::get($oid);
            if (!$o || (int)$o['user_id'] !== $uid) { answerCb(BOT_TOKEN, $cbId, 'پیدا نشد', true); return; }
            if ($o['status'] === Order::APPROVED) {
                answerCb(BOT_TOKEN, $cbId, '✅ قبلا تایید شده', true); return;
            }
            [$paid, $st] = gwCheck($o);
            if ($paid) {
                answerCb(BOT_TOKEN, $cbId, '✅ پرداخت تایید شد');
                gwSettle($oid);
                return;
            }
            $left = max(0, (int)(($o['gw']['expires_at'] ?? 0)) - time());
            answerCb(BOT_TOKEN, $cbId,
                $left > 0
                    ? "⏳ هنوز واریزی دیده نشد.\nوضعیت: {$st}\nمهلت: " . sprintf('%02d:%02d', (int)floor($left/60), $left % 60)
                    : "⌛️ مهلت تمام شد. دوباره درخواست شارژ بدهید.", true);
            return;
        }

        if (str_starts_with($data, 'rcpt_')) {
            $oid = substr($data, 5);
            $o = Order::get($oid);
            if (!$o || (int)$o['user_id'] !== $uid) { answerCb(BOT_TOKEN, $cbId, 'سفارش نامعتبر', true); return; }
            if ($o['status'] !== Order::PENDING) { answerCb(BOT_TOKEN, $cbId, 'قبلا ثبت شده', true); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState($uid, 'receipt', ['order' => $oid, 'ask_msg' => $msgId]);

            $ask = T('receipt_ask') . "\n\n🧾 کد پیگیری: <code>" . h($oid) . '</code>';
            $kb  = inlineKb([[btnUI('cancel', 'ocancel_' . $oid, 'cancel')]]);
            if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $ask, $kb);
            else        sendMsg(BOT_TOKEN, $chatId, $ask, $kb);
            return;
        }

        if (str_starts_with($data, 'ocancel_')) {
            $oid = substr($data, 8);
            $o   = Order::get($oid);
            clearState($uid);
            if ($o && (int)$o['user_id'] === $uid && ($o['status'] ?? '') === Order::PENDING)
                Order::delete($oid);
            answerCb(BOT_TOKEN, $cbId, 'لغو شد');

            if ($msgId) { delMsg(BOT_TOKEN, $chatId, $msgId); slotClear($uid, 'shop'); }
            startTopup($uid, $chatId);
            return;
        }

        $adminPrefixes = ['aok_', 'ano_', 'adm_', 'ag_', 'eb', 'et', 'eg', 'eu', 'eref', 'esup',
                          'jn', 'gw', 'pay', 'px', 'dm', 'ch', 'gma', 'gm_', 'num', 'reply_', 'bk', 'mn',
                          'qz_home', 'qza', 'qzb',
                          'fnt_', 'fntv_',
                          'tp_home', 'tpa',
                          'tl_home', 'tla',
                          'eshop',
                          'locks_'];
        $isAdminCb = false;
        foreach ($adminPrefixes as $pref) {
            if (str_starts_with($data, $pref)) { $isAdminCb = true; break; }
        }
        if ($isAdminCb) {
            if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, '🔒 دسترسی ندارید.', true); return; }
            panelMode(true);
        } else {
            answerCb(BOT_TOKEN, $cbId);
            return;
        }

        if (str_starts_with($data, 'aok_')) {
            $oid = substr($data, 4);
            [$ok, $res] = Order::approve($oid, $uid);
            if (!$ok) { answerCb(BOT_TOKEN, $cbId, $res, true); return; }
            completeApprovedOrder($res);
            answerCb(BOT_TOKEN, $cbId, '✅ تایید شد');
            if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, "✅ سفارش <code>" . h($oid) . "</code> تایید شد.",
                inlineKb([[['text' => UT('panel'), 'callback_data' => 'adm_home', 'style' => gs('admin') ?: null]]]));
            return;
        }

        if (str_starts_with($data, 'ano_')) {
            $oid = substr($data, 4);
            [$ok, $res] = Order::reject($oid, $uid);
            if (!$ok) { answerCb(BOT_TOKEN, $cbId, $res, true); return; }
            sendMsg(BOT_TOKEN, $res['user_id'], T('rejected'));
            answerCb(BOT_TOKEN, $cbId, 'رد شد');
            if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, "❌ سفارش <code>" . h($oid) . "</code> رد شد.",
                inlineKb([[['text' => UT('panel'), 'callback_data' => 'adm_home', 'style' => gs('admin') ?: null]]]));
            return;
        }

        if ($data === 'ebuttons') { answerCb(BOT_TOKEN, $cbId); edButtons($chatId, $msgId); return; }
        if ($data === 'etexts')   { answerCb(BOT_TOKEN, $cbId); edTexts($chatId, $msgId); return; }
        if ($data === 'eglass')   { answerCb(BOT_TOKEN, $cbId); edGlass($chatId, $msgId); return; }
        if ($data === 'euis')     { answerCb(BOT_TOKEN, $cbId); clearState(admStateUid($chatId)); edUiTexts($chatId, $msgId); return; }
        if ($data === 'eshop')    { answerCb(BOT_TOKEN, $cbId); clearState(admStateUid($chatId)); edShop($chatId, $msgId); return; }
        if ($data === 'eshop_c' || preg_match('/^eshopc_(\w+)$/', $data, $em)) {
            $k = $data === 'eshop_c' ? 'open_app' : $em[1];
            if (!isset(shopBtnKeys()[$k])) { answerCb(BOT_TOKEN, $cbId); return; }
            $cur = (string)(cfg()['ui_colors'][$k] ?? 'none');
            cfgSet(function (&$c) use ($k, $cur) { $c['ui_colors'][$k] = nextStyle($cur); });
            answerCb(BOT_TOKEN, $cbId, '🎨');
            edShop($chatId, $msgId);
            return;
        }
        if ($data === 'eshop_b' || preg_match('/^eshopb_(\w+)$/', $data, $em)) {
            $k = $data === 'eshop_b' ? 'open_app' : $em[1];
            if (!isset(shopBtnKeys()[$k])) { answerCb(BOT_TOKEN, $cbId); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), 'ed_uitext', ['key' => $k, 'back' => 'eshop']);
            sendMsg(BOT_TOKEN, $chatId,
                "🔤 متن تازه‌ی دکمه‌ی «" . h(UT($k)) . "» را بفرستید.\n\n" .
                "✨ برای ایموجی پریمیوم، همان ایموجی را اول متن بگذارید؛ روی دکمه می‌نشیند و جدا از متن نمایش داده می‌شود.",
                inlineKb([[btnCb(UT('cancel'), 'eshop', 'cancel')]]));
            return;
        }
        if ($data === 'etrust')   { answerCb(BOT_TOKEN, $cbId); edTrustBtn($chatId, $msgId); return; }
        if ($data === 'etrust_c') {
            cfgSet(function (&$c) { $c['trust_btn']['color'] = nextStyle($c['trust_btn']['color'] ?? 'none'); });
            answerCb(BOT_TOKEN, $cbId, '🎨');
            edTrustBtn($chatId, $msgId);
            return;
        }
        if ($data === 'etrust_x') {
            cfgSet(function (&$c) { $c['trust_btn']['url'] = ''; });
            answerCb(BOT_TOKEN, $cbId, '🗑 برداشته شد');
            edTrustBtn($chatId, $msgId);
            return;
        }
        if ($data === 'etrust_t' || $data === 'etrust_u') {
            answerCb(BOT_TOKEN, $cbId);
            $isUrl = $data === 'etrust_u';
            setState(admStateUid($chatId), $isUrl ? 'ed_trust_url' : 'ed_trust_text', []);
            sendMsg(BOT_TOKEN, $chatId, $isUrl
                ? "🔗 لینکِ کانالِ گزارش‌ها را بفرستید.\n\nمثال: <code>@mychannel</code> یا <code>https://t.me/mychannel</code>"
                : "✏️ متنِ دکمه را بفرستید. اگر <b>ایموجی پریمیوم</b> بگذارید، روی دکمه می‌نشیند.\n\nالان: " .
                  h((string)(cfg()['trust_btn']['text'] ?? '')),
                inlineKb([[btnCb(UT('cancel'), 'etrust', 'cancel')]]));
            return;
        }
        if (str_starts_with($data, 'ets_')) { answerCb(BOT_TOKEN, $cbId); edTexts($chatId, $msgId, (int)substr($data, 4)); return; }
        if (str_starts_with($data, 'eus_')) { answerCb(BOT_TOKEN, $cbId); edUiTexts($chatId, $msgId, (int)substr($data, 4)); return; }
        if (str_starts_with($data, 'eb_'))  { answerCb(BOT_TOKEN, $cbId); edButton($chatId, $msgId, substr($data, 3)); return; }
        if (str_starts_with($data, 'et_'))  { answerCb(BOT_TOKEN, $cbId); edText($chatId, $msgId, substr($data, 3)); return; }

        if ($data === 'adm_join')    { answerCb(BOT_TOKEN, $cbId); admJoin($chatId, $msgId); return; }
        if (str_starts_with($data, 'ag_')) {
            answerCb(BOT_TOKEN, $cbId);
            admGroup($chatId, $msgId, substr($data, 3));
            return;
        }
        if (pxAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (dmAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (chAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (gmAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (function_exists('bkAdminCallback') && bkAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (function_exists('mnAdminCallback') && mnAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (function_exists('qzAdminCallback') && qzAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (tlAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (function_exists('fntCallback') && fntCallback($data, $chatId, $msgId, $cbId)) return;
        if (function_exists('tpAdminCallback') && tpAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if ($data === 'adm_gw')      { answerCb(BOT_TOKEN, $cbId); admGateway($chatId, $msgId); return; }
        if ($data === 'adm_pay')     { answerCb(BOT_TOKEN, $cbId); admPay($chatId, $msgId); return; }
        foreach ([['payc', 'pay_card', "💳 شماره کارت را بفرستید (۱۶ رقم).\n\nخط تیره = پاک کردن"],
                  ['payn', 'pay_name', "👤 نام صاحب کارت را بفرستید.\n\nخط تیره = پاک کردن"]] as [$d0, $act, $ask]) {
            if ($data !== $d0) continue;
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), $act, []);
            sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnUI('cancel', 'adm_pay', 'cancel')]]));
            return;
        }
        if ($data === 'gwx') {
            cfgSet(function (&$c) { $c['gateway']['on'] = empty($c['gateway']['on']); });
            answerCb(BOT_TOKEN, $cbId, '✅'); admGateway($chatId, $msgId); return;
        }
        if ($data === 'gwp') {
            cfgSet(function (&$c) {
                $seq = ['oxapay', 'nowpayments', 'custom'];
                $i = array_search($c['gateway']['provider'] ?? 'oxapay', $seq, true);
                $c['gateway']['provider'] = $seq[(($i === false ? 0 : $i) + 1) % count($seq)];
            });
            answerCb(BOT_TOKEN, $cbId, '✅'); admGateway($chatId, $msgId); return;
        }
        if ($data === 'gwtest') {
            answerCb(BOT_TOKEN, $cbId);
            if (!gwOn()) { sendMsg(BOT_TOKEN, $chatId, "⚠️ اول کلید API و آدرس ربات را بگذارید و درگاه را روشن کنید."); return; }
            $g2 = cfg()['gateway'];
            [$ok2, $inv2, $err2] = gwCreateInvoice('or_TEST' . bin2hex(random_bytes(3)), max(50000, (int)$g2['min']));
            sendMsg(BOT_TOKEN, $chatId, $ok2
                ? "✅ <b>درگاه کار می‌کند</b>\n\n" .
                  "🔗 لینک: " . h($inv2['url']) . "\n" .
                  (!empty($inv2['address']) ? "📥 آدرس: <code>" . h($inv2['address']) . "</code>\n" : '') .
                  "⏳ مهلت: " . (int)$g2['expire'] . " دقیقه\n\n" .
                  "یادتان نرود در پنل درگاه، Callback را روی این بگذارید:\n<code>" . h(gwCallbackUrl()) . "</code>"
                : "❌ <b>درگاه جواب نداد</b>\n\n<code>" . h($err2) . "</code>\n\nکلید API و تنظیمات را بررسی کنید.");
            return;
        }
        foreach ([['gwk', 'gw_key',  "🔑 کلید API (Merchant Key) درگاه را بفرستید:"],
                  ['gws', 'gw_ipn',  "🔐 کلید IPN Secret را بفرستید:"],
                  ['gwu', 'gw_url',  "🌐 آدرس عمومی همین فایل ربات را بفرستید.\n\nمثال: <code>https://site.com/bot.php</code>"],
                  ['gwc', 'gw_coin', "🪙 نماد ارز را بفرستید. مثال: <code>USDT</code> یا <code>TRX</code>"],
                  ['gwn', 'gw_net',  "🔗 شبکه را بفرستید. مثال: <code>TRC20</code> (خط تیره = بدون شبکه)"],
                  ['gwr', 'gw_rate', "💱 هر ۱ واحد ارز چند تومان است؟ (۰ = تبدیل با خود درگاه)"],
                  ['gwe', 'gw_exp',  "⏳ مهلت هر فاکتور به دقیقه:"],
                  ['gwm', 'gw_min',  "🔢 حداقل مبلغ شارژ با درگاه (تومان):"],
                  ['gwcu','gw_curl', "🔗 آدرس درگاه دلخواه.\n\nمتغیرها: <code>{amount}</code> <code>{order}</code> <code>{callback}</code>"]] as [$d0, $act, $ask]) {
            if ($data !== $d0) continue;
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), $act, []);
            sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnUI('cancel', 'adm_gw', 'cancel')]]));
            return;
        }
        if ($data === 'jno') {
            cfgSet(function (&$c) { $c['join']['on'] = empty($c['join']['on']); });
            answerCb(BOT_TOKEN, $cbId, '✅');
            admJoin($chatId, $msgId);
            return;
        }
        if ($data === 'jna') {
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), 'jn_add', []);
            sendMsg(BOT_TOKEN, $chatId,
                "➕ <b>افزودن کانال عضویت اجباری</b>\n\n" .
                "۱) اول ربات را در کانال <b>ادمین</b> کنید.\n" .
                "۲) بعد یکی از این‌ها را بفرستید:\n" .
                "• آیدی کانال مثل <code>@mychannel</code>\n" .
                "• لینک کانال مثل <code>https://t.me/mychannel</code>\n" .
                "• آیدی عددی مثل <code>-1001234567890</code>\n" .
                "• یا یک پست از خودِ کانال را اینجا فوروارد کنید (برای کانال خصوصی).",
                inlineKb([[btnUI('cancel', 'adm_join', 'cancel')]]));
            return;
        }
        if (str_starts_with($data, 'jnd_')) {
            $i = (int)substr($data, 4);
            cfgSet(function (&$c) use ($i) {
                if (isset($c['join']['channels'][$i])) {
                    unset($c['join']['channels'][$i]);
                    $c['join']['channels'] = array_values($c['join']['channels']);
                }
            });
            answerCb(BOT_TOKEN, $cbId, '🗑 حذف شد');
            admJoin($chatId, $msgId);
            return;
        }
        foreach ([['jnt', 'jn_text', "✏️ متن قفل عضویت را بفرستید.\n\n" .
                                     "لینک کانال‌ها را <b>داخل همین متن</b> بنویسید (دکمه شیشه‌ای نداریم).\n\n" .
                                     "اگر <code>{channels}</code> بنویسید، فهرست کانال‌ها خودکار همان‌جا می‌آید.\n\n" .
                                     "✨ ایموجی پریمیوم و نقل‌قول پشتیبانی می‌شود."],
                  ['jnb', 'jn_btn', '🔘 متن دکمه «عضو شدم»:']] as [$d0, $act, $ask]) {
            if ($data !== $d0) continue;
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), $act, []);
            sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnUI('cancel', 'adm_join', 'cancel')]]));
            return;
        }

        if ($data === 'ebmode') {
            cfgSet(function (&$c) { $c['ui']['mode'] = ($c['ui']['mode'] === 'glass') ? 'menu' : 'glass'; });
            answerCb(BOT_TOKEN, $cbId, '✅ حالت عوض شد');
            edButtons($chatId, $msgId);
            sendMsg(BOT_TOKEN, $chatId, '👇 منوی جدید:', mainKeyboard());
            return;
        }
        if ($data === 'ebpin') {
            cfgSet(function (&$c) { $c['ui']['persistent'] = empty($c['ui']['persistent']); });
            $on = !empty(cfg()['ui']['persistent']);
            answerCb(BOT_TOKEN, $cbId, $on ? 'چسبان روشن شد' : 'چسبان خاموش شد', true);
            edButtons($chatId, $msgId);
            sendMsg(BOT_TOKEN, $chatId,
                $on ? '📌 کیبورد چسبان شد — کاربر نمی‌تواند ببنددش.'
                    : '✅ کیبورد قابل بستن شد — کاربر می‌تواند ببنددش.',
                mainKeyboard());
            return;
        }
        if ($data === 'eblay') {
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), 'ed_layout');
            sendMsg(BOT_TOKEN, $chatId,
                "📐 الگوی چیدمان را بفرستید.\n\nمثال: <code>2,1,1</code> یعنی ۲ دکمه بالا، بعد ۱، بعد ۱.\n" .
                "الگو تکرار نمی‌شود؛ دکمه‌های اضافه تک‌تک می‌آیند.",
                inlineKb([[btnCb(UT('cancel'), 'ebuttons', 'cancel')]]));
            return;
        }
        if ($data === 'ebnew') {
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), 'ed_newbtn');
            sendMsg(BOT_TOKEN, $chatId,
                "➕ متن دکمه جدید را بفرستید.\n\n(می‌توانید ایموجی پریمیوم هم بگذارید)",
                inlineKb([[btnCb(UT('cancel'), 'ebuttons', 'cancel')]]));
            return;
        }

        foreach ([['ebt_', 'ed_btext', '✏️ متن جدید دکمه را بفرستید (ایموجی پریمیوم مجاز است):'],
                  ['ebe_', 'ed_bemoji', '😀 ایموجی جدید را بفرستید (یا خط تیره برای حذف):'],
                  ['ebi_', 'ed_bicon', "✨ کد ایموجی پریمیوم را بفرستید.\nبا /emoji می‌گیرید. برای حذف خط تیره بفرستید."],
                  ['ebr_', 'ed_brow', '📐 شماره ردیف را بفرستید (۰ = خودکار):'],
                  ['ebo_', 'ed_border', '🔢 شماره ترتیب را بفرستید:'],
                  ['ebv_', 'ed_bvalue', '📝 مقدار دکمه را بفرستید (متن یا آدرس):']] as $it) {
            [$pref, $act, $ask] = $it;
            if (str_starts_with($data, $pref)) {
                $bid = substr($data, strlen($pref));
                if (!isset(cfg()['buttons'][$bid])) { answerCb(BOT_TOKEN, $cbId, 'دکمه پیدا نشد', true); return; }
                answerCb(BOT_TOKEN, $cbId);
                setState(admStateUid($chatId), $act, ['btn' => $bid]);
                sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnCb(UT('cancel'), 'eb_' . $bid, 'cancel')]]));
                return;
            }
        }

        if (str_starts_with($data, 'ebc_')) {
            $bid = substr($data, 4);
            cfgSet(function (&$c) use ($bid) {
                if (isset($c['buttons'][$bid])) $c['buttons'][$bid]['color'] = nextStyle($c['buttons'][$bid]['color'] ?? 'none');
            });
            answerCb(BOT_TOKEN, $cbId, '🎨');
            edButton($chatId, $msgId, $bid);
            return;
        }
        if (str_starts_with($data, 'ebx_')) {
            $bid = substr($data, 4);
            cfgSet(function (&$c) use ($bid) {
                if (isset($c['buttons'][$bid])) $c['buttons'][$bid]['on'] = empty($c['buttons'][$bid]['on']);
            });
            answerCb(BOT_TOKEN, $cbId, '✅');
            edButton($chatId, $msgId, $bid);
            return;
        }
        if (str_starts_with($data, 'ebd_')) {
            $bid = substr($data, 4);
            if (!str_starts_with($bid, 'c_')) { answerCb(BOT_TOKEN, $cbId, 'فقط دکمه‌های ساخته‌شده حذف می‌شوند', true); return; }
            cfgSet(function (&$c) use ($bid) { unset($c['buttons'][$bid]); });
            answerCb(BOT_TOKEN, $cbId, 'حذف شد');
            edButtons($chatId, $msgId);
            return;
        }

        if (str_starts_with($data, 'ete_')) {
            $k = substr($data, 4);
            if (!isset(textLabels()[$k])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), 'edit_text', ['key' => $k]);
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ متن جدید را بفرستید.\n\n✨ ایموجی پریمیوم، نقل‌قول و قالب‌بندی همه حفظ می‌شود.",
                inlineKb([[btnCb(UT('cancel'), 'et_' . $k, 'cancel')]]));
            return;
        }
        if (str_starts_with($data, 'etq_') || str_starts_with($data, 'etx_')) {
            $exp = str_starts_with($data, 'etx_');
            $k = substr($data, 4);
            if (!isset(textLabels()[$k])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            cfgSet(function (&$c) use ($k, $exp) {
                $t = trim($c['texts'][$k] ?? '');
                if (str_starts_with($t, '<blockquote')) {
                    $t = preg_replace('#^<blockquote[^>]*>#', '', $t);
                    $t = preg_replace('#</blockquote>$#', '', trim($t));
                    if ($exp) $t = '<blockquote expandable>' . trim($t) . '</blockquote>';
                } else {
                    $t = ($exp ? '<blockquote expandable>' : '<blockquote>') . $t . '</blockquote>';
                }
                $c['texts'][$k] = trim($t);
            });
            answerCb(BOT_TOKEN, $cbId, '❝ اعمال شد');
            edText($chatId, $msgId, $k);
            return;
        }
        if (str_starts_with($data, 'etr_')) {
            $k = substr($data, 4);
            $d = defaultConfig()['texts'][$k] ?? null;
            if ($d === null) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            cfgSet(function (&$c) use ($k, $d) { $c['texts'][$k] = $d; });
            answerCb(BOT_TOKEN, $cbId, '♻️ بازگردانی شد');
            edText($chatId, $msgId, $k);
            return;
        }
        if (str_starts_with($data, 'euc_')) {
            $k = substr($data, 4);
            if (!isset(uiTextLabels()[$k])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            cfgSet(function (&$c) use ($k) {
                $cur = $c['ui_colors'][$k] ?? 'none';
                $c['ui_colors'][$k] = nextStyle($cur);
            });
            answerCb(BOT_TOKEN, $cbId, '🎨');
            $page = 0;
            $keys = array_keys(uiTextLabels());
            $i = array_search($k, $keys, true);
            if ($i !== false) $page = intdiv($i, 9);
            edUiTexts($chatId, $msgId, $page);
            return;
        }
        if (str_starts_with($data, 'eu_')) {
            $k = substr($data, 3);
            if (!isset(uiTextLabels()[$k])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), 'ed_uitext', ['key' => $k]);
            sendMsg(BOT_TOKEN, $chatId,
                "🔤 متن جدید دکمه «" . h(uiTextLabels()[$k]) . "» را بفرستید.\n\nمقدار فعلی: " . h(UT($k)),
                inlineKb([[btnCb(UT('cancel'), 'euis', 'cancel')]]));
            return;
        }
        if (str_starts_with($data, 'egc_')) {
            $role = substr($data, 4);
            if (!isset(glassRoleLabels()[$role])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            cfgSet(function (&$c) use ($role) { $c['glass_colors'][$role] = nextStyle($c['glass_colors'][$role] ?? 'none'); });
            answerCb(BOT_TOKEN, $cbId, '🎨');
            edGlass($chatId, $msgId);
            return;
        }

        if ($data === 'adm_home')   { answerCb(BOT_TOKEN, $cbId); admHome($chatId, $msgId); return; }

        if (str_starts_with($data, 'reply_')) {
            $target = (int)substr($data, 6);
            if ($target <= 0) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), 'reply_user', ['to' => $target, 'mid' => (int)$msgId]);
            sendMsg(BOT_TOKEN, $chatId, "💬 پاسخ خود را بفرستید (به <code>{$target}</code>):",
                inlineKb([[btnUI('cancel', 'adm_home', 'cancel')]]));
            return;
        }

        if (str_starts_with($data, 'adm_txt_')) {
            $key = substr($data, 8);
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), 'edit_text', ['key' => $key]);
            $cur = cfg()['texts'][$key] ?? '';
            sendMsg(BOT_TOKEN, $chatId,
                "📝 متن فعلی:\n\n<code>" . h($cur) . "</code>\n\nمتن جدید را بفرستید:",
                inlineKb([[['text' => UT('cancel'), 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]]]));
            return;
        }

        if ($data === 'adm_bc') {
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), 'broadcast');
            sendMsg(BOT_TOKEN, $chatId,
                "📢 <b>پیام همگانی</b>\n\n" .
                "👥 گیرنده: <b>" . number_format(count(bcIdsMain())) . "</b> نفر\n\n" .
                "پیام را بفرستید (متن، عکس، هرچه باشد عینا فرستاده می‌شود).",
                inlineKb([[btnUI('cancel', 'ag_rep', 'cancel')]]));
            return;
        }

        if ($data === 'esup')  { answerCb(BOT_TOKEN, $cbId); edSupMain($chatId, $msgId); return; }
        if (str_starts_with($data, 'esup_')) {
            answerCb(BOT_TOKEN, $cbId);
            edSupOne($chatId, $msgId, substr($data, 5));
            return;
        }
        if ($data === 'esupclr') {
            cfgSet(function (&$c) {
                foreach (['direct', 'indirect', 'group'] as $k) {
                    $c['support_main'][$k]['emoji'] = '';
                    $c['support_main'][$k]['text']  = stripPlainEmoji($c['support_main'][$k]['text'] ?? '');
                }
            });
            answerCb(BOT_TOKEN, $cbId, '🧹 پاک شد');
            edSupMain($chatId, $msgId);
            return;
        }
        if (preg_match('/^esup([teicu])_(direct|indirect|group)$/', $data, $em)) {
            static $ask = [
                't' => ["✏️ متنِ دکمه را بفرستید.", 'sup_text'],
                'e' => ["😀 ایموجیِ معمولی را بفرستید.\n\n<code>-</code> بفرستید تا پاک شود.", 'sup_emoji'],
                'i' => ["✨ <b>ایموجی پریمیوم</b>\n\nخودِ ایموجی را بفرستید — شناسه‌اش خودکار خوانده می‌شود.\n\n" .
                        "<code>-</code> بفرستید تا برداشته شود.", 'sup_icon'],
                'u' => ["🔗 لینک را بفرستید. مثلا <code>https://t.me/username</code>", 'sup_url'],
            ];
            if ($em[1] === 'c') {
                answerCb(BOT_TOKEN, $cbId);
                $rows = [];
                foreach (styleMap() as $sk => $sl)
                    $rows[] = [btnCb($sl, 'esupC_' . $em[2] . '_' . $sk, 'info')];
                $rows[] = [btnUI('back', 'esup_' . $em[2], 'nav')];
                editMsg(BOT_TOKEN, $chatId, $msgId, "🎨 <b>رنگ دکمه</b>", inlineKb($rows));
                return;
            }
            [$txt, $st] = $ask[$em[1]];
            setState($uid, $st, ['which' => $em[2]]);
            answerCb(BOT_TOKEN, $cbId);
            sendMsg(BOT_TOKEN, $chatId, $txt,
                    inlineKb([[btnUI('cancel', 'esup_' . $em[2], 'cancel')]]));
            return;
        }
        if (preg_match('/^esupC_(direct|indirect|group)_(\w+)$/', $data, $em)) {
            $col = isStyle($em[2]) ? $em[2] : 'none';
            cfgSet(function (&$c) use ($em, $col) { $c['support_main'][$em[1]]['color'] = $col; });
            answerCb(BOT_TOKEN, $cbId, '✅');
            edSupOne($chatId, $msgId, $em[1]);
            return;
        }

        if ($data === 'etop_home') { answerCb(BOT_TOKEN, $cbId); edTopupRules($chatId, $msgId); return; }
        if ($data === 'etopx') {
            cfgSet(function (&$c) { $c['topup_rules']['on'] = empty($c['topup_rules']['on']); });
            answerCb(BOT_TOKEN, $cbId, '✅'); edTopupRules($chatId, $msgId); return;
        }
        if ($data === 'etopm') {
            answerCb(BOT_TOKEN, $cbId);
            setState($uid, 'tr_text', []);
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ متنِ تازه‌ی قوانین را بفرستید.\n\n✨ ایموجی پریمیوم و <code>&lt;blockquote&gt;</code> هم می‌پذیرد.",
                inlineKb([[btnUI('cancel', 'etop_home', 'cancel')]]));
            return;
        }
        if (str_starts_with($data, 'etopb_')) {
            answerCb(BOT_TOKEN, $cbId);
            edTopupRulesBtn($chatId, $msgId, substr($data, 6));
            return;
        }
        if (preg_match('/^etopb([teic])_(ok|cancel|done)$/', $data, $em)) {
            if ($em[1] === 'c') {
                answerCb(BOT_TOKEN, $cbId);
                $rows = [];
                foreach (styleMap() as $sk => $sl) $rows[] = [btnCb($sl, 'etopbC_' . $em[2] . '_' . $sk, 'info')];
                $rows[] = [btnUI('back', 'etopb_' . $em[2], 'nav')];
                editMsg(BOT_TOKEN, $chatId, $msgId, "🎨 <b>رنگ دکمه</b>", inlineKb($rows));
                return;
            }
            static $trAsk = [
                't' => ["✏️ متنِ دکمه را بفرستید.", 'tr_btext'],
                'e' => ["😀 ایموجیِ معمولی را بفرستید.\n\n<code>-</code> بفرستید تا پاک شود.", 'tr_bemoji'],
                'i' => ["✨ <b>ایموجی پریمیوم</b>\n\nخودِ ایموجی را بفرستید — شناسه‌اش خودکار خوانده می‌شود.\n\n" .
                        "<code>-</code> بفرستید تا برداشته شود.", 'tr_bicon'],
            ];
            [$txt, $st] = $trAsk[$em[1]];
            setState($uid, $st, ['which' => $em[2]]);
            answerCb(BOT_TOKEN, $cbId);
            sendMsg(BOT_TOKEN, $chatId, $txt, inlineKb([[btnUI('cancel', 'etopb_' . $em[2], 'cancel')]]));
            return;
        }
        if (preg_match('/^etopbC_(ok|cancel|done)_(\w+)$/', $data, $em)) {
            $col = isStyle($em[2]) ? $em[2] : 'none';
            cfgSet(function (&$c) use ($em, $col) { $c['topup_rules']['btns'][$em[1]]['color'] = $col; });
            answerCb(BOT_TOKEN, $cbId, '✅');
            edTopupRulesBtn($chatId, $msgId, $em[1]);
            return;
        }

        if ($data === 'eref_home') { answerCb(BOT_TOKEN, $cbId); edRefButtons($chatId, $msgId); return; }
        if (preg_match('/^eref_(link|hist|wallet|share)$/', $data, $em)) {
            answerCb(BOT_TOKEN, $cbId);
            edRefButtonOne($chatId, $msgId, $em[1]);
            return;
        }
        if (preg_match('/^erefb([teic])_(link|hist|wallet|share)$/', $data, $em)) {
            if ($em[1] === 'c') {
                answerCb(BOT_TOKEN, $cbId);
                $rows = [];
                foreach (styleMap() as $sk => $sl) $rows[] = [btnCb($sl, 'erefbC_' . $em[2] . '_' . $sk, 'info')];
                $rows[] = [btnUI('back', 'eref_' . $em[2], 'nav')];
                editMsg(BOT_TOKEN, $chatId, $msgId, "🎨 <b>رنگ دکمه</b>", inlineKb($rows));
                return;
            }
            static $refAsk = [
                't' => ["✏️ متنِ دکمه را بفرستید.", 'ref_btext'],
                'e' => ["😀 ایموجیِ معمولی را بفرستید.\n\n<code>-</code> بفرستید تا پاک شود.", 'ref_bemoji'],
                'i' => ["✨ <b>ایموجی پریمیوم</b>\n\nخودِ ایموجی را بفرستید — شناسه‌اش خودکار خوانده می‌شود.\n\n" .
                        "<code>-</code> بفرستید تا برداشته شود.", 'ref_bicon'],
            ];
            [$txt, $st] = $refAsk[$em[1]];
            setState($uid, $st, ['which' => $em[2]]);
            answerCb(BOT_TOKEN, $cbId);
            sendMsg(BOT_TOKEN, $chatId, $txt, inlineKb([[btnUI('cancel', 'eref_' . $em[2], 'cancel')]]));
            return;
        }
        if (preg_match('/^erefbC_(link|hist|wallet|share)_(\w+)$/', $data, $em)) {
            $col = isStyle($em[2]) ? $em[2] : 'none';
            cfgSet(function (&$c) use ($em, $col) { $c['referral']['btns'][$em[1]]['color'] = $col; });
            answerCb(BOT_TOKEN, $cbId, '✅');
            edRefButtonOne($chatId, $msgId, $em[1]);
            return;
        }

        if ($data === 'adm_check') { answerCb(BOT_TOKEN, $cbId); admCheck($chatId, $msgId); return; }

        if ($data === 'adm_web') {
            answerCb(BOT_TOKEN, $cbId);
            editMsg(BOT_TOKEN, $chatId, $msgId,
                "🌐 <b>پنل وب</b>\n\nاین بخش‌ها در پنل وب هستند:\n\n" .
                "☎️ کشورها، اپراتورها و قیمت‌ها · 📦 سفارش‌ها و شارژها\n" .
                "👥 کاربران و رفرال · 💳 کیف پول · 📞 پشتیبانی\n" .
                "🩺 تشخیص و سرعت (تست نشتی، تست نوشتن، سرعت ربات)\n\n" .
                "آدرس: <code>admin_panel.php</code>",
                inlineKb([[btnCb(UT('back'), 'adm_home', 'nav')]]));
            return;
        }

        answerCb(BOT_TOKEN, $cbId);
        return;
    }

    if (isset($update['my_chat_member'])) {
        if (function_exists('qzGroupMember')) qzGroupMember($update['my_chat_member']);
        return;
    }

    if (!isset($update['message'])) return;

    $msg    = $update['message'];
    $uid    = (int)($msg['from']['id'] ?? 0);
    $chatId = $msg['chat']['id'] ?? $uid;
    $uname  = $msg['from']['username'] ?? '';
    $fname  = $msg['from']['first_name'] ?? '';
    $text   = trim($msg['text'] ?? '');
    if (!$uid) return;

    if (($msg['chat']['type'] ?? 'private') !== 'private') {
        if (!empty($msg['migrate_to_chat_id'])) {
            if (function_exists('qzGroupMigrate')) qzGroupMigrate($chatId, $msg['migrate_to_chat_id']);
            return;
        }
        if (function_exists('maSupReply') && maSupReply($msg)) return;
        if (tlHandle($msg, $uid, $chatId)) return;
        $rt = $msg['message_id'] ?? null;
        if (function_exists('tpTouch')) tpTouch($chatId, $uid);
        if (function_exists('tpHandleText') && tpHandleText($text, $uid, $chatId, $fname, $uname, $rt, false)) return;
        if (pxAnswerThenWarm($text, $chatId, $rt)) return;
        if (gmHandleText($text, $uid, $chatId, $fname, $uname, $rt, false, $msg)) return;
        if (dmHandleText($text, $uid, $chatId, $fname, $uname, $rt, false)) return;
        if (function_exists('bkHandleText') && bkHandleText($text, $uid, $chatId, $fname, $uname, $rt, false, $msg)) return;
        if (function_exists('mnHandleText') && mnHandleText($text, $uid, $chatId, $fname, $uname, $rt, false, $msg)) return;
        return;
    }

    if (str_starts_with($text, '/start')) {
        $arg = trim(explode(' ', $text, 2)[1] ?? '');
        $ref = (str_starts_with($arg, 'ref')) ? (int)substr($arg, 3) : null;
        touchUser($uid, $uname, $fname, $ref);
        clearState($uid);
        slotClear($uid);
        if ($miss = masterJoinMissing($uid)) { masterJoinGate($uid, $chatId, $miss); return; }
        showHome($uid, $chatId, $fname);
        return;
    }

    touchUser($uid, $uname, $fname);
    $u = getUser($uid);
    if ($u && !empty($u['banned'])) { sendMsg(BOT_TOKEN, $chatId, T('banned')); return; }
    if (tlHandle($msg, $uid, $chatId)) return;

    if (!empty($msg['reply_to_message'])) {
        if (function_exists('maSupReply') && maSupReply($msg)) return;
        if (function_exists('maSupFollowUp') && maSupFollowUp($msg, $uid, $uname, $fname, $chatId)) return;
    }

    if ($text === '/panel' || $text === '/admin') {
        if (!isAdmin($uid)) { sendMsg(BOT_TOKEN, $chatId, "🔒 دسترسی ندارید."); return; }
        panelMode(true);
        admHome($chatId);
        return;
    }
    if ($text === '/id')     { sendMsg(BOT_TOKEN, $chatId, "🆔 <code>{$uid}</code>"); return; }
    if ($text === '/emoji') {
        if (!isAdmin($uid)) return;
        setState($uid, 'grab_emoji');
        sendMsg(BOT_TOKEN, $chatId,
            "✨ <b>گرفتن کد ایموجی پریمیوم</b>\n\n" .
            "یک پیام بفرستید که ایموجی‌های پریمیوم مورد نظرتان داخلش باشد.\n" .
            "کد هرکدام را به شما می‌دهم تا در پنل استفاده کنید.",
            inlineKb([[btnCb(UT('cancel'), 'cancel', 'cancel')]]));
        return;
    }
    if ($text === '/cancel') { clearState($uid); sendMsg(BOT_TOKEN, $chatId, "❌ لغو شد.", mainKeyboard()); return; }
    if ($text === '/orders') {
        clearState($uid); showOrders($uid, $chatId, $msg['message_id'] ?? null); return;
    }
    if ($text === '/menu')   { showHome($uid, $chatId, $fname); return; }

    if (!isAdmin($uid) && ($miss = masterJoinMissing($uid))) {
        masterJoinGate($uid, $chatId, $miss);
        return;
    }

    $act = findMenuAction($text);
    if ($act) {
        clearState($uid);
        $mine = (string)$chatId === (string)$uid ? (int)($msg['message_id'] ?? 0) : 0;
        $prevU = $mine ? slotGet($uid, 'umsg') : null;
        runMenuAction($act, $uid, $chatId, $uname, $fname, $msg['message_id'] ?? null);
        if ($mine) {
            slotSet($uid, 'umsg', $mine);
            if ($prevU && $prevU !== $mine) delMsg(BOT_TOKEN, $chatId, $prevU);
        }
        return;
    }

    $st = getState($uid);
    if (!$st) {
        $rt = $msg['message_id'] ?? null;
        if (pxAnswerThenWarm($text, $chatId, $rt)) return;
        if (gmHandleText($text, $uid, $chatId, $fname, $uname, $rt, true, $msg)) return;
        if (dmHandleText($text, $uid, $chatId, $fname, $uname, $rt, true)) return;
        if (function_exists('bkHandleText') && bkHandleText($text, $uid, $chatId, $fname, $uname, $rt, true, $msg)) return;
        return;
    }
    $action = $st['action'];
    $sd     = $st['data'] ?? [];

    if (maStateHandle($action, $sd, $msg, $uid, $chatId)) return;
    if (numStateHandle($action, $msg, $uid, $chatId)) return;

    if (pxStateHandle($action, $msg, $uid, $chatId)) return;
    if (dmStateHandle($action, $msg, $uid, $chatId)) return;
    if (chStateHandle($action, $msg, $uid, $chatId)) return;
    if (gmStateHandle($action, $msg, $uid, $chatId)) return;
    if (function_exists('bkStateHandle') && bkStateHandle($action, $msg, $uid, $chatId)) return;
    if (function_exists('mnStateHandle') && mnStateHandle($action, $msg, $uid, $chatId)) return;
    if (function_exists('qzStateHandle') && qzStateHandle($action, $msg, $uid, $chatId)) return;
    if (tlStateHandle($action, $msg, $uid, $chatId)) return;
    if (function_exists('tpStateHandle') && tpStateHandle($action, $msg, $uid, $chatId)) return;
    if (function_exists('fntState') && fntState($action, getState($uid)['data'] ?? [], $msg, $uid, $chatId)) return;

    if (str_starts_with($action, 'pay_')) {
        $plain = trim($msg['text'] ?? '');
        $back  = inlineKb([[btnCb('💳 مقصد پرداخت', 'adm_pay', 'admin')]]);
        $blank = ($plain === '-' || $plain === '—');
        $map   = ['pay_card' => 'card', 'pay_name' => 'card_name'];
        $f = $map[$action] ?? '';
        if ($f === '') { clearState($uid); return; }

        $v = $blank ? '' : $plain;
        if ($f === 'card' && $v !== '') {
            $digits = preg_replace('/\D/', '', norm_fa_digits($v));
            if (strlen($digits) !== 16) {
                sendMsg(BOT_TOKEN, $chatId,
                    "⚠️ شماره کارت باید دقیقا ۱۶ رقم باشد — الان " . strlen($digits) . " رقم فرستادید.\n" .
                    "با فاصله یا خط تیره هم اشکالی ندارد.");
                return;
            }
            $v = implode('-', str_split($digits, 4));
        }
        cfgSet(function (&$c) use ($f, $v) { $c['wallets'][$f] = $v; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $v !== '' ? "✅ ذخیره شد." : "✅ پاک شد.", $back);
        admPay($chatId);
        return;
    }

    if (str_starts_with($action, 'gw_')) {
        $plain = trim($msg['text'] ?? '');
        $back  = inlineKb([[btnCb('💠 درگاه پرداخت', 'adm_gw', 'admin')]]);
        $map = ['gw_key' => 'api_key', 'gw_ipn' => 'ipn_secret', 'gw_url' => 'base_url',
                'gw_coin' => 'coin', 'gw_net' => 'network', 'gw_curl' => 'custom_url'];
        $nums = ['gw_rate' => 'rate', 'gw_exp' => 'expire', 'gw_min' => 'min'];

        if (isset($nums[$action])) {
            $v = (float)str_replace([',', '،', ' '], '', $plain);
            if ($v < 0) { sendMsg(BOT_TOKEN, $chatId, "⚠️ عدد معتبر بفرستید."); return; }
            if ($action === 'gw_exp' && $v < 5) { sendMsg(BOT_TOKEN, $chatId, "⚠️ حداقل ۵ دقیقه."); return; }
            $f = $nums[$action];
            cfgSet(function (&$c) use ($f, $v) { $c['gateway'][$f] = $v; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد.", $back);
            return;
        }
        if (isset($map[$action])) {
            $f = $map[$action];
            $v = ($plain === '-' || $plain === '—') ? '' : $plain;
            if ($f === 'base_url' && $v !== '' && !preg_match('#^https://#i', $v)) {
                sendMsg(BOT_TOKEN, $chatId, "⚠️ آدرس باید با <code>https://</code> شروع شود."); return;
            }
            if (in_array($f, ['coin', 'network'], true)) $v = strtoupper($v);
            cfgSet(function (&$c) use ($f, $v) { $c['gateway'][$f] = $v; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                "✅ ذخیره شد." . ($f === 'base_url' && $v !== ''
                    ? "\n\n📡 این آدرس را در پنل درگاه به‌عنوان Callback/IPN بگذارید:\n<code>" .
                      h(gwCallbackUrl()) . "</code>" : ''), $back);
            return;
        }
        clearState($uid);
        return;
    }

    if (str_starts_with($action, 'sup_')) {
        $st    = getState($uid);
        $which = (string)(($st['data'] ?? [])['which'] ?? 'direct');
        if (!in_array($which, ['direct', 'indirect', 'group'], true)) { clearState($uid); return; }
        $plain = trim((string)($msg['text'] ?? ''));
        $blank = ($plain === '-' || $plain === '—');
        $back  = inlineKb([[btnCb('📞 دکمه‌های پشتیبانی', 'esup', 'admin')]]);

        if ($action === 'sup_text') {
            if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ یک متن بفرستید."); return; }
            $v = mb_substr($plain, 0, 40);
            cfgSet(function (&$c) use ($which, $v) { $c['support_main'][$which]['text'] = $v; });
            clearState($uid); sendMsg(BOT_TOKEN, $chatId, '✅ ثبت شد.', $back); return;
        }
        if ($action === 'sup_emoji') {
            $v = $blank ? '' : mb_substr($plain, 0, 8);
            cfgSet(function (&$c) use ($which, $v) { $c['support_main'][$which]['emoji'] = $v; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, $v === '' ? '✅ ایموجی معمولی پاک شد.' : '✅ ثبت شد.', $back);
            return;
        }
        if ($action === 'sup_icon') {
            $ids = customEmojiIds($msg);
            $v   = $blank ? '' : (string)($ids[0] ?? '');
            if (!$blank && $v === '') {
                sendMsg(BOT_TOKEN, $chatId,
                    "⚠️ ایموجی پریمیوم پیدا نشد.\n\n" .
                    "باید خودِ ایموجیِ پریمیوم را بفرستید (با اکانت پریمیوم)، نه شناسه‌اش را.");
                return;
            }
            cfgSet(function (&$c) use ($which, $v) { $c['support_main'][$which]['icon'] = $v; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                $v === '' ? '✅ پریمیوم برداشته شد.'
                          : "✅ ثبت شد: <code>" . h($v) . "</code>\n\n" .
                            "<i>اگر ایموجیِ معمولی هم دارد، پاکش کنید — با هم جا نمی‌شوند.</i>",
                $back);
            return;
        }
        if ($action === 'sup_url') {
            if (!$blank && !preg_match('#^https?://[^\s]+$#i', $plain)) {
                sendMsg(BOT_TOKEN, $chatId, "⚠️ لینک باید با <code>https://</code> شروع شود."); return;
            }
            $v = $blank ? '' : $plain;
            cfgSet(function (&$c) use ($which, $v) { $c['support_main'][$which]['value'] = $v; });
            clearState($uid); sendMsg(BOT_TOKEN, $chatId, '✅ ثبت شد.', $back); return;
        }
        clearState($uid);
        return;
    }

    if ($action === 'tr_text') {
        $html = function_exists('msgHtml') ? msgHtml($msg) : trim((string)($msg['text'] ?? ''));
        if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return; }
        cfgSet(function (&$c) use ($html) { $c['topup_rules']['text'] = $html; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ثبت شد.', inlineKb([[btnCb('📋 قوانین شارژ', 'etop_home', 'admin')]]));
        return;
    }
    if (str_starts_with($action, 'tr_b')) {
        $st    = getState($uid);
        $which = (string)(($st['data'] ?? [])['which'] ?? '');
        if (!in_array($which, ['ok', 'cancel', 'done'], true)) { clearState($uid); return; }
        $plain = trim((string)($msg['text'] ?? ''));
        $blank = ($plain === '-' || $plain === '—');
        $back  = inlineKb([[btnCb('📋 قوانین شارژ', 'etop_home', 'admin')]]);

        if ($action === 'tr_btext') {
            if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ یک متن بفرستید."); return; }
            $v = mb_substr($plain, 0, 40);
            cfgSet(function (&$c) use ($which, $v) { $c['topup_rules']['btns'][$which]['text'] = $v; });
            clearState($uid); sendMsg(BOT_TOKEN, $chatId, '✅ ثبت شد.', $back); return;
        }
        if ($action === 'tr_bemoji') {
            $v = $blank ? '' : mb_substr($plain, 0, 8);
            cfgSet(function (&$c) use ($which, $v) { $c['topup_rules']['btns'][$which]['emoji'] = $v; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, $v === '' ? '✅ ایموجی معمولی پاک شد.' : '✅ ثبت شد.', $back);
            return;
        }
        if ($action === 'tr_bicon') {
            $ids = customEmojiIds($msg);
            $v   = $blank ? '' : (string)($ids[0] ?? '');
            if (!$blank && $v === '') {
                sendMsg(BOT_TOKEN, $chatId,
                    "⚠️ ایموجی پریمیوم پیدا نشد.\n\n" .
                    "باید خودِ ایموجیِ پریمیوم را بفرستید (با اکانت پریمیوم)، نه شناسه‌اش را.");
                return;
            }
            cfgSet(function (&$c) use ($which, $v) { $c['topup_rules']['btns'][$which]['icon'] = $v; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                $v === '' ? '✅ پریمیوم برداشته شد.'
                          : "✅ ثبت شد: <code>" . h($v) . "</code>\n\n" .
                            "<i>اگر ایموجیِ معمولی هم دارد، پاکش کنید — با هم جا نمی‌شوند.</i>",
                $back);
            return;
        }
        clearState($uid);
        return;
    }

    if (str_starts_with($action, 'ref_b')) {
        $st    = getState($uid);
        $which = (string)(($st['data'] ?? [])['which'] ?? '');
        if (!in_array($which, ['link', 'hist', 'wallet', 'share'], true)) { clearState($uid); return; }
        $plain = trim((string)($msg['text'] ?? ''));
        $blank = ($plain === '-' || $plain === '—');
        $back  = inlineKb([[btnCb('👥 دکمه‌های زیرمجموعه', 'eref_home', 'admin')]]);

        if ($action === 'ref_btext') {
            if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ یک متن بفرستید."); return; }
            $v = mb_substr($plain, 0, 40);
            cfgSet(function (&$c) use ($which, $v) { $c['referral']['btns'][$which]['text'] = $v; });
            clearState($uid); sendMsg(BOT_TOKEN, $chatId, '✅ ثبت شد.', $back); return;
        }
        if ($action === 'ref_bemoji') {
            $v = $blank ? '' : mb_substr($plain, 0, 8);
            cfgSet(function (&$c) use ($which, $v) { $c['referral']['btns'][$which]['emoji'] = $v; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, $v === '' ? '✅ ایموجی معمولی پاک شد.' : '✅ ثبت شد.', $back);
            return;
        }
        if ($action === 'ref_bicon') {
            $ids = customEmojiIds($msg);
            $v   = $blank ? '' : (string)($ids[0] ?? '');
            if (!$blank && $v === '') {
                sendMsg(BOT_TOKEN, $chatId,
                    "⚠️ ایموجی پریمیوم پیدا نشد.\n\n" .
                    "باید خودِ ایموجیِ پریمیوم را بفرستید (با اکانت پریمیوم)، نه شناسه‌اش را.");
                return;
            }
            cfgSet(function (&$c) use ($which, $v) { $c['referral']['btns'][$which]['icon'] = $v; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                $v === '' ? '✅ پریمیوم برداشته شد.'
                          : "✅ ثبت شد: <code>" . h($v) . "</code>\n\n" .
                            "<i>اگر ایموجیِ معمولی هم دارد، پاکش کنید — با هم جا نمی‌شوند.</i>",
                $back);
            return;
        }
        clearState($uid);
        return;
    }

    if (str_starts_with($action, 'jn_')) {
        $plain = trim($msg['text'] ?? '');
        $ids   = customEmojiIds($msg);
        $back  = inlineKb([[btnCb('🔒 عضویت اجباری', 'adm_join', 'admin')]]);

        if ($action === 'jn_add') {
            $ref = joinChannelRef($msg);
            if ($ref === '') {
                sendMsg(BOT_TOKEN, $chatId, "⚠️ آیدی، لینک یا آیدی عددیِ کانال را بفرستید؛ یا یک پست از کانال فوروارد کنید.");
                return;
            }
            [$ok, $res] = joinAddChannel($ref);
            if (!$ok) { sendMsg(BOT_TOKEN, $chatId, '⚠️ ' . h($res)); return; }
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ کانال «" . h($res) . "» اضافه شد و عضویت اجباری روشن است.", $back);
            return;
        }
        if ($action === 'jn_text') {
            $html = msgHtml($msg);
            if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
            cfgSet(function (&$c) use ($html) { $c['join']['text'] = $html; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد." . ($ids ? "\n✨ ایموجی پریمیوم حفظ شد." : ''), $back);
            return;
        }
        if ($action === 'jn_btn') {
            if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
            cfgSet(function (&$c) use ($plain, $ids) {
                $c['join']['btn']['text'] = $plain;
                if ($ids) $c['join']['btn']['icon'] = $ids[0];
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد.", $back);
            return;
        }
        clearState($uid);
        return;
    }

    if ($action === 'receipt') {
        $oid = $sd['order'] ?? '';
        $o = Order::get($oid);
        if (!$o || (int)$o['user_id'] !== $uid) { clearState($uid); return; }

        $type = null; $val = null;
        if (!empty($msg['photo'])) { $p = $msg['photo']; $type = 'photo'; $val = $p[count($p) - 1]['file_id']; }
        elseif ($text !== '')      { $type = 'text';  $val = $text; }

        if (!$type) { sendMsg(BOT_TOKEN, $chatId, "⚠️ عکس رسید یا کد تراکنش بفرستید."); return; }
        if (!Order::attachReceipt($oid, $type, $val)) {
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "❌ این سفارش دیگر قابل ویرایش نیست.");
            return;
        }
        clearState($uid);

        $askMid = (int)($sd['ask_msg'] ?? 0);
        if ($askMid > 0) editMsg(BOT_TOKEN, $chatId, $askMid, T('receipt_ok'), null);
        else             sendMsg(BOT_TOKEN, $chatId, T('receipt_ok'));

        $fresh = Order::get($oid);
        $sentToChannel = (($fresh['type'] ?? '') === 'topup') && function_exists('chTopupReceipt')
            && chTopupReceipt($fresh);
        if (!$sentToChannel) notifyAdminOrder($oid);
        return;
    }

    if ($action === 'topup_amount') {
        $amt = round(maNum($text));
        $why = topupAmountError($amt);
        if ($why !== '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ ' . $why); return; }
        clearState($uid);
        createOrderAndAsk($uid, $chatId, $uname, $amt);
        return;
    }

    if ($action === 'grab_emoji') {
        $ids = customEmojiIds($msg);
        if (!$ids) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ در این پیام ایموجی پریمیوم پیدا نشد. دوباره بفرستید.");
            return;
        }
        clearState($uid);
        $out = "✨ <b>کدهای ایموجی پریمیوم</b>\n\n";
        foreach ($ids as $id) {
            $out .= "<tg-emoji emoji-id=\"" . h($id) . "\">✨</tg-emoji>  <code>" . h($id) . "</code>\n";
        }
        $out .= "\nکد را کپی کنید و در پنل، فیلد «ایموجی پریمیوم» بگذارید.\n";
        $out .= "برای داخل متن‌ها هم می‌توانید بنویسید:\n";
        $out .= "<code>&lt;tg-emoji emoji-id=\"" . h($ids[0]) . "\"&gt;✨&lt;/tg-emoji&gt;</code>";
        sendMsg(BOT_TOKEN, $chatId, $out, mainKeyboard());
        return;
    }

    if ($action === 'ticket') {
        $body = msgHtml($msg);
        if (trim($body) === '' && empty($msg['photo'])) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ لطفا متن پیام را بنویسید.");
            return;
        }
        clearState($uid);

        $tid = uid('tk');
        mutate('tickets', function (&$a) use ($tid, $uid, $uname, $fname, $body) {
            $a[$tid] = ['id' => $tid, 'user_id' => (int)$uid,
                        'username' => $uname, 'name' => $fname,
                        'text' => $body, 'at' => nowStr(), 'answered' => false];
        });

        sendMsg(BOT_TOKEN, $chatId, T('sup_sent'), mainKeyboard());
        maSupLog($uid, 'u', trim((string)($msg['text'] ?? $msg['caption'] ?? '')) ?: '📎 ' . maSupKind($msg));

        $r = chTicketAlert($uid, $uname, $fname, $body,
            inlineKb([[btnCb('💬 پاسخ', 'reply_' . $uid, 'admin')]]));
        if (!$r) error_log('[ticket] admin notify failed for all admins');
        return;
    }

    if (!isAdmin($uid)) return;

    if ($action === 'reply_user') {
        $to = (int)($sd['to'] ?? 0);
        clearState($uid);
        if ($to <= 0) return;
        [$r, $logged] = maSupDeliver($to, $msg);
        if (!empty($r['ok']) || $logged) {
            $mid = (int)($sd['mid'] ?? 0);
            if ($mid > 0) tg(BOT_TOKEN, 'editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => $mid,
                'reply_markup' => kbJson(inlineKb([[btnCb('✅ پاسخ داده شد · پاسخِ دوباره', 'reply_' . $to, 'admin')]]))], 8);
        }
        sendMsg(BOT_TOKEN, $chatId, !empty($r['ok']) ? "✅ پاسخ ارسال شد و در چتِ مینی‌اپ هم دیده می‌شود."
            : ($logged ? "⚠️ پیوی نرسید، ولی در چتِ مینی‌اپ دیده می‌شود: " : "❌ ارسال نشد: ") . h((string)($r['description'] ?? '')));
        return;
    }

    if ($action === 'edit_text') {
        $key = $sd['key'] ?? '';
        if (!array_key_exists($key, defaultConfig()['texts'])) { clearState($uid); return; }
        $html = msgHtml($msg);
        if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
        cfgSet(function (&$c) use ($key, $html) { $c['texts'][$key] = $html; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ متن ذخیره شد.\n\n<b>پیش‌نمایش:</b>\n" . $html, mainKeyboard());
        return;
    }

    if (str_starts_with($action, 'ed_b')) {
        $bid = $sd['btn'] ?? '';
        if (!isset(cfg()['buttons'][$bid])) { clearState($uid); return; }
        $plain = trim($msg['text'] ?? '');
        $ids   = customEmojiIds($msg);
        $back  = inlineKb([[btnCb(UT('back'), 'eb_' . $bid, 'nav')]]);

        if ($action === 'ed_btext') {
            if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
            cfgSet(function (&$c) use ($bid, $plain, $ids) {
                $c['buttons'][$bid]['text'] = $plain;
                if ($ids) $c['buttons'][$bid]['icon'] = $ids[0];
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                "✅ متن دکمه شد: <b>" . h($plain) . "</b>" . ($ids ? "\n✨ ایموجی پریمیوم هم نشست." : ''),
                $back);
            sendMsg(BOT_TOKEN, $chatId, '👇 منوی به‌روز:', mainKeyboard());
            return;
        }
        if ($action === 'ed_bemoji') {
            $em = ($plain === '-' || $plain === '—') ? '' : $plain;
            cfgSet(function (&$c) use ($bid, $em) { $c['buttons'][$bid]['emoji'] = $em; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ ایموجی ذخیره شد.", $back);
            sendMsg(BOT_TOKEN, $chatId, '👇 منوی به‌روز:', mainKeyboard());
            return;
        }
        if ($action === 'ed_bicon') {
            $ic = '';
            if ($ids) $ic = $ids[0];
            elseif (ctype_digit($plain)) $ic = $plain;
            elseif ($plain === '-' || $plain === '—') $ic = '';
            else { sendMsg(BOT_TOKEN, $chatId, "⚠️ یک ایموجی پریمیوم بفرستید، یا کد عددی، یا خط تیره."); return; }
            cfgSet(function (&$c) use ($bid, $ic) { $c['buttons'][$bid]['icon'] = $ic; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, $ic ? "✅ ایموجی پریمیوم نشست." : "✅ حذف شد.", $back);
            return;
        }
        if ($action === 'ed_brow' || $action === 'ed_border') {
            if (!ctype_digit($plain)) { sendMsg(BOT_TOKEN, $chatId, "⚠️ فقط عدد."); return; }
            $f = ($action === 'ed_brow') ? 'row' : 'order';
            $v = (int)$plain;
            cfgSet(function (&$c) use ($bid, $f, $v) { $c['buttons'][$bid][$f] = $v; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد.", $back);
            sendMsg(BOT_TOKEN, $chatId, '👇 منوی به‌روز:', mainKeyboard());
            return;
        }
        if ($action === 'ed_bvalue') {
            $html = msgHtml($msg);
            cfgSet(function (&$c) use ($bid, $html, $plain) {
                $c['buttons'][$bid]['value'] = (($c['buttons'][$bid]['action'] ?? '') === 'text') ? $html : $plain;
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ مقدار ذخیره شد.", $back);
            return;
        }

    }

    if ($action === 'ed_layout') {
        if (!parseLayout($text)) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ چیدمان نامعتبر. مثال: <code>2,1,1</code>");
            return;
        }
        $map = applyLayoutToRows($text);
        cfgSet(function (&$c) use ($map, $text) {
            $c['ui']['layout'] = trim($text);
            foreach ($map as $b => $r) if (isset($c['buttons'][$b])) $c['buttons'][$b]['row'] = $r;
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ چیدمان اعمال شد.", mainKeyboard());
        return;
    }

    if ($action === 'ed_newbtn') {
        $plain = trim($msg['text'] ?? '');
        if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
        $ids = customEmojiIds($msg);
        $nid = 'c_' . bin2hex(random_bytes(4));
        cfgSet(function (&$c) use ($nid, $plain, $ids) {
            $c['buttons'][$nid] = [
                'emoji' => '', 'text' => $plain, 'color' => 'none',
                'icon' => $ids ? $ids[0] : '', 'row' => 0, 'order' => 50,
                'on' => true, 'action' => 'text', 'value' => '',
            ];
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId,
            "✅ دکمه <b>" . h($plain) . "</b> ساخته شد.\n\nحالا متن، رنگ و ردیفش را تنظیم کنید:",
            inlineKb([[btnCb('⚙️ تنظیم دکمه', 'eb_' . $nid, 'admin')]]));
        return;
    }

    if ($action === 'ed_trust_text' || $action === 'ed_trust_url') {
        $plain = trim($msg['text'] ?? '');
        $back  = inlineKb([[btnCb('🔗 دکمه‌ی زیرِ «اعتماد»', 'etrust', 'admin')]]);
        if ($action === 'ed_trust_url') {
            $u = trustUrl($plain);
            if ($u === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ لینک نامعتبر است. مثلا <code>@mychannel</code> بفرستید."); return; }
            cfgSet(function (&$c) use ($u) { $c['trust_btn']['url'] = $u; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ لینک ثبت شد: <code>" . h($u) . "</code>", $back);
            return;
        }
        if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
        $ids = customEmojiIds($msg);
        $txt = mb_substr($ids ? (textWithoutCustomEmoji($msg) ?: $plain) : $plain, 0, 60);
        cfgSet(function (&$c) use ($txt, $ids) {
            $c['trust_btn']['text'] = $txt;
            $c['trust_btn']['icon'] = $ids ? $ids[0] : '';
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId,
            "✅ متنِ دکمه شد: <b>" . h($txt) . "</b>" . ($ids ? "\n✨ ایموجی پریمیوم روی دکمه نشست." : ''), $back);
        return;
    }

    if ($action === 'ed_uitext') {
        $k = $sd['key'] ?? '';
        if (!isset(uiTextLabels()[$k])) { clearState($uid); return; }
        $plain = trim($msg['text'] ?? '');
        if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
        $ids = customEmojiIds($msg);
        $txt = $ids ? (textWithoutCustomEmoji($msg) ?: $plain) : $plain;
        cfgSet(function (&$c) use ($k, $txt, $ids) {
            $c['ui_texts'][$k] = $txt;
            if ($ids) $c['ui_icons'][$k] = $ids[0];
            else unset($c['ui_icons'][$k]);
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId,
            "✅ ذخیره شد: <b>" . h($txt) . "</b>" . ($ids ? "\n✨ ایموجی پریمیوم روی دکمه نشست." : ''),
            inlineKb([[btnUI('back', ($sd['back'] ?? '') === 'eshop' ? 'eshop' : 'euis', 'nav')]]));
        return;
    }

    if ($action === 'broadcast') {
        $html = msgHtml($msg);
        if (trim($html) === '') return;
        clearState($uid);
        [$n, $err] = bcQueue($html);
        sendMsg(BOT_TOKEN, $chatId, $n > 0
            ? "📢 <b>پیام همگانی در صف قرار گرفت</b>\n\n" .
              "👥 گیرنده: <b>" . number_format($n) . "</b> نفر\n" .
              "⏳ در پس‌زمینه فرستاده می‌شود؛ گزارشش را همین‌جا می‌گیرید.\n\n" .
              "می‌توانید ربات را ببندید — ادامه‌اش خودکار پیش می‌رود."
            : "⚠️ " . h($err ?: 'کسی برای فرستادن نیست.'));
        return;
    }
}

function bcIdsMain() {
    $ids = [];
    foreach (allUsers() as $u) {
        if (!empty($u['banned'])) continue;
        $t = (int)($u['telegram_id'] ?? 0);
        if ($t > 0) $ids[] = $t;
    }
    return array_values(array_unique($ids));
}

function bcQueue($html) {
    $ids = bcIdsMain();
    if (!$ids) return [0, 'هیچ کاربری نیست.'];

    $busy = load('broadcast');
    if ($busy && empty($busy['done']) && !empty($busy['ids'])) {
        $left = count((array)$busy['ids']) - (int)($busy['i'] ?? 0);
        if ($left > 0) return [0, 'یک پیام همگانی نیمه‌تمام در صف است (' .
                                  number_format($left) . ' گیرنده مانده). بگذارید تمام شود.'];
    }
    mutate('broadcast', function (&$b) use ($html, $ids) {
        $b = ['text' => $html, 'ids' => $ids, 'i' => 0,
              'sent' => 0, 'fail' => 0, 'at' => time(), 'done' => false];
    });
    return [count($ids), ''];
}

function bcTick($limit = 25) {
    $b = load('broadcast');
    if (!$b || !empty($b['done']) || empty($b['ids'])) return 0;

    $text = (string)($b['text'] ?? '');
    $i    = (int)($b['i'] ?? 0);
    $ids  = (array)$b['ids'];
    if ($text === '' || $i >= count($ids)) { bcFinish(); return 0; }

    $saveEvery = 5;
    $sent = 0; $fail = 0; $n = 0; $fin = false; $done = $i;
    $flush = function () use (&$done, &$sent, &$fail, &$fin) {
        mutate('broadcast', function (&$x) use ($done, $sent, $fail, &$fin) {
            if (!is_array($x) || empty($x['ids'])) return;
            $x['i']    = $done;
            $x['sent'] = (int)($x['sent'] ?? 0) + $sent;
            $x['fail'] = (int)($x['fail'] ?? 0) + $fail;
            if ($done >= count($x['ids'])) { $x['done'] = true; $fin = true; }
        });
        $sent = 0; $fail = 0;
    };

    for (; $i < count($ids) && $n < $limit; $i++, $n++) {
        $r = sendMsg(BOT_TOKEN, $ids[$i], $text);
        if (!empty($r['ok'])) $sent++; else $fail++;
        $done = $i + 1;
        if ($done % $saveEvery === 0) $flush();
        usleep(40000);
    }
    $flush();
    if ($fin) bcFinish();
    return $n;
}

function bcFinish() {
    $b = load('broadcast');
    if (!$b) return;
    if (!empty($b['told'])) return;
    mutate('broadcast', function (&$x) { if (is_array($x)) $x['told'] = true; });
    notifyAdmins(
        "📢 <b>پیام همگانی تمام شد</b>\n\n" .
        '✅ رسید: <b>' . number_format((int)($b['sent'] ?? 0)) . "</b>\n" .
        '❌ نرسید: <b>' . number_format((int)($b['fail'] ?? 0)) . '</b>');
}

function runMenuAction($act, $uid, $chatId, $uname, $fname, $replyTo = null) {
    $b = cfg()['buttons'][$act] ?? null;
    if ($b && ($b['action'] ?? '') === 'url') {
        sendMsg(BOT_TOKEN, $chatId, btnLabel($b), inlineKb([[btnUrl(UT('open'), $b['value'], 'link')]]));
        return;
    }
    if ($b && ($b['action'] ?? '') === 'text') {
        $v = trim((string)($b['value'] ?? ''));
        if ($v === '' && isAdmin($uid)) {
            sendMsg(BOT_TOKEN, $chatId,
                "⚠️ متن دکمه «" . h($b['text']) . "» هنوز تنظیم نشده.\n" .
                "/panel ← 🎨 ظاهر و متن‌ها ← 🎨 دکمه‌ها ← همین دکمه ← 📝 مقدار");
            return;
        }
        if ($v !== '') sendMsg(BOT_TOKEN, $chatId, $v);
        return;
    }

    switch ($act) {
        case 'buy':      showShop($uid, $chatId, $replyTo); break;
        case 'account':  showAccount($uid, $chatId, $replyTo); break;
        case 'topup':    startTopup($uid, $chatId, $replyTo); break;
        case 'referral': showReferral($uid, $chatId, $replyTo); break;
        case 'orders':   showOrders($uid, $chatId, $replyTo); break;
        case 'support':  showSupport($uid, $chatId, $replyTo); break;
        case 'trust':    panelShow($uid, $chatId, 'menu', T('trust'), trustKb(), $replyTo); break;
        default:         showHome($uid, $chatId, $fname); break;
    }
}

function showShop($uid, $chatId, $replyTo = null) {
    $kb = function_exists('svShopKb') ? svShopKb($uid) : (maReady() ? maOpenKb() : null);
    if (!$kb) {
        $t = T('shop_closed');
        if (isAdmin($uid))
            $t .= "\n\n👑 مینی‌اپ خاموش است یا آدرسش ثبت نشده: /panel ← 🚀 مینی‌اپ";
        panelShow($uid, $chatId, 'shop', $t, null, $replyTo);
        return;
    }
    if (isAdmin($uid) && (string)$chatId === (string)$uid)
        $kb['inline_keyboard'][] = [btnCb('✏️ ویرایش این پیام و دکمه (فقط ادمین)', 'eshop', 'admin')];
    panelShow($uid, $chatId, 'shop',
        T('shop', ['balance' => fmtNum((float)(getUser($uid)['balance'] ?? 0))]), $kb, $replyTo);
}

function showOrders($uid, $chatId, $replyTo = null) {
    $list = MaOrder::forUser($uid, 8);
    $kb   = maOpenKb('orders');
    if (!$list) {
        panelShow($uid, $chatId, 'menu', T('orders_empty'), $kb, $replyTo);
        return;
    }
    $t    = T('orders_head') . "\n";
    $bare = !str_contains((string)(cfg()['texts']['orders_item'] ?? ''), '{phone}');
    foreach ($list as $o) {
        $act   = numGet((string)$o['id']);
        $code  = trim((string)($act['code'] ?? ''));
        $phone = h((string)($act['phone'] ?? '—'));
        $cl    = $code !== '' ? "\n   🔑 کد: <code>" . h($code) . '</code>' : '';
        $t .= T('orders_item', [
            'status'    => MaOrder::statusLabel((string)($o['status'] ?? '')),
            'title'     => h((string)($o['item_name'] ?? '')),
            'phone'     => $phone,
            'code_line' => $cl,
            'amount'    => fmtNum((float)($o['total'] ?? 0)),
            'currency'  => 'تومان',
            'id'        => h((string)$o['id']),
            'date'      => h((string)($o['created_at'] ?? '')),
        ]) . ($bare ? '   ☎️ <code>' . $phone . '</code>' . $cl . "\n" : '') . "\n";
    }
    panelShow($uid, $chatId, 'menu', mb_substr($t, 0, 3900), $kb, $replyTo);
}

if (defined('MEMBERSHIP_LIB_ONLY')) return;

if (isset($_GET['ipn'])) {
    try { handleIpn(); }
    catch (Throwable $e) { error_log('[ipn] ' . $e->getMessage()); http_response_code(500); echo 'err'; }
    exit;
}

if (isset($_GET['app'])) {
    if (function_exists('maNoNet')) maNoNet(true);
    try { maServe((string)$_GET['app']); }
    catch (Throwable $e) {
        error_log('[miniapp] ' . $e->getMessage());
        http_response_code(500);
        echo 'server error';
    }
    exit;
}

if (isset($_GET['maav'])) {
    try { maServeAvatar(); }
    catch (Throwable $e) { error_log('[maav] ' . $e->getMessage()); http_response_code(204); }
    exit;
}

if (isset($_GET['marp'])) {
    try { maServeReviewPhoto(); }
    catch (Throwable $e) { error_log('[marp] ' . $e->getMessage()); http_response_code(204); }
    exit;
}

if (isset($_GET['mapi'])) {
    if (function_exists('maNoNet')) maNoNet(true);
    try { maApi(); }
    catch (Throwable $e) {
        error_log('[mapi] ' . $e->getMessage());
        maApiOut(['ok' => false, 'error' => 'server_error', 'message' => 'خطای سرور — دوباره تلاش کنید.'], 500);
    }
    exit;
}

if (isset($_GET['cron'])) {
    http_response_code(200);
    if (strlen(CRON_KEY) < 12 || !hash_equals(CRON_KEY, (string)$_GET['cron'])) {
        echo 'forbidden'; exit;
    }
    echo 'gw: ' . gwPoll(50) .
         ' · games: ' . gmTick(50) .
         ' · numbers: ' . numTick(50) .
         ' · services: ' . (function_exists('svSync') ? svSync(100) : 0) .
         ' · archive: ' . (ordersArchive() + maOrdersArchive()) .
         ' · mine: ' . (function_exists('mnTick') ? mnTick(50) : 0) .
         ' · bank: ' . (function_exists('bkPendSweep') ? bkPendSweep(200) : 0) .
         ' · quiz: ' . (function_exists('qzTick') ? qzTick(20) : 0) .
         ' · states: ' . (function_exists('stateSweep') ? stateSweep() : 0) .
         ' · top_members: ' . (function_exists('tpSweep') ? tpSweep(120, 500) : 0) .
         ' · broadcast: ' . bcTick(120);
    exit;
}

function seenMessage($update) {
    foreach (['message', 'edited_message', 'callback_query'] as $k) {
        if (!isset($update[$k])) continue;
        $m = ($k === 'callback_query') ? ($update[$k]['message'] ?? null) : $update[$k];
        $chat = $m['chat']['id'] ?? null;
        $mid  = $m['message_id'] ?? null;
        if ($chat === null || $mid === null) continue;
        $extra = ($k === 'callback_query') ? ('c' . ($update[$k]['id'] ?? '')) : '';
        return seenKey('m' . $chat . '_' . $mid . '_' . $extra);
    }
    return true;
}

function seenUpdate($id) {
    $id = (int)$id;
    if ($id <= 0) return true;
    return seenKey('u' . $id);
}

function seenKey($key) {
    $key = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$key);
    if ($key === '') return true;

    $base = DATA_DIR . '/.upd';
    $shard = substr(md5($key), 0, 2);
    $dir = $base . '/' . $shard;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $f = $dir . '/' . $key;
    $h = @fopen($f, 'x');
    if ($h === false) {
        if (is_file($f)) return false;
        error_log('[shop-bot] پوشه‌ی .upd قابل نوشتن نیست: ' . $dir);
        return true;
    }
    fclose($h);

    if (mt_rand(1, 50) === 1) {
        $now = time();
        $dh = @opendir($dir);
        if ($dh) {
            $n = 0;
            while (($e = readdir($dh)) !== false) {
                if ($e === '.' || $e === '..') continue;
                if (++$n > 500) break;
                $old = $dir . '/' . $e;
                if ($now - (int)@filemtime($old) > 3600) @unlink($old);
            }
            closedir($dh);
        }
    }
    return true;
}

function panelLabel($label) {
    $t = trim(strip_tags((string)$label));
    $clean = preg_replace(
        '/[\x{1F300}-\x{1FAFF}\x{2190}-\x{21FF}\x{2300}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{20E3}\x{2600}-\x{26FF}]/u',
        '', $t);
    $clean = preg_replace('/\s{2,}/u', ' ', (string)$clean);
    $clean = (string)preg_replace('/^[\s|\-–—]+|[\s|\-–—]+$/u', '', (string)$clean);
    if ($clean !== '') return $clean;
    return $t !== '' ? $t : '•';
}

function panelMode($set = null) {
    static $on = false;
    if ($set !== null) $on = (bool)$set;
    return $on;
}

function admStateUid($chatId) {
    $c = (int)$chatId;
    return $c > 0 ? $c : (int)ADMIN_ID;
}

function tickDue($key, $secs = 20) {
    $f = rtrim(DATA_DIR, '/') . '/.tick_' . preg_replace('/[^a-z0-9_]/i', '', (string)$key);
    if (time() - (int)@filemtime($f) < max(1, (int)$secs)) return false;
    @touch($f);
    return true;
}

function closeRequest($body = '') {
    ignore_user_abort(true);
    if (!headers_sent()) {
        header('Content-Type: application/json');
        header('Content-Length: ' . strlen($body));
        header('Connection: close');
    }
    echo $body;

    if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); return; }
    if (function_exists('litespeed_finish_request')) { litespeed_finish_request(); return; }

    while (ob_get_level() > 0) @ob_end_flush();
    @flush();
}

function migrateOnce($key, callable $fn) {
    $mark = DATA_DIR . '/.migrated_' . $key;
    if (is_file($mark)) return false;
    try {
        $fn();
    } catch (Throwable $e) {
        error_log('[shop-bot] مهاجرت ' . $key . ' نگرفت: ' . $e->getMessage());
        return false;
    }
    @touch($mark);
    return true;
}

function hookAllowMemberUpdates() {
    $i = tg(BOT_TOKEN, 'getWebhookInfo', [], 8);
    if (empty($i['ok'])) throw new RuntimeException('getWebhookInfo');
    $url  = (string)($i['result']['url'] ?? '');
    $have = (array)($i['result']['allowed_updates'] ?? []);
    if ($url === '' || WEBHOOK_SECRET === '' || !$have || in_array('my_chat_member', $have, true)) return;
    $p = [
        'url' => $url, 'secret_token' => WEBHOOK_SECRET,
        'allowed_updates' => json_encode(array_values(array_unique(array_merge($have, ['my_chat_member'])))),
    ];
    if (!empty($i['result']['max_connections'])) $p['max_connections'] = (int)$i['result']['max_connections'];
    $r = tg(BOT_TOKEN, 'setWebhook', $p, 8);
    if (empty($r['ok'])) throw new RuntimeException('setWebhook');
}

function pxAnswerThenWarm($text, $chatId, $replyTo = null) {
    if (!function_exists('pxHandleText')) return false;

    $cold = !function_exists('pxHasAnyCache') || !pxHasAnyCache();

    $stale = function_exists('pxStale') ? pxStale() : false;
    if (!$cold && function_exists('pxNoNet')) pxNoNet(true);
    $hit = pxHandleText($text, $chatId, $replyTo);
    if (function_exists('pxNoNet')) pxNoNet(false);

    if (!$hit) return false;
    if (($stale || $cold) && function_exists('pxWarm')) pxWarm();
    return true;
}

function techHealthCheck() {
    $warn = [];

    if (function_exists('opcache_get_status')) {
        $st = @opcache_get_status(false);
        if (!is_array($st) || empty($st['opcache_enabled']))
            $warn[] = '⚡️ opcache خاموش است — کدِ ربات در هر درخواست از نو کامپایل می‌شود.';
        else {
            $free = (float)($st['memory_usage']['free_memory'] ?? 0) / 1048576;
            if ($free < 8) $warn[] = '⚡️ حافظه‌ی opcache دارد تمام می‌شود (' . number_format($free, 0) . ' مگابایت آزاد).';
        }
    }

    $biggest = 0; $biggestName = '';
    foreach ((glob(DATA_DIR . '/*.json') ?: []) as $f) {
        $kb = @filesize($f) / 1024;
        if ($kb > $biggest) { $biggest = $kb; $biggestName = basename($f, '.json'); }
    }
    if ($biggest > 2000)
        $warn[] = '🗄 فایلِ «' . $biggestName . '» به ' . number_format($biggest / 1024, 1) .
                   ' مگابایت رسیده — هر نوشتن روی آن کندتر می‌شود.';

    $free = @disk_free_space(DATA_DIR);
    if ($free !== false && $free < 100 * 1048576)
        $warn[] = '💾 فضای دیسک کم مانده (' . number_format($free / 1048576, 0) . ' مگابایت آزاد).';

    if (numReady()) {
        [$bal, $cur, $bErr] = numBalanceDo();
        if ($bErr === '' && $bal < 1)
            $warn[] = '☎️ موجودیِ حسابِ ' . numProvName() .
                       ' رو به اتمام است: <b>' . fmtNum($bal) . '</b> ' . h($cur) . '.';
    }

    if ($warn && function_exists('chTechAlert'))
        chTechAlert("🩺 <b>بررسیِ روزانه</b>\n\n" . implode("\n", $warn));
}

function admCheckText() {
    $ok = fn($b) => $b ? '✅' : '🔴';
    $sw = function ($fn) use ($ok) {
        if (!function_exists($fn)) return '➖ نصب نیست';
        return $fn() ? '✅ روشن' : '❌ خاموش';
    };

    $t  = "🩺 <b>چکاپِ بخش‌ها</b>\n\n";

    $gd   = function_exists('imagecreatetruecolor');
    $ft   = function_exists('imagettftext');
    $font = function_exists('pxFont') ? (string)pxFont(true) : '';
    $t .= "<b>ساختِ کارت‌ها</b>\n";
    $t .= $ok($gd) . " GD · " . $ok($ft) . " FreeType\n";
    $t .= ($font !== '' ? '✅ فونت: <code>' . h(basename($font)) . '</code>'
                        : '🔴 هیچ فونتی پیدا نشد') . "\n";
    if ($font === '')
        $t .= "└ تا این درست نشود، کارتِ قیمت و تاپ الماسی و بانک هر سه\n" .
              "   بی‌صدا به متنِ ساده تبدیل می‌شوند.\n" .
              "   پنل ← 💹 قیمت ← 🖼 کارت ← 🔄 گشتنِ دوباره‌ی فونت\n";
    $t .= "\n";

    $t .= "<b>قابلیت‌ها</b>\n";
    $rows = [
        'قیمت لحظه‌ای — کارت' => function_exists('pxVal')
            ? (!empty(pxVal('card.on')) ? (function_exists('pxCardWhy') && pxCardWhy() !== ''
                ? '⚠️ روشن، ولی ساخته نمی‌شود' : '✅ روشن') : '❌ خاموش')
            : '➖ نصب نیست',
        'تاپ الماسی'   => $sw('tpOn'),
        'تاپ الماسی — کارت' => function_exists('tpCardOn')
            ? (tpCardOn() ? '✅ روشن'
                          : (function_exists('tpVal') && !empty(tpVal('card'))
                             ? '⚠️ روشن، ولی ساخته نمی‌شود' : '❌ خاموش'))
            : '➖ نصب نیست',
        'بانک الماسی'  => $sw('bkOn'),
        'الماس'        => $sw('dmOn'),
        'بازی‌ها'      => $sw('gmOn'),
        'ماین'         => $sw('mnOn'),
        'کوییز'        => $sw('qzOn'),
        'درگاه رمزارز' => $sw('gwOn'),
    ];
    foreach ($rows as $k => $v) $t .= '• ' . $k . ': ' . $v . "\n";

    $t .= "\n<b>☎️ فروش شماره</b>\n";
    $t .= '• مینی‌اپ: ' . (maReady() ? '✅ آماده' : (empty(maCfg()['on']) ? '❌ خاموش' : '🔴 آدرس ثبت نشده')) . "\n";
    $t .= '• فروشنده: ' . (numReady() ? '✅ ' . h(numProvName()) : '🔴 وصل نیست') . "\n";
    $t .= '• کشور/اپراتورِ فعال: <b>' . count(maCatalogPublic()['cats']) . '</b> / <b>' .
          count(maCatalogPublic()['items']) . "</b>\n";
    $t .= '• شماره‌ی باز: <b>' . number_format(MaOrder::countBy(MaOrder::PAID)) . "</b>\n";

    if (function_exists('pxAssetPrice')) {
        $t .= "\n<b>قیمت‌ها</b>\n";
        foreach (['usd' => 'دلار', 'aed' => 'درهم', 'eur' => 'یورو',
                  'try' => 'لیر', 'gold' => 'طلا'] as $k => $nm) {
            $v = (float)pxAssetPrice($k);
            $t .= '• ' . $nm . ': ' . ($v > 0 ? '✅ ' . number_format($v) : '🔴 نیامد') . "\n";
        }
    }

    return $t;
}

function admCheck($chatId, $msgId = 0) {
    $t  = admCheckText();
    $kb = [[btnCb('🔄 دوباره چک کن', 'adm_check', 'confirm')],
           [btnCb(UT('back'), 'adm_home', 'nav')]];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($kb));
    else        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($kb));
}

function admSpeedText() {
    $t  = "⚡️ <b>سرعت ربات</b>\n\n";

    $st = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
    $on = is_array($st) && !empty($st['opcache_enabled']);
    $t .= "<b>opcache</b>: " . ($on ? '✅ روشن' : '❌ خاموش') . "\n";
    if ($on) {
        $mem  = $st['memory_usage'] ?? [];
        $free = (float)($mem['free_memory'] ?? 0) / 1048576;
        $hit  = (float)($st['opcache_statistics']['opcache_hit_rate'] ?? 0);
        $t .= 'اصابت: <b>' . number_format($hit, 1) . '٪</b> · حافظه‌ی آزاد: <b>' .
              number_format($free, 0) . "</b> مگابایت\n";
        if ($free < 8) $t .= "⚠️ حافظه‌اش کم است — <code>opcache.memory_consumption=128</code>\n";
        if ($hit > 0 && $hit < 90) $t .= "⚠️ نرخ اصابت پایین است؛ شاید حافظه‌اش کم باشد.\n";
    } else {
        $t .= "\n🔴 <b>مهم‌ترین کاری که می‌توانید بکنید همین است.</b>\n" .
              "بدون آن، PHP در هر درخواست کلِ کدِ ربات را از نو می‌خواند و " .
              "کامپایل می‌کند. روی یک سرور معمولی این تفاوت <b>۳۳</b> میلی‌ثانیه " .
              "با <b>۵</b> میلی‌ثانیه است — در هر پیام، هر دکمه، هر باز کردنِ مینی‌اپ.\n\n" .
              "در php.ini بگذارید:\n" .
              "<code>opcache.enable=1</code>\n" .
              "<code>opcache.memory_consumption=128</code>\n" .
              "<code>opcache.max_accelerated_files=10000</code>\n" .
              "<code>opcache.revalidate_freq=2</code>\n\n" .
              "در سی‌پنل: Select PHP Version ← Extensions ← تیک <b>opcache</b>\n";
    }

    $t .= "\n<b>فایل‌های داده</b>\n";
    $files = glob(DATA_DIR . '/*.json') ?: [];
    usort($files, fn($a, $b) => filesize($b) <=> filesize($a));
    $total = 0;
    foreach ($files as $f) $total += filesize($f);
    foreach (array_slice($files, 0, 6) as $f) {
        $kb = filesize($f) / 1024;
        $t .= '• <code>' . h(basename($f, '.json')) . '</code> — <b>' .
              number_format($kb, 1) . "</b> KB" .
              ($kb > 800 ? ' ⚠️' : '') . "\n";
    }
    $t .= 'مجموع: <b>' . number_format($total / 1024, 1) . "</b> KB\n";
    if ($total > 3145728)
        $t .= "⚠️ داده‌ها بزرگ شده‌اند. هر نوشتن یعنی بازنویسیِ کلِ فایل.\n";

    $probe = DATA_DIR . '/.speed.tmp';
    $blob  = str_repeat('x', 262144);
    $t0 = microtime(true);
    for ($i = 0; $i < 5; $i++) { file_put_contents($probe, $blob); }
    $w = (microtime(true) - $t0) / 5 * 1000;
    $t0 = microtime(true);
    for ($i = 0; $i < 5; $i++) { @file_get_contents($probe); }
    $r = (microtime(true) - $t0) / 5 * 1000;
    @unlink($probe);
    $t .= "\n<b>دیسک</b> (۲۵۶ کیلوبایت)\n";
    $t .= 'نوشتن: <b>' . number_format($w, 2) . '</b> ms · خواندن: <b>' .
          number_format($r, 2) . "</b> ms\n";
    if ($w > 20) $t .= "⚠️ دیسک کند است — روی هاست اشتراکی معمول است.\n";

    $t0 = microtime(true);
    for ($i = 0; $i < 5; $i++) maBoot();
    $b = (microtime(true) - $t0) / 5 * 1000;
    $t .= "\n<b>ساختِ صفحه‌ی مینی‌اپ</b>: <b>" . number_format($b, 2) . "</b> ms\n";

    return $t;
}

function runBackgroundQueues() {
    $gMark = DATA_DIR . '/.gw_at';
    if (time() - (@filemtime($gMark) ?: 0) >= 20) {
        @touch($gMark);
        gwPoll(10);
    }

    $qMark = DATA_DIR . '/.queue_at';
    if (time() - (@filemtime($qMark) ?: 0) >= 60) {
        @touch($qMark);
        gmTick(5);
        if (function_exists('mnTick')) mnTick(10);
        if (function_exists('qzTick')) qzTick(10);
        bcTick(20);

        if (function_exists('pxWarm') && function_exists('pxStale') && pxStale()) pxWarm();

        foreach (['preview_tg.php', 'preview_num.php', 'miniapp_view_tg.php', 'miniapp_view_num.php',
                  'miniapp_view_react.php', 'miniapp_view_unified.php', 'ops.php', 'ops_bot.php',
                  'ton_wallet.php', 'admin_ext.php', 'profit.php'] as $old)
            if (is_file(__DIR__ . '/' . $old)) @unlink(__DIR__ . '/' . $old);

        try {
            $healed = maHealUrls();
            if ($healed) error_log('[heal-urls] ' . implode(', ', $healed));
            maMenuSync();
        } catch (Throwable $e) { error_log('[heal-urls] ' . $e->getMessage()); }
    }

    $nMark = DATA_DIR . '/.num_at';
    if (time() - (@filemtime($nMark) ?: 0) >= 20) {
        @touch($nMark);
        numTick(10);
    }

    $sMark = DATA_DIR . '/.svc_at';
    if (function_exists('svTick') && time() - (@filemtime($sMark) ?: 0) >= 60) {
        @touch($sMark);
        svTick();
    }

    $cMark = DATA_DIR . '/.ma_cache_prune_at';
    if (time() - (@filemtime($cMark) ?: 0) >= 21600) {
        @touch($cMark);
        maCachePrune();
    }

    $stMark = DATA_DIR . '/.states_prune_at';
    if (time() - (@filemtime($stMark) ?: 0) >= 21600) {
        @touch($stMark);
        stateSweep();
    }

    $hMark = DATA_DIR . '/.health_at';
    if (time() - (@filemtime($hMark) ?: 0) >= 86400) {
        @touch($hMark);
        techHealthCheck();
    }

    migrateOnce('numbers_only_hooks', function () {
        $toks = [];
        foreach ((array)load('bots') as $b)
            if (is_array($b) && trim((string)($b['token'] ?? '')) !== '') $toks[] = trim((string)$b['token']);
        $ops = trim((string)(load('config')['ops']['token'] ?? ''));
        if ($ops !== '') $toks[] = $ops;
        if (defined('OPS_BOT_TOKEN') && trim((string)OPS_BOT_TOKEN) !== '') $toks[] = trim((string)OPS_BOT_TOKEN);
        foreach (array_unique($toks) as $t)
            if (!hash_equals((string)BOT_TOKEN, $t)) tg($t, 'deleteWebhook', ['drop_pending_updates' => 'true'], 8);
        maMenuSync();
        if (!maItems())
            adminAlertOnce('num_catalog_empty',
                "☎️ <b>فروشگاه شماره هنوز خالی است</b>\n\n" .
                "کشورها و اپراتورها را از فروشنده وارد کنید: /panel ← ☎️ شماره مجازی ← 📥 وارد کردن", 86400);
    });

    migrateOnce('v2', function () {
        if (function_exists('pxDropOldDemo')) pxDropOldDemo();
    });
    migrateOnce('v14_ref_approved_counts', function () {
        backfillRefCounts();
    });
    migrateOnce('v15_quiz_groups', function () {
        if (function_exists('qzAdoptOldChat')) qzAdoptOldChat();
    });
    migrateOnce('v15_member_updates', function () {
        hookAllowMemberUpdates();
    });
    migrateOnce('v15_cards', function () {
        foreach (['card_*.png', '_bg_v*.png', '_top_v*.png', 'top_*.png'] as $g)
            foreach ((array)glob(DATA_DIR . '/cards/' . $g) as $f) @unlink($f);
    });
    if (defined('FIVESIM_TOKEN') && trim((string)FIVESIM_TOKEN) !== '' && function_exists('numUse5sim'))
        migrateOnce('v16_5sim_' . substr(md5(preg_replace('/\s+/', '', (string)FIVESIM_TOKEN)), 0, 12), function () {
            if (!numTokenShape(preg_replace('/\s+/', '', (string)FIVESIM_TOKEN))['ok']) return;
            numUse5sim((string)FIVESIM_TOKEN);
            numSet(function (&$c) { $c['api']['on'] = true; });
        });
    migrateOnce('v15_buy_report', function () {
        if (function_exists('chSet'))
            chSet('mini_num', function (&$s) { $s['text'] = chDefaults()['mini_num']['text']; });
    });
    migrateOnce('v5', function () {
        if (function_exists('gmDropDoubleIcons')) gmDropDoubleIcons();
    });
    migrateOnce('v7_cooldown', function () {
        if (function_exists('dmSet')) dmSet(function (&$c) { $c['cooldown'] = 300; });
    });
    migrateOnce('v17_ttt_split', function () {
        if (!function_exists('gmSet')) return;
        gmSet(function (&$c) {
            unset($c['duel_board']);
            if (isset($c['word_duel'])) {
                $ttt  = array_map('mb_strtolower', gmWords((string)($c['word_ttt'] ?? 'دوز')));
                $keep = array_filter(gmWords((string)$c['word_duel']), fn($w) => !in_array(mb_strtolower($w), $ttt, true));
                $c['word_duel'] = $keep ? implode(',', $keep) : 'چالش';
            }
            $old = (string)($c['texts']['duel_turn'] ?? '');
            if ($old !== '' && !isset($c['texts']['ttt_turn'])) $c['texts']['ttt_turn'] = str_replace('چالش', 'دوز', $old);
            unset($c['texts']['duel_turn']);
        });
    });
    migrateOnce('v17_bank_on', function () {
        if (function_exists('bkSet')) bkSet(function (&$c) { $c['on'] = 1; $c['group_only'] = 0; });
    });
    migrateOnce('v18_shop_ui', function () {
        cfgSet(function (&$c) {
            $kash = str_contains(implode(' ', array_map('strval', (array)($c['ui_texts'] ?? []))), 'ـ');
            $shop = (string)($c['texts']['shop'] ?? '');
            if ($shop !== '' && str_contains($shop, 'اپراتور')) {
                if (str_contains($shop, '<tg-emoji'))
                    $c['texts']['shop'] =
                        "<tg-emoji emoji-id=\"5278702045883292456\">🛍</tg-emoji> <b>ثـبـت سـفـارش</b>\n" .
                        "<blockquote><tg-emoji emoji-id=\"5258073068852485953\">✈️</tg-emoji>    خدمات تلگرام: ممبر، بازدید، ری‌اکشن و بوست</blockquote>\n" .
                        "<blockquote>📸    خدمات اینستاگرام: فالوور، لایک، ویو و کامنت</blockquote>\n" .
                        "<blockquote><tg-emoji emoji-id=\"5319227750970573446\">📱</tg-emoji>    شماره مجازی: تحویل آنی، کد همین‌جا</blockquote>\n" .
                        "<tg-emoji emoji-id=\"5445353829304387411\">💳</tg-emoji> موجودی شما: <b>{balance}</b> تومان\n\n" .
                        "<tg-emoji emoji-id=\"5305265301917549162\">📎</tg-emoji> <b>سفارش‌های قبلی را با دکمه‌ی بالا ببینید.</b>";
                else unset($c['texts']['shop']);
            }
            if (str_contains(str_replace('ـ', '', (string)($c['ui_texts']['open_app'] ?? '')), 'فروشگاه')) {
                unset($c['ui_texts']['open_app'], $c['ui_icons']['open_app']);
                $c['ui_colors']['open_app'] = 'success';
            }
            if ($kash) {
                foreach (['shop_orders' => ['سـفـارش هـای ثـبـت شـده', '5444856076954520455'],
                          'open_tgs'    => ['خـدمـات تـلـگـرام', '5931391527023546946'],
                          'open_igs'    => ['📸 خـدمـات اینـسـتـاگـرام', ''],
                          'open_app'    => ['شـمـاره مـجـازی', '5766910798030966025']] as $k => [$t, $ic]) {
                    if (isset($c['ui_texts'][$k])) continue;
                    $c['ui_texts'][$k] = $t;
                    if ($ic !== '' && !isset($c['ui_icons'][$k])) $c['ui_icons'][$k] = $ic;
                }
            }
        });
        if (function_exists('gmSet')) gmSet(function (&$c) {
            $open = (string)($c['texts']['duel_open'] ?? '');
            if (str_contains($open, 'دوز') && !isset($c['texts']['ttt_open'])) {
                $c['texts']['ttt_open']  = $open;
                $c['texts']['duel_open'] = str_replace('دوز', 'چالش', $open);
            }
            $how = (string)($c['texts']['duel_how'] ?? '');
            if ($how !== '' && !isset($c['texts']['ttt_how']))
                $c['texts']['ttt_how'] = preg_replace('/چ[ـ]*ا[ـ]*ل[ـ]*ش/u', 'دوز', $how);
        });
        if (function_exists('bkSet')) bkSet(function (&$c) {
            foreach (['word_hack', 'manual_protect', 'shield_after', 'hack_cooldown', 'rng'] as $k) unset($c[$k]);
            foreach (array_keys((array)($c['texts'] ?? [])) as $k)
                if (preg_match('/^(hack_|risk_|btn_risk_)|^(protected|protect_still)$/', (string)$k)) unset($c['texts'][$k]);
        });
    });
    migrateOnce('v7_ttl', function () {
        if (function_exists('pxSet'))
            pxSet(function (&$c) { if ((int)($c['ttl'] ?? 0) < 60) $c['ttl'] = 60; });
    });

    migrateOnce('v10_cardfiles', function () {
        if (function_exists('pxDropCardCache')) pxDropCardCache();
    });

    migrateOnce('v12_5sim', function () {
        numForceTelegramOnly();
    });

    migrateOnce('v12_dmsum', function () {
        if (function_exists('dmSumRebuild')) dmSumRebuild();
    });

    migrateOnce('v13_gmnames', function () {
        if (!function_exists('gmSet')) return;
        gmSet(function (&$c) {
            foreach (['duel_win', 'rand_win'] as $k) {
                $t = (string)($c['texts'][$k] ?? '');
                if ($t === '' || !str_contains($t, '{winner}')) continue;
                $t = preg_replace('/<code>\s*\{winner\}\s*<\/code>/u', '{wname}', $t);
                $t = preg_replace('/<code>\s*\{loser\}\s*<\/code>/u',  '{lname}', $t);
                $t = str_replace(['{winner}', '{loser}'], ['{wname}', '{lname}'], $t);
                $t = str_replace(['کاربر برنده', 'کاربر بازنده'], ['برنده', 'بازنده'], $t);
                $c['texts'][$k] = $t;
            }
        });
    });

    migrateOnce('v13_dm5min', function () {
        if (!function_exists('dmSet')) return;
        dmSet(function (&$c) {
            $c['cooldown']   = 300;
            $c['level_step'] = 10000;
            if (isset($c['texts']['win']) && is_string($c['texts']['win']))
                $c['texts']['win'] = trim(preg_replace(
                    ['/\s*·?\s*پیشرفت:\s*\{progress\}/u', '/\{progress\}/u'],
                    '', $c['texts']['win']));
        });
    });

    $aMark = DATA_DIR . '/.archive_at';
    if (time() - (@filemtime($aMark) ?: 0) >= 3600) {
        @touch($aMark);
        ordersArchive(0, 800);
        maOrdersArchive(0, 800);
    }
}

if (isset($_GET['bot'])) {
    http_response_code(200);
    exit;
}

if (!webhookSecretOk()) {
    http_response_code(401);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "✅ ربات سالم است.\n\n" .
             "همین ۴۰۱ یعنی همه‌چیز درست کار می‌کند: این آدرس فقط آپدیتِ\n" .
             "امضاشده‌ی تلگرام را می‌پذیرد و درخواستِ مرورگر را — که امضا\n" .
             "ندارد — رد می‌کند. اگر خراب بود، این پیام را نمی‌دیدید.\n\n" .
             "برای دیدنِ وضعیتِ وبهوک، این را در مرورگر باز کنید\n" .
             "(توکن را از config.local.php بردارید):\n\n" .
             "  https://api.telegram.org/bot<TOKEN>/getWebhookInfo\n\n" .
             "پنلِ مدیریت: admin_panel.php (کنارِ همین فایل)\n";
    }
    exit;
}

$raw = file_get_contents('php://input');
$update = json_decode($raw, true);

http_response_code(200);

if (is_array($update)) {
    $dupe = (isset($update['update_id']) && !seenUpdate((int)$update['update_id']))
         || !seenMessage($update);
    if ($dupe) { echo json_encode(['ok' => true]); exit; }
}

closeRequest(json_encode(['ok' => true]));

if (is_array($update)) {
    try {
        masterHandle($update);
    } catch (Throwable $e) {
        $where = basename($e->getFile()) . ':' . $e->getLine();
        error_log('[shop-bot] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        if (function_exists('adminAlertOnce'))
            adminAlertOnce('crash_' . md5($where),
                "🔴 <b>خطای برنامه</b>\n\n<code>" . h(get_class($e)) . ': ' .
                h(mb_substr($e->getMessage(), 0, 300)) . "</code>\n📍 <code>" . h($where) . '</code>', 600);
    }
}

runBackgroundQueues();
