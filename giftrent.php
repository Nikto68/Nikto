<?php
/**
 * 🎁 giftrent.php — اجاره‌ی گیفتِ NFT (باز-فروش)
 * ============================================================
 *
 * فروشگاه هیچ گیفتی مالک نیست؛ فقط واسطه‌ی مارکتِ اجاره‌ی گیفتِ
 * marketapp.org است (همان پنلی که استارز/پریمیوم/گیفتِ فعلی از آن
 * می‌آید — همان کلید API را اینجا هم دوباره استفاده می‌کنیم، توکنِ
 * جدیدی لازم نیست).
 *
 * جریانِ کار:
 *   ۱. GET /v1/rent/gifts/      → لیست + قیمتِ روزانه (کش‌شده)
 *   ۲. POST …/pay/              → با کیف‌پولِ خودِ فروشگاه پرداخت می‌شود
 *      (همان تابعِ axWalletHandle که برای گیفتِ معمولی هم استفاده می‌شود)
 *   ۳. POST …/tonconnect/       → کیف‌پولِ مشتری وصل می‌شود؛ از این لحظه
 *      گیفت زیرِ نامِ خودِ مشتری دیده می‌شود — مالکیتِ واقعی هیچ‌وقت جابه‌جا
 *      نمی‌شود، فقط نمایش است.
 *
 * ⚠️ این فایل کاملاً مستقل از miniapps.php است — نه apps/cats/items را
 *    لمس می‌کند نه maServe/maApi را. یعنی هر باگی اینجا باشد، مینی‌اپِ
 *    «تلگرام»/«شماره مجازی» دست‌نخورده می‌ماند.
 */

if (!defined('GR_LIB')) define('GR_LIB', 1);

// ============================================================
// ⚙️ پیکربندی
// ============================================================

function grDefaults() {
    return [
        'on' => false,   // تا ادمین روشن نکند، هیچ‌جا دیده نمی‌شود
    ];
}

function grCfg() {
    $c = cfg()['gift_rent'] ?? null;
    return is_array($c) ? array_replace(grDefaults(), $c) : grDefaults();
}

function grSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!isset($c['gift_rent']) || !is_array($c['gift_rent'])) $c['gift_rent'] = [];
        $fn($c['gift_rent']);
    });
}

function grOn() { return !empty(grCfg()['on']); }

// ============================================================
// 🌐 تماس با marketapp.org — از همان کلیدِ پنلِ فروشِ فعلی
// ============================================================

/**
 * یک تماسِ خام. عمداً از maFulfillRaw استفاده نمی‌کند تا اسکیمای
 * fulfill.ops (استارز/پریمیوم/گیفت) دست‌نخورده بماند — فقط سه‌تا مقدارِ
 * موجود (base, auth_key, auth_value) را می‌خواند، هیچ‌چیزی نمی‌نویسد.
 */
function grApiCall($path, $method = 'GET', $bodyArr = null, $timeout = null) {
    if (function_exists('__grHttpHook')) return __grHttpHook($path, $method, $bodyArr);

    $f    = maCfg()['fulfill'] ?? [];
    $base = rtrim((string)($f['base'] ?? ''), '/');
    if ($base === '') return [null, 'آدرسِ پنلِ فروش ثبت نشده'];

    $url = $base . '/' . ltrim((string)$path, '/');
    $ak  = trim((string)($f['auth_key'] ?? 'Authorization'));
    $av  = (string)($f['auth_value'] ?? '');
    if ($av === '') return [null, 'کلیدِ APIِ پنلِ فروش خالی است'];
    $headers = $ak !== '' ? $ak . ': ' . $av : '';
    $body    = $bodyArr === null ? '' : json_encode($bodyArr, JSON_UNESCAPED_UNICODE);

    return maHttp($url, $method, $headers, $body, (int)($timeout ?? ($f['timeout'] ?? 20)));
}

/** خطا را از پاسخِ marketapp (چه رشته چه detail[]) یک خط می‌کند */
function grErrText($resp, $fallback) {
    if (!is_array($resp)) return $fallback;
    if (function_exists('maErrText') && isset($resp['detail'])) {
        $t = maErrText($resp['detail']);
        if ($t !== '') return mb_substr($t, 0, 200);
    }
    foreach (['message', 'error', 'msg'] as $k)
        if (!empty($resp[$k]) && is_string($resp[$k])) return mb_substr($resp[$k], 0, 200);
    return $fallback;
}

