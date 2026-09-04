<?php
/**
 * 🎮 «بازی الماسی» — منویِ دسته‌بندی‌شده‌ی بازی‌ها + دو بازیِ تازه
 * (سنگ‌کاغذقیچی، بسکتبال). «مین» (مین‌یاب) از قبل در mine.php هست.
 *
 * عمدا جدا از games.php: آن فایل قبلا با منطقِ «چالش/دوز» (تخته‌ی
 * ۳×۳) بافته شده و هر شاخه‌ای رویِ kind شرط دارد — قاطی کردنِ یک
 * kind تازه آن‌جا یعنی ریسکِ خرابی رویِ یک بازیِ زنده و پرکاربرد.
 * این‌جا خودش یک جدولِ SQLiteِ جدا دارد (arcade_games)، ولی موجودیِ
 * الماس را از همان‌جا (gmPoints()/gmAdd() از games.php) می‌خواند —
 * دقیقا همان قراردادی که diamond.php/bank.php/vault.php/mine.php هم دارند.
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
        'rps_wait'    => 120,   // ثانیه — تا لغوِ خودکارِ لابیِ بدونِ بازیکنِ دوم
        'bball_min'   => 100,
        'bball_max'   => 1000000,
        // 🏀 احتمال‌ها جمعا باید ۱۰۰ باشند: باقی‌مانده = «خطا» (بدونِ برد)
        'bball_swish_pct'   => 35.0, 'bball_swish_mult'   => 2.0,
        'bball_perfect_pct' => 15.0, 'bball_perfect_mult' => 3.0,

        'texts' => [
            'off'   => '🔒 «بازی الماسی» فعلا خاموش است.',
            'group_only' => '🎮 بازی الماسی فقط داخلِ گروه کار می‌کند.',
            'hub'   => "💎 <b>بازی الماسی</b>\n\nیکی از بازی‌ها را انتخاب کن:",
            'btn_mine' => '⛏ مین', 'btn_rps' => '✂️ سنگ کاغذ قیچی', 'btn_bball' => '🏀 بسکتبال',
            'mine_tip' => "⛏ برای معدن بنویس: <code>مین ۵۰۰</code> (به‌جایِ ۵۰۰ هر مبلغی)",

            'ask_stake'   => '💎 با چند الماس شرط ببندیم؟',
            'bad_stake'   => '❌ شرط باید بینِ <b>{min}</b> تا <b>{max}</b> الماس باشد.',
            'low_wallet'  => '❌ الماسِ کافی نداری.\n✨ موجودی: <b>{wallet}</b>',

            'rps_lobby'   => "✂️ <b>سنگ کاغذ قیچی</b>\n\n👤 میزبان: {host}\n💎 شرط: <b>{stake}</b>\n\nمنتظرِ حریف…",
            'rps_btn_join'=> '⚔️ پیوستن به بازی',
            'rps_self_join' => '😄 نمی‌تونی با خودت بازی کنی.',
            'rps_full'    => '❌ این بازی پر است.',
            'rps_expired' => '⌛️ این بازی دیگر معتبر نیست.',
            'rps_cancel_btn' => '🚫 لغوِ بازی',
            'rps_cancelled'  => "🚫 <b>لغو شد</b>\n\n💎 شرط به {host} برگشت.",
            'rps_pick'    => "⚔️ <b>{p1}</b> در برابرِ <b>{p2}</b>\n💎 شرط: <b>{stake}</b>\n\nهرکس دکمه‌ی خودش را بزند 👇",
            'rps_picked'  => '✅ انتخابت ثبت شد — منتظرِ حریف…',
            'rps_not_player' => '🔒 این بازیِ تو نیست.',
            'rps_already' => '✅ قبلا انتخاب کرده‌ای.',
            'rps_result_win'  => "🏆 <b>{winner}</b> برد!\n\n{p1}: {c1}\n{p2}: {c2}\n\n💎 +{amount}",
            'rps_result_tie'  => "🤝 <b>مساوی!</b>\n\n{p1}: {c1}\n{p2}: {c2}\n\n💎 شرط به هر دو برگشت.",

            'bball_result_miss'    => "🏀 پرتاب کردی… ❌ <b>خطا رفت!</b>\n\n💎 −{stake}",
            'bball_result_swish'   => "🏀 پرتاب کردی… 🟢 <b>تو حلقه!</b>\n\n💎 +{amount}",
            'bball_result_perfect' => "🏀 پرتاب کردی… 🎯 <b>نتِ خالی، فوق‌العاده بود!</b>\n\n💎 +{amount}",
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
function arT($slug, $vars = []) {
    $t = (string)arVal('texts.' . $slug, arDefaults()['texts'][$slug] ?? $slug);
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}
function arNum($n) { return number_format((float)$n, 0, '.', ','); }

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

/** لابی‌هایِ رهاشده (کسی نپیوست) را با برگرداندنِ شرط لغو می‌کند — دقیقا الگویِ gmTick */
function arTick($limit = 20) {
    $db = arcadeDb();
    if (!$db) return 0;
    $cut = time() - max(30, (int)arVal('rps_wait', 120));
    $res = $db->query("SELECT id FROM arcade_games WHERE status = 'open' AND created < $cut LIMIT " . max(1, (int)$limit));
    $n = 0;
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
    return $n;
}

