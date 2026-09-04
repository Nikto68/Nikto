<?php
/**
 * 🎮 «بازی الماسی» — تنها دروازه‌ی بازی‌های گروهی: مین، دوز،
 * سنگ‌کاغذقیچی، بسکتبال. هیچ‌کدام دیگر با تایپِ مستقیمِ کلمه‌شان
 * (مثلا «مین ۵۰۰» یا «دوز ۵۰۰») ساخته نمی‌شوند — فقط از همین‌جا،
 * با زدنِ دکمه. «مین» در mine.php و «دوز» در games.php از قبل
 * هستند؛ توابعِ سازنده‌شان (mnCreate/mnRender، gmCreate/gmShow)
 * دست‌نخورده مانده‌اند — این‌جا مستقیم صدایشان می‌زند.
 *
 * عمدا جدا از games.php: آن فایل قبلا با منطقِ «چالش/دوز» (تخته‌ی
 * ۳×۳) بافته شده و هر شاخه‌ای رویِ kind شرط دارد — قاطی کردنِ یک
 * kind تازه آن‌جا یعنی ریسکِ خرابی رویِ یک بازیِ زنده و پرکاربرد.
 * این‌جا خودش یک جدولِ SQLiteِ جدا دارد (arcade_games)، ولی موجودیِ
 * الماس را از همان‌جا (gmPoints()/gmAdd() از games.php) می‌خواند —
 * دقیقا همان قراردادی که diamond.php/bank.php/vault.php/mine.php هم دارند.
 *
 * ⚙️ همه‌جا: کلیک رویِ هابِ بازی همان یک پیام را ادیت می‌کند — از
 * انتخابِ بازی تا پرسیدنِ مبلغ تا تاییدِ نهایی تا خودِ بازی — تا گروه
 * شلوغ نشود. فقط وقتی بازی واقعا شروع شد (لابیِ سنگ‌کاغذقیچی/بسکتبال،
 * یا تخته‌ی مین/دوز) یک بازیِ تازه روی صفحه می‌ماند.
 */

// ============================================================
// ⚙️ پیکربندی
// ============================================================

function arDefaults() {
    return [
        'on'          => true,
        'group_only'  => 1,
        'word_hub'    => 'بازی الماسی',
        'rps_min'     => 100,
        'rps_max'     => 1000000,
        'rps_wait'    => 120,        // ثانیه — تا لغوِ خودکارِ لابیِ بدونِ بازیکنِ دوم
        'bball_min'   => 100,
        'bball_max'   => 1000000,
        'bball_wait'      => 120,    // ثانیه — تا لغوِ خودکارِ لابیِ بدونِ بازیکنِ دوم
        'bball_throw_wait'=> 180,    // ثانیه — بعدِ پیوستنِ نفرِ دوم، مهلتِ پرتابِ هردو

        // 🎨 رنگ/آیکونِ هر دکمه — کاملا مثلِ الگویِ bkBtn()/vlBtn()
        'icons' => [
            'btn_mine' => '', 'btn_duel' => '', 'btn_rps' => '', 'btn_bball' => '',
            'btn_stake_back' => '', 'btn_stake_confirm' => '',
            'rps_btn_join' => '', 'rps_cancel_btn' => '',
            'pick_rock' => '', 'pick_paper' => '', 'pick_scissors' => '',
            'bball_btn_join' => '', 'bball_cancel_btn' => '',
        ],
        'btns' => [
            'btn_mine' => ['color' => 'primary'], 'btn_duel' => ['color' => 'primary'],
            'btn_rps' => ['color' => 'primary'], 'btn_bball' => ['color' => 'primary'],
            'btn_stake_back' => ['color' => 'danger'], 'btn_stake_confirm' => ['color' => 'success'],
            'rps_btn_join' => ['color' => 'primary'], 'rps_cancel_btn' => ['color' => 'danger'],
            'pick_rock' => ['color' => 'primary'], 'pick_paper' => ['color' => 'primary'], 'pick_scissors' => ['color' => 'primary'],
            'bball_btn_join' => ['color' => 'primary'], 'bball_cancel_btn' => ['color' => 'danger'],
        ],

        'texts' => [
            'off'   => '🔒 «بازی الماسی» فعلا خاموش است.',
            'group_only' => '🎮 بازی الماسی فقط داخلِ گروه کار می‌کند.',
            'hub'   => "💎 <b>بازی الماسی</b>\n\nیکی از بازی‌ها را انتخاب کن:",

            // 🕹 دکمه‌های هاب — عمدا بدونِ متنِ لیبل: فقط ایموجی (پریمیوم اگر
            // ادمین ست کرده باشد، وگرنه همین ایموجیِ ساده به‌عنوانِ نمایشِ جایگزین)
            'btn_mine' => '⛏', 'btn_duel' => '❌⭕', 'btn_rps' => '✂️', 'btn_bball' => '🏀',
            'btn_stake_back' => '↩️', 'btn_stake_confirm' => '✅',

            'ask_stake'   => '💎 با چند الماس شرط ببندیم؟',
            'ask_confirm' => "💎 شرط: <b>{stake}</b> الماس\n\nتایید می‌کنی؟",
            'bad_stake'   => '❌ شرط باید بینِ <b>{min}</b> تا <b>{max}</b> الماس باشد.',
            'low_wallet'  => "❌ الماسِ کافی نداری.\n✨ موجودی: <b>{wallet}</b>",
            'busy'        => '⏳ یک بازیِ دیگر از همین نوع برایت باز است — اول آن را تمام کن.',

            'rps_lobby'   => "✂️ <b>سنگ کاغذ قیچی</b>\n\n👤 میزبان: {host}\n💎 شرط: <b>{stake}</b>\n\nمنتظرِ حریف…",
            'rps_btn_join'=> '⚔️',
            'rps_self_join' => '😄 نمی‌تونی با خودت بازی کنی.',
            'rps_full'    => '❌ این بازی پر است.',
            'rps_expired' => '⌛️ این بازی دیگر معتبر نیست.',
            'rps_cancel_btn' => '🚫',
            'rps_cancelled'  => "🚫 <b>لغو شد</b>\n\n💎 شرط به {host} برگشت.",
            'rps_pick'    => "⚔️ <b>{p1}</b> در برابرِ <b>{p2}</b>\n💎 شرط: <b>{stake}</b>\n\nهرکس دکمه‌ی خودش را بزند 👇",
            'rps_picked'  => '✅ انتخابت ثبت شد — منتظرِ حریف…',
            'rps_not_player' => '🔒 این بازیِ تو نیست.',
            'rps_already' => '✅ قبلا انتخاب کرده‌ای.',
            'rps_result_win'  => "🏆 <b>{winner}</b> برد!\n\n{p1}: {c1}\n{p2}: {c2}\n\n💎 +{amount}",
            'rps_result_tie'  => "🤝 <b>مساوی!</b>\n\n{p1}: {c1}\n{p2}: {c2}\n\n💎 شرط به هر دو برگشت.",
            'pick_rock' => '🪨', 'pick_paper' => '📄', 'pick_scissors' => '✂️',

            'bball_lobby'    => "🏀 <b>بسکتبال</b>\n\n👤 میزبان: {host}\n💎 شرط: <b>{stake}</b>\n\nمنتظرِ حریف…",
            'bball_btn_join' => '⚔️',
            'bball_cancel_btn' => '🚫',
            'bball_self_join'  => '😄 نمی‌تونی با خودت بازی کنی.',
            'bball_full'       => '❌ این بازی پر است.',
            'bball_expired'    => '⌛️ این بازی دیگر معتبر نیست.',
            'bball_cancelled'  => "🚫 <b>لغو شد</b>\n\n💎 شرط به {host} برگشت.",
            // 🧵 دقیقا رویِ همین پیام ریپلای می‌کنند و ایموجیِ 🏀 را به‌عنوانِ
            // پیامِ واقعی می‌فرستند — تلگرام خودش آن را پرتابِ انیمیشنی می‌کند.
            'bball_throw_ask' => "🏀 <b>{p1}</b> در برابرِ <b>{p2}</b>\n💎 شرط: <b>{stake}</b>\n\n" .
                                 "هرکس رویِ همین پیام ریپلای کند و ایموجیِ 🏀 بفرستد — پرتابش ثبت می‌شود.",
            'bball_progress_waiting' => "🏀 <b>{p1}</b> در برابرِ <b>{p2}</b>\n💎 شرط: <b>{stake}</b>\n\n" .
                                        "{t1}\n{t2}\n\nهنوز منتظرِ پرتابِ حریف…",
            'bball_thrown'  => '✅ پرتاب کرد',
            'bball_pending' => '⏳ هنوز پرتاب نکرده',
            'bball_result_win' => "🏆 <b>{winner}</b> برد!\n\n{c1}\n{c2}\n\n💎 +{amount}",
            'bball_result_tie' => "🤝 <b>مساوی!</b>\n\n{c1}\n{c2}\n\n💎 شرط به هر دو برگشت.",
            'bball_timeout'    => "⌛️ <b>وقتِ پرتاب تمام شد</b>\n\n💎 شرط به هر دو نفر برگشت.",
        ],
    ];
}

