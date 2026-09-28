<?php

function tlDefaults() {
    return [
        'on'    => true,
        'words' => 'ترجمه,translate',
        'who'   => 'owner',
        'icons' => ['btn_en' => '', 'btn_fa' => '', 'btn_ar' => '', 'btn_tr' => '', 'btn_ru' => ''],
        'btns'  => [
            'btn_en' => ['color' => 'primary'], 'btn_fa' => ['color' => 'success'], 'btn_ar' => ['color' => 'primary'],
            'btn_tr' => ['color' => 'primary'], 'btn_ru' => ['color' => 'primary'],
        ],
        'texts' => [
            'btn_en' => 'English', 'btn_fa' => 'فارسی', 'btn_ar' => 'العربية',
            'btn_tr' => 'Türkçe', 'btn_ru' => 'Русский',
            'ask'       => "🌐 <b>به چه زبانی ترجمه کنم؟</b>",
            'result'    => "🌐 <b>ترجمه به {lang}</b>\n\n<blockquote expandable>{text}</blockquote>",
            'no_text'   => "⚠️ روی یک پیامِ متنی ریپلای کن و بنویس «ترجمه».",
            'fail'      => "⚠️ الان ترجمه نشد — چند لحظه دیگر دوباره بزن.",
            'not_yours' => 'این دکمه‌ها مالِ کسی است که ترجمه خواست.',
            'gone'      => 'پیامِ اصلی پاک شده.',
            'slow'      => 'کمی آرام‌تر — چند ثانیه دیگر بزن.',
        ],
    ];
}

function tlCfg() {
    $c = cfg()['translate'] ?? null;
    return is_array($c) ? array_replace_recursive(tlDefaults(), $c) : tlDefaults();
}

function tlSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!is_array($c['translate'] ?? null)) $c['translate'] = [];
        $fn($c['translate']);
    });
}

function tlVal($path, $default = null) {
    $v = tlCfg();
    foreach (explode('.', $path) as $seg) {
        if (!is_array($v) || !array_key_exists($seg, $v)) return $default;
        $v = $v[$seg];
    }
    return $v;
}

function tlOn() { return !empty(tlVal('on')); }

function tlLangs() { return ['en' => 'btn_en', 'fa' => 'btn_fa', 'ar' => 'btn_ar', 'tr' => 'btn_tr', 'ru' => 'btn_ru']; }
function tlBtnKeys() { return array_values(tlLangs()); }
function tlIsButtonKey($k) { return in_array($k, tlBtnKeys(), true); }

function tlT($slug, $vars = []) {
    $t = (string)tlVal('texts.' . $slug, tlDefaults()['texts'][$slug] ?? $slug);
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}

function tlLabels() {
    return [
        'btn_en' => 'دکمه: انگلیسی', 'btn_fa' => 'دکمه: فارسی', 'btn_ar' => 'دکمه: عربی',
        'btn_tr' => 'دکمه: ترکی', 'btn_ru' => 'دکمه: روسی',
        'ask'       => 'متنِ اصلی (بالای دکمه‌ها)',
        'result'    => 'متنِ نتیجه‌ی ترجمه',
        'no_text'   => 'ریپلای روی پیامِ بی‌متن',
        'fail'      => 'ترجمه نشد',
        'not_yours' => 'پاپ‌آپ — مالِ تو نیست',
        'gone'      => 'پاپ‌آپ — پیامِ اصلی پاک شده',
        'slow'      => 'پاپ‌آپ — زیاد زدی',
    ];
}
function tlLabel($k) { return tlLabels()[$k] ?? $k; }

function tlBtn($key, $data) {
    $b = ['callback_data' => $data];
    $color = (string)tlVal('btns.' . $key . '.color', '');
    if (isStyle($color)) $b['style'] = $color;
    return btnApplyLabel($b, tlT($key), tlVal('icons.' . $key, ''));
}

function tlKb($uid) {
    $l = tlLangs();
    $b = fn($code) => tlBtn($l[$code], 'tll_' . $code . '_' . (int)$uid);
    return inlineKb([[$b('en'), $b('fa'), $b('ar')], [$b('tr'), $b('ru')]]);
}

function tlLangName($code) {
    [$txt] = btnLabelEmoji(tlT(tlLangs()[$code] ?? 'btn_en'));
    return trim($txt);
}

function tlIsWord($t) {
    $t = mb_strtolower(trim((string)$t));
    foreach (explode(',', (string)tlVal('words', '')) as $w)
        if ($t !== '' && $t === mb_strtolower(trim($w))) return true;
    return false;
}

