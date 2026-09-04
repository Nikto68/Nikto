<?php
/**
 * 🏦 بانک — بازیِ سرقت/هک داخلِ گروه
 *
 * عمدا ارزِ جدیدی نمی‌سازد: کیف‌پول همان امتیازِ الماسِ diamond.php است
 * (gmPoints()/gmAdd() از games.php — همان چیزی که خودِ بازی‌ها و بازیِ
 * الماس هم رویش کار می‌کنند). فقط «بانک» — یعنی بخشی از همان الماس که
 * کاربر کنار گذاشته و می‌شود دزدید — و آمارِ هکِ هر کاربر این‌جا،
 * توی bank_users.json، جدا نگه داشته می‌شود؛ درست مثل اینکه diamond_users
 * امتیاز را نگه می‌دارد و این فایل فقط رویِ همان uid آمارِ بانک را.
 *
 * چرا فایلِ جدا و نه فیلدِ اضافه روی diamond_users: آن فایل را بازیِ
 * الماس (که خیلی داغ‌تر است — هر کلمه‌ی «الماس» در گروه) هر بار کامل
 * می‌خواند؛ اضافه کردنِ ده‌ها فیلدِ بانکی به هر رکوردِ آن فقط حجمش را
 * برای آن مسیرِ داغ زیاد می‌کرد بدونِ هیچ فایده‌ای برای دزدی.
 */

// ============================================================
// ⚙️ پیکربندی
// ============================================================

