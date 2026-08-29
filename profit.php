<?php
/**
 * 📈 profit.php — یک جا برای همه‌ی سودها
 * ============================================================
 *
 * سود در این ربات چهار جای مختلف زندگی می‌کرد و هیچ‌کدام از وجودِ
 * بقیه خبر نداشتند:
 *
 *   • خرید ممبر     — اصلا سودی نداشت؛ قیمتی که ادمین می‌نوشت همان بود
 *   • مینی‌اپ‌ها     — سودِ دسته‌ای و محصولی داشت، ولی سودِ عمومی نه
 *   • شماره مجازی  — markup خودش
 *   • قیمت‌گیری ارز — margin خودش
 *
 * نتیجه‌اش این بود که «۲۵٪ روی همه‌چیز» یعنی رفتن به چهار صفحه و
 * یادت نرود کدام را زدی. این فایل یک اتاقِ فرمان است:
 *
 *   • یک درصدِ عمومی که هرجا سودِ خاص‌تری نیست، همان می‌نشیند
 *   • درصدِ جدا برای هر بخش، وقتی خواستی
 *   • و یک دکمه که همان عدد را در هر چهار جا می‌نشاند
 *
 * ⚠️ برای شماره مجازی و قیمت‌گیری، عددِ خودشان همچنان تنها منبعِ حقیقت
 *    است — اینجا فقط خوانده و نوشته می‌شود. دو تا عددِ سود برای یک چیز
 *    یعنی یکی‌شان همیشه دروغ است.
 */

if (!defined('PF_LIB')) define('PF_LIB', 1);

/**
 * 💰 سود، به دو شکل.
 *
 * تا حالا سود فقط درصد بود. ولی «۵۰۰۰ تومان روی هر پک استارز» معنی‌اش
 * روشن‌تر از «۱۲٪» است، مخصوصا برای چیزهایی که نرخشان لحظه‌ای عوض
 * می‌شود (استارز، پریمیوم، تون) — درصد رویشان می‌لرزد، عددِ ثابت نه.
 * پس هر بخش («عمومی»، «خرید ممبر»، «مینی‌اپ‌ها») حالا یک حالت
 * (mode: pct | fixed) و یک عدد دارد.
 *
 * ⚠️ شماره مجازی و قیمت‌گیری همچنان فقط درصد را می‌شناسند — موتورِ
 *    قیمت‌شان خودشان است و اینجا کاری‌شان نداریم؛ برای همان یک دلیلِ
 *    قدیمی: دو منبعِ حقیقت برای یک عدد یعنی یکی‌شان دروغ است.
 */
function pfDefaults() {
    return [
        'on'     => false,
        'all'    => ['mode' => 'pct', 'v' => 0.0],   // عمومی
        'member' => ['mode' => null,  'v' => null],  // null یعنی «از عمومی پیروی کن»
        'ma'     => ['mode' => null,  'v' => null],
        'rent'   => ['mode' => null,  'v' => null],  // اجاره‌ی گیفت
    ];
}

function pfCfg() {
    $c = cfg()['profit'] ?? null;
    $d = pfDefaults();
    if (!is_array($c)) return $d;
    $out = array_replace($d, array_intersect_key($c, $d));
    // سازگاری با نسخه‌ی قدیمی‌تر که این سه فقط یک عددِ درصدِ خام بودند
    foreach (['all', 'member', 'ma', 'rent'] as $k) {
        if (isset($c[$k]) && !is_array($c[$k]))
            $out[$k] = ['mode' => 'pct', 'v' => $c[$k]];
    }
    return $out;
}

function pfVal($k, $default = null) {
    $c = pfCfg();
    return array_key_exists($k, $c) ? $c[$k] : $default;
}

function pfSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!isset($c['profit']) || !is_array($c['profit'])) $c['profit'] = [];
        $fn($c['profit']);
    });
}

function pfOn() { return !empty(pfVal('on')); }

