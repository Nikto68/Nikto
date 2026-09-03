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
        'word_hack'  => 'هک,حمله',           // کلمه‌های شروعِ هک (به‌جز خودِ /hack)

        'min_withdraw'   => 50000,   // زیرِ این موجودی، برداشت اصلا باز نمی‌شود
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
        // /panel ← 🔘 ایموجیِ پریمیوم گرفته می‌شود، بعد همین‌جا (پنلِ وب) جا می‌گیرد
        'icons' => ['btn_protect' => '', 'btn_deposit' => '', 'btn_withdraw' => ''],
        'btns'  => [
            'btn_protect'  => ['color' => 'primary'],
            'btn_deposit'  => ['color' => 'success'],
            'btn_withdraw' => ['color' => 'danger'],
        ],

        'texts' => [
            'btn_protect'  => '🛡 حفاظت از بانک',
            'btn_deposit'  => '💎 انتقال الماس',
            'btn_withdraw' => '💰 برداشت الماس',

            'card' => "🏦 <b>BANK</b>\n\n" .
                      "👤 User: {name}\n\n" .
                      "💎 Wallet: <b>{wallet}</b>\n" .
                      "🏦 Bank: <b>{bank}</b>\n\n" .
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

            'ask_deposit' => "💎 <b>DEPOSIT DIAMONDS</b>\n\nمقدار الماس موردنظر را ارسال کنید:\n\nمثال: <code>50000</code>",
            'ask_withdraw' => "💰 <b>WITHDRAW DIAMONDS</b>\n\nمقدار الماس موردنظر را ارسال کنید:\n\nمثال: <code>50000</code>",
            'ask_bad_num' => "❌ یک عددِ صحیح و بزرگ‌تر از صفر بفرستید.",
            'ask_expired' => "⌛️ این درخواست منقضی شده. دوباره از روی /bank شروع کنید.",

            'dep_low_wallet' => "❌ موجودیِ کیف‌پول کافی نیست.\n💎 Wallet: <b>{wallet}</b>",
            'dep_ok' => "🏦 <b>DEPOSIT SUCCESSFUL</b>\n\n💎 Amount: +{amount}\n\n━━━━━━━━━━━━━━\n\n💎 Wallet: <b>{wallet}</b>\n🏦 Bank: <b>{bank}</b>",

            'wd_locked' => "🏦 Bank Balance:\n\n💎 {bank}\n\n❌ Withdrawal unavailable\n\nحداقل موجودی موردنیاز:\n\n💎 {min}",
            'wd_low_bank' => "❌ موجودیِ بانک کمتر از مقدارِ درخواستی است.\n🏦 Bank: <b>{bank}</b>",
            'wd_ok' => "💰 <b>WITHDRAW SUCCESSFUL</b>\n\n💎 Withdrawn: {amount}\n\n━━━━━━━━━━━━━━\n\n🏦 Bank: <b>{bank}</b>\n💎 Wallet: <b>{wallet}</b>",

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
    return in_array($slug, ['btn_protect', 'btn_deposit', 'btn_withdraw'], true);
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

/** m:s یا «الان» برایِ یک ثانیه‌شمارِ رو به جلو */
function bkLeftStr($untilTs) {
    $left = (int)$untilTs - time();
    if ($left <= 0) return '—';
    $m = intdiv($left, 60); $s = $left % 60;
    return ($m > 0 ? $m . ' دقیقه و ' : '') . $s . ' ثانیه';
}

// ============================================================
// 🗃 داده‌ی هر کاربر
// ============================================================

function bkUserDefault($uid) {
    return [
        'id' => (int)$uid, 'name' => '', 'username' => '',
        'bank_balance' => 0.0, 'protection_until' => 0, 'shield_until' => 0,
        'bank_level' => 1, 'security_level' => 1,
        'successful_hacks' => 0, 'failed_hacks' => 0,
        'total_stolen' => 0.0, 'total_lost' => 0.0,
        'hack_cooldown_until' => 0,
        'created_at' => time(), 'updated_at' => time(),
    ];
}

function bkUser($uid) {
    $a = load('bank_users');
    return $a[(string)$uid] ?? null;
}

/** تغییرِ اتمیکِ یک کاربر — رکورد نبود، با پیش‌فرض ساخته می‌شود */
function bkUserSet($uid, callable $fn) {
    return mutate('bank_users', function (&$a) use ($uid, $fn) {
        $k = (string)$uid;
        if (!isset($a[$k])) $a[$k] = bkUserDefault($uid);
        $r = $fn($a[$k]);
        $a[$k]['updated_at'] = time();
        return $r;
    });
}

function bkLevelFromStolen($stolen) {
    $step = max(1, (int)bkVal('level_step', 500000));
    return min(1000, (int)floor(max(0, (float)$stolen) / $step) + 1);
}

function bkTop($n = 10) {
    $n = max(1, (int)$n);
    $top = []; $min = -INF;
    foreach (load('bank_users') as $u) {
        $b = (float)($u['bank_balance'] ?? 0);
        if (count($top) >= $n && $b <= $min) continue;
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

function bkPendSet($uid, $chat, $kind, $msgId) {
    mutate('bank_states', function (&$s) use ($uid, $chat, $kind, $msgId) {
        $s[bkPendKey($uid, $chat)] = ['kind' => $kind, 'msg' => (int)$msgId, 'at' => time()];
    });
}

function bkPendGet($uid, $chat) {
    $s = load('bank_states');
    $e = $s[bkPendKey($uid, $chat)] ?? null;
    if (!$e || time() - (int)($e['at'] ?? 0) > 300) return null; // ۵ دقیقه اعتبار
    return $e;
}

function bkPendClear($uid, $chat) {
    mutate('bank_states', function (&$s) use ($uid, $chat) { unset($s[bkPendKey($uid, $chat)]); });
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

    return bkT('card', [
        'name'         => h($name),
        'wallet'       => bkNum(gmPoints($uid)),
        'bank'         => bkNum($u['bank_balance'] ?? 0),
        'sec_status'   => $active ? 'ACTIVE' : 'INACTIVE',
        'protect_left' => $active ? bkLeftStr($protUntil) : '—',
        'level'        => (int)($u['bank_level'] ?? 1),
        'wins'         => bkNum($u['successful_hacks'] ?? 0),
        'stolen'       => bkNum($u['total_stolen'] ?? 0),
    ]);
}

function bkKb($uid) {
    return inlineKb([
        [bkBtn('btn_protect', [], 'bk_protect')],
        [bkBtn('btn_deposit', [], 'bk_dep'), bkBtn('btn_withdraw', [], 'bk_wd')],
    ]);
}

function bkShow($uid, $chatId, $name, $editMsgId = null) {
    $text = bkCardText($uid, $name);
    if ($editMsgId) { editMsg(BOT_TOKEN, $chatId, $editMsgId, $text, bkKb($uid)); return; }
    sendMsg(BOT_TOKEN, $chatId, $text, bkKb($uid));
}

// ============================================================
// 💎 واریز / برداشت — کیف‌پول همان الماسِ فروشگاه است
// ============================================================

function bkDeposit($uid, $name, $uname, $amount) {
    $amount = (float)$amount;
    if ($amount <= 0 || floor($amount) != $amount) return [false, bkT('ask_bad_num')];

    $wallet = gmPoints($uid);
    if ($amount > $wallet + 1e-9) return [false, bkT('dep_low_wallet', ['wallet' => bkNum($wallet)])];

    if (!gmAdd($uid, -$amount, $name, $uname)) {
        return [false, bkT('dep_low_wallet', ['wallet' => bkNum(gmPoints($uid))])];
    }
    // اگر همین‌جا خطا بخورد (دیسک/قفل)، همان الماس را برگردان — بدونِ
    // این جبران، الماس از کیف‌پول کم می‌شد ولی هیچ‌وقت به بانک نمی‌رسید.
    try {
        bkUserSet($uid, function (&$u) use ($amount, $name, $uname) {
            $u['bank_balance'] = (float)($u['bank_balance'] ?? 0) + $amount;
            if ($name !== '')  $u['name'] = $name;
            if ($uname !== '') $u['username'] = $uname;
        });
    } catch (Throwable $e) {
        gmAdd($uid, $amount, $name, $uname);
        error_log('[bank] واریز شکست خورد، الماس برگشت: ' . $e->getMessage());
        return [false, bkT('ask_bad_num')];
    }

    $u = bkUser($uid);
    return [true, bkT('dep_ok', [
        'amount' => bkNum($amount), 'wallet' => bkNum(gmPoints($uid)), 'bank' => bkNum($u['bank_balance'] ?? 0),
    ])];
}

function bkWithdraw($uid, $name, $uname, $amount) {
    $amount = (float)$amount;
    if ($amount <= 0 || floor($amount) != $amount) return [false, bkT('ask_bad_num')];

    $u = bkUser($uid) ?? bkUserDefault($uid);
    $bank = (float)($u['bank_balance'] ?? 0);
    $min  = max(0, (float)bkVal('min_withdraw', 50000));

    if ($bank < $min) return [false, bkT('wd_locked', ['bank' => bkNum($bank), 'min' => bkNum($min)])];
    if ($amount > $bank + 1e-9) return [false, bkT('wd_low_bank', ['bank' => bkNum($bank)])];

    $ok = bkUserSet($uid, function (&$u) use ($amount) {
        $bal = (float)($u['bank_balance'] ?? 0);
        if ($amount > $bal + 1e-9) return false;
        $u['bank_balance'] = $bal - $amount;
        return true;
    });
    if (!$ok) return [false, bkT('wd_low_bank', ['bank' => bkNum(bkUser($uid)['bank_balance'] ?? 0)])];

    gmAdd($uid, $amount, $name, $uname);

    $u2 = bkUser($uid);
    return [true, bkT('wd_ok', [
        'amount' => bkNum($amount), 'bank' => bkNum($u2['bank_balance'] ?? 0), 'wallet' => bkNum(gmPoints($uid)),
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
 * یک قفلِ اتمیکِ واحد رویِ کلِ فایل — هم چک‌های امنیتی (خودحفاظتی،
 * کول‌داون، شیلد) و هم جابه‌جاییِ الماس، همه داخلِ همان یک نوشتن.
 * بدونِ این، دو کلیکِ هم‌زمان می‌توانستند هر دو از چکِ کول‌داون رد
 * شوند و هدف را دوبار خالی کنند.
 */
function bkHack($hackerId, $hackerName, $hackerUname, $targetId) {
    $now = time();
    $out = ['err' => null];

    mutate('bank_users', function (&$a) use ($hackerId, $hackerName, $hackerUname, $targetId, $now, &$out) {
        $hk = (string)$hackerId; $tg = (string)$targetId;
        if (!isset($a[$hk])) $a[$hk] = bkUserDefault($hackerId);
        if (!isset($a[$tg])) $a[$tg] = bkUserDefault($targetId);

        $hacker = &$a[$hk]; $target = &$a[$tg];
        if ($hackerName !== '')  $hacker['name'] = $hackerName;
        if ($hackerUname !== '') $hacker['username'] = $hackerUname;

        if ((float)($hacker['hack_cooldown_until'] ?? 0) > $now) {
            $out['err'] = 'cooldown'; $out['left'] = (int)$hacker['hack_cooldown_until'] - $now; return;
        }
        $shield = max((int)($target['protection_until'] ?? 0), (int)($target['shield_until'] ?? 0));
        if ($shield > $now) { $out['err'] = 'protected'; $out['left'] = $shield - $now; return; }

        $bal = (float)($target['bank_balance'] ?? 0);
        if ($bal <= 0) { $out['err'] = 'empty'; return; }

        $roll = bkHackRoll($hacker, $target);
        $cooldown = max(60, (int)bkVal('hack_cooldown', 1200));
        $shieldSecs = max(0, (int)bkVal('shield_after', 300));
        $hacker['hack_cooldown_until'] = $now + $cooldown;
        $target['shield_until'] = $now + $shieldSecs;

        $out['tier'] = $roll['tier'];

        if (in_array($roll['tier'], ['jackpot', 'perfect', 'success', 'partial'], true)) {
            $amt = min($bal, floor($bal * $roll['pct'] / 100));
            $amt = max(0.0, $amt);
            $target['bank_balance'] = $bal - $amt;
            $hacker['bank_balance'] = (float)($hacker['bank_balance'] ?? 0) + $amt;
            $hacker['successful_hacks'] = (int)($hacker['successful_hacks'] ?? 0) + 1;
            $hacker['total_stolen']     = (float)($hacker['total_stolen'] ?? 0) + $amt;
            $target['total_lost']       = (float)($target['total_lost'] ?? 0) + $amt;
            $hacker['bank_level']       = bkLevelFromStolen($hacker['total_stolen']);
            $out['amount'] = $amt; $out['pct'] = $roll['pct'];
            $out['hackerBank'] = $hacker['bank_balance'];
        } elseif ($roll['tier'] === 'critfail') {
            $ownBal = (float)($hacker['bank_balance'] ?? 0);
            $fine = min($ownBal, floor($ownBal * $roll['fine_pct'] / 100));
            $hacker['bank_balance'] = $ownBal - $fine;
            $hacker['failed_hacks'] = (int)($hacker['failed_hacks'] ?? 0) + 1;
            $out['fine'] = $fine; $out['hackerBank'] = $hacker['bank_balance'];
        } else {
            $hacker['failed_hacks'] = (int)($hacker['failed_hacks'] ?? 0) + 1;
        }
    });

    return $out;
}

function bkHackCmd($uid, $chatId, $name, $uname, $replyTo, $msg) {
    $extra = $replyTo ? ['reply_to_message_id' => $replyTo] : [];
    $to = $msg['reply_to_message']['from'] ?? null;

    if (!$to || !empty($to['is_bot'])) {
        sendMsg(BOT_TOKEN, $chatId, bkT('hack_how', ['word' => bkVal('word_hack', 'هک')]), null, $extra);
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

function bkHandleAmount($raw, $uid, $chatId, $name, $uname, $pend) {
    $n = (float)str_replace([',', '٬', ' '], '', norm_fa_digits($raw));
    if ($n <= 0 || floor($n) != $n) { sendMsg(BOT_TOKEN, $chatId, bkT('ask_bad_num')); return true; }

    bkPendClear($uid, $chatId);
    [$ok, $msgText] = $pend['kind'] === 'withdraw'
        ? bkWithdraw($uid, $name, $uname, $n)
        : bkDeposit($uid, $name, $uname, $n);

    sendMsg(BOT_TOKEN, $chatId, $msgText);
    if (!empty($pend['msg'])) bkShow($uid, $chatId, $name, (int)$pend['msg']);
    return true;
}

function bkHandleText($text, $uid, $chatId, $name, $uname, $replyTo, $isPrivate, $msg = null) {
    if (!bkOn()) return false;
    if (!empty(bkVal('group_only', 1)) && $isPrivate) return false;

    $raw = trim((string)$text);
    if ($raw === '') return false;

    $pend = bkPendGet($uid, $chatId);
    if ($pend) return bkHandleAmount($raw, $uid, $chatId, $name, $uname, $pend);

    if (preg_match('/^\/bankleader(?:@\w+)?(?:\s|$)/i', $raw)) { sendMsg(BOT_TOKEN, $chatId, bkTopText()); return true; }

    $isBank = (bool)preg_match('/^\/bank(?:@\w+)?(?:\s|$)/i', $raw);
    if (!$isBank) {
        foreach (explode(',', (string)bkVal('word_bank', 'بانک')) as $w) {
            $w = trim($w);
            if ($w !== '' && mb_strtolower($raw) === mb_strtolower($w)) { $isBank = true; break; }
        }
    }
    if ($isBank) { bkShow($uid, $chatId, $name); return true; }

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
    if (!in_array($data, ['bk_protect', 'bk_dep', 'bk_wd'], true)) return false;
    if (!bkOn()) { answerCb(BOT_TOKEN, $cbId); return true; }

    $name  = (string)($from['first_name'] ?? '');
    $uname = (string)($from['username'] ?? '');

    // 🔒 دکمه‌های زیرِ کارتِ بانک فقط برای صاحبِ همان کارت — وگرنه هر
    // کسی می‌توانست از زیرِ کارتِ یکی دیگر بانکِ خودش را واریز/برداشت کند
    $u = bkUser($uid);
    // (پیامِ /bank همیشه برای همان کسی فرستاده می‌شود که آن را باز کرده،
    //  پس شناساییِ صاحب از رویِ خودِ uid کافی است — کارتِ شخصیِ هرکس فقط زیرِ
    //  پیامِ خودش می‌آید.)

    if ($data === 'bk_protect') {
        [$ok, $t] = bkProtect($uid);
        answerCb(BOT_TOKEN, $cbId, $ok ? '🛡' : '', !$ok);
        sendMsg(BOT_TOKEN, $chatId, $t);
        bkShow($uid, $chatId, $name, (int)$msgId);
        return true;
    }
    if ($data === 'bk_dep') {
        answerCb(BOT_TOKEN, $cbId);
        bkPendSet($uid, $chatId, 'deposit', $msgId);
        sendMsg(BOT_TOKEN, $chatId, bkT('ask_deposit'));
        return true;
    }
    if ($data === 'bk_wd') {
        answerCb(BOT_TOKEN, $cbId);
        bkPendSet($uid, $chatId, 'withdraw', $msgId);
        sendMsg(BOT_TOKEN, $chatId, bkT('ask_withdraw'));
        return true;
    }
    return false;
}
