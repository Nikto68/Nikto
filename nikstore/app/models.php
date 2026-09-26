<?php
function categories($activeOnly = true) {
    return rows('SELECT * FROM categories' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY sort, id');
}
function category($slugOrId) {
    return is_numeric($slugOrId)
        ? row('SELECT * FROM categories WHERE id = ?', [(int)$slugOrId])
        : row('SELECT * FROM categories WHERE slug = ?', [(string)$slugOrId]);
}
function products($catId, $activeOnly = true) {
    return rows('SELECT * FROM products WHERE cat_id = ?' . ($activeOnly ? ' AND active = 1' : '') . ' ORDER BY sort, id', [(int)$catId]);
}
function product($id, $activeOnly = true) {
    $p = row('SELECT p.*, c.slug AS cat_slug, c.kind AS cat_kind, c.title AS cat_title, c.active AS cat_active
              FROM products p JOIN categories c ON c.id = p.cat_id WHERE p.id = ?', [(int)$id]);
    if ($p && $activeOnly && (!(int)$p['active'] || !(int)$p['cat_active'])) return null;
    return $p;
}

function category_from_price($catId) {
    $min = 0;
    foreach (products($catId) as $p) {
        $v = $p['pricing'] === 'unit' ? product_total($p, max((int)$p['qmin'], 1)) : (int)$p['price'];
        if ($v > 0 && ($min === 0 || $v < $min)) $min = $v;
    }
    return $min;
}

function product_total($p, $qty) {
    if ($p['pricing'] === 'unit') return (int)ceil((int)$p['price'] * (float)$qty / max(1, (int)$p['per']));
    return (int)$p['price'];
}

function product_price_label($p) {
    if ((int)$p['price'] <= 0) return 'استعلام قیمت';
    if ($p['pricing'] !== 'unit') return toman($p['price']);
    return 'هر ' . fa($p['per']) . ' ' . ($p['unit'] ?: 'عدد') . ' ' . toman($p['price']);
}

function field_meta($field) {
    return [
        'tg_user' => ['label' => 'آیدی تلگرام گیرنده', 'ph' => '@username', 'hint' => 'آیدیِ عمومیِ تلگرام. رمز یا کدِ ورود لازم نیست — هرگز به کسی ندهید.'],
        'tg_link' => ['label' => 'لینک کانال یا پست تلگرام', 'ph' => 'https://t.me/…', 'hint' => 'کانال یا گروه باید عمومی باشد.'],
        'ig_link' => ['label' => 'لینک پیج یا پست اینستاگرام', 'ph' => 'https://instagram.com/…', 'hint' => 'پیج باید عمومی (Public) باشد. رمزِ اینستاگرام هرگز لازم نیست.'],
        'link'    => ['label' => 'لینک', 'ph' => 'https://…', 'hint' => ''],
        'text'    => ['label' => 'مشخصاتِ سفارش', 'ph' => 'توضیح دهید…', 'hint' => ''],
    ][$field] ?? null;
}
function field_types() {
    return ['none' => '— هیچ (فقط پرداخت)', 'tg_user' => 'آیدی تلگرام', 'tg_link' => 'لینک تلگرام',
            'ig_link' => 'لینک/آیدی اینستاگرام', 'link' => 'لینک دلخواه', 'text' => 'متن دلخواه'];
}

function field_clean($field, $v, &$err) {
    $v = trim(ns_digits((string)$v));
    $err = '';
    switch ($field) {
        case 'none': return '';
        case 'tg_user':
            $u = ltrim(preg_replace('#^(https?://)?(t\.me|telegram\.me)/#i', '', $v), '@');
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{3,31}$/', $u)) { $err = 'آیدی تلگرام معتبر نیست (مثلا @username).'; return ''; }
            return '@' . $u;
        case 'tg_link':
            if (preg_match('/^@([A-Za-z][A-Za-z0-9_]{3,31})$/', $v, $m)) return 'https://t.me/' . $m[1];
            if (preg_match('#^(https?://)?(t\.me|telegram\.me)/([A-Za-z0-9_+/\-?=&%.]{2,250})$#i', $v, $m)) return 'https://t.me/' . $m[3];
            $err = 'لینک تلگرام معتبر نیست (مثلا https://t.me/channel).'; return '';
        case 'ig_link':
            if (preg_match('/^@?([A-Za-z0-9._]{2,30})$/', $v, $m)) return 'https://instagram.com/' . $m[1];
            if (preg_match('#^(https?://)?(www\.)?(instagram\.com|instagr\.am)/([^\s]{1,250})$#i', $v, $m)) return 'https://instagram.com/' . $m[4];
            $err = 'لینک یا آیدی اینستاگرام معتبر نیست.'; return '';
        case 'link':
            if (preg_match('#^https?://[^\s<>"]{4,300}$#i', $v)) return $v;
            $err = 'لینک معتبر نیست (باید با https:// شروع شود).'; return '';
        case 'text':
            if (mb_strlen($v) >= 2 && mb_strlen($v) <= 500) return $v;
            $err = 'مشخصاتِ سفارش را بنویسید (حداکثر ۵۰۰ نویسه).'; return '';
    }
    $err = 'نوعِ فیلد ناشناخته است.';
    return '';
}

function qty_clean($p, $qty, &$err) {
    $err = '';
    if ($p['pricing'] !== 'unit') return 1;
    $q = (int)floor((float)ns_digits((string)$qty));
    $mn = max(1, (int)$p['qmin']); $mx = (int)$p['qmax']; $st = max(1, (int)$p['qstep']);
    if ($q < $mn) { $err = 'حداقل تعداد ' . fa($mn) . ' است.'; return 0; }
    if ($mx > 0 && $q > $mx) { $err = 'حداکثر تعداد ' . fa($mx) . ' است.'; return 0; }
    if ($st > 1 && $q % $st !== 0) { $err = 'تعداد باید مضربِ ' . fa($st) . ' باشد.'; return 0; }
    return $q;
}

function order_statuses() {
    return [
        'pending'    => ['در انتظار پرداخت', 'st-wait', 'clock'],
        'review'     => ['در انتظار تایید رسید', 'st-review', 'eye'],
        'paid'       => ['پرداخت شد — در صف انجام', 'st-paid', 'check'],
        'processing' => ['در حال انجام', 'st-proc', 'refresh'],
        'completed'  => ['تکمیل شد', 'st-done', 'check'],
        'canceled'   => ['لغو شد', 'st-off', 'close'],
        'failed'     => ['انجام نشد', 'st-bad', 'alert'],
        'refunded'   => ['مبلغ برگشت داده شد', 'st-off', 'wallet'],
    ];
}
function status_label($s) { return order_statuses()[$s][0] ?? $s; }
function status_cls($s)   { return order_statuses()[$s][1] ?? ''; }

function order_by_code($code) {
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$code));
    return $code === '' ? null : row('SELECT * FROM orders WHERE code = ?', [$code]);
}
function order_get($id) { return row('SELECT * FROM orders WHERE id = ?', [(int)$id]); }

