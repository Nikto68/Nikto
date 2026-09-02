<?php
/**
 * 💣 مین‌یاب — بازیِ گروهی با همان الماسِ فروشگاه (بدون ارزِ جدید)
 *
 * کاربر می‌نویسد «مین ۵۰۰»، وارد یک میدانِ ۳×۳ می‌شود که یک خانه‌اش
 * مین است. هر خانه‌ی امن جایزه اضافه می‌کند؛ هر وقت خواست می‌تواند
 * جایزه را نقد کند. اگر روی مین بزند و کمتر از حدِ حفاظت خانه‌ی امن
 * پیدا کرده باشد، مبلغِ ورودی را باخته (که همان لحظه‌ی Join از کیف‌پول
 * کم شده)؛ اگر به‌اندازه‌ی کافی خانه‌ی امن پیدا کرده باشد، فقط بازی
 * بدونِ جایزه تمام می‌شود — نه جریمه‌ی اضافه.
 *
 * ذخیره‌سازی عمدا SQLite است، نه فایلِ JSON با قفلِ سراسری: هر بازی
 * ردیفِ خودش را دارد (درست مثلِ games.php برای چالش/قرعه)، پس کلیکِ
 * صد نفرِ هم‌زمان روی صد بازیِ جداگانه، صف نمی‌شود پشتِ هم — فقط
 * کسانی که دقیقا رویِ همان یک بازی کلیک می‌کنند قفل را می‌بینند، که
 * همان چیزی است که برای «هر کلیک فقط یک‌بار حساب شود» لازم است.
 */

// ============================================================
// ⚙️ پیکربندی
// ============================================================

function mnDefaults() {
    return [
        'on'         => false,
        'group_only' => 1,
        'word'       => 'مین',

        'entry_min' => 100,
        'entry_max' => 1000000,

        'min_safe_for_protection' => 3,
        // 🏆 جایزه‌ی افزوده‌شده به‌ازایِ رسیدن به همین شماره خانه‌ی امن
        // (نه تجمعی نوشته شده، ولی جمعِ رویِ‌همِ همین‌ها را پرداخت می‌کند)
        'rewards' => [100, 150, 250, 400, 600, 900, 1300, 1900],
        'reward_growth' => 1.5, // اگر (که با ۹ خانه پیش نمی‌آید) از آرایه فراتر رفت

        'max_active_games' => 200,
        'game_timeout'     => 1800,  // ثانیه — ۳۰ دقیقه بی‌کاری
        'expire_refund'    => 0,     // ۰=بدونِ برگشتِ ورودی، ۱=ورودی برگردد
        'game_cooldown'    => 0,     // ثانیه — فاصله‌ی دو بازیِ همان کاربر (۰=خاموش)

        'icons' => ['btn_join' => '', 'btn_cancel' => '', 'btn_cash' => ''],
        'btns'  => [
            'btn_join'   => ['color' => 'success'],
            'btn_cancel' => ['color' => 'danger'],
            'btn_cash'   => ['color' => 'primary'],
        ],

        'texts' => [
            'btn_field'  => '💣 MINE FIELD',
            'btn_join'   => '🎮 پیوستن',
            'btn_cancel' => '❌ لغو',
            'btn_cash'   => '💰 برداشت جایزه',

            'preview' => "💣 <b>MINE FINDER</b>\n\n" .
                         "💎 Entry: {entry}\n\n" .
                         "⚠️ داخل میدان مین، الماس‌های مخفی وجود دارد.\n\n" .
                         "🎯 هدف:\nقبل از برخورد با Mine، الماس جمع کن!\n\n" .
                         "━━━━━━━━━━━━━━",
            'need_join' => "🎮 اول رویِ «پیوستن» بزن.",
            'low_wallet' => "❌ <b>INSUFFICIENT DIAMONDS</b>\n\nبرای شروعِ بازی به:\n\n💎 {need} Diamond\n\nنیاز دارید.",
            'bad_amount' => "❌ مبلغ باید بینِ {min} تا {max} باشد.",
            'busy'       => "⏳ همین الان یک بازیِ دیگر دارید. اول آن را تمام کنید.",
            'cooldown'   => "⏳ چند لحظه دیگر می‌توانید بازیِ تازه شروع کنید.",
            'full'       => "⏳ الان ظرفیتِ بازی‌های فعال پره — چند لحظه دیگر امتحان کن.",
            'not_yours'  => "❌ این بازی متعلق به شما نیست.",
            'gone'       => "این بازی دیگر در دسترس نیست.",
            'already'    => "این خانه قبلا انتخاب شده.",

            'active' => "💣 <b>MINE FINDER</b>\n\n" .
                        "👤 Player: {name}\n\n" .
                        "💎 Entry: {entry}\n" .
                        "💎 Reward: {reward}\n" .
                        "🎯 Safe Picks: {picks}\n\n" .
                        "━━━━━━━━━━━━━━",

            'cancelled' => "❌ بازی لغو شد. چیزی از شما کم نشد.",

            'lost' => "💣 <b>BOOM!</b>\n\nمتأسفانه روی Mine کلیک کردی.\n\n" .
                      "🎯 Safe Picks: {picks}\n💎 Lost: {entry}\n\n❌ شما بازی را باختید.",

            'protected' => "💥 <b>MINE HIT</b>\n\nاما شما {picks} خانه‌ی امن پیدا کرده بودید!\n\n" .
                           "🛡️ Protection Rule Activated\n\n💎 Lost: 0\n🎯 Safe Picks: {picks}\n\n━━━━━━━━━━━━━━",

            'cashout' => "🏆 <b>CASH OUT SUCCESS</b>\n\n💎 Reward: +{reward}\n💎 Balance: {wallet}",

            'expired' => "⏰ <b>GAME EXPIRED</b>\n\nبازی منقضی شد.",

            'gameover_head' => "💣 <b>GAME OVER</b>\n\n",
        ],
    ];
}