function pfSecSet($sec, $mode, $v) {
    pfSet(function (&$c) use ($sec, $mode, $v) { $c[$sec] = ['mode' => $mode, 'v' => $v]; });
}

/**
 * حالت و عددِ مؤثرِ یک بخش — ['mode' => 'pct'|'fixed', 'v' => float]
 * ترتیب: خودِ بخش → عمومی → صفر (خاموش).
 */
function pfEffective($sec) {
    if (!pfOn()) return ['mode' => 'pct', 'v' => 0.0];
    $own = (array)pfVal($sec, []);
    if (!empty($own['mode']) && $own['v'] !== null && $own['v'] !== '')
        return ['mode' => (string)$own['mode'], 'v' => (float)$own['v']];
    $all = (array)pfVal('all', []);
    return ['mode' => (string)($all['mode'] ?? 'pct'), 'v' => (float)($all['v'] ?? 0)];
}

/** فقط برای جاهایی که همیشه درصد می‌خواهند (مثلا axMarginOf) */
function pfPct($sec) {
    $e = pfEffective($sec);
    return $e['mode'] === 'pct' ? $e['v'] : 0.0;
}

/** فقط برای جاهایی که به تومانِ ثابت هم رسیدگی می‌کنند */
function pfFixed($sec) {
    $e = pfEffective($sec);
    return $e['mode'] === 'fixed' ? $e['v'] : 0.0;
}

/** قیمت + سودِ آن بخش — درصد یا تومانِ ثابت، هرکدام که مُدِ مؤثرش باشد */
function pfApply($sec, $price) {
    $p = (float)$price;
    if ($p <= 0) return $p;
    $e = pfEffective($sec);
    if ($e['v'] == 0.0) return $p;
    return $e['mode'] === 'fixed' ? ($p + $e['v']) : ($p * (1 + $e['v'] / 100));
}

/** نمایشِ خوانا: «۱۲٪» یا «۵٬۰۰۰ تومان» */
function pfAmountStr($e) {
    return $e['mode'] === 'fixed' ? (fmtNum($e['v']) . ' تومان') : pfPctStr($e['v']);
}

/**
 * محصولِ ممبر، با سودش.
 *
 * قیمتِ خام زیرِ `price_base` می‌ماند — پنلِ ویرایش همان را نشان
 * می‌دهد، وگرنه هر بار ذخیره، سود روی سود سوار می‌شد.
 */
function pfProduct($p) {
    if (!is_array($p)) return $p;
    $base = (float)($p['price'] ?? 0);
    $p['price_base'] = $base;
    $p['price']      = round(pfApply('member', $base), 2);
    return $p;
}

/**
 * 📊 همه‌ی سودهای ربات، در یک نگاه.
 *
 * هر ردیف: [کلید, برچسب, ['mode'=>,'v'=>] مؤثر, آیا عددِ خودش را دارد, کجا تنظیم می‌شود]
 */
function pfRows() {
    $own = function ($sec) { $o = (array)pfVal($sec, []); return !empty($o['mode']) && $o['v'] !== null && $o['v'] !== ''; };
    $rows = [
        ['member', '🎯 خرید ممبر', pfEffective('member'), $own('member'), 'pf_member'],
        ['ma',     '🚀 مینی‌اپ‌ها', pfEffective('ma'),     $own('ma'),     'pf_ma'],
        ['rent',   '🎁 اجاره‌ی گیفت', pfEffective('rent'), $own('rent'), 'pf_rent'],
    ];
    if (function_exists('numVal'))
        $rows[] = ['num', '☎️ شماره مجازی', ['mode' => 'pct', 'v' => (float)numVal('markup', 0)], true, 'num_home'];
    if (function_exists('pxVal'))
        $rows[] = ['px',  '💹 قیمت‌گیری ارز', ['mode' => 'pct', 'v' => (float)pxVal('margin', 0)], true, 'px_home'];
    return $rows;
}