// ============================================================
// 📋 کاتالوگ — لیستِ گیفت‌های قابل‌اجاره
// ============================================================

function grListRaw($fresh = false) {
    $ck = 'gr_list';
    if (!$fresh) {
        $hit = maCacheGet($ck, 300);
        if (is_array($hit)) return $hit;
    }
    return grListRefresh();
}

/**
 * 📄 کاتالوگ کامل — همه‌ی صفحه‌ها.
 *
 * قبلاً فقط اولین صفحه‌ی marketapp خوانده می‌شد — یعنی گیفت‌های
 * معروف‌تر (که تعدادِ زیادی هم دارن) عملاً هیچ‌وقت به کشِ ما نمی‌رسیدن،
 * پس نه تو لیست بودن نه تو جستجو. اینجا با cursor جلو می‌رود تا کاتالوگ
 * واقعاً کامل شود.
 *
 * ⚠️ این تابع می‌تواند چند رفت‌وبرگشتِ شبکه بخواهد — به همین دلیل از
 * runBackgroundQueues() هر چند دقیقه در پس‌زمینه (بعد از جواب‌دادن به
 * تلگرام، بدونِ اینکه کاربری منتظرش بماند) صدا زده می‌شود. مسیرِ
 * synchronous (اولین بارِ بدونِ کش) هم همین را صدا می‌زند، ولی آن‌وقت
 * فقط یک‌بار در عمرِ نصب اتفاق می‌افتد.
 */
function grListRefresh() {
    $ck = 'gr_list';
    $items = [];
    $seen  = [];
    $cursor = '';
    for ($page = 0; $page < 25; $page++) {
        $path = '/v1/rent/gifts/?sort_by=price_per_day';
        if ($cursor !== '') $path .= '&cursor=' . rawurlencode($cursor);
        [$resp, $err] = grApiCall($path, 'GET', null, 12);
        if (!is_array($resp) || !is_array($resp['items'] ?? null) || !$resp['items']) break;

        foreach ($resp['items'] as $it) {
            if (!is_array($it)) continue;
            $addr = trim((string)($it['nft_address'] ?? ''));
            if ($addr === '' || isset($seen[$addr])) continue;
            $seen[$addr] = true;
            $items[] = [
                'nft_address'    => $addr,
                'nft_name'       => (string)($it['nft_name'] ?? 'گیفت'),
                'owner'          => (string)($it['owner'] ?? ''),
                'attributes'     => is_array($it['attributes'] ?? null) ? $it['attributes'] : [],
                'min_duration'   => (int)($it['min_duration'] ?? 86400),
                'max_duration'   => (int)($it['max_duration'] ?? 86400),
                'price_per_day'  => (string)($it['price_per_day'] ?? '0'),
                'discount_pd'    => (float)($it['discount_per_day'] ?? 0),
            ];
        }

        $next = trim((string)($resp['cursor'] ?? ''));
        if ($next === '' || $next === $cursor) break;
        $cursor = $next;
    }

    if (!$items) return (array)(maCacheGet($ck, 0) ?: []);
    maCachePut($ck, $items);
    return $items;
}

function grFindGift($nftAddress) {
    foreach (grListRaw() as $it) if ($it['nft_address'] === $nftAddress) return $it;
    // شاید بینِ کش تازه شدن رفته — یک‌بار تازه امتحان کن
    foreach (grListRaw(true) as $it) if ($it['nft_address'] === $nftAddress) return $it;
    return null;
}

/**
 * قیمتِ یک‌روزِ این گیفت به تومان، با سود.
 * price_per_day از marketapp به nanoTON می‌آید (همان قراردادِ amountِ
 * بقیه‌ی این API) — با نرخِ لحظه‌ایِ TON (که خودِ ربات برای قیمت‌گیری هم
 * استفاده می‌کند) به تومان تبدیل می‌شود، بعد سودِ بخشِ «rent» رویش می‌نشیند.
 */
