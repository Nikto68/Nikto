<?php
require __DIR__ . '/app/bootstrap.php';

session_boot();
security_headers();
header('Content-Type: text/html; charset=utf-8');

$page = preg_replace('/[^a-z_]/', '', (string)($_GET['p'] ?? 'home')) ?: 'home';
$me = user_current();

function remember_order($code) {
    $_SESSION['my_orders'] = array_slice(array_unique(array_merge([(string)$code], (array)($_SESSION['my_orders'] ?? []))), 0, 50);
}

function can_view_order($o, $me) {
    if (!$o) return false;
    if ((int)$o['user_id'] > 0) return $me && (int)$me['id'] === (int)$o['user_id'];
    return in_array($o['code'], (array)($_SESSION['my_orders'] ?? []), true);
}

function pay_methods($me) {
    $m = [];
    if ($me && wallet_on()) $m['wallet'] = 'کیف پول';
    if (zp_on()) $m['zarinpal'] = 'درگاه آنلاین';
    if (card_on()) $m['card'] = 'کارت به کارت';
    return $m;
}

function start_payment($o, $method, $me) {
    if ($method === 'wallet') {
        if (!$me) { flash('err', 'برای پرداخت از کیف پول وارد حسابتان شوید.'); redirect(url('login')); }
        [$ok, $err] = pay_wallet($o, $me);
        flash($ok ? 'ok' : 'err', $ok ? 'پرداخت از کیف پول انجام شد.' : $err);
        redirect(url('order', ['code' => $o['code']]));
    }
    if ($method === 'zarinpal') {
        [$ok, $res] = zp_start($o);
        if ($ok) redirect($res);
        flash('err', $res);
        redirect(url('order', ['code' => $o['code']]));
    }
    order_set($o['id'], ['method' => 'card']);
    redirect(url('order', ['code' => $o['code']]));
}

