<?php
define('NS_ROOT', dirname(__DIR__));
define('NS_APP', __DIR__);
define('NS_VERSION', '1.0.0');

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('Nik Store به PHP 8.0 یا بالاتر نیاز دارد. نسخه‌ی فعلی: ' . PHP_VERSION);
}
if (!is_file(NS_ROOT . '/config.php')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("config.php پیدا نشد.\n\nconfig.sample.php را کپی کنید، نامش را config.php بگذارید و مقدارهایش را پر کنید.");
}

$GLOBALS['NS_CFG'] = require NS_ROOT . '/config.php';
define('NS_STORAGE', rtrim((string)($GLOBALS['NS_CFG']['storage'] ?? NS_ROOT . '/storage'), '/\\'));

date_default_timezone_set('Asia/Tehran');
mb_internal_encoding('UTF-8');

if (!empty($GLOBALS['NS_CFG']['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

require NS_APP . '/helpers.php';
require NS_APP . '/db.php';
require NS_APP . '/models.php';
require NS_APP . '/payments.php';
require NS_APP . '/providers.php';

ns_storage_ready();

set_exception_handler(function (Throwable $e) {
    error_log('[nikstore] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) http_response_code(500);
    $dbg = !empty($GLOBALS['NS_CFG']['debug']);
    if (str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || isset($_GET['json'])) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'server', 'message' => 'خطای سرور — دوباره تلاش کنید.'], JSON_UNESCAPED_UNICODE);
        return;
    }
    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><body style="font-family:Tahoma,sans-serif;background:#0b0e1a;color:#e6e9f5;display:grid;place-items:center;min-height:100vh;margin:0">'
       . '<div style="max-width:520px;padding:32px;text-align:center"><h2>مشکلی پیش آمد</h2><p>لطفا چند لحظه بعد دوباره تلاش کنید.</p>'
       . ($dbg ? '<pre dir="ltr" style="text-align:left;white-space:pre-wrap;color:#fda4af">' . htmlspecialchars($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()) . '</pre>' : '')
       . '</div></body>';
});
