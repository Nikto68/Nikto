<?php
// ─────────────────────────────────────────────────────────────────────────────
//  شارژِ حساب — دو روش:
//   ۱) ارز دیجیتال (OxaPay / NOWPayments): آدرسِ ولت + مقدار + کیوآر نشان داده
//      می‌شود و به‌محضِ واریز، کیف پول خودکار شارژ می‌شود.
//   ۲) درگاه ایرانی (زرین‌پال / زیبال): شماره‌ی موبایل ← مبلغ ← (اگر بالای سقف
//      بود) احراز هویت ← تایید و لینکِ درگاه با دکمه‌ی شیشه‌ای.
//  همه‌ی متن‌ها و دکمه‌ها از پنل (ربات و وب) قابل ویرایش‌اند؛ با ایموجی پریمیوم و رنگ.
// ─────────────────────────────────────────────────────────────────────────────

if (!defined('PAY_LIB')) define('PAY_LIB', 1);

// ───────── پایه ─────────

function payBase() {
    $b = function_exists('maBaseUrl') ? maBaseUrl() : '';
    return preg_match('#^https://#i', $b) ? rtrim($b, '/') : '';
}
function payQ($b) { return $b . (str_contains($b, '?') ? '&' : '?'); }
function payTok($oid) { return substr(hash_hmac('sha256', 'pay|' . (string)$oid, BOT_TOKEN), 0, 24); }
function payTokOk($oid, $t) { return is_string($t) && $t !== '' && hash_equals(payTok($oid), $t); }

function payPageUrl($oid = '', array $extra = []) {
    $b = payBase();
    if ($b === '') return '';
    $q = ['app' => 'pay', 'v' => function_exists('maViewVer') ? maViewVer() : '1'];
    if ($oid !== '') { $q['o'] = (string)$oid; $q['t'] = payTok($oid); }
    return payQ($b) . http_build_query($q + $extra);
}

function payAmt($v) {
    $s = rtrim(rtrim(number_format((float)$v, 6, '.', ''), '0'), '.');
    return $s === '' ? '0' : $s;
}

