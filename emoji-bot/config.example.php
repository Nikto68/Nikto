<?php
/**
 * تنظیمات ربات ساخت ایموجی پریمیوم
 *
 * این فایل را به نام config.php کپی کنید و مقادیر را پر کنید:
 *   cp config.example.php config.php
 *
 * هشدار: config.php را هرگز داخل گیت کامیت نکنید (در .gitignore هست).
 */
return [
    // توکن ربات از @BotFather — محرمانه!
    'bot_token' => '',

    // آیدی عددی مدیرها (می‌توانید چند نفر بگذارید)
    'admin_ids' => [6595849261],

    // آدرس HTTPS پوشه public روی هاست، بدون / آخر
    // مثال: https://example.com/emoji-bot/public
    'base_url' => 'https://example.com/emoji-bot/public',

    // یک رشته تصادفی طولانی (حداقل ۳۲ کاراکتر، فقط A-Z a-z 0-9 _ -)
    // برای امنیت وبهوک، لینک setup و لینک cron استفاده می‌شود.
    // مثلاً خروجی این دستور: php -r "echo bin2hex(random_bytes(24));"
    'secret' => '',

    'db' => [
        // sqlite (بدون نیاز به تنظیم) یا mysql
        'driver' => 'sqlite',
        'sqlite_path' => __DIR__ . '/storage/database.sqlite',
        'mysql' => [
            'host' => 'localhost',
            'port' => 3306,
            'name' => '',
            'user' => '',
            'pass' => '',
        ],
    ],

    // مسیر ffmpeg برای ساخت ایموجی‌های متحرک (WEBM/VP9).
    // اگر هاست ffmpeg ندارد مقدار '' بگذارید؛ قالب‌های متحرک مخفی می‌شوند.
    'ffmpeg' => 'ffmpeg',

    'timezone' => 'Asia/Tehran',

    // بسته‌های خرید سکه با Telegram Stars (ستاره)
    'stars_packages' => [
        ['stars' => 25,  'coins' => 50],
        ['stars' => 50,  'coins' => 110],
        ['stars' => 100, 'coins' => 240],
        ['stars' => 250, 'coins' => 650],
    ],

    // حداکثر تعداد ساخت هم‌زمان پک روی سرور (برای جلوگیری از فشار CPU)
    'max_parallel_jobs' => 2,

    // اعتبار initData مینی‌اپ (ثانیه)
    'init_data_ttl' => 86400,

    // آدرس Bot API (برای Local Bot API Server یا تست قابل تغییر است)
    'api_url' => 'https://api.telegram.org',

    'debug' => false,
];