function tlDb() {
    static $db = null;
    if ($db !== null) return $db ?: null;
    if (!class_exists('SQLite3')) return $db = false;
    try { $db = new SQLite3(DATA_DIR . '/translate.sqlite'); }
    catch (Throwable $e) { error_log('[translate] ' . $e->getMessage()); return $db = false; }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('CREATE TABLE IF NOT EXISTS tl_cache (k TEXT PRIMARY KEY, v TEXT NOT NULL, at INTEGER NOT NULL)');
    return $db;
}

function tlApi() { return defined('TL_API') ? TL_API : 'https://translate.googleapis.com/translate_a/single'; }

function tlTranslate($text, $to) {
    $text = mb_substr(trim((string)$text), 0, 1500);
    if ($text === '' || !isset(tlLangs()[$to])) return null;
    $key = md5($to . '|' . $text);
    $db  = tlDb();
    if ($db) {
        $st = $db->prepare('SELECT v FROM tl_cache WHERE k = :k AND at > :t');
        $st->bindValue(':k', $key, SQLITE3_TEXT);
        $st->bindValue(':t', time() - 7 * 86400, SQLITE3_INTEGER);
        $row = $st->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row) return (string)$row['v'];
    }
    $ch = curl_init(tlApi() . '?client=gtx&sl=auto&tl=' . $to . '&dt=t');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => 'q=' . rawurlencode($text),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded;charset=UTF-8'],
        CURLOPT_USERAGENT => 'Mozilla/5.0',
    ]);
    $res  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = ($res !== false && $code === 200) ? json_decode((string)$res, true) : null;
    if (!is_array($j) || !is_array($j[0] ?? null)) return null;
    $out = '';
    foreach ($j[0] as $seg) if (is_array($seg) && is_string($seg[0] ?? null)) $out .= $seg[0];
    $out = trim($out);
    if ($out === '') return null;
    if ($db) {
        $st = $db->prepare('INSERT OR REPLACE INTO tl_cache (k, v, at) VALUES (:k, :v, :t)');
        $st->bindValue(':k', $key, SQLITE3_TEXT);
        $st->bindValue(':v', $out, SQLITE3_TEXT);
        $st->bindValue(':t', time(), SQLITE3_INTEGER);
        $st->execute();
        if (mt_rand(1, 200) === 1) $db->exec('DELETE FROM tl_cache WHERE at < ' . (time() - 7 * 86400));
    }
    return $out;
}

function tlHandle($msg, $uid, $chatId) {
    if (!tlOn() || !tlIsWord($msg['text'] ?? '')) return false;
    $rep = $msg['reply_to_message'] ?? null;
    $src = is_array($rep) ? trim((string)($rep['text'] ?? $rep['caption'] ?? '')) : '';
    if ($src === '') {
        sendMsg(BOT_TOKEN, $chatId, tlT('no_text'), null, ['reply_to_message_id' => (int)($msg['message_id'] ?? 0), 'allow_sending_without_reply' => 'true']);
        return true;
    }
    sendMsg(BOT_TOKEN, $chatId, tlT('ask'), tlKb($uid),
        ['reply_to_message_id' => (int)$rep['message_id'], 'allow_sending_without_reply' => 'true']);
    return true;
}

function tlCallback($cb) {
    if (!preg_match('/^tll_([a-z]{2})_(\d+)$/', (string)($cb['data'] ?? ''), $m)) return false;
    $cbId = $cb['id'];
    $uid  = (int)($cb['from']['id'] ?? 0);
    $own  = (int)$m[2];
    if ($uid !== $own && tlVal('who') !== 'any' && !isAdmin($uid)) { answerCb(BOT_TOKEN, $cbId, tlT('not_yours'), true); return true; }
    $orig = $cb['message']['reply_to_message'] ?? null;
    $src  = is_array($orig) ? trim((string)($orig['text'] ?? $orig['caption'] ?? '')) : '';
    if ($src === '') { answerCb(BOT_TOKEN, $cbId, tlT('gone'), true); return true; }
    if (function_exists('maRateOk') && !maRateOk('tl', $uid, 15, 60)) { answerCb(BOT_TOKEN, $cbId, tlT('slow'), true); return true; }
    answerCb(BOT_TOKEN, $cbId, '⏳');
    $chat = $cb['message']['chat']['id'] ?? 0;
    $mid  = (int)($cb['message']['message_id'] ?? 0);
    $out  = tlTranslate($src, $m[1]);
    editMsg(BOT_TOKEN, $chat, $mid,
        $out === null ? tlT('fail') : tlT('result', ['lang' => h(tlLangName($m[1])), 'text' => h($out)]), tlKb($own));
    return true;
}

