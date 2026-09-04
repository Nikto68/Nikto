<?php
/**
 * 🏷 کد تخفیف — برایِ ویزاردِ پرداختِ مینی‌اپِ یکپارچه.
 *
 * ساده و امن نگه داشته شده: درصد یا مبلغِ ثابت، سقفِ تعدادِ کل،
 * سقفِ استفاده‌یِ هر کاربر، حداقلِ مبلغِ سفارش، تاریخِ انقضا.
 * محاسبه‌ی نهاییِ تخفیف همیشه سمتِ سرور انجام می‌شود (در order در
 * miniapps.php) — چیزی که کلاینت می‌فرستد فقط برایِ پیش‌نمایش است.
 */

function cpDbPath() { return DATA_DIR . '/coupons.sqlite'; }

function cpDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3')) return null;

    $path = cpDbPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    try {
        $db = new SQLite3($path);
    } catch (Throwable $e) {
        error_log('[coupons] coupons.sqlite باز نشد: ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec("CREATE TABLE IF NOT EXISTS coupons (
        code TEXT PRIMARY KEY,
        kind TEXT NOT NULL DEFAULT 'percent',
        value REAL NOT NULL DEFAULT 0,
        max_uses INTEGER NOT NULL DEFAULT 0,
        used INTEGER NOT NULL DEFAULT 0,
        per_user_limit INTEGER NOT NULL DEFAULT 1,
        min_total REAL NOT NULL DEFAULT 0,
        max_discount REAL NOT NULL DEFAULT 0,
        expires_at INTEGER NOT NULL DEFAULT 0,
        on_flag INTEGER NOT NULL DEFAULT 1,
        created_at INTEGER NOT NULL DEFAULT 0
    )");
    $db->exec('CREATE TABLE IF NOT EXISTS coupon_redemptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL,
        user_id INTEGER NOT NULL,
        order_id TEXT NOT NULL,
        amount REAL NOT NULL,
        created_at INTEGER NOT NULL
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_cpred_code_user ON coupon_redemptions(code, user_id)');
    return $db;
}

function cpNorm($code) { return strtoupper(trim((string)$code)); }

function cpGet($code) {
    $db = cpDb();
    $code = cpNorm($code);
    if (!$db || $code === '') return null;
    $stmt = $db->prepare('SELECT * FROM coupons WHERE code = :c');
    $stmt->bindValue(':c', $code, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ?: null;
}

/** ساخت/ویرایشِ یک کد — برای مصرفِ ادمین (وب‌پنل/دستور)، نه مسیرِ مشتری. */
function cpUpsert($code, array $fields) {
    $db = cpDb();
    $code = cpNorm($code);
    if (!$db || $code === '') return false;
    $cur = cpGet($code) ?: [
        'code' => $code, 'kind' => 'percent', 'value' => 0, 'max_uses' => 0, 'used' => 0,
        'per_user_limit' => 1, 'min_total' => 0, 'max_discount' => 0, 'expires_at' => 0,
        'on_flag' => 1, 'created_at' => time(),
    ];
    $row = array_merge($cur, $fields);
    $stmt = $db->prepare('INSERT OR REPLACE INTO coupons
        (code, kind, value, max_uses, used, per_user_limit, min_total, max_discount, expires_at, on_flag, created_at)
        VALUES (:code,:kind,:value,:max_uses,:used,:pul,:min_total,:max_discount,:exp,:on,:created)');
    $stmt->bindValue(':code', $code, SQLITE3_TEXT);
    $stmt->bindValue(':kind', (string)$row['kind'], SQLITE3_TEXT);
    $stmt->bindValue(':value', (float)$row['value'], SQLITE3_FLOAT);
    $stmt->bindValue(':max_uses', (int)$row['max_uses'], SQLITE3_INTEGER);
    $stmt->bindValue(':used', (int)$row['used'], SQLITE3_INTEGER);
    $stmt->bindValue(':pul', (int)$row['per_user_limit'], SQLITE3_INTEGER);
    $stmt->bindValue(':min_total', (float)$row['min_total'], SQLITE3_FLOAT);
    $stmt->bindValue(':max_discount', (float)$row['max_discount'], SQLITE3_FLOAT);
    $stmt->bindValue(':exp', (int)$row['expires_at'], SQLITE3_INTEGER);
    $stmt->bindValue(':on', (int)$row['on_flag'], SQLITE3_INTEGER);
    $stmt->bindValue(':created', (int)$row['created_at'], SQLITE3_INTEGER);
    return (bool)$stmt->execute();
}

/**
 * اعتبارسنجیِ کد برای یک کاربر و یک مبلغِ مشخص — بدونِ مصرف کردنش.
 * برگشت: [ok, discountAmount, message, code]
 */
function cpValidate($code, $uid, $subtotal) {
    $c = cpGet($code);
    if (!$c) return [false, 0.0, 'کد تخفیف پیدا نشد.', ''];
    if (empty($c['on_flag'])) return [false, 0.0, 'این کد دیگر فعال نیست.', ''];
    if ((int)$c['expires_at'] > 0 && time() > (int)$c['expires_at']) return [false, 0.0, 'این کد منقضی شده.', ''];
    if ((int)$c['max_uses'] > 0 && (int)$c['used'] >= (int)$c['max_uses']) return [false, 0.0, 'سقفِ استفاده از این کد پر شده.', ''];
    if ((float)$subtotal < (float)$c['min_total']) {
        return [false, 0.0, 'حداقلِ سفارش برای این کد ' . fmtNum((float)$c['min_total']) . ' تومان است.', ''];
    }

    $db = cpDb();
    if ($db && (int)$c['per_user_limit'] > 0) {
        $stmt = $db->prepare('SELECT COUNT(*) AS n FROM coupon_redemptions WHERE code = :c AND user_id = :u');
        $stmt->bindValue(':c', $c['code'], SQLITE3_TEXT);
        $stmt->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ((int)($row['n'] ?? 0) >= (int)$c['per_user_limit']) {
            return [false, 0.0, 'شما قبلا از این کد استفاده کرده‌اید.', ''];
        }
    }

    $discount = $c['kind'] === 'fixed'
        ? (float)$c['value']
        : round((float)$subtotal * ((float)$c['value'] / 100), 0);
    if ((float)$c['max_discount'] > 0) $discount = min($discount, (float)$c['max_discount']);
    $discount = max(0.0, min($discount, (float)$subtotal));

    return [true, $discount, '', $c['code']];
}

/**
 * ثبتِ مصرفِ کد — فقط بعدِ موفقیتِ واقعیِ سفارش صدا زده شود.
 *
 * 🔒 cpValidate() فقط برایِ پیش‌نمایش است (پیش از ساختنِ سفارش) و ضمانتی
 *    نمی‌دهد؛ اجرای واقعیِ سقف‌ها اینجاست، همه داخلِ یک BEGIN IMMEDIATE —
 *    وگرنه صد درخواستِ هم‌زمان همه می‌توانستند used را قبل از پرشدنِ
 *    سقف ببینند و همه رد شوند، یعنی سقفِ کل عملا هیچ اثری نداشت.
 */
function cpRedeem($code, $uid, $orderId, $amount) {
    // 🔁 چند تلاشِ کوتاه: زیرِ بارِ خیلی سنگین (ده‌ها درخواستِ کاملا هم‌زمان
    //    رویِ دقیقا همان کد)، قفلِ BEGIN IMMEDIATE گاهی صف می‌کشد و
    //    عملیات با یک خطایِ گذرا (نه رد شدنِ واقعیِ کد) شکست می‌خورد —
    //    busyTimeout(5000) بیشترِ این‌ها را می‌گیرد، این فقط لایه‌ی دومِ
    //    اطمینان است.
    for ($try = 0; $try < 3; $try++) {
        [$ok, $transient] = cpRedeemOnce($code, $uid, $orderId, $amount);
        if (!$transient) return $ok;
        usleep(random_int(30000, 90000));
    }
    error_log('[coupons] cpRedeem: سه تلاش هم زیرِ قفل شکست خورد — code=' . $code . ' uid=' . $uid);
    return false;
}

/** برمی‌گرداند [نتیجه, آیا_خطایِ_گذرا_بود]. */
function cpRedeemOnce($code, $uid, $orderId, $amount) {
    $db = cpDb();
    $code = cpNorm($code);
    if (!$db || $code === '') return [false, false];

    // 🔎 خودِ SQLite3 استثنا پرتاب نمی‌کند (enableExceptions هیچ‌جای این
    //    پروژه روشن نیست) — زیرِ قفلِ صف‌کشیده، execute() به‌سادگی false
    //    برمی‌گرداند، بعد fetchArray() رویِ false یک Error واقعیِ PHP
    //    می‌شود. همان Error هم این‌جا «گذرا» به‌حساب می‌آید، دقیقا همان
    //    چیزی که واقعا رخ داده.
    if ($db->exec('BEGIN IMMEDIATE') === false) return [false, true];
    try {
        $stmt = $db->prepare('SELECT max_uses, used, per_user_limit FROM coupons WHERE code = :c');
        $stmt->bindValue(':c', $code, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$row) { $db->exec('ROLLBACK'); return [false, false]; }

        if ((int)$row['max_uses'] > 0 && (int)$row['used'] >= (int)$row['max_uses']) {
            $db->exec('ROLLBACK'); return [false, false];
        }
        if ((int)$row['per_user_limit'] > 0) {
            $cstmt = $db->prepare('SELECT COUNT(*) AS n FROM coupon_redemptions WHERE code = :c AND user_id = :u');
            $cstmt->bindValue(':c', $code, SQLITE3_TEXT);
            $cstmt->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
            $crow = $cstmt->execute()->fetchArray(SQLITE3_ASSOC);
            if ((int)($crow['n'] ?? 0) >= (int)$row['per_user_limit']) { $db->exec('ROLLBACK'); return [false, false]; }
        }

        $stmt = $db->prepare('UPDATE coupons SET used = used + 1 WHERE code = :c');
        $stmt->bindValue(':c', $code, SQLITE3_TEXT);
        $stmt->execute();

        $ins = $db->prepare('INSERT INTO coupon_redemptions (code, user_id, order_id, amount, created_at) VALUES (:c,:u,:o,:a,:t)');
        $ins->bindValue(':c', $code, SQLITE3_TEXT);
        $ins->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
        $ins->bindValue(':o', (string)$orderId, SQLITE3_TEXT);
        $ins->bindValue(':a', (float)$amount, SQLITE3_FLOAT);
        $ins->bindValue(':t', time(), SQLITE3_INTEGER);
        $ins->execute();

        $db->exec('COMMIT');
        return [true, false];
    } catch (Throwable $e) {
        @$db->exec('ROLLBACK');
        return [false, true];
    }
}