function payFa($n) { return strtr(fmtNum($n), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹',','=>'٬']); }

// ───────── تنظیمات ─────────

function irCfg() { return (array)(cfg()['irpay'] ?? []); }
function irProv() { return (irCfg()['provider'] ?? 'zarinpal') === 'zibal' ? 'zibal' : 'zarinpal'; }
function irMerchant() {
    $c = irCfg();
    $m = function_exists('gwCleanKey') ? gwCleanKey($c['merchant'] ?? '') : trim((string)($c['merchant'] ?? ''));
    if (!empty($c['sandbox']) && irProv() === 'zibal') return 'zibal';
    return $m;
}
function irOn() {
    return !empty(irCfg()['on']) && irMerchant() !== '' && payBase() !== '';
}
function cardOn() {
    return !empty(cfg()['topup_card']) && trim((string)(cfg()['wallets']['card'] ?? '')) !== '';
}
function tuMethods() {
    $m = [];
    if (function_exists('gwOn') && gwOn()) $m[] = 'crypto';
    if (irOn()) $m[] = 'iran';
    if (cardOn()) $m[] = 'card';
    return $m;
}
function tuMin($m) {
    $base = max(1000.0, (float)(cfg()['topup_min'] ?? 10000));
    if ($m === 'crypto') return max($base, (float)(cfg()['gateway']['min'] ?? 0));
    if ($m === 'iran')   return max($base, (float)(irCfg()['min'] ?? 0));
    return $base;
}
function tuMax($m) {
    if ($m === 'iran') { $x = (float)(irCfg()['max'] ?? 0); if ($x > 0) return $x; }
    return 500000000.0;
}
function tuQuick($m) {
    $min = tuMin($m); $max = tuMax($m); $out = [];
    foreach ([50000, 100000, 200000, 500000, 1000000, 2000000, 5000000] as $v)
        if ($v >= $min && $v <= $max) $out[] = $v;
    return array_slice($out, 0, 4);
}
function kycLimit() { return (float)(irCfg()['kyc_limit'] ?? 0); }
// احراز هویت فقط با شماره‌ی موبایل (بدونِ اسم و فامیل):
//  otp   = زرین‌پال یک کدِ یک‌بارمصرف (پیامک/USSD) به همان شماره می‌فرستد و کاربر کد را وارد می‌کند (خودکار)
//  phone = شماره برای مدیر فرستاده می‌شود و مدیر تایید می‌کند (وقتی کلیدِ OAuth زرین‌پال گذاشته نشده)
//  docs  = کدِ ملی + عکسِ کارتِ ملی (اختیاری)
function zpOauthCfg() { $c = irCfg(); $cl = function_exists('gwCleanKey') ? 'gwCleanKey' : 'trim'; return [$cl((string)($c['oauth_id'] ?? '')), $cl((string)($c['oauth_secret'] ?? ''))]; }
function kycMode() {
    $m = (string)(irCfg()['kyc_mode'] ?? 'auto');
    if ($m === 'docs' || $m === 'phone') return $m;
    [$id, $sec] = zpOauthCfg();
    return ($id !== '' && $sec !== '') ? 'otp' : 'phone';
}

// OAuthِ زرین‌پال (next.zarinpal.com/api/oauth/…) — [داده, خطا]
function zpOauth($path, array $body) {
    $r = gwHttp('https://next.zarinpal.com/api/oauth/' . $path, ['Accept: application/json'], $body);
    if (empty($r['ok'])) return [null, (string)($r['error'] ?? 'خطای شبکه')];
    $j = (array)$r['data'];
    $errs = $j['errors'] ?? null;
    if (!empty($errs)) {
        $m = [];
        foreach ((array)$errs as $e) $m[] = is_array($e) ? (string)($e['message'] ?? json_encode($e, JSON_UNESCAPED_UNICODE)) : (string)$e;
        return [null, 'زرین‌پال: ' . trim(implode(' · ', array_filter($m)))];
    }
    $d = (isset($j['data']) && is_array($j['data'])) ? $j['data'] : $j;
    if (!$d) return [null, 'پاسخِ نامعتبرِ زرین‌پال'];
    return [$d, ''];
}

// ارسالِ کدِ یک‌بارمصرف به شماره‌ی کاربر — [ok, info|خطا]
function kycOtpSend($uid) {
    $u = getUser($uid) ?: [];
    $ph = trim((string)($u['phone'] ?? ''));
    if ($ph === '') return [false, 'need_phone'];
    $k = (array)($u['kyc_otp'] ?? []);
    $left = (int)($k['at'] ?? 0) + (int)($k['wait'] ?? 0) - time();
    if ($left > 0) return [false, 'wait:' . $left];
    $ch = (irCfg()['otp_ch'] ?? 'sms') === 'ussd' ? 'ussd' : 'sms';
    [$d, $err] = zpOauth('initialize', ['username' => $ph, 'channel' => $ch]);
    if (!$d) return [false, $err ?: 'ارسالِ کد انجام نشد'];
    $info = ['at' => time(), 'wait' => max(30, min(600, (int)($d['waiting_time'] ?? 120))), 'ch' => (string)($d['channel'] ?? $ch),
             'ussd' => (string)($d['ussd_code'] ?? ''), 'tries' => 0, 'phone' => $ph];
    mutateUser($uid, function (&$x) use ($info) { if ($x !== null) $x['kyc_otp'] = $info; });
    return [true, $info];
}

// بررسیِ کد — [ok, خطا]
function kycOtpCheck($uid, $code) {
    $code = preg_replace('/\D/', '', norm_fa_digits((string)$code));
    $u = getUser($uid) ?: [];
    $k = (array)($u['kyc_otp'] ?? []);
    $ph = trim((string)($u['phone'] ?? ''));
    if ($ph === '' || empty($k['at'])) return [false, 'اول کدِ تایید را درخواست کنید.'];
    if ((string)($k['phone'] ?? $ph) !== $ph) return [false, 'شماره عوض شده؛ دوباره کد بگیرید.'];
    if (strlen($code) < 4 || strlen($code) > 10) return [false, 'کد را درست وارد کنید.'];
    if ((int)($k['tries'] ?? 0) >= 6) return [false, 'دفعاتِ اشتباه زیاد شد؛ کدِ تازه بگیرید.'];
    mutateUser($uid, function (&$x) { if ($x !== null && is_array($x['kyc_otp'] ?? null)) $x['kyc_otp']['tries'] = (int)($x['kyc_otp']['tries'] ?? 0) + 1; });
    [$id, $sec] = zpOauthCfg();
    [$d, $err] = zpOauth('token', ['grant_type' => 'password', 'client_id' => ctype_digit($id) ? (int)$id : $id,
                                   'client_secret' => $sec, 'username' => $ph, 'password' => $code, 'scope' => '*']);
    if (!$d || empty($d['access_token'])) return [false, $err ?: 'کد درست نیست.'];
    mutateUser($uid, function (&$x) use ($ph) {
        if ($x === null) return;
        $x['kyc'] = ['st' => 'ok', 'mode' => 'otp', 'phone' => $ph, 'at' => time(), 'by' => 0];
        unset($x['kyc_otp']);
    });
    kycQueue(function (&$q) use ($uid) { unset($q[(string)$uid]); });
    return [true, ''];
}

function kycOtpAskText($info, $uid) {
    $ussd = trim((string)($info['ussd'] ?? ''));
    return T('kyc_otp_ask', ['phone' => tuPhoneShow(getUser($uid)['phone'] ?? ''),
        'channel' => ($info['ch'] ?? 'sms') === 'ussd' ? 'کدِ USSD' : 'پیامک',
        'ussd' => $ussd !== '' ? "📞 برای گرفتنِ کد، <code>" . h($ussd) . "</code> را شماره‌گیری کنید.\n" : '']);
}

function kycOtpStart($uid, $chatId, $msgId = null) {
    [$ok, $info] = kycOtpSend($uid);
    if (!$ok && $info === 'need_phone') { tuAskPhone($uid, $chatId, 0, 'kyc'); return; }
    if (!$ok && str_starts_with((string)$info, 'wait:')) {
        $st = getState($uid);
        if (($st['action'] ?? '') !== 'kyc_otp') setState($uid, 'kyc_otp');
        $k = (array)(getUser($uid)['kyc_otp'] ?? []);
        tuShow($uid, $chatId, kycOtpAskText($k, $uid) . "\n\n⏳ " . (int)substr($info, 5) . " ثانیه‌ی دیگر می‌توانید کدِ تازه بگیرید.",
               inlineKb([[tuBtn('resend', 'cb', 'tukr'), tuBtn('cancel', 'cb', 'tux')]]), null, $msgId);
        return;
    }
    if (!$ok) {
        tuShow($uid, $chatId, T('kyc_otp_fail', ['error' => h((string)$info)]), inlineKb([[tuBtn('resend', 'cb', 'tukr'), tuBtn('cancel', 'cb', 'tux')]]), null, $msgId);
        return;
    }
    setState($uid, 'kyc_otp');
    tuShow($uid, $chatId, kycOtpAskText($info, $uid), inlineKb([[tuBtn('resend', 'cb', 'tukr'), tuBtn('cancel', 'cb', 'tux')]]), null, $msgId);
}
function kycSt($uid) { return (string)((getUser($uid)['kyc']['st'] ?? '')); }
function kycCode($uid) { return (string)((getUser($uid)['kyc']['code'] ?? '')); }
function kycNeeded($uid, $amt) {
    $c = irCfg();
    return !empty($c['kyc']) && kycLimit() > 0 && $amt > kycLimit() && kycSt($uid) !== 'ok';
}

// ───────── دکمه‌ها (قابلِ ویرایش: متن، ایموجی، ایموجی پریمیوم، رنگ) ─────────

function tuBtnLabels() {
    return [
        'crypto' => 'انتخابِ ارز دیجیتال', 'iran' => 'انتخابِ درگاه ایرانی', 'card' => 'انتخابِ کارت به کارت',
        'phone'  => 'ارسالِ شماره (زیرِ صفحه)', 'open' => 'باز کردنِ درگاهِ ارز', 'copy' => 'کپیِ آدرسِ ولت',
        'check'  => 'بررسیِ پرداخت', 'pay' => 'پرداخت در درگاهِ ایرانی', 'kyc' => 'احراز هویت',
        'less'   => 'مبلغِ دیگر', 'back' => 'برگشت به روش‌ها', 'resend' => 'ارسالِ دوباره‌ی کدِ تایید', 'cancel' => 'انصراف',
    ];
}

function tuBtnCfg($k) {
    $d = defaultConfig()['topup_btns'][$k] ?? ['emoji' => '', 'text' => $k, 'color' => 'none', 'icon' => ''];
    $m = cfg()['topup_btns'][$k] ?? [];
    return (is_array($m) ? $m : []) + $d;
}

// دکمه‌هایی که ادمین می‌تواند از فاکتورِ ربات پنهان کند
function tuBtnHideable() { return ['copy', 'check']; }
function tuBtnOn($k) {
    if (!in_array($k, tuBtnHideable(), true)) return true;
    return !empty(tuBtnCfg($k)['on'] ?? true);
}

function tuBtnText($k) {
    $m = tuBtnCfg($k);
    return trim(trim((string)($m['emoji'] ?? '')) . ' ' . trim((string)($m['text'] ?? '')));
}

function tuBtn($k, $kind = 'cb', $val = '') {
    $m = tuBtnCfg($k);
    $b = ['text' => tuBtnText($k) ?: $k];
    if ($kind === 'cb')          $b['callback_data'] = (string)$val;
    elseif ($kind === 'url')     $b['url'] = (string)$val;
    elseif ($kind === 'webapp')  $b['web_app'] = ['url' => (string)$val];
    elseif ($kind === 'copy')    $b['copy_text'] = ['text' => mb_substr((string)$val, 0, 256)];
    elseif ($kind === 'contact') $b['request_contact'] = true;
    if (isStyle($m['color'] ?? '')) $b['style'] = $m['color'];
    $ic = trim((string)($m['icon'] ?? ''));
    if ($ic !== '') $b['icon_custom_emoji_id'] = $ic;
    return $b;
}

function tuRestoreKb() {
    return (cfg()['ui']['mode'] ?? '') === 'glass' ? ['remove_keyboard' => true] : mainKeyboard();
}

function tuShow($uid, $chatId, $text, $kb, $replyTo = null, $msgId = null) {
    if ($msgId) {
        $r = editMsg(BOT_TOKEN, $chatId, $msgId, $text, $kb);
        if (!empty($r['ok']) || isNotModified($r)) return (int)$msgId;
        return (int)slotGet($uid, 'wallet');
    }
    return (int)panelShow($uid, $chatId, 'wallet', $text, $kb, $replyTo);
}

function tuPhoneNorm($raw) {
    $d = preg_replace('/\D/', '', norm_fa_digits((string)$raw));
    if (str_starts_with($d, '0098')) $d = substr($d, 4);
    elseif (str_starts_with($d, '98') && strlen($d) === 12) $d = substr($d, 2);
    if (strlen($d) === 10 && $d[0] === '9') $d = '0' . $d;
    if (preg_match('/^09\d{9}$/', $d)) return $d;
    return $d !== '' ? '+' . $d : '';
}
function tuPhoneShow($p) {
    $p = (string)$p;
    // نشانه‌ی LRM دو طرف تا وسطِ متنِ فارسی شماره برعکس نشود (۴۴۵۵•••۰۹۱۲)
    if (preg_match('/^(09\d{2})\d{3}(\d{4})$/', $p, $m)) return "\u{200E}" . $m[1] . '•••' . $m[2] . "\u{200E}";
    return $p !== '' ? "\u{200E}" . mb_substr($p, 0, 5) . '•••' . mb_substr($p, -3) . "\u{200E}" : '—';
}

// ───────── ربات: انتخابِ روش ─────────

function tuStart($uid, $chatId, $replyTo = null, $msgId = null) {
    clearState($uid);
    $m = tuMethods();
    if (!$m || $m === ['card']) {
        // فقط کارت‌به‌کارت (یا هنوز هیچ روشی): همان روالِ قبلی
        setState($uid, 'topup_amount');
        tuShow($uid, $chatId, T('topup'), inlineKb([[btnUI('cancel', 'cancel', 'cancel')]]), $replyTo, $msgId);
        return;
    }
    $bal = (float)(getUser($uid)['balance'] ?? 0);
    $rows = [];
    foreach ($m as $k) $rows[] = [tuBtn($k, 'cb', 'tum_' . $k)];
    $rows[] = [tuBtn('cancel', 'cb', 'tux')];
    tuShow($uid, $chatId, T('topup_choose', ['balance' => fmtNum($bal)]), inlineKb($rows), $replyTo, $msgId);
}

function tuAskAmount($uid, $chatId, $m, $msgId = null, $replyTo = null) {
    setState($uid, 'tu_amount', ['m' => $m]);
    $u = getUser($uid) ?: [];
    $vars = ['min' => fmtNum(tuMin($m)), 'balance' => fmtNum((float)($u['balance'] ?? 0)),
             'phone' => tuPhoneShow($u['phone'] ?? ''), 'limit' => fmtNum(kycLimit())];
    $txt = T($m === 'crypto' ? 'topup_crypto_amount' : 'topup_ir_amount', $vars);
    $rows = []; $line = [];
    foreach (tuQuick($m) as $v) {
        $line[] = btnCb(fmtNum($v) . ' تومان', 'tuq_' . $m . '_' . (int)$v, 'info');
        if (count($line) === 2) { $rows[] = $line; $line = []; }
    }
    if ($line) $rows[] = $line;
    $nav = [];
    if (count(tuMethods()) > 1) $nav[] = tuBtn('back', 'cb', 'tuback');
    $nav[] = tuBtn('cancel', 'cb', 'tux');
    $rows[] = $nav;
    tuShow($uid, $chatId, $txt, inlineKb($rows), $replyTo, $msgId);
}

function tuAmountIn($uid, $chatId, $uname, $m, $amt, $msgId = null, $replyTo = null) {
    $amt = round((float)$amt);
    $min = tuMin($m); $max = tuMax($m);
    if ($amt < $min) { sendMsg(BOT_TOKEN, $chatId, '⚠️ حداقل مبلغ ' . fmtNum($min) . ' تومان است.'); return null; }
    if ($amt > $max) { sendMsg(BOT_TOKEN, $chatId, '⚠️ حداکثر مبلغ ' . fmtNum($max) . ' تومان است.'); return null; }
    if ($m === 'crypto') return tuCrypto($uid, $chatId, $uname, $amt, $msgId, $replyTo);
    if ($m === 'iran')   return tuIran($uid, $chatId, $uname, $amt, $msgId, $replyTo);
    clearState($uid);
    return createOrderAndAsk($uid, $chatId, $uname, $amt, 'card');
}

// ───────── ارز دیجیتال ─────────

function tuCryptoNew($uid, $uname, $amt) {
    $oid = Order::create($uid, $uname, $amt);
    Order::set($oid, function (&$x) { $x['method'] = 'crypto'; });
    [$ok, $inv, $err] = gwCreateInvoice($oid, $amt);
    if (!$ok) {
        Order::delete($oid);
        adminAlertOnce('gw_fail', "⚠️ <b>درگاه ارز دیجیتال جواب نداد</b>\n\n<code>" . h((string)$err) .
            "</code>\n\n/panel ← 💳 پرداخت ← 💠 درگاه پرداخت", 900);
        return [null, (string)$err];
    }
    Order::set($oid, function (&$x) use ($inv) { $x['gw'] = $inv; });
    return [Order::get($oid), ''];
}

function tuCrypto($uid, $chatId, $uname, $amt, $msgId = null, $replyTo = null) {
    [$o, ] = tuCryptoNew($uid, $uname, $amt);
    if (!$o) {
        tuShow($uid, $chatId, T('topup_gw_down'), inlineKb([[tuBtn('back', 'cb', 'tuback'), tuBtn('cancel', 'cb', 'tux')]]), $replyTo, $msgId);
        return null;
    }
    clearState($uid);
    $mid = tuShow($uid, $chatId, tuCryptoText($o), tuCryptoKb($o), $replyTo, $msgId);
    if ($mid) Order::set($o['id'], function (&$x) use ($mid, $chatId) { $x['mid'] = (int)$mid; $x['chat'] = $chatId; });
    return $o['id'];
}

function tuCryptoVars($o) {
    $g = (array)($o['gw'] ?? []); $cf = (array)(cfg()['gateway'] ?? []);
    $left = max(0, (int)($g['expires_at'] ?? 0) - time());
    $addr = trim((string)($g['address'] ?? ''));
    $amt  = $g['amount'] ?? null;
    return [
        'amount'  => fmtNum($o['amount']),
        'crypto'  => ($amt !== null && $amt !== '' && (float)$amt > 0) ? '<code>' . h(payAmt($amt)) . '</code>' : '—',
        'coin'    => h((string)($g['coin'] ?? ($cf['coin'] ?? 'USDT'))),
        'network' => h((string)(($g['network'] ?? '') ?: ($cf['network'] ?? '') ?: '—')),
        'address' => $addr !== '' ? '<code>' . h($addr) . '</code>' : 'داخلِ صفحه‌ی درگاه — دکمه‌ی پایین را بزنید',
        'expire'  => (string)max(0, (int)ceil($left / 60)),
        'id'      => '<code>' . h((string)$o['id']) . '</code>',
    ];
}
// درگاه (صفحه‌ی پرداخت یا صفحه‌ی خودِ OxaPay) در دسترس است؟ اگر نه، آدرس داخلِ خودِ پیام می‌آید
function tuCryptoGate($o) {
    return payPageUrl((string)$o['id']) !== '' || !empty(((array)($o['gw'] ?? []))['url']);
}
function tuCryptoText($o) {
    return T(tuCryptoGate($o) ? 'topup_crypto_invoice' : 'topup_crypto_invoice_addr', tuCryptoVars($o));
}

function tuCryptoKb($o) {
    $g = (array)($o['gw'] ?? []);
    $addr = trim((string)($g['address'] ?? ''));
    $rows = [];
    $page = payPageUrl((string)$o['id']);
    if ($page !== '')             $rows[] = [tuBtn('open', 'webapp', $page)];
    elseif (!empty($g['url']))    $rows[] = [tuBtn('open', 'url', (string)$g['url'])];
    $gate = (bool)$rows;
    $r2 = [];
    if ($addr !== '' && (!$gate || tuBtnOn('copy'))) $r2[] = tuBtn('copy', 'copy', $addr);
    if (tuBtnOn('check')) $r2[] = tuBtn('check', 'cb', 'tuc_' . $o['id']);
    if ($r2) $rows[] = $r2;
    $rows[] = [tuBtn('cancel', 'cb', 'tux_' . $o['id'])];
    return inlineKb($rows);
}

// ───────── درگاه ایرانی ─────────

function tuAskPhone($uid, $chatId, $amt = 0, $next = '') {
    setState($uid, 'tu_phone', ['amt' => (float)$amt, 'next' => (string)$next]);
    $kb = ['keyboard' => [[tuBtn('phone', 'contact')], [['text' => tuBtnText('cancel')]]],
           'resize_keyboard' => true, 'one_time_keyboard' => true];
    sendMsg(BOT_TOKEN, $chatId, T('topup_ir_phone'), $kb);
}

function tuIranNew($uid, $uname, $amt) {
    $u = getUser($uid) ?: [];
    $phone = (string)($u['phone'] ?? '');
    if ($phone === '') return [null, 'need_phone'];
    if (kycNeeded($uid, $amt)) return [null, 'need_kyc'];
    $oid = Order::create($uid, $uname, $amt);
    Order::set($oid, function (&$x) use ($phone) { $x['method'] = 'iran'; $x['phone'] = $phone; });
    [$ok, $url, $ref, $err] = irCreate($oid, $amt, $phone, kycSt($uid) === 'ok' ? kycCode($uid) : '');
    if (!$ok) {
        Order::delete($oid);
        adminAlertOnce('ir_fail', "⚠️ <b>درگاه ایرانی جواب نداد</b>\n\n<code>" . h((string)$err) .
            "</code>\n\n/panel ← 💳 پرداخت ← 🏦 درگاه ایرانی", 900);
        return [null, 'down:' . $err];
    }
    Order::set($oid, function (&$x) use ($url, $ref) {
        $x['ir'] = ['prov' => irProv(), 'ref' => (string)$ref, 'url' => (string)$url, 'at' => time()];
    });
    return [Order::get($oid), ''];
}

function tuIran($uid, $chatId, $uname, $amt, $msgId = null, $replyTo = null) {
    [$o, $why] = tuIranNew($uid, $uname, $amt);
    if ($why === 'need_phone') { tuAskPhone($uid, $chatId, $amt); return null; }
    if ($why === 'need_kyc') {
        setState($uid, 'tu_amount', ['m' => 'iran']);
        $pend = kycSt($uid) === 'pending';
        $rows = [];
        if (!$pend) $rows[] = [tuBtn('kyc', 'cb', 'tuk')];
        $rows[] = [tuBtn('less', 'cb', 'tum_iran'), tuBtn('cancel', 'cb', 'tux')];
        tuShow($uid, $chatId, T($pend ? 'kyc_pending' : 'topup_ir_kyc_need',
            ['limit' => fmtNum(kycLimit()), 'amount' => fmtNum($amt)]), inlineKb($rows), $replyTo, $msgId);
        return null;
    }
    if (!$o) {
        tuShow($uid, $chatId, T('topup_ir_down'), inlineKb([[tuBtn('back', 'cb', 'tuback'), tuBtn('cancel', 'cb', 'tux')]]), $replyTo, $msgId);
        return null;
    }
    clearState($uid);
    $mid = tuShow($uid, $chatId, tuIranText($o), tuIranKb($o), $replyTo, $msgId);
    if ($mid) Order::set($o['id'], function (&$x) use ($mid, $chatId) { $x['mid'] = (int)$mid; $x['chat'] = $chatId; });
    return $o['id'];
}

function tuIranText($o) {
    return T('topup_ir_confirm', ['amount' => fmtNum($o['amount']), 'phone' => tuPhoneShow($o['phone'] ?? ''),
                                  'id' => '<code>' . h((string)$o['id']) . '</code>']);
}
function tuIranKb($o) {
    return inlineKb([
        [tuBtn('pay', 'url', (string)($o['ir']['url'] ?? ''))],
        [tuBtn('check', 'cb', 'tuc_' . $o['id']), tuBtn('cancel', 'cb', 'tux_' . $o['id'])],
    ]);
}

function irCallbackUrl($oid) {
    $b = payBase();
    return $b === '' ? '' : payQ($b) . 'irpay=1&o=' . rawurlencode((string)$oid) . '&t=' . payTok($oid);
}

function irHost() {
    return irProv() === 'zibal' ? 'https://gateway.zibal.ir'
         : (!empty(irCfg()['sandbox']) ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com');
}

// [ok, آدرسِ پرداخت, شناسه (authority/trackId), خطا]
function irCreate($oid, $toman, $phone = '', $nat = '') {
    $cb = irCallbackUrl($oid);
    if ($cb === '') return [false, '', '', 'آدرسِ عمومیِ ربات (https) معلوم نیست'];
    $rial = (int)round((float)$toman * 10);
    $desc = trim((string)(irCfg()['desc'] ?? '')) ?: 'شارژ کیف پول';
    $host = irHost();

    if (irProv() === 'zibal') {
        $body = ['merchant' => irMerchant(), 'amount' => $rial, 'callbackUrl' => $cb,
                 'description' => $desc, 'orderId' => (string)$oid];
        if (preg_match('/^09\d{9}$/', (string)$phone)) $body['mobile'] = $phone;
        if ($nat !== '') $body['nationalCode'] = $nat;
        if (!empty(irCfg()['card_check']) && isset($body['mobile'])) $body['checkMobileWithCard'] = true;
        $r = gwHttp($host . '/v1/request', [], $body);
        if (empty($r['ok'])) return [false, '', '', (string)($r['error'] ?? 'خطای شبکه')];
        $d = (array)$r['data'];
        if ((int)($d['result'] ?? 0) === 100 && !empty($d['trackId']))
            return [true, $host . '/start/' . rawurlencode((string)$d['trackId']), (string)$d['trackId'], ''];
        return [false, '', '', 'زیبال: ' . (string)($d['message'] ?? ('کد ' . ($d['result'] ?? '?')))];
    }

    $body = ['merchant_id' => irMerchant(), 'amount' => $rial, 'callback_url' => $cb, 'description' => $desc,
             'metadata' => ['order_id' => (string)$oid]];
    if (preg_match('/^09\d{9}$/', (string)$phone)) $body['metadata']['mobile'] = $phone;
    $r = gwHttp($host . '/pg/v4/payment/request.json', ['Accept: application/json'], $body);
    if (empty($r['ok'])) return [false, '', '', (string)($r['error'] ?? 'خطای شبکه')];
    $d = (array)($r['data']['data'] ?? []);
    if ((int)($d['code'] ?? 0) === 100 && !empty($d['authority']))
        return [true, $host . '/pg/StartPay/' . rawurlencode((string)$d['authority']), (string)$d['authority'], ''];
    $e = (array)($r['data']['errors'] ?? []);
    return [false, '', '', 'زرین‌پال: ' . (string)($e['message'] ?? ('کد ' . ($e['code'] ?? '?')))];
}

// [پرداخت شد؟, کدِ پیگیری/پیام]
function irVerify($o) {
    $ir = (array)($o['ir'] ?? []);
    $ref = (string)($ir['ref'] ?? '');
    if ($ref === '') return [false, 'بدونِ شناسه‌ی پرداخت'];
    $rial = (int)round((float)$o['amount'] * 10);
    $host = irHost();
    if (($ir['prov'] ?? irProv()) === 'zibal') {
        $r = gwHttp($host . '/v1/verify', [], ['merchant' => irMerchant(), 'trackId' => is_numeric($ref) ? (int)$ref : $ref]);
        if (empty($r['ok'])) return [false, (string)($r['error'] ?? 'خطای شبکه')];
        $d = (array)$r['data'];
        $res = (int)($d['result'] ?? 0);
        if (($res === 100 || $res === 201) && (!isset($d['amount']) || (int)$d['amount'] === $rial)) {
            if (!empty($d['cardNumber'])) Order::set($o['id'], function (&$x) use ($d) { $x['ir']['card'] = (string)$d['cardNumber']; });
            return [true, (string)($d['refNumber'] ?? $ref)];
        }
        return [false, (string)($d['message'] ?? ('کد ' . $res))];
    }
    $r = gwHttp($host . '/pg/v4/payment/verify.json', ['Accept: application/json'],
                ['merchant_id' => irMerchant(), 'amount' => $rial, 'authority' => $ref]);
    if (empty($r['ok'])) return [false, (string)($r['error'] ?? 'خطای شبکه')];
    $d = (array)($r['data']['data'] ?? []);
    $code = (int)($d['code'] ?? 0);
    if ($code === 100 || $code === 101) {
        if (!empty($d['card_pan'])) Order::set($o['id'], function (&$x) use ($d) { $x['ir']['card'] = (string)$d['card_pan']; });
        return [true, (string)($d['ref_id'] ?? $ref)];
    }
    $e = (array)($r['data']['errors'] ?? []);
    return [false, (string)($e['message'] ?? ('کد ' . ($e['code'] ?? $code)))];
}

// بازگشت از درگاهِ ایرانی (مرورگرِ مشتری به اینجا برمی‌گردد)
function irCallback() {
    $oid = (string)($_GET['o'] ?? '');
    if (!payTokOk($oid, (string)($_GET['t'] ?? ''))) payResultPage(false, 'لینکِ بازگشت نامعتبر است.');
    $o = Order::get($oid);
    if (!$o || ($o['method'] ?? '') !== 'iran') payResultPage(false, 'این پرداخت پیدا نشد.');
    if (($o['status'] ?? '') === Order::APPROVED) payResultPage(true, '', $o);

    $ir = (array)($o['ir'] ?? []);
    if (($ir['prov'] ?? '') === 'zibal') {
        if ((string)($_GET['trackId'] ?? '') !== '' && (string)$_GET['trackId'] !== (string)($ir['ref'] ?? ''))
            payResultPage(false, 'شناسه‌ی پرداخت با این سفارش نمی‌خواند.', $o);
        if ((string)($_GET['success'] ?? '') !== '1') payResultPage(false, 'پرداخت انجام نشد یا لغو شد.', $o);
    } else {
        if ((string)($_GET['Authority'] ?? '') !== '' && (string)$_GET['Authority'] !== (string)($ir['ref'] ?? ''))
            payResultPage(false, 'شناسه‌ی پرداخت با این سفارش نمی‌خواند.', $o);
        if (strtoupper((string)($_GET['Status'] ?? '')) !== 'OK') payResultPage(false, 'پرداخت انجام نشد یا لغو شد.', $o);
    }
    [$paid, $info] = irVerify($o);
    if (!$paid) payResultPage(false, 'بانک پرداخت را تایید نکرد: ' . $info, $o);
    Order::set($oid, function (&$x) use ($info) { $x['ir']['refnum'] = (string)$info; });
    gwSettle($oid, 'درگاه ایرانی — پیگیری ' . $info);
    payResultPage(true, '', Order::get($oid));
}

function payResultPage($ok, $msg = '', $o = null) {
    $bot = function_exists('botUsername') ? (string)botUsername() : '';
    $back = $bot !== '' ? 'https://t.me/' . rawurlencode($bot) : '';
    $amt = $o ? payFa($o['amount']) : '';
    $ref = $o ? (string)($o['ir']['refnum'] ?? '') : '';
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    $font = function_exists('maFontCss') ? maFontCss() : '';
    $t = $ok ? 'پرداخت موفق بود' : 'پرداخت ناموفق';
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' .
         '<title>' . h($t) . '</title><style>' . $font .
         ':root{color-scheme:dark}*{box-sizing:border-box;margin:0;padding:0}body{min-height:100vh;display:grid;place-items:center;padding:20px;' .
         'font-family:Vazirmatn,Tahoma,system-ui,sans-serif;color:#F4F6FA;background:radial-gradient(90% 60% at 50% 0%,' . ($ok ? 'rgba(34,197,94,.28)' : 'rgba(239,68,68,.26)') . ',transparent 70%),' .
         'radial-gradient(80% 50% at 50% 110%,rgba(59,130,246,.22),transparent 70%),#05070c}' .
         '.c{width:100%;max-width:380px;text-align:center;padding:28px 20px 22px;border-radius:28px;border:1px solid rgba(255,255,255,.16);' .
         'background:linear-gradient(180deg,rgba(255,255,255,.1),rgba(255,255,255,.03));box-shadow:0 30px 60px -30px #000,inset 0 1px 0 rgba(255,255,255,.2);' .
         '-webkit-backdrop-filter:blur(20px);backdrop-filter:blur(20px)}' .
         '.i{width:84px;height:84px;margin:0 auto 14px;border-radius:50%;display:grid;place-items:center;font-size:40px;color:#fff;' .
         'background:' . ($ok ? 'linear-gradient(145deg,#4ADE80,#16A34A)' : 'linear-gradient(145deg,#F87171,#B91C1C)') . ';box-shadow:0 16px 34px -12px ' . ($ok ? 'rgba(34,197,94,.9)' : 'rgba(239,68,68,.9)') . '}' .
         'h1{font-size:21px;font-weight:900}p{margin-top:8px;color:#AEB6C6;font-size:13px;line-height:1.9}b.a{display:block;margin-top:12px;font-size:26px;font-weight:900}' .
         '.r{margin-top:6px;font-size:12px;color:#8B94A5}a.b{display:flex;align-items:center;justify-content:center;height:52px;margin-top:20px;border-radius:17px;' .
         'text-decoration:none;color:#fff;font-weight:900;font-size:14px;background:linear-gradient(135deg,#3B82F6,#22C55E);box-shadow:0 16px 30px -14px rgba(59,130,246,.95)}' .
         '</style></head><body><div class="c"><div class="i">' . ($ok ? '✓' : '✕') . '</div><h1>' . h($t) . '</h1>' .
         ($amt !== '' && $ok ? '<b class="a">' . h($amt) . ' تومان</b>' : '') .
         '<p>' . ($ok ? 'کیف پولتان شارژ شد و داخلِ ربات هم خبرش آمد.' : h($msg ?: 'دوباره امتحان کنید.')) . '</p>' .
         ($ref !== '' ? '<div class="r">کدِ پیگیری: ' . h($ref) . '</div>' : '') .
         ($back !== '' ? '<a class="b" href="' . h($back) . '">بازگشت به ربات</a>' : '') .
         '</div></body></html>';
    exit;
}

// ───────── بعد از شارژ: پیامِ فاکتور هم به‌روز می‌شود ─────────

function payAfterSettle($o) {
    $mid = (int)($o['mid'] ?? 0);
    $chat = $o['chat'] ?? ($o['user_id'] ?? 0);
    if ($mid > 0 && $chat) {
        $t = T('topup_paid', ['amount' => fmtNum($o['amount']), 'id' => '<code>' . h((string)$o['id']) . '</code>',
                              'balance' => fmtNum((float)(getUser((int)$o['user_id'])['balance'] ?? 0))]);
        tg(BOT_TOKEN, 'editMessageText', ['chat_id' => $chat, 'message_id' => $mid, 'text' => $t,
                                          'parse_mode' => 'HTML', 'disable_web_page_preview' => 'true']);
    }
}

// ───────── شماره‌ی موبایل (فقط با دکمه‌ی «ارسال شماره» — شماره‌ی خودِ کاربر) ─────────

function payOnContact($msg, $uid, $chatId) {
    $c = $msg['contact'] ?? null;
    if (!is_array($c)) return false;
    $st = getState($uid);
    $want = ($st['action'] ?? '') === 'tu_phone';
    if ((int)($c['user_id'] ?? 0) !== (int)$uid) {
        sendMsg(BOT_TOKEN, $chatId, T('topup_ir_phone_bad'));
        return true;
    }
    $ph = tuPhoneNorm($c['phone_number'] ?? '');
    if ($ph === '' || (!preg_match('/^09\d{9}$/', $ph) && !empty(irCfg()['ir_only']))) {
        sendMsg(BOT_TOKEN, $chatId, T('topup_ir_phone_bad'));
        return true;
    }
    mutateUser($uid, function (&$u) use ($ph) { if ($u !== null) { $u['phone'] = $ph; $u['phone_at'] = time(); } });
    sendMsg(BOT_TOKEN, $chatId, T('topup_ir_phone_ok', ['phone' => tuPhoneShow($ph)]), tuRestoreKb());
    if ($want) {
        $amt = (float)($st['data']['amt'] ?? 0);
        $next = (string)($st['data']['next'] ?? '');
        clearState($uid);
        $rt = $msg['message_id'] ?? null;
        if ($next === 'kyc') kycStart($uid, $chatId);
        elseif ($amt > 0) tuIran($uid, $chatId, (string)($msg['from']['username'] ?? ''), $amt, null, $rt);
        else tuAskAmount($uid, $chatId, 'iran', null, $rt);
    }
    return true;
}

// ───────── احراز هویت ─────────

function natCodeOk($c) {
    $c = (string)$c;
    if (!preg_match('/^\d{10}$/', $c) || preg_match('/^(\d)\1{9}$/', $c)) return false;
    $s = 0;
    for ($i = 0; $i < 9; $i++) $s += (int)$c[$i] * (10 - $i);
    $r = $s % 11; $chk = (int)$c[9];
    return ($r < 2 && $chk === $r) || ($r >= 2 && $chk === 11 - $r);
}

function kycStart($uid, $chatId, $msgId = null) {
    $st = kycSt($uid);
    if ($st === 'ok') { sendMsg(BOT_TOKEN, $chatId, T('kyc_ok'), inlineKb([[tuBtn('iran', 'cb', 'tum_iran')]])); return; }
    if ($st === 'pending') { sendMsg(BOT_TOKEN, $chatId, T('kyc_pending', ['limit' => fmtNum(kycLimit())])); return; }
    if (kycMode() === 'otp') {
        if (trim((string)(getUser($uid)['phone'] ?? '')) === '') { tuAskPhone($uid, $chatId, 0, 'kyc'); return; }
        kycOtpStart($uid, $chatId, $msgId);
        return;
    }
    if (kycMode() === 'phone') {
        $ph = trim((string)(getUser($uid)['phone'] ?? ''));
        if ($ph === '') { tuAskPhone($uid, $chatId, 0, 'kyc'); return; }
        tuShow($uid, $chatId, T('kyc_phone_confirm', ['phone' => tuPhoneShow($ph)]),
               inlineKb([[tuBtn('kyc', 'cb', 'tuks')], [tuBtn('cancel', 'cb', 'tux')]]), null, $msgId);
        return;
    }
    setState($uid, 'kyc_code');
    tuShow($uid, $chatId, T('kyc_code_ask'), inlineKb([[tuBtn('cancel', 'cb', 'tux')]]), null, $msgId);
}

function kycDir() {
    $d = DATA_DIR . '/kyc';
    if (is_dir($d)) {
        // حتی اگر پوشه‌ی داده داخلِ سایت باشد، این پوشه جداگانه بسته است
        if (!is_file($d . '/.htaccess'))  @file_put_contents($d . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
        if (!is_file($d . '/index.html')) @file_put_contents($d . '/index.html', '');
    }
    return $d;
}
// نامِ فایلِ عکسِ کارتِ ملی تصادفی است (نه آیدیِ کاربر) تا حدس‌زدنی نباشد؛ فایل‌های قدیمیِ «آیدی.img» هم خوانده می‌شوند
function kycImgPath($uid) {
    $n = (string)((getUser((int)$uid) ?: [])['kyc']['img'] ?? '');
    if ($n !== '' && preg_match('/^[a-f0-9]{32}\.img$/', $n) && is_file(kycDir() . '/' . $n)) return kycDir() . '/' . $n;
    $old = kycDir() . '/' . (int)$uid . '.img';
    return is_file($old) ? $old : '';
}

function kycSavePhoto($uid, $fileId) {
    $f  = tg(BOT_TOKEN, 'getFile', ['file_id' => (string)$fileId], 10);
    $fp = (string)($f['result']['file_path'] ?? '');
    if ($fp === '') return false;
    $ch = curl_init(rtrim(TG_API_BASE, '/') . '/file/bot' . BOT_TOKEN . '/' . $fp);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8]);
    $bin = curl_exec($ch);
    curl_close($ch);
    if (!is_string($bin) || strlen($bin) < 64 || strlen($bin) > 8000000) return false;
    if (!is_dir(DATA_DIR . '/kyc')) @mkdir(DATA_DIR . '/kyc', 0700, true);
    $name = bin2hex(random_bytes(16)) . '.img';
    if (@file_put_contents(kycDir() . '/' . $name, $bin) === false) return false;
    $prev = kycImgPath($uid);
    mutateUser($uid, function (&$u) use ($name) { if ($u !== null) $u['kyc']['img'] = $name; });
    if ($prev !== '' && basename($prev) !== $name) @unlink($prev);
    return true;
}

function kycQueue(callable $fn = null) {
    if ($fn === null) { $q = load('kyc_queue'); return is_array($q) ? $q : []; }
    return mutate('kyc_queue', function (&$q) use ($fn) { if (!is_array($q)) $q = []; $fn($q); });
}

function kycSubmit($uid, $uname, $fname, $code, $fileId, $isDoc) {
    mutateUser($uid, function (&$u) use ($code, $fileId, $isDoc) {
        if ($u === null) return;
        $u['kyc'] = ['st' => 'pending', 'code' => (string)$code, 'photo' => (string)$fileId, 'doc' => $isDoc ? 1 : 0,
                     'phone' => (string)($u['phone'] ?? ''), 'mode' => $fileId !== '' ? 'docs' : 'phone', 'at' => time(),
                     'img' => (string)($u['kyc']['img'] ?? '')];
    });
    kycQueue(function (&$q) use ($uid) { $q[(string)$uid] = time(); });
    if ($fileId !== '') kycSavePhoto($uid, $fileId);
    kycNotify($uid, $uname, $fname);
}

// احراز هویت فقط با شماره‌ی موبایل: شماره همان است که کاربر با دکمه‌ی «ارسال شماره» فرستاده (تلگرام تاییدش کرده)
function kycSubmitPhone($uid) {
    $u = getUser($uid) ?: [];
    if (trim((string)($u['phone'] ?? '')) === '') return 'need_phone';
    $st = kycSt($uid);
    if ($st === 'ok' || $st === 'pending') return $st;
    kycSubmit($uid, (string)($u['username'] ?? ''), (string)($u['first_name'] ?? ''), '', '', false);
    return 'pending';
}

function kycCaption($uid) {
    $u = getUser($uid) ?: [];
    $k = (array)($u['kyc'] ?? []);
    return "🪪 <b>درخواستِ احراز هویت</b>\n\n" .
           "👤 " . h((string)($u['first_name'] ?? $u['name'] ?? '')) . (!empty($u['username']) ? ' (@' . h($u['username']) . ')' : '') .
           "\n🆔 <code>" . (int)$uid . "</code>\n📱 شماره: <code>" . h((string)($u['phone'] ?? '—')) . "</code>" .
           (trim((string)($k['code'] ?? '')) !== '' ? "\n🔢 کدِ ملی: <code>" . h((string)$k['code']) . "</code>" : '') .
           "\n\n" . (($k['mode'] ?? 'phone') === 'docs' ? '📎 مدارک: عکسِ کارتِ ملی' : '☑️ احراز با شماره‌ی موبایل (تاییدشده توسطِ تلگرام)');
}

function kycKb($uid) {
    return inlineKb([[btnCb('✅ تایید', 'akyc_ok_' . (int)$uid, 'confirm'), btnCb('❌ رد', 'akyc_no_' . (int)$uid, 'reject')]]);
}

function kycNotify($uid, $uname = '', $fname = '') {
    $u = getUser($uid) ?: [];
    $k = (array)($u['kyc'] ?? []);
    $fid = (string)($k['photo'] ?? '');
    foreach (ADMIN_IDS as $aid) {
        $data = ['chat_id' => $aid, 'caption' => kycCaption($uid), 'parse_mode' => 'HTML',
                 'reply_markup' => kbJson(kycKb($uid))];
        $r = null;
        if ($fid !== '') {
            $data[!empty($k['doc']) ? 'document' : 'photo'] = $fid;
            $r = tg(BOT_TOKEN, !empty($k['doc']) ? 'sendDocument' : 'sendPhoto', $data);
            if (empty($r['ok']) && isStyleError($r)) {
                $data['reply_markup'] = json_encode(stripStyles(kycKb($uid)));
                $r = tg(BOT_TOKEN, !empty($k['doc']) ? 'sendDocument' : 'sendPhoto', $data);
            }
        }
        if (empty($r['ok'])) sendMsg(BOT_TOKEN, $aid, kycCaption($uid), kycKb($uid));
    }
}

function kycDecide($uid, $ok, $by, $note = '') {
    $done = false;
    mutateUser($uid, function (&$u) use ($ok, $by, $note, &$done) {
        if ($u === null || !is_array($u['kyc'] ?? null)) return;
        if (($u['kyc']['st'] ?? '') !== 'pending') return;
        $u['kyc']['st'] = $ok ? 'ok' : 'rejected';
        $u['kyc']['by'] = (int)$by; $u['kyc']['dec_at'] = time();
        if ($note !== '') $u['kyc']['note'] = $note;
        $done = true;
    });
    kycQueue(function (&$q) use ($uid) { unset($q[(string)$uid]); });
    if (!$done) return false;
    if ($ok) sendMsg(BOT_TOKEN, (int)$uid, T('kyc_ok'), irOn() ? inlineKb([[tuBtn('iran', 'cb', 'tum_iran')]]) : null);
    else     sendMsg(BOT_TOKEN, (int)$uid, T('kyc_no', ['note' => h($note)]), inlineKb([[tuBtn('kyc', 'cb', 'tuk')]]));
    return true;
}

// ───────── کال‌بکِ کاربر ─────────

function payUserCallback($data, $uid, $chatId, $msgId, $cbId, $uname) {
    if (!preg_match('/^tu(m_|q_|back$|x$|x_|c_|k$|ks$|kr$)/', $data)) return false;

    if ($data === 'tux') {
        clearState($uid);
        answerCb(BOT_TOKEN, $cbId, 'لغو شد');
        if ($msgId) { delMsg(BOT_TOKEN, $chatId, $msgId); slotClear($uid, 'wallet'); }
        return true;
    }
    if (str_starts_with($data, 'tux_')) {
        $oid = substr($data, 4);
        $o = Order::get($oid);
        clearState($uid);
        if ($o && (int)$o['user_id'] === $uid && ($o['status'] ?? '') === Order::PENDING) Order::delete($oid);
        answerCb(BOT_TOKEN, $cbId, 'لغو شد');
        tuStart($uid, $chatId, null, $msgId);
        return true;
    }
    if ($data === 'tuback') { answerCb(BOT_TOKEN, $cbId); tuStart($uid, $chatId, null, $msgId); return true; }
    if ($data === 'tuk')    { answerCb(BOT_TOKEN, $cbId); kycStart($uid, $chatId, $msgId); return true; }
    if ($data === 'tukr') {
        if (kycSt($uid) === 'ok') { answerCb(BOT_TOKEN, $cbId, '✅ قبلا تایید شده'); kycStart($uid, $chatId, $msgId); return true; }
        answerCb(BOT_TOKEN, $cbId, '📩');
        kycOtpStart($uid, $chatId, $msgId);
        return true;
    }
    if ($data === 'tuks') {
        $r = kycSubmitPhone($uid);
        if ($r === 'need_phone') { answerCb(BOT_TOKEN, $cbId); tuAskPhone($uid, $chatId, 0, 'kyc'); return true; }
        answerCb(BOT_TOKEN, $cbId, $r === 'ok' ? '✅ قبلا تایید شده' : '✅ برای بررسی فرستاده شد');
        if ($r === 'ok') { kycStart($uid, $chatId, $msgId); return true; }
        tuShow($uid, $chatId, T('kyc_sent'), inlineKb([[tuBtn('less', 'cb', 'tum_iran')]]), null, $msgId);
        return true;
    }

    if (str_starts_with($data, 'tum_')) {
        $m = substr($data, 4);
        if (!in_array($m, tuMethods(), true)) { answerCb(BOT_TOKEN, $cbId, 'این روش الان فعال نیست.', true); tuStart($uid, $chatId, null, $msgId); return true; }
        answerCb(BOT_TOKEN, $cbId);
        if ($m === 'card') {
            setState($uid, 'topup_amount');
            tuShow($uid, $chatId, T('topup'), inlineKb([[tuBtn('back', 'cb', 'tuback'), tuBtn('cancel', 'cb', 'tux')]]), null, $msgId);
            return true;
        }
        if ($m === 'iran' && trim((string)(getUser($uid)['phone'] ?? '')) === '') { tuAskPhone($uid, $chatId); return true; }
        tuAskAmount($uid, $chatId, $m, $msgId);
        return true;
    }
    if (preg_match('/^tuq_(crypto|iran)_(\d{3,10})$/', $data, $mm)) {
        if (!in_array($mm[1], tuMethods(), true)) { answerCb(BOT_TOKEN, $cbId, 'این روش الان فعال نیست.', true); return true; }
        answerCb(BOT_TOKEN, $cbId, '⏳');
        tuAmountIn($uid, $chatId, $uname, $mm[1], (float)$mm[2], $msgId);
        return true;
    }
    if (str_starts_with($data, 'tuc_')) {
        $oid = substr($data, 4);
        $o = Order::get($oid);
        if (!$o || (int)$o['user_id'] !== $uid) { answerCb(BOT_TOKEN, $cbId, 'پیدا نشد', true); return true; }
        if (($o['status'] ?? '') === Order::APPROVED) { answerCb(BOT_TOKEN, $cbId, '✅ این پرداخت قبلا تایید و شارژ شده.', true); return true; }
        if (!maRateOk('tuchk', $uid, 8, 60)) { answerCb(BOT_TOKEN, $cbId, 'کمی صبر کنید؛ خودکار هم بررسی می‌شود.', true); return true; }
        [$paid, $st] = payCheck($o, true);
        if ($paid) { answerCb(BOT_TOKEN, $cbId, '✅ پرداخت تایید شد'); return true; }
        $left = max(0, (int)($o['gw']['expires_at'] ?? 0) - time());
        answerCb(BOT_TOKEN, $cbId, ($o['method'] ?? '') === 'iran'
            ? "⏳ هنوز پرداختی تایید نشده.\nاول با دکمه‌ی «" . tuBtnText('pay') . "» پرداخت کنید."
            : ($left > 0 ? "⏳ هنوز واریزی دیده نشد.\nمهلت: " . sprintf('%02d:%02d', intdiv($left, 60), $left % 60)
                         : "⌛️ مهلتِ این فاکتور تمام شد؛ دوباره شارژ بزنید."), true);
        return true;
    }
    return false;
}

// بررسیِ یک سفارش (ارز یا ایرانی) و شارژ اگر پرداخت شده بود — [پرداخت شد؟, وضعیت]
function payCheck($o, $force = false) {
    if (!$o) return [false, 'none'];
    if (($o['status'] ?? '') === Order::APPROVED) return [true, 'paid'];
    if (in_array($o['status'] ?? '', [Order::REJECTED], true)) return [false, 'rejected'];
    $key = 'paychk_' . $o['id'];
    if (!$force && function_exists('maCacheGet') && maCacheGet($key, 15)) return [false, 'wait'];
    if (function_exists('maCachePut')) maCachePut($key, 1);
    if (($o['method'] ?? '') === 'iran') {
        [$paid, $info] = irVerify($o);
        if ($paid) {
            Order::set($o['id'], function (&$x) use ($info) { $x['ir']['refnum'] = (string)$info; });
            gwSettle($o['id'], 'درگاه ایرانی — پیگیری ' . $info);
            return [true, 'paid'];
        }
        return [false, 'pending'];
    }
    if (!empty($o['gw']['invoice'])) {
        [$paid, $st] = gwCheck($o);
        if ($paid) { gwSettle($o['id']); return [true, 'paid']; }
        return [false, (string)$st];
    }
    return [false, 'pending'];
}

// ───────── وضعیت‌های گفتگو ─────────

function payStateHandle($action, $msg, $uid, $chatId) {
    if (!in_array($action, ['tu_amount', 'tu_phone', 'kyc_code', 'kyc_photo', 'kyc_otp'], true)) return false;
    $st = getState($uid); $sd = (array)($st['data'] ?? []);
    $text = trim((string)($msg['text'] ?? ''));
    $uname = (string)($msg['from']['username'] ?? '');
    $fname = (string)($msg['from']['first_name'] ?? '');
    $rt = $msg['message_id'] ?? null;

    if ($action === 'tu_amount') {
        $amt = round(maNum($text));
        if ($amt <= 0) { sendMsg(BOT_TOKEN, $chatId, '⚠️ مبلغ را فقط با عدد و به تومان بفرستید؛ مثلا ۲۰۰۰۰۰'); return true; }
        tuAmountIn($uid, $chatId, $uname, (string)($sd['m'] ?? ''), $amt, null, $rt);
        return true;
    }
    if ($action === 'tu_phone') {
        if (!empty($msg['contact'])) return payOnContact($msg, $uid, $chatId);
        if ($text === tuBtnText('cancel') || $text === UT('cancel')) {
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, '❌ لغو شد.', tuRestoreKb());
            return true;
        }
        sendMsg(BOT_TOKEN, $chatId, T('topup_ir_phone_bad'));
        return true;
    }
    if ($action === 'kyc_otp') {
        [$ok, $err] = kycOtpCheck($uid, $text);
        if (!$ok) {
            $again = str_contains($err, 'تازه') || str_contains($err, 'درخواست');
            sendMsg(BOT_TOKEN, $chatId, T('kyc_otp_bad', ['error' => h($err)]),
                inlineKb([[tuBtn('resend', 'cb', 'tukr'), tuBtn('cancel', 'cb', 'tux')]]));
            if ($again) clearState($uid);
            return true;
        }
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, T('kyc_ok'), irOn() ? inlineKb([[tuBtn('iran', 'cb', 'tum_iran')]]) : null);
        return true;
    }
    if ($action === 'kyc_code') {
        $d = preg_replace('/\D/', '', norm_fa_digits($text));
        if (!natCodeOk($d)) { sendMsg(BOT_TOKEN, $chatId, T('kyc_code_bad'), inlineKb([[tuBtn('cancel', 'cb', 'tux')]])); return true; }
        setState($uid, 'kyc_photo', ['code' => $d]);
        sendMsg(BOT_TOKEN, $chatId, T('kyc_photo_ask'), inlineKb([[tuBtn('cancel', 'cb', 'tux')]]));
        return true;
    }
    if ($action === 'kyc_photo') {
        $fid = ''; $doc = false;
        if (!empty($msg['photo'])) { $p = $msg['photo']; $fid = (string)($p[count($p) - 1]['file_id'] ?? ''); }
        elseif (!empty($msg['document']) && str_starts_with((string)($msg['document']['mime_type'] ?? ''), 'image/')) {
            $fid = (string)($msg['document']['file_id'] ?? ''); $doc = true;
        }
        if ($fid === '') { sendMsg(BOT_TOKEN, $chatId, T('kyc_photo_ask'), inlineKb([[tuBtn('cancel', 'cb', 'tux')]])); return true; }
        $code = (string)($sd['code'] ?? '');
        if (!natCodeOk($code)) { setState($uid, 'kyc_code'); sendMsg(BOT_TOKEN, $chatId, T('kyc_code_ask')); return true; }
        clearState($uid);
        kycSubmit($uid, $uname, $fname, $code, $fid, $doc);
        sendMsg(BOT_TOKEN, $chatId, T('kyc_sent'));
        return true;
    }
    return false;
}