function bkDefaults() {
    return [
        'on'         => false,
        'group_only' => 1,
        'word_bank'  => 'بانک,حساب بانکی',   // کلمه‌های باز کردنِ کارتِ بانک
        'word_hack'  => 'سرقت الماس,هک,حمله', // کلمه‌های شروعِ هک (به‌جز خودِ /hack) — اولی نامِ اصلی/نمایشی است

        'manual_protect' => 900,     // ثانیه — حفاظتِ دستی (۱۵ دقیقه)
        'shield_after'   => 300,     // ثانیه — حفاظتِ خودکارِ کوتاه بعدِ هر هک (موفق یا ناموفق)
        'hack_cooldown'  => 1200,    // ثانیه — فاصله‌ی دو هکِ همان مهاجم (۲۰ دقیقه)
        'level_step'     => 500000,  // هر چند الماسِ دزدیده‌شده، یک سطحِ بانک بالاتر
        'top_n'          => 10,

        // 🎲 موتورِ رند — همه از پنل قابلِ تنظیم، تا خودِ فرمول را
        // بشود بدونِ دست‌زدن به کد رام‌تر یا سخت‌تر کرد
        'rng' => [
            'base_success'  => 42.0,  // درصدِ پایه، پیش از اثرِ سطح/امنیت/جیتر
            'success_floor' => 18.0,
            'success_ceil'  => 72.0,
            'jitter_pct'    => 12.0,  // ± چند درصد، رندومِ خودش
            'jackpot_pct'   => 0.4,
            'perfect_pct'   => 7.0,
            'critfail_pct'  => 8.0,
            'partial_share' => 0.35,  // از سهمِ موفقیت، چند درصدش partial باشد

            'jackpot_min'  => 25.0, 'jackpot_max' => 40.0,
            'perfect_min'  => 10.0, 'perfect_max' => 16.0,
            'success_min'  => 4.0,  'success_max' => 10.0,
            'partial_min'  => 1.0,  'partial_max' => 4.0,
            'critfail_min' => 5.0,  'critfail_max' => 15.0,   // درصدی از بانکِ خودِ هکر
        ],

        // 🔘 دکمه‌ها — آیکون (ایموجیِ پریمیوم) با شناسه‌اش از
        // پنل ← 🔘 ایموجیِ پریمیوم گرفته می‌شود، بعد همین‌جا (پنلِ وب) جا می‌گیرد
        'icons' => ['btn_protect' => '', 'btn_send' => '', 'btn_send_confirm' => '', 'btn_back' => ''],
        'btns'  => [
            'btn_protect'      => ['color' => 'primary'],
            'btn_send'         => ['color' => 'none'],
            'btn_send_confirm' => ['color' => 'success'],
            'btn_back'         => ['color' => 'none'],
        ],

        'texts' => [
            'btn_protect'  => '🛡 حفاظت از بانک',
            'btn_send'         => '🎁 ارسال به کاربر',
            'btn_send_confirm' => '✅ انتقال',
            'btn_back'         => '🔙 برگشت',

            // 🏦 «بانک» یعنی همون کیف‌پولِ الماسِ خودِ کاربر — عمدا صندوقِ
            // جدایی نیست که باید دستی توش واریز کرد؛ هرچی کاربر تویِ
            // کیف‌پولش داره، خودکار همون قابلِ هک‌شدن است.
            'card' => "🏦 <b>BANK</b>\n\n" .
                      "👤 User: {name}\n\n" .
                      "💎 Wallet (Hackable): <b>{wallet}</b>\n\n" .
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

            'ask_bad_num' => "❌ یک عددِ صحیح و بزرگ‌تر از صفر بفرستید.",
            'ask_expired' => "⌛️ این درخواست منقضی شده. دوباره از روی /bank شروع کنید.",
            'not_your_card' => "🔒 این کارتِ بانکِ شما نیست — با /bank کارتِ خودتان را باز کنید.",

            // 🎁 ارسالِ الماس به کاربرِ دیگر — مستقیم از کیف‌پول به کیف‌پول
            'ask_send_amt'   => "🎁 <b>SEND DIAMONDS</b>\n\nچند تا الماس می‌خوای بفرستی؟",
            'ask_send_id'    => "👤 آیدیِ عددی یا یوزرنیمِ گیرنده (@username) را بفرست.\n\n<i>راهنما: کاربرِ موردنظر باید قبلا با ربات پیام داده باشد.</i>",
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

/** کلیدهایی که متن‌شان مستقیم روی یک دکمه‌ی شیشه‌ای می‌نشیند — تگ قبول نمی‌کنند */
function bkIsButtonKey($slug) {
    return in_array($slug, ['btn_protect', 'btn_send', 'btn_send_confirm', 'btn_back'], true);
}

function bkT($slug, $vars = []) {
    $t = (string)bkVal('texts.' . $slug, bkDefaults()['texts'][$slug] ?? $slug);
    if (bkIsButtonKey($slug)) $t = strip_tags($t);
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}

/** دکمه‌ی شیشه‌ای با متنِ قابل‌ویرایش + رنگِ قابل‌ویرایش + ایموجیِ پریمیومِ اختیاری */
function bkBtn($key, $vars, $data) {
    $b = ['text' => bkT($key, $vars), 'callback_data' => $data];
    $color = (string)bkVal('btns.' . $key . '.color', '');
    if (function_exists('isStyle') && isStyle($color)) $b['style'] = $color;
    $ic = trim((string)bkVal('icons.' . $key, ''));
    if ($ic !== '') $b['icon_custom_emoji_id'] = $ic;
    return $b;
}

function bkNum($n) { return number_format((float)$n, 0, '.', ','); }

/** «نام (@یوزرنیم)» برایِ نشان‌دادنِ طرفِ یک تراکنش تویِ پیام — نه فقط آیدیِ خشک */
function bkUserTag($id, $name, $uname) {
    $label = trim((string)$name) !== '' ? (string)$name : ('#' . (int)$id);
    $u = trim((string)$uname);
    return h($label) . ($u !== '' ? ' (@' . h($u) . ')' : '');
}

/** m:s یا «الان» برایِ یک ثانیه‌شمارِ رو به جلو */
function bkLeftStr($untilTs) {
    $left = (int)$untilTs - time();
    if ($left <= 0) return '—';
    $m = intdiv($left, 60); $s = $left % 60;
    return ($m > 0 ? $m . ' دقیقه و ' : '') . $s . ' ثانیه';
}

// ============================================================
// 🗃 داده‌ی هر کاربر — SQLite، هر کاربر یک ردیف (نه فایلِ JSONِ تخت
// با یک قفلِ سراسری که هر هک/حفاظت باید پشتش صف می‌شد)
// ============================================================

function bkUserDefault($uid) {
    return [
        'id' => (int)$uid, 'name' => '', 'username' => '',
        'protection_until' => 0, 'shield_until' => 0,
        'bank_level' => 1, 'security_level' => 1,
        'successful_hacks' => 0, 'failed_hacks' => 0,
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

/** یک‌بار، فقط وقتی bank_users.sqlite تازه ساخته می‌شود: هرچه در bank_users.json قدیمی بود کوچ می‌کند. */
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
    // فایلِ قدیمی پاک نمی‌شود، فقط از سرِ راه می‌رود — برای اطمینان
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

/** تغییرِ اتمیکِ یک کاربر — قفل فقط رویِ همین یک ردیف؛ رکورد نبود، با پیش‌فرض ساخته می‌شود */
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

/** «بانک» رکورد جدا نداره — موجودیِ زنده‌ی کیف‌پول رو می‌گیریم، نه فیلدِ کهنه */
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

// ============================================================
// ⏳ درخواستِ در انتظار (واریز/برداشت) — فایلِ جدا و سبک، جدا از
// state-machineِ خصوصیِ ربات چون این‌جا همه‌چیز باید داخلِ گروه کار کند
// ============================================================

function bkPendKey($uid, $chat) { return $uid . '_' . $chat; }

function bkPendSet($uid, $chat, $kind, $msgId, $data = []) {
    mutate('bank_states', function (&$s) use ($uid, $chat, $kind, $msgId, $data) {
        $s[bkPendKey($uid, $chat)] = ['kind' => $kind, 'msg' => (int)$msgId, 'at' => time(), 'data' => $data];
    });
}

function bkPendGet($uid, $chat) {
    $s = load('bank_states');
    $e = $s[bkPendKey($uid, $chat)] ?? null;
    // 🧹 خودجاروکن: این فایل روی هر پیامِ گروه خونده می‌شود (نه فقط
    // پیام‌های بانکی)، پس اگر بادوامِ کرون (?cron=) قابلِ اعتماد نباشد،
    // با شانسِ کم همین‌جا هم جارو می‌زنیم — مطمئن‌تر از تکیه‌کردنِ صرف
    // به کرونِ بیرونی، بدونِ اینکه هر خواندن قفل بگیرد.
    if (count($s) > 30 && random_int(1, 50) === 1) bkPendSweep(100);
    if (!$e || time() - (int)($e['at'] ?? 0) > 300) return null; // ۵ دقیقه اعتبار
    return $e;
}

function bkPendClear($uid, $chat) {
    mutate('bank_states', function (&$s) use ($uid, $chat) { unset($s[bkPendKey($uid, $chat)]); });
}

/**
 * پاک‌سازیِ دوره‌ای — وگرنه هر واریز/برداشت/ارسالِ رهاشده (کسی که دکمه را
 * زد ولی هیچ‌وقت عدد نفرستاد) برای همیشه توی bank_states.json می‌ماند؛
 * زیرِ بارِ زیاد و در درازمدت همین فایل را بزرگ و بزرگ‌تر می‌کند. از
 * ?cron= صدا زده می‌شود، درست مثلِ gmTick/mnTick.
 */
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

/** آیا این متن اصلا شبیهِ یک عددِ معتبر است — نه یک دستور/کلمه‌ی دیگر */
function bkLooksLikeAmount($raw) {
    $n = trim(str_replace([',', '٬', ' '], '', norm_fa_digits(trim((string)$raw))));
    return $n !== '' && preg_match('/^\d+(\.\d+)?$/', $n) === 1;
}

/** آیا این متن شبیهِ یک آیدیِ عددی یا یک یوزرنیم است */
function bkLooksLikeTarget($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return false;
    if ($raw[0] === '@') $raw = substr($raw, 1);
    if (preg_match('/^[A-Za-z][A-Za-z0-9_]{2,31}$/', $raw) === 1) return true;
    $digits = trim(norm_fa_digits($raw));
    return $digits !== '' && preg_match('/^\d+$/', $digits) === 1;
}

/**
 * آیدیِ عددی یا یوزرنیمِ گیرنده را به یک آیدیِ عددی تبدیل می‌کند.
 * برایِ یوزرنیم، چون جدولِ users فقط با id ایندکس شده (یوزرنیم داخلِ
 * ستونِ JSON است، نه یک ستونِ جدا)، با allUsers() یک اسکنِ کامل می‌زنیم —
 * این تابع فقط با کلیکِ «ارسال به کاربر» صدا زده می‌شود، نه توی مسیرِ داغ.
 */
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

// ============================================================
// 🎲 موتورِ رندِ امن
// ============================================================
//
// عمدا چند لایه‌ی مستقل، همه با random_int (نه mt_rand/Math.random):
//   ۱) شانسِ پایه از رویِ سطح/امنیت/تاریخچه ساخته می‌شود — نه یک عددِ
//      ثابت، ولی هنوز هم قابلِ حدس‌زدن اگر همین‌جا متوقف می‌شد.
//   ۲) رویِ همان شانس یک جیترِ رندومِ امن سوار می‌شود — یعنی برایِ
//      دقیقا همان دو نفر، شانسِ مؤثر هر بار عوض می‌شود.
//   ۳) نتیجه از رویِ یک «بلیط»ِ ۱ تا ۱۰۰٬۰۰۰ تعیین می‌شود، با چند
//      لایه‌ی مستقل رویش (جک‌پات/کریتیکال‌فیل قبل از هرچیز، بعد
//      باقی‌مانده بینِ پرفکت/موفق/جزئی/شکست).
//   ۴) درصدِ دزدیده‌شده هم خودش رندوم است (با یک رقمِ اعشار) — نه یک
//      عددِ ثابتِ توی هر ردیف.

function bkRollPercent($min, $max) {
    $min = (float)$min; $max = (float)$max;
    if ($max < $min) [$min, $max] = [$max, $min];
    $lo = (int)round($min * 10); $hi = (int)round($max * 10);
    if ($hi <= $lo) return round($min, 1);
    return random_int($lo, $hi) / 10.0;
}

function bkHackRoll(array $hacker, array $target) {
    $r = bkVal('rng', bkDefaults()['rng']);

    $hackerLevel = max(1, (int)($hacker['bank_level'] ?? 1));
    $targetSec   = max(1, (int)($target['security_level'] ?? 1));
    $wins        = (int)($hacker['successful_hacks'] ?? 0);
    $fails       = (int)($hacker['failed_hacks'] ?? 0);

    $edge = ($hackerLevel - $targetSec) * 1.8;
    $edge = max(-20.0, min(20.0, $edge));

    $hist = ($wins + $fails) > 0 ? ((($wins / ($wins + $fails)) - 0.5) * 10) : 0.0;

    $base = (float)$r['base_success'] + $edge + $hist;

    $jitter = max(1.0, (float)$r['jitter_pct']);
    $base  += random_int((int)round(-$jitter * 10), (int)round($jitter * 10)) / 10.0;

    $chance = max((float)$r['success_floor'], min((float)$r['success_ceil'], $base));

    $ticket = random_int(1, 100000);

    $jackpotSlice  = max(0, (int)round((float)$r['jackpot_pct']  * 1000));
    $critFailSlice = max(0, (int)round((float)$r['critfail_pct'] * 1000));
    $perfectSlice  = max(0, (int)round((float)$r['perfect_pct']  * 1000));

    if ($ticket <= $jackpotSlice) {
        return ['tier' => 'jackpot', 'pct' => bkRollPercent($r['jackpot_min'], $r['jackpot_max'])];
    }
    $ticket -= $jackpotSlice;

    if ($ticket <= $critFailSlice) {
        return ['tier' => 'critfail', 'fine_pct' => bkRollPercent($r['critfail_min'], $r['critfail_max'])];
    }
    $ticket -= $critFailSlice;

    if ($ticket <= $perfectSlice) {
        return ['tier' => 'perfect', 'pct' => bkRollPercent($r['perfect_min'], $r['perfect_max'])];
    }
    $ticket -= $perfectSlice;

    $remaining    = max(1, 100000 - $jackpotSlice - $critFailSlice - $perfectSlice);
    $successShare = max(0.0, min(1.0, $chance / 100.0));
    $successSlice = (int)round($remaining * $successShare * (1 - (float)$r['partial_share']));
    $partialSlice = (int)round($remaining * $successShare * (float)$r['partial_share']);

    if ($ticket <= $successSlice) {
        return ['tier' => 'success', 'pct' => bkRollPercent($r['success_min'], $r['success_max'])];
    }
    $ticket -= $successSlice;

    if ($ticket <= $partialSlice) {
        return ['tier' => 'partial', 'pct' => bkRollPercent($r['partial_min'], $r['partial_max'])];
    }

    return ['tier' => 'fail'];
}

// ============================================================
// 🏦 کارتِ بانک
// ============================================================

function bkCardText($uid, $name) {
    $u = bkUser($uid) ?? bkUserDefault($uid);
    $now = time();
    $protUntil = max((int)($u['protection_until'] ?? 0), (int)($u['shield_until'] ?? 0));
    $active    = $protUntil > $now;

    $wallet = gmPoints($uid);
    return bkT('card', [
        'name'         => h($name),
        'wallet'       => bkNum($wallet),
        'bank'         => bkNum($wallet), // «بانک» = همین کیف‌پول — برایِ سازگاری با متن‌های سفارشیِ قدیمی
        'sec_status'   => $active ? 'ACTIVE' : 'INACTIVE',
        'protect_left' => $active ? bkLeftStr($protUntil) : '—',
        'level'        => (int)($u['bank_level'] ?? 1),
        'wins'         => bkNum($u['successful_hacks'] ?? 0),
        'stolen'       => bkNum($u['total_stolen'] ?? 0),
    ]);
}

function bkKb($uid) {
    // 🔒 صاحبِ کارت تویِ خودِ callback_data — چون تویِ گروه هرکسی می‌تونه
    // زیرِ کارتِ بانکِ یکیِ دیگه دکمه بزنه، و بدونِ این، کلیک‌کننده رویِ
    // اکانتِ خودش عمل می‌کرد ولی کارتِ صاحبِ پیام overwrite می‌شد.
    return inlineKb([
        [bkBtn('btn_protect', [], 'bk_protect_' . (int)$uid)],
        [bkBtn('btn_send', [], 'bk_send_' . (int)$uid)],
    ]);
}

function bkShow($uid, $chatId, $name, $editMsgId = null, $replyToMsgId = null) {
    $text = bkCardText($uid, $name);
    if ($editMsgId) { editMsg(BOT_TOKEN, $chatId, $editMsgId, $text, bkKb($uid)); return; }
    // 🧵 وقتی خودِ کاربر با نوشتنِ «بانک» کارت را باز می‌کند، ریپلای‌شده
    // رویِ همان پیامش می‌رود — تا توی گروهِ شلوغ معلوم باشد جوابِ کدوم پیامه
    $extra = $replyToMsgId ? ['reply_to_message_id' => (int)$replyToMsgId] : [];
    sendMsg(BOT_TOKEN, $chatId, $text, bkKb($uid), $extra);
}

/**
 * سوال/تاییدِ هر گامِ واریز-برداشت-ارسال، همیشه رویِ همان پیامِ کارتِ
 * بانک ویرایش می‌شود (نه پیامِ تازه) — تا گروه با چند پیامِ پیاپی شلوغ
 * نشود — و همیشه یک دکمه‌ی «برگشت» زیرش هست که هر لحظه به همان کارتِ
 * اصلی برمی‌گردد.
 */
function bkAskEdit($chatId, $pendMsgId, $text, array $extraRows = [], $uid = 0) {
    $rows = $extraRows;
    $rows[] = [bkBtn('btn_back', [], 'bk_cancelflow_' . (int)$uid)];
    $kb = inlineKb($rows);
    if ($pendMsgId) { editMsg(BOT_TOKEN, $chatId, (int)$pendMsgId, $text, $kb); return; }
    sendMsg(BOT_TOKEN, $chatId, $text, $kb);
}

/**
 * 🎁 ارسالِ الماس از کیف‌پول به کیف‌پولِ یک کاربرِ دیگر — مستقیم، نه از
 * راهِ بانک. عمدا از همان gmAdd() دوباره استفاده می‌شود (نه یک نوشتنِ
 * دستیِ تازه رویِ diamond_users): gmAdd هرباری که صدا زده شود، شمارنده‌ی
 * جمعِ کلِ الماس (diamond_sum) را هم خودش به‌روز نگه می‌دارد — دور زدنِ
 * آن یعنی آن شمارنده کم‌کم غلط می‌شود. اگر نوشتنِ سمتِ گیرنده به هر
 * دلیلی نگرفت، همان الماس به فرستنده برمی‌گردد (جبرانِ تراکنشِ ناتمام).
 */
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
        // عملا نمی‌افتد (gmAdd فقط برایِ کم‌کردنِ منفی رد می‌کند)، ولی
        // برایِ اطمینان — الماس هیچ‌وقت نباید بی‌صاحب بماند
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

function bkProtect($uid) {
    $secs = max(60, (int)bkVal('manual_protect', 900));
    $now  = time();
    $left = 0;
    bkUserSet($uid, function (&$u) use ($secs, $now, &$left) {
        $cur = max((int)($u['protection_until'] ?? 0), (int)($u['shield_until'] ?? 0));
        if ($cur > $now) { $left = $cur - $now; return; }
        $u['protection_until'] = $now + $secs;
    });
    if ($left > 0) {
        $m = intdiv($left, 60); $s = $left % 60;
        return [false, bkT('protect_still', ['left' => ($m > 0 ? $m . ' دقیقه و ' : '') . $s . ' ثانیه'])];
    }
    return [true, bkT('protected', ['mins' => (int)round($secs / 60)])];
}

// ============================================================
// 🔫 هک
// ============================================================

/**
 * یک قفلِ اتمیکِ واحد رویِ همین دو ردیف (هکر+هدف) — هم چک‌های امنیتی
 * (خودحفاظتی، کول‌داون، شیلد) و هم تصمیمِ نتیجه، همه داخلِ همان یک
 * تراکنش؛ بدونِ این، دو کلیکِ هم‌زمان می‌توانستند هر دو از چکِ
 * کول‌داون/شیلد رد شوند و هدف را دوبار خالی کنند. جابه‌جاییِ واقعیِ
 * الماس هم — چون «بانک» دیگر صندوقِ جدایی نیست، همان کیف‌پولِ زنده‌ی
 * diamond_users است — از همین‌جا، هنوز زیرِ همین قفل، با gmAdd() انجام
 * می‌شود؛ gmAdd خودش رویِ فایلِ دیگری (diamond_users.sqlite) قفلِ
 * جداگانه می‌گیرد، پس تداخل/بن‌بستی پیش نمی‌آید، و اگر همان لحظه هدف
 * جای دیگری (مثلا خریدِ فروشگاه) کم‌تر از مقدارِ محاسبه‌شده داشت،
 * gmAdd خودش رد می‌کند — نتیجه وقتی به fail برمی‌گردد.
 */
function bkHack($hackerId, $hackerName, $hackerUname, $targetId) {
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
        $target = $hk === $tg ? $hacker : $fetch($tg); // خودحفاظتی جای دیگر رد می‌شود، این فقط برای اطمینان

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
            $roll = bkHackRoll($hacker, $target);
            $cooldown = max(60, (int)bkVal('hack_cooldown', 1200));
            $shieldSecs = max(0, (int)bkVal('shield_after', 300));
            $hacker['hack_cooldown_until'] = $now + $cooldown;
            $target['shield_until'] = $now + $shieldSecs;

            $out['tier'] = $roll['tier'];

            if (in_array($roll['tier'], ['jackpot', 'perfect', 'success', 'partial'], true)) {
                $amt = min($bal, floor($bal * $roll['pct'] / 100));
                $amt = max(0.0, $amt);
                if ($amt <= 0) {
                    // موجودیِ هدف اونقدر کمه که سهم گردِ صفر می‌شه — «موفقیتِ
                    // بدونِ غنیمت» نشون ندیم و کول‌داونِ هکر رو الکی مصرف نکنیم
                    $out['tier'] = 'fail';
                    $hacker['failed_hacks'] = (int)($hacker['failed_hacks'] ?? 0) + 1;
                } elseif (gmAdd($tg, -$amt)) {
                    gmAdd($hk, $amt, $hackerName, $hackerUname);
                    $hacker['successful_hacks'] = (int)($hacker['successful_hacks'] ?? 0) + 1;
                    $hacker['total_stolen']     = (float)($hacker['total_stolen'] ?? 0) + $amt;
                    $target['total_lost']       = (float)($target['total_lost'] ?? 0) + $amt;
                    $hacker['bank_level']       = bkLevelFromStolen($hacker['total_stolen']);
                    $out['amount'] = $amt; $out['pct'] = $roll['pct'];
                    $out['hackerBank'] = gmPoints($hk);
                } else {
                    // همون لحظه هدف جایِ دیگری خرج کرده بود — به‌جایِ الماسِ
                    // بی‌پشتوانه، شکست حساب می‌شود
                    $out['tier'] = 'fail';
                    $hacker['failed_hacks'] = (int)($hacker['failed_hacks'] ?? 0) + 1;
                }
            } elseif ($roll['tier'] === 'critfail') {
                $ownBal = gmPoints($hk);
                $fine = min($ownBal, floor($ownBal * $roll['fine_pct'] / 100));
                $deducted = $fine > 0 && gmAdd($hk, -$fine);
                $hacker['failed_hacks'] = (int)($hacker['failed_hacks'] ?? 0) + 1;
                $out['fine'] = $deducted ? $fine : 0; $out['hackerBank'] = gmPoints($hk);
            } else {
                $hacker['failed_hacks'] = (int)($hacker['failed_hacks'] ?? 0) + 1;
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

function bkHackCmd($uid, $chatId, $name, $uname, $replyTo, $msg) {
    $extra = $replyTo ? ['reply_to_message_id' => $replyTo] : [];
    $to = $msg['reply_to_message']['from'] ?? null;

    if (!$to || !empty($to['is_bot'])) {
        $firstWord = trim(explode(',', (string)bkVal('word_hack', 'هک'))[0]) ?: 'هک';
        sendMsg(BOT_TOKEN, $chatId, bkT('hack_how', ['word' => $firstWord]), null, $extra);
        return;
    }
    $targetId = (int)$to['id'];
    if ($targetId === (int)$uid) { sendMsg(BOT_TOKEN, $chatId, bkT('hack_self'), null, $extra); return; }

    // 🚫 نه هکرِ بلاک‌شده، نه هدفِ بلاک‌شده
    $hu = function_exists('getUser') ? getUser($uid) : null;
    if ($hu && !empty($hu['banned'])) return;
    $tuRaw = function_exists('getUser') ? getUser($targetId) : null;
    if ($tuRaw && !empty($tuRaw['banned'])) {
        sendMsg(BOT_TOKEN, $chatId, bkT('hack_no_target'), null, $extra); return;
    }

    $res = bkHack($uid, $name, $uname, $targetId);

    if ($res['err'] === 'cooldown') {
        sendMsg(BOT_TOKEN, $chatId, bkT('hack_cooldown', ['left' => bkLeftStr(time() + $res['left'])]), null, $extra);
        return;
    }
    if ($res['err'] === 'protected') {
        sendMsg(BOT_TOKEN, $chatId, bkT('hack_protected', ['left' => bkLeftStr(time() + $res['left'])]), null, $extra);
        return;
    }
    if ($res['err'] === 'empty') {
        sendMsg(BOT_TOKEN, $chatId, bkT('hack_empty'), null, $extra);
        return;
    }

    $hn = h($name); $tn = h($to['first_name'] ?? ($to['username'] ?? 'کاربر'));
    $vars = ['hn' => $hn, 'tn' => $tn];

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
    sendMsg(BOT_TOKEN, $chatId, $text, null, $extra);
}

// ============================================================
// 🏆 برترین‌ها
// ============================================================

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

// ============================================================
// 💬 دیسپچِ متنِ گروه
// ============================================================

function bkHandlePending($raw, $uid, $chatId, $name, $uname, $pend) {
    $kind = $pend['kind'] ?? '';
    $pendMsg = (int)($pend['msg'] ?? 0);

    // 🎁 گامِ دومِ ارسال به کاربر: حالا آیدیِ عددی یا یوزرنیم می‌خواهیم
    if ($kind === 'send_id') {
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

    // گامِ اولِ ارسال (send_amt) یک عددِ صحیح می‌خواهد
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

    // حالتِ ناشناخته — نباید پیش بیاید (bkHandleText جلوش را می‌گیرد)، فقط برایِ اطمینان
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
        // فقط وقتی متن واقعا شبیهِ جوابِ همین سوال است مصرفش کن — وگرنه
        // مثلا «مین ۵۰۰» (که برایِ بازیِ دیگری‌ست) به‌جایِ رسیدن به آن
        // بازی، اینجا به‌عنوانِ آیدیِ نامعتبر رد می‌شد. اگر جواب نبود،
        // دست‌نخورده رهایش کن تا بازی‌های دیگر بتوانند خودشان جوابش را بدهند.
        // «send_confirm» اصلا متن نمی‌خواهد (فقط دکمه‌ی تایید) — وگرنه یک
        // عددِ اتفاقی این‌جا بی‌جهت مصرف می‌شد.
        if ($kind === 'send_id') {
            $looksLikeAnswer = bkLooksLikeTarget($raw);
        } elseif ($kind === 'send_amt') {
            $looksLikeAnswer = bkLooksLikeAmount($raw);
        } else {
            $looksLikeAnswer = false;
        }
        if ($looksLikeAnswer) return bkHandlePending($raw, $uid, $chatId, $name, $uname, $pend);
    }

    if (preg_match('/^\/bankleader(?:@\w+)?(?:\s|$)/i', $raw)) { sendMsg(BOT_TOKEN, $chatId, bkTopText()); return true; }

    $isBank = (bool)preg_match('/^\/bank(?:@\w+)?(?:\s|$)/i', $raw);
    if (!$isBank) {
        foreach (explode(',', (string)bkVal('word_bank', 'بانک')) as $w) {
            $w = trim($w);
            if ($w !== '' && mb_strtolower($raw) === mb_strtolower($w)) { $isBank = true; break; }
        }
    }
    if ($isBank) { bkShow($uid, $chatId, $name, null, $msg['message_id'] ?? null); return true; }

    $isHack = (bool)preg_match('/^\/hack(?:@\w+)?(?:\s|$)/i', $raw);
    if (!$isHack) {
        foreach (explode(',', (string)bkVal('word_hack', 'هک,حمله')) as $w) {
            $w = trim($w);
            if ($w !== '' && mb_strtolower($raw) === mb_strtolower($w)) { $isHack = true; break; }
        }
    }
    if ($isHack) { bkHackCmd($uid, $chatId, $name, $uname, $replyTo, $msg ?? []); return true; }

    return false;
}

// ============================================================
// 🔘 کال‌بک‌ها
// ============================================================

function bkCallback($data, $uid, $chatId, $msgId, $cbId, $from = []) {
    if ($data === 'bk_nop') { answerCb(BOT_TOKEN, $cbId); return true; }

    // 🔒 صاحبِ کارت تویِ خودِ callback_data: bk_<action>_<ownerUid>.
    //
    // تویِ گروه، پیامِ کارتِ بانکِ هرکس برایِ همه قابلِ‌دیدن است و دکمه‌هایش
    // هم برایِ همه قابلِ‌کلیک — قبلا هیچ‌جا چک نمی‌شد که کلیک‌کننده همان
    // صاحبِ کارت است یا نه؛ نتیجه این‌که کلیک‌کننده رویِ اکانتِ خودش عمل
    // می‌کرد ولی پیامِ کارتِ صاحبِ اصلی با کارتِ کلیک‌کننده overwrite
    // می‌شد (پولی جابه‌جا نمی‌شد، ولی UX/کارتِ آن کاربر به‌هم می‌ریخت).
    if (!preg_match('/^bk_(protect|send|sendok|cancelflow)_(\d+)$/', (string)$data, $m)) {
        // فرمِ قدیمیِ بدونِ صاحب (نسخه‌ی قبل از این رفعِ باگ) — دیگر تولید
        // نمی‌شود، ولی اگر پیامِ خیلی قدیمی‌ای هنوز رویِ صفحه باشد بی‌صدا رد شود
        if (in_array($data, ['bk_protect', 'bk_send', 'bk_sendok', 'bk_cancelflow'], true)) {
            answerCb(BOT_TOKEN, $cbId, bkT('ask_expired'), true);
            return true;
        }
        return false;
    }
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
        sendMsg(BOT_TOKEN, $chatId, $t);
        bkShow($uid, $chatId, $name, (int)$msgId);
        return true;
    }
    if ($action === 'send') {
        answerCb(BOT_TOKEN, $cbId);
        bkPendSet($uid, $chatId, 'send_amt', $msgId);
        bkAskEdit($chatId, $msgId, bkT('ask_send_amt'), [], $uid);
        return true;
    }
    if ($action === 'cancelflow') {
        answerCb(BOT_TOKEN, $cbId);
        bkPendClear($uid, $chatId);
        bkShow($uid, $chatId, $name, (int)$msgId);
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

// ============================================================
// ✏️ ویرایشگرِ داخلِ ربات — پنل ← 🏦 بانک
// ============================================================
//
// همان الگویِ دقیقِ games.php (gmAdminHome/gmAdminTexts/gmStateHandle):
// متن‌ها با msgHtml() ذخیره می‌شوند (کوتیشن/بولد/ایموجیِ پریمیومِ
// داخلِ متن، هرچه تلگرام خودش فهمید، سالم می‌ماند)؛ برچسبِ خودِ دکمه
// (که تلگرام HTML رویش قبول نمی‌کند) متنِ ساده + شناسه‌ی ایموجیِ
// پریمیومِ جدا ذخیره می‌شود. تنظیماتِ عددیِ ریزِ موتورِ رند عمدا این‌جا
// نیست — آن یکی برایِ یک‌بار تنظیم‌کردنِ دقیق، فرمِ وب مناسب‌تر است؛
// چیزی که هرروز دست خورد (متن‌ها، دکمه‌ها، رنگ‌ها، کلمه‌ها، آستانه‌ها)
// همین‌جاست.

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
        'send_self' => 'ارسال — به خودت', 'send_no_target' => 'ارسال — گیرنده ناشناس',
        'send_low_wallet' => 'ارسال — کیف‌پول کم', 'send_confirm' => 'ارسال — تاییدیه', 'send_ok' => 'ارسال — موفق',
        'btn_protect' => 'دکمه: حفاظت',
        'btn_send' => 'دکمه: ارسال به کاربر', 'btn_send_confirm' => 'دکمه: تاییدِ ارسال', 'btn_back' => 'دکمه: برگشت',
    ];
}
function bkLabel($k) { return bkLabels()[$k] ?? $k; }
function bkBtnKeys() { return ['btn_protect', 'btn_send', 'btn_send_confirm', 'btn_back']; }

function bkAdminHome($chatId, $msgId = null) {
    $c = bkCfg();
    $t  = "🏦 <b>بانک</b>\n\n";
    $t .= 'وضعیت: ' . (bkOn() ? '✅ روشن' : '❌ خاموش') . "\n\n";
    $t .= 'کلمه‌ی باز کردنِ بانک: <code>' . h($c['word_bank']) . "</code>\n";
    $t .= 'کلمه‌های هک: <code>' . h($c['word_hack']) . "</code>\n\n";
    $t .= '🛡 حفاظتِ دستی: <b>' . (int)round($c['manual_protect'] / 60) . "</b> دقیقه\n";
    $t .= '⏳ کول‌داونِ هک: <b>' . (int)round($c['hack_cooldown'] / 60) . "</b> دقیقه\n";
    $t .= '🛡 شیلدِ خودکار بعدِ هک: <b>' . (int)$c['shield_after'] . "</b> ثانیه\n";

    $rows = [
        [btnCb(bkOn() ? '✅ روشن' : '❌ خاموش', 'bkax', 'info')],
        [btnCb('🗣 کلمه‌ها', 'bkaw_home', 'admin'), btnCb('✏️ متن‌ها و دکمه‌ها', 'bkat_home', 'admin')],
        [btnCb('🎨 رنگِ دکمه‌ها', 'bkacolors', 'admin')],
        [btnCb('🛡 حفاظتِ دستی (دقیقه)', 'bkaprot', 'admin'), btnCb('🛡 شیلدِ خودکار (ثانیه)', 'bkashield', 'admin')],
        [btnCb('⏳ کول‌داونِ هک (دقیقه)', 'bkacool', 'admin')],
        [btnCb(UT('back'), 'adm_home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function bkAdminTexts($chatId, $msgId, $page = 0) {
    $keys = array_keys((array)bkVal('texts', []));
    $per  = 12;
    $tot  = max(1, (int)ceil(count($keys) / $per));
    $page = max(0, min($tot - 1, (int)$page));
    $slice = array_slice($keys, $page * $per, $per);

    $t  = "✏️ <b>متن‌ها و دکمه‌های بانک</b> — صفحه " . ($page + 1) . " از {$tot}\n\n";
    $t .= "هرچه بنویسید عینا همان می‌رود: ایموجیِ پریمیوم و quote سالم می‌مانند.\n\n";
    $rows = [];
    foreach ($slice as $k) {
        $v = (string)bkVal('texts.' . $k, '');
        $t .= '• <b>' . h(bkLabel($k)) . '</b>: <code>' .
              h(mb_substr(str_replace("\n", ' ', strip_tags($v)), 0, 34)) . "</code>\n";
        $rows[] = [btnCb(bkLabel($k), 'bkats_' . $k, 'admin')];
    }
    $nav = [];
    if ($page > 0)        $nav[] = btnCb('◀️', 'bkat_' . ($page - 1), 'nav');
    if ($page < $tot - 1) $nav[] = btnCb('▶️', 'bkat_' . ($page + 1), 'nav');
    if ($nav) $rows[] = $nav;
    $rows[] = [btnCb(UT('back'), 'bk_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3800), inlineKb($rows));
}

function bkAdminColors($chatId, $msgId) {
    $t = "🎨 <b>رنگِ دکمه‌های بانک</b>\n\nروی هرکدام بزن تا رنگش را عوض کنی.\n\n";
    $rows = [];
    foreach (bkBtnKeys() as $k) {
        $color = (string)bkVal('btns.' . $k . '.color', 'none');
        $t .= '• ' . h(bkLabel($k)) . ': <b>' . h(styleMap()[$color] ?? $color) . "</b>\n";
        $rows[] = [btnCb(bkT($k, []) ?: bkLabel($k), 'bkacolk_' . $k, 'info')];
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
    if ($data === 'bkax') {
        bkSet(function (&$c) { $c['on'] = empty($c['on']); });
        answerCb(BOT_TOKEN, $cbId, '✅'); bkAdminHome($chatId, $msgId); return true;
    }

    if ($data === 'bkaw_home') { answerCb(BOT_TOKEN, $cbId); bkAdminWords($chatId, $msgId); return true; }
    if ($data === 'bkat_home') { answerCb(BOT_TOKEN, $cbId); bkAdminTexts($chatId, $msgId, 0); return true; }
    if (preg_match('/^bkat_(\d+)$/', $data, $m)) { answerCb(BOT_TOKEN, $cbId); bkAdminTexts($chatId, $msgId, (int)$m[1]); return true; }

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
        setState(ADMIN_ID, $act, []);
        sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnCb('انصراف', 'bk_home', 'cancel')]]));
        return true;
    }

    foreach (['bkats_' => ['bk_text', 'texts.'], 'bkaws_' => ['bk_word', '']] as $pre => [$act, $path]) {
        if (!str_starts_with($data, $pre)) continue;
        $k = substr($data, strlen($pre));
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, $act, ['k' => $k]);
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