// ⚠️ حداقلِ منطقیِ قیمتِ روزانه — پایین‌تر از این یعنی جایی تو تبدیلِ
// واحد (نانو-تون یا نرخ) خرابه، نه اینکه واقعاً همچین قیمتی درسته.
// موقع کشف‌شدنش زیرِ ۲۰۰ تومان بود که باعثِ ضررِ خالص می‌شد — پس اینجا
// به‌جای نمایشِ عددِ مشکوک، جنسِ «نرخ نداریم» برمی‌گردونه (grPublicList
// خودش این آیتم رو قایم می‌کنه، دقیقاً مثلِ وقتی که واقعاً نرخِ TON نیست).
define('GR_MIN_DAY_TOMAN', 500);

function grDayTomanDebug($item) {
    $raw = (string)($item['price_per_day'] ?? '0');
    $ton = (float)(function_exists('nanoToTon') ? nanoToTon($raw) : 0);
    $p    = function_exists('pxFetch') ? pxFetch() : [];
    $usdt = (float)($p['TON/USDT'] ?? 0);
    $irt  = (float)($p['USDT/IRT'] ?? 0);
    $toman = ($ton > 0 && $usdt > 0 && $irt > 0) ? $ton * $usdt * $irt : 0.0;
    $final = function_exists('pfApply') ? round(pfApply('rent', $toman), 0) : round($toman, 0);
    if ($final > 0 && $final < GR_MIN_DAY_TOMAN) {
        error_log('[giftrent] suspiciously low day price — raw=' . $raw . ' ton=' . $ton .
                   ' ton_usdt=' . $usdt . ' usdt_irt=' . $irt . ' toman=' . $final .
                   ' nft=' . (string)($item['nft_address'] ?? ''));
        $final = 0.0;
    }
    return ['toman' => $final, 'raw' => $raw, 'ton' => $ton, 'ton_usdt' => $usdt, 'usdt_irt' => $irt];
}

function grDayToman($item) { return grDayTomanDebug($item)['toman']; }

/** شکلِ عمومی — همان چیزی که مینی‌اپ می‌بیند. */
function grPublicList() {
    $out = [];
    foreach (grListRaw() as $it) {
        $day = grDayToman($it);
        if ($day <= 0) continue;   // نرخِ TON نیامده یا مشکوکه — نمایشش ندیم
        $out[] = [
            'nft_address'  => $it['nft_address'],
            'nft_name'     => $it['nft_name'],
            'attributes'   => $it['attributes'],
            'min_days'     => max(1, (int)round($it['min_duration'] / 86400)),
            'max_days'     => max(1, (int)round($it['max_duration'] / 86400)),
            'price_day'    => $day,
        ];
    }
    return $out;
}

// ============================================================
// 💾 دیتای اجاره‌ها
// ============================================================

function grOrderId() { return 'gr_' . base_convert((string)time(), 10, 36) . bin2hex(random_bytes(3)); }

function grLoad($id) { return load('gift_rentals')[$id] ?? null; }

function grSave($id, array $row) {
    mutate('gift_rentals', function (&$a) use ($id, $row) { $a[$id] = $row; });
}

function grMyRentals($uid) {
    $out = [];
    foreach (load('gift_rentals') as $r) if ((int)($r['uid'] ?? 0) === (int)$uid) $out[] = $r;
    usort($out, fn($a, $b) => ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0));
    return array_slice($out, 0, 30);
}

/** کسرِ اتمیکِ موجودی — برخلاف addBalance، اگر کم بود false برمی‌گرداند */
function grDebit($uid, $amount) {
    $ok = false;
    mutate('users', function (&$users) use ($uid, $amount, &$ok) {
        $k = (string)$uid;
        if (!isset($users[$k])) return;
        if ((float)$users[$k]['balance'] < $amount) return;
        $users[$k]['balance'] = (float)round((float)$users[$k]['balance'] - $amount);
        $ok = true;
    });
    return $ok;
}

// ============================================================
// 💳 پرداخت + اتصالِ کیف‌پول
// ============================================================

/**
 * پرداختِ اجاره از کیف‌پولِ خودِ فروشگاه.
 * برگشت: [ok, error]
 */
