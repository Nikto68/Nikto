<?php


function bkDefaults() {
    return [
        'on'         => false,
        'group_only' => 1,
        'word_bank'  => 'بانک,حساب بانکی',
        'word_hack'  => 'سرقت الماس',

        'manual_protect' => 900,
        'shield_after'   => 300,
        'hack_cooldown'  => 1200,
        'level_step'     => 500000,
        'top_n'          => 10,
        'card_image'     => 1,
        'card_footer'    => 'کارتِ اختصاصیِ شما',

        'interest' => [
            'on'       => true,
            'day_secs' => 86400,
            'max_days' => 30,
            'tiers'    => [
                [0,          0.5],
                [100000,     0.8],
                [1000000,    1.2],
                [10000000,   1.6],
                [100000000,  2.0],
            ],
        ],

        'rng' => [
            'base_success'  => 42.0,
            'success_floor' => 18.0,
            'success_ceil'  => 72.0,
            'jitter_pct'    => 12.0,
            'jackpot_pct'   => 0.4,
            'perfect_pct'   => 7.0,
            'critfail_pct'  => 22.0,
            'partial_share' => 0.35,

            'jackpot_min'  => 25.0, 'jackpot_max' => 40.0,
            'perfect_min'  => 10.0, 'perfect_max' => 16.0,
            'success_min'  => 4.0,  'success_max' => 10.0,
            'partial_min'  => 1.0,  'partial_max' => 4.0,
            'critfail_min' => 5.0,  'critfail_max' => 15.0,
        ],

        'risk' => [
            'high'   => ['success_mul' => 0.55, 'amount_mul' => 2.40, 'critfail_mul' => 2.60, 'cooldown_mul' => 1.35],
            'normal' => ['success_mul' => 1.00, 'amount_mul' => 1.00, 'critfail_mul' => 1.00, 'cooldown_mul' => 1.00],
            'low'    => ['success_mul' => 1.55, 'amount_mul' => 0.40, 'critfail_mul' => 0.30, 'cooldown_mul' => 0.75],
        ],

        'icons' => ['btn_protect' => '', 'btn_send' => '', 'btn_send_confirm' => '', 'btn_back' => '',
                    'btn_dep' => '', 'btn_wd' => '',
                    'btn_risk_high' => '', 'btn_risk_normal' => '', 'btn_risk_low' => ''],
        'btns'  => [
            'btn_risk_high'    => ['color' => 'danger'],
            'btn_risk_normal'  => ['color' => 'primary'],
            'btn_risk_low'     => ['color' => 'success'],
            'btn_protect'      => ['color' => 'primary'],
            'btn_dep'          => ['color' => 'success'],
            'btn_wd'           => ['color' => 'none'],
            'btn_send'         => ['color' => 'none'],
            'btn_send_confirm' => ['color' => 'success'],
            'btn_back'         => ['color' => 'none'],
        ],

        'texts' => [
            'btn_protect'  => '🛡 حفاظت از بانک',
            'btn_dep'          => '🏦 انتقال به بانک',
            'btn_wd'           => '💸 برداشت از بانک',
            'btn_send'         => '🎁 ارسال به کاربر',
            'btn_send_confirm' => '✅ انتقال',
            'btn_back'         => '🔙 برگشت',

            'card' => "🏦 <b>BANK</b>\n\n" .
                      "👤 User: {name}\n\n" .
                      "💎 موجودیِ کیف‌پول (قابلِ سرقت): <b>{wallet}</b>\n" .
                      "🏦 موجودیِ بانک (امن): <b>{vault}</b>\n" .
                      "📈 سودِ روزانه: <b>{rate}٪</b>\n\n" .
                      "🔐 Security: {sec_status}\n" .
                      "⏱ Protection: {protect_left}\n\n" .
                      "📊 Bank Level: {level}\n" .
                      "🔥 Successful Heists: {wins}\n" .
                      "💰 Total Stolen: {stolen}\n\n" .
                      "━━━━━━━━━━━━━━",

            'protected' => "🛡 <b>BANK PROTECTED</b>\n\n" .
                           "بانک شما با موفقیت محافظت شد.\n\n" .
                           "⏱ مدت حفاظت: {mins} دقیقه\n\n" .
                           "🔐 Status: ACTIVE",
            'protect_still' => "🛡 بانک شما همین الان هم محافظت‌شده است.\n⏱ {left} باقی مانده.",

            'dep_ask' => "🏦 <b>انتقال به بانک</b>\n\n" .
                         "چند الماس به صندوقِ بانک منتقل شود؟ عدد را بفرستید.\n\n" .
                         "💎 کیفِ‌پول: <b>{wallet}</b>\n" .
                         "🏦 صندوق: <b>{vault}</b>\n\n" .
                         "برایِ انتقالِ همه، بنویسید <code>همه</code>.",
            'dep_ok'  => "✅ <b>{amount}</b> الماس به بانک منتقل شد.\n\n" .
                         "💎 کیفِ‌پول: <b>{wallet}</b>\n🏦 صندوق: <b>{vault}</b>\n" .
                         "📈 سودِ روزانه‌ی این مبلغ: <b>{rate}٪</b>",
            'dep_low' => "❌ این‌قدر الماس تویِ کیفِ‌پول نداری.\n💎 موجودی: <b>{wallet}</b>",

            'wd_ask' => "💸 <b>برداشت از بانک</b>\n\n" .
                        "چند الماس از صندوق برداشته شود؟ عدد را بفرستید.\n\n" .
                        "🏦 صندوق: <b>{vault}</b>\n" .
                        "💎 کیفِ‌پول: <b>{wallet}</b>\n\n" .
                        "برایِ برداشتِ همه، بنویسید <code>همه</code>.",
            'wd_ok'  => "✅ <b>{amount}</b> الماس از بانک برداشته شد.\n\n" .
                        "💎 کیفِ‌پول: <b>{wallet}</b>\n🏦 صندوق: <b>{vault}</b>",
            'wd_low' => "❌ این‌قدر الماس تویِ صندوق نیست.\n🏦 صندوق: <b>{vault}</b>",

            'ask_bad_num' => "❌ یک عددِ صحیح و بزرگ‌تر از صفر بفرستید.",
            'ask_expired' => "⌛️ این درخواست منقضی شده. دوباره از روی /bank شروع کنید.",
            'not_your_card' => "🔒 این کارتِ بانکِ شما نیست — با /bank کارتِ خودتان را باز کنید.",

            'ask_send_amt'   => "🎁 <b>SEND DIAMONDS</b>\n\nچند تا الماس می‌خوای بفرستی؟",
            'ask_send_id'    => "👤 روی همین پیام <b>ریپلای</b> کن و آیدیِ عددی یا یوزرنیمِ گیرنده (@username) را بفرست.\n\n<i>راهنما: کاربرِ موردنظر باید قبلا با ربات پیام داده باشد.</i>",
            'send_need_reply' => "↩️ برای انتقال، باید روی <b>همین پیامِ ربات</b> ریپلای کنی و آیدیِ گیرنده را بنویسی.\n\n<i>بدونِ ریپلای انتقال انجام نمی‌شود — این جلوی فرستادنِ اشتباهی به آدمِ دیگر را می‌گیرد.</i>",
            'ask_send_badid' => "❌ این آیدی/یوزرنیم معتبر نبود.",
            'send_self'      => "😄 نمی‌تونی برایِ خودت بفرستی.",
            'send_no_target' => "❌ این آیدی برایِ ربات شناخته‌شده نیست — گیرنده باید قبلا با ربات پیام داده باشد.",
            'send_low_wallet'=> "❌ موجودیِ کیف‌پول کافی نیست.\n💎 Wallet: <b>{wallet}</b>",
            'send_confirm'   => "🎁 <b>تاییدِ ارسال</b>\n\n💎 مقدار: <b>{amount}</b>\n👤 گیرنده: {to_tag}\n\nبرایِ تاییدِ نهایی بزن:",
            'send_ok'        => "✅ <b>SEND SUCCESS</b>\n\n💎 Sent: {amount}\n👤 From: {from_tag}\n👤 To: {to_tag}\n\n━━━━━━━━━━━━━━\n\n💎 Wallet: <b>{wallet}</b>",

            'hack_how'      => "🔫 برای هک، روی پیامِ همون کاربر ریپلای کن و بنویس «{word}» یا /hack",
            'hack_self'     => "😄 نمی‌تونی خودتو هک کنی.",
            'hack_no_target'=> "❌ Target not found",
            'hack_protected'=> "🛡 <b>HACK BLOCKED</b>\n\nاین بانک در حال حاضر تحت حفاظت است.\n\n⏱ Protection remaining: {left}",
            'hack_empty'    => "❌ <b>EMPTY BANK</b>\n\nاین بانک الماسی برای سرقت ندارد.",

            'hack_menu'     => "<b>هدف قفل شد</b>\n\n" .
                               "قربانی: {tn}\n" .
                               "موجودیِ قابلِ سرقت: <b>{bal}</b>\n\n" .
                               "چطور می‌خواهی بزنی؟\n\n" .
                               "| <b>پرریسک</b> — غنیمتِ سنگین، ولی احتمالِ دستگیری بالا\n" .
                               "| <b>معمولی</b> — تعادلِ شانس و غنیمت\n" .
                               "| <b>کم‌ریسک</b> — تقریبا همیشه می‌گیری، ولی کم\n\n" .
                               "<i>فقط {hn} می‌تواند انتخاب کند.</i>",
            'btn_risk_high'   => 'سرقت (پرریسک)',
            'btn_risk_normal' => 'سرقت (معمولی)',
            'btn_risk_low'    => 'سرقت (کم‌ریسک)',
            'hack_not_yours'  => '🔒 این سرقتِ تو نیست.',
            'hack_menu_gone'  => '⌛️ این درخواست منقضی شده — دوباره ریپلای کن.',
            'risk_line'       => "\n\n🎚 سطح: <b>{risk}</b>",
            'risk_high'       => 'پرریسک',
            'risk_normal'     => 'معمولی',
            'risk_low'        => 'کم‌ریسک',
            'hack_cooldown' => "⏳ <b>HACK COOLDOWN</b>\n\nدوباره می‌توانید Hack کنید:\n\n{left}\n\nباقی مانده است.",

            'hack_jackpot'  => "🎯 <b>JACKPOT!</b>\n\n{hn} بانکِ {tn} رو به فنا داد!\n\n💎 +{amount} ({pct}%)\n🏦 موجودیِ بانکِ تو: <b>{bank}</b>",
            'hack_perfect'  => "🟢 <b>PERFECT HEIST</b>\n\n{hn} یه سرقتِ حرفه‌ای از بانکِ {tn} زد!\n\n💎 +{amount} ({pct}%)\n🏦 موجودیِ بانکِ تو: <b>{bank}</b>",
            'hack_success'  => "🟢 <b>SUCCESS</b>\n\n{hn} از بانکِ {tn} دزدید.\n\n💎 +{amount} ({pct}%)\n🏦 موجودیِ بانکِ تو: <b>{bank}</b>",
            'hack_partial'  => "🟡 <b>PARTIAL SUCCESS</b>\n\n{hn} یه مقدارِ کم از بانکِ {tn} برداشت.\n\n💎 +{amount} ({pct}%)\n🏦 موجودیِ بانکِ تو: <b>{bank}</b>",
            'hack_critfail' => "💥 <b>CRITICAL FAILURE</b>\n\nسیستمِ امنیتیِ بانکِ {tn} فعال شد و {hn} جریمه شد!\n\n💎 −{fine}\n🏦 موجودیِ بانکِ تو: <b>{bank}</b>",
            'hack_failed'   => "🔴 <b>FAILED</b>\n\n{hn} تلاش کرد بانکِ {tn} رو هک کنه، ولی شکست خورد.",

            'top_head' => "🏆 <b>برترین‌های بانک</b>\n",
            'top_row'  => "{rank}. {name} — 🏦 <b>{bank}</b> (🔥 {wins})",
            'top_none' => "هنوز کسی بانکی نساخته.",
        ],
    ];
}

