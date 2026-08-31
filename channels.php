<?php
/**
 * 📡 کانال‌های متصل
 *
 * تا حالا گزارش‌ها پراکنده بودند: رسید شارژ فقط برای ادمین می‌رفت و
 * گزارش خرید هم زیر تنظیماتِ تک‌تک محصول‌ها گم بود. اینجا هر جریان
 * مقصد خودش را دارد، جدا از بقیه، با متن و دکمه‌های خودش:
 *
 *   🧾 رسید شارژ حساب   → هرکس کیف پولش را شارژ کرد، رسیدش اینجا می‌افتد
 *   🛒 گزارش خرید       → هر فروشِ ربات و مینی‌اپ، هرکدام مستقل و یک‌بار تنظیم
 *
 * هرکدام می‌تواند یک گروه با تاپیک باشد؛ لینکِ همان تاپیک را بدهید،
 * آیدی و شماره‌ی تاپیک خودش درمی‌آید.
 */

if (!defined('CH_LIB')) define('CH_LIB', 1);

// ============================================================
// ⚙️ پیکربندی
// ============================================================

/**
 * هر بخش، کانال خودش — نه بیشتر.
 *
 * قبلا هر دسته‌ی مینی‌اپ (هر زیرمجموعه‌ی «خدمات تلگرام»/«شماره مجازی»)
 * یک ردیفِ جدا اینجا می‌ساخت؛ با چند دسته این صفحه پر می‌شد از ردیف‌های
 * ریزی که ربطی به «کانالِ متصل» ندارند. اینجا فقط جریان‌های گزارشِ
 * خرید است، هرکدام مستقل: کل «خدمات تلگرام» یک مقصد، کل «شماره مجازی»
 * یک مقصد — با یک دکمه‌ی شیشه‌ای مثل بقیه.
 */
function chStreams() {
    return [
        'topup'   => ['🧾 رسید شارژ حساب', 'رسیدهای شارژ کیف پول — از همین‌جا تایید کنید.'],
        'mini_tg' => ['🌟 خدمات تلگرام', 'هر فروشِ مینی‌اپ خدمات تلگرام — استارز، پریمیوم، گیفت و بقیه‌ی دسته‌ها.'],
        'mini_num'=> ['☎️ شماره مجازی', 'هر فروشِ مینی‌اپ شماره مجازی.'],
        'mem_vip' => ['💎 ممبر ویژه', 'سفارش‌های ممبر ویژه.'],
        'mem_ok'  => ['✅ ممبر اخلاقی', 'سفارش‌های ممبر اخلاقی.'],
        'mem_no'  => ['🔞 ممبر غیراخلاقی', 'سفارش‌های ممبر غیراخلاقی.'],
        'buy'     => ['🛒 بقیه‌ی فروش‌ها', 'هر فروشی که در دسته‌های بالا نیفتد.'],
        'tech'    => ['🛠 گزارش فنی', 'خطا، افت سرعت، کمبود بودجه — هرچیزی که نشان می‌دهد ربات دارد مشکل پیدا می‌کند.'],
    ];
}

/**
 * یک محصول به کدام کانال می‌رود؟
 *
 * برای مینی‌اپ‌ها از روی خودِ اپ (نه دسته‌ی داخلی‌اش)، و برای محصول‌های
 * ربات از روی نام محصول — چون دسته‌بندیِ ممبر همان‌جا در نامش است.
 * هرچه جا نیفتاد، می‌رود در «بقیه‌ی فروش‌ها».
 */
function chStreamFor($app, $productName = '', $cat = '') {
    $app = strtolower(trim((string)$app));
    if ($app !== '') {
        if ($app === 'tg')  return 'mini_tg';
        if ($app === 'num') return 'mini_num';
        return 'buy';
    }

    $n = chNormFa(mb_strtolower(trim((string)$productName)));
    if ($n !== '') {
        foreach (chMemberWords() as $stream => $words)
            foreach ($words as $w) {
                $w = chNormFa(mb_strtolower(trim($w)));
                if ($w !== '' && str_contains($n, $w)) return $stream;
            }
    }
    return 'buy';
}

/** کلمه‌هایی که دسته‌ی ممبر را می‌سازند — از پنل قابل تغییر */
function chMemberWords() {
    $d = [
        'mem_no'  => 'غیراخلاقی,غیر اخلاقی,نامناسب',
        'mem_vip' => 'ویژه,vip,پریمیوم ممبر',
        'mem_ok'  => 'اخلاقی,عادی,ممبر',
    ];
    $out = [];
    foreach ($d as $k => $def) {
        $raw = (string)(cfg()['channels']['words'][$k] ?? $def);
        $out[$k] = array_values(array_filter(array_map('trim', explode(',', $raw)), fn($x) => $x !== ''));
    }
    return $out;
}

/** فارسی‌سازی ساده برای مقایسه: ی و ک عربی، نیم‌فاصله */
function chNormFa($s) {
    return strtr((string)$s, ['ي' => 'ی', 'ك' => 'ک', "\u{200c}" => ' ', '‌' => ' ']);
}