// ───────── صفحه‌ی درگاه (مینی‌اپِ پرداخت) ─────────

function payBackUrl($k) {
    // کیف پول از مینی‌اپ‌ها برداشته شد؛ برگشت به خانه‌ی همان مینی‌اپ
    if ($k === 'tgs' && function_exists('svUrl')) return svUrl('tg');
    if ($k === 'igs' && function_exists('svUrl')) return svUrl('ig');
    if ($k === 'num' && function_exists('maUrl')) return maUrl();
    return '';
}

function payView($o) {
    if (!$o) return null;
    $st = (string)($o['status'] ?? '');
    $g = (array)($o['gw'] ?? []);
    $exp = (int)($g['expires_at'] ?? 0);
    $state = $st === Order::APPROVED ? 'paid' : ($st === Order::REJECTED ? 'rejected'
           : (($o['method'] ?? '') === 'crypto' && $exp > 0 && time() > $exp + 60 ? 'expired' : 'pending'));
    $v = ['id' => (string)$o['id'], 'm' => (string)($o['method'] ?? 'crypto'), 'st' => $state,
          'amount' => (float)$o['amount'], 't' => payTok($o['id'])];
    if ($v['m'] === 'crypto') {
        $addr = trim((string)($g['address'] ?? ''));
        $v['c'] = ['amt' => ($g['amount'] ?? '') !== '' && (float)($g['amount'] ?? 0) > 0 ? payAmt($g['amount']) : '',
                   'coin' => (string)($g['coin'] ?? ''), 'net' => (string)($g['network'] ?? ''),
                   'addr' => $addr, 'url' => (string)($g['url'] ?? ''), 'exp' => $exp,
                   'qr' => $addr !== '' ? qrSvg($addr) : ''];
    } else {
        $v['i'] = ['url' => (string)($o['ir']['url'] ?? ''), 'phone' => tuPhoneShow($o['phone'] ?? ''),
                   'ref' => (string)($o['ir']['refnum'] ?? '')];
    }
    return $v;
}