function bkCfg() {
    $c = cfg()['bank'] ?? null;
    return is_array($c) ? array_replace_recursive(bkDefaults(), $c) : bkDefaults();
}

function bkSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!is_array($c['bank'] ?? null)) $c['bank'] = bkDefaults();
        $fn($c['bank']);
    });
}

function bkVal($path, $default = null) {
    $v = bkCfg();
    foreach (explode('.', $path) as $seg) {
        if (!is_array($v) || !array_key_exists($seg, $v)) return $default;
        $v = $v[$seg];
    }
    return $v;
}

function bkOn() { return !empty(bkVal('on')); }

function bkIsButtonKey($slug) {
    return in_array($slug, ['btn_protect', 'btn_send', 'btn_send_confirm', 'btn_back',
                            'btn_dep', 'btn_wd'], true);
}

function bkT($slug, $vars = []) {
    $t = (string)bkVal('texts.' . $slug, bkDefaults()['texts'][$slug] ?? $slug);
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}

function bkBtn($key, $vars, $data) {
    $b = ['callback_data' => $data];
    $color = (string)bkVal('btns.' . $key . '.color', '');
    if (function_exists('isStyle') && isStyle($color)) $b['style'] = $color;
    $b = function_exists('btnApplyLabel')
        ? btnApplyLabel($b, bkT($key, $vars), bkVal('icons.' . $key, ''))
        : ['text' => strip_tags((string)(bkT($key, $vars)))] + $b;
    return $b;
}

function bkNum($n) { return number_format((float)$n, 0, '.', ','); }

function bkUserTag($id, $name, $uname) {
    $label = trim((string)$name) !== '' ? (string)$name : ('#' . (int)$id);
    $u = trim((string)$uname);
    return h($label) . ($u !== '' ? ' (@' . h($u) . ')' : '');
}

function bkLeftStr($untilTs) {
    $left = (int)$untilTs - time();
    if ($left <= 0) return '—';
    $m = intdiv($left, 60); $s = $left % 60;
    return ($m > 0 ? $m . ' دقیقه و ' : '') . $s . ' ثانیه';
}


function bkUserDefault($uid) {
    return [
        'id' => (int)$uid, 'name' => '', 'username' => '',
        'protection_until' => 0, 'shield_until' => 0,
        'vault' => 0.0, 'vault_at' => 0,
        'bank_level' => 1, 'security_level' => 1,
        'successful_hacks' => 0, 'failed_hacks' => 0, 'win_streak' => 0,
        'total_stolen' => 0.0, 'total_lost' => 0.0,
        'hack_cooldown_until' => 0,
        'created_at' => time(), 'updated_at' => time(),
    ];
}

function bankDbPath() { return DATA_DIR . '/bank_users.sqlite'; }

function bankDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3')) return null;

    $path = bankDbPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fresh = !is_file($path);

    try {
        $db = new SQLite3($path);
    } catch (Throwable $e) {
        error_log('[bank] bank_users.sqlite باز نشد: ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS bank_users (id INTEGER PRIMARY KEY, data TEXT NOT NULL)');

    if ($fresh) bankImportFromJson($db);
    return $db;
}

