<?php
/**
 * نمونه‌ی فایلِ تنظیماتِ محلی.
 *
 * این فایل را کپی کنید به کنارِ همین پوشه با نامِ config.local.php (که در
 * .gitignore است و هرگز به گیت نمی‌رود) و مقادیرِ واقعی را داخلش بگذارید.
 */

define('REPORT_BOT_TOKEN', 'TOKEN_HERE');
define('REPORT_ADMIN_ID', 0);

// اختیاری: مدیرهای بیشتر، با کاما جدا
// define('REPORT_ADMIN_IDS', '111111111,222222222');

// یک رشته‌ی تصادفیِ بلند بسازید، مثلا با:
//   php -r "echo bin2hex(random_bytes(32));"
define('REPORT_WEBHOOK_SECRET', 'CHANGE_ME');