function grPay($rentalId) {
    $r = grLoad($rentalId);
    if (!$r || $r['status'] !== 'paying') return [false, 'سفارش پیدا نشد'];

    [$resp, $err] = grApiCall(
        '/v1/rent/' . rawurlencode($r['nft_address']) . '/pay/',
        'POST',
        ['duration' => (int)$r['duration'], 'price_per_day' => (string)$r['price_per_day_raw']]
    );
    if (!is_array($resp)) {
        $r['status'] = 'failed'; $r['note'] = 'پنل: ' . $err; grSave($rentalId, $r);
        return [false, $err ?: 'پاسخی از پنل نیامد'];
    }
    if (isset($resp['detail'])) {
        $why = grErrText($resp, 'رد شد');
        $r['status'] = 'failed'; $r['note'] = $why; grSave($rentalId, $r);
        return [false, $why];
    }

    if (!function_exists('axWalletHandle')) {
        $r['status'] = 'failed'; $r['note'] = 'ماژولِ کیف‌پول بارگذاری نشده'; grSave($rentalId, $r);
        return [false, 'ماژولِ کیف‌پول بارگذاری نشده'];
    }
    [$ok, $info] = axWalletHandle($resp, $rentalId);
    if (!$ok) {
        $r['status'] = 'failed'; $r['note'] = $info; grSave($rentalId, $r);
        return [false, $info];
    }

    $r['status']     = 'connect_wait';
    $r['paid_at']    = time();
    $r['expires_at'] = time() + (int)$r['duration'];
    $r['pay_info']   = $info;
    grSave($rentalId, $r);
    return [true, ''];
}

/** اتصالِ کیف‌پولِ مشتری — از این لحظه گیفت زیرِ نامِ او دیده می‌شود */
function grConnect($rentalId, $tonconnectUrl) {
    $r = grLoad($rentalId);
    if (!$r) return [false, 'سفارش پیدا نشد'];
    if ($r['status'] !== 'connect_wait' && $r['status'] !== 'active') return [false, 'این سفارش آماده‌ی اتصال نیست'];

    [$resp, $err] = grApiCall(
        '/v1/rent/' . rawurlencode($r['nft_address']) . '/tonconnect/',
        'POST',
        ['tonconnect_url' => (string)$tonconnectUrl]
    );
    if (!is_array($resp)) return [false, $err ?: 'پاسخی از پنل نیامد'];
    if (isset($resp['detail'])) return [false, grErrText($resp, 'اتصال رد شد')];

    $r['status']       = 'active';
    $r['connected_at'] = time();
    grSave($rentalId, $r);
    return [true, ''];
}

// ============================================================
// 🔌 API مینی‌اپ
// ============================================================