function payInfo($uid = 0) {
    $g = (array)(cfg()['gateway'] ?? []);
    $u = $uid ? (getUser($uid) ?: []) : [];
    return [
        'crypto' => ['on' => (function_exists('gwOn') && gwOn()) ? 1 : 0, 'min' => tuMin('crypto'),
                     'coin' => strtoupper(trim((string)($g['coin'] ?? 'USDT'))) ?: 'USDT',
                     'net' => strtoupper(trim((string)($g['network'] ?? ''))),
                     'rate' => (float)($g['rate'] ?? 0) > 0 ? (float)$g['rate'] : (function_exists('pxUsdtToman') ? (float)pxUsdtToman() : 0),
                     'q' => tuQuick('crypto')],
        'iran'   => ['on' => irOn() ? 1 : 0, 'min' => tuMin('iran'), 'max' => tuMax('iran'),
                     'kyc' => (!empty(irCfg()['kyc']) && kycLimit() > 0) ? kycLimit() : 0, 'q' => tuQuick('iran')],
        'me'     => $uid ? ['phone' => tuPhoneShow($u['phone'] ?? ''), 'hasPhone' => trim((string)($u['phone'] ?? '')) !== '' ? 1 : 0,
                            'kyc' => (string)($u['kyc']['st'] ?? ''), 'balance' => (float)($u['balance'] ?? 0)] : null,
    ];
}

function payServe() {
    $oid = (string)($_GET['o'] ?? '');
    $o = null;
    if ($oid !== '' && payTokOk($oid, (string)($_GET['t'] ?? ''))) $o = Order::get($oid);
    $th = in_array($_GET['th'] ?? '', ['tgs', 'igs', 'num'], true) ? (string)$_GET['th'] : payPageTheme();
    $bk = in_array($_GET['back'] ?? '', ['tgs', 'igs', 'num'], true) ? (string)$_GET['back'] : '';
    $boot = [
        'api'  => function_exists('maApiUrl') ? maApiUrl() : '',
        'st'   => payQ(payBase()) . 'paystat=1',
        'bot'  => function_exists('botUsername') ? (string)botUsername() : '',
        'th'   => $th,
        'back' => $bk !== '' ? payBackUrl($bk) : '',
        'm'    => in_array($_GET['m'] ?? '', ['crypto', 'iran'], true) ? (string)$_GET['m'] : '',
        'a'    => max(0, (int)maNum($_GET['a'] ?? 0)),
        'info' => payInfo(0),
        'o'    => payView($o),
        'txt'  => ['crypto' => tuBtnText('crypto'), 'iran' => tuBtnText('iran')],
        'pt'   => (object)payPageOverrides(),
    ];
    $html = payPageHtml($boot);
    if (function_exists('maSecurityHeaders')) maSecurityHeaders();
    else header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    if (function_exists('maEmit')) maEmit($html); else echo $html;
    exit;
}

// وضعیتِ یک پرداخت بدونِ initData (با توکنِ امضاشده) — صفحه‌ی درگاه هر چند ثانیه این را می‌پرسد
function payStatusJson() {
    $oid = (string)($_GET['o'] ?? '');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    if (!payTokOk($oid, (string)($_GET['t'] ?? ''))) { http_response_code(403); echo '{"ok":false}'; exit; }
    $o = Order::get($oid);
    if (!$o) { http_response_code(404); echo '{"ok":false}'; exit; }
    if (($o['status'] ?? '') === Order::PENDING) {
        $force = !empty($_GET['check']) && function_exists('maRateOk') && maRateOk('paychk', $oid, 6, 60);
        if (($o['method'] ?? '') === 'crypto' || $force) { payCheck($o, $force); $o = Order::get($oid); }
    }
    $v = payView($o);
    echo json_encode(['ok' => true, 'st' => $v['st'], 'bal' => (float)(getUser((int)$o['user_id'])['balance'] ?? 0),
                      'ref' => (string)($o['ir']['refnum'] ?? '')], JSON_UNESCAPED_UNICODE);
    exit;
}

// اکشن‌های API برای صفحه‌ی درگاه و مینی‌اپ‌ها (با initData)
function payApi($action, $uid, $uname, array $body) {
    if ($action === 'pay_info') maApiOut(['ok' => true] + payInfo($uid));
    if ($action === 'pay_new') {
        if (!maRateOk('paynew', $uid, 10, 300))
            maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'درخواستِ شارژ زیاد شد؛ چند دقیقه بعد دوباره.'], 429);
        $m = (string)($body['m'] ?? '');
        if (!in_array($m, ['crypto', 'iran'], true) || !in_array($m, tuMethods(), true))
            maApiOut(['ok' => false, 'error' => 'off', 'message' => 'این روشِ پرداخت الان فعال نیست.'], 400);
        $amt = round(maNum($body['amount'] ?? 0));
        if ($amt < tuMin($m)) maApiOut(['ok' => false, 'error' => 'amount', 'message' => 'حداقل مبلغ ' . fmtNum(tuMin($m)) . ' تومان است.'], 400);
        if ($amt > tuMax($m)) maApiOut(['ok' => false, 'error' => 'amount', 'message' => 'حداکثر مبلغ ' . fmtNum(tuMax($m)) . ' تومان است.'], 400);
        if ($m === 'crypto') {
            [$o, $err] = tuCryptoNew($uid, $uname, $amt);
            if (!$o) maApiOut(['ok' => false, 'error' => 'down', 'message' => 'درگاهِ ارز دیجیتال الان جواب نمی‌دهد؛ کمی بعد دوباره.'], 502);
            maApiOut(['ok' => true, 'o' => payView($o)]);
        }
        [$o, $why] = tuIranNew($uid, $uname, $amt);
        if ($why === 'need_phone') maApiOut(['ok' => false, 'error' => 'need_phone', 'message' => 'اول شماره‌ی موبایلتان را بفرستید.']);
        if ($why === 'need_kyc') maApiOut(['ok' => false, 'error' => 'need_kyc', 'kyc' => kycSt($uid), 'limit' => kycLimit(), 'mode' => kycMode(),
                                           'message' => 'برای پرداختِ بیش از ' . fmtNum(kycLimit()) . ' تومان، احراز هویت لازم است.']);
        if (!$o) maApiOut(['ok' => false, 'error' => 'down', 'message' => 'درگاهِ ایرانی الان جواب نمی‌دهد؛ کمی بعد دوباره.'], 502);
        maApiOut(['ok' => true, 'o' => payView($o)]);
    }
    if ($action === 'pay_kyc' && kycMode() === 'otp') {
        if (kycSt($uid) === 'ok') maApiOut(['ok' => true, 'kyc' => 'ok']);
        if (($body['step'] ?? '') === 'code') {
            if (!maRateOk('kycode', $uid, 8, 300)) maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'کمی صبر کنید.']);
            [$ok, $err] = kycOtpCheck($uid, (string)($body['code'] ?? ''));
            if (!$ok) maApiOut(['ok' => false, 'error' => 'bad_code', 'message' => $err]);
            maApiOut(['ok' => true, 'kyc' => 'ok']);
        }
        [$ok, $info] = kycOtpSend($uid);
        if (!$ok && $info === 'need_phone') maApiOut(['ok' => false, 'error' => 'need_phone', 'message' => 'اول شماره‌ی موبایلتان را بفرستید.']);
        if (!$ok && str_starts_with((string)$info, 'wait:'))
            maApiOut(['ok' => true, 'kyc' => 'otp', 'sent' => 1, 'wait' => (int)substr($info, 5), 'phone' => tuPhoneShow(getUser($uid)['phone'] ?? '')]);
        if (!$ok) maApiOut(['ok' => false, 'error' => 'otp_fail', 'message' => (string)$info]);
        maApiOut(['ok' => true, 'kyc' => 'otp', 'sent' => 1, 'wait' => (int)$info['wait'], 'ch' => $info['ch'], 'ussd' => $info['ussd'],
                  'phone' => tuPhoneShow(getUser($uid)['phone'] ?? '')]);
    }
    if ($action === 'pay_kyc') {
        if (kycMode() !== 'phone') maApiOut(['ok' => false, 'error' => 'docs', 'message' => 'احراز هویت از داخلِ ربات انجام می‌شود.']);
        $r = kycSubmitPhone($uid);
        if ($r === 'need_phone') maApiOut(['ok' => false, 'error' => 'need_phone', 'message' => 'اول شماره‌ی موبایلتان را بفرستید.']);
        maApiOut(['ok' => true, 'kyc' => $r]);
    }
    if ($action === 'pay_cancel') {
        $o = Order::get((string)($body['order'] ?? ''));
        if ($o && (int)$o['user_id'] === $uid && ($o['status'] ?? '') === Order::PENDING) Order::delete($o['id']);
        maApiOut(['ok' => true]);
    }
    maApiOut(['ok' => false, 'error' => 'bad_action'], 400);
}

// ───────── پنلِ ربات: درگاه ایرانی، احراز هویت، متن‌ها و دکمه‌های شارژ ─────────

function payTextLabels() {
    return [
        'topup_choose' => '💳 انتخابِ روشِ شارژ', 'topup_crypto_amount' => '🪙 مبلغِ شارژِ ارزی',
        'topup_crypto_invoice' => '🪙 فاکتور (ورود به درگاه)', 'topup_crypto_invoice_addr' => '🪙 فاکتور بدونِ درگاه (با آدرس)',
        'topup_gw_down' => '🪙 درگاهِ ارز جواب نداد',
        'topup_ir_phone' => '🏦 درخواستِ شماره', 'topup_ir_phone_ok' => '🏦 شماره ثبت شد',
        'topup_ir_phone_bad' => '🏦 شماره‌ی نامعتبر', 'topup_ir_amount' => '🏦 مبلغِ شارژِ ریالی',
        'topup_ir_kyc_need' => '🏦 نیاز به احراز هویت', 'topup_ir_confirm' => '🏦 تایید و لینکِ درگاه',
        'topup_ir_down' => '🏦 درگاهِ ایرانی جواب نداد', 'topup_paid' => '✅ فاکتورِ پرداخت‌شده',
        'kyc_otp_ask' => '🪪 درخواستِ کدِ تایید (زرین‌پال)', 'kyc_otp_bad' => '🪪 کدِ تاییدِ اشتباه', 'kyc_otp_fail' => '🪪 کد فرستاده نشد',
        'kyc_phone_confirm' => '🪪 احراز با شماره (تایید و ارسال)',
        'kyc_code_ask' => '🪪 درخواستِ کدِ ملی', 'kyc_code_bad' => '🪪 کدِ ملیِ نادرست',
        'kyc_photo_ask' => '🪪 درخواستِ عکسِ کارتِ ملی', 'kyc_sent' => '🪪 مدارک ثبت شد',
        'kyc_pending' => '🪪 در حالِ بررسی', 'kyc_ok' => '🪪 احراز هویت تایید شد', 'kyc_no' => '🪪 احراز هویت رد شد',
    ];
}

function payTextVars() {
    return [
        'topup_choose' => '{balance}', 'topup_crypto_amount' => '{min} {balance}',
        'topup_crypto_invoice' => '{amount} {crypto} {coin} {network} {address} {expire} {id}',
        'topup_crypto_invoice_addr' => '{amount} {crypto} {coin} {network} {address} {expire} {id}',
        'topup_ir_phone_ok' => '{phone}', 'topup_ir_amount' => '{min} {balance} {phone} {limit}',
        'topup_ir_kyc_need' => '{limit} {amount}', 'topup_ir_confirm' => '{amount} {phone} {id}',
        'topup_paid' => '{amount} {balance} {id}', 'kyc_no' => '{note}', 'kyc_phone_confirm' => '{phone}', 'kyc_pending' => '{limit}',
        'kyc_otp_ask' => '{phone} {channel} {ussd}', 'kyc_otp_bad' => '{error}', 'kyc_otp_fail' => '{error}',
    ];
}

// متن‌های صفحه‌ی درگاه (پیش‌فرض همان است که در صفحه نوشته شده) — از پنلِ ربات عوض می‌شوند
function payPageTexts() {
    return [
        'title'     => ['درگاه پرداخت', 'عنوانِ بالای صفحه'],
        'lock'      => ['پرداختِ امن', 'برچسبِ کنارِ عنوان'],
        'amt'       => ['مبلغِ شارژ', 'برچسبِ مبلغ'],
        'go'        => ['ادامه و پرداخت', 'دکمه‌ی ساختِ فاکتور'],
        'wait'      => ['در انتظارِ واریز', 'وضعیتِ فاکتورِ ارزی'],
        'exact'     => ['مقدارِ دقیقِ واریز', 'برچسبِ مقدارِ واریز'],
        'copyaddr'  => ['کپیِ آدرسِ ولت', 'دکمه‌ی کپیِ آدرس'],
        'openpg'    => ['باز کردنِ صفحه‌ی پرداخت', 'دکمه‌ی صفحه‌ی درگاه (حالتِ صفحه)'],
        'warn'      => ['فقط {coin} روی شبکه‌ی {network} و دقیقا همین مقدار را بفرستید؛ کارمزدِ برداشتِ صرافی را جدا حساب کنید.', 'هشدارِ واریز ({coin} {network})'],
        'live'      => ['بعد از واریز، خودکار شارژ می‌شود — لازم نیست کاری کنید', 'خطِ «خودکار شارژ می‌شود»'],
        'check'     => ['بررسی پرداخت', 'دکمه‌ی بررسی'],
        'cancel'    => ['انصراف', 'دکمه‌ی انصراف'],
        'ir_title'  => ['تاییدِ پرداخت', 'عنوانِ درگاهِ ایرانی'],
        'ir_pay'    => ['پرداختِ آنلاین', 'دکمه‌ی پرداختِ ایرانی'],
        'ir_hint'   => ['درگاه در مرورگر باز می‌شود؛ بعد از پرداخت به همین‌جا برگردید — خودکار شارژ می‌شود.', 'راهنمای درگاهِ ایرانی'],
        'ph_title'  => ['شماره‌ی موبایل', 'عنوانِ درخواستِ شماره'],
        'ph_text'   => ["برای پرداخت با درگاهِ ایرانی، شماره‌ی موبایلِ خودتان را یک‌بار بفرستید.\nشماره باید به نامِ صاحبِ کارت باشد.", 'توضیحِ درخواستِ شماره'],
        'ph_go'     => ['ارسالِ شماره‌ی من', 'دکمه‌ی ارسالِ شماره'],
        'ok_title'  => ['حسابتان شارژ شد', 'عنوانِ پرداختِ موفق'],
        'ok_back'   => ['بازگشت', 'دکمه‌ی بازگشت'],
        'exp_title' => ['مهلتِ این فاکتور تمام شد', 'عنوانِ فاکتورِ منقضی'],
        'exp_text'  => ['دوباره مبلغ را وارد کنید تا فاکتورِ تازه ساخته شود.', 'توضیحِ فاکتورِ منقضی'],
        'again'     => ['شارژِ دوباره', 'دکمه‌ی شارژِ دوباره'],
    ];
}
function payPageThemes() { return ['num' => 'آبی و سبز', 'tgs' => 'بنفشِ نئونی', 'igs' => 'صورتیِ اینستاگرامی']; }
function payPageTheme() {
    $t = (string)(cfg()['pay_page']['th'] ?? 'num');
    return isset(payPageThemes()[$t]) ? $t : 'num';
}
function payPageOverrides() {
    $o = [];
    foreach ((array)(cfg()['pay_page']['t'] ?? []) as $k => $v)
        if (isset(payPageTexts()[$k]) && is_string($v) && trim($v) !== '') $o[$k] = $v;
    return $o;
}

