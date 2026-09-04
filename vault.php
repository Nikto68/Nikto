<?php
/**
 * 🏦 بانکِ الماسی — واریز/برداشتِ سپرده‌ای، جدا از بازیِ هکِ bank.php
 *
 * bank.php («بانک»/«هک») یک بازیِ سرقت است: کیف‌پولِ زنده‌ی الماس
 * (gmPoints/gmAdd از games.php) همیشه قابلِ هک ماندن، هیچ‌چیزی قفل
 * نمی‌شود. این‌جا برعکس است: کاربر با «واریز» بخشی از الماسِ کیف‌پولش
 * را این‌طرف می‌آورد — دیگر نه قابلِ بازی‌کردن است نه (چون از
 * diamond_users بیرون رفته) قابلِ هک‌شدن با bank.php — و در عوض هر روز
 * سود می‌گیرد، تا با «برداشت» دوباره برگردد به کیف‌پولِ قابلِ‌خرج.
 *
 * ذخیره‌سازی: هر کاربر یک ردیفِ خودش در vault_users.sqlite (همان الگویِ
 * BEGIN IMMEDIATE که diamond.php/bank.php/games.php/mine.php/airdrop.php
 * همه استفاده می‌کنند) — سودِ سپرده هم دقیقا مثلِ airdrop.php («تسویه‌ی
 * موقعِ خواندن») حساب می‌شود: هر بار کارتِ بانک باز می‌شود، فاصله‌ی
 * زمانی از آخرین تسویه محاسبه و سود اضافه می‌شود.
 */

// ============================================================
// ⚙️ پیکربندی
// ============================================================

function vlDefaults() {
    return [
        'on'            => true,
        'group_only'    => 1,
        'word_bank'     => 'بانک الماسی',
        'word_account'  => 'حساب الماس,حساب الماسی',
        'daily_rate'    => 3.0,       // درصدِ سود در روز، رویِ موجودیِ قفل‌شده
        'min_deposit'   => 100,
        'min_withdraw'  => 100,
        'min_send'      => 100,
        'tx_log_max'    => 20,

        'icons' => ['btn_deposit' => '', 'btn_withdraw' => '', 'btn_send' => '', 'btn_history' => '',
                    'btn_open_bank' => '', 'btn_back' => ''],
        'btns'  => [
            'btn_deposit'    => ['color' => 'success'],
            'btn_withdraw'   => ['color' => 'primary'],
            'btn_send'       => ['color' => 'none'],
            'btn_history'    => ['color' => 'none'],
            'btn_open_bank'  => ['color' => 'primary'],
            'btn_back'       => ['color' => 'none'],
        ],

        'texts' => [
            'btn_deposit'   => '⬇️ واریز به بانک',
            'btn_withdraw'  => '⬆️ برداشت از بانک',
            'btn_send'      => '💳 کارت به کارت الماسی',
            'btn_history'   => '🧾 تراکنش‌ها',
            'btn_open_bank' => '🏦 بانک',
            'btn_back'      => '🔙 برگشت',

            'account' => "💎 <b>حسابِ الماس</b>\n\n" .
                         "👤 کاربر: {name}\n🆔 آیدی: <code>{uid}</code>\n\n" .
                         "✨ الماسِ قابلِ‌خرج: <b>{points}</b>\n⭐️ سطح: <b>{level}</b>\n\n" .
                         "🏦 موجودیِ بانک: <b>{locked}</b>",

            'card' => "🏦 <b>بانکِ الماسی</b>\n\n" .
                      "👤 {name}\n💳 شماره حساب: <code>{card_no}</code>\n\n" .
                      "🔒 موجودیِ بانک: <b>{locked}</b>\n" .
                      "✨ الماسِ قابلِ‌خرج: <b>{wallet}</b>\n\n" .
                      "📈 سودِ بانکی: <b>{rate}٪</b> در روز\n{interest_line}\n" .
                      "━━━━━━━━━━━━━━",
            'interest_line' => "💰 آخرین سود: <b>{amount}</b> — {when}",
            'interest_none' => "💤 هنوز سودی واریز نشده.",

            'ask_deposit'  => "⬇️ چند الماس واریزِ بانک کنیم؟\n\n✨ الماسِ قابلِ‌خرج: <b>{wallet}</b>",
            'ask_withdraw' => "⬆️ چند الماس از بانک برداشت کنیم؟\n\n🏦 موجودیِ بانک: <b>{locked}</b>",
            'ask_send_amt' => "💳 چند الماس کارت‌به‌کارت کنیم؟\n\n🏦 موجودیِ بانک: <b>{locked}</b>",
            'ask_send_id'  => "👤 آیدیِ عددی یا یوزرنیمِ گیرنده (@username) را بفرست.\n\n<i>گیرنده باید قبلا با ربات پیام داده باشد.</i>",
            'ask_bad_num'  => "❌ یک عددِ صحیح و بزرگ‌تر از صفر بفرستید.",
            'ask_badid'    => "❌ این آیدی/یوزرنیم معتبر نبود.",
            'ask_expired'  => "⌛️ این درخواست منقضی شده. دوباره از «بانک الماسی» شروع کنید.",
            'not_your_card'=> "🔒 این کارتِ بانکِ شما نیست.",

            'off' => "🔒 بانکِ الماسی فعلا خاموش است.",

            'deposit_low_min'    => "❌ حداقلِ واریز <b>{min}</b> الماس است.",
            'deposit_low_wallet' => "❌ الماسِ قابلِ‌خرجت کافی نیست.\n✨ موجودی: <b>{wallet}</b>",
            'deposit_ok'         => "✅ <b>{amount}</b> الماس به بانک واریز شد.\n🏦 موجودیِ بانک: <b>{locked}</b>",

            'withdraw_low_min'    => "❌ حداقلِ برداشت <b>{min}</b> الماس است.",
            'withdraw_low_locked' => "❌ موجودیِ بانک کافی نیست.\n🏦 موجودیِ بانک: <b>{locked}</b>",
            'withdraw_ok'         => "✅ <b>{amount}</b> الماس از بانک برداشت شد.\n✨ الماسِ قابلِ‌خرج: <b>{wallet}</b>",

            'send_self'       => "😄 نمی‌تونی برایِ خودت بفرستی.",
            'send_no_target'  => "❌ این آیدی برایِ ربات شناخته‌شده نیست — گیرنده باید قبلا با ربات پیام داده باشد.",
            'send_low_min'    => "❌ حداقلِ کارت‌به‌کارت <b>{min}</b> الماس است.",
            'send_low_locked' => "❌ موجودیِ بانک کافی نیست.\n🏦 موجودیِ بانک: <b>{locked}</b>",
            'send_confirm'    => "💳 <b>تاییدِ کارت‌به‌کارت</b>\n\n💎 مقدار: <b>{amount}</b>\n👤 گیرنده: {to_tag}\n\nبرایِ تاییدِ نهایی بزن:",
            'btn_send_confirm'=> '✅ تاییدِ انتقال',
            'send_ok'         => "✅ <b>{amount}</b> الماس کارت‌به‌کارت شد.\n👤 گیرنده: {to_tag}\n🏦 موجودیِ بانک: <b>{locked}</b>",

            'tx_head' => "🧾 <b>تراکنش‌های اخیرِ بانک</b>\n",
            'tx_none' => "هنوز تراکنشی ثبت نشده.",
            'tx_row_deposit'  => "⬇️ واریز — <b>{amount}</b> — {when}",
            'tx_row_withdraw' => "⬆️ برداشت — <b>{amount}</b> — {when}",
            'tx_row_interest' => "💰 سودِ بانکی — <b>{amount}</b> — {when}",
            'tx_row_send_out' => "💳 ارسال به {who} — <b>{amount}</b> — {when}",
            'tx_row_send_in'  => "💳 دریافت از {who} — <b>{amount}</b> — {when}",
        ],
    ];
}

