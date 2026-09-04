<?php
/**
 * 💎 ایردراپ — استخراج کریستال، سطح، فصل، لیدربورد، رفرال، ماموریت
 *
 * بخش تازه‌ی مینی‌اپ یکپارچه: هر کاربر یک نرخ استخراج ساعتی دارد (بسته
 * به سطحش) و کریستال به‌مرور در پس‌زمینه جمع می‌شود — دقیقا مثل بازی‌های
 * «tap to earn». به‌جای cron یا صف، همون الگوی «تسویه‌ی موقعِ خوندن» که
 * جاهای دیگر پروژه (numTick، dmUserSet) هم استفاده شده: هر بار کاربر
 * صفحه را باز می‌کند یا وضعیتش را می‌خواهد، adTick() فاصله‌ی زمانی از
 * آخرین تسویه را حساب و به موجودی اضافه می‌کند — چه یک ثانیه گذشته
 * باشد چه یک هفته.
 *
 * کاملا جدا از:
 *   • کیف‌پول فروشگاه (balance) — کریستال پول نیست، فقط با «تبدیل به
 *     کیف‌پول» (adRedeem) به آن راه پیدا می‌کند.
 *   • بازیِ الماس (diamond.php) — آن یک امتیازِ گروه است، این یک
 *     مکانیزمِ mini-app-محورِ جداگانه.
 *
 * ⚡️ سقفِ انباشتِ آفلاین (AD_MAX_BUFFER_HOURS): بدون این سقف، کاربری که
 *    یک ماه سر نمی‌زند، یک‌جا معادلِ یک ماه کریستال می‌گرفت — نه منصفانه
 *    نسبت به کسی که هرروز سر می‌زند، نه چیزی که «استخراجِ زنده» را واقعی
 *    نشان می‌دهد.
 */

define('AD_BASE_RATE', 30.0);        // کریستال در ساعت، در سطح ۱ (بدونِ بوست) — یعنی هر ۲ دقیقه یک کریستال
define('AD_XP_PER_LEVEL', 300.0);    // XP لازم برای رفتن هر سطح (ثابت، ساده)
define('AD_MAX_LEVEL', 50);
define('AD_MAX_BUFFER_HOURS', 24);   // سقفِ انباشتِ آفلاین
define('AD_SEASON_DAYS', 120);
define('AD_REDEEM_RATE', 50.0);      // هر ۱ کریستال = این‌قدر تومان
define('AD_REDEEM_MIN', 100.0);      // حداقل کریستال برای تبدیل
define('AD_BOOST_PRICE', 100.0);     // تومان — هزینه‌ی هر پله‌ی «افزایش سرعت»
define('AD_BOOST_STEP', 0.20);       // هر پله ۲۰٪ به نرخِ استخراج اضافه می‌کند
define('AD_BOOST_MAX', 25);          // سقفِ پله‌ها (نرخ حداکثر ۶ برابرِ پایه)

function adDbPath() { return DATA_DIR . '/airdrop.sqlite'; }

function adDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3')) return null;

    $path = adDbPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    try {
        $db = new SQLite3($path);
    } catch (Throwable $e) {
        error_log('[airdrop] airdrop.sqlite باز نشد: ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    // id=0 ردیفِ ویژه‌ی «فصل» است، نه یک کاربر — همیشه با متادیتای adSeasonMeta() دست می‌خورد.
    $db->exec('CREATE TABLE IF NOT EXISTS airdrop_users (
        id INTEGER PRIMARY KEY,
        crystals REAL NOT NULL DEFAULT 0,
        level INTEGER NOT NULL DEFAULT 1,
        xp REAL NOT NULL DEFAULT 0,
        last_tick INTEGER NOT NULL DEFAULT 0,
        season INTEGER NOT NULL DEFAULT 1,
        data TEXT NOT NULL DEFAULT \'{}\'
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_airdrop_crystals ON airdrop_users(season, crystals DESC)');
    return $db;
}

/** فصل جاری — اولین‌باری که کسی سراغش می‌آید ساخته می‌شود، بعد ثابت می‌ماند. */
function adSeasonMeta() {
    $db = adDb();
    $fallback = ['season' => 1, 'start' => time(), 'end' => time() + AD_SEASON_DAYS * 86400];
    if (!$db) return $fallback;

    $row = $db->querySingle('SELECT data FROM airdrop_users WHERE id = 0', true);
    if ($row) {
        $d = json_decode((string)($row['data'] ?? ''), true);
        if (is_array($d) && isset($d['season'], $d['start'], $d['end'])) return $d;
    }
    // رقابتِ دو درخواستِ هم‌زمان روی همین ردیف بی‌ضرر است: هر دو تقریبا
    // همین fallback را می‌نویسند، بدترین حالت چند ثانیه اختلاف در start.
    $stmt = $db->prepare('INSERT OR REPLACE INTO airdrop_users (id, crystals, level, xp, last_tick, season, data) VALUES (0,0,0,0,0,0,:d)');
    $stmt->bindValue(':d', json_encode($fallback), SQLITE3_TEXT);
    $stmt->execute();
    return $fallback;
}

function adXpForLevel($level) { return AD_XP_PER_LEVEL * max(1, (int)$level); }
/** تعدادِ پله‌های «افزایش سرعت»ِ خریداری‌شده — هر پله در data['boost_n'] نگه داشته می‌شود. */
function adBoostN($u) { return max(0, min(AD_BOOST_MAX, (int)($u['data']['boost_n'] ?? 0))); }
function adRate($level, $boostN = 0) {
    return AD_BASE_RATE * max(1, (int)$level) * (1 + AD_BOOST_STEP * max(0, (int)$boostN));
}

function adUser($uid) {
    $db = adDb();
    if (!$db) return null;
    $stmt = $db->prepare('SELECT crystals, level, xp, last_tick, season, data FROM airdrop_users WHERE id = :id');
    $stmt->bindValue(':id', (int)$uid, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $d = json_decode((string)($row['data'] ?? ''), true);
    return [
        'crystals'  => (float)$row['crystals'],
        'level'     => (int)$row['level'],
        'xp'        => (float)$row['xp'],
        'last_tick' => (int)$row['last_tick'],
        'season'    => (int)$row['season'],
        'data'      => is_array($d) ? $d : [],
    ];
}

/**
 * تغییرِ اتمیکِ یک کاربر — همان الگوی dmUserSet/numActsClaim: قفل فقط
 * روی همین یک ردیف (BEGIN IMMEDIATE)، نه کل جدول.
 */
function adUserSet($uid, callable $fn) {
    $db = adDb();
    if (!$db) return null;
    $id = (int)$uid;
    if ($id <= 0) return null;

    $db->exec('BEGIN IMMEDIATE');
    try {
        $stmt = $db->prepare('SELECT crystals, level, xp, last_tick, season, data FROM airdrop_users WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $season = adSeasonMeta()['season'];
        if ($row) {
            $d = json_decode((string)($row['data'] ?? ''), true);
            $u = [
                'crystals' => (float)$row['crystals'], 'level' => (int)$row['level'],
                'xp' => (float)$row['xp'], 'last_tick' => (int)$row['last_tick'],
                'season' => (int)$row['season'], 'data' => is_array($d) ? $d : [],
            ];
        } else {
            $u = ['crystals' => 0.0, 'level' => 1, 'xp' => 0.0, 'last_tick' => time(), 'season' => $season, 'data' => []];
        }

        $result = $fn($u);

        $up = $db->prepare('INSERT OR REPLACE INTO airdrop_users (id, crystals, level, xp, last_tick, season, data) VALUES (:id,:c,:l,:x,:t,:s,:d)');
        $up->bindValue(':id', $id, SQLITE3_INTEGER);
        $up->bindValue(':c', (float)$u['crystals'], SQLITE3_FLOAT);
        $up->bindValue(':l', (int)$u['level'], SQLITE3_INTEGER);
        $up->bindValue(':x', (float)$u['xp'], SQLITE3_FLOAT);
        $up->bindValue(':t', (int)$u['last_tick'], SQLITE3_INTEGER);
        $up->bindValue(':s', (int)$u['season'], SQLITE3_INTEGER);
        $up->bindValue(':d', json_encode($u['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        $up->execute();
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        error_log('[airdrop] adUserSet خطا: ' . $e->getMessage());
        return null;
    }
    return $result;
}

/**
 * تسویه‌ی استخراج تا همین لحظه + به‌روزرسانیِ نام/یوزرنیم برای لیدربورد.
 * همیشه همین تابع صدا زده می‌شود، نه SELECT خام — چون خودِ خواندن است
 * که باید موجودیِ تازه را حساب کند.
 */
function adTick($uid, $name = '', $username = '') {
    return adUserSet($uid, function (&$u) use ($name, $username) {
        $now = time();
        $season = adSeasonMeta()['season'];
        if ((int)$u['season'] !== (int)$season) {
            // فصل عوض شده — کریستال/سطح این فصل از صفر شروع می‌شود، ولی
            // خودِ ردیف (و XP کلی که در data نگه داشته می‌شود) از بین نمی‌رود.
            $u['data']['prev_seasons'][(string)$u['season']] = ['crystals' => $u['crystals'], 'level' => $u['level']];
            $u['crystals'] = 0.0; $u['level'] = 1; $u['xp'] = 0.0; $u['season'] = $season;
        }
        $elapsed = max(0, $now - (int)$u['last_tick']);
        $elapsed = min($elapsed, AD_MAX_BUFFER_HOURS * 3600);
        if ($elapsed > 0) {
            $earned = adRate($u['level'], adBoostN($u)) * ($elapsed / 3600.0);
            $u['crystals'] = round($u['crystals'] + $earned, 4);
            $u['xp'] = round($u['xp'] + $earned, 4);
            while ($u['level'] < AD_MAX_LEVEL && $u['xp'] >= adXpForLevel($u['level'])) {
                $u['xp'] -= adXpForLevel($u['level']);
                $u['level']++;
            }
        }
        $u['last_tick'] = $now;
        if ($name !== '')     $u['data']['name'] = mb_substr($name, 0, 40);
        if ($username !== '') $u['data']['username'] = mb_substr(ltrim($username, '@'), 0, 40);
        return $u;
    });
}

/** وضعیتِ کامل برای نمایش — بعد از تسویه. */
function adState($uid, $name = '', $username = '') {
    $u = adTick($uid, $name, $username);
    if (!$u) $u = ['crystals' => 0.0, 'level' => 1, 'xp' => 0.0, 'last_tick' => time(), 'season' => 1, 'data' => []];
    $meta = adSeasonMeta();
    $boostN = adBoostN($u);
    $rate = adRate($u['level'], $boostN);
    $need = adXpForLevel($u['level']);
    return [
        'crystals'   => $u['crystals'],
        'level'      => $u['level'],
        'xp'         => $u['xp'],
        'xp_need'    => $need,
        'rate'       => $rate,
        'tick_secs'  => $rate > 0 ? round(3600.0 / $rate) : 0,
        'boost_n'    => $boostN,
        'boost_max'  => AD_BOOST_MAX,
        'boost_price'=> AD_BOOST_PRICE,
        'boost_step' => AD_BOOST_STEP,
        'season'     => $meta['season'],
        'season_end' => $meta['end'],
        'missions'   => adMissions($uid, $u),
        'redeem_rate'=> AD_REDEEM_RATE,
        'redeem_min' => AD_REDEEM_MIN,
    ];
}

// ============================================================
// 🏆 لیدربورد — کش‌شده، چون رویِ ۲۰هزار کاربر هر باز شدنِ صفحه
//    نباید کلِ جدول را دوباره مرتب کند.
// ============================================================

function adLeaderboard($limit = 50) {
    $limit = max(1, min(100, (int)$limit));
    $ck = 'ad_leader_' . $limit;
    $cached = maCacheGet($ck, 20);
    if (is_array($cached)) return $cached;

    $db = adDb();
    if (!$db) return [];
    $season = adSeasonMeta()['season'];
    $stmt = $db->prepare('SELECT id, crystals, data FROM airdrop_users WHERE id != 0 AND season = :s ORDER BY crystals DESC LIMIT :n');
    $stmt->bindValue(':s', $season, SQLITE3_INTEGER);
    $stmt->bindValue(':n', $limit, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $out = [];
    $rank = 1;
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $d = json_decode((string)($row['data'] ?? ''), true);
        $d = is_array($d) ? $d : [];
        $out[] = [
            'rank'     => $rank++,
            'uid'      => (int)$row['id'],
            'crystals' => (float)$row['crystals'],
            'name'     => (string)($d['name'] ?? ''),
            'username' => (string)($d['username'] ?? ''),
        ];
    }
    maCachePut($ck, $out);
    return $out;
}

/**
 * برترین معرف‌ها — از رویِ ref_count که در بدنه‌ی users.sqlite (پروژه‌ی
 * اصلی) کش شده. هیچ ستونِ ایندکس‌شده‌ای برایش نیست، پس با یک کوئری
 * json_extract روی همه‌ی کاربران — قابل قبول چون کش می‌شود و فقط وقتی
 * کسی تبِ رفرالِ ایردراپ را باز می‌کند اجرا می‌شود، نه هر بارگذاری.
 */
function adTopReferrers($limit = 50) {
    $limit = max(1, min(100, (int)$limit));
    $ck = 'ad_refs_' . $limit;
    $cached = maCacheGet($ck, 45);
    if (is_array($cached)) return $cached;

    if (!function_exists('usersDb')) return [];
    $db = usersDb();
    if (!$db) return [];
    $stmt = $db->prepare(
        "SELECT id, json_extract(data,'$.ref_count') AS rc, data FROM users " .
        "WHERE json_extract(data,'$.ref_count') > 0 ORDER BY rc DESC LIMIT :n"
    );
    $stmt->bindValue(':n', $limit, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $out = [];
    $rank = 1;
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $d = json_decode((string)($row['data'] ?? ''), true);
        $d = is_array($d) ? $d : [];
        $name = trim((string)($d['first_name'] ?? '') . ' ' . (string)($d['last_name'] ?? ''));
        $out[] = [
            'rank'     => $rank++,
            'uid'      => (int)$row['id'],
            'count'    => (int)$row['rc'],
            'name'     => $name,
            'username' => (string)($d['username'] ?? ''),
        ];
    }
    maCachePut($ck, $out);
    return $out;
}

/** داده‌ی تب رفرالِ ایردراپ — بر پایه‌ی سیستمِ رفرالِ موجودِ ربات، نه چیزِ تازه. */
function adReferralInfo($uid) {
    $u = function_exists('getUser') ? getUser($uid) : null;
    return [
        'link'       => function_exists('refInviteLink') ? refInviteLink($uid) : '',
        'ref_count'  => (int)($u['ref_count'] ?? 0),
        'ref_earned' => (float)($u['ref_earned'] ?? 0),
        'ref_pending'=> (float)($u['ref_pending'] ?? 0),
        'top'        => adTopReferrers(20),
    ];
}

// ============================================================
// 🎯 ماموریت‌ها — چند مورد ساده، بر پایه‌ی داده‌ی واقعی (نه چیزِ فرمایشی)
// ============================================================

function adMissionDefs() {
    return [
        ['id' => 'daily',    'emoji' => '📅', 'name' => 'حضور روزانه',        'reward' => 20,  'kind' => 'daily'],
        ['id' => 'ref1',     'emoji' => '👥', 'name' => 'دعوت اولین دوست',    'reward' => 60,  'kind' => 'ref',   'need' => 1],
        ['id' => 'ref5',     'emoji' => '🚀', 'name' => 'دعوت ۵ دوست',        'reward' => 300, 'kind' => 'ref',   'need' => 5],
        ['id' => 'order1',   'emoji' => '🛍', 'name' => 'اولین خرید از فروشگاه','reward' => 80,'kind' => 'order', 'need' => 1],
    ];
}

/** وضعیتِ هر ماموریت برای این کاربر: پیشرفت، آماده‌ی claim، claim‌شده. */
function adMissions($uid, $u = null) {
    if ($u === null) $u = adUser($uid) ?: ['data' => []];
    $data = $u['data'] ?? [];
    $claimed = (array)($data['claimed'] ?? []);
    $refCount = function_exists('countReferrals') ? countReferrals($uid) : 0;
    $orderCount = class_exists('MaOrder') ? count(MaOrder::forUser($uid, 1)) : 0;
    $today = gmdate('Y-m-d');

    $out = [];
    foreach (adMissionDefs() as $m) {
        $done = false; $progress = 0; $need = (int)($m['need'] ?? 1);
        if ($m['kind'] === 'daily') {
            $done = ($data['daily_at'] ?? '') === $today;
            $progress = $done ? 1 : 0; $need = 1;
        } elseif ($m['kind'] === 'ref') {
            $progress = $refCount; $done = $refCount >= $need;
        } elseif ($m['kind'] === 'order') {
            $progress = $orderCount; $done = $orderCount >= $need;
        }
        $isClaimed = !empty($claimed[$m['id']]) && ($m['kind'] !== 'daily' || $claimed[$m['id']] === $today);
        $out[] = [
            'id' => $m['id'], 'emoji' => $m['emoji'], 'name' => $m['name'], 'reward' => $m['reward'],
            'progress' => min($progress, $need), 'need' => $need,
            'ready' => $done && !$isClaimed, 'claimed' => $isClaimed,
        ];
    }
    return $out;
}

/** claim یک ماموریت — سرور دوباره خودش شرط را چک می‌کند، به کلاینت اعتماد نمی‌شود. */
function adClaimMission($uid, $missionId) {
    $defs = [];
    foreach (adMissionDefs() as $m) $defs[$m['id']] = $m;
    if (!isset($defs[$missionId])) return [false, 'ماموریت پیدا نشد.'];

    $reward = 0; $ok = false; $msg = '';
    adUserSet($uid, function (&$u) use ($uid, $missionId, $defs, &$reward, &$ok, &$msg) {
        $m = $defs[$missionId];
        $claimed = (array)($u['data']['claimed'] ?? []);
        $today = gmdate('Y-m-d');

        if ($m['kind'] === 'daily') {
            if (($claimed[$missionId] ?? '') === $today) { $msg = 'امروز قبلا گرفته‌ای.'; return; }
        } elseif (!empty($claimed[$missionId])) { $msg = 'قبلا دریافت شده.'; return; }

        $done = false;
        if ($m['kind'] === 'daily') $done = true;
        elseif ($m['kind'] === 'ref') $done = (function_exists('countReferrals') ? countReferrals($uid) : 0) >= (int)$m['need'];
        elseif ($m['kind'] === 'order') $done = (class_exists('MaOrder') ? count(MaOrder::forUser($uid, 1)) : 0) >= (int)$m['need'];
        if (!$done) { $msg = 'هنوز شرایطش کامل نشده.'; return; }

        $reward = (float)$m['reward'];
        $u['crystals'] = round($u['crystals'] + $reward, 4);
        $u['xp'] = round($u['xp'] + $reward, 4);
        while ($u['level'] < AD_MAX_LEVEL && $u['xp'] >= adXpForLevel($u['level'])) {
            $u['xp'] -= adXpForLevel($u['level']); $u['level']++;
        }
        $claimed[$missionId] = $m['kind'] === 'daily' ? $today : true;
        $u['data']['claimed'] = $claimed;
        $ok = true;
    });
    return $ok ? [true, $reward] : [false, $msg ?: 'دریافت انجام نشد.'];
}

// ============================================================
// 💱 تبدیل کریستال به موجودیِ کیف‌پول
// ============================================================

function adRedeem($uid, $amount) {
    $amount = round((float)$amount, 4);
    if ($amount < AD_REDEEM_MIN) return [false, 'حداقل ' . (int)AD_REDEEM_MIN . ' کریستال لازم است.'];

    $ok = false; $toman = 0.0;
    adUserSet($uid, function (&$u) use ($amount, &$ok, &$toman) {
        if ($u['crystals'] + 0.001 < $amount) return;
        $u['crystals'] = round($u['crystals'] - $amount, 4);
        $toman = round($amount * AD_REDEEM_RATE, 2);
        $ok = true;
    });
    if (!$ok) return [false, 'کریستال کافی نیست.'];
    if (function_exists('addBalance')) addBalance($uid, $toman);
    return [true, $toman];
}

// ============================================================
// ⚡️ افزایش سرعت — با ۱۰۰ تومان، یک پله (۲۰٪) به نرخِ استخراج اضافه می‌شود
// ============================================================

/**
 * خریدِ یک پله‌ی «افزایش سرعت». کسرِ کیف‌پول و افزایشِ boost_n دو سیستمِ
 * جدا هستند (کیف‌پول در users.sqlite، این یکی در airdrop.sqlite)، پس
 * یک تراکنشِ واحد ممکن نیست — دقیقا مثلِ maPayFromWallet. برای جلوگیری
 * از هدر رفتنِ پول در رقابتِ نادرِ «دقیقا روی سقف»، اگر پله‌گیریِ اتمیک
 * نشان داد که کاربر همان لحظه به سقف رسیده، پول برمی‌گردد.
 */
function adBuyBoost($uid) {
    if (!function_exists('debitBalance') || !function_exists('addBalance'))
        return [false, 'خرید در دسترس نیست.'];

    $u = adUser($uid);
    if ($u && adBoostN($u) >= AD_BOOST_MAX) return [false, 'به سقفِ افزایشِ سرعت رسیده‌ای.'];
    if (!debitBalance($uid, AD_BOOST_PRICE)) return [false, 'موجودی کیف‌پول کافی نیست.'];

    $n = 0; $atCap = false;
    adUserSet($uid, function (&$u) use (&$n, &$atCap) {
        $cur = adBoostN($u);
        if ($cur >= AD_BOOST_MAX) { $atCap = true; $n = $cur; return; }
        $n = $cur + 1;
        $u['data']['boost_n'] = $n;
    });
    if ($atCap) {
        addBalance($uid, AD_BOOST_PRICE);
        return [false, 'به سقفِ افزایشِ سرعت رسیده‌ای.'];
    }
    return [true, $n];
}