function bankImportFromJson($db) {
    $old = dataPath('bank_users');
    if (!is_file($old)) return;
    $raw = @file_get_contents($old);
    $arr = $raw ? json_decode($raw, true) : null;

    if (is_array($arr) && $arr) {
        $db->exec('BEGIN');
        $stmt = $db->prepare('INSERT OR REPLACE INTO bank_users (id, data) VALUES (:id, :data)');
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

function bkUser($uid) {
    $db = bankDb();
    if (!$db) return null;
    $stmt = $db->prepare('SELECT data FROM bank_users WHERE id = :id');
    $stmt->bindValue(':id', (int)$uid, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $d = json_decode($row['data'], true);
    return is_array($d) ? $d : null;
}

function bkUserSet($uid, callable $fn) {
    $db = bankDb();
    if (!$db) return null;
    $id = (int)$uid;

    $db->exec('BEGIN IMMEDIATE');
    try {
        $stmt = $db->prepare('SELECT data FROM bank_users WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $u = $row ? json_decode($row['data'], true) : null;
        if (!is_array($u)) $u = bkUserDefault($id);

        $result = $fn($u);
        $u['updated_at'] = time();

        $up = $db->prepare('INSERT OR REPLACE INTO bank_users (id, data) VALUES (:id, :data)');
        $up->bindValue(':id', $id, SQLITE3_INTEGER);
        $up->bindValue(':data', json_encode($u, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        $up->execute();
        $db->exec('COMMIT');
        return $result;
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        error_log('[bank] bkUserSet خطا: ' . $e->getMessage());
        return null;
    }
}

function bkLevelFromStolen($stolen) {
    $step = max(1, (int)bkVal('level_step', 500000));
    return min(1000, (int)floor(max(0, (float)$stolen) / $step) + 1);
}

function bkTop($n = 10) {
    $n = max(1, (int)$n);
    $top = []; $min = -INF;
    $db = bankDb();
    $rows = [];
    if ($db) {
        $res = $db->query('SELECT data FROM bank_users');
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $u = json_decode($row['data'], true);
            if (is_array($u)) $rows[] = $u;
        }
    }
    foreach ($rows as $u) {
        $b = gmPoints((int)($u['id'] ?? 0));
        if (count($top) >= $n && $b <= $min) continue;
        $u['bank_balance'] = $b;
        $i = count($top);
        while ($i > 0 && (float)($top[$i - 1]['bank_balance'] ?? 0) < $b) $i--;
        array_splice($top, $i, 0, [$u]);
        if (count($top) > $n) array_pop($top);
        $min = (float)($top[count($top) - 1]['bank_balance'] ?? 0);
    }
    return $top;
}


function bkPendKey($uid, $chat) { return $uid . '_' . $chat; }

function bkPendSet($uid, $chat, $kind, $msgId, $data = []) {
    mutate('bank_states', function (&$s) use ($uid, $chat, $kind, $msgId, $data) {
        $s[bkPendKey($uid, $chat)] = ['kind' => $kind, 'msg' => (int)$msgId, 'at' => time(), 'data' => $data];
    });
}

function bkPendGet($uid, $chat) {
    $s = load('bank_states');
    $e = $s[bkPendKey($uid, $chat)] ?? null;
    if (count($s) > 30 && random_int(1, 50) === 1) bkPendSweep(100);
    if (!$e || time() - (int)($e['at'] ?? 0) > 300) return null;
    return $e;
}

function bkPendClear($uid, $chat) {
    mutate('bank_states', function (&$s) use ($uid, $chat) { unset($s[bkPendKey($uid, $chat)]); });
}

function bkPendSweep($limit = 200) {
    $now = time();
    $removed = 0;
    mutate('bank_states', function (&$s) use ($now, $limit, &$removed) {
        foreach (array_keys($s) as $k) {
            if ($removed >= $limit) break;
            if ($now - (int)($s[$k]['at'] ?? 0) > 300) { unset($s[$k]); $removed++; }
        }
    });
    return $removed;
}

function bkLooksLikeAmount($raw) {
    $n = trim(str_replace([',', '٬', ' '], '', norm_fa_digits(trim((string)$raw))));
    return $n !== '' && preg_match('/^\d+(\.\d+)?$/', $n) === 1;
}

function bkLooksLikeTarget($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return false;
    if ($raw[0] === '@') $raw = substr($raw, 1);
    if (preg_match('/^[A-Za-z][A-Za-z0-9_]{2,31}$/', $raw) === 1) return true;
    $digits = trim(norm_fa_digits($raw));
    return $digits !== '' && preg_match('/^\d+$/', $digits) === 1;
}

function bkResolveTarget($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return null;
    if ($raw[0] === '@') $raw = substr($raw, 1);

    $digits = trim(norm_fa_digits($raw));
    if ($digits !== '' && preg_match('/^\d+$/', $digits) === 1) return (int)$digits;

    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{2,31}$/', $raw) || !function_exists('allUsers')) return null;
    $needle = mb_strtolower($raw);
    foreach (allUsers() as $id => $u) {
        if (mb_strtolower((string)($u['username'] ?? '')) === $needle) return (int)$id;
    }
    return null;
}


function bkRollPercent($min, $max) {
    $min = (float)$min; $max = (float)$max;
    if ($max < $min) [$min, $max] = [$max, $min];
    $lo = (int)round($min * 10); $hi = (int)round($max * 10);
    if ($hi <= $lo) return round($min, 1);
    return random_int($lo, $hi) / 10.0;
}

function bkRisk($risk) {
    $all = (array)bkVal('risk', bkDefaults()['risk']);
    $d   = $all['normal'] ?? ['success_mul' => 1, 'amount_mul' => 1, 'critfail_mul' => 1, 'cooldown_mul' => 1];
    return (array)($all[(string)$risk] ?? $d) + $d;
}
function bkRiskLabel($risk) { return bkT('risk_' . (string)$risk); }
function bkHackRoll(array $hacker, array $target, $risk = 'normal') {
    $r  = bkVal('rng', bkDefaults()['rng']);
    $rk = bkRisk($risk);

    $hackerLevel = max(1, (int)($hacker['bank_level'] ?? 1));
    $targetSec   = max(1, (int)($target['security_level'] ?? 1));
    $streak      = max(0, (int)($hacker['win_streak'] ?? 0));

    $edge = ($hackerLevel - $targetSec) * 1.8;
    $edge = max(-20.0, min(20.0, $edge));

    $streakPenalty = -min(18.0, $streak * 6.0);

    $base = (float)$r['base_success'] + $edge + $streakPenalty;

    $jitter = max(1.0, (float)$r['jitter_pct']);
    $base  += random_int((int)round(-$jitter * 10), (int)round($jitter * 10)) / 10.0;

    $base   = $base * (float)$rk['success_mul'];
    $chance = max((float)$r['success_floor'], min((float)$r['success_ceil'], $base));

    $ticket = random_int(1, 100000);

    $amtMul = max(0.05, (float)$rk['amount_mul']);
    $pc = function ($min, $max) use ($amtMul) {
        return round(min(95.0, bkRollPercent($min, $max) * $amtMul), 2);
    };

    $jackpotSlice  = max(0, (int)round((float)$r['jackpot_pct']  * 1000 * $amtMul));
    $critFailSlice = max(0, (int)round((float)$r['critfail_pct'] * 1000 * (float)$rk['critfail_mul']));
    $perfectSlice  = max(0, (int)round((float)$r['perfect_pct']  * 1000));

    if ($ticket <= $jackpotSlice) {
        return ['tier' => 'jackpot', 'pct' => $pc($r['jackpot_min'], $r['jackpot_max'])];
    }
    $ticket -= $jackpotSlice;

    if ($ticket <= $critFailSlice) {
        return ['tier' => 'critfail', 'fine_pct' => round(bkRollPercent($r['critfail_min'], $r['critfail_max']) * (float)$rk['critfail_mul'], 2)];
    }
    $ticket -= $critFailSlice;

    if ($ticket <= $perfectSlice) {
        return ['tier' => 'perfect', 'pct' => $pc($r['perfect_min'], $r['perfect_max'])];
    }
    $ticket -= $perfectSlice;

    $remaining    = max(1, 100000 - $jackpotSlice - $critFailSlice - $perfectSlice);
    $successShare = max(0.0, min(1.0, $chance / 100.0));
    $successSlice = (int)round($remaining * $successShare * (1 - (float)$r['partial_share']));
    $partialSlice = (int)round($remaining * $successShare * (float)$r['partial_share']);

    if ($ticket <= $successSlice) {
        return ['tier' => 'success', 'pct' => $pc($r['success_min'], $r['success_max'])];
    }
    $ticket -= $successSlice;

    if ($ticket <= $partialSlice) {
        return ['tier' => 'partial', 'pct' => $pc($r['partial_min'], $r['partial_max'])];
    }

    return ['tier' => 'fail'];
}


function bkCardText($uid, $name) {
    $u = bkUser($uid) ?? bkUserDefault($uid);
    $now = time();
    $protUntil = max((int)($u['protection_until'] ?? 0), (int)($u['shield_until'] ?? 0));
    $active    = $protUntil > $now;

    $wallet = gmPoints($uid);
    $vault  = bkVaultOf($uid);
    return bkT('card', [
        'name'         => h($name),
        'wallet'       => bkNum($wallet),
        'vault'        => bkNum($vault),
        'rate'         => rtrim(rtrim(number_format(bkRate($vault), 2, '.', ''), '0'), '.'),
        'bank'         => bkNum($vault),
        'sec_status'   => $active ? 'ACTIVE' : 'INACTIVE',
        'protect_left' => $active ? bkLeftStr($protUntil) : '—',
        'level'        => (int)($u['bank_level'] ?? 1),
        'wins'         => bkNum($u['successful_hacks'] ?? 0),
        'stolen'       => bkNum($u['total_stolen'] ?? 0),
    ]);
}

function bkKb($uid) {
    return inlineKb([
        [bkBtn('btn_protect', [], 'bk_protect_' . (int)$uid)],
        [bkBtn('btn_dep', [], 'bk_dep_' . (int)$uid), bkBtn('btn_wd', [], 'bk_wd_' . (int)$uid)],
        [bkBtn('btn_send', [], 'bk_send_' . (int)$uid)],
    ]);
}

function bkEditCard($chatId, $msgId, $text, $kb = null) {
    $msgId = (int)$msgId;
    if ($msgId <= 0) { sendMsg(BOT_TOKEN, $chatId, $text, $kb); return; }

    $data = ['chat_id' => $chatId, 'message_id' => $msgId,
             'caption' => mb_substr((string)$text, 0, 1024), 'parse_mode' => 'HTML'];
    if ($kb) $data['reply_markup'] = function_exists('kbJson') ? kbJson($kb) : json_encode($kb);
    $r = tg(BOT_TOKEN, 'editMessageCaption', $data);
    if (!empty($r['ok'])) return;
    if (function_exists('isNotModified') && isNotModified($r)) return;

    editMsg(BOT_TOKEN, $chatId, $msgId, $text, $kb);
}

function bkBackKb($uid) {
    return inlineKb([[bkBtn('btn_back', [], 'bk_back_' . (int)$uid)]]);
}

function bkShow($uid, $chatId, $name, $editMsgId = null, $replyToMsgId = null) {
    $text = bkCardText($uid, $name);
    if ($editMsgId) { editMsg(BOT_TOKEN, $chatId, $editMsgId, $text, bkKb($uid)); return; }

    $extra = $replyToMsgId ? ['reply_to_message_id' => (int)$replyToMsgId] : [];

    if (!empty(bkVal('card_image', 1)) && function_exists('bcSend')) {
        $u  = bkUser($uid);
        $ok = bcSend($chatId, $text, [
            'uid'      => (int)$uid,
            'name'     => $name,
            'username' => (string)($u['username'] ?? ''),
            'card_no'  => (string)($u['card_no'] ?? ''),
            'locked'   => bkVaultOf($uid),
            'level'    => (int)($u['bank_level'] ?? 1),
            'rank'     => 0,
            'footer'   => (string)bkVal('card_footer', 'کارتِ اختصاصیِ شما'),
        ], bkKb($uid), $extra);
        if ($ok) return;
    }

    sendMsg(BOT_TOKEN, $chatId, $text, bkKb($uid), $extra);
}

function bkAskEdit($chatId, $pendMsgId, $text, array $extraRows = [], $uid = 0) {
    $rows = $extraRows;
    $rows[] = [bkBtn('btn_back', [], 'bk_cancelflow_' . (int)$uid)];
    $kb = inlineKb($rows);
    if ($pendMsgId) { bkEditCard($chatId, (int)$pendMsgId, $text, $kb); return; }
    sendMsg(BOT_TOKEN, $chatId, $text, $kb);
}

function bkSendDiamond($fromUid, $toUid, $fromName, $fromUname, $amount) {
    $amount = (float)$amount;
    if ($amount <= 0 || floor($amount) != $amount) return [false, bkT('ask_bad_num')];
    if ((int)$toUid === (int)$fromUid) return [false, bkT('send_self')];

    $wallet = gmPoints($fromUid);
    if ($amount > $wallet + 1e-9) return [false, bkT('send_low_wallet', ['wallet' => bkNum($wallet)])];

    if (!gmAdd($fromUid, -$amount, $fromName, $fromUname)) {
        return [false, bkT('send_low_wallet', ['wallet' => bkNum(gmPoints($fromUid))])];
    }
    if (!gmAdd($toUid, $amount)) {
        gmAdd($fromUid, $amount, $fromName, $fromUname);
        return [false, bkT('ask_bad_num')];
    }

    $toUser = function_exists('getUser') ? getUser($toUid) : null;

    return [true, bkT('send_ok', [
        'amount'   => bkNum($amount),
        'to'       => $toUid,
        'from_tag' => bkUserTag($fromUid, $fromName, $fromUname),
        'to_tag'   => bkUserTag($toUid, $toUser['first_name'] ?? '', $toUser['username'] ?? ''),
        'wallet'   => bkNum(gmPoints($fromUid)),
    ])];
}

function bkVaultMove($uid, $amount, $dir, $name = '', $uname = '') {
    $amount = (float)$amount;
    if ($amount <= 0 || floor($amount) != $amount) return [false, bkT('ask_bad_num')];

    $vault = bkVaultOf($uid);
    $wallet = gmPoints($uid);

    if ($dir > 0) {
        if ($amount > $wallet + 1e-9)
            return [false, bkT('dep_low', ['wallet' => bkNum($wallet)])];
        if (!gmAdd($uid, -$amount, $name, $uname))
            return [false, bkT('dep_low', ['wallet' => bkNum(gmPoints($uid))])];

        $done = false;
        bkUserSet($uid, function (&$x) use ($amount, &$done) {
            bkSettleInterest($x);
            $x['vault']    = max(0.0, (float)($x['vault'] ?? 0)) + $amount;
            if ((int)($x['vault_at'] ?? 0) <= 0) $x['vault_at'] = time();
            $done = true;
        });
        if (!$done) { gmAdd($uid, $amount, $name, $uname); return [false, bkT('ask_bad_num')]; }

        $nv = bkVaultOf($uid);
        return [true, bkT('dep_ok', [
            'amount' => bkNum($amount),
            'wallet' => bkNum(gmPoints($uid)),
            'vault'  => bkNum($nv),
            'rate'   => rtrim(rtrim(number_format(bkRate($nv), 2, '.', ''), '0'), '.'),
        ])];
    }

    if ($amount > $vault + 1e-9)
        return [false, bkT('wd_low', ['vault' => bkNum($vault)])];

    $taken = false;
    bkUserSet($uid, function (&$x) use ($amount, &$taken) {
        bkSettleInterest($x);
        $cur = max(0.0, (float)($x['vault'] ?? 0));
        if ($cur + 1e-9 < $amount) return;
        $x['vault']    = $cur - $amount;
        $x['vault_at'] = time();
        $taken = true;
    });
    if (!$taken) return [false, bkT('wd_low', ['vault' => bkNum(bkVaultOf($uid))])];

    if (!gmAdd($uid, $amount, $name, $uname)) {
        bkUserSet($uid, function (&$x) use ($amount) {
            $x['vault'] = max(0.0, (float)($x['vault'] ?? 0)) + $amount;
        });
        return [false, bkT('ask_bad_num')];
    }

    return [true, bkT('wd_ok', [
        'amount' => bkNum($amount),
        'wallet' => bkNum(gmPoints($uid)),
        'vault'  => bkNum(bkVaultOf($uid)),
    ])];
}

function bkRate($amount) {
    $tiers = (array)bkVal('interest.tiers', bkDefaults()['interest']['tiers']);
    $rate  = 0.0;
    foreach ($tiers as $t) {
        $floor = (float)($t[0] ?? 0);
        if ((float)$amount + 1e-9 >= $floor) $rate = (float)($t[1] ?? 0);
    }
    return max(0.0, $rate);
}

function bkSettleInterest(array &$u) {
    if (empty(bkVal('interest.on', true))) return 0.0;

    $now   = time();
    $vault = max(0.0, (float)($u['vault'] ?? 0));
    $last  = (int)($u['vault_at'] ?? 0);
    if ($last <= 0) { $u['vault_at'] = $now; return 0.0; }
    if ($vault <= 0) { $u['vault_at'] = $now; return 0.0; }

    $day  = max(60, (int)bkVal('interest.day_secs', 86400));
    $dt   = max(0, $now - $last);
    $dt   = min($dt, max(1, (int)bkVal('interest.max_days', 30)) * $day);
    if ($dt <= 0) return 0.0;

    $rate = bkRate($vault) / 100.0;
    $gain = floor($vault * $rate * ($dt / $day));
    if ($gain <= 0) return 0.0;

    $u['vault']    = $vault + $gain;
    $u['vault_at'] = $now;
    return (float)$gain;
}

function bkVaultOf($uid) {
    $out = 0.0;
    bkUserSet($uid, function (&$u) use (&$out) {
        bkSettleInterest($u);
        $out = max(0.0, (float)($u['vault'] ?? 0));
    });
    return $out;
}

function bkProtect($uid) {
    $secs = max(60, (int)bkVal('manual_protect', 900));
    $now  = time();
    $left = 0;
    bkUserSet($uid, function (&$u) use ($secs, $now, &$left) {
        $manual = (int)($u['protection_until'] ?? 0);
        $until  = $now + $secs;
        if ($manual >= $until) { $left = $manual - $now; return; }
        $u['protection_until'] = $until;
    });
    if ($left > 0) {
        $m = intdiv($left, 60); $s = $left % 60;
        return [false, bkT('protect_still', ['left' => ($m > 0 ? $m . ' دقیقه و ' : '') . $s . ' ثانیه'])];
    }
    return [true, bkT('protected', ['mins' => (int)round($secs / 60)])];
}


function bkHack($hackerId, $hackerName, $hackerUname, $targetId, $risk = 'normal') {
    $now = time();
    $out = ['err' => null];
    $db = bankDb();
    if (!$db) { $out['err'] = 'empty'; return $out; }

    $hk = (int)$hackerId; $tg = (int)$targetId;

    $fetch = function ($id) use ($db) {
        $stmt = $db->prepare('SELECT data FROM bank_users WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $u = $row ? json_decode($row['data'], true) : null;
        return is_array($u) ? $u : bkUserDefault($id);
    };
    $save = function ($id, $u) use ($db) {
        $stmt = $db->prepare('INSERT OR REPLACE INTO bank_users (id, data) VALUES (:id, :data)');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':data', json_encode($u, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        $stmt->execute();
    };

    $db->exec('BEGIN IMMEDIATE');
    try {
        $hacker = $fetch($hk);
        $target = $hk === $tg ? $hacker : $fetch($tg);

        if ($hackerName !== '')  $hacker['name'] = $hackerName;
        if ($hackerUname !== '') $hacker['username'] = $hackerUname;

        if ((float)($hacker['hack_cooldown_until'] ?? 0) > $now) {
            $out['err'] = 'cooldown'; $out['left'] = (int)$hacker['hack_cooldown_until'] - $now;
        } elseif (max((int)($target['protection_until'] ?? 0), (int)($target['shield_until'] ?? 0)) > $now) {
            $shield = max((int)($target['protection_until'] ?? 0), (int)($target['shield_until'] ?? 0));
            $out['err'] = 'protected'; $out['left'] = $shield - $now;
        } elseif (gmPoints($tg) <= 0) {
            $out['err'] = 'empty';
        } else {
            $bal = gmPoints($tg);
            $roll = bkHackRoll($hacker, $target, $risk);
            $cooldown = max(60, (int)round(bkVal('hack_cooldown', 1200) * (float)bkRisk($risk)['cooldown_mul']));
            $cooldown = max(60, $cooldown);
            $shieldSecs = max(0, (int)bkVal('shield_after', 300));
            $hacker['hack_cooldown_until'] = $now + $cooldown;
            $target['shield_until'] = $now + $shieldSecs;

            $out['tier'] = $roll['tier'];

            if (in_array($roll['tier'], ['jackpot', 'perfect', 'success', 'partial'], true)) {
                $amt = min($bal, floor($bal * $roll['pct'] / 100));
                $amt = max(0.0, $amt);
                if ($amt <= 0) {
                    $out['tier'] = 'fail';
                    $hacker['failed_hacks'] = (int)($hacker['failed_hacks'] ?? 0) + 1;
                    $hacker['win_streak']   = 0;
                } elseif (gmAdd($tg, -$amt)) {
                    gmAdd($hk, $amt, $hackerName, $hackerUname);
                    $hacker['successful_hacks'] = (int)($hacker['successful_hacks'] ?? 0) + 1;
                    $hacker['win_streak']       = (int)($hacker['win_streak'] ?? 0) + 1;
                    $hacker['total_stolen']     = (float)($hacker['total_stolen'] ?? 0) + $amt;
                    $target['total_lost']       = (float)($target['total_lost'] ?? 0) + $amt;
                    $hacker['bank_level']       = bkLevelFromStolen($hacker['total_stolen']);
                    $out['amount'] = $amt; $out['pct'] = $roll['pct'];
                    $out['hackerBank'] = gmPoints($hk);
                } else {
                    $out['tier'] = 'fail';
                    $hacker['failed_hacks'] = (int)($hacker['failed_hacks'] ?? 0) + 1;
                    $hacker['win_streak']   = 0;
                }
            } elseif ($roll['tier'] === 'critfail') {
                $ownBal = gmPoints($hk);
                $fine = min($ownBal, floor($ownBal * $roll['fine_pct'] / 100));
                $deducted = $fine > 0 && gmAdd($hk, -$fine);
                $hacker['failed_hacks'] = (int)($hacker['failed_hacks'] ?? 0) + 1;
                $hacker['win_streak']   = 0;
                $out['fine'] = $deducted ? $fine : 0; $out['hackerBank'] = gmPoints($hk);
            } else {
                $hacker['failed_hacks'] = (int)($hacker['failed_hacks'] ?? 0) + 1;
                $hacker['win_streak']   = 0;
            }
        }

        $hacker['updated_at'] = time();
        $save($hk, $hacker);
        if ($hk !== $tg) { $target['updated_at'] = time(); $save($tg, $target); }
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        error_log('[bank] bkHack خطا: ' . $e->getMessage());
        return ['err' => 'empty'];
    }

    return $out;
}

function bkRiskKb($hackerId, $targetId) {
    $suf = '_' . (int)$hackerId . '_' . (int)$targetId;
    return inlineKb([
        [bkBtn('btn_risk_high',   [], 'bk_h_high'   . $suf)],
        [bkBtn('btn_risk_normal', [], 'bk_h_normal' . $suf),
         bkBtn('btn_risk_low',    [], 'bk_h_low'    . $suf)],
    ]);
}

function bkHackCmd($uid, $chatId, $name, $uname, $replyTo, $msg) {
    $extra = $replyTo ? ['reply_to_message_id' => $replyTo] : [];
    $to = $msg['reply_to_message']['from'] ?? null;

    if (!$to || !empty($to['is_bot'])) {
        $firstWord = trim(explode(',', (string)bkVal('word_hack', 'سرقت الماس'))[0]) ?: 'سرقت الماس';
        sendMsg(BOT_TOKEN, $chatId, bkT('hack_how', ['word' => $firstWord]), null, $extra);
        return;
    }
    $targetId = (int)$to['id'];
    if ($targetId === (int)$uid) { sendMsg(BOT_TOKEN, $chatId, bkT('hack_self'), null, $extra); return; }

    $hu = function_exists('getUser') ? getUser($uid) : null;
    if ($hu && !empty($hu['banned'])) return;
    $tuRaw = function_exists('getUser') ? getUser($targetId) : null;
    if ($tuRaw && !empty($tuRaw['banned'])) {
        sendMsg(BOT_TOKEN, $chatId, bkT('hack_no_target'), null, $extra); return;
    }

    $bal = function_exists('gmPoints') ? gmPoints($targetId) : 0;
    if ($bal <= 0) { sendMsg(BOT_TOKEN, $chatId, bkT('hack_empty'), null, $extra); return; }

    sendMsg(BOT_TOKEN, $chatId, bkT('hack_menu', [
        'hn'  => h($name),
        'tn'  => h((string)($to['first_name'] ?? ($to['username'] ?? 'کاربر'))),
        'bal' => bkNum($bal),
    ]), bkRiskKb($uid, $targetId), $extra);
}

function bkRunHack($uid, $chatId, $msgId, $name, $uname, $targetId, $risk) {
    $res = bkHack($uid, $name, $uname, $targetId, $risk);

    $tn = 'کاربر';
    $tu = function_exists('getUser') ? getUser($targetId) : null;
    if ($tu) $tn = (string)($tu['first_name'] ?? ($tu['username'] ?? 'کاربر'));

    if ($res['err'] === 'cooldown') {
        editMsg(BOT_TOKEN, $chatId, $msgId, bkT('hack_cooldown', ['left' => bkLeftStr(time() + $res['left'])]), null);
        return;
    }
    if ($res['err'] === 'protected') {
        editMsg(BOT_TOKEN, $chatId, $msgId, bkT('hack_protected', ['left' => bkLeftStr(time() + $res['left'])]), null);
        return;
    }
    if ($res['err'] === 'empty') {
        editMsg(BOT_TOKEN, $chatId, $msgId, bkT('hack_empty'), null);
        return;
    }

    $vars = ['hn' => h($name), 'tn' => h($tn)];
    switch ($res['tier']) {
        case 'jackpot':
            $text = bkT('hack_jackpot', $vars + ['amount' => bkNum($res['amount']), 'pct' => $res['pct'], 'bank' => bkNum($res['hackerBank'])]);
            break;
        case 'perfect':
            $text = bkT('hack_perfect', $vars + ['amount' => bkNum($res['amount']), 'pct' => $res['pct'], 'bank' => bkNum($res['hackerBank'])]);
            break;
        case 'success':
            $text = bkT('hack_success', $vars + ['amount' => bkNum($res['amount']), 'pct' => $res['pct'], 'bank' => bkNum($res['hackerBank'])]);
            break;
        case 'partial':
            $text = bkT('hack_partial', $vars + ['amount' => bkNum($res['amount']), 'pct' => $res['pct'], 'bank' => bkNum($res['hackerBank'])]);
            break;
        case 'critfail':
            $text = bkT('hack_critfail', $vars + ['fine' => bkNum($res['fine']), 'bank' => bkNum($res['hackerBank'])]);
            break;
        default:
            $text = bkT('hack_failed', $vars);
    }
    $text .= bkT('risk_line', ['risk' => bkRiskLabel($risk)]);
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, null);
}


function bkTopText($n = null) {
    $rows = bkTop($n ?? (int)bkVal('top_n', 10));
    if (!$rows) return bkT('top_none');
    $out = bkT('top_head');
    $i = 1;
    foreach ($rows as $u) {
        $nm = trim((string)($u['name'] ?? '')) !== '' ? (string)$u['name'] : ((string)($u['id'] ?? ''));
        $out .= "\n" . bkT('top_row', [
            'rank' => $i, 'name' => h($nm), 'bank' => bkNum($u['bank_balance'] ?? 0), 'wins' => (int)($u['successful_hacks'] ?? 0),
        ]);
        $i++;
    }
    return $out;
}


function bkHandlePending($raw, $uid, $chatId, $name, $uname, $pend, $replyToId = null) {
    $kind = $pend['kind'] ?? '';
    $pendMsg = (int)($pend['msg'] ?? 0);

    if ($kind === 'send_id') {
        if ($pendMsg > 0 && (int)$replyToId !== $pendMsg) {
            sendMsg(BOT_TOKEN, $chatId, bkT('send_need_reply'));
            return true;
        }
        $toId = bkResolveTarget($raw);
        if ($toId === null) {
            bkAskEdit($chatId, $pendMsg, bkT('ask_send_badid') . "\n\n" . bkT('ask_send_id'), [], $uid);
            return true;
        }
        if ($toId === (int)$uid) {
            bkAskEdit($chatId, $pendMsg, bkT('send_self') . "\n\n" . bkT('ask_send_id'), [], $uid);
            return true;
        }
        if (!function_exists('getUser') || !getUser($toId)) {
            bkAskEdit($chatId, $pendMsg, bkT('send_no_target') . "\n\n" . bkT('ask_send_id'), [], $uid);
            return true;
        }
        $amount = (float)($pend['data']['amount'] ?? 0);
        bkPendSet($uid, $chatId, 'send_confirm', $pendMsg, ['amount' => $amount, 'to' => $toId]);
        $toUser = function_exists('getUser') ? getUser($toId) : null;
        $toTag  = bkUserTag($toId, $toUser['first_name'] ?? '', $toUser['username'] ?? '');
        bkAskEdit($chatId, $pendMsg, bkT('send_confirm', ['amount' => bkNum($amount), 'to' => $toId, 'to_tag' => $toTag]),
            [[bkBtn('btn_send_confirm', [], 'bk_sendok_' . (int)$uid)]], $uid);
        return true;
    }

    if ($kind === 'send_amt') {
        $n = (float)str_replace([',', '٬', ' '], '', norm_fa_digits($raw));
        if ($n <= 0 || floor($n) != $n) { bkAskEdit($chatId, $pendMsg, bkT('ask_bad_num'), [], $uid); return true; }
        $wallet = gmPoints($uid);
        if ($n > $wallet + 1e-9) {
            bkPendClear($uid, $chatId);
            sendMsg(BOT_TOKEN, $chatId, bkT('send_low_wallet', ['wallet' => bkNum($wallet)]));
            if ($pendMsg) bkShow($uid, $chatId, $name, $pendMsg);
            return true;
        }
        bkPendSet($uid, $chatId, 'send_id', $pendMsg, ['amount' => $n]);
        bkAskEdit($chatId, $pendMsg, bkT('ask_send_id'), [], $uid);
        return true;
    }

    if ($kind === 'dep_amt' || $kind === 'wd_amt') {
        $dir  = $kind === 'dep_amt' ? 1 : -1;
        $word = mb_strtolower(trim($raw));
        if (in_array($word, ['همه', 'همش', 'all', 'کل'], true)) {
            $n = $dir > 0 ? floor(gmPoints($uid)) : floor(bkVaultOf($uid));
        } else {
            $n = (float)str_replace([',', '٬', ' '], '', norm_fa_digits($raw));
        }
        if ($n <= 0 || floor($n) != $n) { bkAskEdit($chatId, $pendMsg, bkT('ask_bad_num'), [], $uid); return true; }

        [$ok, $t] = bkVaultMove($uid, $n, $dir, $name, $uname);
        if (!$ok) { bkAskEdit($chatId, $pendMsg, $t, [], $uid); return true; }

        bkPendClear($uid, $chatId);
        bkEditCard($chatId, $pendMsg, $t, bkBackKb($uid));
        return true;
    }

    bkPendClear($uid, $chatId);
    if ($pendMsg) bkShow($uid, $chatId, $name, $pendMsg);
    return true;
}

function bkHandleText($text, $uid, $chatId, $name, $uname, $replyTo, $isPrivate, $msg = null) {
    if (!bkOn()) return false;
    if (!empty(bkVal('group_only', 1)) && $isPrivate) return false;

    $raw = trim((string)$text);
    if ($raw === '') return false;

    $pend = bkPendGet($uid, $chatId);
    if ($pend) {
        $kind = $pend['kind'] ?? '';
        if ($kind === 'send_id') {
            $looksLikeAnswer = bkLooksLikeTarget($raw);
        } elseif ($kind === 'send_amt' || $kind === 'dep_amt' || $kind === 'wd_amt') {
            $looksLikeAnswer = bkLooksLikeAmount($raw)
                || in_array(mb_strtolower(trim($raw)), ['همه', 'همش', 'all', 'کل'], true);
        } else {
            $looksLikeAnswer = false;
        }
        $replyToId = (int)($msg['reply_to_message']['message_id'] ?? 0);
        if ($looksLikeAnswer) return bkHandlePending($raw, $uid, $chatId, $name, $uname, $pend, $replyToId);
    }

    if (mb_strtolower($raw) === mb_strtolower('برترین‌های بانک')) { sendMsg(BOT_TOKEN, $chatId, bkTopText()); return true; }

    $isBank = false;
    foreach (explode(',', (string)bkVal('word_bank', 'بانک')) as $w) {
        $w = trim($w);
        if ($w !== '' && mb_strtolower($raw) === mb_strtolower($w)) { $isBank = true; break; }
    }
    if ($isBank) { bkShow($uid, $chatId, $name, null, $msg['message_id'] ?? null); return true; }

    $isHack = false;
    foreach (explode(',', (string)bkVal('word_hack', 'سرقت الماس')) as $w) {
        $w = trim($w);
        if ($w !== '' && mb_strtolower($raw) === mb_strtolower($w)) { $isHack = true; break; }
    }
    if ($isHack) { bkHackCmd($uid, $chatId, $name, $uname, $replyTo, $msg ?? []); return true; }

    return false;
}


function bkCallback($data, $uid, $chatId, $msgId, $cbId, $from = []) {
    if ($data === 'bk_nop') { answerCb(BOT_TOKEN, $cbId); return true; }

    if (preg_match('/^bk_h_(high|normal|low)_(\d+)_(\d+)$/', (string)$data, $m)) {
        [$_, $risk, $owner, $target] = $m;
        if (!bkOn()) { answerCb(BOT_TOKEN, $cbId); return true; }
        if ((int)$owner !== (int)$uid) { answerCb(BOT_TOKEN, $cbId, bkT('hack_not_yours'), true); return true; }
        answerCb(BOT_TOKEN, $cbId, '🎯');
        bkRunHack((int)$uid, $chatId, (int)$msgId,
            (string)($from['first_name'] ?? ''), (string)($from['username'] ?? ''),
            (int)$target, $risk);
        return true;
    }

    if (!preg_match('/^bk_(protect|send|sendok|cancelflow|back|dep|wd)_(\d+)$/', (string)$data, $m)) return false;
    $action  = $m[1];
    $ownerId = (int)$m[2];
    if (!bkOn()) { answerCb(BOT_TOKEN, $cbId); return true; }

    if ($ownerId !== (int)$uid) {
        answerCb(BOT_TOKEN, $cbId, bkT('not_your_card'), true);
        return true;
    }

    $name  = (string)($from['first_name'] ?? '');
    $uname = (string)($from['username'] ?? '');

    if ($action === 'protect') {
        [$ok, $t] = bkProtect($uid);
        answerCb(BOT_TOKEN, $cbId, $ok ? '🛡' : '', !$ok);
        bkEditCard($chatId, $msgId, $t, bkBackKb($uid));
        return true;
    }
    if ($action === 'back') {
        answerCb(BOT_TOKEN, $cbId);
        bkPendClear($uid, $chatId);
        bkEditCard($chatId, $msgId, bkCardText($uid, $name), bkKb($uid));
        return true;
    }
    if ($action === 'send') {
        answerCb(BOT_TOKEN, $cbId);
        bkPendSet($uid, $chatId, 'send_amt', $msgId);
        bkAskEdit($chatId, $msgId, bkT('ask_send_amt'), [], $uid);
        return true;
    }
    if ($action === 'dep' || $action === 'wd') {
        answerCb(BOT_TOKEN, $cbId);
        $vars = ['wallet' => bkNum(gmPoints($uid)), 'vault' => bkNum(bkVaultOf($uid))];
        bkPendSet($uid, $chatId, $action === 'dep' ? 'dep_amt' : 'wd_amt', $msgId);
        bkAskEdit($chatId, $msgId, bkT($action === 'dep' ? 'dep_ask' : 'wd_ask', $vars), [], $uid);
        return true;
    }
    if ($action === 'cancelflow') {
        answerCb(BOT_TOKEN, $cbId);
        bkPendClear($uid, $chatId);
        bkEditCard($chatId, $msgId, bkCardText($uid, $name), bkKb($uid));
        return true;
    }
    if ($action === 'sendok') {
        $pend = bkPendGet($uid, $chatId);
        if (!$pend || $pend['kind'] !== 'send_confirm') {
            answerCb(BOT_TOKEN, $cbId, bkT('ask_expired'), true);
            return true;
        }
        bkPendClear($uid, $chatId);
        $amount = (float)($pend['data']['amount'] ?? 0);
        $toId   = (int)($pend['data']['to'] ?? 0);
        [$ok, $t] = bkSendDiamond($uid, $toId, $name, $uname, $amount);
        answerCb(BOT_TOKEN, $cbId, $ok ? '🎁' : '', !$ok);
        sendMsg(BOT_TOKEN, $chatId, $t);
        if (!empty($pend['msg'])) bkShow($uid, $chatId, $name, (int)$pend['msg']);
        return true;
    }
    return false;
}


function bkLabels() {
    return [
        'card' => 'کارتِ بانک', 'protected' => 'حفاظتِ دستی — موفق', 'protect_still' => 'حفاظتِ دستی — از قبل فعال',
        'ask_bad_num' => 'عددِ نامعتبر', 'ask_expired' => 'درخواستِ منقضی', 'not_your_card' => 'کارتِ کسِ دیگری — رد شد',
        'hack_how' => 'هک — راهنما', 'hack_self' => 'هک — خودت', 'hack_no_target' => 'هک — هدف نیست',
        'hack_protected' => 'هک — هدف محافظت‌شده', 'hack_empty' => 'هک — بانکِ خالی', 'hack_cooldown' => 'هک — کول‌داون',
        'hack_jackpot' => 'هک — JACKPOT', 'hack_perfect' => 'هک — PERFECT', 'hack_success' => 'هک — SUCCESS',
        'hack_partial' => 'هک — PARTIAL', 'hack_critfail' => 'هک — CRITICAL FAIL', 'hack_failed' => 'هک — FAILED',
        'top_head' => 'برترین‌ها — سر', 'top_row' => 'برترین‌ها — ردیف', 'top_none' => 'برترین‌ها — خالی',
        'ask_send_amt' => 'ارسال — مقدار', 'ask_send_id' => 'ارسال — آیدی', 'ask_send_badid' => 'ارسال — آیدیِ بد',
        'send_need_reply' => 'ارسال — بدونِ ریپلای',
        'send_self' => 'ارسال — به خودت', 'send_no_target' => 'ارسال — گیرنده ناشناس',
        'send_low_wallet' => 'ارسال — کیف‌پول کم', 'send_confirm' => 'ارسال — تاییدیه', 'send_ok' => 'ارسال — موفق',
        'btn_protect' => 'دکمه: حفاظت',
        'btn_dep' => 'دکمه: انتقال به بانک', 'btn_wd' => 'دکمه: برداشت از بانک',
        'dep_ask' => 'انتقال به بانک — پرسشِ مبلغ', 'dep_ok' => 'انتقال به بانک — موفق',
        'int_on' => 'سودِ بانک',
        'dep_low' => 'انتقال به بانک — کیفِ‌پول کم',
        'wd_ask' => 'برداشت از بانک — پرسشِ مبلغ', 'wd_ok' => 'برداشت از بانک — موفق',
        'wd_low' => 'برداشت از بانک — صندوق کم',
        'btn_risk_high' => 'دکمه: سرقت پرریسک', 'btn_risk_normal' => 'دکمه: سرقت معمولی',
        'btn_risk_low'  => 'دکمه: سرقت کم‌ریسک',
        'hack_menu' => 'سرقت — منویِ انتخابِ سطح', 'hack_not_yours' => 'سرقت — منویِ کسِ دیگری',
        'hack_menu_gone' => 'سرقت — منوی منقضی', 'risk_line' => 'سرقت — خطِ سطحِ ریسک',
        'risk_high' => 'نامِ سطح: پرریسک', 'risk_normal' => 'نامِ سطح: معمولی', 'risk_low' => 'نامِ سطح: کم‌ریسک',
        'btn_send' => 'دکمه: ارسال به کاربر', 'btn_send_confirm' => 'دکمه: تاییدِ ارسال', 'btn_back' => 'دکمه: برگشت',
    ];
}
function bkLabel($k) { return bkLabels()[$k] ?? $k; }
function bkBtnKeys() { return ['btn_protect', 'btn_dep', 'btn_wd', 'btn_send', 'btn_send_confirm',
                               'btn_back', 'btn_risk_high', 'btn_risk_normal', 'btn_risk_low']; }

function bkAdminHome($chatId, $msgId = null) {
    $c = bkCfg();
    $t  = "🏦 <b>بانک</b>\n\n";
    $t .= 'وضعیت: ' . (bkOn() ? '✅ روشن' : '❌ خاموش') . "\n\n";
    $t .= 'کلمه‌ی باز کردنِ بانک: <code>' . h($c['word_bank']) . "</code>\n";
    $t .= 'کلمه‌های هک: <code>' . h($c['word_hack']) . "</code>\n\n";
    $t .= '🛡 حفاظتِ دستی: <b>' . (int)round($c['manual_protect'] / 60) . "</b> دقیقه\n";
    $t .= '⏳ کول‌داونِ هک: <b>' . (int)round($c['hack_cooldown'] / 60) . "</b> دقیقه\n";
    $t .= '🛡 شیلدِ خودکار بعدِ هک: <b>' . (int)$c['shield_after'] . "</b> ثانیه\n";
    if (!empty($c['interest']['on'])) {
        $rs = [];
        foreach ((array)($c['interest']['tiers'] ?? []) as $tt)
            $rs[] = bkNum($tt[0] ?? 0) . '+ → ' . (float)($tt[1] ?? 0) . '٪';
        $t .= "\n📈 <b>سودِ روزانه‌ی صندوق</b>\n" . implode("\n", $rs) . "\n";
    }

    $rows = [
        [btnCb(bkOn() ? '✅ روشن' : '❌ خاموش', 'bkax', 'info')],
        [btnCb('🗣 کلمه‌ها', 'bkaw_home', 'admin'), btnCb('✏️ متن‌ها و دکمه‌ها', 'bkat_home', 'admin')],
        [btnCb((empty($c['card_image']) ? '🖼 کارتِ گرافیکی: خاموش' : '🖼 کارتِ گرافیکی: روشن')
               . (function_exists('bcReady') && !bcReady() ? ' ⚠️' : ''), 'bkacard', 'info')],
        [btnCb('🎨 رنگِ دکمه‌ها', 'bkacolors', 'admin'),
         btnCb('🔤 فونتِ کارت', 'bkafont', 'admin')],
        [btnCb(!empty(bkVal('interest.on', true)) ? '📈 سودِ بانک: روشن' : '📈 سودِ بانک: خاموش', 'bkaint', 'info')],
        [btnCb('🛡 حفاظتِ دستی (دقیقه)', 'bkaprot', 'admin'), btnCb('🛡 شیلدِ خودکار (ثانیه)', 'bkashield', 'admin')],
        [btnCb('⏳ کول‌داونِ هک (دقیقه)', 'bkacool', 'admin')],
        [btnCb(UT('back'), 'ag_games', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function bkTextsCfg() {
    return [
        'title' => 'متن‌های بانک (سرقت الماس)',
        'keys'  => array_keys((array)bkVal('texts', [])),
        'popup' => ['ask_expired', 'not_your_card', 'hack_not_yours', 'hack_menu_gone'],
        'btns'  => bkBtnKeys(),
        'label' => 'bkLabel',
        'value' => function ($k) { return (string)bkVal('texts.' . $k, ''); },
        'cb'    => 'bkat_',
        'edit'  => 'bkats_',
        'back'  => 'bk_home',
    ];
}

function bkAdminFont($chatId, $msgId) {
    if (!function_exists('bcFontList')) {
        editMsg(BOT_TOKEN, $chatId, $msgId, '⚠️ ماژولِ کارتِ گرافیکی در دسترس نیست.',
            inlineKb([[btnCb(UT('back'), 'bk_home', 'nav')]]));
        return;
    }
    $cur     = trim((string)bkVal('card_font', ''));
    $curBold = trim((string)bkVal('card_font_bold', ''));
    $eff     = function_exists('bcFont') ? (string)(bcFont(true) ?: '') : '';
    $follows = function_exists('bcFontFollowsPrices') && bcFontFollowsPrices();
    $list    = bcFontList();

    $t  = "🔤 <b>فونتِ کارتِ بانک</b>\n\n";
    $t .= 'الان: <code>' . h($eff !== '' ? basename($eff) : 'پیدا نشد') . "</code>\n";
    $t .= 'منبع: ' . ($cur !== '' ? '📌 مخصوصِ بانک'
                                  : ($follows ? '🔗 همان فونتِ «قیمت لحظه‌ای»' : '🔎 خودکار')) . "\n";
    if ($cur !== '' && $curBold !== '') $t .= 'بولد: <code>' . h(basename($curBold)) . "</code>\n";

    $t .= "\n🔗 پیش‌فرض این است که کارتِ بانک همان فونتِ بخشِ <b>قیمت لحظه‌ای</b>\n";
    $t .= "را بردارد — یک جا تنظیم می‌کنی، هر دو کارت هم‌شکل می‌مانند.\n\n";
    $t .= "اگر این‌جا فونتی انتخاب کنی، فقط برایِ کارتِ بانک اعمال می‌شود\n";
    $t .= "و دیگر از بخشِ قیمت پیروی نمی‌کند.\n\n";
    $t .= "<b>فا</b> یعنی آن فونت حرفِ فارسی دارد.";

    $rows = [];
    $i = 0;
    foreach ($list as $path => $m) {
        $on  = ($path === $cur) ? '✅ ' : '';
        $fa  = !empty($m['fa']) ? ' · فا' : '';
        $rows[] = [btnCb($on . mb_substr($m['name'], 0, 34) . $fa, 'bkafont_' . $i, 'info')];
        $i++;
        if ($i >= 20) break;
    }
    if (!$rows) $t .= "\n\n🔴 هیچ فونتی رویِ سرور پیدا نشد.";

    $rows[] = [btnCb('📤 فرستادن فونتِ تازه', 'bkafontup', 'confirm')];
    if ($cur !== '' || $curBold !== '')
        $rows[] = [btnCb('🔗 برگرد به فونتِ بخشِ قیمت', 'bkafontclr', 'reject')];
    $rows[] = [btnCb('💹 رفتن به فونتِ بخشِ قیمت', 'pxcard', 'admin')];
    $rows[] = [btnCb('👀 نمونه‌ی کارت', 'bkafontprev', 'confirm')];
    $rows[] = [btnCb(UT('back'), 'bk_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function bkAdminColors($chatId, $msgId) {
    $t = "🎨 <b>رنگِ دکمه‌های بانک</b>\n\nروی هرکدام بزن تا رنگش را عوض کنی.\n\n";
    $rows = [];
    foreach (bkBtnKeys() as $k) {
        $color = (string)bkVal('btns.' . $k . '.color', 'none');
        $t .= '• ' . h(bkLabel($k)) . ': <b>' . h(styleMap()[$color] ?? $color) . "</b>\n";
        $rows[] = [btnCb(bkLabel($k), 'bkacolk_' . $k, 'info')];
    }
    $rows[] = [btnCb(UT('back'), 'bk_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function bkAdminColorPick($chatId, $msgId, $k) {
    $cur = (string)bkVal('btns.' . $k . '.color', 'none');
    $t = "🎨 رنگِ <b>" . h(bkLabel($k)) . "</b> را انتخاب کن:\n\nالان: <b>" . h(styleMap()[$cur] ?? $cur) . "</b>";
    $rows = [];
    foreach (styleMap() as $sk => $sl) $rows[] = [btnCb(($sk === $cur ? '✅ ' : '') . $sl, 'bkacolv_' . $k . '_' . $sk, 'info')];
    $rows[] = [btnCb(UT('back'), 'bkacolors', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function bkAdminWords($chatId, $msgId) {
    $c = bkCfg();
    $t = "🗣 <b>کلمه‌های بانک</b>\n\nهر کلمه را با ویرگول جدا کنید.\n\n";
    $map = ['word_bank' => 'باز کردنِ بانک', 'word_hack' => 'شروعِ هک'];
    $rows = [];
    foreach ($map as $k => $lbl) {
        $t .= '• <b>' . h($lbl) . '</b>: <code>' . h((string)$c[$k]) . "</code>\n";
        $rows[] = [btnCb($lbl, 'bkaws_' . $k, 'admin')];
    }
    $rows[] = [btnCb(UT('back'), 'bk_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function bkAdminCallback($data, $chatId, $msgId, $cbId) {
    if (!str_starts_with((string)$data, 'bk')) return false;

    if ($data === 'bk_home') { answerCb(BOT_TOKEN, $cbId); bkAdminHome($chatId, $msgId); return true; }
    if ($data === 'bkacard') {
        bkSet(function (&$c) { $c['card_image'] = empty($c['card_image']) ? 1 : 0; });
        $warn = (function_exists('bcReady') && !bcReady())
            ? '⚠️ روی این هاست GD یا فونتِ فارسی نیست — کارت متنی می‌ماند.' : '✅';
        answerCb(BOT_TOKEN, $cbId, $warn, true); bkAdminHome($chatId, $msgId); return true;
    }
    if ($data === 'bkax') {
        bkSet(function (&$c) { $c['on'] = empty($c['on']); });
        answerCb(BOT_TOKEN, $cbId, '✅'); bkAdminHome($chatId, $msgId); return true;
    }

    if ($data === 'bkaw_home') { answerCb(BOT_TOKEN, $cbId); bkAdminWords($chatId, $msgId); return true; }
    if (txRoute(bkTextsCfg(), $data, $chatId, $msgId, $cbId)) return true;

    if ($data === 'bkafont') { answerCb(BOT_TOKEN, $cbId); bkAdminFont($chatId, $msgId); return true; }
    if (preg_match('/^bkafont_(\d+)$/', $data, $m) && function_exists('bcFontList')) {
        $paths = array_keys(bcFontList());
        $p = $paths[(int)$m[1]] ?? '';
        if ($p === '') { answerCb(BOT_TOKEN, $cbId, '❌ پیدا نشد', true); return true; }
        $bold = '';
        foreach (['-Bold', 'Bold', '-bold'] as $suf) {
            $cand = preg_replace('/(-?(Regular|regular))?\.ttf$/i', $suf . '.ttf', $p);
            if ($cand !== $p && is_file($cand)) { $bold = $cand; break; }
        }
        bkSet(function (&$c) use ($p, $bold) { $c['card_font'] = $p; $c['card_font_bold'] = $bold; });
        if (function_exists('bcFontBust')) bcFontBust();
        if (function_exists('bcIdCachePath')) @unlink(bcIdCachePath());
        answerCb(BOT_TOKEN, $cbId, '✅ ' . basename($p));
        bkAdminFont($chatId, $msgId);
        return true;
    }
    if ($data === 'bkafontclr') {
        bkSet(function (&$c) { $c['card_font'] = ''; $c['card_font_bold'] = ''; });
        if (function_exists('bcFontBust')) bcFontBust();
        if (function_exists('bcIdCachePath')) @unlink(bcIdCachePath());
        answerCb(BOT_TOKEN, $cbId, '🔗 از بخشِ قیمت');
        bkAdminFont($chatId, $msgId);
        return true;
    }
    if ($data === 'bkafontup') {
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'bk_font', []);
        sendMsg(BOT_TOKEN, $chatId,
            "🔤 فایلِ فونت را بفرستید.\n\nفقط <code>.ttf</code> — و بهتر است فونتی باشد که حرفِ فارسی دارد (وزیرمتن، ایران‌سنس و مانندش).",
            inlineKb([[btnCb('انصراف', 'bkafont', 'cancel')]]));
        return true;
    }
    if ($data === 'bkafontprev') {
        answerCb(BOT_TOKEN, $cbId, '🖼 نمونه…');
        if (function_exists('bcSend')) {
            bcSend($chatId, '👀 نمونه‌ی کارت با فونتِ فعلی', [
                'name' => 'محمدرضا حسینی', 'username' => 'numbix', 'uid' => 1,
                'locked' => 1234567, 'level' => 3,
            ]);
        }
        return true;
    }

    if ($data === 'bkaint') {
        bkSet(function (&$c) {
            if (!is_array($c['interest'] ?? null)) $c['interest'] = bkDefaults()['interest'];
            $c['interest']['on'] = empty($c['interest']['on']);
        });
        answerCb(BOT_TOKEN, $cbId, '✅'); bkAdminHome($chatId, $msgId); return true;
    }

    if ($data === 'bkacolors') { answerCb(BOT_TOKEN, $cbId); bkAdminColors($chatId, $msgId); return true; }
    if (preg_match('/^bkacolk_(\w+)$/', $data, $m) && in_array($m[1], bkBtnKeys(), true)) {
        answerCb(BOT_TOKEN, $cbId); bkAdminColorPick($chatId, $msgId, $m[1]); return true;
    }
    if (preg_match('/^bkacolv_(\w+)_(\w+)$/', $data, $m) && in_array($m[1], bkBtnKeys(), true) && isset(styleMap()[$m[2]])) {
        bkSet(function (&$c) use ($m) {
            if (!is_array($c['btns'][$m[1]] ?? null)) $c['btns'][$m[1]] = [];
            $c['btns'][$m[1]]['color'] = $m[2];
        });
        answerCb(BOT_TOKEN, $cbId, '✅'); bkAdminColorPick($chatId, $msgId, $m[1]); return true;
    }

    $asks = [
        'bkaprot'   => ['bk_prot',   "🛡 مدتِ حفاظتِ دستی چند دقیقه باشد؟"],
        'bkashield' => ['bk_shield', "🛡 مدتِ شیلدِ خودکار (بعدِ هر هک) چند ثانیه باشد؟"],
        'bkacool'   => ['bk_cool',   "⏳ فاصله‌ی دو هکِ همان مهاجم چند دقیقه باشد؟"],
    ];
    if (isset($asks[$data])) {
        [$act, $ask] = $asks[$data];
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), $act, []);
        sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnCb('انصراف', 'bk_home', 'cancel')]]));
        return true;
    }

    foreach (['bkats_' => ['bk_text', 'texts.'], 'bkaws_' => ['bk_word', '']] as $pre => [$act, $path]) {
        if (!str_starts_with($data, $pre)) continue;
        $k = substr($data, strlen($pre));
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), $act, ['k' => $k]);
        $cur = (string)bkVal($path . $k, '');
        $back = inlineKb([[btnCb(UT('back'), $act === 'bk_text' ? 'bkat_home' : 'bkaw_home', 'cancel')]]);
        if ($act === 'bk_text' && bkIsButtonKey($k)) {
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ متنِ دکمه‌ی <b>" . h(bkLabel($k)) . "</b> را بفرست.\n\n" .
                "اگر می‌خواهی ایموجیِ پریمیوم هم رویِ دکمه بنشیند، همان ایموجی را داخلِ همین پیام بفرست.\n\n" .
                "الان: <code>" . h($cur) . "</code>", $back);
        } else {
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ متنِ <b>" . h(bkLabel($k) ?: $k) . "</b> را بفرست.\n\n" .
                "جای‌گذاری‌های داخلِ آکولاد ({name}، {amount}، ...) را دست‌نخورده نگه دار.\n\n" .
                "الان:\n" . ($act === 'bk_text' ? $cur : '<code>' . h($cur) . '</code>'), $back);
        }
        return true;
    }

    return false;
}

function bkStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'bk_')) return false;
    if (!isAdmin($uid)) return false;

    $st   = getState($uid);
    $sd   = $st['data'] ?? [];
    $text = trim((string)($msg['text'] ?? ''));
    $back = inlineKb([[btnCb('🏦 بانک', 'bk_home', 'admin')]]);
    $done = function ($m = "✅ ذخیره شد.") use ($uid, $chatId, $back) {
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $m, $back);
        return true;
    };

    if ($action === 'bk_font') {
        $doc = $msg['document'] ?? null;
        if (!$doc) { sendMsg(BOT_TOKEN, $chatId, "⚠️ فونت را به‌شکل <b>فایل</b> بفرستید، نه عکس."); return true; }

        $name = (string)($doc['file_name'] ?? 'font.ttf');
        if (!preg_match('/\.ttf$/i', $name)) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ فقط <code>.ttf</code>."); return true;
        }
        if ((int)($doc['file_size'] ?? 0) > 12 * 1024 * 1024) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ فایل خیلی بزرگ است (بیشتر از ۱۲ مگابایت)."); return true;
        }

        $r    = tg(BOT_TOKEN, 'getFile', ['file_id' => (string)$doc['file_id']], 15);
        $path = (string)($r['result']['file_path'] ?? '');
        if ($path === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ فایل از تلگرام گرفته نشد."); return true; }

        $ch = curl_init(rtrim(TG_API_BASE, '/') . '/file/bot' . BOT_TOKEN . '/' . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 8]);
        $bytes = curl_exec($ch);
        curl_close($ch);
        if (!is_string($bytes) || strlen($bytes) < 1000) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ دانلودِ فونت نشد."); return true;
        }

        $dir = rtrim(DATA_DIR, '/') . '/fonts';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $dst = $dir . '/' . preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        if (@file_put_contents($dst, $bytes) === false) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ نوشتن نشد — پوشه‌ی داده اجازه‌ی نوشتن ندارد."); return true;
        }

        bkSet(function (&$c) use ($dst) { $c['card_font'] = $dst; $c['card_font_bold'] = $dst; });
        if (function_exists('bcFontBust')) bcFontBust();
        if (function_exists('bcIdCachePath')) @unlink(bcIdCachePath());
        clearState($uid);

        $fa = function_exists('bcFontHasFa') ? bcFontHasFa($dst) : true;
        sendMsg(BOT_TOKEN, $chatId,
            '✅ فونت ثبت شد: <code>' . h(basename($dst)) . '</code>' .
            ($fa ? '' : "\n\n⚠️ این فونت حرفِ فارسی ندارد — اسم‌های فارسی مربعِ خالی می‌شوند."),
            inlineKb([[btnCb('🔤 فونتِ کارت', 'bkafont', 'admin')]]));
        return true;
    }

    if ($action === 'bk_prot') {
        $v = (int)norm_fa_digits($text);
        if ($v < 1 || $v > 1440) { sendMsg(BOT_TOKEN, $chatId, "⚠️ بین ۱ تا ۱۴۴۰ دقیقه."); return true; }
        bkSet(function (&$c) use ($v) { $c['manual_protect'] = $v * 60; });
        return $done('✅ حفاظتِ دستی: ' . $v . ' دقیقه');
    }
    if ($action === 'bk_shield') {
        $v = (int)norm_fa_digits($text);
        if ($v < 0 || $v > 3600) { sendMsg(BOT_TOKEN, $chatId, "⚠️ بین ۰ تا ۳۶۰۰ ثانیه."); return true; }
        bkSet(function (&$c) use ($v) { $c['shield_after'] = $v; });
        return $done('✅ شیلدِ خودکار: ' . $v . ' ثانیه');
    }
    if ($action === 'bk_cool') {
        $v = (int)norm_fa_digits($text);
        if ($v < 1 || $v > 1440) { sendMsg(BOT_TOKEN, $chatId, "⚠️ بین ۱ تا ۱۴۴۰ دقیقه."); return true; }
        bkSet(function (&$c) use ($v) { $c['hack_cooldown'] = $v * 60; });
        return $done('✅ کول‌داونِ هک: ' . $v . ' دقیقه');
    }
    if ($action === 'bk_word') {
        $k = (string)($sd['k'] ?? '');
        if ($k === '' || $text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ خالی نمی‌شود."); return true; }
        bkSet(function (&$c) use ($k, $text) { $c[$k] = $text; });
        return $done();
    }
    if ($action === 'bk_text') {
        $k = (string)($sd['k'] ?? '');
        if ($k === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ چیزی برای ذخیره نیست."); return true; }

        if (bkIsButtonKey($k)) {
            if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
            $ids  = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
            $icon = $ids ? (string)$ids[0] : '';
            if ($icon !== '' && function_exists('textWithoutCustomEmoji')) {
                $clean = textWithoutCustomEmoji($msg);
                if ($clean !== '') $text = $clean;
            }
            if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
            bkSet(function (&$c) use ($k, $text, $icon) {
                $c['texts'][$k] = $text;
                if (!isset($c['icons']) || !is_array($c['icons'])) $c['icons'] = [];
                $c['icons'][$k] = $icon;
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                "✅ ذخیره شد" . ($icon !== '' ? " — ایموجیِ پریمیوم هم رویِ دکمه نشست." : '.') . "\n\nاین‌طور دیده می‌شود:",
                inlineKb([[bkBtn($k, [], 'bk_nop')]]));
            sendMsg(BOT_TOKEN, $chatId, '👆', $back);
            return true;
        }

        $html = msgHtml($msg);
        if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
        bkSet(function (&$c) use ($k, $html) { $c['texts'][$k] = $html; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد.", $back);
        return true;
    }
    clearState($uid);
    return true;
}