function vlCfg() {
    $c = cfg()['vault'] ?? null;
    return is_array($c) ? array_replace_recursive(vlDefaults(), $c) : vlDefaults();
}

function vlSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!is_array($c['vault'] ?? null)) $c['vault'] = vlDefaults();
        $fn($c['vault']);
    });
}

function vlVal($path, $default = null) {
    $v = vlCfg();
    foreach (explode('.', $path) as $seg) {
        if (!is_array($v) || !array_key_exists($seg, $v)) return $default;
        $v = $v[$seg];
    }
    return $v;
}

function vlOn() { return !empty(vlVal('on')); }

function vlIsButtonKey($slug) {
    return in_array($slug, ['btn_deposit', 'btn_withdraw', 'btn_send', 'btn_history', 'btn_open_bank',
                             'btn_back', 'btn_send_confirm'], true);
}

function vlT($slug, $vars = []) {
    $t = (string)vlVal('texts.' . $slug, vlDefaults()['texts'][$slug] ?? $slug);
    if (vlIsButtonKey($slug)) $t = strip_tags($t);
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}

/** دکمه‌ی شیشه‌ای با متن/رنگ/ایموجیِ پریمیومِ قابل‌ویرایش — همان الگویِ bkBtn() */
function vlBtn($key, $vars, $data) {
    $b = ['text' => vlT($key, $vars), 'callback_data' => $data];
    $color = (string)vlVal('btns.' . $key . '.color', '');
    if (function_exists('isStyle') && isStyle($color)) $b['style'] = $color;
    $ic = trim((string)vlVal('icons.' . $key, ''));
    if ($ic !== '') $b['icon_custom_emoji_id'] = $ic;
    return $b;
}

function vlNum($n) { return number_format((float)$n, 0, '.', ','); }

function vlUserTag($id, $name, $uname) {
    $label = trim((string)$name) !== '' ? (string)$name : ('#' . (int)$id);
    $u = trim((string)$uname);
    return h($label) . ($u !== '' ? ' (@' . h($u) . ')' : '');
}