function chDefaults() {
    // ⚠️ عمداً بدون هیچ ایموجیِ معمولی — تنها ایموجی‌ای که اینجا دیده
    // می‌شود، ایموجیِ پریمیومِ خودِ همین جریان است (فیلدِ premium_icon،
    // از دکمه‌ی «🌟 ایموجیِ پریمیوم» تو صفحه‌ی خودِ همین جریان)، که
    // chBuy جلوی {icon} می‌گذارد. اگر چیزی تنظیم نشده باشد، {icon} خالی
    // می‌ماند.
    $saleText = "{icon} <b>{section}</b>\n\n" .
                "{user}\n" .
                "<blockquote>محصول: {product}\n" .
                "تعداد: <b>{qty}</b>\n" .
                "مبلغ: <b>{amount}</b> تومان</blockquote>\n" .
                "<code>{code}</code>\n{date}";
    $saleBtns = [
        ['on' => 1, 'text' => '🛒 ثبت سفارش', 'url' => '', 'color' => 'success', 'icon' => ''],
        ['on' => 1, 'text' => '💬 پشتیبانی',  'url' => '', 'color' => 'primary', 'icon' => ''],
    ];

    $out = [
        'topup' => [
            'on' => false, 'chat_id' => '', 'thread_id' => 0,
            'text' => "<b>رسید شارژ حساب</b>\n\n" .
                      "{user}\n<code>{uid}</code>\n" .
                      "<blockquote>مبلغ: <b>{amount}</b> تومان\n" .
                      "موجودی بعد از تایید: <b>{balance}</b> تومان</blockquote>\n" .
                      "<code>{code}</code>\n{date}",
            'photo'   => true,     // عکس رسید هم فرستاده شود
            'buttons' => [
                ['on' => 1, 'text' => '🤖 ربات', 'url' => '', 'color' => 'primary', 'icon' => ''],
            ],
        ],
        'tech' => [
            'on' => false, 'chat_id' => '', 'thread_id' => 0,
            'text' => "<b>گزارش فنی</b>\n\n{text}\n\n{date}",
            'photo' => false, 'buttons' => [],
        ],
    ];

    // بقیه‌ی جریان‌ها همه فروشند و یک شکل دارند
    foreach (chStreams() as $k => [$label, $desc]) {
        if ($k === 'topup' || $k === 'tech') continue;
        $out[$k] = [
            'on' => false, 'chat_id' => '', 'thread_id' => 0,
            'text' => $saleText, 'photo' => false, 'buttons' => $saleBtns,
            'premium_icon' => '',   // 🌟 کدِ ایموجیِ پریمیوم — خالی یعنی {icon} چیزی نشان نمی‌دهد
        ];
    }
    return $out;
}

function chCfg() {
    $c = cfg()['channels'] ?? null;
    $out = chDefaults();
    if (!is_array($c)) return $out;
    foreach ($out as $k => $def) {
        if (!isset($c[$k]) || !is_array($c[$k])) continue;
        $row = array_replace($def, $c[$k]);
        // فهرست دکمه‌ها عینا همان چیزی که ذخیره شده — نه ادغام عمقی،
        // وگرنه حذف یک دکمه هیچ‌وقت اثر نمی‌کند.
        if (isset($c[$k]['buttons']) && is_array($c[$k]['buttons'])) {
            $row['buttons'] = [];
            foreach (array_values($c[$k]['buttons']) as $i => $b) {
                $base = $def['buttons'][$i] ?? ['on' => 1, 'text' => '', 'url' => '', 'color' => 'primary', 'icon' => ''];
                $row['buttons'][] = array_replace($base, is_array($b) ? $b : []);
            }
        }
        $out[$k] = $row;
    }
    return $out;
}

function chOf($stream) { return chCfg()[$stream] ?? chDefaults()['buy']; }

function chSet($stream, callable $fn) {
    cfgSet(function (&$c) use ($stream, $fn) {
        if (!isset($c['channels']) || !is_array($c['channels'])) $c['channels'] = [];
        if (!isset($c['channels'][$stream]) || !is_array($c['channels'][$stream]))
            $c['channels'][$stream] = chDefaults()[$stream] ?? [];
        $fn($c['channels'][$stream]);
    });
}

/** آماده‌ی ارسال است؟ */
function chReady($stream) {
    $s = chOf($stream);
    return !empty($s['on']) && trim((string)$s['chat_id']) !== '';
}

// ============================================================
// 📤 فرستادن
// ============================================================

/**
 * یک گزارش را روی کانالِ همان جریان می‌فرستد.
 * $vars جای‌گذاری‌های {…}، $photo شناسه‌ی عکس (رسید) اگر داشت.
 * $extraRows ردیف‌های اینلاینِ اضافه (مثلا تایید/رد) — جلوترِ دکمه‌های
 * تنظیم‌شده‌ی خودِ ادمین می‌نشینند.
 * برگشت false یعنی نرفت — ولی هیچ‌وقت جریانِ اصلی را نمی‌شکند.
 */