function order_create(array $d) {
    $now = time();
    for ($i = 0; $i < 5; $i++) {
        $code = rand_code(10);
        if (!val('SELECT 1 FROM orders WHERE code = ?', [$code])) break;
    }
    $id = insert('orders', $d + ['code' => $code, 'status' => 'pending', 'created' => $now, 'updated' => $now]);
    return order_get($id);
}

function order_set($id, array $f) {
    $f['updated'] = time();
    update('orders', $f, 'id = ?', [(int)$id]);
    return order_get($id);
}

function order_mark_paid($order, $method, $ref = '') {
    $n = q("UPDATE orders SET status = 'paid', method = ?, ref = ?, paid_at = ?, updated = ? WHERE id = ? AND status IN ('pending', 'review')",
           [$method, (string)$ref, time(), time(), (int)$order['id']])->rowCount();
    if ($n !== 1) return false;
    $o = order_get($order['id']);
    if ($o['kind'] === 'topup') {
        wallet_credit((int)$o['user_id'], (int)$o['amount'], 'شارژ کیف پول', (int)$o['id']);
        order_set($o['id'], ['status' => 'completed', 'delivery' => 'کیف پول شما ' . toman($o['amount']) . ' شارژ شد.']);
        return true;
    }
    provider_dispatch($o);
    return true;
}

function order_refund_wallet($order, $why = '') {
    if ((int)$order['user_id'] <= 0 || $order['kind'] === 'topup') return false;

    $n = q("UPDATE orders SET status = 'refunded', updated = ? WHERE id = ? AND status IN ('paid', 'processing', 'failed') AND paid_at > 0",
           [time(), (int)$order['id']])->rowCount();
    if ($n !== 1) return false;
    wallet_credit((int)$order['user_id'], (int)$order['amount'], 'برگشتِ وجهِ سفارش ' . $order['code'] . ($why !== '' ? ' — ' . $why : ''), (int)$order['id']);
    return true;
}

function orders_expire() {
    $h = max(1, (int)setting('pending_hours', '24'));
    return q("UPDATE orders SET status = 'canceled', updated = ? WHERE status = 'pending' AND created < ?", [time(), time() - $h * 3600])->rowCount();
}

function user_current() {
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    $id = (int)($_SESSION['uid'] ?? 0);
    if ($id > 0) {
        $r = row('SELECT * FROM users WHERE id = ?', [$id]);
        if ($r && !(int)$r['blocked'] && hash_equals((string)($_SESSION['uph'] ?? ''), substr(hash('sha256', $r['pass']), 0, 16))) $u = $r;
        else unset($_SESSION['uid'], $_SESSION['uph']);
    }
    return $u;
}

function user_login_as($u) {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['uph'] = substr(hash('sha256', $u['pass']), 0, 16);
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
    update('users', ['last_login' => time()], 'id = ?', [(int)$u['id']]);
}

function user_logout() {
    unset($_SESSION['uid'], $_SESSION['uph']);
    session_regenerate_id(true);
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function wallet_credit($uid, $amount, $reason, $orderId = null) {
    $amount = (int)$amount;
    if ($uid <= 0 || $amount === 0) return;
    tx(function () use ($uid, $amount, $reason, $orderId) {
        q('UPDATE users SET balance = balance + ? WHERE id = ?', [$amount, $uid]);
        insert('wallet_tx', ['user_id' => $uid, 'amount' => $amount, 'reason' => mb_substr((string)$reason, 0, 180), 'order_id' => $orderId, 'created' => time()]);
    });
}

function wallet_debit($uid, $amount, $reason, $orderId = null) {
    $amount = (int)$amount;
    if ($uid <= 0 || $amount <= 0) return false;
    return tx(function () use ($uid, $amount, $reason, $orderId) {
        $n = q('UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?', [$amount, $uid, $amount])->rowCount();
        if ($n !== 1) return false;
        insert('wallet_tx', ['user_id' => $uid, 'amount' => -$amount, 'reason' => mb_substr((string)$reason, 0, 180), 'order_id' => $orderId, 'created' => time()]);
        return true;
    });
}
