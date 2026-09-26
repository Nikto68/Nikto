<?php
require __DIR__ . '/app/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
if (!hash_equals(cron_key(), (string)($_GET['key'] ?? ($argv[1] ?? '')))) {
    http_response_code(403);
    exit('forbidden');
}
@set_time_limit(120);
$expired = orders_expire();
$n = 0;
foreach (rows("SELECT * FROM orders WHERE status = 'processing' AND provider IN ('smm', '5sim') ORDER BY updated LIMIT 40") as $o) {
    provider_refresh($o, true);
    $n++;
}
echo 'ok expired=' . $expired . ' refreshed=' . $n . "\n";