// ============================================================
// 🎣 منویِ هاب
// ============================================================

function arHubKb() {
    return inlineKb([
        [btnCb(arT('btn_mine'), 'ar_mine', null, 'primary')],
        [btnCb(arT('btn_rps'), 'ar_new_rps', null, 'success'), btnCb(arT('btn_bball'), 'ar_new_bball', null, 'success')],
    ]);
}

function arHandleText($text, $uid, $chatId, $name, $uname, $replyTo, $isPrivate, $msg = null) {
    if (!arOn()) return false;
    if (!empty(arVal('group_only', 1)) && $isPrivate) return false;
    $raw = trim((string)$text);
    if ($raw === '') return false;

    arTick(20);

    $pend = arPendGet($uid, $chatId);
    if ($pend && arLooksLikeAmount($raw)) return arHandleStakeAnswer($raw, $uid, $chatId, $name, $uname, $pend);

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
// ⏳ درخواستِ در انتظار (پرسیدنِ مبلغِ شرط) — همان الگویِ vlPend*
// ============================================================

function arPendKey($uid, $chat) { return $uid . '_' . $chat; }
function arPendSet($uid, $chat, $kind, $msgId) {
    mutate('arcade_states', function (&$s) use ($uid, $chat, $kind, $msgId) {
        $s[arPendKey($uid, $chat)] = ['kind' => $kind, 'msg' => (int)$msgId, 'at' => time()];
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

function arHandleStakeAnswer($raw, $uid, $chatId, $name, $uname, $pend) {
    arPendClear($uid, $chatId);
    $kind = $pend['kind'];
    $stake = (float)str_replace([',', '٬', ' '], '', norm_fa_digits($raw));

    $min = (float)arVal($kind === 'rps' ? 'rps_min' : 'bball_min', 100);
    $max = (float)arVal($kind === 'rps' ? 'rps_max' : 'bball_max', 1000000);
    if ($stake < $min || $stake > $max || floor($stake) != $stake) {
        sendMsg(BOT_TOKEN, $chatId, arT('bad_stake', ['min' => arNum($min), 'max' => arNum($max)]));
        return true;
    }
    if (!gmAdd($uid, -$stake, $name, $uname)) {
        sendMsg(BOT_TOKEN, $chatId, arT('low_wallet', ['wallet' => arNum(gmPoints($uid))]));
        return true;
    }

    if ($kind === 'rps') {
        $id = arGameCreate([
            'game' => 'rps', 'status' => 'open', 'created' => time(),
            'host' => (int)$uid, 'host_name' => $name, 'host_uname' => $uname, 'stake' => $stake,
        ]);
        $res = sendMsg(BOT_TOKEN, $chatId, arT('rps_lobby', ['host' => h($name), 'stake' => arNum($stake)]),
            inlineKb([[btnCb(arT('rps_btn_join'), 'ar_join_' . $id, null, 'primary')],
                      [btnCb(arT('rps_cancel_btn'), 'ar_cxl_' . $id, null, 'danger')]]));
        $mid = (int)($res['result']['message_id'] ?? 0);
        if ($mid) arGameSet($id, function (&$g) use ($mid) { $g['msg'] = $mid; return true; });
        return true;
    }

    // 🏀 بسکتبال — تک‌نفره، همان لحظه حل می‌شود
    arResolveBasketball($uid, $name, $uname, $chatId, $stake);
    return true;
}

// ============================================================
// ✂️ سنگ‌کاغذقیچی
// ============================================================

function arRpsJoinKb($id) {
    return inlineKb([[
        btnCb('🪨', 'ar_pick_' . $id . '_rock', null, 'primary'),
        btnCb('📄', 'ar_pick_' . $id . '_paper', null, 'primary'),
        btnCb('✂️', 'ar_pick_' . $id . '_scissors', null, 'primary'),
    ]]);
}

function arRpsBeats($a, $b) {
    return ($a === 'rock' && $b === 'scissors') || ($a === 'scissors' && $b === 'paper') || ($a === 'paper' && $b === 'rock');
}
function arRpsEmoji($p) { return ['rock' => '🪨', 'paper' => '📄', 'scissors' => '✂️'][$p] ?? '?'; }

function arResolveBasketball($uid, $name, $uname, $chatId, $stake) {
    $ticket = random_int(1, 1000);
    $swishSlice   = (int)round((float)arVal('bball_swish_pct', 35.0) * 10);
    $perfectSlice = (int)round((float)arVal('bball_perfect_pct', 15.0) * 10);

    if ($ticket <= $perfectSlice) {
        $amount = round($stake * (float)arVal('bball_perfect_mult', 3.0), 2);
        gmAdd($uid, $amount, $name, $uname);
        sendMsg(BOT_TOKEN, $chatId, arT('bball_result_perfect', ['amount' => arNum($amount)]));
    } elseif ($ticket <= $perfectSlice + $swishSlice) {
        $amount = round($stake * (float)arVal('bball_swish_mult', 2.0), 2);
        gmAdd($uid, $amount, $name, $uname);
        sendMsg(BOT_TOKEN, $chatId, arT('bball_result_swish', ['amount' => arNum($amount)]));
    } else {
        sendMsg(BOT_TOKEN, $chatId, arT('bball_result_miss', ['stake' => arNum($stake)]));
    }
}

// ============================================================
// 🔘 کال‌بک‌ها
// ============================================================

function arCallback($data, $uid, $chatId, $msgId, $cbId, $from = []) {
    $data = (string)$data;
    if (!arOn()) return false;
    $name  = (string)($from['first_name'] ?? '');
    $uname = (string)($from['username'] ?? '');

    if ($data === 'ar_mine') {
        answerCb(BOT_TOKEN, $cbId);
        sendMsg(BOT_TOKEN, $chatId, arT('mine_tip'));
        return true;
    }
    if ($data === 'ar_new_rps' || $data === 'ar_new_bball') {
        answerCb(BOT_TOKEN, $cbId);
        arPendSet($uid, $chatId, $data === 'ar_new_rps' ? 'rps' : 'bball', $msgId);
        sendMsg(BOT_TOKEN, $chatId, arT('ask_stake'));
        return true;
    }

    if (preg_match('/^ar_join_(\w+)$/', $data, $m)) {
        $id = $m[1];
        $g = arGameGet($id);
        if (!$g || $g['status'] !== 'open') { answerCb(BOT_TOKEN, $cbId, arT('rps_expired'), true); return true; }
        if ((int)$g['host'] === (int)$uid) { answerCb(BOT_TOKEN, $cbId, arT('rps_self_join'), true); return true; }

        if (!gmAdd($uid, -(float)$g['stake'], $name, $uname)) {
            answerCb(BOT_TOKEN, $cbId, arT('low_wallet', ['wallet' => arNum(gmPoints($uid))]), true);
            return true;
        }
        $ok = arGameSet($id, function (&$g) use ($uid, $name, $uname) {
            if ($g['status'] !== 'open') return false;
            $g['status'] = 'playing';
            $g['guest'] = (int)$uid; $g['guest_name'] = $name; $g['guest_uname'] = $uname;
            $g['picks'] = [];
            return true;
        });
        if (!$ok) {
            gmAdd($uid, (float)$g['stake'], $name, $uname); // بازی همون لحظه پر/بسته شد — شرط برگردد
            answerCb(BOT_TOKEN, $cbId, arT('rps_full'), true);
            return true;
        }
        answerCb(BOT_TOKEN, $cbId, '⚔️');
        editMsg(BOT_TOKEN, $chatId, $msgId,
            arT('rps_pick', ['p1' => h($g['host_name']), 'p2' => h($name), 'stake' => arNum($g['stake'])]),
            arRpsJoinKb($id));
        return true;
    }

    if (preg_match('/^ar_cxl_(\w+)$/', $data, $m)) {
        $id = $m[1];
        $g = arGameGet($id);
        if (!$g) { answerCb(BOT_TOKEN, $cbId, arT('rps_expired'), true); return true; }
        if ((int)$g['host'] !== (int)$uid) { answerCb(BOT_TOKEN, $cbId, arT('rps_not_player'), true); return true; }
        $ok = arGameSet($id, function (&$g) {
            if ($g['status'] !== 'open') return false;
            $g['status'] = 'cancelled';
            return true;
        });
        if (!$ok) { answerCb(BOT_TOKEN, $cbId, arT('rps_expired'), true); return true; }
        gmAdd((int)$g['host'], (float)$g['stake'], '', '');
        answerCb(BOT_TOKEN, $cbId, '🚫');
        editMsg(BOT_TOKEN, $chatId, $msgId, arT('rps_cancelled', ['host' => h($g['host_name'])]), null);
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