// ============================================================
// 🗃 داده — SQLite، هر کاربر یک ردیف
// ============================================================

function vaultDbPath() { return DATA_DIR . '/vault_users.sqlite'; }

function vaultDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3')) return null;

    $path = vaultDbPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    try {
        $db = new SQLite3($path);
    } catch (Throwable $e) {
        error_log('[vault] vault_users.sqlite باز نشد: ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS vault_users (
        id INTEGER PRIMARY KEY, locked REAL NOT NULL DEFAULT 0, data TEXT NOT NULL
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_vault_locked ON vault_users(locked DESC)');
    return $db;
}

function vlUserDefault($uid) {
    return [
        'id' => (int)$uid, 'name' => '', 'username' => '', 'card_no' => '',
        'last_settle' => time(), 'tx' => [], 'created_at' => time(),
    ];
}

function vlUser($uid) {
    $db = vaultDb();
    if (!$db) return null;
    $stmt = $db->prepare('SELECT locked, data FROM vault_users WHERE id = :id');
    $stmt->bindValue(':id', (int)$uid, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $d = json_decode($row['data'], true);
    $d = is_array($d) ? $d : vlUserDefault($uid);
    $d['locked'] = (float)$row['locked'];
    return $d;
}

/** تغییرِ اتمیکِ یک کاربر — قفل فقط رویِ همین یک ردیف (BEGIN IMMEDIATE) */
function vlUserSet($uid, callable $fn) {
    $db = vaultDb();
    if (!$db) return null;
    $id = (int)$uid;

    $db->exec('BEGIN IMMEDIATE');
    try {
        $stmt = $db->prepare('SELECT locked, data FROM vault_users WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row) {
            $u = json_decode($row['data'], true);
            $u = is_array($u) ? $u : vlUserDefault($id);
            $u['locked'] = (float)$row['locked'];
        } else {
            $u = vlUserDefault($id);
            $u['locked'] = 0.0;
        }

        $result = $fn($u);

        $locked = (float)($u['locked'] ?? 0);
        unset($u['locked']); // خودِ ستونِ locked جدا نوشته می‌شود، داخلِ JSON تکرار نشود
        $up = $db->prepare('INSERT OR REPLACE INTO vault_users (id, locked, data) VALUES (:id, :locked, :data)');
        $up->bindValue(':id', $id, SQLITE3_INTEGER);
        $up->bindValue(':locked', $locked, SQLITE3_FLOAT);
        $up->bindValue(':data', json_encode($u, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        $up->execute();
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        error_log('[vault] vlUserSet خطا: ' . $e->getMessage());
        return null;
    }
    return $result;
}

/** شماره‌حسابِ ۱۲‌رقمیِ ثابتِ هر کاربر — یک‌بار ساخته می‌شود، بعد همیشه همان است */
function vlEnsureCardNo(&$u) {
    if (!empty($u['card_no'])) return;
    $u['card_no'] = (string)random_int(100000000000, 999999999999);
}

function vlTxAppend(&$u, $kind, $amount, $extra = []) {
    $max = max(1, (int)vlVal('tx_log_max', 20));
    $log = (array)($u['tx'] ?? []);
    array_unshift($log, array_merge(['t' => time(), 'kind' => $kind, 'amount' => (float)$amount], $extra));
    $u['tx'] = array_slice($log, 0, $max);
}

/**
 * تسویه‌ی سود — دقیقا الگویِ airdrop.php: فاصله‌ی زمانی از آخرین تسویه
 * حساب و به موجودیِ قفل‌شده اضافه می‌شود؛ همیشه داخلِ همان قفلِ
 * vlUserSet که خودِ عملیات (واریز/برداشت/نمایش) رویش کار می‌کند.
 */
function vlSettleInterest(&$u) {
    $now = time();
    $last = (int)($u['last_settle'] ?? $now);
    $elapsed = max(0, $now - $last);
    if ($elapsed > 0) {
        $locked = (float)($u['locked'] ?? 0);
        if ($locked > 0) {
            $rate = max(0.0, (float)vlVal('daily_rate', 3.0)) / 100.0;
            $earned = round($locked * $rate * ($elapsed / 86400.0), 4);
            if ($earned >= 0.01) {
                $u['locked'] = round($locked + $earned, 4);
                vlTxAppend($u, 'interest', $earned);
            }
        }
        $u['last_settle'] = $now;
    }
}

/** تسویه + به‌روزرسانیِ نام، بعد وضعیتِ تازه را برمی‌گرداند — نقطه‌ی ورودِ همه‌ی نمایش‌ها */
function vlTouch($uid, $name = '', $uname = '') {
    return vlUserSet($uid, function (&$u) use ($name, $uname) {
        vlEnsureCardNo($u);
        vlSettleInterest($u);
        if ($name !== '')  $u['name'] = mb_substr($name, 0, 40);
        if ($uname !== '') $u['username'] = mb_substr(ltrim($uname, '@'), 0, 40);
        return $u;
    });
}

// ============================================================
// 💰 واریز / برداشت / کارت‌به‌کارت
// ============================================================

/** واریز: کیف‌پولِ الماس (diamond.php) کم، موجودیِ بانک زیاد */
function vlDeposit($uid, $amount, $name, $uname) {
    $amount = (float)$amount;
    $min = max(0, (float)vlVal('min_deposit', 100));
    if ($amount <= 0 || floor($amount) != $amount) return [false, vlT('ask_bad_num')];
    if ($amount < $min) return [false, vlT('deposit_low_min', ['min' => vlNum($min)])];

    $wallet = function_exists('gmPoints') ? gmPoints($uid) : 0.0;
    if ($amount > $wallet + 1e-9) return [false, vlT('deposit_low_wallet', ['wallet' => vlNum($wallet)])];

    if (!function_exists('gmAdd') || !gmAdd($uid, -$amount, $name, $uname)) {
        return [false, vlT('deposit_low_wallet', ['wallet' => vlNum(gmPoints($uid))])];
    }

    $locked = 0.0;
    vlTouch($uid, $name, $uname);
    vlUserSet($uid, function (&$u) use ($amount, &$locked) {
        $u['locked'] = round((float)($u['locked'] ?? 0) + $amount, 4);
        vlTxAppend($u, 'deposit', $amount);
        $locked = $u['locked'];
    });

    return [true, vlT('deposit_ok', ['amount' => vlNum($amount), 'locked' => vlNum($locked)])];
}

/** برداشت: موجودیِ بانک کم، کیف‌پولِ الماس زیاد */
function vlWithdraw($uid, $amount, $name, $uname) {
    $amount = (float)$amount;
    $min = max(0, (float)vlVal('min_withdraw', 100));
    if ($amount <= 0 || floor($amount) != $amount) return [false, vlT('ask_bad_num')];
    if ($amount < $min) return [false, vlT('withdraw_low_min', ['min' => vlNum($min)])];

    vlTouch($uid, $name, $uname);
    $ok = false; $locked = 0.0;
    vlUserSet($uid, function (&$u) use ($amount, &$ok, &$locked) {
        $cur = (float)($u['locked'] ?? 0);
        if ($amount > $cur + 1e-9) { $locked = $cur; return; }
        $u['locked'] = round($cur - $amount, 4);
        vlTxAppend($u, 'withdraw', $amount);
        $ok = true; $locked = $u['locked'];
    });
    if (!$ok) return [false, vlT('withdraw_low_locked', ['locked' => vlNum($locked)])];

    if (function_exists('gmAdd')) gmAdd($uid, $amount, $name, $uname);
    $wallet = function_exists('gmPoints') ? gmPoints($uid) : 0.0;
    return [true, vlT('withdraw_ok', ['amount' => vlNum($amount), 'wallet' => vlNum($wallet)])];
}

/**
 * کارت‌به‌کارت: بینِ دو موجودیِ بانک (نه کیف‌پولِ قابل‌خرج). اگر نوشتنِ
 * سمتِ گیرنده هر دلیلی نگرفت، الماس به فرستنده برمی‌گردد — درست مثلِ
 * bkSendDiamond در bank.php.
 */
function vlSend($fromUid, $toUid, $fromName, $fromUname, $amount) {
    $amount = (float)$amount;
    $min = max(0, (float)vlVal('min_send', 100));
    if ($amount <= 0 || floor($amount) != $amount) return [false, vlT('ask_bad_num')];
    if ((int)$toUid === (int)$fromUid) return [false, vlT('send_self')];
    if ($amount < $min) return [false, vlT('send_low_min', ['min' => vlNum($min)])];

    vlTouch($fromUid, $fromName, $fromUname);
    $ok = false; $locked = 0.0;
    vlUserSet($fromUid, function (&$u) use ($amount, &$ok, &$locked) {
        $cur = (float)($u['locked'] ?? 0);
        if ($amount > $cur + 1e-9) { $locked = $cur; return; }
        $u['locked'] = round($cur - $amount, 4);
        $ok = true; $locked = $u['locked'];
    });
    if (!$ok) return [false, vlT('send_low_locked', ['locked' => vlNum($locked)])];

    vlTouch($toUid);
    $recvOk = false;
    vlUserSet($toUid, function (&$u) use ($amount, &$recvOk) {
        $u['locked'] = round((float)($u['locked'] ?? 0) + $amount, 4);
        $recvOk = true;
    });
    if (!$recvOk) {
        vlUserSet($fromUid, function (&$u) use ($amount) { $u['locked'] = round((float)($u['locked'] ?? 0) + $amount, 4); });
        return [false, vlT('ask_bad_num')];
    }

    $toUser = function_exists('getUser') ? getUser($toUid) : null;
    $toTag  = vlUserTag($toUid, $toUser['first_name'] ?? '', $toUser['username'] ?? '');
    $fromTag = vlUserTag($fromUid, $fromName, $fromUname);

    vlUserSet($fromUid, function (&$u) use ($amount, $toTag) { vlTxAppend($u, 'send_out', $amount, ['who' => $toTag]); });
    vlUserSet($toUid,   function (&$u) use ($amount, $fromTag) { vlTxAppend($u, 'send_in', $amount, ['who' => $fromTag]); });

    return [true, vlT('send_ok', ['amount' => vlNum($amount), 'to_tag' => $toTag, 'locked' => vlNum($locked)])];
}

function vlResolveTarget($raw) {
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

function vlLooksLikeAmount($raw) {
    $n = trim(str_replace([',', '٬', ' '], '', norm_fa_digits(trim((string)$raw))));
    return $n !== '' && preg_match('/^\d+(\.\d+)?$/', $n) === 1;
}

function vlLooksLikeTarget($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return false;
    if ($raw[0] === '@') $raw = substr($raw, 1);
    if (preg_match('/^[A-Za-z][A-Za-z0-9_]{2,31}$/', $raw) === 1) return true;
    $digits = trim(norm_fa_digits($raw));
    return $digits !== '' && preg_match('/^\d+$/', $digits) === 1;
}

// ============================================================
// 📄 متن‌ها/کیبورد
// ============================================================

function vlLastInterestLine($u) {
    foreach ((array)($u['tx'] ?? []) as $row) {
        if (($row['kind'] ?? '') === 'interest') {
            return vlT('interest_line', ['amount' => vlNum($row['amount'] ?? 0), 'when' => vlAgo((int)($row['t'] ?? 0))]);
        }
    }
    return vlT('interest_none');
}

function vlAgo($t) {
    if (function_exists('agoTxt')) return agoTxt($t);
    $s = time() - (int)$t;
    if ($s < 60) return 'همین الان';
    if ($s < 3600) return intdiv($s, 60) . ' دقیقه پیش';
    if ($s < 86400) return intdiv($s, 3600) . ' ساعت پیش';
    return intdiv($s, 86400) . ' روز پیش';
}

function vlAccountText($uid, $name) {
    vlTouch($uid, $name);
    $u = vlUser($uid) ?? vlUserDefault($uid);
    $points = function_exists('gmPoints') ? gmPoints($uid) : 0.0;
    $level  = function_exists('dmLevel') ? dmLevel($points) : 1;
    return vlT('account', [
        'name'   => h($u['name'] ?: $name),
        'uid'    => (int)$uid,
        'points' => vlNum($points),
        'level'  => $level,
        'locked' => vlNum($u['locked'] ?? 0),
    ]);
}

function vlAccountKb($uid) {
    return inlineKb([[vlBtn('btn_open_bank', [], 'vl_bank_' . (int)$uid)]]);
}

function vlBankText($uid, $name) {
    vlTouch($uid, $name);
    $u = vlUser($uid) ?? vlUserDefault($uid);
    $wallet = function_exists('gmPoints') ? gmPoints($uid) : 0.0;
    return vlT('card', [
        'name'    => h($u['name'] ?: $name),
        'card_no' => (string)($u['card_no'] ?? ''),
        'locked'  => vlNum($u['locked'] ?? 0),
        'wallet'  => vlNum($wallet),
        'rate'    => rtrim(rtrim(number_format((float)vlVal('daily_rate', 3.0), 2), '0'), '.'),
        'interest_line' => vlLastInterestLine($u),
    ]);
}

function vlBankKb($uid) {
    $uid = (int)$uid;
    return inlineKb([
        [vlBtn('btn_send', [], 'vl_send_' . $uid)],
        [vlBtn('btn_deposit', [], 'vl_dep_' . $uid), vlBtn('btn_withdraw', [], 'vl_wd_' . $uid)],
        [vlBtn('btn_history', [], 'vl_tx_' . $uid)],
    ]);
}

function vlTxText($uid) {
    $u = vlUser($uid) ?? vlUserDefault($uid);
    $rows = (array)($u['tx'] ?? []);
    if (!$rows) return vlT('tx_head') . "\n" . vlT('tx_none');
    $t = vlT('tx_head') . "\n";
    $map = ['deposit' => 'tx_row_deposit', 'withdraw' => 'tx_row_withdraw', 'interest' => 'tx_row_interest',
            'send_out' => 'tx_row_send_out', 'send_in' => 'tx_row_send_in'];
    $n = 0;
    foreach ($rows as $r) {
        $kind = (string)($r['kind'] ?? '');
        $slug = $map[$kind] ?? null;
        if (!$slug) continue;
        $t .= vlT($slug, [
            'amount' => vlNum($r['amount'] ?? 0),
            'when'   => vlAgo((int)($r['t'] ?? 0)),
            'who'    => $r['who'] ?? '',
        ]) . "\n";
        if (++$n >= 15) break;
    }
    return $t;
}

/** فقط دکمه‌ی برگشت — برایِ نمایشِ تاریخچه */
function vlBackKb($uid, $to = 'bank') {
    $uid = (int)$uid;
    $data = $to === 'account' ? 'vl_acct_' . $uid : 'vl_bank_' . $uid;
    return inlineKb([[vlBtn('btn_back', [], $data)]]);
}

// ============================================================
// ⏳ درخواستِ در انتظار — همان الگویِ bkPend*
// ============================================================

function vlPendKey($uid, $chat) { return $uid . '_' . $chat; }

function vlPendSet($uid, $chat, $kind, $msgId, $data = []) {
    mutate('vault_states', function (&$s) use ($uid, $chat, $kind, $msgId, $data) {
        $s[vlPendKey($uid, $chat)] = ['kind' => $kind, 'msg' => (int)$msgId, 'at' => time(), 'data' => $data];
    });
}

function vlPendGet($uid, $chat) {
    $s = load('vault_states');
    $e = $s[vlPendKey($uid, $chat)] ?? null;
    if (count($s) > 30 && random_int(1, 50) === 1) vlPendSweep(100);
    if (!$e || time() - (int)($e['at'] ?? 0) > 300) return null;
    return $e;
}

function vlPendClear($uid, $chat) {
    mutate('vault_states', function (&$s) use ($uid, $chat) { unset($s[vlPendKey($uid, $chat)]); });
}

function vlPendSweep($limit = 200) {
    $now = time(); $removed = 0;
    mutate('vault_states', function (&$s) use ($now, $limit, &$removed) {
        foreach (array_keys($s) as $k) {
            if ($removed >= $limit) break;
            if ($now - (int)($s[$k]['at'] ?? 0) > 300) { unset($s[$k]); $removed++; }
        }
    });
    return $removed;
}

// ============================================================
// ✍️ متن — کلمه‌های باز کردن + جواب‌های در-انتظار
// ============================================================

function vlHandleText($text, $uid, $chatId, $name, $uname, $replyTo, $isPrivate, $msg = null) {
    if (!vlOn()) return false;
    if (!empty(vlVal('group_only', 1)) && $isPrivate) return false;

    $raw = trim((string)$text);
    if ($raw === '') return false;

    $pend = vlPendGet($uid, $chatId);
    if ($pend) {
        $kind = $pend['kind'] ?? '';
        $looksLikeAnswer = false;
        if ($kind === 'send_id') $looksLikeAnswer = vlLooksLikeTarget($raw);
        elseif (in_array($kind, ['deposit', 'withdraw', 'send_amt'], true)) $looksLikeAnswer = vlLooksLikeAmount($raw);
        if ($looksLikeAnswer) return vlHandlePending($raw, $uid, $chatId, $name, $uname, $pend);
    }

    foreach (explode(',', (string)vlVal('word_account', 'حساب الماس')) as $w) {
        $w = trim($w);
        if ($w !== '' && mb_strtolower($raw) === mb_strtolower($w)) {
            $extra = $replyTo ? ['reply_to_message_id' => $replyTo] : [];
            sendMsg(BOT_TOKEN, $chatId, vlAccountText($uid, $name), vlAccountKb($uid), $extra);
            return true;
        }
    }
    foreach (explode(',', (string)vlVal('word_bank', 'بانک الماسی')) as $w) {
        $w = trim($w);
        if ($w !== '' && mb_strtolower($raw) === mb_strtolower($w)) {
            $extra = $replyTo ? ['reply_to_message_id' => $replyTo] : [];
            sendMsg(BOT_TOKEN, $chatId, vlBankText($uid, $name), vlBankKb($uid), $extra);
            return true;
        }
    }
    return false;
}

function vlHandlePending($raw, $uid, $chatId, $name, $uname, $pend) {
    $kind = $pend['kind'];
    $msgId = $pend['msg'];

    if ($kind === 'deposit') {
        vlPendClear($uid, $chatId);
        $amt = (float)str_replace([',', '٬', ' '], '', norm_fa_digits($raw));
        [$ok, $t] = vlDeposit($uid, $amt, $name, $uname);
        sendMsg(BOT_TOKEN, $chatId, $t);
        sendMsg(BOT_TOKEN, $chatId, vlBankText($uid, $name), vlBankKb($uid));
        return true;
    }
    if ($kind === 'withdraw') {
        vlPendClear($uid, $chatId);
        $amt = (float)str_replace([',', '٬', ' '], '', norm_fa_digits($raw));
        [$ok, $t] = vlWithdraw($uid, $amt, $name, $uname);
        sendMsg(BOT_TOKEN, $chatId, $t);
        sendMsg(BOT_TOKEN, $chatId, vlBankText($uid, $name), vlBankKb($uid));
        return true;
    }
    if ($kind === 'send_amt') {
        $amt = (float)str_replace([',', '٬', ' '], '', norm_fa_digits($raw));
        $min = max(0, (float)vlVal('min_send', 100));
        if ($amt <= 0 || floor($amt) != $amt) { vlAskEdit($chatId, $msgId, vlT('ask_bad_num'), [], $uid); return true; }
        if ($amt < $min) { vlAskEdit($chatId, $msgId, vlT('send_low_min', ['min' => vlNum($min)]), [], $uid); return true; }
        vlPendSet($uid, $chatId, 'send_id', $msgId, ['amount' => $amt]);
        vlAskEdit($chatId, $msgId, vlT('ask_send_id'), [], $uid);
        return true;
    }
    if ($kind === 'send_id') {
        $toId = vlResolveTarget($raw);
        if (!$toId) { vlAskEdit($chatId, $msgId, vlT('ask_badid'), [], $uid); return true; }
        if ($toId === (int)$uid) { vlAskEdit($chatId, $msgId, vlT('send_self'), [], $uid); return true; }
        $toUser = function_exists('getUser') ? getUser($toId) : null;
        if (!$toUser) { vlAskEdit($chatId, $msgId, vlT('send_no_target'), [], $uid); return true; }
        $amount = (float)($pend['data']['amount'] ?? 0);
        $toTag  = vlUserTag($toId, $toUser['first_name'] ?? '', $toUser['username'] ?? '');
        vlPendSet($uid, $chatId, 'send_confirm', $msgId, ['amount' => $amount, 'to' => $toId]);
        vlAskEdit($chatId, $msgId, vlT('send_confirm', ['amount' => vlNum($amount), 'to_tag' => $toTag]),
            [[vlBtn('btn_send_confirm', [], 'vl_sendok_' . (int)$uid)]], $uid);
        return true;
    }
    return false;
}

function vlAskEdit($chatId, $pendMsgId, $text, array $extraRows = [], $uid = 0) {
    $rows = $extraRows;
    $rows[] = [vlBtn('btn_back', [], 'vl_cancelflow_' . (int)$uid)];
    $kb = inlineKb($rows);
    if ($pendMsgId) { editMsg(BOT_TOKEN, $chatId, (int)$pendMsgId, $text, $kb); return; }
    sendMsg(BOT_TOKEN, $chatId, $text, $kb);
}

// ============================================================
// 🔘 کال‌بک‌ها
// ============================================================

function vlCallback($data, $uid, $chatId, $msgId, $cbId, $from = []) {
    if (!preg_match('/^vl_(acct|bank|dep|wd|send|tx|cancelflow|sendok)_(\d+)$/', (string)$data, $m)) return false;
    $action  = $m[1];
    $ownerId = (int)$m[2];
    if (!vlOn()) { answerCb(BOT_TOKEN, $cbId); return true; }
    if ($ownerId !== (int)$uid) { answerCb(BOT_TOKEN, $cbId, vlT('not_your_card'), true); return true; }

    $name  = (string)($from['first_name'] ?? '');
    $uname = (string)($from['username'] ?? '');

    if ($action === 'acct') {
        answerCb(BOT_TOKEN, $cbId);
        vlPendClear($uid, $chatId);
        editMsg(BOT_TOKEN, $chatId, $msgId, vlAccountText($uid, $name), vlAccountKb($uid));
        return true;
    }
    if ($action === 'bank') {
        answerCb(BOT_TOKEN, $cbId);
        editMsg(BOT_TOKEN, $chatId, $msgId, vlBankText($uid, $name), vlBankKb($uid));
        return true;
    }
    if ($action === 'dep') {
        answerCb(BOT_TOKEN, $cbId);
        $wallet = function_exists('gmPoints') ? gmPoints($uid) : 0.0;
        vlPendSet($uid, $chatId, 'deposit', $msgId);
        vlAskEdit($chatId, $msgId, vlT('ask_deposit', ['wallet' => vlNum($wallet)]), [], $uid);
        return true;
    }
    if ($action === 'wd') {
        answerCb(BOT_TOKEN, $cbId);
        $u = vlUser($uid) ?? vlUserDefault($uid);
        vlPendSet($uid, $chatId, 'withdraw', $msgId);
        vlAskEdit($chatId, $msgId, vlT('ask_withdraw', ['locked' => vlNum($u['locked'] ?? 0)]), [], $uid);
        return true;
    }
    if ($action === 'send') {
        answerCb(BOT_TOKEN, $cbId);
        $u = vlUser($uid) ?? vlUserDefault($uid);
        vlPendSet($uid, $chatId, 'send_amt', $msgId);
        vlAskEdit($chatId, $msgId, vlT('ask_send_amt', ['locked' => vlNum($u['locked'] ?? 0)]), [], $uid);
        return true;
    }
    if ($action === 'tx') {
        answerCb(BOT_TOKEN, $cbId);
        editMsg(BOT_TOKEN, $chatId, $msgId, vlTxText($uid), vlBackKb($uid));
        return true;
    }
    if ($action === 'cancelflow') {
        answerCb(BOT_TOKEN, $cbId);
        vlPendClear($uid, $chatId);
        editMsg(BOT_TOKEN, $chatId, $msgId, vlBankText($uid, $name), vlBankKb($uid));
        return true;
    }
    if ($action === 'sendok') {
        $pend = vlPendGet($uid, $chatId);
        if (!$pend || $pend['kind'] !== 'send_confirm') {
            answerCb(BOT_TOKEN, $cbId, vlT('ask_expired'), true);
            return true;
        }
        vlPendClear($uid, $chatId);
        $amount = (float)($pend['data']['amount'] ?? 0);
        $toId   = (int)($pend['data']['to'] ?? 0);
        [$ok, $t] = vlSend($uid, $toId, $name, $uname, $amount);
        answerCb(BOT_TOKEN, $cbId, $ok ? '💳' : '', !$ok);
        sendMsg(BOT_TOKEN, $chatId, $t);
        editMsg(BOT_TOKEN, $chatId, $msgId, vlBankText($uid, $name), vlBankKb($uid));
        return true;
    }
    return false;
}

// ============================================================
// ✏️ ویرایشگرِ داخلِ ربات — /panel ← 💰 بانکِ الماسی
//
// عمدا کوچک: فقط روشن/خاموش + نرخِ سود + کلمه‌های باز کردن. رنگ/آیکون/
// متن‌هایِ تک‌تکِ پیام‌ها (که bank.php/games.php دارند) این‌جا نیست —
// همان الگو، هرکدام لازم بود بعدا همین‌طوری اضافه می‌شود.
// ============================================================

function vlAdminHome($chatId, $msgId = null) {
    $c = vlCfg();
    $t  = "💰 <b>بانکِ الماسی</b>\n\n";
    $t .= 'وضعیت: ' . (vlOn() ? '✅ روشن' : '❌ خاموش') . "\n\n";
    $t .= '📈 سودِ روزانه: <b>' . h((string)$c['daily_rate']) . "٪</b>\n";
    $t .= 'کلمه‌ی بازکردنِ بانک: <code>' . h($c['word_bank']) . "</code>\n";
    $t .= 'کلمه‌ی بازکردنِ حساب: <code>' . h($c['word_account']) . "</code>\n";

    $rows = [
        [btnCb(vlOn() ? '✅ روشن' : '❌ خاموش', 'vlax', 'admin')],
        [btnCb('📈 سودِ روزانه (٪)', 'vlarate', 'admin')],
        [btnCb('🗣 کلمه‌ی بانک', 'vlaw_bank', 'admin'), btnCb('🗣 کلمه‌ی حساب', 'vlaw_acct', 'admin')],
        [btnCb(UT('back'), 'adm_home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function vlAdminCallback($data, $chatId, $msgId, $cbId) {
    if (!str_starts_with((string)$data, 'vl')) return false;

    if ($data === 'vl_home') { answerCb(BOT_TOKEN, $cbId); vlAdminHome($chatId, $msgId); return true; }
    if ($data === 'vlax') {
        vlSet(function (&$c) { $c['on'] = empty($c['on']); });
        answerCb(BOT_TOKEN, $cbId, '✅'); vlAdminHome($chatId, $msgId); return true;
    }
    if ($data === 'vlarate') {
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, 'vl_rate', []);
        sendMsg(BOT_TOKEN, $chatId, '📈 سودِ روزانه چند درصد باشد؟ (مثلا 3 یا 2.5)',
            inlineKb([[btnCb('انصراف', 'vl_home', 'cancel')]]));
        return true;
    }
    $words = ['vlaw_bank' => ['vl_word_bank', 'word_bank', 'کلمه‌های بازکردنِ بانک'],
              'vlaw_acct' => ['vl_word_acct', 'word_account', 'کلمه‌های بازکردنِ حساب']];
    if (isset($words[$data])) {
        [$act, $path, $label] = $words[$data];
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, $act, []);
        $cur = (string)vlVal($path, '');
        sendMsg(BOT_TOKEN, $chatId,
            "🗣 <b>" . h($label) . "</b> را بفرست — چند کلمه را با ویرگول جدا کن.\n\nالان: <code>" . h($cur) . "</code>",
            inlineKb([[btnCb('انصراف', 'vl_home', 'cancel')]]));
        return true;
    }
    return false;
}

function vlStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'vl_')) return false;
    if (!isAdmin($uid)) return false;

    $text = trim((string)($msg['text'] ?? ''));
    $back = inlineKb([[btnCb('💰 بانکِ الماسی', 'vl_home', 'admin')]]);
    $done = function ($m = '✅ ذخیره شد.') use ($uid, $chatId, $back) {
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $m, $back);
        return true;
    };

    if ($action === 'vl_rate') {
        $n = (float)str_replace([',', '٬'], '', norm_fa_digits($text));
        if ($n <= 0 || $n > 100) { sendMsg(BOT_TOKEN, $chatId, '❌ یک عددِ بینِ ۰ تا ۱۰۰ بفرست.'); return true; }
        vlSet(function (&$c) use ($n) { $c['daily_rate'] = $n; });
        return $done();
    }
    if ($action === 'vl_word_bank' || $action === 'vl_word_acct') {
        if ($text === '') { sendMsg(BOT_TOKEN, $chatId, '❌ خالی نباشد.'); return true; }
        $key = $action === 'vl_word_bank' ? 'word_bank' : 'word_account';
        vlSet(function (&$c) use ($key, $text) { $c[$key] = $text; });
        return $done();
    }
    return false;
}
