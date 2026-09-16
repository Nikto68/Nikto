<?php
/**
 * بوت‌استرپِ ربات گزارشات — پروژه‌ای کاملاً جدا از بقیه‌ی فایل‌های این ریپو
 * (ربات فروشگاه/آپلودر). توکن و آیدیِ مدیر مخصوصِ همین ربات است و هیچ
 * تداخلی با ثابت‌های BOT_TOKEN / ADMIN_ID در فایل‌های دیگر ندارد.
 *
 * دنبالِ تنظیمات در دو جا می‌گردیم، اولی که بود برنده است:
 *   ۱) فایلِ config.local.php کنارِ همین فایل — بیرون از گیت
 *   ۲) متغیرهای محیطیِ سرور
 *
 * هیچ توکنی داخلِ سورس نیست؛ بدونِ تنظیم، ربات عمداً بالا نمی‌آید.
 */

if (is_file(__DIR__ . '/config.local.php')) require_once __DIR__ . '/config.local.php';

if (!defined('REPORT_BOT_TOKEN'))   define('REPORT_BOT_TOKEN', (string)getenv('REPORT_BOT_TOKEN'));
if (!defined('REPORT_ADMIN_ID'))    define('REPORT_ADMIN_ID', (int)getenv('REPORT_ADMIN_ID'));
if (!defined('REPORT_ADMIN_IDS_RAW')) {
    define('REPORT_ADMIN_IDS_RAW', defined('REPORT_ADMIN_IDS') ? REPORT_ADMIN_IDS : (getenv('REPORT_ADMIN_IDS') ?: ''));
}
if (!defined('REPORT_WEBHOOK_SECRET')) define('REPORT_WEBHOOK_SECRET', getenv('REPORT_WEBHOOK_SECRET') ?: '');
if (!defined('REPORT_DATA_DIR'))    define('REPORT_DATA_DIR', getenv('REPORT_DATA_DIR') ?: __DIR__ . '/data');

if (REPORT_BOT_TOKEN === '') {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("REPORT_BOT_TOKEN تنظیم نشده — config.local.php را از روی config.local.example.php بسازید.\n");
}

if (!class_exists('SQLite3')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("افزونه‌ی SQLite3 در PHP این هاست فعال نیست.\n");
}

/** فهرستِ آیدیِ عددیِ همه‌ی مدیرها (اولین‌شان REPORT_ADMIN_ID است) */
function reportAdminIds(): array {
    static $ids = null;
    if ($ids !== null) return $ids;
    $ids = [];
    if (REPORT_ADMIN_ID > 0) $ids[] = (int)REPORT_ADMIN_ID;
    if (REPORT_ADMIN_IDS_RAW !== '') {
        foreach (explode(',', REPORT_ADMIN_IDS_RAW) as $part) {
            $v = (int)trim($part);
            if ($v > 0) $ids[] = $v;
        }
    }
    return array_values(array_unique($ids));
}

function reportIsAdmin($uid): bool {
    return in_array((int)$uid, reportAdminIds(), true);
}

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/alerts.php';
require_once __DIR__ . '/lib/state.php';
require_once __DIR__ . '/lib/entities.php';
require_once __DIR__ . '/lib/telegram.php';
require_once __DIR__ . '/lib/emoji.php';
require_once __DIR__ . '/lib/captions.php';
require_once __DIR__ . '/lib/keyboards.php';
require_once __DIR__ . '/lib/screen.php';