function chSend($stream, array $vars, $photo = null, array $extraRows = []) {
    if (!chReady($stream)) return false;
    $s = chOf($stream);

    $vars += ['date' => chDate()];
    $text = chFill((string)$s['text'], $vars);
    if (trim($text) === '') return false;

    $extra = [];
    if ((int)$s['thread_id'] > 0) $extra['message_thread_id'] = (int)$s['thread_id'];

    $kb = chKeyboard($s);
    if ($extraRows) {
        $rows = array_merge($extraRows, $kb['inline_keyboard'] ?? []);
        $kb   = ['inline_keyboard' => $rows];
    }

    if ($photo !== null && !empty($s['photo'])) {
        $d = array_merge([
            'chat_id' => $s['chat_id'], 'photo' => $photo,
            'caption' => mb_substr($text, 0, 1000), 'parse_mode' => 'HTML',
        ], $extra);
        if ($kb) $d['reply_markup'] = json_encode($kb);
        $r = tg(BOT_TOKEN, 'sendPhoto', $d);
        if (!empty($r['ok'])) return true;
        // عکس نرفت؟ لااقل متن برود
    }

    $r = sendMsg(BOT_TOKEN, $s['chat_id'], $text, $kb, $extra);
    if (empty($r['ok'])) {
        chWarn($stream, (string)($r['description'] ?? 'بی‌پاسخ'));
        return false;
    }
    return true;
}

/** به ادمین بگو کدام کانال جواب نمی‌دهد — ولی نه هر بار، که آزاردهنده شود */
function chWarn($stream, $why) {
    if (!function_exists('adminAlertOnce')) return;
    [$label] = chStreams()[$stream] ?? [$stream];
    adminAlertOnce('ch_' . $stream,
        "📡 <b>گزارش به کانال نرفت</b>\n\n" . h($label) . "\n<code>" . h(mb_substr($why, 0, 180)) . "</code>\n\n" .
        "پنل ← 📡 کانال‌های متصل — ربات باید در آن گروه ادمین باشد.");
}

/**
 * 🔗 لینکِ پیش‌فرضِ دکمه‌ها — خودِ ربات.
 *
 * دکمه‌ی «ثبت سفارش» از اول در متنِ گزارش بود ولی لینکش خالی می‌ماند و
 * دکمه‌ی بی‌لینک هم دور انداخته می‌شد؛ یعنی هیچ‌وقت دیده نمی‌شد. حالا
 * اگر ادمین لینکی نگذارد، دکمه به خودِ ربات وصل می‌شود.
 *
 * <code>{bot}</code> هم هرجای لینک نوشته شود، نامِ ربات می‌نشیند.
 */
function chBotLink() {
    static $u = null;
    if ($u === null) $u = function_exists('botUsername') ? trim((string)botUsername()) : '';
    return $u !== '' ? 'https://t.me/' . ltrim($u, '@') : '';
}

function chButtonUrl($b) {
    $url = trim((string)($b['url'] ?? ''));
    if ($url !== '') return str_replace('{bot}', ltrim(chBotLink() !== '' ? (string)botUsername() : '', '@'), $url);
    // بدون لینک: اگر خودش را «ثبت سفارش» می‌داند، ببرش به ربات
    return chBotLink();
}

function chKeyboard($s) {
    $rows = [];
    $line = [];
    foreach ((array)($s['buttons'] ?? []) as $b) {
        if (empty($b['on'])) continue;
        $url = chButtonUrl($b);
        $txt = trim((string)($b['text'] ?? ''));
        if ($url === '' || $txt === '') continue;
        $btn = ['text' => $txt, 'url' => $url];
        if (function_exists('gs') && ($st = gs((string)($b['color'] ?? '')))) $btn['style'] = $st;
        if (trim((string)($b['icon'] ?? '')) !== '') $btn['icon_custom_emoji_id'] = (string)$b['icon'];
        $line[] = $btn;
        if (count($line) === 2) { $rows[] = $line; $line = []; }
    }
    if ($line) $rows[] = $line;
    return $rows ? ['inline_keyboard' => $rows] : null;
}

function chFill($tpl, array $vars) {
    $map = [];
    foreach ($vars as $k => $v) $map['{' . $k . '}'] = (string)$v;
    return strtr((string)$tpl, $map);
}

function chDate() {
    if (function_exists('pxJalali')) return pxJalali();
    return date('Y/m/d H:i');
}

/** نام قابل نمایشِ یک کاربر */
function chUser($uid, $uname = '', $fname = '') {
    $n = trim((string)$fname);
    $u = trim((string)$uname);
    if ($u !== '') return '@' . ltrim($u, '@');
    return $n !== '' ? h($n) : ('<code>' . (int)$uid . '</code>');
}

// ============================================================
// 🔔 قلاب‌ها — جاهایی که گزارش ساخته می‌شود
// ============================================================

/**
 * رسید شارژ کیف پول.
 *
 * دکمه‌های ✅ تایید / ❌ رد همان دکمه‌های همیشگیِ سفارش‌ها هستند
 * (aok_/ano_، دستِ همان قلابِ عمومی در masterHandle) — فقط این‌بار
 * زیرِ همین پیامِ گروه/تاپیک می‌نشینند تا مدیر همان‌جا تصمیم بگیرد،
 * بدون آنکه چیزی به چتِ خصوصیِ ربات بیاید.
 *
 * برگشتِ true یعنی رفت (پس دیگر لازم نیست خصوصی هم برای ادمین بفرستیم).
 */
