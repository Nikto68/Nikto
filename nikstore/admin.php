<?php
require __DIR__ . '/app/bootstrap.php';

session_boot();
security_headers();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$a = preg_replace('/[^a-z_]/', '', (string)($_GET['a'] ?? 'dashboard')) ?: 'dashboard';

function admin_secret() {
    $h = (string)setting('admin_hash');
    return $h !== '' ? $h : 'cfg:' . hash('sha256', (string)cfg('admin_password', ''));
}
function admin_ok() {
    return !empty($_SESSION['adm']) && hash_equals((string)$_SESSION['adm'], hash('sha256', admin_secret() . '|' . cfg('secret', '')));
}
function admin_check_pass($p) {
    $h = (string)setting('admin_hash');
    if ($h !== '') return password_verify($p, $h);
    $c = (string)cfg('admin_password', '');
    return $c !== '' && $c !== 'change-me' && hash_equals($c, $p);
}
function aview($name, array $vars = []) {
    $vars['A'] = $GLOBALS['a'];
    $vars['attn'] = (int)val("SELECT COUNT(*) FROM orders WHERE status IN ('review', 'paid', 'failed')");
    view('admin/' . $name, $vars, 'admin/layout');
}

if ($a === 'login') {
    $err = '';
    if (is_post()) {
        csrf_check();
        if (!throttle_ok('adm:' . client_ip(), 8, 900)) $err = 'تلاشِ زیاد؛ ۱۵ دقیقه بعد امتحان کنید.';
        elseif (!admin_check_pass((string)($_POST['pass'] ?? ''))) { usleep(400000); $err = 'رمز اشتباه است.'; }
        else {
            session_regenerate_id(true);
            $_SESSION['adm'] = hash('sha256', admin_secret() . '|' . cfg('secret', ''));
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            throttle_clear('adm:' . client_ip());
            redirect(aurl());
        }
    }
    view('admin/login', ['title' => 'ورود مدیر', 'err' => $err], null);
    exit;
}
if (!admin_ok()) redirect(aurl('login'));
if (is_post()) csrf_check();

