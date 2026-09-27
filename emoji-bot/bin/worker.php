<?php
declare(strict_types=1);

use EmojiBot\Worker;

// Cron (every minute):  * * * * * php /path/to/emoji-bot/bin/worker.php
// Or as a daemon:        php bin/worker.php --loop
require dirname(__DIR__) . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
$worker = new Worker(emojibot());
if (in_array('--loop', $argv, true)) {
    while (true) {
        $worker->tick(50);
        sleep(2);
    }
}
echo json_encode($worker->tick(50)), "\n";