function payAdmPt($chatId, $msgId) {
    $ov = payPageOverrides();
    $t  = "🖥 <b>متن‌های صفحه‌ی درگاه</b>\n\n";
    $t .= "صفحه‌ای که کاربر با دکمه‌ی فاکتور وارد می‌شود. هر مورد را بزنید و متنِ تازه را بفرستید.\n";
    $t .= "✏️ = عوض شده · بقیه پیش‌فرض.\n\n🎨 رنگِ صفحه: <b>" . h(payPageThemes()[payPageTheme()]) . "</b>";
    $rows = []; $line = [];
    foreach (payPageTexts() as $k => [$def, $lbl]) {
        $line[] = btnCb((isset($ov[$k]) ? '✏️ ' : '') . $lbl, 'payx_pte_' . $k, isset($ov[$k]) ? 'confirm' : 'info');
        if (count($line) === 2) { $rows[] = $line; $line = []; }
    }
    if ($line) $rows[] = $line;
    $rows[] = [btnCb('🎨 رنگِ صفحه: ' . payPageThemes()[payPageTheme()], 'payx_pth', 'admin')];
    if ($ov) $rows[] = [btnCb('♻️ همه‌ی متن‌ها پیش‌فرض', 'payx_ptr', 'reject')];
    $rows[] = [btnUI('back', 'payx_ed', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function payAdmHome($chatId, $msgId) {
    $cr = function_exists('gwOn') && gwOn(); $ir = irOn(); $cd = cardOn();
    $q = count(kycQueue());
    $t  = "💳 <b>شارژِ حساب</b>\n\n";
    $t .= "💎 ارز دیجیتال: " . ($cr ? '✅ فعال' : '❌ خاموش/ناقص') . "\n";
    $t .= "🏦 درگاه ایرانی: " . ($ir ? '✅ فعال' : '❌ خاموش/ناقص') . "\n";
    $t .= "💳 کارت به کارت: " . ($cd ? '✅ فعال' : '❌ خاموش') . "\n";
    $t .= "🪪 احراز هویتِ منتظرِ بررسی: <b>" . $q . "</b>\n\n";
    $t .= "کاربر «افزایش موجودی» که بزند، روش‌های فعال را می‌بیند.";
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb([
        [btnCb('💠 ارز دیجیتال', 'adm_gw', 'admin'), btnCb('🏦 درگاه ایرانی', 'payx_ir', 'admin')],
        [btnCb('💳 کارت به کارت', 'adm_pay', 'admin'), btnCb(($cd ? '✅' : '❌') . ' کارت در منو', 'payx_card', 'info')],
        [btnCb('🪪 احراز هویت‌ها (' . $q . ')', 'payx_kyc', 'admin')],
        [btnCb('✏️ متن‌ها و دکمه‌های شارژ', 'payx_ed', 'admin')],
        [btnUI('back', 'adm_home', 'nav')],
    ]));
}

function payAdmIr($chatId, $msgId) {
    $c = irCfg();
    $pv = irProv() === 'zibal' ? 'زیبال' : 'زرین‌پال';
    $t  = "🏦 <b>درگاه پرداخت ایرانی</b>\n\n";
    $t .= "وضعیت: " . (irOn() ? '✅ آماده' : (!empty($c['on']) ? '⚠️ روشن ولی ناقص' : '❌ خاموش')) . "\n";
    $t .= "درگاه: <b>{$pv}</b>" . (!empty($c['sandbox']) ? ' (آزمایشی)' : '') . "\n";
    $m = trim((string)($c['merchant'] ?? ''));
    $t .= "مرچنت: " . ($m !== '' ? '✅ ' . h(mb_substr($m, 0, 6)) . '…' : '<b>خالی</b>') . "\n";
    $t .= "حداقل: <b>" . fmtNum(tuMin('iran')) . "</b> · حداکثر: <b>" . ((float)($c['max'] ?? 0) > 0 ? fmtNum($c['max']) : 'بی‌سقف') . "</b> تومان\n";
    $t .= "احراز هویت: " . (!empty($c['kyc']) ? '✅ بالای <b>' . fmtNum(kycLimit()) . '</b> تومان — ' .
          ['otp' => 'کدِ یک‌بارمصرفِ زرین‌پال به شماره‌ی موبایل', 'phone' => 'شماره‌ی موبایل + تاییدِ مدیر', 'docs' => 'کدِ ملی + عکسِ کارتِ ملی'][kycMode()] : '❌ خاموش') . "\n";
    [$oid0, $osc0] = zpOauthCfg();
    $t .= "OAuthِ زرین‌پال (برای کدِ پیامکی): " . ($oid0 !== '' && $osc0 !== '' ? '✅ ثبت شده' : '— ثبت نشده (client_id و client_secret را از پشتیبانیِ زرین‌پال بگیرید)') . "\n";
    $t .= "فقط شماره‌ی ایرانی: " . (!empty($c['ir_only']) ? '✅' : '❌') . "\n";
    if (payBase() === '') $t .= "\n⚠️ آدرسِ عمومیِ ربات (https) معلوم نیست؛ پنلِ وب ← API ← آدرسِ عمومی.";
    else $t .= "\n📡 آدرسِ بازگشت خودکار ساخته می‌شود؛ لازم نیست جایی ثبتش کنید.";
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb([
        [btnCb(!empty($c['on']) ? '❌ خاموش کردن' : '✅ روشن کردن', 'payx_ir_on', 'info'), btnCb('🔀 ' . $pv, 'payx_ir_p', 'admin')],
        [btnCb('🔑 مرچنت', 'payx_ir_m', 'admin'), btnCb((!empty($c['sandbox']) ? '🧪 آزمایشی: روشن' : '🧪 آزمایشی: خاموش'), 'payx_ir_s', 'admin')],
        [btnCb('🔻 حداقل', 'payx_ir_min', 'admin'), btnCb('🔺 حداکثر', 'payx_ir_max', 'admin')],
        [btnCb((!empty($c['kyc']) ? '🪪 احراز: روشن' : '🪪 احراز: خاموش'), 'payx_ir_k', 'admin'), btnCb('📏 سقفِ بدونِ احراز', 'payx_ir_kl', 'admin')],
        [btnCb((!empty($c['ir_only']) ? '🇮🇷 فقط ایرانی: روشن' : '🇮🇷 فقط ایرانی: خاموش'), 'payx_ir_io', 'admin'),
         btnCb(['otp' => '📩 احراز: کدِ پیامکیِ زرین‌پال', 'phone' => '📱 احراز: شماره + تاییدِ مدیر', 'docs' => '📎 احراز: کد ملی + عکس'][kycMode()], 'payx_ir_km', 'admin')],
        [btnCb('🔐 OAuth: client_id', 'payx_ir_ci', 'admin'), btnCb('🔐 OAuth: client_secret', 'payx_ir_cs', 'admin')],
        [btnCb(((irCfg()['otp_ch'] ?? 'sms') === 'ussd' ? '📞 کد با USSD' : '💬 کد با پیامک'), 'payx_ir_ch', 'admin')],
        [btnCb('🧪 تستِ ساختِ لینک', 'payx_ir_t', 'confirm')],
        [btnUI('back', 'payx_home', 'nav')],
    ]));
}

function payAdmKyc($chatId, $msgId) {
    $q = kycQueue(); arsort($q);
    $t = "🪪 <b>احراز هویت‌های منتظر</b>\n\n" . ($q ? "روی هرکدام بزنید تا مدارکش بیاید:" : "فعلا درخواستی نیست.");
    $rows = [];
    foreach (array_slice(array_keys($q), 0, 12) as $u) {
        $uu = getUser((int)$u) ?: [];
        $rows[] = [btnCb('👁 ' . mb_substr((string)($uu['first_name'] ?? $uu['name'] ?? $u), 0, 20) . ' · ' . $u, 'payx_kv_' . (int)$u, 'info')];
    }
    $rows[] = [btnUI('back', 'payx_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function payAdmEd($chatId, $msgId) {
    $t  = "✏️ <b>متن‌ها و دکمه‌های شارژ</b>\n\n";
    $t .= "هر متن را بزنید؛ موقعِ فرستادنِ متنِ تازه، <b>ایموجی پریمیوم</b>، نقل‌قول و قالب‌بندی حفظ می‌شود.\n";
    $t .= "هر دکمه: متن، ایموجیِ معمولی، ایموجیِ پریمیوم و رنگ.";
    $rows = []; $line = [];
    foreach (payTextLabels() as $k => $lbl) {
        $line[] = btnCb($lbl, 'et_' . $k, 'info');
        if (count($line) === 2) { $rows[] = $line; $line = []; }
    }
    if ($line) $rows[] = $line;
    $line = [];
    foreach (tuBtnLabels() as $k => $lbl) {
        $line[] = btnCb('🔘 ' . $lbl, 'payx_b_' . $k, 'admin');
        if (count($line) === 2) { $rows[] = $line; $line = []; }
    }
    if ($line) $rows[] = $line;
    $rows[] = [btnCb('🖥 متن‌ها و رنگِ صفحه‌ی درگاه', 'payx_pt', 'admin')];
    $rows[] = [btnUI('back', 'payx_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function payAdmBtn($chatId, $msgId, $k) {
    if (!isset(tuBtnLabels()[$k])) { payAdmEd($chatId, $msgId); return; }
    $m = tuBtnCfg($k);
    $t  = "🔘 <b>" . h(tuBtnLabels()[$k]) . "</b>\n\n";
    $t .= "متن: " . h(trim((string)($m['text'] ?? '')) ?: '—') . "\n";
    $t .= "ایموجیِ معمولی: " . (trim((string)($m['emoji'] ?? '')) !== '' ? h($m['emoji']) : '<b>ندارد</b>') . "\n";
    $t .= "✨ پریمیوم: " . (!empty($m['icon']) ? '<tg-emoji emoji-id="' . h($m['icon']) . '">✨</tg-emoji> <code>' . h($m['icon']) . '</code>' : '—') . "\n";
    $t .= "رنگ: " . (styleMap()[$m['color'] ?? 'none'] ?? '—') . "\n\n";
    $t .= "👇 پیش‌نمایش:";
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb([
        [tuBtn($k, 'cb', 'trnop')],
        [btnCb('✏️ متن', 'payx_bt_' . $k, 'admin'), btnCb('😀 ایموجی', 'payx_be_' . $k, 'admin')],
        [btnCb('✨ پریمیوم', 'payx_bi_' . $k, 'admin'), btnCb('🎨 رنگ', 'payx_bc_' . $k, 'admin')],
        in_array($k, tuBtnHideable(), true)
            ? [btnCb(tuBtnOn($k) ? '👁 در فاکتور نمایش داده می‌شود' : '🙈 در فاکتور پنهان است', 'payx_bv_' . $k, tuBtnOn($k) ? 'confirm' : 'reject')]
            : [],
        [btnCb('♻️ پیش‌فرض', 'payx_br_' . $k, 'reject')],
        [btnUI('back', 'payx_ed', 'nav')],
    ]));
}

function payAdminCallback($data, $chatId, $msgId, $cbId) {
    if (str_starts_with($data, 'akyc_')) {
        if (!preg_match('/^akyc_(ok|no)_(\d+)$/', $data, $m)) { answerCb(BOT_TOKEN, $cbId); return true; }
        $admin = admStateUid($chatId);
        $ok = kycDecide((int)$m[2], $m[1] === 'ok', $admin);
        answerCb(BOT_TOKEN, $cbId, $ok ? ($m[1] === 'ok' ? '✅ تایید شد' : '❌ رد شد') : 'قبلا بررسی شده', !$ok);
        if ($msgId) {
            $cap = kycCaption((int)$m[2]) . "\n\n" . ($ok ? ($m[1] === 'ok' ? '✅ <b>تایید شد</b>' : '❌ <b>رد شد</b>') : 'ℹ️ قبلا بررسی شده');
            $r = tg(BOT_TOKEN, 'editMessageCaption', ['chat_id' => $chatId, 'message_id' => $msgId, 'caption' => $cap, 'parse_mode' => 'HTML']);
            if (empty($r['ok'])) editMsg(BOT_TOKEN, $chatId, $msgId, $cap, null);
        }
        return true;
    }
    if (!str_starts_with($data, 'payx_')) return false;
    $admin = admStateUid($chatId);

    if ($data === 'payx_home') { answerCb(BOT_TOKEN, $cbId); payAdmHome($chatId, $msgId); return true; }
    if ($data === 'payx_ir')   { answerCb(BOT_TOKEN, $cbId); clearState($admin); payAdmIr($chatId, $msgId); return true; }
    if ($data === 'payx_kyc')  { answerCb(BOT_TOKEN, $cbId); payAdmKyc($chatId, $msgId); return true; }
    if ($data === 'payx_ed')   { answerCb(BOT_TOKEN, $cbId); clearState($admin); payAdmEd($chatId, $msgId); return true; }
    if ($data === 'payx_card') {
        $on = !empty(cfg()['topup_card']);
        cfgSet(function (&$c) use ($on) { $c['topup_card'] = !$on; });
        answerCb(BOT_TOKEN, $cbId, !empty(cfg()['topup_card']) ? 'کارت به کارت در منوی شارژ نشان داده می‌شود' : 'کارت به کارت از منوی شارژ برداشته شد', true);
        payAdmHome($chatId, $msgId);
        return true;
    }
    if ($data === 'payx_gm') {
        $nx = (cfg()['gateway']['mode'] ?? 'address') === 'page' ? 'address' : 'page';
        cfgSet(function (&$c) use ($nx) { $c['gateway']['mode'] = $nx; });
        answerCb(BOT_TOKEN, $cbId, '✅');
        admGateway($chatId, $msgId);
        return true;
    }
    $flip = ['payx_ir_on' => 'on', 'payx_ir_s' => 'sandbox', 'payx_ir_k' => 'kyc', 'payx_ir_io' => 'ir_only'];
    if (isset($flip[$data])) {
        $f = $flip[$data];
        $on = !empty(irCfg()[$f]);
        cfgSet(function (&$c) use ($f, $on) { $c['irpay'][$f] = !$on; });
        answerCb(BOT_TOKEN, $cbId, '✅');
        payAdmIr($chatId, $msgId);
        return true;
    }
    if ($data === 'payx_ir_ch') {
        $nx = (irCfg()['otp_ch'] ?? 'sms') === 'ussd' ? 'sms' : 'ussd';
        cfgSet(function (&$c) use ($nx) { $c['irpay']['otp_ch'] = $nx; });
        answerCb(BOT_TOKEN, $cbId, '✅');
        payAdmIr($chatId, $msgId);
        return true;
    }
    if ($data === 'payx_ir_km') {
        $cur = (string)(irCfg()['kyc_mode'] ?? 'auto');
        $nx = ['auto' => 'phone', 'otp' => 'phone', 'phone' => 'docs', 'docs' => 'auto'][$cur] ?? 'auto';
        cfgSet(function (&$c) use ($nx) { $c['irpay']['kyc_mode'] = $nx; });
        answerCb(BOT_TOKEN, $cbId, '✅');
        payAdmIr($chatId, $msgId);
        return true;
    }
    if ($data === 'payx_ir_p') {
        $nx = irProv() === 'zibal' ? 'zarinpal' : 'zibal';
        cfgSet(function (&$c) use ($nx) { $c['irpay']['provider'] = $nx; });
        answerCb(BOT_TOKEN, $cbId, '✅');
        payAdmIr($chatId, $msgId);
        return true;
    }
    if ($data === 'payx_ir_t') {
        answerCb(BOT_TOKEN, $cbId);
        if (!irOn()) { sendMsg(BOT_TOKEN, $chatId, "⚠️ اول درگاه را روشن کنید و مرچنت را بگذارید."); return true; }
        [$ok, $url, , $err] = irCreate('or_TEST' . bin2hex(random_bytes(3)), max(10000, tuMin('iran')));
        sendMsg(BOT_TOKEN, $chatId, $ok ? "✅ <b>درگاه کار می‌کند</b>\n\n🔗 " . h($url) : "❌ <b>درگاه جواب نداد</b>\n\n<code>" . h($err) . "</code>");
        return true;
    }
    $asks = ['payx_ir_m' => ['tue_ir_m', "🔑 مرچنتِ درگاه را بفرستید (زرین‌پال: ۳۶ کاراکتر · زیبال: کدِ مرچنت).\nخط تیره = پاک کردن"],
             'payx_ir_min' => ['tue_ir_min', "🔻 حداقلِ شارژ با درگاهِ ایرانی (تومان):"],
             'payx_ir_max' => ['tue_ir_max', "🔺 حداکثرِ هر پرداخت (تومان) — ۰ یعنی بی‌سقف:"],
             'payx_ir_kl'  => ['tue_ir_kl', "📏 تا چه مبلغی بدونِ احراز هویت؟ (تومان)\nبالاتر از این، احراز هویت لازم است. ۰ = هیچ‌وقت لازم نیست."],
             'payx_ir_ci'  => ['tue_ir_ci', "🔐 client_id (عدد) که از پشتیبانیِ زرین‌پال گرفته‌اید را بفرستید.\nخط تیره = پاک کردن"],
             'payx_ir_cs'  => ['tue_ir_cs', "🔐 client_secret زرین‌پال را بفرستید.\nخط تیره = پاک کردن"]];
    if (isset($asks[$data])) {
        answerCb(BOT_TOKEN, $cbId);
        setState($admin, $asks[$data][0], []);
        sendMsg(BOT_TOKEN, $chatId, $asks[$data][1], inlineKb([[btnUI('cancel', 'payx_ir', 'cancel')]]));
        return true;
    }
    if (preg_match('/^payx_kv_(\d+)$/', $data, $m)) {
        answerCb(BOT_TOKEN, $cbId);
        if (kycSt((int)$m[1]) !== 'pending') { sendMsg(BOT_TOKEN, $chatId, 'این درخواست دیگر منتظر نیست.'); return true; }
        $u = getUser((int)$m[1]) ?: []; $k = (array)($u['kyc'] ?? []);
        $d = ['chat_id' => $chatId, 'caption' => kycCaption((int)$m[1]), 'parse_mode' => 'HTML', 'reply_markup' => kbJson(kycKb((int)$m[1]))];
        $d[!empty($k['doc']) ? 'document' : 'photo'] = (string)($k['photo'] ?? '');
        $r = tg(BOT_TOKEN, !empty($k['doc']) ? 'sendDocument' : 'sendPhoto', $d);
        if (empty($r['ok'])) sendMsg(BOT_TOKEN, $chatId, kycCaption((int)$m[1]), kycKb((int)$m[1]));
        return true;
    }
    if (preg_match('/^payx_b_(\w+)$/', $data, $m)) { answerCb(BOT_TOKEN, $cbId); clearState($admin); payAdmBtn($chatId, $msgId, $m[1]); return true; }
    if (preg_match('/^payx_bv_(\w+)$/', $data, $m) && in_array($m[1], tuBtnHideable(), true)) {
        $k = $m[1]; $on = tuBtnOn($k);
        cfgSet(function (&$c) use ($k, $on) { $c['topup_btns'][$k]['on'] = !$on; });
        answerCb(BOT_TOKEN, $cbId, $on ? '🙈 پنهان شد' : '👁 نمایش داده می‌شود');
        payAdmBtn($chatId, $msgId, $k);
        return true;
    }
    if ($data === 'payx_pt') { answerCb(BOT_TOKEN, $cbId); clearState($admin); payAdmPt($chatId, $msgId); return true; }
    if ($data === 'payx_pth') {
        $ks = array_keys(payPageThemes()); $i = array_search(payPageTheme(), $ks, true);
        $nx = $ks[((int)$i + 1) % count($ks)];
        cfgSet(function (&$c) use ($nx) { $c['pay_page']['th'] = $nx; });
        answerCb(BOT_TOKEN, $cbId, '🎨 ' . payPageThemes()[$nx]);
        payAdmPt($chatId, $msgId);
        return true;
    }
    if ($data === 'payx_ptr') {
        cfgSet(function (&$c) { $c['pay_page']['t'] = []; });
        answerCb(BOT_TOKEN, $cbId, '♻️');
        payAdmPt($chatId, $msgId);
        return true;
    }
    if (preg_match('/^payx_pte_(\w+)$/', $data, $m) && isset(payPageTexts()[$m[1]])) {
        answerCb(BOT_TOKEN, $cbId);
        [$def, $lbl] = payPageTexts()[$m[1]];
        $cur = payPageOverrides()[$m[1]] ?? $def;
        setState($admin, 'tue_pt', ['k' => $m[1]]);
        sendMsg(BOT_TOKEN, $chatId, "🖥 <b>" . h($lbl) . "</b>\n\nالان:\n<blockquote>" . h($cur) . "</blockquote>\n" .
            "متنِ تازه را بفرستید (ایموجیِ معمولی هم می‌شود). خط تیره = پیش‌فرض." .
            (str_contains($def, '{') ? "\n\nمتغیرها: <code>{coin}</code> <code>{network}</code>" : ''),
            inlineKb([[btnUI('cancel', 'payx_pt', 'cancel')]]));
        return true;
    }
    if (preg_match('/^payx_bc_(\w+)$/', $data, $m) && isset(tuBtnLabels()[$m[1]])) {
        $k = $m[1];
        $nx = nextStyle((string)(tuBtnCfg($k)['color'] ?? 'none'));
        cfgSet(function (&$c) use ($k, $nx) { $c['topup_btns'][$k]['color'] = $nx; });
        answerCb(BOT_TOKEN, $cbId, '🎨');
        payAdmBtn($chatId, $msgId, $k);
        return true;
    }
    if (preg_match('/^payx_br_(\w+)$/', $data, $m) && isset(tuBtnLabels()[$m[1]])) {
        $k = $m[1];
        cfgSet(function (&$c) use ($k) { unset($c['topup_btns'][$k]); });
        answerCb(BOT_TOKEN, $cbId, '♻️');
        payAdmBtn($chatId, $msgId, $k);
        return true;
    }
    if (preg_match('/^payx_b(t|e|i)_(\w+)$/', $data, $m) && isset(tuBtnLabels()[$m[2]])) {
        answerCb(BOT_TOKEN, $cbId);
        setState($admin, 'tue_b' . $m[1], ['k' => $m[2], 'mid' => (int)$msgId]);
        $ask = ['t' => "✏️ متنِ تازه‌ی دکمه را بفرستید.\n\n✨ اگر ایموجیِ پریمیوم هم بگذارید، روی دکمه می‌نشیند.",
                'e' => "😀 ایموجیِ معمولیِ دکمه را بفرستید (خط تیره = حذف).",
                'i' => "✨ خودِ ایموجیِ پریمیوم را بفرستید (یا کدِ عددی‌اش). خط تیره = حذف."][$m[1]];
        sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnUI('cancel', 'payx_b_' . $m[2], 'cancel')]]));
        return true;
    }
    answerCb(BOT_TOKEN, $cbId);
    return true;
}