switch ($a) {

case 'logout':
    if (is_post()) { unset($_SESSION['adm']); session_regenerate_id(true); }
    redirect(aurl('login'));

case 'dashboard':
    if (is_post() && input('op') === 'seed_ok') { settings_save(['seed_review' => '0']); redirect(aurl()); }
    if (is_post() && input('op') === 'expire') { $n = orders_expire(); flash('ok', fa($n) . ' سفارشِ پرداخت‌نشده‌ی کهنه لغو شد.'); redirect(aurl()); }
    $day = strtotime('today');
    $sum = fn($from) => (int)val("SELECT COALESCE(SUM(amount), 0) FROM orders WHERE kind = 'product' AND paid_at >= ? AND status IN ('paid','processing','completed')", [$from]);
    $stats = [
        'today' => $sum($day), 'week' => $sum($day - 6 * 86400), 'month' => $sum($day - 29 * 86400),
        'orders_today' => (int)val("SELECT COUNT(*) FROM orders WHERE kind = 'product' AND paid_at >= ?", [$day]),
        'users' => (int)val('SELECT COUNT(*) FROM users'),
        'review' => (int)val("SELECT COUNT(*) FROM orders WHERE status = 'review'"),
        'queue' => (int)val("SELECT COUNT(*) FROM orders WHERE status = 'paid'"),
        'proc' => (int)val("SELECT COUNT(*) FROM orders WHERE status = 'processing'"),
        'failed' => (int)val("SELECT COUNT(*) FROM orders WHERE status = 'failed'"),
        'wallets' => (int)val('SELECT COALESCE(SUM(balance), 0) FROM users'),
    ];
    $chart = [];
    for ($i = 13; $i >= 0; $i--) { $d0 = $day - $i * 86400; $chart[] = [$d0, (int)val("SELECT COALESCE(SUM(amount), 0) FROM orders WHERE kind = 'product' AND paid_at >= ? AND paid_at < ? AND status IN ('paid','processing','completed')", [$d0, $d0 + 86400])]; }
    $recent = rows('SELECT * FROM orders ORDER BY id DESC LIMIT 8');
    aview('dashboard', ['title' => 'داشبورد', 'S' => $stats, 'chart' => $chart, 'recent' => $recent]);
    break;

case 'orders':
    $st = (string)($_GET['st'] ?? '');
    $qq = trim((string)($_GET['q'] ?? ''));
    $pg = max(1, (int)($_GET['pg'] ?? 1));
    $w = []; $p = [];
    if ($st === 'attn') $w[] = "status IN ('review', 'paid', 'failed')";
    elseif (isset(order_statuses()[$st])) { $w[] = 'status = ?'; $p[] = $st; }
    if ($qq !== '') { $w[] = '(code LIKE ? OR target LIKE ? OR contact LIKE ? OR title LIKE ?)'; $like = '%' . ns_digits($qq) . '%'; array_push($p, $like, $like, $like, $like); }
    $where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
    $total = (int)val('SELECT COUNT(*) FROM orders' . $where, $p);
    $list = rows('SELECT * FROM orders' . $where . ' ORDER BY id DESC LIMIT 30 OFFSET ' . (($pg - 1) * 30), $p);
    aview('orders', ['title' => 'سفارش‌ها', 'list' => $list, 'st' => $st, 'qq' => $qq, 'pg' => $pg, 'pages' => max(1, (int)ceil($total / 30)), 'total' => $total]);
    break;

case 'order':
    $o = order_get((int)($_GET['id'] ?? 0));
    if (!$o) { flash('err', 'سفارش پیدا نشد.'); redirect(aurl('orders')); }
    if (is_post()) {
        $op = input('op');
        if ($op === 'approve' && $o['status'] === 'review') { order_mark_paid($o, 'card', 'CARD'); flash('ok', 'رسید تایید شد.'); }
        if ($op === 'reject' && $o['status'] === 'review') { order_set($o['id'], ['status' => 'pending', 'delivery' => 'رسید تایید نشد: ' . mb_substr(input('why') ?: 'رسید نامعتبر بود', 0, 200)]); flash('ok', 'رسید رد شد.'); }
        if ($op === 'save') { order_set($o['id'], ['delivery' => mb_substr(input('delivery'), 0, 2000), 'admin_note' => mb_substr(input('admin_note'), 0, 2000)]); flash('ok', 'ذخیره شد.'); }
        if ($op === 'processing' && in_array($o['status'], ['paid', 'failed'], true)) { order_set($o['id'], ['status' => 'processing']); flash('ok', 'در حالِ انجام.'); }
        if ($op === 'complete' && in_array($o['status'], ['paid', 'processing', 'failed'], true)) { order_set($o['id'], ['status' => 'completed', 'delivery' => input('delivery') !== '' ? mb_substr(input('delivery'), 0, 2000) : ($o['delivery'] ?: 'سفارش انجام شد.')]); flash('ok', 'تکمیل شد.'); }
        if ($op === 'cancel' && in_array($o['status'], ['pending', 'review'], true)) { order_set($o['id'], ['status' => 'canceled']); flash('ok', 'لغو شد.'); }
        if ($op === 'refund') {
            $ok = order_refund_wallet($o, input('why'));
            flash($ok ? 'ok' : 'err', $ok ? 'مبلغ به کیف پولِ کاربر برگشت.'
                : ((int)$o['user_id'] <= 0 ? 'خریدار حساب ندارد؛ وجه را دستی برگردانید و «ثبتِ برگشتِ دستی» را بزنید.' : 'برگشت ممکن نبود؛ قبلا برگشت داده شده یا وضعیتِ سفارش اجازه نمی‌دهد.'));
        }
        if ($op === 'manual_refund' && in_array($o['status'], ['paid', 'processing', 'failed'], true)) { order_set($o['id'], ['status' => 'refunded', 'admin_note' => trim($o['admin_note'] . "\nبرگشتِ وجهِ دستی: " . input('why'))]); flash('ok', 'به‌عنوانِ «برگشت داده شد» ثبت شد.'); }
        if ($op === 'dispatch' && $o['status'] === 'paid') { provider_dispatch($o); flash('ok', 'ارسال به سرویس‌دهنده انجام شد — نتیجه را ببینید.'); }
        if ($op === 'refresh') { provider_refresh($o, true); flash('ok', 'وضعیت از سرویس‌دهنده پرسیده شد.'); }
        redirect(aurl('order', ['id' => $o['id']]));
    }
    $u = $o['user_id'] ? row('SELECT * FROM users WHERE id = ?', [(int)$o['user_id']]) : null;
    aview('order', ['title' => 'سفارش ' . $o['code'], 'o' => $o, 'u' => $u, 'p' => $o['product_id'] ? product($o['product_id'], false) : null]);
    break;

case 'receipt':
    $o = order_get((int)($_GET['id'] ?? 0));
    $f = $o && $o['receipt'] ? NS_STORAGE . '/uploads/' . basename($o['receipt']) : '';
    if ($f === '' || !is_file($f)) { http_response_code(404); exit('not found'); }
    $mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][strtolower(pathinfo($f, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($f));
    header('Cache-Control: private, max-age=600');
    readfile($f);
    exit;

case 'products':
    if (is_post()) {
        $id = (int)input('id');
        if (input('op') === 'toggle') { q('UPDATE products SET active = 1 - active WHERE id = ?', [$id]); }
        if (input('op') === 'delete') {
            if (val('SELECT 1 FROM orders WHERE product_id = ?', [$id])) { q('UPDATE products SET active = 0 WHERE id = ?', [$id]); flash('ok', 'این محصول سفارش دارد؛ به‌جای حذف، غیرفعال شد.'); }
            else { q('DELETE FROM products WHERE id = ?', [$id]); flash('ok', 'حذف شد.'); }
        }
        redirect(aurl('products', ['cat' => (int)($_GET['cat'] ?? 0)]));
    }
    $cats = categories(false);
    $cf = (int)($_GET['cat'] ?? 0);
    $list = [];
    foreach ($cats as $c) if (!$cf || (int)$c['id'] === $cf) $list[] = $c + ['items' => products($c['id'], false)];
    aview('products', ['title' => 'محصولات', 'cats' => $cats, 'list' => $list, 'cf' => $cf]);
    break;

case 'product':
    $id = (int)($_GET['id'] ?? 0);
    $p = $id ? row('SELECT * FROM products WHERE id = ?', [$id]) : null;
    $cats = categories(false);
    $err = '';
    $d = $p ?: ['cat_id' => (int)($_GET['cat'] ?? ($cats[0]['id'] ?? 0)), 'title' => '', 'descr' => '', 'emoji' => '', 'badge' => '', 'pricing' => 'fixed', 'price' => 0, 'per' => 1,
                'unit' => '', 'qmin' => 1, 'qmax' => 1, 'qstep' => 1, 'field' => 'tg_user', 'provider' => 'manual', 'provider_ref' => '', 'sort' => 0, 'active' => 1];
    if (is_post()) {
        $n = fn($k) => (int)ns_digits(str_replace([',', '٬'], '', input($k, '0')));
        $d = [
            'cat_id' => $n('cat_id'), 'title' => mb_substr(input('title'), 0, 120), 'descr' => mb_substr(input('descr'), 0, 400),
            'emoji' => mb_substr(input('emoji'), 0, 8), 'badge' => mb_substr(input('badge'), 0, 30),
            'pricing' => input('pricing') === 'unit' ? 'unit' : 'fixed', 'price' => $n('price'), 'per' => max(1, $n('per')),
            'unit' => mb_substr(input('unit'), 0, 30), 'qmin' => max(1, $n('qmin')), 'qmax' => max(1, $n('qmax')), 'qstep' => max(1, $n('qstep')),
            'field' => array_key_exists(input('field'), field_types()) ? input('field') : 'none',
            'provider' => in_array(input('provider'), ['manual', 'smm', '5sim'], true) ? input('provider') : 'manual',
            'provider_ref' => mb_substr(input('provider_ref'), 0, 120), 'sort' => $n('sort'), 'active' => input('active') === '1' ? 1 : 0,
        ];
        if ($d['title'] === '') $err = 'عنوان را بنویسید.';
        elseif (!category($d['cat_id'])) $err = 'دسته را انتخاب کنید.';
        elseif ($d['price'] < 0) $err = 'قیمت نامعتبر است.';
        elseif ($d['pricing'] === 'unit' && $d['qmax'] < $d['qmin']) $err = 'حداکثر نباید از حداقل کمتر باشد.';
        else {
            if ($p) update('products', $d, 'id = ?', [$p['id']]);
            else { $d['created'] = time(); $id = insert('products', $d); }
            flash('ok', 'محصول ذخیره شد.');
            redirect(aurl('products', ['cat' => $d['cat_id']]));
        }
    }
    aview('product', ['title' => $p ? 'ویرایشِ محصول' : 'محصولِ تازه', 'd' => $d, 'p' => $p, 'cats' => $cats, 'err' => $err]);
    break;

case 'cats':
    if (is_post()) {
        if (input('op') === 'save') {
            foreach ((array)($_POST['c'] ?? []) as $cid => $c) {
                if (!is_array($c)) continue;
                update('categories', ['title' => mb_substr(trim((string)($c['title'] ?? '')), 0, 80) ?: 'بی‌نام', 'subtitle' => mb_substr(trim((string)($c['subtitle'] ?? '')), 0, 200),
                                      'sort' => (int)ns_digits((string)($c['sort'] ?? 0)), 'active' => !empty($c['active']) ? 1 : 0], 'id = ?', [(int)$cid]);
            }
            flash('ok', 'دسته‌ها ذخیره شد.');
        }
        if (input('op') === 'add') {
            $slug = strtolower(preg_replace('/[^a-z0-9\-]/i', '', input('slug')));
            if ($slug === '' || category($slug)) flash('err', 'نامکِ لاتینِ یکتا لازم است (مثلا youtube).');
            else { insert('categories', ['slug' => $slug, 'kind' => 'other', 'title' => mb_substr(input('title') ?: $slug, 0, 80), 'subtitle' => '', 'icon' => 'bag', 'sort' => 99, 'active' => 1]); flash('ok', 'دسته اضافه شد.'); }
        }
        redirect(aurl('cats'));
    }
    aview('cats', ['title' => 'دسته‌ها', 'cats' => categories(false)]);
    break;

case 'users':
    $qq = trim((string)($_GET['q'] ?? ''));
    $list = $qq !== '' ? rows('SELECT * FROM users WHERE name LIKE ? OR mobile LIKE ? ORDER BY id DESC LIMIT 100', ['%' . $qq . '%', '%' . ns_digits($qq) . '%'])
                       : rows('SELECT * FROM users ORDER BY id DESC LIMIT 100');
    aview('users', ['title' => 'کاربران', 'list' => $list, 'qq' => $qq]);
    break;

case 'user':
    $u = row('SELECT * FROM users WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
    if (!$u) { flash('err', 'کاربر پیدا نشد.'); redirect(aurl('users')); }
    if (is_post()) {
        $op = input('op');
        if ($op === 'block') { update('users', ['blocked' => (int)$u['blocked'] ? 0 : 1], 'id = ?', [$u['id']]); flash('ok', (int)$u['blocked'] ? 'رفعِ مسدودی شد.' : 'مسدود شد.'); }
        if ($op === 'balance') {
            $amt = (int)ns_digits(str_replace([',', '٬'], '', input('amount')));
            if (input('dir') === 'sub') $amt = -$amt;
            if ($amt === 0) flash('err', 'مبلغ را وارد کنید.');
            elseif ($amt < 0 && !wallet_debit((int)$u['id'], -$amt, 'کسرِ مدیر: ' . input('why'))) flash('err', 'موجودی کافی نیست.');
            else { if ($amt > 0) wallet_credit((int)$u['id'], $amt, 'افزایشِ مدیر: ' . input('why')); flash('ok', 'موجودی به‌روز شد.'); }
        }
        if ($op === 'pass') {
            $np = (string)($_POST['np'] ?? '');
            if (mb_strlen($np) < 6) flash('err', 'رمز حداقل ۶ نویسه.');
            else { update('users', ['pass' => password_hash($np, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]); flash('ok', 'رمزِ کاربر عوض شد.'); }
        }
        redirect(aurl('user', ['id' => $u['id']]));
    }
    aview('user', ['title' => $u['name'], 'u' => $u,
        'orders' => rows('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 30', [(int)$u['id']]),
        'txs' => rows('SELECT * FROM wallet_tx WHERE user_id = ? ORDER BY id DESC LIMIT 30', [(int)$u['id']])]);
    break;

case 'settings':
    $err = '';
    if (is_post()) {
        $keys = ['site_name', 'tagline', 'notice', 'support', 'channel', 'contact_text', 'card_number', 'card_holder', 'card_bank', 'zp_merchant',
                 'topup_min', 'smm_url', 'smm_key', 'fivesim_key', 'pending_hours', 'terms', 'about'];
        $save = [];
        foreach ($keys as $k) $save[$k] = mb_substr(trim((string)($_POST[$k] ?? '')), 0, $k === 'terms' || $k === 'about' || $k === 'contact_text' ? 5000 : 300);
        foreach (['card_on', 'zp_on', 'zp_sandbox', 'wallet_on', 'trust_proxy'] as $k) $save[$k] = !empty($_POST[$k]) ? '1' : '0';
        $save['support'] = ltrim($save['support'], '@');
        $save['channel'] = ltrim($save['channel'], '@');
        $save['topup_min'] = (string)max(1000, (int)ns_digits($save['topup_min']));
        $save['pending_hours'] = (string)max(1, (int)ns_digits($save['pending_hours']));
        if ($save['smm_url'] !== '' && !preg_match('#^https://#i', $save['smm_url'])) $err = 'آدرسِ API پنلِ SMM باید با https:// شروع شود.';
        $np = (string)($_POST['new_pass'] ?? '');
        if ($err === '' && $np !== '') {
            if (mb_strlen($np) < 8) $err = 'رمزِ تازه‌ی مدیر باید حداقل ۸ نویسه باشد.';
            else $save['admin_hash'] = password_hash($np, PASSWORD_DEFAULT);
        }
        if ($err === '') {
            settings_save($save);
            if (isset($save['admin_hash'])) $_SESSION['adm'] = hash('sha256', admin_secret() . '|' . cfg('secret', ''));
            flash('ok', 'تنظیمات ذخیره شد.');
            redirect(aurl('settings'));
        }
    }
    aview('settings', ['title' => 'تنظیمات', 'err' => $err, 'cron' => base_url() . '/cron.php?key=' . cron_key()]);
    break;

default:
    redirect(aurl());
}
