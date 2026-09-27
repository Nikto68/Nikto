<?php
/**
 * Fake Bot API server for local tests:  php -S 127.0.0.1:8081 tests/mock_telegram.php
 * Logs every call to $MOCK_LOG (JSON lines) and validates sticker uploads the way Telegram does.
 */
declare(strict_types=1);

$log = getenv('MOCK_LOG') ?: sys_get_temp_dir() . '/mock_telegram.jsonl';
$ffprobe = getenv('MOCK_FFMPEG') ?: '';
$botUsername = 'EmojiTestBot';

if (!preg_match('~^/bot(\d+:[\w-]+)/(\w+)$~', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), $m)) {
    http_response_code(404);
    exit;
}
$method = $m[2];
$params = str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
    ? (json_decode(file_get_contents('php://input'), true) ?: [])
    : $_POST;
foreach (['stickers', 'sticker', 'reply_markup', 'menu_button', 'commands', 'prices', 'allowed_updates', 'scope'] as $k) {
    if (isset($params[$k]) && is_string($params[$k]) && ($params[$k][0] ?? '') !== 'a') {
        $d = json_decode($params[$k], true);
        if ($d !== null) {
            $params[$k] = $d;
        }
    }
}

$files = [];
foreach ($_FILES as $name => $f) {
    $info = @getimagesize($f['tmp_name']);
    $files[$name] = ['size' => $f['size'], 'type' => $f['type'], 'dims' => $info ? [$info[0], $info[1]] : null, 'head' => bin2hex((string) file_get_contents($f['tmp_name'], false, null, 0, 4))];
}
file_put_contents($log, json_encode(['method' => $method, 'params' => $params, 'files' => $files], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

function ok(mixed $result): never
{
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'result' => $result]);
    exit;
}
function fail(string $desc, int $code = 400): never
{
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode(['ok' => false, 'error_code' => $code, 'description' => $desc]);
    exit;
}

/** Validates one InputSticker + its uploaded file like Telegram would for custom emoji. */
function checkSticker(array $s, array $files): void
{
    if (!preg_match('/^attach:\/\/(\w+)$/', (string) ($s['sticker'] ?? ''), $mm) || !isset($files[$mm[1]])) {
        fail('Bad Request: sticker file not found');
    }
    $f = $files[$mm[1]];
    $list = $s['emoji_list'] ?? [];
    if (!is_array($list) || count($list) < 1 || count($list) > 20) {
        fail('Bad Request: invalid sticker emojis');
    }
    if ($f['size'] > 64 * 1024) {
        fail('Bad Request: STICKER_FILE_TOO_BIG');
    }
    if ($s['format'] === 'static') {
        if ($f['dims'] !== [100, 100]) {
            fail('Bad Request: STICKER_PNG_DIMENSIONS');
        }
    } elseif ($s['format'] === 'video') {
        if ($f['head'] !== '1a45dfa3') {
            fail('Bad Request: STICKER_VIDEO_NOWEBM');
        }
    } else {
        fail('Bad Request: wrong sticker format');
    }
}

$now = time();
switch ($method) {
    case 'getMe':
        ok(['id' => 123456789, 'is_bot' => true, 'first_name' => 'Emoji Test', 'username' => $botUsername]);
    case 'getStickerSet':
        $list = [];
        for ($i = 0; $i < 4; $i++) {
            $list[] = ['file_id' => "f$i", 'file_unique_id' => "u$i", 'type' => 'custom_emoji', 'width' => 100, 'height' => 100, 'is_animated' => false, 'is_video' => true, 'emoji' => '⭐', 'custom_emoji_id' => (string) (5368324170671202000 + $i)];
        }
        ok(['name' => $params['name'] ?? '', 'title' => 'x', 'sticker_type' => 'custom_emoji', 'stickers' => $list]);
    case 'sendMessage':
        // Simulate a bot whose owner has no Premium: custom emoji entities are refused.
        if (str_contains((string) ($params['text'] ?? ''), '<tg-emoji')) {
            fail('Bad Request: not enough rights to send custom emoji');
        }
        ok(['message_id' => random_int(100, 99999), 'date' => $now, 'chat' => ['id' => (int) ($params['chat_id'] ?? 0), 'type' => 'private'], 'text' => $params['text'] ?? '']);
    case 'editMessageText':
        ok(['message_id' => random_int(100, 99999), 'date' => $now, 'chat' => ['id' => (int) ($params['chat_id'] ?? 0), 'type' => 'private'], 'text' => $params['text'] ?? '']);
    case 'copyMessage':
        if ((int) $params['chat_id'] === 5550003) {
            fail('Forbidden: bot was blocked by the user', 403);
        }
        ok(['message_id' => random_int(100, 99999)]);
    case 'createInvoiceLink':
        ok('https://t.me/$' . bin2hex(random_bytes(6)));
    case 'getChatMember':
        ok(['status' => (int) $params['user_id'] === 5550002 ? 'left' : 'member', 'user' => ['id' => (int) $params['user_id']]]);
    case 'getWebhookInfo':
        ok(['url' => 'https://example.test/webhook.php', 'pending_update_count' => 0]);
    case 'createNewStickerSet':
        $name = (string) ($params['name'] ?? '');
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $name) || str_contains($name, '__') || !str_ends_with(strtolower($name), '_by_' . strtolower($botUsername)) || strlen($name) > 64) {
            fail('Bad Request: invalid sticker set name is specified');
        }
        $title = (string) ($params['title'] ?? '');
        if (mb_strlen($title) < 1 || mb_strlen($title) > 64) {
            fail('Bad Request: invalid sticker set title');
        }
        if (str_contains($title, 'FAILME')) {
            fail('Bad Request: PEER_ID_INVALID');
        }
        if (($params['sticker_type'] ?? '') !== 'custom_emoji') {
            fail('Bad Request: expected custom_emoji');
        }
        $stickers = $params['stickers'] ?? [];
        if (!is_array($stickers) || count($stickers) < 1 || count($stickers) > 50) {
            fail('Bad Request: invalid stickers count');
        }
        foreach ($stickers as $s) {
            checkSticker($s, $files);
        }
        ok(true);
    case 'addStickerToSet':
        checkSticker($params['sticker'] ?? [], $files);
        ok(true);
    default:
        ok(true);
}