function grApiOut($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function grApi() {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST')
        grApiOut(['ok' => false, 'error' => 'bad_method'], 405);
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768)
        grApiOut(['ok' => false, 'error' => 'too_large'], 413);

    $ip = function_exists('maClientIp') ? maClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '');
    if (function_exists('maRateOk') && !maRateOk('ip', $ip, 300, 60))
        grApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'درخواست‌ها زیاد است، کمی صبر کنید.'], 429);

    $raw  = file_get_contents('php://input', false, null, 0, 32768);
    $body = json_decode((string)$raw, true);
    if (!is_array($body)) $body = [];

    if (!grOn()) grApiOut(['ok' => false, 'error' => 'closed', 'message' => 'این بخش موقتا بسته است.'], 403);

    $initData = (string)($body['initData'] ?? '');
    $reason = '';
    $user = function_exists('maVerifyInitData') ? maVerifyInitData($initData, $reason) : null;
    if (!$user) grApiOut(['ok' => false, 'error' => 'unauthorized', 'reason' => $reason], 401);

    $uid = (int)$user['id'];
    if (function_exists('maRateOk') && !maRateOk('u', $uid, 30, 60))
        grApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'درخواست‌ها زیاد است، کمی صبر کنید.'], 429);

    if (function_exists('touchUser'))
        touchUser($uid, (string)($user['username'] ?? ''), (string)($user['first_name'] ?? ''));
    $u = function_exists('getUser') ? getUser($uid) : null;
    if ($u && !empty($u['banned'])) grApiOut(['ok' => false, 'error' => 'banned'], 403);

    $action = (string)($body['action'] ?? '');

    if ($action === 'me') {
        grApiOut(['ok' => true, 'balance' => (float)($u['balance'] ?? 0), 'rentals' => grMyRentals($uid)]);
    }

    if ($action === 'list') {
        grApiOut(['ok' => true, 'items' => grPublicList()]);
    }

    if ($action === 'order') {
        $addr = trim((string)($body['nft_address'] ?? ''));
        $days = max(1, (int)($body['days'] ?? 0));
        $item = grFindGift($addr);
        if (!$item) grApiOut(['ok' => false, 'error' => 'not_found', 'message' => 'این گیفت دیگر موجود نیست.']);

        $minD = max(1, (int)round($item['min_duration'] / 86400));
        $maxD = max($minD, (int)round($item['max_duration'] / 86400));
        if ($days < $minD || $days > $maxD)
            grApiOut(['ok' => false, 'error' => 'bad_days', 'message' => "مدت باید بینِ {$minD} تا {$maxD} روز باشد."]);

        $dayToman = grDayToman($item);
        if ($dayToman <= 0)
            grApiOut(['ok' => false, 'error' => 'no_rate', 'message' => 'نرخ لحظه‌ای در دسترس نیست، چند لحظه بعد امتحان کنید.']);
        $total = round($dayToman * $days);

        if (!grDebit($uid, $total))
            grApiOut(['ok' => false, 'error' => 'no_balance', 'message' => 'موجودی کافی نیست.', 'need' => $total]);

        $rid = grOrderId();
        grSave($rid, [
            'id' => $rid, 'uid' => $uid, 'nft_address' => $addr, 'nft_name' => $item['nft_name'],
            'duration' => $days * 86400, 'price_per_day_raw' => $item['price_per_day'],
            'toman_total' => $total, 'status' => 'paying', 'created_at' => time(),
        ]);

        [$ok, $err] = grPay($rid);
        if (!$ok) {
            addBalance($uid, $total);   // برگشتِ پول — پرداخت به پنل نرفت یا رد شد
            grApiOut(['ok' => false, 'error' => 'pay_failed', 'message' => $err]);
        }
        grApiOut(['ok' => true, 'rental_id' => $rid]);
    }

    if ($action === 'connect') {
        $rid = (string)($body['rental_id'] ?? '');
        $url = (string)($body['tonconnect_url'] ?? '');
        $r = grLoad($rid);
        if (!$r || (int)$r['uid'] !== $uid) grApiOut(['ok' => false, 'error' => 'not_found']);
        [$ok, $err] = grConnect($rid, $url);
        grApiOut($ok ? ['ok' => true] : ['ok' => false, 'error' => 'connect_failed', 'message' => $err]);
    }

    if ($action === 'status') {
        $rid = (string)($body['rental_id'] ?? '');
        $r = grLoad($rid);
        if (!$r || (int)$r['uid'] !== $uid) grApiOut(['ok' => false, 'error' => 'not_found']);
        grApiOut(['ok' => true, 'status' => $r['status'], 'expires_at' => (int)($r['expires_at'] ?? 0)]);
    }

    grApiOut(['ok' => false, 'error' => 'bad_action'], 400);
}

// ============================================================
// 🌐 سرو کردنِ صفحه
// ============================================================

function grSecurityHeaders() {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    // ⚠️ برخلافِ مینی‌اپ‌های دیگر، اینجا TonConnect واقعاً لازم است — یعنی
    // یک کتابخانه‌ی بیرونی (SDK رسمی) و یک اتصالِ شبکه به بریج‌های
    // TonConnect. دست‌ساز پیاده‌سازی‌کردنِ آن یعنی رمزنگاریِ خودمان —
    // خطرش از وابستگی به SDKِ رسمی بیشتر است. این سیاست فقط همین یک صفحه
    // را شل می‌کند؛ بقیه‌ی مینی‌اپ‌ها (CSP سخت‌گیرشان) دست‌نخورده می‌مانند.
    header(
        "Content-Security-Policy: " .
        "default-src 'none'; " .
        "script-src 'self' 'unsafe-inline' https://telegram.org https://*.telegram.org " .
            "https://cdn.jsdelivr.net; " .
        "style-src 'self' 'unsafe-inline'; " .
        // عکسِ خودِ گیفت از سرورِ فرگمنت می‌آید — تنها استثنای img-src نسبت به
        // مینی‌اپ‌های دیگر، چون کاتالوگ بدونِ عکسِ واقعیِ گیفت بی‌فایده است
        "img-src 'self' data: https://t.me https://*.telegram.org https://*.telesco.pe " .
            "https://nft.fragment.com; " .
        "connect-src 'self' https://*.tonapi.io https://*.ton.org https://walletbot.me " .
            "https://bridge.tonapi.io https://*.toncenter.com; " .
        "base-uri 'none'; form-action 'none'; " .
        "frame-ancestors https://web.telegram.org https://*.telegram.org; " .
        "object-src 'none'"
    );
}

