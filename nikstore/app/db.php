<?php
const NS_SCHEMA = 1;

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = (array)cfg('db', []);
    $driver = ($c['driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
    $opt = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try {
        if ($driver === 'mysql') {
            $dsn = 'mysql:host=' . ($c['host'] ?? 'localhost') . ';dbname=' . ($c['name'] ?? '') . ';charset=utf8mb4';
            if (!empty($c['port'])) $dsn .= ';port=' . (int)$c['port'];
            $pdo = new PDO($dsn, (string)($c['user'] ?? ''), (string)($c['pass'] ?? ''), $opt);
        } else {
            if (!in_array('sqlite', PDO::getAvailableDrivers(), true))
                throw new RuntimeException('افزونه‌ی pdo_sqlite روی هاست فعال نیست — از پنل هاست فعالش کنید یا به MySQL بروید.');
            $path = (string)($c['path'] ?? NS_STORAGE . '/nikstore.sqlite');
            $pdo = new PDO('sqlite:' . $path, null, null, $opt);
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    } catch (PDOException $e) {
        throw new RuntimeException('اتصال به دیتابیس برقرار نشد: ' . $e->getMessage());
    }
    $GLOBALS['NS_DRIVER'] = $driver;
    ns_migrate($pdo);
    return $pdo;
}

function db_driver() { db(); return $GLOBALS['NS_DRIVER']; }

function q($sql, array $p = []) {
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st;
}
function row($sql, array $p = []) { $r = q($sql, $p)->fetch(); return $r ?: null; }
function rows($sql, array $p = []) { return q($sql, $p)->fetchAll(); }
function val($sql, array $p = []) { $v = q($sql, $p)->fetchColumn(); return $v === false ? null : $v; }

function insert($table, array $d) {
    $cols = array_keys($d);
    q('INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($d));
    return (int)db()->lastInsertId();
}

function update($table, array $d, $where, array $wp = []) {
    $set = implode(',', array_map(fn($k) => $k . ' = ?', array_keys($d)));
    return q('UPDATE ' . $table . ' SET ' . $set . ' WHERE ' . $where, array_merge(array_values($d), $wp))->rowCount();
}

function tx(callable $fn) {
    $pdo = db();
    $pdo->beginTransaction();
    try { $r = $fn(); $pdo->commit(); return $r; }
    catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function ns_migrate(PDO $pdo) {
    $driver = $GLOBALS['NS_DRIVER'];
    try {
        $v = (int)$pdo->query("SELECT v FROM settings WHERE k = 'schema'")->fetchColumn();
        if ($v >= NS_SCHEMA) return;
    } catch (PDOException $e) {  }

    $map = $driver === 'mysql'
        ? ['{PK}' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', '{S}' => 'VARCHAR(191)', '{T}' => 'TEXT',
           '{I}' => 'INT', '{B}' => 'BIGINT', '{E}' => ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci']
        : ['{PK}' => 'INTEGER PRIMARY KEY AUTOINCREMENT', '{S}' => 'TEXT', '{T}' => 'TEXT',
           '{I}' => 'INTEGER', '{B}' => 'INTEGER', '{E}' => ')'];

    $ddl = [
        "CREATE TABLE IF NOT EXISTS settings (k {S} NOT NULL PRIMARY KEY, v {T} {E}",
        "CREATE TABLE IF NOT EXISTS users (
            id {PK}, name {S} NOT NULL, mobile {S} NOT NULL, pass {S} NOT NULL,
            balance {B} NOT NULL DEFAULT 0, blocked {I} NOT NULL DEFAULT 0,
            created {I} NOT NULL DEFAULT 0, last_login {I} NOT NULL DEFAULT 0 {E}",
        "CREATE UNIQUE INDEX IF NOT EXISTS ux_users_mobile ON users (mobile)",
        "CREATE TABLE IF NOT EXISTS categories (
            id {PK}, slug {S} NOT NULL, kind {S} NOT NULL, title {S} NOT NULL, subtitle {S},
            icon {S}, sort {I} NOT NULL DEFAULT 0, active {I} NOT NULL DEFAULT 1 {E}",
        "CREATE UNIQUE INDEX IF NOT EXISTS ux_cat_slug ON categories (slug)",
        "CREATE TABLE IF NOT EXISTS products (
            id {PK}, cat_id {I} NOT NULL, title {S} NOT NULL, descr {T}, emoji {S}, badge {S},
            pricing {S} NOT NULL DEFAULT 'fixed', price {B} NOT NULL DEFAULT 0, per {I} NOT NULL DEFAULT 1,
            unit {S}, qmin {B} NOT NULL DEFAULT 1, qmax {B} NOT NULL DEFAULT 1, qstep {I} NOT NULL DEFAULT 1,
            field {S} NOT NULL DEFAULT 'none', provider {S} NOT NULL DEFAULT 'manual', provider_ref {S},
            sort {I} NOT NULL DEFAULT 0, active {I} NOT NULL DEFAULT 1, created {I} NOT NULL DEFAULT 0 {E}",
        "CREATE INDEX IF NOT EXISTS ix_prod_cat ON products (cat_id)",
        "CREATE TABLE IF NOT EXISTS orders (
            id {PK}, code {S} NOT NULL, user_id {I}, product_id {I} NOT NULL DEFAULT 0, kind {S} NOT NULL DEFAULT 'product',
            title {S} NOT NULL, qty {B} NOT NULL DEFAULT 1, amount {B} NOT NULL DEFAULT 0,
            target {S}, note {T}, name {S}, contact {S},
            status {S} NOT NULL DEFAULT 'pending', method {S}, authority {S}, ref {S}, receipt {S}, receipt_note {S},
            delivery {T}, provider {S}, provider_id {S}, provider_state {T}, admin_note {T},
            created {I} NOT NULL DEFAULT 0, updated {I} NOT NULL DEFAULT 0, paid_at {I} NOT NULL DEFAULT 0 {E}",
        "CREATE UNIQUE INDEX IF NOT EXISTS ux_orders_code ON orders (code)",
        "CREATE INDEX IF NOT EXISTS ix_orders_status ON orders (status)",
        "CREATE INDEX IF NOT EXISTS ix_orders_user ON orders (user_id)",
        "CREATE TABLE IF NOT EXISTS wallet_tx (
            id {PK}, user_id {I} NOT NULL, amount {B} NOT NULL, reason {S}, order_id {I}, created {I} NOT NULL DEFAULT 0 {E}",
        "CREATE INDEX IF NOT EXISTS ix_wtx_user ON wallet_tx (user_id)",
        "CREATE TABLE IF NOT EXISTS throttle (k {S} NOT NULL PRIMARY KEY, n {I} NOT NULL DEFAULT 0, t {I} NOT NULL DEFAULT 0 {E}",
    ];
    foreach ($ddl as $sql) {
        $sql = strtr($sql, $map);

        if ($driver === 'mysql' && str_starts_with($sql, 'CREATE ') && str_contains($sql, ' INDEX IF NOT EXISTS ')) {
            $sql = str_replace(' INDEX IF NOT EXISTS ', ' INDEX ', $sql);
            try { $pdo->exec($sql); } catch (PDOException $e) { if (($e->errorInfo[1] ?? 0) != 1061) throw $e; }
            continue;
        }
        $pdo->exec($sql);
    }

    $seeded = false;
    try { $seeded = (bool)$pdo->query("SELECT v FROM settings WHERE k = 'seeded'")->fetchColumn(); } catch (PDOException $e) {}
    if (!$seeded) {
        require_once NS_APP . '/seed.php';
        ns_seed($pdo);
    }
    $pdo->prepare("DELETE FROM settings WHERE k = 'schema'")->execute();
    $pdo->prepare("INSERT INTO settings (k, v) VALUES ('schema', ?)")->execute([(string)NS_SCHEMA]);
}

function setting_defaults() {
    return [
        'site_name'     => 'Nik Store',
        'tagline'       => 'فروشگاه خدمات تلگرام و اینستاگرام',
        'notice'        => '',
        'support'       => 'whaleQT',
        'channel'       => 'Kong_Trade',
        'contact_text'  => '',
        'card_on'       => '1',
        'card_number'   => '',
        'card_holder'   => '',
        'card_bank'     => '',
        'zp_on'         => '0',
        'zp_merchant'   => '',
        'zp_sandbox'    => '0',
        'wallet_on'     => '1',
        'topup_min'     => '50000',
        'smm_url'       => '',
        'smm_key'       => '',
        'fivesim_key'   => '',
        'pending_hours' => '24',
        'trust_proxy'   => '0',
        'admin_hash'    => '',
        'terms'         => "• خرید فقط با اطلاعاتِ درست (آیدی یا لینکِ عمومی) انجام می‌شود؛ مسئولیتِ اطلاعاتِ اشتباه با خریدار است.\n• برای هیچ خدمتی رمز یا کدِ ورودِ حساب از شما خواسته نمی‌شود.\n• اگر سفارشی قابلِ انجام نباشد، مبلغ به کیف پول یا حسابِ شما برگردانده می‌شود.\n• سفارش‌های تکمیل‌شده قابلِ بازگشت نیستند.",
        'about'         => 'ما خدماتِ تلگرام (استارز، پریمیوم، گیفت)، شماره مجازی و خدماتِ اینستاگرام را با قیمتِ منصفانه، پرداختِ امن و تحویلِ سریع ارائه می‌دهیم.',
        'seed_review'   => '1',
    ];
}

function setting($k, $default = null) {
    static $cache = null;
    if ($k === null) { $cache = null; return null; }
    if ($cache === null) {
        $cache = setting_defaults();
        foreach (rows('SELECT k, v FROM settings') as $r) $cache[$r['k']] = (string)$r['v'];
    }
    return $cache[$k] ?? $default;
}

function settings_save(array $kv) {
    tx(function () use ($kv) {
        foreach ($kv as $k => $v) {
            q('DELETE FROM settings WHERE k = ?', [$k]);
            q('INSERT INTO settings (k, v) VALUES (?, ?)', [$k, (string)$v]);
        }
    });
    setting(null);
}

function throttle_ok($key, $max, $window) {
    $now = time();
    $k = substr(hash('sha256', $key), 0, 40);
    $r = row('SELECT n, t FROM throttle WHERE k = ?', [$k]);
    if (!$r || $now - (int)$r['t'] > $window) {
        q('DELETE FROM throttle WHERE k = ?', [$k]);
        q('INSERT INTO throttle (k, n, t) VALUES (?, 1, ?)', [$k, $now]);
        if (random_int(1, 100) === 1) q('DELETE FROM throttle WHERE t < ?', [$now - 86400]);
        return true;
    }
    if ((int)$r['n'] >= $max) return false;
    q('UPDATE throttle SET n = n + 1 WHERE k = ?', [$k]);
    return true;
}
function throttle_clear($key) { q('DELETE FROM throttle WHERE k = ?', [substr(hash('sha256', $key), 0, 40)]); }