function tlTextsCfg() {
    return [
        'title' => 'متن‌ها و دکمه‌های ترجمه',
        'keys'  => array_keys((array)tlVal('texts', [])),
        'popup' => ['not_yours', 'gone', 'slow'],
        'btns'  => tlBtnKeys(),
        'label' => 'tlLabel',
        'value' => function ($k) { return (string)tlVal('texts.' . $k, ''); },
        'cb'    => 'tlat_',
        'edit'  => 'tlats_',
        'back'  => 'tl_home',
    ];
}

function tlAdminHome($chatId, $msgId = null) {
    $words = implode('، ', array_filter(array_map('trim', explode(',', (string)tlVal('words', '')))));
    $t  = "🌐 <b>ترجمه</b>\n\n";
    $t .= 'وضعیت: ' . (tlOn() ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= '🗣 کلمه‌ها: <b>' . h($words) . "</b>\n";
    $t .= '👥 چه کسی زبان را انتخاب کند: <b>' . (tlVal('who') === 'any' ? 'همه' : 'فقط کسی که ترجمه خواست') . "</b>\n\n";
    $t .= "<i>روی هر پیام (در گروه یا پیوی) ریپلای کن و یکی از کلمه‌ها را بنویس؛ ربات زیرِ همان پیام ۵ دکمه‌ی زبان می‌گذارد.</i>";
    $rows = [
        [btnCb(tlOn() ? '✅ روشن' : '❌ خاموش', 'tlax', 'info')],
        [btnCb('🗣 کلمه‌ها', 'tlaw', 'admin'), btnCb('👥 چه کسی بزند', 'tlawho', 'info')],
        [btnCb('✏️ متن‌ها و دکمه‌ها', 'tlat_home', 'admin'), btnCb('🎨 رنگِ دکمه‌ها', 'tlacolors', 'admin')],
        [btnCb('👀 پیش‌نمایش', 'tlaprev', 'confirm')],
        [btnCb(UT('back'), 'ag_games', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function tlAdminColors($chatId, $msgId) {
    $t = "🎨 <b>رنگِ دکمه‌های ترجمه</b>\n\nروی هرکدام بزن تا رنگش را عوض کنی.\n\n";
    $rows = [];
    foreach (tlBtnKeys() as $k) {
        $color = (string)tlVal('btns.' . $k . '.color', 'none');
        $t .= '• ' . h(tlLabel($k)) . ': <b>' . h(styleMap()[$color] ?? $color) . "</b>\n";
        $rows[] = [btnCb(tlLabel($k), 'tlacolk_' . $k, 'info')];
    }
    $rows[] = [btnCb(UT('back'), 'tl_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function tlAdminColorPick($chatId, $msgId, $k) {
    $cur = (string)tlVal('btns.' . $k . '.color', 'none');
    $t = "🎨 رنگِ <b>" . h(tlLabel($k)) . "</b> را انتخاب کن:\n\nالان: <b>" . h(styleMap()[$cur] ?? $cur) . "</b>";
    $rows = [];
    foreach (styleMap() as $sk => $sl) $rows[] = [btnCb(($sk === $cur ? '✅ ' : '') . $sl, 'tlacolv_' . $k . '_' . $sk, 'info')];
    $rows[] = [btnCb(UT('back'), 'tlacolors', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function tlAdminCallback($data, $chatId, $msgId, $cbId) {
    $d = (string)$data;
    if (!str_starts_with($d, 'tl_home') && !str_starts_with($d, 'tla')) return false;
    if ($d === 'tl_home') { answerCb(BOT_TOKEN, $cbId); tlAdminHome($chatId, $msgId); return true; }
    if (txRoute(tlTextsCfg(), $d, $chatId, $msgId, $cbId)) return true;

    if ($d === 'tlax') {
        tlSet(function (&$c) { $c['on'] = !tlOn(); });
        answerCb(BOT_TOKEN, $cbId, '✅'); tlAdminHome($chatId, $msgId); return true;
    }
    if ($d === 'tlawho') {
        tlSet(function (&$c) { $c['who'] = tlVal('who') === 'any' ? 'owner' : 'any'; });
        answerCb(BOT_TOKEN, $cbId, '✅'); tlAdminHome($chatId, $msgId); return true;
    }
    if ($d === 'tlaprev') {
        answerCb(BOT_TOKEN, $cbId);
        sendMsg(BOT_TOKEN, $chatId, tlT('ask'), tlKb(0));
        return true;
    }
    if ($d === 'tlaw') {
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'tl_words', []);
        sendMsg(BOT_TOKEN, $chatId,
            "🗣 کلمه‌هایی که با ریپلای ترجمه را باز می‌کنند؛ با کاما جدا کن.\n\nمثال: <code>ترجمه,translate,tr</code>\n\nالان: <code>" .
            h((string)tlVal('words', '')) . '</code>',
            inlineKb([[btnCb(UT('cancel'), 'tl_home', 'cancel')]]));
        return true;
    }
    if ($d === 'tlacolors') { answerCb(BOT_TOKEN, $cbId); tlAdminColors($chatId, $msgId); return true; }
    if (preg_match('/^tlacolk_(\w+)$/', $d, $m) && tlIsButtonKey($m[1])) {
        answerCb(BOT_TOKEN, $cbId); tlAdminColorPick($chatId, $msgId, $m[1]); return true;
    }
    if (preg_match('/^tlacolv_(\w+)_(\w+)$/', $d, $m) && tlIsButtonKey($m[1]) && isset(styleMap()[$m[2]])) {
        tlSet(function (&$c) use ($m) {
            if (!is_array($c['btns'][$m[1]] ?? null)) $c['btns'][$m[1]] = [];
            $c['btns'][$m[1]]['color'] = $m[2];
        });
        answerCb(BOT_TOKEN, $cbId, '✅'); tlAdminColorPick($chatId, $msgId, $m[1]); return true;
    }
    if (str_starts_with($d, 'tlats_')) {
        $k = substr($d, 6);
        if (!isset(tlLabels()[$k])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return true; }
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'tl_text', ['k' => $k]);
        $back = inlineKb([[btnCb(UT('back'), 'tlat_home', 'cancel')]]);
        $cur  = (string)tlVal('texts.' . $k, '');
        if (tlIsButtonKey($k)) {
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ متنِ <b>" . h(tlLabel($k)) . "</b> را بفرست.\n\n" .
                "ایموجیِ پریمیوم را داخلِ همین پیام بفرست تا رویِ دکمه بنشیند؛ ایموجیِ معمولی رویش نمی‌ماند.\n\n" .
                'الان: <code>' . h(strip_tags($cur)) . '</code>', $back);
        } else {
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ متنِ <b>" . h(tlLabel($k)) . "</b> را بفرست.\n\n" .
                ($k === 'result' ? "جای‌گذاری‌ها: <code>{lang}</code> زبان و <code>{text}</code> متنِ ترجمه‌شده.\n\n" : '') .
                "الان:\n" . $cur, $back);
        }
        return true;
    }
    return false;
}

function tlStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'tl_')) return false;
    if (!isAdmin($uid)) return false;
    $back = inlineKb([[btnCb('🌐 ترجمه', 'tl_home', 'admin')]]);
    $text = trim((string)($msg['text'] ?? ''));

    if ($action === 'tl_words') {
        $w = implode(',', array_slice(array_values(array_filter(array_map(fn($x) => mb_substr(trim($x), 0, 20),
            preg_split('/[,،\n]+/u', $text)), fn($x) => $x !== '')), 0, 8));
        if ($w === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ حداقل یک کلمه بفرست.'); return true; }
        tlSet(function (&$c) use ($w) { $c['words'] = $w; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ کلمه‌ها ثبت شد.', $back);
        return true;
    }

    if ($action === 'tl_text') {
        $k = (string)((getState($uid)['data'] ?? [])['k'] ?? '');
        if (!isset(tlLabels()[$k])) { clearState($uid); return true; }
        if (tlIsButtonKey($k)) {
            if ($text === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ متن خالی نمی‌شود.'); return true; }
            $ids  = customEmojiIds($msg);
            $icon = $ids ? (string)$ids[0] : '';
            if ($icon !== '') {
                $clean = textWithoutCustomEmoji($msg);
                $text  = $clean !== '' ? $clean : $text;
            }
            tlSet(function (&$c) use ($k, $text, $icon) {
                $c['texts'][$k] = mb_substr($text, 0, 40);
                $c['icons'][$k] = $icon;
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                '✅ ذخیره شد' . ($icon !== '' ? ' — ایموجیِ پریمیوم رویِ دکمه نشست.' : '.') . "\n\nاین‌طور دیده می‌شود:",
                inlineKb([[tlBtn($k, 'tlnop')], [btnCb('🌐 ترجمه', 'tl_home', 'admin')]]));
            return true;
        }
        $html = msgHtml($msg);
        if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ متن خالی نمی‌شود.'); return true; }
        tlSet(function (&$c) use ($k, $html) { $c['texts'][$k] = $html; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد.', $back);
        return true;
    }
    return false;
}