// ============================================================
// 🎛 دکمه‌ی کنارِ محصولات + پنلِ مدیریت
// ============================================================

function grBtnCfg() {
    $c = grCfg();
    return [
        'text'  => (string)($c['btn_text']  ?? 'اجاره‌ی گیفت'),
        'emoji' => (string)($c['btn_emoji'] ?? '🎁'),
        'icon'  => (string)($c['btn_icon']  ?? ''),
        'order' => (int)($c['btn_order']    ?? 60),
        'row'   => (int)($c['btn_row']      ?? 0),
    ];
}

/**
 * دکمه‌ای که کنارِ زیردکمه‌های «ثـبـت سـفـارش» (ممبر فیک و بقیه) می‌نشیند —
 * دقیقاً با همان مکانیزمِ فعلیِ ادغامِ دکمه‌ی مینی‌اپ‌ها، فقط برای همین یکی.
 */
function grSubItem() {
    if (!grOn()) return null;
    $base = function_exists('maBaseUrl') ? maBaseUrl() : '';
    if ($base === '') return null;
    $b = grBtnCfg();
    return [
        'id' => '__gr_rent', 'emoji' => $b['emoji'], 'text' => $b['text'],
        'color' => 'none', 'icon' => $b['icon'], 'order' => $b['order'], 'row' => $b['row'],
        'on' => true, 'action' => '',
        '_webapp' => $base . (str_contains($base, '?') ? '&' : '?') . 'rent=1',
    ];
}