function payAdminState($action, $msg, $uid, $chatId) {
    if (!str_starts_with($action, 'tue_')) return false;
    $st = getState($uid); $sd = (array)($st['data'] ?? []);
    $plain = trim((string)($msg['text'] ?? ''));
    $blank = ($plain === '-' || $plain === '—');

    if (in_array($action, ['tue_ir_m', 'tue_ir_min', 'tue_ir_max', 'tue_ir_kl', 'tue_ir_ci', 'tue_ir_cs'], true)) {
        $back = inlineKb([[btnCb('🏦 درگاه ایرانی', 'payx_ir', 'admin')]]);
        if ($action === 'tue_ir_ci' || $action === 'tue_ir_cs') {
            $v = $blank ? '' : preg_replace('/\s+/', '', $plain);
            if ($action === 'tue_ir_ci' && $v !== '' && !ctype_digit($v)) { sendMsg(BOT_TOKEN, $chatId, '⚠️ client_id عدد است.'); return true; }
            $f = $action === 'tue_ir_ci' ? 'oauth_id' : 'oauth_secret';
            cfgSet(function (&$c) use ($f, $v) { $c['irpay'][$f] = $v; });
            if ($msg['message_id'] ?? 0) delMsg(BOT_TOKEN, $chatId, (int)$msg['message_id']);
        } elseif ($action === 'tue_ir_m') {
            $v = $blank ? '' : preg_replace('/\s+/', '', $plain);
            if ($v !== '' && !preg_match('/^[A-Za-z0-9\-]{4,64}$/', $v)) { sendMsg(BOT_TOKEN, $chatId, '⚠️ مرچنت فقط حروفِ انگلیسی، عدد و خط تیره است.'); return true; }
            cfgSet(function (&$c) use ($v) { $c['irpay']['merchant'] = $v; });
        } else {
            $n = round(maNum($plain));
            if ($n < 0 || ($plain !== '0' && $n == 0 && $plain !== '۰')) { sendMsg(BOT_TOKEN, $chatId, '⚠️ فقط عدد بفرستید.'); return true; }
            $f = ['tue_ir_min' => 'min', 'tue_ir_max' => 'max', 'tue_ir_kl' => 'kyc_limit'][$action];
            cfgSet(function (&$c) use ($f, $n) { $c['irpay'][$f] = $n; });
        }
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد.', $back);
        return true;
    }
    if ($action === 'tue_pt') {
        $k = (string)($sd['k'] ?? '');
        if (!isset(payPageTexts()[$k])) { clearState($uid); return true; }
        // صفحه‌ی وب متنِ ساده می‌گیرد (نه HTML)؛ ایموجیِ پریمیوم آن‌جا نمایش داده نمی‌شود
        $v = $blank ? '' : mb_substr(trim(strip_tags(textWithoutCustomEmoji($msg))), 0, 300);
        if (!$blank && $v === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ متن خالی است.'); return true; }
        cfgSet(function (&$c) use ($k, $v) {
            if (!is_array($c['pay_page']['t'] ?? null)) $c['pay_page']['t'] = [];
            if ($v === '') unset($c['pay_page']['t'][$k]); else $c['pay_page']['t'][$k] = $v;
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $v === '' ? '♻️ به پیش‌فرض برگشت.' : '✅ ذخیره شد؛ از همین حالا در صفحه‌ی درگاه دیده می‌شود.',
            inlineKb([[btnCb('🖥 متن‌های صفحه‌ی درگاه', 'payx_pt', 'nav')]]));
        return true;
    }
    if (in_array($action, ['tue_bt', 'tue_be', 'tue_bi'], true)) {
        $k = (string)($sd['k'] ?? '');
        if (!isset(tuBtnLabels()[$k])) { clearState($uid); return true; }
        $back = inlineKb([[btnCb('🔘 ' . tuBtnLabels()[$k], 'payx_b_' . $k, 'nav')]]);
        $ids = customEmojiIds($msg);
        if ($action === 'tue_bt') {
            $txt = trim(textWithoutCustomEmoji($msg));
            if ($txt === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ متن خالی است.'); return true; }
            cfgSet(function (&$c) use ($k, $txt, $ids) {
                $c['topup_btns'][$k]['text'] = mb_substr($txt, 0, 60);
                if ($ids) { $c['topup_btns'][$k]['icon'] = $ids[0]; $c['topup_btns'][$k]['emoji'] = ''; }
            });
        } elseif ($action === 'tue_be') {
            $em = $blank ? '' : mb_substr($plain, 0, 8);
            cfgSet(function (&$c) use ($k, $em) { $c['topup_btns'][$k]['emoji'] = $em; });
        } else {
            $id = $blank ? '' : ($ids[0] ?? (preg_match('/^\d{5,25}$/', $plain) ? $plain : null));
            if ($id === null) { sendMsg(BOT_TOKEN, $chatId, '⚠️ ایموجیِ پریمیوم پیدا نشد؛ خودِ ایموجی را بفرستید.'); return true; }
            cfgSet(function (&$c) use ($k, $id) { $c['topup_btns'][$k]['icon'] = $id; });
        }
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد. پیش‌نمایش 👇", inlineKb([[tuBtn($k, 'cb', 'trnop')], [btnCb('◀️ برگشت', 'payx_b_' . $k, 'nav')]]));
        return true;
    }
    clearState($uid);
    return true;
}

// ───────── کیوآر کد (بدونِ کتابخانه و بدونِ سرویسِ بیرونی) — حالتِ بایت، سطحِ خطای M، نسخه‌ی ۱ تا ۱۰ ─────────

function qrMatrix($text) {
    $data = array_values(unpack('C*', (string)$text) ?: []);
    // [کلِ کلمه‌ها, تصحیحِ هر بلوک, [[تعدادِ بلوک, دیتای هر بلوک], …]]
    $T = [1 => [26, 10, [[1, 16]]], 2 => [44, 16, [[1, 28]]], 3 => [70, 26, [[1, 44]]], 4 => [100, 18, [[2, 32]]],
          5 => [134, 24, [[2, 43]]], 6 => [172, 16, [[4, 27]]], 7 => [196, 18, [[4, 31]]],
          8 => [242, 22, [[2, 38], [2, 39]]], 9 => [292, 22, [[3, 36], [2, 37]]], 10 => [346, 26, [[4, 43], [1, 44]]]];
    $AL = [2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38],
           8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];
    $n = count($data);
    $ver = 0;
    foreach ($T as $v => $t) {
        $cap = 0; foreach ($t[2] as [$bn, $bd]) $cap += $bn * $bd;
        $bits = 4 + ($v < 10 ? 8 : 16) + 8 * $n;
        if ($bits <= $cap * 8) { $ver = $v; break; }
    }
    if (!$ver) return null;
    [$total, $ecn, $groups] = $T[$ver];
    $cap = 0; foreach ($groups as [$bn, $bd]) $cap += $bn * $bd;

    // رشته‌ی بیت‌ها
    $bs = [];
    $push = function ($val, $len) use (&$bs) { for ($i = $len - 1; $i >= 0; $i--) $bs[] = ($val >> $i) & 1; };
    $push(4, 4);
    $push($n, $ver < 10 ? 8 : 16);
    foreach ($data as $b) $push($b, 8);
    $push(0, min(4, $cap * 8 - count($bs)));
    while (count($bs) % 8) $bs[] = 0;
    $cw = [];
    for ($i = 0; $i < count($bs); $i += 8) { $x = 0; for ($j = 0; $j < 8; $j++) $x = ($x << 1) | $bs[$i + $j]; $cw[] = $x; }
    for ($p = 0; count($cw) < $cap; $p ^= 1) $cw[] = $p ? 0x11 : 0xEC;

    // رید-سولومون روی GF(256)
    $exp = array_fill(0, 512, 0); $log = array_fill(0, 256, 0);
    for ($i = 0, $x = 1; $i < 255; $i++) { $exp[$i] = $x; $log[$x] = $i; $x <<= 1; if ($x & 0x100) $x ^= 0x11D; }
    for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
    $mul = function ($a, $b) use ($exp, $log) { return ($a && $b) ? $exp[$log[$a] + $log[$b]] : 0; };
    $gen = [1];
    for ($i = 0; $i < $ecn; $i++) {
        $ng = array_fill(0, count($gen) + 1, 0);
        foreach ($gen as $j => $g) { $ng[$j] ^= $g; $ng[$j + 1] ^= $mul($g, $exp[$i]); }
        $gen = $ng;
    }
    $blocks = []; $ecb = []; $off = 0;
    foreach ($groups as [$bn, $bd]) for ($k = 0; $k < $bn; $k++) {
        $blk = array_slice($cw, $off, $bd); $off += $bd;
        $rem = array_merge($blk, array_fill(0, $ecn, 0));
        for ($i = 0; $i < $bd; $i++) {
            $c = $rem[$i];
            if ($c) for ($j = 1; $j <= $ecn; $j++) $rem[$i + $j] ^= $mul($gen[$j], $c);
        }
        $blocks[] = $blk; $ecb[] = array_slice($rem, $bd);
    }
    $final = [];
    $maxd = 0; foreach ($blocks as $b) $maxd = max($maxd, count($b));
    for ($i = 0; $i < $maxd; $i++) foreach ($blocks as $b) if (isset($b[$i])) $final[] = $b[$i];
    for ($i = 0; $i < $ecn; $i++) foreach ($ecb as $b) $final[] = $b[$i];

    // چیدنِ الگوهای ثابت
    $size = 17 + 4 * $ver;
    $m = array_fill(0, $size, array_fill(0, $size, null));
    $fn = array_fill(0, $size, array_fill(0, $size, false));
    $set = function ($r, $c, $v) use (&$m, &$fn) { $m[$r][$c] = $v ? 1 : 0; $fn[$r][$c] = true; };
    foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$r0, $c0])
        for ($r = -1; $r <= 7; $r++) for ($c = -1; $c <= 7; $c++) {
            $rr = $r0 + $r; $cc = $c0 + $c;
            if ($rr < 0 || $cc < 0 || $rr >= $size || $cc >= $size) continue;
            $in = $r >= 0 && $r <= 6 && $c >= 0 && $c <= 6;
            $on = $in && ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4));
            $set($rr, $cc, $on);
        }
    for ($i = 8; $i < $size - 8; $i++) { $set(6, $i, $i % 2 === 0); $set($i, 6, $i % 2 === 0); }
    if (isset($AL[$ver])) {
        $ps = $AL[$ver]; $last = count($ps) - 1;
        foreach ($ps as $a => $r) foreach ($ps as $b => $c) {
            if (($a === 0 && $b === 0) || ($a === 0 && $b === $last) || ($a === $last && $b === 0)) continue;
            for ($dr = -2; $dr <= 2; $dr++) for ($dc = -2; $dc <= 2; $dc++)
                $set($r + $dr, $c + $dc, max(abs($dr), abs($dc)) !== 1);
        }
    }
    $set($size - 8, 8, true);
    // جای اطلاعاتِ قالب و نسخه رزرو می‌شود
    for ($i = 0; $i < 9; $i++) { if (!$fn[8][$i]) $set(8, $i, false); if (!$fn[$i][8]) $set($i, 8, false); }
    for ($i = 0; $i < 8; $i++) { $set(8, $size - 1 - $i, false); $set($size - 1 - $i, 8, false); }
    if ($ver >= 7) {
        $v = $ver; $rem = $v;
        for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        $vb = ($v << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $bit = ($vb >> $i) & 1; $a = $size - 11 + $i % 3; $b = intdiv($i, 3);
            $set($a, $b, $bit); $set($b, $a, $bit);
        }
    }

    // جای دیتا (زیگزاگ)
    $bits = [];
    foreach ($final as $b) for ($i = 7; $i >= 0; $i--) $bits[] = ($b >> $i) & 1;
    $k = 0; $nb = count($bits);
    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) $right = 5;
        for ($vert = 0; $vert < $size; $vert++) for ($j = 0; $j < 2; $j++) {
            $c = $right - $j;
            $up = (($right + 1) & 2) === 0;
            $r = $up ? $size - 1 - $vert : $vert;
            if ($fn[$r][$c]) continue;
            $m[$r][$c] = $k < $nb ? $bits[$k] : 0; $k++;
        }
    }

    $masks = [
        fn($r, $c) => ($r + $c) % 2 === 0,
        fn($r, $c) => $r % 2 === 0,
        fn($r, $c) => $c % 3 === 0,
        fn($r, $c) => ($r + $c) % 3 === 0,
        fn($r, $c) => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0,
        fn($r, $c) => ($r * $c) % 2 + ($r * $c) % 3 === 0,
        fn($r, $c) => (($r * $c) % 2 + ($r * $c) % 3) % 2 === 0,
        fn($r, $c) => (($r + $c) % 2 + ($r * $c) % 3) % 2 === 0,
    ];
    $best = null; $bestP = PHP_INT_MAX;
    foreach ($masks as $mi => $mf) {
        $q = $m;
        for ($r = 0; $r < $size; $r++) for ($c = 0; $c < $size; $c++) if (!$fn[$r][$c] && $mf($r, $c)) $q[$r][$c] ^= 1;
        // اطلاعاتِ قالب: سطحِ M = 00
        $fmt = (0 << 3) | $mi; $rem = $fmt;
        for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        $fb = (($fmt << 10) | $rem) ^ 0x5412;
        for ($i = 0; $i <= 5; $i++) $q[$i][8] = ($fb >> $i) & 1;
        $q[7][8] = ($fb >> 6) & 1; $q[8][8] = ($fb >> 7) & 1; $q[8][7] = ($fb >> 8) & 1;
        for ($i = 9; $i < 15; $i++) $q[8][14 - $i] = ($fb >> $i) & 1;
        for ($i = 0; $i < 8; $i++) $q[8][$size - 1 - $i] = ($fb >> $i) & 1;
        for ($i = 8; $i < 15; $i++) $q[$size - 15 + $i][8] = ($fb >> $i) & 1;
        $q[$size - 8][8] = 1;
        $p = qrPenalty($q, $size);
        if ($p < $bestP) { $bestP = $p; $best = $q; }
    }
    return $best;
}

function qrPenalty($q, $n) {
    $p = 0; $dark = 0;
    for ($r = 0; $r < $n; $r++) {
        foreach ([0, 1] as $axis) {
            $run = 1;
            for ($i = 1; $i < $n; $i++) {
                $a = $axis ? $q[$i][$r] : $q[$r][$i]; $b = $axis ? $q[$i - 1][$r] : $q[$r][$i - 1];
                if ($a === $b) { $run++; if ($i === $n - 1 && $run >= 5) $p += $run - 2; }
                else { if ($run >= 5) $p += $run - 2; $run = 1; }
            }
        }
        for ($c = 0; $c < $n; $c++) {
            $dark += $q[$r][$c];
            if ($r < $n - 1 && $c < $n - 1) {
                $s = $q[$r][$c] + $q[$r + 1][$c] + $q[$r][$c + 1] + $q[$r + 1][$c + 1];
                if ($s === 0 || $s === 4) $p += 3;
            }
        }
    }
    $pat = [1, 0, 1, 1, 1, 0, 1];
    for ($r = 0; $r < $n; $r++) for ($c = 0; $c + 7 <= $n; $c++) {
        $h = true; $v = true;
        for ($k = 0; $k < 7; $k++) { if ($q[$r][$c + $k] !== $pat[$k]) $h = false; if ($q[$c + $k][$r] !== $pat[$k]) $v = false; }
        if ($h) $p += 40;
        if ($v) $p += 40;
    }
    $p += intdiv(abs($dark * 20 - $n * $n * 10), $n * $n) * 10;
    return $p;
}

function qrSvg($text, $fg = '#0B1020', $bg = '#FFFFFF') {
    $q = qrMatrix($text);
    if (!$q) return '';
    $n = count($q); $pad = 3; $w = $n + 2 * $pad;
    $d = '';
    for ($r = 0; $r < $n; $r++) for ($c = 0; $c < $n; $c++)
        if ($q[$r][$c]) $d .= 'M' . ($c + $pad) . ' ' . ($r + $pad) . 'h1v1h-1z';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $w . '" shape-rendering="crispEdges">' .
           '<rect width="' . $w . '" height="' . $w . '" fill="' . $bg . '"/><path d="' . $d . '" fill="' . $fg . '"/></svg>';
}

// ───────── صفحه‌ی درگاه: HTML ─────────