function mnCfg() {
    $c = cfg()['mine'] ?? null;
    return is_array($c) ? array_replace_recursive(mnDefaults(), $c) : mnDefaults();
}

function mnSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!is_array($c['mine'] ?? null)) $c['mine'] = mnDefaults();
        $fn($c['mine']);
    });
}

function mnVal($path, $default = null) {
    $v = mnCfg();
    foreach (explode('.', $path) as $seg) {
        if (!is_array($v) || !array_key_exists($seg, $v)) return $default;
        $v = $v[$seg];
    }
    return $v;
}

function mnOn() { return !empty(mnVal('on')); }

function mnIsButtonKey($slug) {
    return in_array($slug, ['btn_field', 'btn_join', 'btn_cancel', 'btn_cash'], true);
}

function mnT($slug, $vars = []) {
    $t = (string)mnVal('texts.' . $slug, mnDefaults()['texts'][$slug] ?? $slug);
    if (mnIsButtonKey($slug)) $t = strip_tags($t);
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}

function mnBtn($key, $vars, $data) {
    $b = ['text' => mnT($key, $vars), 'callback_data' => $data];
    $color = (string)mnVal('btns.' . $key . '.color', '');
    if (function_exists('isStyle') && isStyle($color)) $b['style'] = $color;
    $ic = trim((string)mnVal('icons.' . $key, ''));
    if ($ic !== '') $b['icon_custom_emoji_id'] = $ic;
    return $b;
}

function mnNum($n) { return number_format((float)$n, 0, '.', ','); }

// ============================================================
// 🗃 داده — SQLite، هر بازی یک ردیف (مثلِ games.php)
// ============================================================

function mineDbPath() { return DATA_DIR . '/mine_games.sqlite'; }

function mineDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3')) return null;

    $path = mineDbPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    try {
        $db = new SQLite3($path);
    } catch (Throwable $e) {
        error_log('[mine] mine_games.sqlite باز نشد: ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS mine_games (
        id TEXT PRIMARY KEY, user_id INTEGER NOT NULL, status TEXT NOT NULL,
        started INTEGER NOT NULL, data TEXT NOT NULL
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_mine_user   ON mine_games(user_id, status)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_mine_status ON mine_games(status, started)');
    return $db;
}

function mnGet($id) {
    $db = mineDb();
    if (!$db) return null;
    $stmt = $db->prepare('SELECT data FROM mine_games WHERE id = :id');
    $stmt->bindValue(':id', (string)$id, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $d = json_decode($row['data'], true);
    return is_array($d) ? $d : null;
}

/** تغییرِ اتمیکِ یک بازی — قفل فقط رویِ همین یک ردیف، نه کلِ جدول */
function mnSetGame($id, callable $fn) {
    $db = mineDb();
    if (!$db) return null;
    $id = (string)$id;

    $db->exec('BEGIN IMMEDIATE');
    try {
        $stmt = $db->prepare('SELECT data FROM mine_games WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $g = $row ? json_decode($row['data'], true) : null;
        if (!is_array($g)) { $db->exec('ROLLBACK'); return null; }

        $result = $fn($g);

        $up = $db->prepare('UPDATE mine_games SET data = :data, status = :status WHERE id = :id');
        $up->bindValue(':data', json_encode($g, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        $up->bindValue(':status', (string)($g['status'] ?? ''), SQLITE3_TEXT);
        $up->bindValue(':id', $id, SQLITE3_TEXT);
        $up->execute();
        $db->exec('COMMIT');
        return $result;
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        error_log('[mine] mnSetGame خطا: ' . $e->getMessage());
        return null;
    }
}

function mnCreate($uid, $chatId, $name, $uname, $entry) {
    $db = mineDb();
    if (!$db) return null;
    $id = 'm_' . bin2hex(random_bytes(6));
    $now = time();
    $g = [
        'id' => $id, 'user_id' => (int)$uid, 'chat_id' => $chatId, 'msg_id' => 0,
        'name' => $name, 'username' => $uname,
        'entry' => (float)$entry,
        'mine_pos' => random_int(1, 9),
        'selected' => [], 'safe_picks' => 0, 'reward' => 0.0,
        'status' => 'waiting',
        'started_at' => $now, 'joined_at' => 0, 'finished_at' => 0, 'updated_at' => $now,
    ];
    $stmt = $db->prepare('INSERT INTO mine_games (id, user_id, status, started, data) VALUES (:id, :uid, :st, :t, :data)');
    $stmt->bindValue(':id', $id, SQLITE3_TEXT);
    $stmt->bindValue(':uid', (int)$uid, SQLITE3_INTEGER);
    $stmt->bindValue(':st', 'waiting', SQLITE3_TEXT);
    $stmt->bindValue(':t', $now, SQLITE3_INTEGER);
    $stmt->bindValue(':data', json_encode($g, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
    $stmt->execute();
    return $g;
}

function mnActiveCountForUser($uid) {
    $db = mineDb();
    if (!$db) return 0;
    $stmt = $db->prepare("SELECT COUNT(*) c FROM mine_games WHERE user_id = :uid AND status IN ('waiting','active')");
    $stmt->bindValue(':uid', (int)$uid, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return (int)($row['c'] ?? 0);
}

function mnActiveCountAll() {
    $db = mineDb();
    if (!$db) return 0;
    return (int)$db->querySingle("SELECT COUNT(*) FROM mine_games WHERE status = 'active'");
}

function mnCooldownLeft($uid) {
    $secs = max(0, (int)mnVal('game_cooldown', 0));
    if ($secs <= 0) return 0;
    $db = mineDb();
    if (!$db) return 0;
    $stmt = $db->prepare("SELECT data FROM mine_games WHERE user_id = :uid AND status NOT IN ('waiting','active') ORDER BY started DESC LIMIT 1");
    $stmt->bindValue(':uid', (int)$uid, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return 0;
    $d = json_decode($row['data'], true);
    $fin = (int)($d['finished_at'] ?? 0);
    if ($fin <= 0) return 0;
    $left = $fin + $secs - time();
    return max(0, $left);
}

// ============================================================
// 🏆 پاداش
// ============================================================

function mnRewardStep($pickNumber) {
    $arr = mnVal('rewards', mnDefaults()['rewards']);
    if (!is_array($arr) || !$arr) $arr = mnDefaults()['rewards'];
    $arr = array_values($arr);
    $n = count($arr);
    if ($pickNumber <= $n) return (float)$arr[$pickNumber - 1];
    $growth = max(1.0, (float)mnVal('reward_growth', 1.5));
    $extra  = $pickNumber - $n;
    return round((float)$arr[$n - 1] * pow($growth, $extra), 2);
}

function mnCumReward($safePicks) {
    $sum = 0.0;
    for ($i = 1; $i <= $safePicks; $i++) $sum += mnRewardStep($i);
    return $sum;
}

// ============================================================
// 🖼 نمایش
// ============================================================

function mnFieldRows($g, $revealAll = false) {
    $sel  = $g['selected'] ?? [];
    $mine = (int)($g['mine_pos'] ?? 0);
    $isPreview = ($g['status'] ?? '') === 'waiting';
    $rows = [];
    for ($r = 0; $r < 3; $r++) {
        $row = [];
        for ($c = 1; $c <= 3; $c++) {
            $pos = $r * 3 + $c;
            if ($revealAll && $pos === $mine) {
                $row[] = ['text' => '❌', 'callback_data' => 'mn_done'];
            } elseif (in_array($pos, $sel, true)) {
                $row[] = ['text' => '✅', 'callback_data' => 'mn_done'];
            } else {
                $cb = $isPreview ? ('mn_pre_' . $g['id']) : ('mn_pick_' . $g['id'] . '_' . $pos);
                $row[] = ['text' => '💎', 'callback_data' => $cb];
            }
        }
        $rows[] = $row;
    }
    return $rows;
}

function mnPreviewKb($g) {
    $rows = [[mnBtn('btn_field', [], 'mn_nop')]];
    foreach (mnFieldRows($g) as $r) $rows[] = $r;
    $rows[] = [mnBtn('btn_cancel', [], 'mn_cancel_' . $g['id']), mnBtn('btn_join', [], 'mn_join_' . $g['id'])];
    return inlineKb($rows);
}

function mnActiveKb($g) {
    $rows = [[mnBtn('btn_field', [], 'mn_nop')]];
    foreach (mnFieldRows($g) as $r) $rows[] = $r;
    if ((int)($g['safe_picks'] ?? 0) >= 1) $rows[] = [mnBtn('btn_cash', [], 'mn_cash_' . $g['id'])];
    return inlineKb($rows);
}

function mnFinishedKb($g) {
    $rows = [];
    foreach (mnFieldRows($g, true) as $r) $rows[] = $r;
    return inlineKb($rows);
}

function mnPreviewText($g) { return mnT('preview', ['entry' => mnNum($g['entry'])]); }
function mnActiveText($g) {
    return mnT('active', [
        'name' => h($g['name'] ?? ''), 'entry' => mnNum($g['entry']),
        'reward' => mnNum($g['reward'] ?? 0), 'picks' => (int)($g['safe_picks'] ?? 0),
    ]);
}

function mnRender($g, $chatId, $msgId = null) {
    $text = $g['status'] === 'waiting' ? mnPreviewText($g) : mnActiveText($g);
    $kb   = $g['status'] === 'waiting' ? mnPreviewKb($g) : mnActiveKb($g);
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $text, $kb);
    else        sendMsg(BOT_TOKEN, $chatId, $text, $kb);
}

// ============================================================
// 💬 شروعِ بازی
// ============================================================

function mnHandleText($text, $uid, $chatId, $name, $uname, $isPrivate) {
    if (!mnOn()) return false;
    if (!empty(mnVal('group_only', 1)) && $isPrivate) return false;

    $raw = trim((string)$text);
    if ($raw === '') return false;

    $word = trim((string)mnVal('word', 'مین'));
    $q = preg_quote($word, '/');
    if (!preg_match('/^' . $q . '\s+([\d,٬]+)$/u', norm_fa_digits($raw), $m)) return false;

    $amount = (float)str_replace([',', '٬'], '', $m[1]);
    $min = max(0, (float)mnVal('entry_min', 100));
    $max = max($min, (float)mnVal('entry_max', 1000000));
    if ($amount < $min || $amount > $max) {
        sendMsg(BOT_TOKEN, $chatId, mnT('bad_amount', ['min' => mnNum($min), 'max' => mnNum($max)]));
        return true;
    }

    if (mnActiveCountForUser($uid) > 0) { sendMsg(BOT_TOKEN, $chatId, mnT('busy')); return true; }
    $left = mnCooldownLeft($uid);
    if ($left > 0) { sendMsg(BOT_TOKEN, $chatId, mnT('cooldown')); return true; }

    if (gmPoints($uid) < $amount) {
        sendMsg(BOT_TOKEN, $chatId, mnT('low_wallet', ['need' => mnNum($amount)]));
        return true;
    }

    $g = mnCreate($uid, $chatId, $name, $uname, $amount);
    if (!$g) return true;
    $res = sendMsg(BOT_TOKEN, $chatId, mnPreviewText($g), mnPreviewKb($g));
    $mid = (int)($res['result']['message_id'] ?? 0);
    if ($mid > 0) mnSetGame($g['id'], function (&$g) use ($mid) { $g['msg_id'] = $mid; });
    return true;
}

// ============================================================
// 🔘 کال‌بک‌ها
// ============================================================

function mnExpireIfNeeded(&$g) {
    if (!in_array($g['status'], ['waiting', 'active'], true)) return false;
    $timeout = max(60, (int)mnVal('game_timeout', 1800));
    if (time() - (int)$g['started_at'] < $timeout) return false;

    if ($g['status'] === 'active' && !empty(mnVal('expire_refund', 0))) {
        gmAdd((int)$g['user_id'], (float)$g['entry'], $g['name'] ?? '', $g['username'] ?? '');
    }
    $g['status'] = 'expired';
    $g['finished_at'] = time();
    return true;
}

function mnCallback($data, $uid, $chatId, $msgId, $cbId, $from = []) {
    if ($data === 'mn_nop' || $data === 'mn_done') { answerCb(BOT_TOKEN, $cbId); return true; }
    if (!preg_match('/^mn_(pre|join|cancel|cash|pick)_(m_[0-9a-f]+)(?:_(\d))?$/', (string)$data, $m)) return false;
    if (!mnOn()) { answerCb(BOT_TOKEN, $cbId); return true; }

    [$all, $act, $gid, $pos] = array_pad($m, 4, null);
    $pos = $pos !== null ? (int)$pos : 0;

    $name  = (string)($from['first_name'] ?? '');
    $uname = (string)($from['username'] ?? '');

    $g = mnGet($gid);
    if (!$g) { answerCb(BOT_TOKEN, $cbId, mnT('gone'), true); return true; }
    if ((int)$g['user_id'] !== (int)$uid) { answerCb(BOT_TOKEN, $cbId, mnT('not_yours'), true); return true; }

    if ($act === 'pre') { answerCb(BOT_TOKEN, $cbId, mnT('need_join'), true); return true; }

    if ($act === 'cancel') {
        $out = mnSetGame($gid, function (&$g) {
            if ($g['status'] !== 'waiting') return 'gone';
            $g['status'] = 'cancelled'; $g['finished_at'] = time();
            return 'ok';
        });
        answerCb(BOT_TOKEN, $cbId, $out === 'ok' ? '' : mnT('gone'), $out !== 'ok');
        if ($out === 'ok') editMsg(BOT_TOKEN, $chatId, $msgId, mnT('cancelled'));
        return true;
    }

    if ($act === 'join') {
        $entry = (float)$g['entry'];
        $res = null;
        if ($g['status'] !== 'waiting') {
            $res = 'gone';
        } elseif (mnActiveCountAll() >= max(1, (int)mnVal('max_active_games', 200))) {
            $res = 'full';
        } elseif (!gmAdd($uid, -$entry, $name, $uname)) {
            $res = 'low';
        } else {
            $out = mnSetGame($gid, function (&$g) use ($name, $uname) {
                if ($g['status'] !== 'waiting') return 'gone';
                $g['status'] = 'active'; $g['joined_at'] = time(); $g['updated_at'] = time();
                if ($name !== '')  $g['name'] = $name;
                if ($uname !== '') $g['username'] = $uname;
                return 'ok';
            });
            if ($out !== 'ok') { gmAdd($uid, $entry, $name, $uname); $res = $out ?: 'gone'; }
            else $res = 'ok';
        }

        if ($res === 'low')  { answerCb(BOT_TOKEN, $cbId, mnT('low_wallet', ['need' => mnNum($entry)]), true); return true; }
        if ($res === 'full') { answerCb(BOT_TOKEN, $cbId, mnT('full'), true); return true; }
        if ($res !== 'ok')   { answerCb(BOT_TOKEN, $cbId, mnT('gone'), true); return true; }

        answerCb(BOT_TOKEN, $cbId, '🎮');
        $g2 = mnGet($gid);
        mnRender($g2, $chatId, $msgId);
        return true;
    }

    if ($act === 'cash') {
        $out = mnSetGame($gid, function (&$g) {
            if ($g['status'] !== 'active') return ['err' => 'gone'];
            if ((int)($g['safe_picks'] ?? 0) < 1) return ['err' => 'gone'];
            $g['status'] = 'cashed_out'; $g['finished_at'] = time(); $g['updated_at'] = time();
            return ['ok' => true, 'reward' => $g['reward']];
        });
        if (empty($out['ok'])) { answerCb(BOT_TOKEN, $cbId, mnT('gone'), true); return true; }
        gmAdd($uid, (float)$out['reward'], $name, $uname);
        answerCb(BOT_TOKEN, $cbId, '🏆');
        $g2 = mnGet($gid);
        editMsg(BOT_TOKEN, $chatId, $msgId,
            mnT('cashout', ['reward' => mnNum($out['reward']), 'wallet' => mnNum(gmPoints($uid))]),
            mnFinishedKb($g2));
        return true;
    }

    if ($act === 'pick') {
        if ($pos < 1 || $pos > 9) { answerCb(BOT_TOKEN, $cbId); return true; }

        $out = mnSetGame($gid, function (&$g) use ($pos) {
            if ($g['status'] !== 'active') return ['err' => 'gone'];
            if (in_array($pos, $g['selected'] ?? [], true)) return ['err' => 'already'];

            $g['updated_at'] = time();
            if ($pos === (int)$g['mine_pos']) {
                $minSafe = max(0, (int)mnVal('min_safe_for_protection', 3));
                $picks = (int)($g['safe_picks'] ?? 0);
                if ($picks >= $minSafe) {
                    $g['status'] = 'protected_hit';
                } else {
                    $g['status'] = 'lost';
                }
                $g['finished_at'] = time();
                return ['err' => null, 'tier' => $g['status'], 'picks' => $picks, 'entry' => $g['entry']];
            }

            $g['selected'][] = $pos;
            $g['safe_picks'] = (int)($g['safe_picks'] ?? 0) + 1;
            $g['reward'] = mnCumReward($g['safe_picks']);

            // ۸ خانه‌ی امن یعنی فقط مین مانده — دیگر چیزی برای انتخاب نیست،
            // خودش را با نقدِ خودکار تمام می‌کند تا کاربر با یک صفحه‌ی
            // بن‌بست (فقط یک خانه‌ی مین‌دار باقی‌مانده) روبه‌رو نشود.
            if ($g['safe_picks'] >= 8) {
                $g['status'] = 'won'; $g['finished_at'] = time();
                return ['err' => null, 'tier' => 'won', 'reward' => $g['reward']];
            }

            return ['err' => null, 'tier' => 'safe'];
        });

        if (!$out) { answerCb(BOT_TOKEN, $cbId, mnT('gone'), true); return true; }
        if ($out['err'] === 'already') { answerCb(BOT_TOKEN, $cbId, mnT('already'), true); return true; }
        if ($out['err'] === 'gone') { answerCb(BOT_TOKEN, $cbId, mnT('gone'), true); return true; }

        $g2 = mnGet($gid);

        if ($out['tier'] === 'safe') {
            answerCb(BOT_TOKEN, $cbId, '💎');
            mnRender($g2, $chatId, $msgId);
            return true;
        }
        if ($out['tier'] === 'won') {
            gmAdd($uid, (float)$out['reward'], $name, $uname);
            answerCb(BOT_TOKEN, $cbId, '🏆');
            editMsg(BOT_TOKEN, $chatId, $msgId,
                mnT('cashout', ['reward' => mnNum($out['reward']), 'wallet' => mnNum(gmPoints($uid))]),
                mnFinishedKb($g2));
            return true;
        }
        if ($out['tier'] === 'lost') {
            answerCb(BOT_TOKEN, $cbId, '💣', true);
            editMsg(BOT_TOKEN, $chatId, $msgId,
                mnT('gameover_head') . mnT('lost', ['picks' => $out['picks'], 'entry' => mnNum($out['entry'])]),
                mnFinishedKb($g2));
            return true;
        }
        if ($out['tier'] === 'protected_hit') {
            answerCb(BOT_TOKEN, $cbId, '🛡️');
            editMsg(BOT_TOKEN, $chatId, $msgId,
                mnT('gameover_head') . mnT('protected', ['picks' => $out['picks']]),
                mnFinishedKb($g2));
            return true;
        }
        answerCb(BOT_TOKEN, $cbId);
        return true;
    }

    return false;
}

// ============================================================
// ⏰ جاروبِ منقضی‌ها — از همان cron که بازی‌ها/شماره‌ها استفاده می‌کنند
// ============================================================

function mnTick($limit = 50) {
    $db = mineDb();
    if (!$db) return 0;
    $done = 0;
    $now = time();
    $timeout = max(60, (int)mnVal('game_timeout', 1800));

    $res = $db->query("SELECT id FROM mine_games WHERE status IN ('waiting','active') AND started <= " . ($now - $timeout) . " LIMIT " . (int)$limit);
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $gid = $row['id'];
        $out = mnSetGame($gid, function (&$g) {
            $changed = mnExpireIfNeeded($g);
            return $changed ? $g : null;
        });
        if (!$out) continue;
        $done++;
        if (!empty($out['msg_id'])) {
            editMsg(BOT_TOKEN, $out['chat_id'], (int)$out['msg_id'], mnT('expired'));
        }
    }
    return $done;
}
