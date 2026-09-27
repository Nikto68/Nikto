<?php
declare(strict_types=1);

use EmojiBot\Setup;

// php bin/setup.php            -> set webhook, commands, menu button
// php bin/setup.php --polling  -> same but without webhook (for bin/poll.php)
require dirname(__DIR__) . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
$polling = in_array('--polling', $argv, true);
echo implode("\n", (new Setup(emojibot()))->run(!$polling)), "\n";
