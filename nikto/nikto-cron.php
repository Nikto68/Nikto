<?php

if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "NIKTO CRYPTO BOT\n\n";
    echo "PHP " . PHP_VERSION . " — this bot needs PHP 8.0 or newer.\n";
    echo "نسخه PHP هاست را از پنل هاست روی 8.1 یا بالاتر بگذارید.\n";
    exit;
}

define('NIKTO_LIBRARY', true);

$bot = __DIR__ . '/nikto-bot.php';
if (!is_file($bot)) {
    exit("فایل nikto-bot.php کنار این فایل پیدا نشد.\n");
}
require $bot;

if (PHP_SAPI === 'cli') {
    $loop = in_array('--loop', $argv, true);
    exit(\Nikto\Bundle\Runtime::handleCli(['cron', $loop ? 'cron:loop' : 'cron']));
}

\Nikto\Bundle\Runtime::handleWebCron();
