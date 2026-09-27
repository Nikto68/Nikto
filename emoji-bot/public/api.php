<?php
declare(strict_types=1);

use EmojiBot\Api\Api;

require dirname(__DIR__) . '/bootstrap.php';

(new Api(emojibot()))->handle();
