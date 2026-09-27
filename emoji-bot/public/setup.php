<?php
declare(strict_types=1);

use EmojiBot\Setup;

// Open once after upload and enter the "secret" from config.php.
// The secret is sent with POST (never in the URL) so it does not end up in server logs.
require dirname(__DIR__) . '/bootstrap.php';

$app = emojibot();
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
$report = null;
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    usleep(300_000); // slow down guessing
    if (hash_equals((string) $app->config('secret'), (string) ($_POST['secret'] ?? ''))) {
        $report = (new Setup($app))->run();
    } else {
        $error = 'کلید اشتباه است.';
        http_response_code(403);
    }
}
?><!doctype html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>نصب ربات</title>
<style>body{font-family:system-ui,Tahoma,sans-serif;background:#0d1117;color:#e9eef5;max-width:720px;margin:30px auto;padding:0 16px}
input,button{font:inherit;padding:12px;border-radius:10px;border:1px solid #334;width:100%;box-sizing:border-box;margin:6px 0}
button{background:#27b8f5;border:0;font-weight:bold;cursor:pointer}pre{background:#151c26;padding:14px;border-radius:12px;white-space:pre-wrap;line-height:1.9}.err{color:#ff4d61}</style>
</head><body>
<h2>نصب / بروزرسانی تنظیمات ربات</h2>
<?php if ($report !== null): ?>
<pre><?= htmlspecialchars(implode("\n", $report), ENT_QUOTES, 'UTF-8') ?></pre>
<?php else: ?>
<form method="post" autocomplete="off">
<p>مقدار <b>secret</b> داخل config.php را وارد کنید:</p>
<input type="password" name="secret" required>
<button type="submit">اجرای نصب</button>
<?php if ($error): ?><p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
</form>
<?php endif; ?>
</body></html>