/** یک درصد را در هر چهار جا بنشان — شماره مجازی و قیمت‌گیری فقط درصد می‌شناسند */
function pfSetAll($pct) {
    $pct = max(0.0, min(1000.0, (float)$pct));
    pfSet(function (&$c) use ($pct) {
        $c['on']     = true;
        $c['all']    = ['mode' => 'pct', 'v' => $pct];
        $c['member'] = ['mode' => null, 'v' => null];      // همه از عمومی پیروی کنند
        $c['ma']     = ['mode' => null, 'v' => null];
    });
    if (function_exists('numSet')) numSet(function (&$c) use ($pct) { $c['markup'] = $pct; });
    if (function_exists('pxSet'))  pxSet(function (&$c) use ($pct)  { $c['margin'] = $pct; });
    return $pct;
}

function pfPctStr($v) { return rtrim(rtrim(number_format((float)$v, 1), '0'), '.') . '٪'; }

// ============================================================
// 🛠 پنل
// ============================================================

function pfHome($chatId, $msgId = null) {
    $on = pfOn();

    $t  = "📈 <b>سود روی محصولات</b>\n\n";
    $t .= 'وضعیت: ' . ($on ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= '📊 سودِ عمومی: <b>' . pfAmountStr(pfEffective('all')) . "</b>\n\n";

    $t .= "<b>الان روی هر بخش:</b>\n";
    foreach (pfRows() as [$k, $lbl, $eff, $own, $_]) {
        $t .= $lbl . ': <b>' . pfAmountStr($eff) . '</b>' .
              ($own ? '' : ' <i>(از عمومی)</i>') . "\n";
    }

    $t .= "\n";
    if (!$on) {
        $t .= "⚠️ تا روشن نشود، سودِ عمومی و سودِ بخش‌ها روی قیمت‌ها نمی‌نشیند.\n" .
              "<i>شماره مجازی و قیمت‌گیری عددِ خودشان را دارند و مستقل کار می‌کنند.</i>\n\n";
    }
    $t .= "💡 <b>سودِ عمومی</b> هرجا که عددِ خاص‌تری نیست می‌نشیند.\n";
    $t .= "برای هر بخش عددِ جدا هم می‌شود گذاشت — <code>-</code> بفرستید تا " .
          "دوباره از عمومی پیروی کند.\n\n";
    $t .= "🎯 «روی همه بنشان» همان عدد را در هر چهار بخش می‌نویسد — " .
          "از جمله شماره مجازی و قیمت‌گیری که تنظیمِ خودشان را دارند.\n\n";
    $t .= "<i>سودِ دسته‌ای و سودِ تک‌محصولِ مینی‌اپ‌ها از این هم خاص‌ترند و " .
          "اگر ست شده باشند، بر این مقدم‌اند.</i>";

    $rows = [
        [btnCb($on ? '❌ خاموش کن' : '✅ روشن کن', 'pf_tog', 'info')],
        [btnCb('📊 سودِ عمومی', 'pf_all', 'admin')],
        [btnCb('🎯 سودِ خرید ممبر', 'pf_member', 'admin'),
         btnCb('🚀 سودِ مینی‌اپ‌ها', 'pf_ma', 'admin')],
        [btnCb('🎁 سودِ اجاره‌ی گیفت', 'pf_rent', 'admin')],
        [btnCb('🎯 روی همه بنشان', 'pf_every', 'confirm')],
        [btnCb('☎️ سودِ شماره مجازی', 'num_home', 'nav'),
         btnCb('💹 سودِ قیمت‌گیری', 'px_home', 'nav')],
        [btnCb('👀 نمونه‌ی قیمت‌ها', 'pf_peek', 'confirm')],
        [btnCb('🔙 بازگشت', 'adm_home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

/**
 * 👀 قبل و بعد.
 *
 * درصد گفتن آسان است؛ دیدنِ اینکه «۵۰٬۰۰۰ می‌شود ۶۲٬۵۰۰» همان چیزی
 * است که آدم را از اشتباهِ ۲۵۰٪ به‌جای ۲۵٪ نجات می‌دهد.
 */
function pfPeek($chatId, $msgId) {
    $t = "👀 <b>نمونه‌ی قیمت‌ها</b>\n\n";

    $n = 0;
    if (class_exists('Product')) {
        foreach (Product::all() as $p) {
            if (++$n > 5) break;
            $base = (float)($p['price_base'] ?? $p['price']);
            $t .= '🎯 ' . h(mb_substr((string)$p['name'], 0, 24)) . ' — ' .
                  fmtNum($base) . ' ← <b>' . fmtNum($p['price']) . '</b>' .
                  ' ' . h((string)($p['currency'] ?? '')) . "\n";
        }
    }
    if (!$n) $t .= "<i>هنوز محصولِ ممبری نساخته‌اید.</i>\n";

    if (function_exists('maGet') && function_exists('maItemPrice')) {
        $t .= "\n";
        $m = 0;
        foreach (['tg' => '🚀', 'num' => '☎️'] as $app => $em) {
            foreach ((array)(maGet($app)['items'] ?? []) as $it) {
                if (empty($it['on'])) continue;
                if (++$m > 6) break 2;
                $t .= $em . ' ' . h(mb_substr((string)$it['name'], 0, 22)) . ' — ' .
                      fmtNum((float)($it['price'] ?? 0)) . ' ← <b>' .
                      fmtNum(maItemPrice($it)) . "</b>\n";
            }
        }
    }

    $t .= "\n<i>عددِ چپ آنچه ثبت شده، عددِ راست آنچه مشتری می‌پردازد.</i>";
    editMsg(BOT_TOKEN, $chatId, $msgId, $t,
        inlineKb([[btnCb('🔙 بازگشت', 'pf_home', 'nav')]]));
}

/** برگشت true یعنی این callback مالِ بخش سود بود */
function pfCallback($data, $uid, $chatId, $msgId, $cbId, $isAdmin) {
    if (!str_starts_with((string)$data, 'pf_')) return false;
    if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, '🔒', true); return true; }
    $ack = function ($m = '') use ($cbId) { answerCb(BOT_TOKEN, $cbId, $m); };

    if ($data === 'pf_home') { $ack(); pfHome($chatId, $msgId); return true; }
    if ($data === 'pf_peek') { $ack(); pfPeek($chatId, $msgId); return true; }

    if ($data === 'pf_tog') {
        pfSet(function (&$c) { $c['on'] = empty($c['on']); });
        $ack(pfOn() ? '✅ روشن' : '❌ خاموش');
        pfHome($chatId, $msgId);
        return true;
    }

    if ($data === 'pf_every') {
        setState($uid, 'pf_every', []);
        $ack();
        sendMsg(BOT_TOKEN, $chatId,
            "🎯 <b>روی همه بنشان</b>\n\n" .
            "یک عدد بفرستید — همان درصد در هر چهار بخش می‌نشیند:\n" .
            "خرید ممبر، مینی‌اپ‌ها، شماره مجازی، قیمت‌گیری ارز.\n\n" .
            "مثلا <code>25</code>",
            inlineKb([[btnCb('🔙 بی‌خیال', 'pf_home', 'nav')]]));
        return true;
    }

    // 📈 «سودِ عمومی/ممبر/مینی‌اپ» — اول بپرس درصد یا تومانِ ثابت
    $secLbl = ['pf_all' => 'سودِ عمومی', 'pf_member' => 'سودِ خرید ممبر', 'pf_ma' => 'سودِ مینی‌اپ‌ها',
               'pf_rent' => 'سودِ اجاره‌ی گیفت'];
    if (isset($secLbl[$data])) {
        $sec = substr($data, 3);
        $ack();
        $t = '📈 <b>' . $secLbl[$data] . "</b>\n\nبه چه شکل حساب شود؟\n\n" .
             "📊 <b>درصد</b> — مثلا ۱۲٪ روی قیمتِ پایه\n" .
             "💰 <b>تومانِ ثابت</b> — مثلا همیشه ۵٬۰۰۰ تومان اضافه، صرفِ نظر از قیمتِ پایه";
        $rows = [[btnCb('📊 درصد', 'pfm_' . $sec . '_pct', 'admin'),
                  btnCb('💰 تومانِ ثابت', 'pfm_' . $sec . '_fixed', 'admin')]];
        if ($sec !== 'all') $rows[] = [btnCb('⚪️ از عمومی پیروی کند', 'pfm_' . $sec . '_off', 'info')];
        $rows[] = [btnCb('🔙 بی‌خیال', 'pf_home', 'nav')];
        editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
        return true;
    }

    if (preg_match('/^pfm_(all|member|ma|rent)_(pct|fixed|off)$/', $data, $m)) {
        [$_, $sec, $mode] = $m;
        if ($mode === 'off') {
            pfSecSet($sec, null, null);
            $ack('⚪️ از عمومی پیروی می‌کند');
            pfHome($chatId, $msgId);
            return true;
        }
        setState($uid, 'pf_set', ['sec' => $sec, 'mode' => $mode]);
        $ack();
        $ask = $mode === 'pct'
            ? "چند درصد؟ مثلا <code>25</code>"
            : "چند تومان همیشه اضافه شود؟ مثلا <code>5000</code>";
        sendMsg(BOT_TOKEN, $chatId, '📈 <b>' . $secLbl['pf_' . $sec] . '</b>' .
            ($mode === 'fixed' ? ' — تومانِ ثابت' : ' — درصد') . "\n\n" . $ask,
            inlineKb([[btnCb('🔙 بی‌خیال', 'pf_home', 'nav')]]));
        return true;
    }

    $ack();
    return true;
}

function pfStateHandle($action, $msg, $uid, $chatId) {
    if (!in_array((string)$action, ['pf_set', 'pf_every'], true)) return false;

    $plain = trim((string)($msg['text'] ?? ''));
    $back  = inlineKb([[btnCb('📈 سود', 'pf_home', 'admin')]]);
    $num   = function ($s) {
        $s = strtr((string)$s, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5',
                                '۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٫'=>'.','،'=>'',','=>'']);
        return (float)preg_replace('/[^0-9.]/', '', $s);
    };

    if ($action === 'pf_every') {
        $v = pfSetAll($num($plain));
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId,
            "✅ <b>" . pfPctStr($v) . "</b> روی هر چهار بخش نشست.\n\n" .
            "<i>بخشِ سود هم خودکار روشن شد.</i>", $back);
        return true;
    }

    // pf_set: هم درصد را می‌پذیرد هم تومانِ ثابت — با همان مُدی که قبلا انتخاب شد
    $st  = getState($uid);
    $sd  = $st['data'] ?? [];
    $sec = (string)($sd['sec'] ?? '');
    $mode = (string)($sd['mode'] ?? 'pct');
    if (!in_array($sec, ['all', 'member', 'ma', 'rent'], true)) { clearState($uid); return true; }

    $v = $mode === 'pct' ? max(0.0, min(1000.0, $num($plain))) : max(0.0, $num($plain));
    pfSecSet($sec, $mode, $v);
    clearState($uid);
    $lbl = ['all' => 'سودِ عمومی', 'member' => 'سودِ خرید ممبر', 'ma' => 'سودِ مینی‌اپ‌ها', 'rent' => 'سودِ اجاره‌ی گیفت'][$sec];
    sendMsg(BOT_TOKEN, $chatId, '✅ ' . $lbl . ': <b>' . pfAmountStr(['mode' => $mode, 'v' => $v]) . '</b>' .
        (pfOn() ? '' : "\n\n⚠️ بخشِ سود خاموش است — تا روشنش نکنید نمی‌نشیند."), $back);
    return true;
}