switch ($page) {

case 'home':
    $cats = categories();
    $byKind = [];
    foreach ($cats as $c) $byKind[$c['kind']] = $c + ['items' => products($c['id']), 'from' => category_from_price($c['id'])];
    view('home', ['title' => '', 'cats' => $cats, 'K' => $byKind, 'me' => $me]);
    break;

case 'cat':
    $c = category((string)($_GET['slug'] ?? ''));
    if (!$c || !(int)$c['active']) { http_response_code(404); view('404', ['title' => 'پیدا نشد', 'me' => $me]); break; }
    view('cat', ['title' => $c['title'], 'c' => $c, 'items' => products($c['id']), 'me' => $me]);
    break;

case 'buy':
    $p = product((int)($_GET['id'] ?? 0));
    if (!$p) { http_response_code(404); view('404', ['title' => 'پیدا نشد', 'me' => $me]); break; }
    $methods = pay_methods($me);
    $errors = [];
    $old = ['qty' => (string)($_GET['qty'] ?? $p['qmin']), 'target' => '', 'note' => '', 'mobile' => '', 'name' => '', 'method' => array_key_first($methods) ?? ''];
    if (is_post()) {
        csrf_check();
        foreach ($old as $k => $v) $old[$k] = input($k, $v);
        if (!throttle_ok('buy:' . client_ip(), 30, 600)) $errors[] = 'تعدادِ سفارش‌ها زیاد است؛ چند دقیقه بعد امتحان کنید.';
        $qty = qty_clean($p, $old['qty'], $e1); if ($e1) $errors[] = $e1;
        $target = field_clean($p['field'], $old['target'], $e2); if ($e2) $errors[] = $e2;
        $contact = $me ? $me['mobile'] : norm_mobile($old['mobile']);
        if ($contact === '') $errors[] = 'شماره موبایل معتبر وارد کنید (مثلا ۰۹۱۲۳۴۵۶۷۸۹).';
        if (!isset($methods[$old['method']])) $errors[] = 'روشِ پرداخت را انتخاب کنید.';
        $amount = product_total($p, $qty ?: 1);
        if ($amount <= 0) $errors[] = 'قیمتِ این خدمت هنوز تعیین نشده؛ با پشتیبانی تماس بگیرید.';
        elseif ($old['method'] === 'wallet' && $me && (int)$me['balance'] < $amount)
            $errors[] = 'موجودیِ کیف پول (' . toman($me['balance']) . ') کافی نیست؛ روشِ دیگری انتخاب کنید یا از «حساب من» کیف پول را شارژ کنید.';
        if (!$errors) {
            $o = order_create([
                'user_id' => $me ? (int)$me['id'] : null, 'product_id' => (int)$p['id'], 'kind' => 'product',
                'title' => $p['title'], 'qty' => $qty, 'amount' => $amount, 'target' => $target,
                'note' => mb_substr($old['note'], 0, 500), 'name' => mb_substr($me ? $me['name'] : $old['name'], 0, 80),
                'contact' => $contact, 'method' => $old['method'],
            ]);
            remember_order($o['code']);
            start_payment($o, $old['method'], $me);
        }
    }
    view('buy', ['title' => 'خرید ' . $p['title'], 'p' => $p, 'methods' => $methods, 'errors' => $errors, 'old' => $old, 'me' => $me]);
    break;

case 'order':
    $o = order_by_code((string)($_GET['code'] ?? ''));
    if (!can_view_order($o, $me)) {
        if ($o && (int)$o['user_id'] > 0 && !$me) { flash('err', 'این سفارش مالِ یک حساب است؛ اول وارد شوید.'); redirect(url('login', ['next' => 'order:' . $o['code']])); }
        flash('err', 'سفارش پیدا نشد. کدِ سفارش و شماره موبایل را وارد کنید.');
        redirect(url('track'));
    }
    if (is_post()) {
        csrf_check();
        $op = input('op');
        if ($op === 'pay' && $o['status'] === 'pending') start_payment($o, input('method'), $me);
        if ($op === 'receipt') {
            if (!throttle_ok('rcpt:' . $o['id'], 10, 3600)) flash('err', 'تعدادِ آپلود زیاد است؛ کمی بعد امتحان کنید.');
            else { [$ok, $err] = receipt_save($o, $_FILES['receipt'] ?? null, input('rnote')); flash($ok ? 'ok' : 'err', $ok ? 'رسید ثبت شد؛ بعد از بررسی، سفارش انجام می‌شود.' : $err); }
        }
        if ($op === 'cancel' && $o['status'] === 'pending') { order_set($o['id'], ['status' => 'canceled']); flash('ok', 'سفارش لغو شد.'); }
        if ($op === 'numcancel') { [$ok, $err] = number_cancel($o); flash($ok ? 'ok' : 'err', $ok ? 'شماره لغو شد.' : $err); }
        redirect(url('order', ['code' => $o['code']]));
    }
    $o = provider_refresh($o);
    view('order', ['title' => 'سفارش ' . $o['code'], 'o' => $o, 'p' => $o['product_id'] ? product($o['product_id'], false) : null,
                   'methods' => pay_methods($me), 'me' => $me]);
    break;

case 'state':
    $o = order_by_code((string)($_GET['code'] ?? ''));
    if (!can_view_order($o, $me)) json_out(['ok' => false], 404);
    $o = provider_refresh($o);
    $st = provider_state($o);
    json_out(['ok' => true, 'status' => $o['status'], 'label' => status_label($o['status']), 'cls' => status_cls($o['status']),
              'delivery' => (string)$o['delivery'], 'phone' => (string)($st['phone'] ?? ''), 'code' => (string)($st['code'] ?? '')]);

case 'zp':
    $o = order_by_code((string)($_GET['code'] ?? ''));
    if (!$o) redirect('./');
    remember_order($o['code']);
    if ($o['status'] === 'pending' && $o['method'] === 'zarinpal') {
        if (($_GET['Status'] ?? '') !== 'OK') flash('err', 'پرداخت انجام نشد یا لغو شد. دوباره امتحان کنید.');
        else {
            [$ok, $ref] = zp_verify($o, (string)($_GET['Authority'] ?? ''));
            if ($ok && order_mark_paid($o, 'zarinpal', $ref)) flash('ok', 'پرداخت با موفقیت انجام شد. کد پیگیری: ' . fa_digits($ref));
            elseif (!$ok) flash('err', $ref);
        }
    }
    redirect(url('order', ['code' => $o['code']]));

case 'track':
    $err = '';
    if (is_post()) {
        csrf_check();
        if (!throttle_ok('track:' . client_ip(), 20, 600)) $err = 'تعدادِ تلاش زیاد است؛ کمی بعد امتحان کنید.';
        else {
            $o = order_by_code(input('code'));
            $m = norm_mobile(input('mobile'));
            if ($o && (int)$o['user_id'] > 0) { flash('err', 'این سفارش با حساب ثبت شده؛ وارد حسابتان شوید.'); redirect(url('login', ['next' => 'order:' . $o['code']])); }
            if ($o && $m !== '' && hash_equals((string)$o['contact'], $m)) { remember_order($o['code']); redirect(url('order', ['code' => $o['code']])); }
            $err = 'سفارشی با این کد و شماره پیدا نشد.';
        }
    }
    view('track', ['title' => 'پیگیری سفارش', 'err' => $err, 'me' => $me]);
    break;

case 'login':
case 'register':
    if ($me) redirect(url('account'));
    $err = ''; $old = ['name' => input('name'), 'mobile' => input('mobile')];
    $next = preg_replace('/[^A-Za-z0-9:]/', '', input('next'));
    if (is_post()) {
        csrf_check();
        $mobile = norm_mobile(input('mobile'));
        $pass = (string)($_POST['pass'] ?? '');
        if (!throttle_ok('auth:' . client_ip(), 15, 900)) $err = 'تعدادِ تلاش زیاد است؛ ۱۵ دقیقه بعد امتحان کنید.';
        elseif ($mobile === '') $err = 'شماره موبایل معتبر نیست.';
        elseif ($page === 'register') {
            $name = mb_substr(trim(input('name')), 0, 60);
            if (mb_strlen($name) < 2) $err = 'نامتان را وارد کنید.';
            elseif (mb_strlen($pass) < 6) $err = 'رمز باید حداقل ۶ نویسه باشد.';
            elseif ($pass !== (string)($_POST['pass2'] ?? '')) $err = 'تکرارِ رمز یکی نیست.';
            elseif (val('SELECT 1 FROM users WHERE mobile = ?', [$mobile])) $err = 'با این شماره قبلا ثبت‌نام شده؛ وارد شوید.';
            else {
                $id = insert('users', ['name' => $name, 'mobile' => $mobile, 'pass' => password_hash($pass, PASSWORD_DEFAULT), 'created' => time()]);
                user_login_as(row('SELECT * FROM users WHERE id = ?', [$id]));
                flash('ok', 'خوش آمدید، ' . $name . '!');
            }
        } else {
            $u = row('SELECT * FROM users WHERE mobile = ?', [$mobile]);
            if (!$u || !password_verify($pass, $u['pass'])) $err = 'شماره یا رمز اشتباه است.';
            elseif ((int)$u['blocked']) $err = 'دسترسیِ این حساب مسدود است.';
            else { user_login_as($u); throttle_clear('auth:' . client_ip()); }
        }
        if ($err === '') {
            if (str_starts_with($next, 'order:')) redirect(url('order', ['code' => substr($next, 6)]));
            redirect(url('account'));
        }
    }
    view('auth', ['title' => $page === 'register' ? 'ثبت‌نام' : 'ورود', 'mode' => $page, 'err' => $err, 'old' => $old, 'next' => $next, 'me' => $me]);
    break;

case 'logout':
    if (is_post()) { csrf_check(); user_logout(); flash('ok', 'از حسابتان خارج شدید.'); }
    redirect('./');

case 'account':
    if (!$me) redirect(url('login'));
    $err = '';
    if (is_post()) {
        csrf_check();
        $op = input('op');
        if ($op === 'topup') {
            $amt = (int)ns_digits(str_replace([',', '٬'], '', input('amount')));
            $min = max(1000, (int)setting('topup_min', '50000'));
            $methods = array_diff_key(pay_methods($me), ['wallet' => 1]);
            $method = input('method');
            if (!wallet_on()) $err = 'کیف پول فعال نیست.';
            elseif ($amt < $min) $err = 'حداقلِ شارژ ' . toman($min) . ' است.';
            elseif ($amt > 200000000) $err = 'مبلغ خیلی زیاد است.';
            elseif (!isset($methods[$method])) $err = 'روشِ پرداخت را انتخاب کنید.';
            else {
                $o = order_create(['user_id' => (int)$me['id'], 'kind' => 'topup', 'title' => 'شارژ کیف پول', 'qty' => 1, 'amount' => $amt,
                                   'name' => $me['name'], 'contact' => $me['mobile'], 'method' => $method]);
                start_payment($o, $method, $me);
            }
        }
        if ($op === 'password') {
            $cur = (string)($_POST['cur'] ?? ''); $new = (string)($_POST['new'] ?? '');
            if (!password_verify($cur, $me['pass'])) $err = 'رمزِ فعلی اشتباه است.';
            elseif (mb_strlen($new) < 6) $err = 'رمزِ تازه باید حداقل ۶ نویسه باشد.';
            else {
                update('users', ['pass' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [(int)$me['id']]);
                user_login_as(row('SELECT * FROM users WHERE id = ?', [(int)$me['id']]));
                flash('ok', 'رمز عوض شد.'); redirect(url('account'));
            }
        }
    }
    view('account', ['title' => 'حساب من', 'me' => $me, 'err' => $err,
        'orders' => rows('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 50', [(int)$me['id']]),
        'txs' => rows('SELECT * FROM wallet_tx WHERE user_id = ? ORDER BY id DESC LIMIT 30', [(int)$me['id']]),
        'methods' => array_diff_key(pay_methods($me), ['wallet' => 1])]);
    break;

case 'page':
    $k = (string)($_GET['k'] ?? '');
    $pages = ['terms' => 'قوانین و مقررات', 'about' => 'درباره ما', 'contact' => 'تماس با ما'];
    if (!isset($pages[$k])) { http_response_code(404); view('404', ['title' => 'پیدا نشد', 'me' => $me]); break; }
    view('page', ['title' => $pages[$k], 'k' => $k, 'me' => $me]);
    break;

default:
    http_response_code(404);
    view('404', ['title' => 'پیدا نشد', 'me' => $me]);
}
