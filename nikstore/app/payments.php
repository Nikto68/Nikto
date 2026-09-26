<?php
function zp_on()     { return setting('zp_on') === '1' && trim((string)setting('zp_merchant')) !== ''; }
function card_on()   { return setting('card_on') === '1' && trim((string)setting('card_number')) !== ''; }
function wallet_on() { return setting('wallet_on') === '1'; }

function zp_base() { return setting('zp_sandbox') === '1' ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com'; }

function zp_start($order) {
    if (!zp_on()) return [false, 'درگاه آنلاین فعال نیست.'];
    $body = json_encode([
        'merchant_id'  => trim((string)setting('zp_merchant')),
        'amount'       => (int)$order['amount'] * 10,
        'callback_url' => base_url() . '/index.php?p=zp&code=' . rawurlencode($order['code']),
        'description'  => mb_substr($order['title'] . ' — ' . setting('site_name'), 0, 200),
        'metadata'     => array_filter(['mobile' => preg_match('/^09\d{9}$/', (string)$order['contact']) ? $order['contact'] : null]),
    ], JSON_UNESCAPED_UNICODE);
    [$code, $res, $err] = http_req('POST', zp_base() . '/pg/v4/payment/request.json', $body,
                                   ['Content-Type: application/json', 'Accept: application/json']);
    if ($err !== '') return [false, 'اتصال به درگاه برقرار نشد: ' . $err];
    $j = json_decode($res, true);
    $auth = (string)($j['data']['authority'] ?? '');
    if ((int)($j['data']['code'] ?? 0) !== 100 || $auth === '') {
        $msg = is_array($j['errors'] ?? null) ? (string)($j['errors']['message'] ?? '') : '';
        return [false, 'درگاه درخواست را نپذیرفت' . ($msg !== '' ? ': ' . $msg : '') . '.'];
    }
    order_set($order['id'], ['method' => 'zarinpal', 'authority' => $auth]);
    return [true, zp_base() . '/pg/StartPay/' . rawurlencode($auth)];
}

function zp_verify($order, $authority) {
    if ($authority === '' || !hash_equals((string)$order['authority'], $authority)) return [false, 'کدِ پرداخت با این سفارش نمی‌خواند.'];
    $body = json_encode(['merchant_id' => trim((string)setting('zp_merchant')), 'amount' => (int)$order['amount'] * 10, 'authority' => $authority]);
    [$code, $res, $err] = http_req('POST', zp_base() . '/pg/v4/payment/verify.json', $body,
                                   ['Content-Type: application/json', 'Accept: application/json']);
    if ($err !== '') return [false, 'اتصال به درگاه برقرار نشد؛ اگر مبلغ کم شده، تا چند دقیقه‌ی دیگر خودکار تایید یا برگشت داده می‌شود.'];
    $j = json_decode($res, true);
    $c = (int)($j['data']['code'] ?? 0);
    if ($c === 100 || $c === 101) return [true, (string)($j['data']['ref_id'] ?? '')];
    return [false, 'پرداخت تایید نشد.'];
}

function pay_wallet($order, $user) {
    if (!wallet_on()) return [false, 'پرداخت از کیف پول فعال نیست.'];
    if ((int)$order['user_id'] !== (int)$user['id']) return [false, 'این سفارش مالِ حسابِ شما نیست.'];
    if ($order['status'] !== 'pending') return [false, 'این سفارش در انتظارِ پرداخت نیست.'];
    if (!wallet_debit((int)$user['id'], (int)$order['amount'], 'پرداختِ سفارش ' . $order['code'], (int)$order['id']))
        return [false, 'موجودی کیف پول کافی نیست.'];
    if (!order_mark_paid($order, 'wallet', 'WALLET')) {

        wallet_credit((int)$user['id'], (int)$order['amount'], 'برگشتِ کسرِ تکراری ' . $order['code'], (int)$order['id']);
        return [false, 'وضعیتِ سفارش عوض شده بود؛ مبلغ به کیف پول برگشت.'];
    }
    return [true, ''];
}

function receipt_save($order, $file, $note) {
    if (!card_on()) return [false, 'کارت‌به‌کارت فعال نیست.'];
    if (!in_array($order['status'], ['pending', 'review'], true)) return [false, 'این سفارش دیگر رسید نمی‌پذیرد.'];
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return [false, 'تصویرِ رسید را انتخاب کنید.'];
    if ((int)$file['size'] > 5 * 1024 * 1024) return [false, 'حجمِ تصویر باید کمتر از ۵ مگابایت باشد.'];
    $info = @getimagesize($file['tmp_name']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? '';
    if ($ext === '') return [false, 'فقط تصویرِ JPG، PNG یا WEBP قبول می‌شود.'];
    $name = date('Ym') . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
    if (!@move_uploaded_file($file['tmp_name'], NS_STORAGE . '/uploads/' . $name)) return [false, 'ذخیره‌ی رسید ممکن نشد — دوباره امتحان کنید.'];
    if (!empty($order['receipt'])) @unlink(NS_STORAGE . '/uploads/' . basename($order['receipt']));
    order_set($order['id'], ['status' => 'review', 'method' => 'card', 'receipt' => $name,
                             'receipt_note' => mb_substr(trim(ns_digits((string)$note)), 0, 120)]);
    return [true, ''];
}