function chTopupReceipt($order) {
    if (!is_array($order)) return false;
    $uid = (int)($order['user_id'] ?? 0);
    $u   = function_exists('getUser') ? (getUser($uid) ?: []) : [];
    $amt = (float)($order['amount'] ?? 0);
    $pending = ($order['status'] ?? '') === 'pending';
    $rows = $pending ? [[
        ['text' => function_exists('UT') ? UT('confirm') : '✅ تایید', 'callback_data' => 'aok_' . $order['id']],
        ['text' => function_exists('UT') ? UT('reject')  : '❌ رد',   'callback_data' => 'ano_' . $order['id']],
    ]] : [];
    return chSend('topup', [
        'user'    => chUser($uid, $order['username'] ?? '', $u['name'] ?? ''),
        'uid'     => $uid,
        'amount'  => fmtNum($amt),
        'balance' => fmtNum((float)($u['balance'] ?? 0) + $amt),
        'code'    => (string)($order['id'] ?? ''),
        'receipt' => (string)($order['receipt_type'] ?? '') === 'text' ? (string)$order['receipt'] : 'تصویر',
    ], ($order['receipt_type'] ?? '') === 'photo' ? ($order['receipt'] ?? null) : null, $rows);
}

/**
 * رسیدِ یک سفارشِ عادی (نه شارژ کیف‌پول) — محصولِ ربات یا مینی‌اپ.
 *
 * قبلِ این تابع، رسیدِ هر سفارشی جز شارژ کیف‌پول همیشه به چتِ خصوصیِ
 * ادمین می‌رفت، حتی اگر همان بخش کانالِ گزارشِ خودش را وصل کرده بود —
 * یعنی دقیقاً همان مشکلی که رسیدِ شارژ قبلاً داشت. اینجا همان مسیرِ
 * چندسال‌جریانه (mem_ok/mem_vip/mini_tg/…) را با دکمه‌ی تایید/رد
 * به‌کار می‌گیرد، دقیقاً مثلِ chBuy ولی با دکمه‌های تصمیم‌گیری روی خودِ
 * پیام، تا هیچ‌چیزی به ربات نیاید.
 *
 * برگشتِ true یعنی رفت — دیگر لازم نیست خصوصی هم برای ادمین بفرستیم.
 */
function chOrderReceipt($uid, $uname, $productName, $qty, $amount, $code,
                         $confirmCb, $rejectCb, $photo = null, $app = '', $cat = '') {
    $stream = chStreamFor($app, $productName, $cat);
    [$label] = chStreams()[$stream] ?? ['🛒 فروش'];
    $plainIcon = '';
    if (preg_match('/^(\X)\s+(.*)$/u', $label, $m)) { $plainIcon = $m[1]; $label = $m[2]; }

    $pid = trim((string)(chOf($stream)['premium_icon'] ?? ''));
    $icon = ($pid !== '' && ctype_digit($pid))
        ? '<tg-emoji emoji-id="' . h($pid) . '">' . h($plainIcon ?: '🛒') . '</tg-emoji>'
        : '';

    $rows = ($confirmCb && $rejectCb) ? [[
        ['text' => function_exists('UT') ? UT('confirm') : '✅ تایید', 'callback_data' => $confirmCb],
        ['text' => function_exists('UT') ? UT('reject')  : '❌ رد',   'callback_data' => $rejectCb],
    ]] : [];

    return chSend($stream, [
        'user'    => chUser($uid, $uname, ''),
        'uid'     => (int)$uid,
        'product' => (string)$productName,
        'qty'     => fmtNum((float)$qty),
        'amount'  => fmtNum((float)$amount),
        'code'    => (string)$code,
        'section' => $label,
        'icon'    => $icon,
        'app'     => (string)$app,
    ], $photo, $rows);
}

/**
 * یک فروش انجام شد — می‌رود روی کانالِ همان بخش.
 * $app: 'tg' یا 'num' برای مینی‌اپ‌ها، خالی برای محصول‌های خودِ ربات.
 */
function chBuy($uid, $uname, $productName, $qty, $amount, $code, $extra = [], $app = '', $cat = '') {
    $stream = chStreamFor($app, $productName, $cat);
    [$label] = chStreams()[$stream] ?? ['🛒 فروش'];
    // ایموجیِ سرِ برچسب فقط برای جداکردنِ خودِ متنِ {section} است؛ دیگر
    // در {icon} نمی‌نشیند. {icon} فقط ایموجیِ پریمیومِ خودِ همین جریان
    // است (اگر ادمین از صفحه‌ی خودِ جریان تنظیم کرده باشد)، وگرنه خالی.
    $plainIcon = '';
    if (preg_match('/^(\X)\s+(.*)$/u', $label, $m)) { $plainIcon = $m[1]; $label = $m[2]; }

    $pid = trim((string)(chOf($stream)['premium_icon'] ?? ''));
    $icon = ($pid !== '' && ctype_digit($pid))
        ? '<tg-emoji emoji-id="' . h($pid) . '">' . h($plainIcon ?: '🛒') . '</tg-emoji>'
        : '';

    chSend($stream, array_merge([
        'user'    => chUser($uid, $uname, ''),
        'uid'     => (int)$uid,
        'product' => (string)$productName,
        'qty'     => fmtNum((float)$qty),
        'amount'  => fmtNum((float)$amount),
        'code'    => (string)$code,
        'section' => $label,
        'icon'    => $icon,
        'app'     => (string)$app,
    ], $extra));
}