function arCfg() {
    $c = cfg()['arcade'] ?? null;
    return is_array($c) ? array_replace_recursive(arDefaults(), $c) : arDefaults();
}
function arSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!is_array($c['arcade'] ?? null)) $c['arcade'] = arDefaults();
        $fn($c['arcade']);
    });
}
function arVal($path, $default = null) {
    $v = arCfg();
    foreach (explode('.', $path) as $seg) {
        if (!is_array($v) || !array_key_exists($seg, $v)) return $default;
        $v = $v[$seg];
    }
    return $v;
}
function arOn() { return !empty(arVal('on')); }
function arIsButtonKey($slug) { return in_array($slug, arBtnKeys(), true); }
function arT($slug, $vars = []) {
    $t = (string)arVal('texts.' . $slug, arDefaults()['texts'][$slug] ?? $slug);
    if (arIsButtonKey($slug)) $t = strip_tags($t);
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}
function arNum($n) { return number_format((float)$n, 0, '.', ','); }

/** دکمه‌ی شیشه‌ای با متن/رنگ/ایموجیِ پریمیومِ قابل‌ویرایش — همان الگویِ bkBtn()/vlBtn() */
function arBtn($key, $vars, $data) {
    $b = ['text' => arT($key, $vars), 'callback_data' => $data];
    $color = (string)arVal('btns.' . $key . '.color', '');
    if (function_exists('isStyle') && isStyle($color)) $b['style'] = $color;
    $ic = trim((string)arVal('icons.' . $key, ''));
    if ($ic !== '') $b['icon_custom_emoji_id'] = $ic;
    return $b;
}

function arBtnKeys() {
    return ['btn_mine', 'btn_duel', 'btn_rps', 'btn_bball', 'btn_stake_back', 'btn_stake_confirm',
            'rps_btn_join', 'rps_cancel_btn', 'pick_rock', 'pick_paper', 'pick_scissors',
            'bball_btn_join', 'bball_cancel_btn'];
}
function arBtnLabels() {
    return [
        'btn_mine' => 'دکمه: مین', 'btn_duel' => 'دکمه: دوز', 'btn_rps' => 'دکمه: سنگ‌کاغذقیچی', 'btn_bball' => 'دکمه: بسکتبال',
        'btn_stake_back' => 'دکمه: بازگشت (تاییدِ شرط)', 'btn_stake_confirm' => 'دکمه: تایید (تاییدِ شرط)',
        'rps_btn_join' => 'دکمه: پیوستن (سنگ‌کاغذقیچی)', 'rps_cancel_btn' => 'دکمه: لغو (سنگ‌کاغذقیچی)',
        'pick_rock' => 'دکمه: سنگ', 'pick_paper' => 'دکمه: کاغذ', 'pick_scissors' => 'دکمه: قیچی',
        'bball_btn_join' => 'دکمه: پیوستن (بسکتبال)', 'bball_cancel_btn' => 'دکمه: لغو (بسکتبال)',
    ];
}
function arBtnLabel($k) { return arBtnLabels()[$k] ?? $k; }

// ============================================================
// 🗃 ذخیره‌سازیِ خودِ بازی‌ها — جدولِ جدا، همان الگویِ games.php
// ============================================================

function arcadeDbPath() { return DATA_DIR . '/arcade_games.sqlite'; }

function arcadeDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3')) return null;
    $path = arcadeDbPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    try {
        $db = new SQLite3($path);
    } catch (Throwable $e) {
        error_log('[arcade] arcade_games.sqlite باز نشد: ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS arcade_games (
        id TEXT PRIMARY KEY, data TEXT NOT NULL, created INTEGER NOT NULL, status TEXT NOT NULL
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_arcade_status ON arcade_games(status)');
    return $db;
}

function arGameId() { return 'r' . bin2hex(random_bytes(5)); }

function arGameGet($id) {
    $db = arcadeDb();
    if (!$db) return null;
    $stmt = $db->prepare('SELECT data FROM arcade_games WHERE id = :id');
    $stmt->bindValue(':id', (string)$id, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $d = json_decode($row['data'], true);
    return is_array($d) ? $d : null;
}

function arGameCreate($data) {
    $db = arcadeDb();
    if (!$db) return null;
    $id = arGameId();
    $data['id'] = $id;
    $stmt = $db->prepare('INSERT INTO arcade_games (id, data, created, status) VALUES (:id, :data, :created, :status)');
    $stmt->bindValue(':id', $id, SQLITE3_TEXT);
    $stmt->bindValue(':data', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
    $stmt->bindValue(':created', (int)($data['created'] ?? time()), SQLITE3_INTEGER);
    $stmt->bindValue(':status', (string)($data['status'] ?? ''), SQLITE3_TEXT);
    $stmt->execute();
    return $id;
}

/** تغییرِ اتمیک — قفل فقط رویِ همین یک بازی، دقیقا الگویِ gmSetGame */
function arGameSet($id, callable $fn) {
    $db = arcadeDb();
    if (!$db) return null;
    $id = (string)$id;
    $db->exec('BEGIN IMMEDIATE');
    try {
        $stmt = $db->prepare('SELECT data FROM arcade_games WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $g = $row ? json_decode($row['data'], true) : null;
        if (!is_array($g)) { $db->exec('ROLLBACK'); return false; }

        $result = $fn($g);

        $up = $db->prepare('INSERT OR REPLACE INTO arcade_games (id, data, created, status) VALUES (:id, :data, :created, :status)');
        $up->bindValue(':id', $id, SQLITE3_TEXT);
        $up->bindValue(':data', json_encode($g, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        $up->bindValue(':created', (int)($g['created'] ?? time()), SQLITE3_INTEGER);
        $up->bindValue(':status', (string)($g['status'] ?? ''), SQLITE3_TEXT);
        $up->execute();
        $db->exec('COMMIT');
        return $result;
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        error_log('[arcade] arGameSet خطا: ' . $e->getMessage());
        return false;
    }
}

/** همه‌ی بازی‌هایِ یک وضعیت — برایِ arTick و پیدا کردنِ بازیِ بسکتبالِ در حالِ پرتاب */
function arScanByStatus($status, $limit = 200) {
    $db = arcadeDb();
    if (!$db) return [];
    $stmt = $db->prepare('SELECT id, data FROM arcade_games WHERE status = :s LIMIT :l');
    $stmt->bindValue(':s', (string)$status, SQLITE3_TEXT);
    $stmt->bindValue(':l', max(1, (int)$limit), SQLITE3_INTEGER);
    $out = [];
    $res = $stmt->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $d = json_decode($row['data'], true);
        if (is_array($d)) $out[] = $d;
    }
    return $out;
}

/** لابی‌هایِ رهاشده (کسی نپیوست) را با برگرداندنِ شرط لغو می‌کند — دقیقا الگویِ gmTick */
function arTick($limit = 20) {
    $db = arcadeDb();
    if (!$db) return 0;
    $n = 0;

    $cut = time() - max(30, (int)arVal('rps_wait', 120));
    $res = $db->query("SELECT id FROM arcade_games WHERE status = 'open' AND created < $cut LIMIT " . max(1, (int)$limit));
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $id = $row['id'];
        arGameSet($id, function (&$g) use (&$n) {
            if (($g['status'] ?? '') !== 'open') return false;
            gmAdd((int)$g['host'], (float)$g['stake'], '', '');
            $g['status'] = 'cancelled';
            $n++;
            return true;
        });
    }

    // 🏀 هردو پیوستند ولی پرتاب‌ها کامل نشد — مهلت تمام شده؟ شرطِ هردو برگردد.
    foreach (arScanByStatus('playing', $limit) as $g) {
        if (($g['game'] ?? '') !== 'bball') continue;
        $deadline = (int)($g['throw_deadline'] ?? 0);
        if ($deadline <= 0 || time() < $deadline) continue;
        $ok = arGameSet($g['id'], function (&$g) {
            if (($g['status'] ?? '') !== 'playing') return false;
            gmAdd((int)$g['host'], (float)$g['stake'], '', '');
            gmAdd((int)$g['guest'], (float)$g['stake'], '', '');
            $g['status'] = 'cancelled';
            return true;
        });
        if ($ok && (int)($g['msg'] ?? 0) && !empty($g['chat'])) {
            editMsg(BOT_TOKEN, $g['chat'], (int)$g['msg'], arT('bball_timeout'), null);
            $n++;
        }
    }
    return $n;
}

// ============================================================
// 🎣 منویِ هاب — ۲×۲: مین+دوز بالا، سنگ‌کاغذقیچی+بسکتبال پایین
// ============================================================

function arHubKb() {
    return inlineKb([
        [arBtn('btn_mine', [], 'ar_go_mine'), arBtn('btn_duel', [], 'ar_go_duel')],
        [arBtn('btn_rps', [], 'ar_go_rps'), arBtn('btn_bball', [], 'ar_go_bball')],
    ]);
}

/** فقط یک دکمه‌ی بازگشت — وقتی مبلغ را می‌پرسیم و هنوز چیزی تایید نشده */
function arAskKb() {
    return inlineKb([[arBtn('btn_stake_back', [], 'ar_stake_back')]]);
}

/** بازگشت / تایید — کنارِ هم، بعدِ اینکه کاربر مبلغ را تایپ کرد */
function arConfirmKb() {
    return inlineKb([[arBtn('btn_stake_back', [], 'ar_stake_back'), arBtn('btn_stake_confirm', [], 'ar_stake_confirm')]]);
}

function arHandleText($text, $uid, $chatId, $name, $uname, $replyTo, $isPrivate, $msg = null) {
    if (!arOn()) return false;
    if (!empty(arVal('group_only', 1)) && $isPrivate) return false;
    $raw = trim((string)$text);
    if ($raw === '') return false;

    arTick(20);

    $pend = arPendGet($uid, $chatId);
    if ($pend && ($pend['stage'] ?? '') === 'ask' && arLooksLikeAmount($raw)) {
        return arHandleStakeAnswer($raw, $uid, $chatId, $name, $uname, $pend);
    }

    foreach (explode(',', (string)arVal('word_hub', 'بازی الماسی')) as $w) {
        $w = trim($w);
        if ($w === '' || mb_strtolower($raw) !== mb_strtolower($w)) continue;
        $extra = $replyTo ? ['reply_to_message_id' => $replyTo] : [];
        sendMsg(BOT_TOKEN, $chatId, arT('hub'), arHubKb(), $extra);
        return true;
    }
    return false;
}

function arLooksLikeAmount($raw) {
    $n = trim(str_replace([',', '٬', ' '], '', norm_fa_digits(trim((string)$raw))));
    return $n !== '' && preg_match('/^\d+(\.\d+)?$/', $n) === 1;
}

// ============================================================
// ⏳ درخواستِ در انتظار — دو مرحله: پرسیدنِ مبلغ («ask») سپس تاییدِ
// مبلغ («confirm»)، هردو رویِ همان یک پیامِ هاب — همان الگویِ vlPend*
// ============================================================

function arPendKey($uid, $chat) { return $uid . '_' . $chat; }
function arPendSet($uid, $chat, array $data) {
    mutate('arcade_states', function (&$s) use ($uid, $chat, $data) {
        $s[arPendKey($uid, $chat)] = $data + ['at' => time()];
    });
}
function arPendGet($uid, $chat) {
    $s = load('arcade_states');
    $e = $s[arPendKey($uid, $chat)] ?? null;
    if (count($s) > 30 && random_int(1, 50) === 1) arPendSweep(100);
    if (!$e || time() - (int)($e['at'] ?? 0) > 300) return null;
    return $e;
}
function arPendClear($uid, $chat) {
    mutate('arcade_states', function (&$s) use ($uid, $chat) { unset($s[arPendKey($uid, $chat)]); });
}
function arPendSweep($limit = 200) {
    $now = time(); $removed = 0;
    mutate('arcade_states', function (&$s) use ($now, $limit, &$removed) {
        foreach (array_keys($s) as $k) {
            if ($removed >= $limit) break;
            if ($now - (int)($s[$k]['at'] ?? 0) > 300) { unset($s[$k]); $removed++; }
        }
    });
    return $removed;
}

/** حدِ مجازِ هر نوع بازی — از همان جایی که خودش صاحبش است (games.php/mine.php یا arVal) */
function arStakeBounds($kind) {
    if ($kind === 'mine') return [(float)mnVal('entry_min', 100), (float)mnVal('entry_max', 1000000)];
    if ($kind === 'duel') return [max(1, (float)gmVal('min', 10)), max(1, (float)gmVal('max', 1e9))];
    if ($kind === 'rps')  return [(float)arVal('rps_min', 100), (float)arVal('rps_max', 1000000)];
    return [(float)arVal('bball_min', 100), (float)arVal('bball_max', 1000000)];
}

function arKindLabel($kind) {
    return ['mine' => 'مین', 'duel' => 'دوز', 'rps' => 'سنگ‌کاغذقیچی', 'bball' => 'بسکتبال'][$kind] ?? $kind;
}

// ============================================================
// 💬 مرحله‌ی مبلغ: پرسیدن → تاییدِ عدد → شروعِ واقعیِ بازی
// ============================================================

function arHandleStakeAnswer($raw, $uid, $chatId, $name, $uname, $pend) {
    $kind  = (string)$pend['kind'];
    $msgId = (int)$pend['msg'];
    $stake = (float)str_replace([',', '٬', ' '], '', norm_fa_digits($raw));

    [$min, $max] = arStakeBounds($kind);
    if ($stake <= 0 || $stake < $min || $stake > $max || floor($stake) != $stake) {
        editMsg(BOT_TOKEN, $chatId, $msgId, arT('bad_stake', ['min' => arNum($min), 'max' => arNum($max)]), arAskKb());
        return true;
    }
    // 💎 چک نرمِ موجودی — همین‌جا فقط برایِ پیامِ بهتر؛ چکِ واقعی (اتمیک)
    // موقعِ زدنِ «تایید» با gmAdd انجام می‌شود.
    if (gmPoints($uid) < $stake) {
        editMsg(BOT_TOKEN, $chatId, $msgId, arT('low_wallet', ['wallet' => arNum(gmPoints($uid))]), arAskKb());
        return true;
    }

    arPendSet($uid, $chatId, ['kind' => $kind, 'msg' => $msgId, 'stage' => 'confirm', 'stake' => $stake]);
    editMsg(BOT_TOKEN, $chatId, $msgId, arT('ask_confirm', ['stake' => arNum($stake)]), arConfirmKb());
    return true;
}

/** بعدِ زدنِ «تایید» — همین‌جا هر بازی راهِ خودش را می‌رود */
function arStartGame($kind, $stake, $uid, $chatId, $name, $uname, $msgId, $thread = 0) {
    if ($kind === 'mine') {
        if (function_exists('mnActiveCountForUser') && mnActiveCountForUser($uid) > 0) {
            editMsg(BOT_TOKEN, $chatId, $msgId, arT('busy'), arHubKb());
            return;
        }
        if (function_exists('mnCooldownLeft') && mnCooldownLeft($uid) > 0) {
            editMsg(BOT_TOKEN, $chatId, $msgId, arT('busy'), arHubKb());
            return;
        }
        // 💣 مین پولی نمی‌گیرد تا خودِ کاربر رویِ «پیوستن» بزند — دقیقا
        // همان قراردادِ خودِ mine.php، دست‌نخورده.
        $g = mnCreate($uid, $chatId, $name, $uname, $stake);
        if ($g) {
            // msg_id را باید خودمان ذخیره کنیم — قبلا این کار را خودِ
            // mnHandleText (که حالا حذف شده) بعدِ sendMsg انجام می‌داد.
            // mnTick برایِ ادیت‌کردنِ پیامِ بازیِ منقضی‌شده به همین فیلد
            // نیاز دارد (mine.php:559)، وگرنه بازیِ رهاشده هیچ‌وقت پیامش
            // «منقضی شد» نمی‌شود.
            mnSetGame($g['id'], function (&$x) use ($msgId) { $x['msg_id'] = $msgId; return true; });
            mnRender($g, $chatId, $msgId);
        }
        return;
    }

    if ($kind === 'duel') {
        if (!gmAdd($uid, -$stake, $name, $uname)) {
            editMsg(BOT_TOKEN, $chatId, $msgId, arT('low_wallet', ['wallet' => arNum(gmPoints($uid))]), arHubKb());
            return;
        }
        $g = gmCreate('duel', $stake, $uid, $chatId, $name, $uname, $thread);
        $g['msg'] = $msgId;
        gmSetGame($g['id'], function (&$x) use ($msgId) { $x['msg'] = $msgId; return true; });
        gmShow($g);
        return;
    }

    if (!gmAdd($uid, -$stake, $name, $uname)) {
        editMsg(BOT_TOKEN, $chatId, $msgId, arT('low_wallet', ['wallet' => arNum(gmPoints($uid))]), arHubKb());
        return;
    }
    $game = $kind === 'rps' ? 'rps' : 'bball';
    $id = arGameCreate([
        'game' => $game, 'status' => 'open', 'created' => time(), 'chat' => (string)$chatId, 'msg' => $msgId,
        'host' => (int)$uid, 'host_name' => $name, 'host_uname' => $uname, 'stake' => $stake,
    ]);
    $lobbyText = arT($game . '_lobby', ['host' => h($name), 'stake' => arNum($stake)]);
    editMsg(BOT_TOKEN, $chatId, $msgId, $lobbyText, arLobbyKb($game, $id));
}

// ============================================================
// 🃏 لابیِ مشترکِ سنگ‌کاغذقیچی/بسکتبال — پیوستن، لغو
// ============================================================

function arLobbyKb($game, $id) {
    return inlineKb([
        [arBtn($game . '_btn_join', [], 'ar_join_' . $id)],
        [arBtn($game . '_cancel_btn', [], 'ar_cxl_' . $id)],
    ]);
}

function arRpsJoinKb($id) {
    return inlineKb([[
        arBtn('pick_rock', [], 'ar_pick_' . $id . '_rock'),
        arBtn('pick_paper', [], 'ar_pick_' . $id . '_paper'),
        arBtn('pick_scissors', [], 'ar_pick_' . $id . '_scissors'),
    ]]);
}

function arRpsBeats($a, $b) {
    return ($a === 'rock' && $b === 'scissors') || ($a === 'scissors' && $b === 'paper') || ($a === 'paper' && $b === 'rock');
}
function arRpsEmoji($p) { return ['rock' => '🪨', 'paper' => '📄', 'scissors' => '✂️'][$p] ?? '?'; }

// ============================================================
// 🏀 بسکتبال — استیکرِ واقعیِ 🏀 (message.dice) روی ریپلای
// ============================================================

/**
 * مستندِ خودِ تلگرام برایِ ایموجیِ 🏀 در dice: مقدار بینِ ۱ تا ۵ است؛
 * ۴ و ۵ یعنی توپ تویِ حلقه رفته، ۱ تا ۳ یعنی خطا رفته. این عددها را
 * تلگرام می‌سازد، نه ما — این‌جا فقط تفسیرش می‌کنیم.
 */
function arBballScored($value) { return (int)$value >= 4; }
function arBballThrowBadge($value) { return arBballScored($value) ? '🏀✅' : '🏀❌'; }

function arFindPlayingBball($chatId, $msgId) {
    foreach (arScanByStatus('playing', 500) as $g) {
        if (($g['game'] ?? '') !== 'bball') continue;
        if ((string)($g['chat'] ?? '') !== (string)$chatId) continue;
        if ((int)($g['msg'] ?? 0) !== (int)$msgId) continue;
        return $g;
    }
    return null;
}

function arBballProgressText($g) {
    $throws = (array)($g['throws'] ?? []);
    $t1 = h($g['host_name']) . ': ' . (isset($throws[(string)$g['host']]) ? arT('bball_thrown') : arT('bball_pending'));
    $t2 = h($g['guest_name']) . ': ' . (isset($throws[(string)$g['guest']]) ? arT('bball_thrown') : arT('bball_pending'));
    return arT('bball_progress_waiting', [
        'p1' => h($g['host_name']), 'p2' => h($g['guest_name']), 'stake' => arNum($g['stake']),
        't1' => $t1, 't2' => $t2,
    ]);
}

function arResolveBballThrows($g, $chatId) {
    $throws   = (array)($g['throws'] ?? []);
    $hostVal  = (int)($throws[(string)$g['host']] ?? 0);
    $guestVal = (int)($throws[(string)$g['guest']] ?? 0);
    $hostScored  = arBballScored($hostVal);
    $guestScored = arBballScored($guestVal);
    $stake = (float)$g['stake'];
    $c1 = arBballThrowBadge($hostVal) . ' ' . h($g['host_name']);
    $c2 = arBballThrowBadge($guestVal) . ' ' . h($g['guest_name']);

    if ($hostScored === $guestScored) {
        gmAdd((int)$g['host'], $stake, '', '');
        gmAdd((int)$g['guest'], $stake, '', '');
        editMsg(BOT_TOKEN, $chatId, (int)$g['msg'], arT('bball_result_tie', ['c1' => $c1, 'c2' => $c2]), null);
        return;
    }
    $winnerId   = $hostScored ? (int)$g['host'] : (int)$g['guest'];
    $winnerName = $hostScored ? $g['host_name'] : $g['guest_name'];
    $amount = $stake * 2;
    gmAdd($winnerId, $amount, '', '');
    editMsg(BOT_TOKEN, $chatId, (int)$g['msg'],
        arT('bball_result_win', ['winner' => h($winnerName), 'c1' => $c1, 'c2' => $c2, 'amount' => arNum($amount)]), null);
}

/** پیامِ 🏀 که ریپلایِ رویِ لابیِ بسکتبال است — از masterHandle صدا زده می‌شود */
function arHandleDice($msg, $uid, $chatId, $name, $uname) {
    if (!arOn()) return false;
    $dice = $msg['dice'] ?? null;
    if (!is_array($dice) || (string)($dice['emoji'] ?? '') !== '🏀') return false;
    $replyTo = (int)($msg['reply_to_message']['message_id'] ?? 0);
    if ($replyTo <= 0) return false;

    $g = arFindPlayingBball($chatId, $replyTo);
    if (!$g) return false;
    if ((int)$uid !== (int)$g['host'] && (int)$uid !== (int)$g['guest']) return false;

    $value = (int)($dice['value'] ?? 0);
    $result = arGameSet($g['id'], function (&$g) use ($uid, $value) {
        if (($g['status'] ?? '') !== 'playing') return ['state' => 'gone'];
        $throws = (array)($g['throws'] ?? []);
        if (isset($throws[(string)$uid])) return ['state' => 'already'];
        $throws[(string)$uid] = $value;
        $g['throws'] = $throws;
        if (count($throws) < 2) return ['state' => 'waiting'];
        $g['status'] = 'done';
        return ['state' => 'resolve'];
    });
    if (!is_array($result) || in_array($result['state'] ?? '', ['gone', 'already'], true)) return true;

    $fresh = arGameGet($g['id']);
    if (!$fresh) return true;
    if (($result['state'] ?? '') === 'waiting') {
        editMsg(BOT_TOKEN, $chatId, (int)$fresh['msg'], arBballProgressText($fresh), null);
        return true;
    }
    arResolveBballThrows($fresh, $chatId);
    return true;
}

// ============================================================
// 🔘 کال‌بک‌ها
// ============================================================

function arCallback($data, $uid, $chatId, $msgId, $cbId, $from = []) {
    $data = (string)$data;
    if (!arOn()) return false;
    $name  = (string)($from['first_name'] ?? '');
    $uname = (string)($from['username'] ?? '');

    $goMap = ['ar_go_mine' => 'mine', 'ar_go_duel' => 'duel', 'ar_go_rps' => 'rps', 'ar_go_bball' => 'bball'];
    if (isset($goMap[$data])) {
        $kind = $goMap[$data];
        answerCb(BOT_TOKEN, $cbId);
        arPendSet($uid, $chatId, ['kind' => $kind, 'msg' => $msgId, 'stage' => 'ask']);
        editMsg(BOT_TOKEN, $chatId, $msgId, arT('ask_stake'), arAskKb());
        return true;
    }

    if ($data === 'ar_stake_back') {
        answerCb(BOT_TOKEN, $cbId);
        arPendClear($uid, $chatId);
        editMsg(BOT_TOKEN, $chatId, $msgId, arT('hub'), arHubKb());
        return true;
    }

    if ($data === 'ar_stake_confirm') {
        $pend = arPendGet($uid, $chatId);
        if (!$pend || ($pend['stage'] ?? '') !== 'confirm' || (int)$pend['msg'] !== (int)$msgId) {
            answerCb(BOT_TOKEN, $cbId, arT('rps_expired'), true);
            return true;
        }
        answerCb(BOT_TOKEN, $cbId);
        arPendClear($uid, $chatId);
        arStartGame((string)$pend['kind'], (float)$pend['stake'], $uid, $chatId, $name, $uname, $msgId);
        return true;
    }

    if (preg_match('/^ar_join_(\w+)$/', $data, $m)) {
        $id = $m[1];
        $g = arGameGet($id);
        $game = (string)($g['game'] ?? 'rps');
        if (!$g || $g['status'] !== 'open') { answerCb(BOT_TOKEN, $cbId, arT($game . '_expired'), true); return true; }
        if ((int)$g['host'] === (int)$uid) { answerCb(BOT_TOKEN, $cbId, arT($game . '_self_join'), true); return true; }

        if (!gmAdd($uid, -(float)$g['stake'], $name, $uname)) {
            answerCb(BOT_TOKEN, $cbId, arT('low_wallet', ['wallet' => arNum(gmPoints($uid))]), true);
            return true;
        }
        $throwDeadline = time() + max(30, (int)arVal('bball_throw_wait', 180));
        $ok = arGameSet($id, function (&$g) use ($uid, $name, $uname, $game, $throwDeadline) {
            if ($g['status'] !== 'open') return false;
            $g['status'] = 'playing';
            $g['guest'] = (int)$uid; $g['guest_name'] = $name; $g['guest_uname'] = $uname;
            if ($game === 'rps') $g['picks'] = [];
            else { $g['throws'] = []; $g['throw_deadline'] = $throwDeadline; }
            return true;
        });
        if (!$ok) {
            gmAdd($uid, (float)$g['stake'], $name, $uname); // بازی همون لحظه پر/بسته شد — شرط برگردد
            answerCb(BOT_TOKEN, $cbId, arT($game . '_full'), true);
            return true;
        }
        answerCb(BOT_TOKEN, $cbId, '⚔️');
        if ($game === 'rps') {
            editMsg(BOT_TOKEN, $chatId, $msgId,
                arT('rps_pick', ['p1' => h($g['host_name']), 'p2' => h($name), 'stake' => arNum($g['stake'])]),
                arRpsJoinKb($id));
        } else {
            editMsg(BOT_TOKEN, $chatId, $msgId,
                arT('bball_throw_ask', ['p1' => h($g['host_name']), 'p2' => h($name), 'stake' => arNum($g['stake'])]),
                null);
        }
        return true;
    }

    if (preg_match('/^ar_cxl_(\w+)$/', $data, $m)) {
        $id = $m[1];
        $g = arGameGet($id);
        $game = (string)($g['game'] ?? 'rps');
        if (!$g) { answerCb(BOT_TOKEN, $cbId, arT('rps_expired'), true); return true; }
        if ((int)$g['host'] !== (int)$uid) { answerCb(BOT_TOKEN, $cbId, arT('rps_not_player'), true); return true; }
        $ok = arGameSet($id, function (&$g) {
            if ($g['status'] !== 'open') return false;
            $g['status'] = 'cancelled';
            return true;
        });
        if (!$ok) { answerCb(BOT_TOKEN, $cbId, arT($game . '_expired'), true); return true; }
        gmAdd((int)$g['host'], (float)$g['stake'], '', '');
        answerCb(BOT_TOKEN, $cbId, '🚫');
        editMsg(BOT_TOKEN, $chatId, $msgId, arT($game . '_cancelled', ['host' => h($g['host_name'])]), null);
        return true;
    }

    if (preg_match('/^ar_pick_(\w+)_(rock|paper|scissors)$/', $data, $m)) {
        $id = $m[1]; $pick = $m[2];
        $g = arGameGet($id);
        if (!$g || $g['status'] !== 'playing') { answerCb(BOT_TOKEN, $cbId, arT('rps_expired'), true); return true; }
        if ((int)$uid !== (int)$g['host'] && (int)$uid !== (int)$g['guest']) {
            answerCb(BOT_TOKEN, $cbId, arT('rps_not_player'), true); return true;
        }

        $result = arGameSet($id, function (&$g) use ($uid, $pick) {
            if ($g['status'] !== 'playing') return ['state' => 'gone'];
            $picks = (array)($g['picks'] ?? []);
            if (isset($picks[(string)$uid])) return ['state' => 'already'];
            $picks[(string)$uid] = $pick;
            $g['picks'] = $picks;
            if (count($picks) < 2) { return ['state' => 'waiting']; }

            $hostPick  = $picks[(string)$g['host']] ?? null;
            $guestPick = $picks[(string)$g['guest']] ?? null;
            $g['status'] = 'done';
            $g['final'] = ['host_pick' => $hostPick, 'guest_pick' => $guestPick];
            return ['state' => 'resolve', 'host_pick' => $hostPick, 'guest_pick' => $guestPick];
        });

        if (($result['state'] ?? '') === 'gone')    { answerCb(BOT_TOKEN, $cbId, arT('rps_expired'), true); return true; }
        if (($result['state'] ?? '') === 'already') { answerCb(BOT_TOKEN, $cbId, arT('rps_already'), true); return true; }
        if (($result['state'] ?? '') === 'waiting')  { answerCb(BOT_TOKEN, $cbId, arT('rps_picked')); return true; }

        // resolve
        answerCb(BOT_TOKEN, $cbId, '✅');
        $hostPick = $result['host_pick']; $guestPick = $result['guest_pick'];
        $stake = (float)$g['stake'];
        $c1 = arRpsEmoji($hostPick) . ' ' . h($g['host_name']);
        $c2 = arRpsEmoji($guestPick) . ' ' . h($g['guest_name']);

        if ($hostPick === $guestPick) {
            gmAdd((int)$g['host'], $stake, '', '');
            gmAdd((int)$g['guest'], $stake, '', '');
            editMsg(BOT_TOKEN, $chatId, $msgId, arT('rps_result_tie', ['p1' => h($g['host_name']), 'p2' => h($g['guest_name']), 'c1' => $c1, 'c2' => $c2]), null);
        } else {
            $hostWins = arRpsBeats($hostPick, $guestPick);
            $winnerId = $hostWins ? (int)$g['host'] : (int)$g['guest'];
            $winnerName = $hostWins ? $g['host_name'] : $g['guest_name'];
            $amount = $stake * 2;
            gmAdd($winnerId, $amount, '', '');
            editMsg(BOT_TOKEN, $chatId, $msgId, arT('rps_result_win', [
                'winner' => h($winnerName), 'p1' => h($g['host_name']), 'p2' => h($g['guest_name']),
                'c1' => $c1, 'c2' => $c2, 'amount' => arNum($amount),
            ]), null);
        }
        return true;
    }

    return false;
}

// ============================================================
// ✏️ ویرایشگرِ داخلِ ربات — /panel ← 🎮 بازی‌ها ← 💎 بازی الماسی
// همان الگویِ bkAdminHome/bkAdminColors/bkAdminTexts (bank.php)
// ============================================================

function arAdminHome($chatId, $msgId = null) {
    $c = arCfg();
    $t  = "💎 <b>بازی الماسی</b>\n\n";
    $t .= 'وضعیت: ' . (arOn() ? '✅ روشن' : '❌ خاموش') . "\n\n";
    $t .= 'کلمه‌ی بازکردنِ هاب: <code>' . h($c['word_hub']) . "</code>\n";
    $t .= '✂️ شرطِ سنگ‌کاغذقیچی: <b>' . arNum($c['rps_min']) . '</b> تا <b>' . arNum($c['rps_max']) . "</b>\n";
    $t .= '🏀 شرطِ بسکتبال: <b>' . arNum($c['bball_min']) . '</b> تا <b>' . arNum($c['bball_max']) . "</b>\n";

    $rows = [
        [btnCb(arOn() ? '✅ روشن' : '❌ خاموش', 'arax', 'info')],
        [btnCb('🗣 کلمه‌ی هاب', 'araw_hub', 'admin'), btnCb('✏️ متن‌ها و دکمه‌ها', 'arat_home', 'admin')],
        [btnCb('🎨 رنگِ دکمه‌ها', 'aracolors', 'admin')],
        [btnCb('✂️ حدِ سنگ‌کاغذقیچی', 'ararps', 'admin'), btnCb('🏀 حدِ بسکتبال', 'arabball', 'admin')],
        [btnCb(UT('back'), 'adm_home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function arAdminTexts($chatId, $msgId, $page = 0) {
    $keys = array_keys((array)arVal('texts', []));
    $per  = 12;
    $tot  = max(1, (int)ceil(count($keys) / $per));
    $page = max(0, min($tot - 1, (int)$page));
    $slice = array_slice($keys, $page * $per, $per);

    $t  = "✏️ <b>متن‌ها و دکمه‌های بازی الماسی</b> — صفحه " . ($page + 1) . " از {$tot}\n\n";
    $t .= "هرچه بنویسید عینا همان می‌رود: ایموجیِ پریمیوم و quote سالم می‌مانند.\n\n";
    $rows = [];
    foreach ($slice as $k) {
        $v = (string)arVal('texts.' . $k, '');
        $t .= '• <b>' . h($k) . '</b>: <code>' .
              h(mb_substr(str_replace("\n", ' ', strip_tags($v)), 0, 34)) . "</code>\n";
        $rows[] = [btnCb(arIsButtonKey($k) ? arBtnLabel($k) : $k, 'arats_' . $k, 'admin')];
    }
    $nav = [];
    if ($page > 0)        $nav[] = btnCb('◀️', 'arat_' . ($page - 1), 'nav');
    if ($page < $tot - 1) $nav[] = btnCb('▶️', 'arat_' . ($page + 1), 'nav');
    if ($nav) $rows[] = $nav;
    $rows[] = [btnCb(UT('back'), 'ar_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3800), inlineKb($rows));
}

function arAdminColors($chatId, $msgId) {
    $t = "🎨 <b>رنگِ دکمه‌های بازی الماسی</b>\n\nروی هرکدام بزن تا رنگش را عوض کنی.\n\n";
    $rows = [];
    foreach (arBtnKeys() as $k) {
        $color = (string)arVal('btns.' . $k . '.color', 'none');
        $t .= '• ' . h(arBtnLabel($k)) . ': <b>' . h(styleMap()[$color] ?? $color) . "</b>\n";
        $rows[] = [btnCb(arT($k, []) ?: arBtnLabel($k), 'aracolk_' . $k, 'info')];
    }
    $rows[] = [btnCb(UT('back'), 'ar_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function arAdminColorPick($chatId, $msgId, $k) {
    $cur = (string)arVal('btns.' . $k . '.color', 'none');
    $t = "🎨 رنگِ <b>" . h(arBtnLabel($k)) . "</b> را انتخاب کن:\n\nالان: <b>" . h(styleMap()[$cur] ?? $cur) . "</b>";
    $rows = [];
    foreach (styleMap() as $sk => $sl) $rows[] = [btnCb(($sk === $cur ? '✅ ' : '') . $sl, 'aracolv_' . $k . '_' . $sk, 'info')];
    $rows[] = [btnCb(UT('back'), 'aracolors', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function arAdminCallback($data, $chatId, $msgId, $cbId) {
    if (!str_starts_with((string)$data, 'ar_home') && !str_starts_with((string)$data, 'ara')) return false;

    if ($data === 'ar_home') { answerCb(BOT_TOKEN, $cbId); arAdminHome($chatId, $msgId); return true; }
    if ($data === 'arax') {
        arSet(function (&$c) { $c['on'] = empty($c['on']); });
        answerCb(BOT_TOKEN, $cbId, '✅'); arAdminHome($chatId, $msgId); return true;
    }

    if ($data === 'araw_hub') {
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, 'ar_word_hub', []);
        $cur = (string)arVal('word_hub', 'بازی الماسی');
        sendMsg(BOT_TOKEN, $chatId,
            "🗣 کلمه‌های بازکردنِ هاب را بفرست — چند کلمه را با ویرگول جدا کن.\n\nالان: <code>" . h($cur) . "</code>",
            inlineKb([[btnCb('انصراف', 'ar_home', 'cancel')]]));
        return true;
    }
    if ($data === 'ararps' || $data === 'arabball') {
        $act = $data === 'ararps' ? 'ar_rps_bounds' : 'ar_bball_bounds';
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, $act, []);
        sendMsg(BOT_TOKEN, $chatId, "عددِ حداقل و حداکثر را با فاصله بفرست — مثلا: <code>100 1000000</code>",
            inlineKb([[btnCb('انصراف', 'ar_home', 'cancel')]]));
        return true;
    }

    if ($data === 'arat_home') { answerCb(BOT_TOKEN, $cbId); arAdminTexts($chatId, $msgId, 0); return true; }
    if (preg_match('/^arat_(\d+)$/', $data, $m)) { answerCb(BOT_TOKEN, $cbId); arAdminTexts($chatId, $msgId, (int)$m[1]); return true; }

    if ($data === 'aracolors') { answerCb(BOT_TOKEN, $cbId); arAdminColors($chatId, $msgId); return true; }
    if (preg_match('/^aracolk_(\w+)$/', $data, $m) && in_array($m[1], arBtnKeys(), true)) {
        answerCb(BOT_TOKEN, $cbId); arAdminColorPick($chatId, $msgId, $m[1]); return true;
    }
    if (preg_match('/^aracolv_(\w+)_(\w+)$/', $data, $m) && in_array($m[1], arBtnKeys(), true) && isset(styleMap()[$m[2]])) {
        arSet(function (&$c) use ($m) {
            if (!is_array($c['btns'][$m[1]] ?? null)) $c['btns'][$m[1]] = [];
            $c['btns'][$m[1]]['color'] = $m[2];
        });
        answerCb(BOT_TOKEN, $cbId, '✅'); arAdminColorPick($chatId, $msgId, $m[1]); return true;
    }

    if (str_starts_with($data, 'arats_')) {
        $k = substr($data, strlen('arats_'));
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, 'ar_text', ['k' => $k]);
        $cur = (string)arVal('texts.' . $k, '');
        $back = inlineKb([[btnCb(UT('back'), 'arat_home', 'cancel')]]);
        if (arIsButtonKey($k)) {
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ متنِ دکمه‌ی <b>" . h(arBtnLabel($k)) . "</b> را بفرست.\n\n" .
                "اگر می‌خواهی ایموجیِ پریمیوم هم رویِ دکمه بنشیند، همان ایموجی را داخلِ همین پیام بفرست.\n\n" .
                "الان: <code>" . h($cur) . "</code>", $back);
        } else {
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ متنِ <b>" . h($k) . "</b> را بفرست.\n\n" .
                "جای‌گذاری‌های داخلِ آکولاد ({stake}، {min}، {max}، ...) را دست‌نخورده نگه دار.\n\n" .
                "الان:\n" . $cur, $back);
        }
        return true;
    }

    return false;
}

function arStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'ar_')) return false;
    if (!isAdmin($uid)) return false;

    $st   = getState($uid);
    $sd   = $st['data'] ?? [];
    $text = trim((string)($msg['text'] ?? ''));
    $back = inlineKb([[btnCb('💎 بازی الماسی', 'ar_home', 'admin')]]);
    $done = function ($m = '✅ ذخیره شد.') use ($uid, $chatId, $back) {
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $m, $back);
        return true;
    };

    if ($action === 'ar_word_hub') {
        if ($text === '') { sendMsg(BOT_TOKEN, $chatId, '❌ خالی نباشد.'); return true; }
        arSet(function (&$c) use ($text) { $c['word_hub'] = $text; });
        return $done();
    }
    if ($action === 'ar_rps_bounds' || $action === 'ar_bball_bounds') {
        $parts = preg_split('/\s+/', norm_fa_digits($text), -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) < 2 || !is_numeric($parts[0]) || !is_numeric($parts[1]) || (float)$parts[0] <= 0 || (float)$parts[1] < (float)$parts[0]) {
            sendMsg(BOT_TOKEN, $chatId, '❌ دو عددِ صحیح بفرست: حداقل و حداکثر (حداکثر ≥ حداقل).');
            return true;
        }
        $min = (float)$parts[0]; $max = (float)$parts[1];
        $pre = $action === 'ar_rps_bounds' ? 'rps' : 'bball';
        arSet(function (&$c) use ($pre, $min, $max) { $c[$pre . '_min'] = $min; $c[$pre . '_max'] = $max; });
        return $done();
    }
    if ($action === 'ar_text') {
        $k = (string)($sd['k'] ?? '');
        if ($k === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ چیزی برای ذخیره نیست.'); return true; }

        if (arIsButtonKey($k)) {
            if ($text === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ متن خالی نمی‌شود.'); return true; }
            $ids  = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
            $icon = $ids ? (string)$ids[0] : '';
            if ($icon !== '' && function_exists('textWithoutCustomEmoji')) {
                $clean = textWithoutCustomEmoji($msg);
                if ($clean !== '') $text = $clean;
            }
            if ($text === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ متن خالی نمی‌شود.'); return true; }
            arSet(function (&$c) use ($k, $text, $icon) {
                $c['texts'][$k] = $text;
                if (!isset($c['icons']) || !is_array($c['icons'])) $c['icons'] = [];
                $c['icons'][$k] = $icon;
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                '✅ ذخیره شد' . ($icon !== '' ? ' — ایموجیِ پریمیوم هم رویِ دکمه نشست.' : '.') . "\n\nاین‌طور دیده می‌شود:",
                inlineKb([[arBtn($k, [], 'ar_nop')]]));
            sendMsg(BOT_TOKEN, $chatId, '👆', $back);
            return true;
        }

        $html = function_exists('msgHtml') ? msgHtml($msg) : $text;
        if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ متن خالی نمی‌شود.'); return true; }
        arSet(function (&$c) use ($k, $html) { $c['texts'][$k] = $html; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد.', $back);
        return true;
    }
    return false;
}