function grHome($chatId, $msgId = null) {
    $c = grCfg(); $b = grBtnCfg();
    $on = !empty($c['on']);
    $t  = "🎁 <b>اجاره‌ی گیفت</b>\n\n";
    $t .= 'وضعیت: ' . ($on ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= 'دکمه: ' . h(trim($b['emoji'] . ' ' . $b['text'])) . ($b['icon'] !== '' ? ' 💎' : '') . "\n";
    $t .= 'ترتیب: <b>' . $b['order'] . '</b> · ردیف: <b>' . $b['row'] . '</b>' .
          ($b['row'] === 0 ? ' (خودکار)' : '') . "\n\n";
    $t .= "این یک مینی‌اپِ جداست — باز-فروشِ اجاره‌ی گیفتِ NFT (بدون مالکیتِ واقعیِ شما).\n" .
          "دکمه‌اش کنارِ محصولاتِ «ثـبـت سـفـارش» می‌نشیند — همون زیردکمه‌هایی که «ممبر فیک» و بقیه توشن؛ " .
          "ترتیب و ردیف رو با همون منطقِ اون‌ها می‌شه چید.";
    $rows = [
        [btnCb($on ? '❌ خاموش کن' : '✅ روشن کن', 'gr_tog', 'info')],
        [btnCb('✏️ متنِ دکمه', 'gr_text', 'admin'), btnCb('🙂 ایموجیِ ساده', 'gr_emoji', 'admin')],
        [btnCb('💎 ایموجیِ پریمیوم', 'gr_icon', 'admin')],
        [btnCb('🔢 ترتیبِ نمایش', 'gr_order', 'admin'), btnCb('📍 شماره‌ی ردیف', 'gr_row', 'admin')],
        [btnCb('📐 چیدمانِ زیردکمه‌های ثبتِ سفارش', 'sbs_buy', 'nav')],
        [btnCb('📈 سودِ اجاره', 'pf_rent', 'nav')],
        [btnCb('🔙 بازگشت', 'adm_home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function grCallback($data, $uid, $chatId, $msgId, $cbId, $isAdmin) {
    if (!str_starts_with((string)$data, 'gr_')) return false;
    if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, '🔒', true); return true; }
    $ack = function ($m = '') use ($cbId) { answerCb(BOT_TOKEN, $cbId, $m); };

    if ($data === 'gr_home') { $ack(); grHome($chatId, $msgId); return true; }

    if ($data === 'gr_tog') {
        grSet(function (&$c) { $c['on'] = empty($c['on']); });
        $ack(grOn() ? '✅ روشن' : '❌ خاموش');
        grHome($chatId, $msgId);
        return true;
    }

    if (in_array($data, ['gr_text', 'gr_emoji', 'gr_icon', 'gr_order', 'gr_row'], true)) {
        $ack();
        setState($uid, $data, []);
        $hint = [
            'gr_text'  => '✏️ متنِ دکمه را بفرستید — مثلا <code>اجاره‌ی گیفت</code>',
            'gr_emoji' => '🙂 یک ایموجیِ معمولی بفرستید — مثلا 🎁',
            'gr_icon'  => '💎 یک پیامِ حاویِ همان ایموجیِ پریمیوم بفرستید — خودش برداشته می‌شود.',
            'gr_order' => "🔢 ترتیبِ نمایش (عدد):\n\nعددِ کوچک‌تر یعنی بالاتر. زیردکمه‌های ثبتِ سفارش هم همین «ترتیب» را دارند، پس با عدد می‌توانید این دکمه را بینِ آن‌ها جابه‌جا کنید.",
            'gr_row'   => '📍 شماره‌ی ردیف (عدد):' . "\n\n" . 'اگر عدد بدهید، این دکمه حتماً در همان ردیف می‌نشیند. ۰ یعنی خودکار (طبقِ چیدمان).',
        ][$data];
        sendMsg(BOT_TOKEN, $chatId, $hint, inlineKb([[btnCb('🔙 بی‌خیال', 'gr_home', 'nav')]]));
        return true;
    }

    $ack();
    return true;
}

function grStateHandle($action, $msg, $uid, $chatId) {
    if (!in_array((string)$action, ['gr_text', 'gr_emoji', 'gr_icon', 'gr_order', 'gr_row'], true)) return false;
    $back = inlineKb([[btnCb('🎁 اجاره‌ی گیفت', 'gr_home', 'admin')]]);

    if ($action === 'gr_icon') {
        $ids = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
        if (!$ids) { sendMsg(BOT_TOKEN, $chatId, '⚠️ ایموجیِ پریمیومی توی پیام پیدا نشد.', $back); clearState($uid); return true; }
        $ic = $ids[0];
        grSet(function (&$c) use ($ic) { $c['btn_icon'] = $ic; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ثبت شد.', $back);
        return true;
    }

    if ($action === 'gr_order' || $action === 'gr_row') {
        $n = (int)preg_replace('/[^0-9]/', '', (string)($msg['text'] ?? ''));
        if ($action === 'gr_order') grSet(function (&$c) use ($n) { $c['btn_order'] = max(1, $n); });
        else                        grSet(function (&$c) use ($n) { $c['btn_row']   = max(0, $n); });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ثبت شد.', $back);
        return true;
    }

    $text = trim((string)($msg['text'] ?? ''));
    if ($text === '') { clearState($uid); return true; }
    if ($action === 'gr_text')  grSet(function (&$c) use ($text) { $c['btn_text']  = mb_substr($text, 0, 30); });
    if ($action === 'gr_emoji') grSet(function (&$c) use ($text) { $c['btn_emoji'] = mb_substr($text, 0, 4); });
    clearState($uid);
    sendMsg(BOT_TOKEN, $chatId, '✅ ثبت شد.', $back);
    return true;
}

function grServe() {
    $c = grCfg();
    if (empty($c['on'])) {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><body style="background:#0B0B12;color:#aaa;' .
             'font-family:sans-serif;display:flex;height:100vh;align-items:center;justify-content:center">' .
             'این بخش موقتا بسته است.</body>';
        exit;
    }
    grSecurityHeaders();
    echo grViewRent();
    exit;
}