// ============================================================
// 👑 پنل
// ============================================================

function chAdminHome($chatId, $msgId = null) {
    $t  = "📡 <b>کانال‌های متصل</b>\n\n";
    $t .= "هر جریان مقصد خودش را دارد. لینکِ همان <b>تاپیک</b> را بدهید،\n";
    $t .= "آیدی گروه و شماره تاپیک خودشان درمی‌آیند.\n";
    $t .= "⚠️ ربات باید در آن گروه <b>ادمین</b> باشد.\n\n";

    $rows = [];
    foreach (chStreams() as $k => [$label, $desc]) {
        $s = chOf($k);
        $set = trim((string)$s['chat_id']) !== '';
        $t .= (chReady($k) ? '✅' : ($set ? '⏸' : '⚪️')) . ' <b>' . h($label) . "</b>\n";
        $t .= '   ' . ($set
                ? '<code>' . h((string)$s['chat_id']) . '</code>' .
                  ((int)$s['thread_id'] > 0 ? ' · 🧵 ' . (int)$s['thread_id'] : '')
                : 'تنظیم نشده') . "\n";
        $rows[] = [btnCb($label, 'chs_' . $k, 'admin')];
    }
    $t .= "\n💡 دسته‌های مینی‌اپ خودشان اینجا می‌آیند؛ هر کدام تاپیک و متنِ خودش.\n";
    $t .= "محصول‌های ربات بر اساس کلمه‌های داخل نامشان دسته‌بندی می‌شوند.";
    $rows[] = [btnCb('🗣 کلمه‌های دسته‌بندی ممبر', 'chwords', 'admin')];
    $rows[] = [btnCb(UT('back'), 'adm_home', 'nav')];

    $t = mb_substr($t, 0, 3800);
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

/** کلمه‌هایی که تصمیم می‌گیرند یک محصول کدام دسته‌ی ممبر است */
function chAdminWords($chatId, $msgId) {
    $w = chMemberWords();
    $t  = "🗣 <b>کلمه‌های دسته‌بندی ممبر</b>\n\n";
    $t .= "نام هر محصول از بالا به پایین با این‌ها سنجیده می‌شود و\n";
    $t .= "اولی که جور دربیاید برنده است. پس ترتیب مهم است.\n\n";
    $rows = [];
    foreach (['mem_no' => '🔞 غیراخلاقی', 'mem_vip' => '💎 ویژه', 'mem_ok' => '✅ اخلاقی'] as $k => $lbl) {
        $t .= '• <b>' . h($lbl) . '</b>: <code>' . h(implode('، ', $w[$k])) . "</code>\n";
        $rows[] = [btnCb($lbl, 'chw_' . $k, 'admin')];
    }
    $rows[] = [btnCb(UT('back'), 'ch_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function chAdminStream($chatId, $msgId, $k) {
    $st = chStreams()[$k] ?? null;
    if (!$st) { chAdminHome($chatId, $msgId); return; }
    [$label, $desc] = $st;
    $s = chOf($k);

    $t  = h($label) . "\n\n" . h($desc) . "\n\n";
    $t .= 'وضعیت: ' . (!empty($s['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= 'مقصد: ' . (trim((string)$s['chat_id']) !== ''
            ? '<code>' . h((string)$s['chat_id']) . '</code>' : '— تنظیم نشده') . "\n";
    $t .= 'تاپیک: ' . ((int)$s['thread_id'] > 0 ? (int)$s['thread_id'] : 'بدون تاپیک') . "\n";
    if ($k === 'topup') $t .= 'عکس رسید: ' . (!empty($s['photo']) ? '✅ فرستاده شود' : '❌ فقط متن') . "\n";
    if ($k !== 'topup' && $k !== 'tech') {
        $pid = trim((string)($s['premium_icon'] ?? ''));
        $t .= '🌟 ایموجیِ پریمیوم: ' . ($pid !== '' ? '<tg-emoji emoji-id="' . h($pid) . '">🛒</tg-emoji> تنظیم‌شده' : 'تنظیم‌نشده') . "\n";
    }
    $t .= "\n<b>متن گزارش:</b>\n" . $s['text'] . "\n\n";
    $t .= "جای‌گذاری‌ها: " . implode(' ', array_map(fn($x) => '<code>{' . $x . '}</code>', chVarsOf($k)));

    $rows = [
        [btnCb(!empty($s['on']) ? '✅ روشن' : '❌ خاموش', 'chx_' . $k, 'info'),
         btnCb('🧪 تست', 'cht_' . $k, 'confirm')],
        [btnCb('🔗 گروه و تاپیک', 'chl_' . $k, 'admin')],
        [btnCb('✏️ متن گزارش', 'chm_' . $k, 'admin')],
    ];
    $resetRow = $k !== 'topup' && $k !== 'tech'
        ? [btnCb('🌟 ایموجیِ پریمیوم', 'chi_' . $k, 'admin'), btnCb('🔄 بازنشانی متن', 'chrs_' . $k, 'confirm')]
        : [btnCb('🔄 بازنشانی متن به پیش‌فرض', 'chrs_' . $k, 'confirm')];
    $rows[] = $resetRow;
    if ($k === 'topup') $rows[] = [btnCb(!empty($s['photo']) ? '🖼 عکس رسید: روشن' : '🖼 عکس رسید: خاموش', 'chp_' . $k, 'info')];
    $t .= "\n\n<b>دکمه‌ها:</b>";
    foreach ((array)$s['buttons'] as $i => $b) {
        $eff = chButtonUrl($b);
        $t .= "\n" . (!empty($b['on']) ? '✅' : '❌') . ' ' . h(trim((string)$b['text']) ?: 'بی‌متن') . ' → ' .
              ($eff !== ''
                ? '<code>' . h(mb_substr($eff, 0, 60)) . '</code>' .
                  (trim((string)($b['url'] ?? '')) === '' ? ' <i>(خودِ ربات)</i>' : '')
                : '<i>لینک ندارد — دیده نمی‌شود</i>');
        $rows[] = [
            btnCb(!empty($b['on']) ? '✅' : '❌', 'chbx_' . $k . '_' . $i, 'info'),
            btnCb('✏️ ' . (trim((string)$b['text']) !== '' ? mb_substr($b['text'], 0, 12) : 'دکمه ' . ($i + 1)),
                  'chbt_' . $k . '_' . $i, 'admin'),
            btnCb('🔗 لینک', 'chbu_' . $k . '_' . $i, 'admin'),
        ];
    }
    $rows[] = [btnCb(UT('back'), 'ch_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3800), inlineKb($rows));
}

function chVarsOf($k) {
    if ($k === 'topup') return ['user', 'uid', 'amount', 'balance', 'code', 'receipt', 'date'];
    if ($k === 'tech')  return ['text', 'date'];
    return ['user', 'uid', 'product', 'qty', 'amount', 'code', 'section', 'icon', 'date'];
}

/**
 * 🛠 یک خبرِ فنی — خطا، افت سرعت، کمبود بودجه — به گروهِ گزارش فنی.
 *
 * اگر آن گروه تنظیم نشده، همان قبلی: پیام خصوصی به مدیرها. یعنی این
 * یک لایه‌ی اضافه است، نه جایگزینِ notifyAdmins — چیزی گم نمی‌شود.
 */
function chTechAlert($text) {
    $sent = chReady('tech') ? chSend('tech', ['text' => $text]) : false;
    if (!$sent && function_exists('notifyAdmins')) notifyAdmins($text);
    return $sent;
}

/** برگشت true یعنی این callback مال بخش کانال‌ها بود */
function chAdminCallback($data, $chatId, $msgId, $cbId) {
    if (!str_starts_with($data, 'ch')) return false;

    if ($data === 'ch_home')  { answerCb(BOT_TOKEN, $cbId); chAdminHome($chatId, $msgId); return true; }
    if ($data === 'chwords')  { answerCb(BOT_TOKEN, $cbId); chAdminWords($chatId, $msgId); return true; }
    if (preg_match('/^chw_(mem_[a-z]+)$/', $data, $m)) {
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, 'ch_words', ['k' => $m[1]]);
        sendMsg(BOT_TOKEN, $chatId,
            "🗣 کلمه‌ها را با ویرگول جدا بفرستید.\n\nالان:\n<code>" .
            h(implode('، ', chMemberWords()[$m[1]] ?? [])) . '</code>',
            inlineKb([[btnCb('انصراف', 'chwords', 'cancel')]]));
        return true;
    }

    foreach (['chs_' => 'open', 'chx_' => 'toggle', 'chp_' => 'photo', 'cht_' => 'test', 'chrs_' => 'reset'] as $pre => $what) {
        if (!str_starts_with($data, $pre)) continue;
        $k = substr($data, strlen($pre));
        if (!isset(chStreams()[$k])) { answerCb(BOT_TOKEN, $cbId); return true; }

        if ($what === 'toggle') {
            chSet($k, function (&$s) { $s['on'] = empty($s['on']); });
            answerCb(BOT_TOKEN, $cbId, '✅');
        } elseif ($what === 'photo') {
            chSet($k, function (&$s) { $s['photo'] = empty($s['photo']); });
            answerCb(BOT_TOKEN, $cbId, '✅');
        } elseif ($what === 'reset') {
            $def = chDefaults()[$k]['text'] ?? '';
            chSet($k, function (&$s) use ($def) { $s['text'] = $def; });
            answerCb(BOT_TOKEN, $cbId, '✅ بازنشانی شد');
        } elseif ($what === 'test') {
            answerCb(BOT_TOKEN, $cbId);
            if (!chReady($k)) {
                sendMsg(BOT_TOKEN, $chatId, "⚠️ اول گروه را تنظیم و جریان را روشن کنید.");
                return true;
            }
            $ok = chSend($k, chSampleVars($k));
            sendMsg(BOT_TOKEN, $chatId, $ok
                ? "✅ گزارش آزمایشی رفت. اگر در گروه ندیدید، ربات آنجا ادمین نیست."
                : "🔴 نرفت. ربات را در آن گروه ادمین کنید و دوباره امتحان کنید.");
            return true;
        }
        chAdminStream($chatId, $msgId, $k);
        return true;
    }

    // روشن/خاموش کردن یک دکمه
    if (preg_match('/^chbx_([a-z0-9_]+)_(\d+)$/', $data, $m)) {
        chSet($m[1], function (&$s) use ($m) {
            $i = (int)$m[2];
            if (isset($s['buttons'][$i])) $s['buttons'][$i]['on'] = empty($s['buttons'][$i]['on']) ? 1 : 0;
        });
        answerCb(BOT_TOKEN, $cbId, '✅');
        chAdminStream($chatId, $msgId, $m[1]);
        return true;
    }

    // ورودی‌های متنی
    $asks = [
        'chl_'  => ['ch_link', "🔗 لینک گروه یا تاپیک را بفرستید.\n\n" .
                               "مثال: <code>https://t.me/c/1234567890/11</code>\n" .
                               "یا آیدی عددی گروه، یا <code>@نام‌کانال</code>.\n\n" .
                               "برای پاک کردن، <code>-</code> بفرستید."],
        'chm_'  => ['ch_text', "✏️ متن گزارش را بفرستید.\n\n" .
                               "ایموجی پرمیوم و نقل‌قول هرچه بگذارید سرِ جایش می‌ماند."],
        'chi_'  => ['ch_icon', "🌟 ایموجیِ پریمیومِ این جریان را بفرستید — همان چیزی که جلوی {icon} می‌نشیند.\n\n" .
                               "یک پیام حاوی همان ایموجی بفرستید، یا کد عددی‌اش را.\n" .
                               "برای برداشتن، یک خط تیره <code>-</code> بفرستید."],
    ];
    foreach ($asks as $pre => [$act, $ask]) {
        if (!str_starts_with($data, $pre)) continue;
        $k = substr($data, strlen($pre));
        if (!isset(chStreams()[$k])) { answerCb(BOT_TOKEN, $cbId); return true; }
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, $act, ['k' => $k]);
        $more = ($act === 'ch_text')
            ? "\n\nجای‌گذاری‌ها: " . implode(' ', array_map(fn($x) => '<code>{' . $x . '}</code>', chVarsOf($k))) .
              "\n\nالان:\n" . chOf($k)['text']
            : '';
        sendMsg(BOT_TOKEN, $chatId, $ask . $more, inlineKb([[btnCb('انصراف', 'chs_' . $k, 'cancel')]]));
        return true;
    }
    if (preg_match('/^chb([tu])_([a-z0-9_]+)_(\d+)$/', $data, $m)) {
        $isText = $m[1] === 't';
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, $isText ? 'ch_btntext' : 'ch_btnurl', ['k' => $m[2], 'i' => (int)$m[3]]);
        sendMsg(BOT_TOKEN, $chatId, $isText
            ? "✏️ متن دکمه را بفرستید.\n\n✨ ایموجی پرمیوم را جلوی متن بگذارید — خودش برداشته می‌شود."
            : "🔗 لینک دکمه را بفرستید.\n\n" .
              "خالی بگذارید (<code>-</code>) تا خودکار به <b>خودِ ربات</b> وصل شود —\n" .
              "همان چیزی که برای «ثبت سفارش» می‌خواهید.\n\n" .
              "<code>{bot}</code> هرجای لینک، نامِ ربات می‌شود:\n" .
              "<code>https://t.me/{bot}?start=shop</code>",
            inlineKb([[btnCb('انصراف', 'chs_' . $m[2], 'cancel')]]));
        return true;
    }
    return false;
}

function chSampleVars($k) {
    if ($k === 'topup') return ['user' => '@testuser', 'uid' => 123456789, 'amount' => fmtNum(500000),
                                'balance' => fmtNum(750000), 'code' => 'TEST-1234', 'receipt' => 'آزمایشی'];
    if ($k === 'tech')  return ['text' => '🧪 این یک گزارشِ فنیِ آزمایشی است.'];
    [$label] = chStreams()[$k] ?? ['🛒 فروش'];
    $plainIcon = '🛒';
    if (preg_match('/^(\X)\s+(.*)$/u', $label, $m)) { $plainIcon = $m[1]; $label = $m[2]; }
    $pid = trim((string)(chOf($k)['premium_icon'] ?? ''));
    $icon = ($pid !== '' && ctype_digit($pid))
        ? '<tg-emoji emoji-id="' . h($pid) . '">' . h($plainIcon) . '</tg-emoji>'
        : '';
    return ['user' => '@testuser', 'uid' => 123456789, 'product' => '⭐️ ۵۰ استارز',
            'qty' => '1', 'amount' => fmtNum(149000), 'code' => 'TEST-1234',
            'section' => $label, 'icon' => $icon, 'app' => ''];
}

/** برگشت true یعنی این گفتگو مال بخش کانال‌ها بود */
function chStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'ch_')) return false;
    if (!isAdmin($uid)) return false;

    $st   = getState($uid);
    $sd   = $st['data'] ?? [];
    $k    = (string)($sd['k'] ?? '');
    $text = trim((string)($msg['text'] ?? ''));
    $blank = ($text === '-' || $text === '—');
    if ($action !== 'ch_words' && !isset(chStreams()[$k])) { clearState($uid); return true; }

    $done = function ($m = "✅ ذخیره شد.") use ($uid, $chatId, $k) {
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $m, inlineKb([[btnCb('📡 کانال‌های متصل', 'chs_' . $k, 'admin')]]));
        return true;
    };

    if ($action === 'ch_words') {
        if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ خالی نمی‌شود."); return true; }
        cfgSet(function (&$c) use ($k, $text) {
            if (!isset($c['channels']['words']) || !is_array($c['channels']['words']))
                $c['channels']['words'] = [];
            $c['channels']['words'][$k] = $text;
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد.",
            inlineKb([[btnCb('🗣 کلمه‌ها', 'chwords', 'admin')]]));
        return true;
    }

    if ($action === 'ch_link') {
        if ($blank) {
            chSet($k, function (&$s) { $s['chat_id'] = ''; $s['thread_id'] = 0; $s['on'] = false; });
            return $done("🧹 پاک شد.");
        }
        [$chat, $thread] = parseChatLink($text);
        if ($chat === null) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ از این لینک آیدی درنیامد.\nلینک یک پیام داخل همان گروه را بفرستید.");
            return true;
        }
        // همان‌جا امتحان کن — بهتر از اینکه بعدا بی‌صدا نرود
        $probe = tg(BOT_TOKEN, 'getChat', ['chat_id' => $chat]);
        if (empty($probe['ok'])) {
            sendMsg(BOT_TOKEN, $chatId,
                "⚠️ ربات به این گروه دسترسی ندارد:\n<code>" .
                h((string)($probe['description'] ?? 'بی‌پاسخ')) . "</code>\n\n" .
                "اول ربات را آنجا ادمین کنید، بعد دوباره بفرستید.");
            return true;
        }
        chSet($k, function (&$s) use ($chat, $thread) {
            $s['chat_id'] = $chat; $s['thread_id'] = (int)$thread; $s['on'] = true;
        });
        return $done("✅ وصل شد: <code>" . h($chat) . '</code>' .
                     ($thread > 0 ? " · 🧵 {$thread}" : '') . "\n\nبا دکمه 🧪 تست امتحانش کنید.");
    }

    if ($action === 'ch_text') {
        $html = msgHtml($msg);
        if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
        chSet($k, function (&$s) use ($html) { $s['text'] = $html; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد. پیش‌نمایش:");
        sendMsg(BOT_TOKEN, $chatId, chFill($html, chSampleVars($k) + ['date' => chDate()]),
                chKeyboard(chOf($k)) ?: inlineKb([[btnCb('📡 برگرد', 'chs_' . $k, 'admin')]]));
        return true;
    }

    if ($action === 'ch_icon') {
        $ids = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
        $v = $ids ? (string)$ids[0] : ($blank ? '' : preg_replace('/\D/', '', norm_fa_digits($text)));
        if (!$blank && $v === '') {
            sendMsg(BOT_TOKEN, $chatId,
                "⚠️ ایموجی پیدا نشد. یک پیام با همان ایموجی بفرستید، یا کد عددی‌اش را.\n" .
                "برای برداشتنش خط تیره بفرستید.");
            return true;
        }
        chSet($k, function (&$s) use ($v) { $s['premium_icon'] = $v; });
        return $done($v === '' ? '✅ حذف شد.' : '✅ ثبت شد.');
    }

    $i = (int)($sd['i'] ?? -1);
    if ($action === 'ch_btntext') {
        if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
        $ids  = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
        $icon = $ids ? (string)$ids[0] : '';
        // ایموجی پرمیوم جدا روی دکمه می‌نشیند؛ اگر نویسه‌اش داخل متن هم
        // بماند، یک ایموجیِ معمولیِ اضافه جلویش دیده می‌شود.
        if ($icon !== '' && function_exists('textWithoutCustomEmoji')) {
            $clean = textWithoutCustomEmoji($msg);
            if ($clean !== '') $text = $clean;
        }
        if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
        chSet($k, function (&$s) use ($i, $text, $icon) {
            if (isset($s['buttons'][$i])) { $s['buttons'][$i]['text'] = $text; $s['buttons'][$i]['icon'] = $icon; }
        });
        return $done();
    }
    if ($action === 'ch_btnurl') {
        if (!$blank && !preg_match('#^https?://#i', $text)) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ لینک باید با http شروع شود."); return true;
        }
        $url = $blank ? '' : $text;
        chSet($k, function (&$s) use ($i, $url) {
            if (isset($s['buttons'][$i])) $s['buttons'][$i]['url'] = $url;
        });
        $eff = chButtonUrl(chOf($k)['buttons'][$i] ?? []);
        return $done($eff !== ''
            ? "✅ دکمه به این آدرس می‌رود:\n<code>" . h($eff) . '</code>' .
              ($blank ? "\n\n(خودِ ربات — چون لینکی ندادید)" : '')
            : "✅ ذخیره شد.");
    }
    clearState($uid);
    return true;
}
