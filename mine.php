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

        'entry_min' => 10,
        'entry_max' => 1000000,

        'min_safe_for_protection' => 3,
        // 🏆 جایزه‌ی افزوده‌شده به‌ازایِ رسیدن به همین شماره خانه‌ی امن
        // (نه تجمعی نوشته شده، ولی جمعِ رویِ‌همِ همین‌ها را پرداخت می‌کند)
        'rewards' => [100, 150, 250, 400, 600, 900, 1300, 1900],
        'reward_growth' => 1.5, // اگر (که با ۹ خانه پیش نمی‌آید) از آرایه فراتر رفت

        'max_active_games' => 200,
        'waiting_timeout'  => 60,    // ثانیه — تا وقتی کسی Join نکرده، بعدِ این مدت خودش بسته می‌شود
        'game_timeout'     => 1800,  // ثانیه — بازیِ Joinشده ولی بی‌کار، ۳۰ دقیقه
        'expire_refund'    => 0,     // ۰=بدونِ برگشتِ ورودی، ۱=ورودی برگردد
        'game_cooldown'    => 0,     // ثانیه — فاصله‌ی دو بازیِ همان کاربر (۰=خاموش)

        'icons' => ['btn_field' => '', 'btn_join' => '', 'btn_cancel' => '', 'btn_cash' => ''],
        'btns'  => [
            'btn_field'  => ['color' => 'none'],
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

// ============================================================
// 🔘 کال‌بک‌ها
// ============================================================
//
// 💣 ساختن بازی با تایپِ مستقیمِ «مین ۵۰۰» دیگر وجود ندارد — این کار
// حالا فقط از هابِ «بازی الماسی» (arcade.php ← ar_mine) انجام می‌شود،
// که مستقیم mnCreate()+mnRender() را صدا می‌زند. بقیه‌ی این فایل
// (پیوستن، انتخابِ خانه، نقدکردن، انقضا) دست‌نخورده مانده.

function mnExpireIfNeeded(&$g) {
    if (!in_array($g['status'], ['waiting', 'active'], true)) return false;
    $timeout = $g['status'] === 'waiting'
        ? max(10, (int)mnVal('waiting_timeout', 60))
        : max(60, (int)mnVal('game_timeout', 1800));
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
    $waitTimeout = max(10, (int)mnVal('waiting_timeout', 60));
    $gameTimeout = max(60, (int)mnVal('game_timeout', 1800));

    $res = $db->query(
        "SELECT id FROM mine_games WHERE " .
        "(status = 'waiting' AND started <= " . ($now - $waitTimeout) . ") OR " .
        "(status = 'active'  AND started <= " . ($now - $gameTimeout) . ") " .
        "LIMIT " . (int)$limit
    );
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

// ============================================================
// ✏️ ویرایشگرِ داخلِ ربات — /panel ← 💣 مین‌یاب
// ============================================================
// همان الگویِ دقیقِ bank.php/games.php.

function mnLabels() {
    return [
        'preview' => 'پیش‌نمایشِ بازی', 'need_join' => 'یادآوریِ پیوستن',
        'low_wallet' => 'کیف‌پول کم', 'bad_amount' => 'مبلغِ نامعتبر',
        'busy' => 'یک بازیِ فعالِ دیگر هست', 'cooldown' => 'کول‌داونِ بازی',
        'full' => 'ظرفیتِ بازی‌ها پره', 'not_yours' => 'مالِ تو نیست', 'gone' => 'بازی تمام شده',
        'already' => 'این خانه قبلا انتخاب شده', 'active' => 'کارتِ بازیِ فعال',
        'cancelled' => 'بازی لغو شد', 'lost' => 'باخت (روی مین)',
        'protected' => 'برخورد با مین، ولی محافظت‌شده', 'cashout' => 'برداشتِ جایزه — موفق',
        'expired' => 'بازی منقضی شد', 'gameover_head' => 'سرِ پیامِ پایانِ بازی',
        'btn_field' => 'دکمه: سرِ میدان', 'btn_join' => 'دکمه: پیوستن',
        'btn_cancel' => 'دکمه: لغو', 'btn_cash' => 'دکمه: برداشتِ جایزه',
    ];
}
function mnLabel($k) { return mnLabels()[$k] ?? $k; }
function mnBtnKeys() { return ['btn_field', 'btn_join', 'btn_cancel', 'btn_cash']; }

function mnAdminHome($chatId, $msgId = null) {
    $c = mnCfg();
    $t  = "💣 <b>مین‌یاب</b>\n\n";
    $t .= 'وضعیت: ' . (mnOn() ? '✅ روشن' : '❌ خاموش') . "\n\n";
    $t .= 'کلمه‌ی شروع: <code>' . h($c['word']) . " ۵۰۰</code>\n\n";
    $t .= '💎 بازه‌ی ورودی: <b>' . mnNum($c['entry_min']) . '</b> تا <b>' . mnNum($c['entry_max']) . "</b>\n";
    $t .= '🛡 حداقلِ خانه‌ی امن برایِ حفاظت: <b>' . (int)$c['min_safe_for_protection'] . "</b>\n";
    $t .= '⏰ مهلتِ بی‌کاری (بعدِ Join): <b>' . (int)round($c['game_timeout'] / 60) . "</b> دقیقه\n";
    $t .= '⏰ مهلتِ Joinنشدن: <b>' . (int)($c['waiting_timeout'] ?? 60) . "</b> ثانیه\n";

    $rows = [
        [btnCb(mnOn() ? '✅ روشن' : '❌ خاموش', 'mnax', 'info')],
        [btnCb('🗣 کلمه‌ی شروع', 'mnaw_home', 'admin'), btnCb('✏️ متن‌ها و دکمه‌ها', 'mnat_home', 'admin')],
        [btnCb('🎨 رنگِ دکمه‌ها', 'mnacolors', 'admin')],
        [btnCb('💎 بازه‌ی ورودی', 'mnarange', 'admin'), btnCb('🛡 حدِ حفاظت', 'mnasafe', 'admin')],
        [btnCb('🏆 جایزه‌ی هر خانه', 'mnarewards', 'admin')],
        [btnCb('⏰ مهلتِ بی‌کاری (دقیقه)', 'mnatimeout', 'admin'), btnCb('⏰ مهلتِ Joinنشدن (ثانیه)', 'mnawaiting', 'admin')],
        [btnCb(UT('back'), 'adm_home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function mnAdminTexts($chatId, $msgId, $page = 0) {
    $keys = array_keys((array)mnVal('texts', []));
    $per  = 12;
    $tot  = max(1, (int)ceil(count($keys) / $per));
    $page = max(0, min($tot - 1, (int)$page));
    $slice = array_slice($keys, $page * $per, $per);

    $t  = "✏️ <b>متن‌ها و دکمه‌های مین‌یاب</b> — صفحه " . ($page + 1) . " از {$tot}\n\n";
    $t .= "هرچه بنویسید عینا همان می‌رود: ایموجیِ پریمیوم و quote سالم می‌مانند.\n\n";
    $rows = [];
    foreach ($slice as $k) {
        $v = (string)mnVal('texts.' . $k, '');
        $t .= '• <b>' . h(mnLabel($k)) . '</b>: <code>' .
              h(mb_substr(str_replace("\n", ' ', strip_tags($v)), 0, 34)) . "</code>\n";
        $rows[] = [btnCb(mnLabel($k), 'mnats_' . $k, 'admin')];
    }
    $nav = [];
    if ($page > 0)        $nav[] = btnCb('◀️', 'mnat_' . ($page - 1), 'nav');
    if ($page < $tot - 1) $nav[] = btnCb('▶️', 'mnat_' . ($page + 1), 'nav');
    if ($nav) $rows[] = $nav;
    $rows[] = [btnCb(UT('back'), 'mn_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3800), inlineKb($rows));
}

function mnAdminColors($chatId, $msgId) {
    $t = "🎨 <b>رنگِ دکمه‌های مین‌یاب</b>\n\nروی هرکدام بزن تا رنگش را عوض کنی.\n\n";
    $rows = [];
    foreach (mnBtnKeys() as $k) {
        $color = (string)mnVal('btns.' . $k . '.color', 'none');
        $t .= '• ' . h(mnLabel($k)) . ': <b>' . h(styleMap()[$color] ?? $color) . "</b>\n";
        $rows[] = [btnCb(mnT($k, []) ?: mnLabel($k), 'mnacolk_' . $k, 'info')];
    }
    $rows[] = [btnCb(UT('back'), 'mn_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function mnAdminColorPick($chatId, $msgId, $k) {
    $cur = (string)mnVal('btns.' . $k . '.color', 'none');
    $t = "🎨 رنگِ <b>" . h(mnLabel($k)) . "</b> را انتخاب کن:\n\nالان: <b>" . h(styleMap()[$cur] ?? $cur) . "</b>";
    $rows = [];
    foreach (styleMap() as $sk => $sl) $rows[] = [btnCb(($sk === $cur ? '✅ ' : '') . $sl, 'mnacolv_' . $k . '_' . $sk, 'info')];
    $rows[] = [btnCb(UT('back'), 'mnacolors', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function mnAdminRewards($chatId, $msgId) {
    $r = mnVal('rewards', mnDefaults()['rewards']);
    $t = "🏆 <b>جایزه‌ی هر خانه‌ی امن</b>\n\nهرکدام، جایزه‌ی همان شماره‌خانه است (نه تجمعی).\n\n";
    $rows = [];
    for ($i = 1; $i <= 8; $i++) {
        $t .= '• خانه‌ی #' . $i . ': <b>' . mnNum($r[$i - 1] ?? 0) . "</b>\n";
        $rows[] = [btnCb('#' . $i . ' — ' . mnNum($r[$i - 1] ?? 0), 'mnarw_' . $i, 'admin')];
    }
    $rows[] = [btnCb(UT('back'), 'mn_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function mnAdminCallback($data, $chatId, $msgId, $cbId) {
    if (!str_starts_with((string)$data, 'mn')) return false;

    if ($data === 'mn_home') { answerCb(BOT_TOKEN, $cbId); mnAdminHome($chatId, $msgId); return true; }
    if ($data === 'mnax') {
        mnSet(function (&$c) { $c['on'] = empty($c['on']); });
        answerCb(BOT_TOKEN, $cbId, '✅'); mnAdminHome($chatId, $msgId); return true;
    }

    if ($data === 'mnaw_home') { answerCb(BOT_TOKEN, $cbId); mnAdminWord($chatId, $msgId); return true; }
    if ($data === 'mnat_home') { answerCb(BOT_TOKEN, $cbId); mnAdminTexts($chatId, $msgId, 0); return true; }
    if (preg_match('/^mnat_(\d+)$/', $data, $m)) { answerCb(BOT_TOKEN, $cbId); mnAdminTexts($chatId, $msgId, (int)$m[1]); return true; }
    if ($data === 'mnarewards') { answerCb(BOT_TOKEN, $cbId); mnAdminRewards($chatId, $msgId); return true; }

    if ($data === 'mnacolors') { answerCb(BOT_TOKEN, $cbId); mnAdminColors($chatId, $msgId); return true; }
    if (preg_match('/^mnacolk_(\w+)$/', $data, $m) && in_array($m[1], mnBtnKeys(), true)) {
        answerCb(BOT_TOKEN, $cbId); mnAdminColorPick($chatId, $msgId, $m[1]); return true;
    }
    if (preg_match('/^mnacolv_(\w+)_(\w+)$/', $data, $m) && in_array($m[1], mnBtnKeys(), true) && isset(styleMap()[$m[2]])) {
        mnSet(function (&$c) use ($m) {
            if (!is_array($c['btns'][$m[1]] ?? null)) $c['btns'][$m[1]] = [];
            $c['btns'][$m[1]]['color'] = $m[2];
        });
        answerCb(BOT_TOKEN, $cbId, '✅'); mnAdminColorPick($chatId, $msgId, $m[1]); return true;
    }

    if (preg_match('/^mnarw_(\d)$/', $data, $m)) {
        $i = (int)$m[1];
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, 'mn_reward', ['i' => $i]);
        sendMsg(BOT_TOKEN, $chatId, "🏆 جایزه‌ی خانه‌ی امنِ #{$i} چند الماس باشد؟",
            inlineKb([[btnCb('انصراف', 'mnarewards', 'cancel')]]));
        return true;
    }

    $asks = [
        'mnasafe'    => ['mn_safe',    "🛡 چند خانه‌ی امنِ پیاپی، از جریمه‌ی برخورد با مین محافظت کند؟ (۰ تا ۸)"],
        'mnatimeout' => ['mn_timeout', "⏰ بازیِ Joinشده ولی بی‌کار، بعدِ چند دقیقه منقضی شود؟"],
        'mnawaiting' => ['mn_waiting', "⏰ اگر کسی Join نکرد، بعدِ چند ثانیه بازی خودش بسته شود؟"],
    ];
    if (isset($asks[$data])) {
        [$act, $ask] = $asks[$data];
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, $act, []);
        sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnCb('انصراف', 'mn_home', 'cancel')]]));
        return true;
    }
    if ($data === 'mnarange') {
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, 'mn_range', []);
        sendMsg(BOT_TOKEN, $chatId, "💎 کف و سقفِ ورودی را با خط تیره بفرست.\nمثال: <code>10-1000000</code>",
            inlineKb([[btnCb('انصراف', 'mn_home', 'cancel')]]));
        return true;
    }

    if (str_starts_with($data, 'mnats_')) {
        $k = substr($data, 6);
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, 'mn_text', ['k' => $k]);
        $cur  = (string)mnVal('texts.' . $k, '');
        $back = inlineKb([[btnCb(UT('back'), 'mnat_home', 'cancel')]]);
        if (mnIsButtonKey($k)) {
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ متنِ دکمه‌ی <b>" . h(mnLabel($k)) . "</b> را بفرست.\n\n" .
                "اگر می‌خواهی ایموجیِ پریمیوم هم رویِ دکمه بنشیند، همان ایموجی را داخلِ همین پیام بفرست.\n\n" .
                "الان: <code>" . h($cur) . "</code>", $back);
        } else {
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ متنِ <b>" . h(mnLabel($k)) . "</b> را بفرست.\n\n" .
                "جای‌گذاری‌های داخلِ آکولاد ({name}، {entry}، ...) را دست‌نخورده نگه دار.\n\nالان:\n" . $cur, $back);
        }
        return true;
    }
    if ($data === 'mnaws_word') {
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, 'mn_word', []);
        sendMsg(BOT_TOKEN, $chatId, "🗣 کلمه‌ی شروعِ بازی را بفرست (مثلا «مین»):",
            inlineKb([[btnCb('انصراف', 'mnaw_home', 'cancel')]]));
        return true;
    }

    return false;
}

function mnAdminWord($chatId, $msgId) {
    $t = "🗣 <b>کلمه‌ی شروع</b>\n\nهمین کلمه + یک عدد، بازی می‌سازد — مثلا <code>" . h(mnVal('word', 'مین')) . " ۵۰۰</code>.\n\n";
    $rows = [[btnCb('✏️ عوض کردنِ کلمه', 'mnaws_word', 'admin')], [btnCb(UT('back'), 'mn_home', 'nav')]];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function mnStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'mn_')) return false;
    if (!isAdmin($uid)) return false;

    $st   = getState($uid);
    $sd   = $st['data'] ?? [];
    $text = trim((string)($msg['text'] ?? ''));
    $back = inlineKb([[btnCb('💣 مین‌یاب', 'mn_home', 'admin')]]);
    $done = function ($m = "✅ ذخیره شد.") use ($uid, $chatId, $back) {
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $m, $back);
        return true;
    };

    if ($action === 'mn_safe') {
        $v = (int)norm_fa_digits($text);
        if ($v < 0 || $v > 8) { sendMsg(BOT_TOKEN, $chatId, "⚠️ بین ۰ تا ۸."); return true; }
        mnSet(function (&$c) use ($v) { $c['min_safe_for_protection'] = $v; });
        return $done('✅ حدِ حفاظت: ' . $v . ' خانه‌ی امن');
    }
    if ($action === 'mn_timeout') {
        $v = (int)norm_fa_digits($text);
        if ($v < 1 || $v > 1440) { sendMsg(BOT_TOKEN, $chatId, "⚠️ بین ۱ تا ۱۴۴۰ دقیقه."); return true; }
        mnSet(function (&$c) use ($v) { $c['game_timeout'] = $v * 60; });
        return $done('✅ مهلتِ بی‌کاری: ' . $v . ' دقیقه');
    }
    if ($action === 'mn_waiting') {
        $v = (int)norm_fa_digits($text);
        if ($v < 10 || $v > 3600) { sendMsg(BOT_TOKEN, $chatId, "⚠️ بین ۱۰ تا ۳۶۰۰ ثانیه."); return true; }
        mnSet(function (&$c) use ($v) { $c['waiting_timeout'] = $v; });
        return $done('✅ مهلتِ Joinنشدن: ' . $v . ' ثانیه');
    }
    if ($action === 'mn_range') {
        if (!preg_match('/^\s*([\d,٬]+)\s*[-–ـ]\s*([\d,٬]+)\s*$/u', norm_fa_digits($text), $m)) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ مثل <code>10-1000000</code> بفرست."); return true;
        }
        $lo = (float)str_replace([',', '٬'], '', $m[1]);
        $hi = (float)str_replace([',', '٬'], '', $m[2]);
        if ($lo < 1 || $hi <= $lo) { sendMsg(BOT_TOKEN, $chatId, "⚠️ سقف باید از کف بزرگ‌تر باشد."); return true; }
        mnSet(function (&$c) use ($lo, $hi) { $c['entry_min'] = $lo; $c['entry_max'] = $hi; });
        return $done();
    }
    if ($action === 'mn_reward') {
        $i = (int)($sd['i'] ?? 0);
        $v = (float)str_replace([',', '،'], '', norm_fa_digits($text));
        if ($i < 1 || $i > 8 || $v < 0) { sendMsg(BOT_TOKEN, $chatId, "⚠️ عددِ معتبر بفرست."); return true; }
        mnSet(function (&$c) use ($i, $v) {
            $r = $c['rewards'] ?? mnDefaults()['rewards'];
            $r[$i - 1] = $v;
            $c['rewards'] = $r;
        });
        return $done('✅ جایزه‌ی خانه‌ی #' . $i . ': ' . mnNum($v));
    }
    if ($action === 'mn_word') {
        if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ خالی نمی‌شود."); return true; }
        mnSet(function (&$c) use ($text) { $c['word'] = $text; });
        return $done();
    }
    if ($action === 'mn_text') {
        $k = (string)($sd['k'] ?? '');
        if ($k === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ چیزی برای ذخیره نیست."); return true; }

        if (mnIsButtonKey($k)) {
            if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
            $ids  = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
            $icon = $ids ? (string)$ids[0] : '';
            if ($icon !== '' && function_exists('textWithoutCustomEmoji')) {
                $clean = textWithoutCustomEmoji($msg);
                if ($clean !== '') $text = $clean;
            }
            if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
            mnSet(function (&$c) use ($k, $text, $icon) {
                $c['texts'][$k] = $text;
                if (!isset($c['icons']) || !is_array($c['icons'])) $c['icons'] = [];
                $c['icons'][$k] = $icon;
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                "✅ ذخیره شد" . ($icon !== '' ? " — ایموجیِ پریمیوم هم رویِ دکمه نشست." : '.') . "\n\nاین‌طور دیده می‌شود:",
                inlineKb([[mnBtn($k, [], 'mn_nop')]]));
            sendMsg(BOT_TOKEN, $chatId, '👆', $back);
            return true;
        }

        $html = msgHtml($msg);
        if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
        mnSet(function (&$c) use ($k, $html) { $c['texts'][$k] = $html; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد.", $back);
        return true;
    }
    clearState($uid);
    return true;
}