function payPageHtml(array $boot) {
    $font = function_exists('maFontCss') ? maFontCss() : '';
    $tpl = <<<'HTML'
<!doctype html>
<html lang="fa" dir="rtl" class="th-__TH__">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="referrer" content="no-referrer">
<title>درگاه پرداخت</title>
<script defer src="https://telegram.org/js/telegram-web-app.js"></script>
<style>
__FONT__
:root{--bg:#03060b;--glass:rgba(14,20,32,.56);--line:rgba(147,197,253,.18);--ink:#F4F6FA;--dim:#8F99AB;--dim2:#5F6878;
  --acc:#60A5FA;--acc2:#4ADE80;--ok:#4ADE80;--red:#F87171;--gold:#FCD34D;
  --grad:linear-gradient(135deg,#3B82F6 0%,#22C55E 100%);--glow:rgba(59,130,246,.9);
  --o1:rgba(37,99,235,.34);--o2:rgba(34,197,94,.2);--o3:rgba(59,130,246,.12);
  --safe:calc(env(safe-area-inset-bottom,0px) / .9);color-scheme:dark}
.th-tgs{--bg:#07051A;--glass:rgba(28,20,70,.52);--line:rgba(196,181,253,.2);--dim:#A7A1CC;--dim2:#6F6A98;--acc:#A78BFA;--acc2:#22D3EE;
  --grad:linear-gradient(120deg,#7C3AED 0%,#6366F1 52%,#0EA5E9 100%);--glow:rgba(124,58,237,.95);--o1:rgba(124,58,237,.36);--o2:rgba(34,211,238,.18);--o3:rgba(232,121,249,.12)}
.th-igs{--bg:#0A0510;--glass:rgba(38,14,44,.52);--line:rgba(255,190,222,.2);--dim:#C0A6BF;--dim2:#846C86;--acc:#FF5FA2;--acc2:#FEDA75;
  --grad:linear-gradient(45deg,#F58529 0%,#DD2A7B 45%,#8134AF 78%,#515BD4 100%);--glow:rgba(221,42,123,.95);--o1:rgba(221,42,123,.34);--o2:rgba(245,133,41,.2);--o3:rgba(129,52,175,.2)}
html{zoom:.9}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{background:var(--bg);color:var(--ink);min-height:100%}
body{font-family:Vazirmatn,Vazir,Tahoma,system-ui,sans-serif;font-size:13px;line-height:1.7;-webkit-font-smoothing:antialiased;overflow-x:hidden;-webkit-user-select:none;user-select:none}
button{font-family:inherit;color:inherit;background:none;border:0;cursor:pointer}
input{font-family:inherit}
svg{display:block}
.hid{display:none!important}
.ltr{direction:ltr;unicode-bidi:isolate}
@keyframes up{from{opacity:0;transform:translate3d(0,14px,0)}to{opacity:1;transform:none}}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes ping{0%{transform:scale(1);opacity:.7}80%,100%{transform:scale(2.6);opacity:0}}
@keyframes shine{0%,70%{transform:translate3d(-130%,0,0) skewX(-20deg)}100%{transform:translate3d(360%,0,0) skewX(-20deg)}}
@keyframes pop{0%{transform:scale(.4);opacity:0}60%{transform:scale(1.08);opacity:1}100%{transform:scale(1)}}
.bgx{position:fixed;inset:0;z-index:0;pointer-events:none;
  background:radial-gradient(70vw 50vh at 100% -5%,var(--o1),transparent 65%),radial-gradient(70vw 45vh at -10% 105%,var(--o2),transparent 65%),
    radial-gradient(60vw 40vh at 50% 45%,var(--o3),transparent 70%),var(--bg)}
.wrap{position:relative;z-index:1;max-width:480px;margin:0 auto;padding:calc(var(--top,0px) + 12px) 16px calc(28px + var(--safe))}
html.fs .wrap{--top:calc((var(--tg-content-safe-area-inset-top,var(--tg-safe-area-inset-top,34px)) + 46px) / .9)}
.top{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.top .bk{width:40px;height:40px;border-radius:14px;display:grid;place-items:center;border:1px solid var(--line);background:rgba(255,255,255,.05)}
.top .bk svg{width:18px;height:18px}
.top b{flex:1;font-size:16px;font-weight:900}
.lock{display:inline-flex;align-items:center;gap:5px;height:28px;padding:0 10px;border-radius:14px;font-size:10.5px;font-weight:800;color:var(--ok);
  background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.28)}
.lock svg{width:13px;height:13px}
.v{animation:up .45s cubic-bezier(.2,.85,.25,1) both}
.gl{position:relative;border-radius:24px;padding:16px;border:1px solid var(--line);
  background:linear-gradient(180deg,rgba(255,255,255,.09),rgba(255,255,255,.02) 40%,rgba(255,255,255,.01)),var(--glass);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.14),0 24px 40px -30px #000}
.gl+.gl{margin-top:12px}
.mts{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px}
.mt{position:relative;overflow:hidden;display:flex;flex-direction:column;align-items:flex-start;gap:4px;text-align:right;padding:14px 13px;border-radius:20px;
  border:1px solid var(--line);background:linear-gradient(160deg,rgba(255,255,255,.08),rgba(255,255,255,.015)),var(--glass);transition:transform .15s,border-color .2s,box-shadow .2s}
.mt .gi{width:44px;height:44px;margin-bottom:4px}
.gi{display:inline-grid;width:42px;height:42px;filter:drop-shadow(0 8px 14px rgba(0,0,0,.45))}.gi svg{width:100%;height:100%}

.mt b{font-size:13.5px;font-weight:900}
.mt small{font-size:10px;color:var(--dim);font-weight:700}
.mt.on{border-color:transparent;box-shadow:0 0 0 1.5px var(--acc),0 16px 30px -18px var(--glow);background:linear-gradient(160deg,rgba(255,255,255,.14),rgba(255,255,255,.03)),var(--glass)}
.mt.on:after{content:"✓";position:absolute;top:10px;left:10px;width:20px;height:20px;border-radius:50%;display:grid;place-items:center;font-size:11px;font-weight:900;color:#fff;background:var(--grad)}
.mt:active{transform:scale(.97)}
.mt[disabled]{opacity:.4}
.lb{display:block;font-size:11.5px;font-weight:800;color:var(--dim);margin-bottom:8px}
.amt{display:flex;align-items:center;gap:8px;height:60px;padding:0 16px;border-radius:18px;border:1px solid var(--line);
  background:linear-gradient(180deg,rgba(255,255,255,.08),rgba(255,255,255,.02));box-shadow:inset 0 1px 0 rgba(255,255,255,.1)}
.amt:focus-within{border-color:var(--acc);box-shadow:0 0 0 3px rgba(255,255,255,.06),inset 0 1px 0 rgba(255,255,255,.12)}
.amt input{flex:1;min-width:0;height:100%;background:none;border:0;outline:0;color:var(--ink);font-size:22px;font-weight:900;text-align:center;-webkit-user-select:text;user-select:text}
.amt input::placeholder{color:var(--dim2);font-size:15px;font-weight:700}
.amt span{font-size:12px;font-weight:800;color:var(--dim)}
.qc{display:grid;grid-template-columns:repeat(4,1fr);gap:7px;margin-top:10px}
.qc button{height:36px;border-radius:12px;font-size:11.5px;font-weight:900;color:var(--dim);border:1px solid var(--line);background:rgba(255,255,255,.04)}
.qc button.on{color:#fff;border-color:transparent;background:var(--grad)}
.hint{margin-top:10px;font-size:11px;color:var(--dim);line-height:1.9}
.hint b{color:var(--ink)}
.prev{display:flex;align-items:center;justify-content:space-between;margin-top:12px;padding:11px 13px;border-radius:15px;background:rgba(255,255,255,.04);border:1px dashed var(--line);font-size:12px}
.prev b{font-size:15px;font-weight:900}
.cta{position:relative;overflow:hidden;display:flex;align-items:center;justify-content:center;gap:8px;width:100%;height:56px;margin-top:14px;border-radius:19px;
  background:var(--grad);color:#fff;font-size:15px;font-weight:900;box-shadow:0 18px 34px -16px var(--glow),inset 0 1px 0 rgba(255,255,255,.4)}
.cta:after{content:"";position:absolute;top:0;bottom:0;left:0;width:30%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.45),transparent);animation:shine 4s ease-in-out infinite}
.cta svg{width:19px;height:19px}
.cta[disabled]{opacity:.5}
.cta[disabled]:after{display:none}
.gh{display:flex;align-items:center;justify-content:center;gap:7px;width:100%;height:48px;margin-top:10px;border-radius:16px;font-size:13px;font-weight:900;
  border:1px solid var(--line);background:rgba(255,255,255,.05)}
.gh svg{width:16px;height:16px}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.row2 .gh{margin-top:10px}
.hd{display:flex;align-items:center;justify-content:space-between;gap:10px}
.hd .am small{display:block;font-size:11px;color:var(--dim);font-weight:700}
.hd .am b{font-size:24px;font-weight:900}
.chip{display:inline-flex;align-items:center;gap:6px;height:28px;padding:0 11px;border-radius:14px;font-size:11px;font-weight:900;white-space:nowrap}
.chip.w{color:var(--gold);background:rgba(252,211,77,.1);border:1px solid rgba(252,211,77,.3)}
.chip.w i{width:7px;height:7px;border-radius:50%;background:currentColor;position:relative}
.chip.w i:after{content:"";position:absolute;inset:0;border-radius:50%;background:inherit;animation:ping 1.6s ease-out infinite}
.chip.p{color:var(--ok);background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.3)}
.chip.x{color:var(--red);background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3)}
.ring{position:relative;width:78px;height:78px;flex:0 0 auto}
.ring svg{width:78px;height:78px;transform:rotate(-90deg)}
.ring circle{fill:none;stroke-width:6}
.ring .t{stroke:rgba(255,255,255,.08)}
.ring .p{stroke:var(--acc);stroke-linecap:round;transition:stroke-dashoffset 1s linear}
.ring b{position:absolute;inset:0;display:grid;place-items:center;font-size:13px;font-weight:900;direction:ltr}
.pay{display:flex;align-items:center;gap:12px;margin-top:14px;padding:13px;border-radius:18px;background:rgba(255,255,255,.05);border:1px solid var(--line)}
.pay .x{flex:1;min-width:0}
.pay small{display:block;font-size:10.5px;color:var(--dim);font-weight:700}
.pay b{display:block;font-size:20px;font-weight:900;direction:ltr;text-align:right;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cp{flex:0 0 auto;height:38px;padding:0 13px;border-radius:13px;display:inline-flex;align-items:center;gap:6px;font-size:11.5px;font-weight:900;color:#fff;background:var(--grad);
  box-shadow:0 10px 20px -12px var(--glow)}
.cp svg{width:14px;height:14px}
.net{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}
.net span{height:26px;padding:0 10px;border-radius:13px;display:inline-flex;align-items:center;font-size:10.5px;font-weight:900;border:1px solid var(--line);background:rgba(255,255,255,.04)}
.qr{width:212px;height:212px;margin:16px auto 4px;padding:10px;border-radius:22px;background:#fff;box-shadow:0 18px 40px -22px var(--glow)}
.qr svg{width:100%;height:100%}
.addr{margin-top:12px;padding:12px 13px;border-radius:16px;background:rgba(0,0,0,.25);border:1px solid var(--line);
  font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;font-weight:700;direction:ltr;text-align:center;word-break:break-all;line-height:1.7;-webkit-user-select:text;user-select:text}
.warn{display:flex;gap:9px;align-items:flex-start;margin-top:12px;padding:11px 12px;border-radius:15px;font-size:11.5px;line-height:1.9;color:#FDE68A;
  background:rgba(252,211,77,.07);border:1px solid rgba(252,211,77,.22)}
.warn svg{width:17px;height:17px;flex:0 0 auto;margin-top:2px}
.live{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:14px;font-size:11.5px;color:var(--dim);font-weight:800}
.live i{width:14px;height:14px;border-radius:50%;border:2px solid rgba(255,255,255,.15);border-top-color:var(--acc);animation:spin .9s linear infinite}
.kv{margin-top:12px}
.kv div{display:flex;justify-content:space-between;align-items:center;padding:9px 0;font-size:12px;color:var(--dim)}
.kv div+div{border-top:1px dashed var(--line)}
.kv b{color:var(--ink);font-weight:900}
.big{text-align:center;padding:10px 0 4px}
.big .ic{width:92px;height:92px;margin:6px auto 14px;border-radius:50%;display:grid;place-items:center;background:var(--grad);box-shadow:0 20px 40px -16px var(--glow);animation:pop .6s cubic-bezier(.2,.85,.25,1) both}
.big .ic svg{width:44px;height:44px;color:#fff}
.big .ic.w{background:linear-gradient(145deg,#FCD34D,#F59E0B)}
.big h2{font-size:19px;font-weight:900}
.big p{margin-top:6px;font-size:12.5px;color:var(--dim);line-height:1.9}
.big b.a{display:block;margin-top:10px;font-size:28px;font-weight:900}
.toast{position:fixed;left:16px;right:16px;bottom:calc(20px + var(--safe));z-index:60;max-width:448px;margin:0 auto;padding:12px 14px;border-radius:16px;
  background:rgba(20,24,34,.96);border:1px solid var(--line);font-size:12px;font-weight:800;transform:translate3d(0,150%,0);visibility:hidden;
  transition:transform .3s cubic-bezier(.2,.85,.25,1),visibility 0s linear .3s}
.toast.on{transform:none;visibility:visible;transition:transform .3s cubic-bezier(.2,.85,.25,1)}
.toast.er{border-color:rgba(248,113,113,.4)}
.toast.ok{border-color:rgba(74,222,128,.4)}
@media (prefers-reduced-motion:reduce){*,*:before,*:after{animation:none!important;transition:none!important}}
</style>
</head>
<body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
<symbol id="i-back" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 5 7 7-7 7"/></symbol>
<symbol id="i-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></symbol>
<symbol id="i-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="8" y="8" width="12.5" height="12.5" rx="2.5"/><path d="M16 8V6a2.5 2.5 0 0 0-2.5-2.5H6A2.5 2.5 0 0 0 3.5 6v7.5A2.5 2.5 0 0 0 6 16h2"/></symbol>
<symbol id="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></symbol>
<symbol id="i-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></symbol>
<symbol id="i-refresh" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 0 0-14.6-4.5L3 9M3 4v5h5M4 13a8 8 0 0 0 14.6 4.5L21 15M21 20v-5h-5"/></symbol>
<symbol id="i-alert" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9.5"/><path d="M12 7.5v5.5M12 16.5v.3"/></symbol>
<symbol id="i-card" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="2.5" y="5" width="19" height="14" rx="3"/><path d="M2.5 10h19M6 15h4"/></symbol>
<symbol id="i-phone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="6.5" y="2.5" width="11" height="19" rx="2.5"/><path d="M10.5 18.5h3"/></symbol>
<symbol id="i-id" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="2.5" y="4.5" width="19" height="15" rx="3"/><circle cx="8.5" cy="11" r="2.4"/><path d="M5 16c.6-1.6 2-2.4 3.5-2.4S11.4 14.4 12 16M14.5 10h4.5M14.5 13.5h3"/></symbol>
<symbol id="i-ext" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6M20 4l-9 9M18 14v4.5A1.5 1.5 0 0 1 16.5 20h-11A1.5 1.5 0 0 1 4 18.5v-11A1.5 1.5 0 0 1 5.5 6H10"/></symbol>
</defs></svg>
<div class="bgx" aria-hidden="true"></div>
<main class="wrap">
  <header class="top"><button class="bk" id="bk" aria-label="بازگشت"><svg><use href="#i-back"/></svg></button><b data-t="title">درگاه پرداخت</b><span class="lock"><svg><use href="#i-lock"/></svg><span data-t="lock">پرداختِ امن</span></span></header>

  <section class="v hid" id="v-new">
    <div class="mts" id="mts"></div>
    <div class="gl">
      <label class="lb" for="amt" data-t="amt">مبلغِ شارژ</label>
      <div class="amt"><input id="amt" inputmode="numeric" autocomplete="off" placeholder="مثلا ۲۰۰٬۰۰۰"><span>تومان</span></div>
      <div class="qc" id="qc"></div>
      <div class="prev hid" id="prev"><span>معادل تقریبی</span><b class="ltr" id="prevV">—</b></div>
      <div class="hint" id="hint"></div>
    </div>
    <button class="cta" id="go"><svg><use href="#i-card"/></svg><span id="goT" data-t="go">ادامه و پرداخت</span></button>
  </section>

  <section class="v hid" id="v-c">
    <div class="gl">
      <div class="hd"><div class="am"><small data-t="amt">مبلغِ شارژ</small><b><span id="cA">—</span> <small style="display:inline">تومان</small></b><div style="margin-top:6px"><span class="chip w" id="cS"><i></i><span data-t="wait">در انتظارِ واریز</span></span></div></div>
        <div class="ring"><svg viewBox="0 0 78 78"><circle class="t" cx="39" cy="39" r="33"/><circle class="p" id="cR" cx="39" cy="39" r="33" stroke-dasharray="207.3" stroke-dashoffset="0"/></svg><b id="cT">--:--</b></div></div>
      <div class="pay" id="cPay"><div class="x"><small data-t="exact">مقدارِ دقیقِ واریز</small><b id="cV">—</b></div><button class="cp" id="cpV"><svg><use href="#i-copy"/></svg>کپی</button></div>
      <div class="net" id="cN"></div>
      <div class="qr hid" id="cQ"></div>
      <div class="addr hid" id="cAd"></div>
      <button class="cta hid" id="cpA"><svg><use href="#i-copy"/></svg><span data-t="copyaddr">کپیِ آدرسِ ولت</span></button>
      <button class="cta hid" id="cU"><svg><use href="#i-ext"/></svg><span data-t="openpg">باز کردنِ صفحه‌ی پرداخت</span></button>
      <div class="warn" id="cW"><svg><use href="#i-alert"/></svg><span id="cWt"></span></div>
      <div class="live" id="cL"><i></i><span data-t="live">بعد از واریز، خودکار شارژ می‌شود — لازم نیست کاری کنید</span></div>
    </div>
    <div class="row2"><button class="gh" id="cChk"><svg><use href="#i-refresh"/></svg><span data-t="check">بررسی پرداخت</span></button><button class="gh" id="cX"><svg><use href="#i-x"/></svg><span data-t="cancel">انصراف</span></button></div>
  </section>

  <section class="v hid" id="v-i">
    <div class="gl">
      <div class="big" style="padding-bottom:0"><div class="ic"><svg><use href="#i-card"/></svg></div><h2 data-t="ir_title">تاییدِ پرداخت</h2><b class="a"><span id="iA">—</span> <small style="font-size:13px">تومان</small></b></div>
      <div class="kv"><div><span>شماره‌ی موبایل</span><b class="ltr" id="iP">—</b></div><div><span>کدِ پیگیری</span><b class="ltr" id="iId">—</b></div><div><span>وضعیت</span><b id="iS">در انتظارِ پرداخت</b></div></div>
      <button class="cta" id="iGo"><svg><use href="#i-card"/></svg><span data-t="ir_pay">پرداختِ آنلاین</span></button>
      <div class="hint" style="text-align:center" data-t="ir_hint">درگاه در مرورگر باز می‌شود؛ بعد از پرداخت به همین‌جا برگردید — خودکار شارژ می‌شود.</div>
    </div>
    <div class="row2"><button class="gh" id="iChk"><svg><use href="#i-refresh"/></svg><span data-t="check">بررسی پرداخت</span></button><button class="gh" id="iX"><svg><use href="#i-x"/></svg><span data-t="cancel">انصراف</span></button></div>
  </section>

  <section class="v hid" id="v-ph">
    <div class="gl"><div class="big"><div class="ic"><svg><use href="#i-phone"/></svg></div><h2 data-t="ph_title">شماره‌ی موبایل</h2>
      <p data-t="ph_text">برای پرداخت با درگاهِ ایرانی، شماره‌ی موبایلِ خودتان را یک‌بار بفرستید.<br>شماره باید به نامِ صاحبِ کارت باشد.</p></div>
      <button class="cta" id="phGo"><svg><use href="#i-phone"/></svg><span data-t="ph_go">ارسالِ شماره‌ی من</span></button>
      <button class="gh" id="phBot">ارسال از داخلِ ربات</button></div>
  </section>

  <section class="v hid" id="v-k">
    <div class="gl"><div class="big"><div class="ic w"><svg><use href="#i-id"/></svg></div><h2>احراز هویت لازم است</h2><p id="kT"></p></div>
      <div class="hid" id="kOtp" style="margin-top:6px">
        <label class="lb" for="kCode" id="kOtpL">کدِ تاییدی که پیامک شد</label>
        <div class="amt"><input id="kCode" inputmode="numeric" autocomplete="one-time-code" placeholder="کدِ تایید" maxlength="10"></div>
        <button class="cta" id="kChk"><svg><use href="#i-check"/></svg>تاییدِ کد</button>
        <button class="gh" id="kRe">ارسالِ دوباره‌ی کد</button>
      </div>
      <button class="cta" id="kGo"><svg><use href="#i-id"/></svg><span id="kGoT">احراز هویت</span></button>
      <button class="gh" id="kLess">وارد کردنِ مبلغِ کمتر</button></div>
  </section>

  <section class="v hid" id="v-ok">
    <div class="gl"><div class="big"><div class="ic"><svg><use href="#i-check"/></svg></div><h2 data-t="ok_title">حسابتان شارژ شد</h2><b class="a"><span id="okA">—</span> <small style="font-size:13px">تومان</small></b>
      <p id="okB"></p></div>
      <button class="cta" id="okGo" data-t="ok_back">بازگشت</button></div>
  </section>

  <section class="v hid" id="v-er">
    <div class="gl"><div class="big"><div class="ic w"><svg><use href="#i-alert"/></svg></div><h2 id="erH">این فاکتور منقضی شد</h2><p id="erT" data-t="exp_text">دوباره مبلغ را وارد کنید تا فاکتورِ تازه ساخته شود.</p></div>
      <button class="cta" id="erGo" data-t="again">شارژِ دوباره</button></div>
  </section>
</main>
<div class="toast" id="toast"></div>
<script>
(function(){
"use strict";
var B = __BOOT__;
(function(){ try { if (/^https?:$/.test(location.protocol)) { var o = location.origin + location.pathname; B.api = o + '?mapi=1'; B.st = o + '?paystat=1'; } } catch(e){} })();
var TG = (window.Telegram && window.Telegram.WebApp) ? window.Telegram.WebApp : null;
var D = document, H = D.documentElement, $ = function(id){ return D.getElementById(id); };
var HASH = (function(){ try { var m = /(?:^|&)tgWebAppData=([^&]*)/.exec(String(location.hash || '').replace(/^#/, '')); return m ? decodeURIComponent(m[1]) : ''; } catch(e){ return ''; } })();
function initData(){ try { if (TG && TG.initData) return TG.initData; } catch(e){} return HASH; }
function esc(s){ return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
function faD(s){ return String(s == null ? '' : s).replace(/\d/g, function(d){ return String.fromCharCode(1776 + +d); }); }
var NF = null; try { NF = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 0 }); } catch(e){}
function fa(n){ n = Number(n) || 0; return NF ? NF.format(n) : faD(Math.round(n)); }
function digits(s){ s = String(s == null ? '' : s); var o = ''; for (var i = 0; i < s.length; i++) { var c = s.charCodeAt(i);
  if (c >= 1776 && c <= 1785) o += (c - 1776); else if (c >= 1632 && c <= 1641) o += (c - 1632); else if (c >= 48 && c <= 57) o += s[i]; } return o; }
function tap(k){ try { TG && TG.HapticFeedback && TG.HapticFeedback.impactOccurred(k || 'light'); } catch(e){} }
function buzz(k){ try { TG && TG.HapticFeedback && TG.HapticFeedback.notificationOccurred(k); } catch(e){} }
var TT;
function toast(m, ok){ var t = $('toast'); t.className = 'toast ' + (ok ? 'ok' : 'er'); t.textContent = m; void t.offsetWidth; t.classList.add('on');
  clearTimeout(TT); TT = setTimeout(function(){ t.classList.remove('on'); }, 3400); buzz(ok ? 'success' : 'error'); }
function copy(txt, what){
  function done(){ toast((what || 'متن') + ' کپی شد.', true); }
  function fb(){ try { var t = D.createElement('textarea'); t.value = txt; t.setAttribute('readonly', ''); t.style.position = 'fixed'; t.style.opacity = '0';
    D.body.appendChild(t); t.select(); D.execCommand('copy'); D.body.removeChild(t); done(); } catch(e){ toast('کپی نشد — دستی کپی کنید.'); } }
  try { if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(txt).then(done, fb); return; } } catch(e){}
  fb();
}
function tgHash(){ var d = initData(); return d ? '#tgWebAppData=' + encodeURIComponent(d) + '&tgWebAppVersion=' + encodeURIComponent((TG && TG.version) || '7.0') +
  '&tgWebAppPlatform=' + encodeURIComponent((TG && TG.platform) || 'unknown') : ''; }
function openBot(arg){ if (!B.bot) return; var u = 'https://t.me/' + B.bot + (arg ? '?start=' + arg : '');
  try { if (TG && TG.openTelegramLink) { TG.openTelegramLink(u); return; } } catch(e){} location.href = u; }
function openExt(u){ try { if (TG && TG.openLink) { TG.openLink(u); return; } } catch(e){} window.open(u, '_blank', 'noopener'); }
function goBack(){
  if (B.back) { location.href = B.back + tgHash(); return; }
  try { if (TG && TG.close && initData()) { TG.close(); return; } } catch(e){}
  history.back();
}
function api(action, extra, ok, bad){
  var body = { action: action, initData: initData() }; for (var k in (extra || {})) body[k] = extra[k];
  fetch(B.api, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), cache: 'no-store', credentials: 'omit' })
    .then(function(r){ return r.json().catch(function(){ return { ok: false, message: 'پاسخ سرور نامعتبر بود.' }; }); })
    .then(function(j){ if (j && j.ok) ok(j); else (bad || function(x){ toast((x && x.message) || 'خطا — دوباره امتحان کنید.'); })(j || {}); })
    .catch(function(){ (bad || function(){ toast('ارتباط با سرور برقرار نشد.'); })({ message: 'ارتباط با سرور برقرار نشد.' }); });
}
var VIEWS = ['v-new', 'v-c', 'v-i', 'v-ph', 'v-k', 'v-ok', 'v-er'];
function show(id){ VIEWS.forEach(function(v){ $(v).classList.toggle('hid', v !== id); }); window.scrollTo(0, 0); }

var S = { m: '', o: null, poll: 0, tick: 0, info: B.info || {}, pend: null };
// متن‌هایی که ادمین از پنلِ ربات عوض کرده (متنِ ساده؛ ایمن با esc)
var PT = (B.pt && typeof B.pt === 'object') ? B.pt : {};
function pt(k, d){ var v = PT[k]; return (typeof v === 'string' && v.trim()) ? v : d; }
[].forEach.call(document.querySelectorAll('[data-t]'), function(el){
  var v = PT[el.getAttribute('data-t')];
  if (typeof v === 'string' && v.trim()) el.innerHTML = esc(v).replace(/\n/g, '<br>');
});
if (PT.title) try { document.title = PT.title; } catch(e){}
var GI = {
  crypto: '<svg viewBox="0 0 48 48" aria-hidden="true"><defs><linearGradient id="giC" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#34D399"/><stop offset=".55" stop-color="#10B981"/><stop offset="1" stop-color="#047857"/></linearGradient></defs>' +
    '<circle cx="24" cy="24" r="22" fill="url(#giC)"/><circle cx="24" cy="24" r="18.5" fill="none" stroke="rgba(255,255,255,.28)" stroke-width="1.4"/>' +
    '<path d="M13.5 14.5h21v4.6h-8.2v17.4h-4.6V19.1h-8.2z" fill="#fff"/><ellipse cx="24" cy="23.4" rx="10.6" ry="3.3" fill="none" stroke="#fff" stroke-width="2.2"/>' +
    '<path d="M9 16a17 17 0 0 1 8-8" stroke="rgba(255,255,255,.55)" stroke-width="2.4" stroke-linecap="round" fill="none"/></svg>',
  iran: '<svg viewBox="0 0 48 48" aria-hidden="true"><defs><linearGradient id="giI" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#818CF8"/><stop offset=".55" stop-color="#6366F1"/><stop offset="1" stop-color="#1D4ED8"/></linearGradient></defs>' +
    '<rect x="2" y="2" width="44" height="44" rx="14" fill="url(#giI)"/><rect x="9" y="14" width="30" height="20" rx="3.6" fill="none" stroke="#fff" stroke-width="2.4"/>' +
    '<path d="M9 20.5h30" stroke="#fff" stroke-width="3"/><rect x="13" y="25.5" width="7" height="4.4" rx="1.2" fill="#FCD34D"/><path d="M24 28h10" stroke="rgba(255,255,255,.8)" stroke-width="2.2" stroke-linecap="round"/>' +
    '<path d="M8 10a14 14 0 0 1 7-4" stroke="rgba(255,255,255,.5)" stroke-width="2.2" stroke-linecap="round" fill="none"/></svg>'
};
var MT = { crypto: { i: GI.crypto, t: (B.txt && B.txt.crypto) || 'ارز دیجیتال' }, iran: { i: GI.iran, t: (B.txt && B.txt.iran) || 'درگاه ایرانی' } };
function stripEm(s){ return String(s || '').replace(/^[^؀-ۿA-Za-z0-9]+/, '').trim(); }
function methodOn(m){ return !!(S.info[m] && S.info[m].on); }

function drawNew(){
  var ms = ['crypto', 'iran'].filter(methodOn);
  if (!S.m || !methodOn(S.m)) S.m = ms[0] || '';
  $('mts').innerHTML = ms.map(function(m){
    var inf = S.info[m] || {};
    var sub = m === 'crypto' ? (inf.coin || 'USDT') + (inf.net ? ' · ' + inf.net : '') + ' · خودکار' : 'کارت‌های بانکی · آنی';
    return '<button class="mt' + (S.m === m ? ' on' : '') + '" data-m="' + m + '"><span class="gi">' + MT[m].i + '</span><b>' + esc(stripEm(MT[m].t)) + '</b><small>' + esc(sub) + '</small></button>';
  }).join('');
  $('mts').style.gridTemplateColumns = ms.length > 1 ? '1fr 1fr' : '1fr';
  var inf = S.info[S.m] || {};
  $('qc').innerHTML = (inf.q || []).map(function(v){ return '<button data-q="' + v + '">' + fa(v / 1000) + ' هزار</button>'; }).join('');
  if (!ms.length) { $('hint').innerHTML = 'فعلا هیچ روشِ پرداختِ آنلاینی روشن نیست.'; $('go').disabled = true; }
  upd();
}
function amtVal(){ return parseInt(digits($('amt').value), 10) || 0; }
function upd(){
  var a = amtVal(), inf = S.info[S.m] || {};
  [].forEach.call($('qc').children, function(b){ b.classList.toggle('on', +b.getAttribute('data-q') === a); });
  var h = 'حداقل: <b>' + fa(inf.min || 0) + '</b> تومان';
  if (S.m === 'iran' && inf.max && inf.max < 500000000) h += ' · حداکثر: <b>' + fa(inf.max) + '</b>';
  if (S.m === 'iran' && inf.kyc) h += '<br>🪪 برای بیش از <b>' + fa(inf.kyc) + '</b> تومان، یک‌بار احراز هویت لازم است.';
  if (S.m === 'crypto') h += '<br>⚡ بعد از واریز، کیف پول <b>خودکار</b> شارژ می‌شود.';
  $('hint').innerHTML = h;
  var pv = $('prev');
  if (S.m === 'crypto' && inf.rate > 0 && a > 0) { pv.classList.remove('hid'); $('prevV').textContent = '≈ ' + (Math.ceil(a / inf.rate * 100) / 100).toFixed(2) + ' ' + (inf.coin || 'USDT'); }
  else pv.classList.add('hid');
  $('go').disabled = !S.m || a < (inf.min || 0);
}
$('mts').addEventListener('click', function(ev){ var b = ev.target.closest('[data-m]'); if (!b) return; tap(); S.m = b.getAttribute('data-m'); drawNew(); });
$('qc').addEventListener('click', function(ev){ var b = ev.target.closest('[data-q]'); if (!b) return; tap(); $('amt').value = fa(+b.getAttribute('data-q')); upd(); });
$('amt').addEventListener('input', function(){ var n = parseInt(digits(this.value).slice(0, 10), 10) || 0; this.value = n ? fa(n) : ''; upd(); });
$('go').onclick = function(){
  var a = amtVal(); if (!S.m) return;
  var b = this; b.disabled = true; $('goT').textContent = 'در حالِ ساختِ فاکتور…'; tap('medium');
  api('pay_new', { m: S.m, amount: a }, function(j){ b.disabled = false; $('goT').textContent = pt('go', 'ادامه و پرداخت'); openOrder(j.o); },
    function(j){ b.disabled = false; $('goT').textContent = pt('go', 'ادامه و پرداخت');
      if (j.error === 'need_phone') { S.pend = a; show('v-ph'); return; }
      if (j.error === 'need_kyc') { S.kmode = j.mode || 'phone'; kycView(j.kyc, j.limit); return; }
      toast(j.message || 'ثبت نشد — دوباره امتحان کنید.'); });
};
$('phGo').onclick = function(){
  tap('medium');
  if (!TG || !TG.requestContact) { openBot('phone'); return; }
  try {
    TG.requestContact(function(ok){
      if (!ok) { toast('شماره فرستاده نشد.'); return; }
      toast('شماره فرستاده شد؛ یک لحظه…', true);
      var n = 0; (function w(){ api('pay_info', {}, function(j){ S.info = j; if (j.me && j.me.hasPhone) { $('go').click(); return; }
        if (++n < 12) setTimeout(w, 1500); else toast('شماره هنوز نرسیده؛ از داخلِ ربات بفرستید.'); }, function(){ if (++n < 12) setTimeout(w, 1500); }); })();
    });
  } catch(e){ openBot('phone'); }
};
$('phBot').onclick = function(){ tap(); openBot('phone'); };
function kycView(st, lim){
  var ph = S.kmode !== 'docs';
  $('kT').innerHTML = st === 'pending' ? 'درخواستِ احراز هویتِ شما در حالِ بررسی است؛ تا تایید، مبلغِ کمتر از <b>' + fa(lim) + '</b> تومان را پرداخت کنید.'
    : 'برای پرداختِ بیش از <b>' + fa(lim) + '</b> تومان، یک‌بار احراز هویت لازم است' + (ph ? ' — فقط با <b>شماره‌ی موبایلِ</b> خودتان؛ اسم و مدرک لازم نیست.' : ' (کدِ ملی + عکسِ کارتِ ملی، داخلِ ربات).');
  if (S.kmode === 'otp') $('kT').innerHTML = 'برای پرداختِ بیش از <b>' + fa(lim) + '</b> تومان، یک‌بار شماره‌ی موبایلتان را با <b>کدِ تاییدِ زرین‌پال</b> تایید کنید؛ اسم و مدرک لازم نیست.';
  $('kGoT').textContent = S.kmode === 'otp' ? 'دریافتِ کدِ تایید' : (ph ? 'ارسالِ شماره برای احراز هویت' : 'احراز هویت در ربات');
  $('kGo').classList.toggle('hid', st === 'pending');
  $('kOtp').classList.add('hid');
  show('v-k');
}
function otpSend(){
  var b = $('kGo'); b.disabled = true; $('kRe').disabled = true;
  api('pay_kyc', { step: 'send' }, function(j){ b.disabled = false; $('kRe').disabled = false;
      if (j.kyc === 'ok') { toast('احراز هویتِ شما تایید شده است.', true); show('v-new'); return; }
      $('kGo').classList.add('hid'); $('kOtp').classList.remove('hid');
      $('kOtpL').textContent = 'کدِ تاییدی که برای ' + (j.phone || 'شماره‌ی شما') + (j.ch === 'ussd' ? ' با USSD می‌آید' : ' پیامک شد');
      if (j.ussd) $('kT').innerHTML = 'برای گرفتنِ کد، <b class="ltr">' + esc(j.ussd) + '</b> را شماره‌گیری کنید.';
      toast(j.wait ? 'کد فرستاده شد؛ ' + fa(j.wait) + ' ثانیه بعد می‌توانید دوباره بگیرید.' : 'کد فرستاده شد.', true);
      setTimeout(function(){ try { $('kCode').focus(); } catch(e){} }, 200); },
    function(j){ b.disabled = false; $('kRe').disabled = false; if (j.error === 'need_phone') { show('v-ph'); return; } toast(j.message || 'کد فرستاده نشد.'); });
}
$('kRe').onclick = function(){ tap(); otpSend(); };
$('kCode').addEventListener('input', function(){ this.value = digits(this.value).slice(0, 10); });
$('kChk').onclick = function(){
  var c = digits($('kCode').value); if (c.length < 4) { toast('کد را کامل وارد کنید.'); return; }
  var b = this; b.disabled = true; tap('medium');
  api('pay_kyc', { step: 'code', code: c }, function(){ b.disabled = false; buzz('success'); toast('✅ شماره‌ی شما تایید شد؛ حالا پرداخت کنید.', true); show('v-new'); if (amtVal()) $('go').click(); },
    function(j){ b.disabled = false; toast(j.message || 'کد درست نیست.'); });
};
$('kGo').onclick = function(){
  tap('medium');
  if (S.kmode === 'docs') { openBot('kyc'); return; }
  if (S.kmode === 'otp') { otpSend(); return; }
  var b = this; b.disabled = true;
  api('pay_kyc', {}, function(j){ b.disabled = false; buzz('success'); toast(j.kyc === 'ok' ? 'احراز هویتِ شما قبلا تایید شده.' : 'برای بررسی فرستاده شد؛ نتیجه داخلِ ربات می‌آید.', true); if (j.kyc === 'ok') { show('v-new'); } else kycView('pending', (S.info.iran || {}).kyc || 0); },
    function(j){ b.disabled = false; if (j.error === 'need_phone') { show('v-ph'); return; } toast(j.message || 'ثبت نشد.'); });
};
$('kLess').onclick = function(){ tap(); show('v-new'); $('amt').focus(); };

function stopPoll(){ clearTimeout(S.poll); clearInterval(S.tick); }
function openOrder(o){
  stopPoll(); S.o = o;
  if (!o) { show('v-new'); drawNew(); return; }
  if (o.st === 'paid') { paid(o, null); return; }
  if (o.st === 'expired' || o.st === 'rejected') { $('erH').textContent = o.st === 'expired' ? pt('exp_title', 'این فاکتور منقضی شد') : 'این پرداخت رد شد'; show('v-er'); return; }
  if (o.m === 'crypto') drawCrypto(o); else drawIran(o);
  poll(o.m === 'crypto' ? 4000 : 6000);
}
function drawCrypto(o){
  var c = o.c || {};
  $('cA').textContent = fa(o.amount);
  $('cV').textContent = c.amt ? c.amt + ' ' + (c.coin || '') : '—';
  $('cpV').classList.toggle('hid', !c.amt);
  $('cN').innerHTML = (c.coin ? '<span>' + esc(c.coin) + '</span>' : '') + (c.net ? '<span>شبکه‌ی ' + esc(c.net) + '</span>' : '') + '<span>کدِ ' + esc(o.id) + '</span>';
  $('cQ').innerHTML = c.qr || ''; $('cQ').classList.toggle('hid', !c.qr);
  $('cAd').textContent = c.addr || ''; $('cAd').classList.toggle('hid', !c.addr);
  $('cpA').classList.toggle('hid', !c.addr);
  $('cU').classList.toggle('hid', !!c.addr || !c.url);
  $('cWt').innerHTML = c.addr ? (PT.warn ? esc(PT.warn).replace(/\{coin\}/g, '<b>' + esc(c.coin || '') + '</b>').replace(/\{network\}/g, '<b>' + esc(c.net || '') + '</b>')
      : 'فقط <b>' + esc(c.coin || '') + '</b>' + (c.net ? ' روی شبکه‌ی <b>' + esc(c.net) + '</b>' : '') + ' و دقیقا همین مقدار را بفرستید؛ کارمزدِ برداشتِ صرافی را جدا حساب کنید.')
    : 'صفحه‌ی پرداخت را باز کنید؛ آدرس و مقدار آنجاست.';
  show('v-c');
  var tot = Math.max(60, (c.exp || 0) - Math.floor(Date.now() / 1000));
  function t(){ var left = Math.max(0, (c.exp || 0) - Math.floor(Date.now() / 1000));
    $('cT').textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
    $('cR').style.strokeDashoffset = (207.3 * (1 - left / tot)).toFixed(1);
    if (!left && c.exp) { stopPoll(); $('erH').textContent = pt('exp_title', 'مهلتِ این فاکتور تمام شد'); show('v-er'); } }
  t(); S.tick = setInterval(t, 1000);
}
function drawIran(o){
  var i = o.i || {};
  $('iA').textContent = fa(o.amount); $('iP').textContent = i.phone || '—'; $('iId').textContent = o.id; $('iS').textContent = 'در انتظارِ پرداخت';
  show('v-i');
}
function paid(o, bal){
  stopPoll(); buzz('success');
  $('okA').textContent = fa(o.amount);
  $('okB').textContent = bal != null ? 'موجودیِ تازه: ' + fa(bal) + ' تومان' : 'موجودیِ شما به‌روز شد.';
  show('v-ok');
}
function status(force, cb){
  var o = S.o; if (!o) return;
  var u = B.st + '&o=' + encodeURIComponent(o.id) + '&t=' + encodeURIComponent(o.t) + (force ? '&check=1' : '');
  fetch(u, { cache: 'no-store', credentials: 'omit' }).then(function(r){ return r.json(); }).then(function(j){
    if (!j || !j.ok || S.o !== o) { cb && cb(false); return; }
    if (j.st === 'paid') { paid(o, j.bal); cb && cb(true); return; }
    if (j.st === 'expired') { stopPoll(); $('erH').textContent = pt('exp_title', 'مهلتِ این فاکتور تمام شد'); show('v-er'); cb && cb(false); return; }
    cb && cb(false, j);
  }).catch(function(){ cb && cb(false); });
}
function poll(ms){ clearTimeout(S.poll); S.poll = setTimeout(function(){ if (!D.hidden) status(false); if (S.o && S.o.st !== 'paid') poll(ms); }, ms); }
D.addEventListener('visibilitychange', function(){ if (!D.hidden && S.o) status(S.o.m === 'iran'); });
function chk(btn){ tap('medium'); btn.disabled = true; status(true, function(ok){ btn.disabled = false; if (!ok) toast(S.o && S.o.m === 'iran' ? 'هنوز پرداختی تایید نشده.' : 'هنوز واریزی دیده نشد؛ خودکار هم بررسی می‌شود.'); }); }
$('cChk').onclick = function(){ chk(this); };
$('iChk').onclick = function(){ chk(this); };
function cancel(){ tap(); var o = S.o; stopPoll(); S.o = null; if (o && initData()) api('pay_cancel', { order: o.id }, function(){}, function(){}); show('v-new'); drawNew(); }
$('cX').onclick = cancel; $('iX').onclick = cancel;
$('cpV').onclick = function(){ var c = (S.o && S.o.c) || {}; tap(); copy(c.amt, 'مقدار'); };
$('cpA').onclick = function(){ var c = (S.o && S.o.c) || {}; tap(); copy(c.addr, 'آدرسِ ولت'); };
$('cU').onclick = function(){ var c = (S.o && S.o.c) || {}; tap(); if (c.url) openExt(c.url); };
$('iGo').onclick = function(){ var i = (S.o && S.o.i) || {}; tap('medium'); if (i.url) { openExt(i.url); $('iS').textContent = 'منتظرِ بازگشت از درگاه…'; } };
$('okGo').onclick = function(){ tap(); goBack(); };
$('erGo').onclick = function(){ tap(); S.o = null; show('v-new'); drawNew(); };
$('bk').onclick = function(){ tap(); goBack(); };

function fsSync(){ var on = false; try { on = !!(TG && TG.isFullscreen); } catch(e){} H.classList.toggle('fs', on); }
function tgSetup(){
  if (!TG) return;
  try { TG.ready(); TG.expand(); } catch(e){}
  try { var bg = getComputedStyle(H).getPropertyValue('--bg').trim() || '#03060b'; TG.setHeaderColor && TG.setHeaderColor(bg); TG.setBackgroundColor && TG.setBackgroundColor(bg); } catch(e){}
  try { if (TG.BackButton) { TG.BackButton.show(); TG.BackButton.onClick(goBack); } } catch(e){}
  try { TG.onEvent('fullscreenChanged', fsSync); } catch(e){}
  fsSync();
}
if (TG) tgSetup(); else { var tries = 0, iv = setInterval(function(){ if (window.Telegram && window.Telegram.WebApp) { clearInterval(iv); TG = window.Telegram.WebApp; tgSetup(); } else if (++tries > 30) clearInterval(iv); }, 100); }

if (B.o) openOrder(B.o);
else {
  S.m = B.m || '';
  if (B.a) $('amt').value = fa(B.a);
  show('v-new'); drawNew();
  if (!initData()) { $('hint').innerHTML = '⚠️ این صفحه را از داخلِ ربات یا مینی‌اپ باز کنید.'; $('go').disabled = true; }
  else api('pay_info', {}, function(j){ S.info = j; drawNew(); }, function(){});
}
})();
</script>
</body>
</html>
HTML;
    $json = json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    return strtr($tpl, ['__TH__' => (string)($boot['th'] ?? 'num'), '__FONT__' => $font, '__BOOT__' => $json]);
}
