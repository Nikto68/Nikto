<?php

if (!defined('SV_OPEN_MAX')) define('SV_OPEN_MAX', 10);

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
        'url'     => '',
        'key'     => '',
        'cur'     => 'toman',
        'fx'      => 0,
        'fx_live' => 0,
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
    return array_replace_recursive(svDefaults(), is_array($s) ? $s : []);
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
    return $db;
}


function svFx() {
    $c = svCfg();
    switch ((string)$c['cur']) {
        case 'toman': return 1.0;
        case 'rial':  return 0.1;
        case 'usd_live':
            if ((float)$c['fx_live'] > 0) return (float)$c['fx_live'];
            return (float)$c['fx'];
        default: return (float)$c['fx'];
    }
}

function svFxRefresh() {
    if ((string)svCfg()['cur'] !== 'usd_live' || !function_exists('pxUsdtIrt')) return 0.0;
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

function svTypeOk($type) {
    $t = strtolower(trim((string)$type));
    return $t === '' || $t === 'default';
}


function svGuess($pname, $pcat) {
    $s = mb_strtolower($pname . ' ' . $pcat);
    $isIg = preg_match('/instagram|insta\b|اینستا|\big\b/u', $s);
    $isTg = preg_match('/telegram|تلگرام|\btg\b/u', $s);
    if ($isIg && !$isTg) {
        $map = [
            'followers' => '/follower|فالو/u', 'likes' => '/like|لایک/u', 'story' => '/story|استوری/u',
            'comments'  => '/comment|کامنت/u', 'saves' => '/save|share|سیو|اشتراک/u',
            'views'     => '/view|reel|play|igtv|video|ویو|بازدید/u',
        ];
        foreach ($map as $cat => $rx) if (preg_match($rx, $s)) return ['ig', $cat];
        return ['ig', 'other'];
    }
    if ($isTg) {
        $map = [
            'members'   => '/member|subscriber|ممبر|عضو/u', 'reactions' => '/reaction|ری.?اکشن|emoji/u',
            'premium'   => '/premium|boost|star|پریمیوم|بوست/u', 'votes' => '/vote|poll|رای/u',
            'comments'  => '/comment|کامنت/u', 'views' => '/view|بازدید|سین/u',
        ];
        foreach ($map as $cat => $rx) if (preg_match($rx, $s)) return ['tg', $cat];
        return ['tg', 'other'];
    }
    return ['', 'other'];
}

function svCleanName($pname) {
    $n = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$pname)));
    return mb_substr($n, 0, 90);
}

function svRowOf(array $r) {
    $r['active'] = (int)$r['active']; $r['min'] = (int)$r['min']; $r['max'] = (int)$r['max'];
    $r['refill'] = (int)$r['refill']; $r['cancel'] = (int)$r['cancel']; $r['gone'] = (int)$r['gone'];
    $r['rate'] = (float)$r['rate']; $r['price'] = (float)$r['price']; $r['pos'] = (int)$r['pos'];
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
        $items[] = [
            'i' => (string)$s['id'], 'c' => $c, 'n' => $s['name'] !== '' ? $s['name'] : svCleanName($s['pname']),
            'p' => $p, 'mn' => max(1, $s['min']), 'mx' => max(max(1, $s['min']), $s['max']),
            'r' => $s['refill'] ? 1 : 0,
        ];
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
    return [(float)$j['balance'], strtoupper(trim((string)($j['currency'] ?? ''))), ''];
}

function svImport() {
    @set_time_limit(120);
    [$j, $err, $kind] = svHttp(['action' => 'services'], 45);
    if ($j === null) return [false, $kind === 'api' ? svErrText($err) : $err];
    $list = isset($j[0]) ? array_values($j) : (is_array($j['services'] ?? null) ? array_values($j['services']) : []);
    if (!$list) return [false, 'پنل هیچ سرویسی برنگرداند.'];

    $db = svDb();
    if (!$db) return [false, 'دیتابیسِ خدمات باز نشد.'];
    svFxRefresh();

    $seen = [];
    $new = $upd = $tgN = $igN = $skip = 0;
    $now = time();
    $db->exec('BEGIN IMMEDIATE');
    try {
        $get = $db->prepare('SELECT id FROM svc WHERE id = :id');
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
    return [true, 'از پنل ' . fmtNum(count($seen)) . ' سرویس خوانده شد — تازه: ' . fmtNum($new) .
                  ' (تلگرام ' . fmtNum($tgN) . '، اینستاگرام ' . fmtNum($igN) . '، نامشخص ' . fmtNum($skip) . ')' .
                  ' · به‌روزشده: ' . fmtNum($upd) . ' · حذف‌شده از پنل: ' . fmtNum($gone) .
                  ($new ? "\nسرویس‌های تازه خاموش‌اند؛ در «سرویس‌ها» آن‌هایی را که می‌خواهید بفروشید روشن کنید." : '')];
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
    $st = $db->prepare('UPDATE svc SET app = :app, cat = :cat, active = :on, name = :name, price = :price WHERE id = :id');
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

function svAdminList($app, $q, $onlyOn, $page, $per) {
    $db = svDb();
    if (!$db) return [[], 0];
    $w = ['gone = 0'];
    $b = [];
    if ($app === 'none') $w[] = "app = ''";
    elseif ($app !== 'all') { $w[] = 'app = :app'; $b[':app'] = $app; }
    if ($onlyOn === 'on')  $w[] = 'active = 1';
    if ($onlyOn === 'off') $w[] = 'active = 0';
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
    return [
        'id' => (string)$o['id'], 'app' => (string)$o['app'], 'n' => (string)$o['name'], 'c' => (string)$o['cat'],
        'l' => (string)$o['link'], 'q' => $qty, 't' => (float)$o['total'], 'rf' => (float)$o['refunded'],
        'st' => (string)$o['status'], 'sx' => svStatusText((string)$o['status'], (string)$o['pst']),
        'sc' => (int)$o['start'], 'rm' => $rem, 'pc' => $done, 'at' => (int)$o['created'],
    ];
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


function svBuy($uid, $uname, $app, $sid, $link, $qty, $seen = 0.0) {
    if (!svAppOn($app)) return [false, 'closed', 'این بخش موقتا بسته است — کمی بعد دوباره امتحان کنید.', []];
    $s = svService($sid);
    if (!$s || $s['app'] !== $app || !$s['active'] || $s['gone'] || !svTypeOk($s['type']))
        return [false, 'bad_item', 'این سرویس دیگر فعال نیست — صفحه را دوباره باز کنید.', []];

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
    if (maDuplicateOrder($uid, 'sv_' . $app, (string)$sid, $qty, $link, 30))
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

    [$j, $err, $kind] = svHttp(['action' => 'add', 'service' => (string)$sid, 'link' => $link, 'quantity' => $qty]);
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
        'sup'     => maSupportLink(),
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
                                         (int)maNum($body['qty'] ?? 0), maNum($body['seen'] ?? 0));
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
    $t .= '🔌 پنلِ خدمات (API): ' . (svReady() ? '✅ وصل' : '❌ ثبت نشده') . "\n";
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
