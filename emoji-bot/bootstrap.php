<?php
declare(strict_types=1);

use EmojiBot\App;

define('EMOJIBOT_ROOT', __DIR__);

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('PHP 8.1+ is required');
}

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'EmojiBot\\')) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 9)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

if (!is_dir(__DIR__ . '/storage/logs')) {
    @mkdir(__DIR__ . '/storage/logs', 0775, true);
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/storage/logs/php-error.log');
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

/**
 * Loads config.php (or the file named by EMOJIBOT_CONFIG, used by tests) and returns the app.
 */
function emojibot(): App
{
    static $app = null;
    if ($app === null) {
        $path = getenv('EMOJIBOT_CONFIG') ?: __DIR__ . '/config.php';
        if (!is_file($path)) {
            http_response_code(500);
            exit('config.php not found. Copy config.example.php to config.php and fill it in.');
        }
        $config = require $path;
        date_default_timezone_set($config['timezone'] ?? 'Asia/Tehran');
        $app = new App($config);
    }
    return $app;
}
