<?php
/**
 * End-to-end test against a running app server + mock Bot API (see tests/run.sh).
 */
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use EmojiBot\Setup;
use EmojiBot\WebAppAuth;
use EmojiBot\Worker;

$app = emojibot();
$BASE = rtrim((string) getenv('APP_URL'), '/');
$LOG = (string) getenv('MOCK_LOG');
$TOKEN = (string) $app->config('bot_token');
$ADMIN = 6595849261;
$U1 = 5550001; // regular user (member of channel)
$U2 = 5550002; // not a channel member
$U3 = 5550003; // blocked the bot (copyMessage -> 403)
$U4 = 5550004; // poor user

$fails = 0;
$passes = 0;
function check(bool $cond, string $label, mixed $debug = null): void
{
    global $fails, $passes;
    if ($cond) {
        $passes++;
        echo "  ✔ $label\n";
    } else {
        $fails++;
        echo "  ✘ $label" . ($debug !== null ? ' :: ' . mb_substr(json_encode($debug, JSON_UNESCAPED_UNICODE), 0, 600) : '') . "\n";
    }
}
function section(string $t): void
{
    echo "\n== $t ==\n";
}

function http(string $url, string $body, array $headers): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, (string) $res];
}

function calls(?string $method = null): array
{
    global $LOG;
    $out = [];
    foreach (file($LOG, FILE_IGNORE_NEW_LINES) ?: [] as $l) {
        $j = json_decode($l, true);
        if ($j && ($method === null || $j['method'] === $method)) {
            $out[] = $j;
        }
    }
    return $out;
}
function lastCall(string $method): ?array
{
    $c = calls($method);
    return $c ? $c[count($c) - 1] : null;
}

$UPDATE = 1000;
function tgUser(int $id, string $name = 'Test', bool $premium = false): array
{
    return ['id' => $id, 'is_bot' => false, 'first_name' => $name, 'username' => 'u' . $id, 'language_code' => 'fa', 'is_premium' => $premium];
}
function webhook(array $update, ?string $secret = null): int
{
    global $BASE, $app, $UPDATE;
    $update['update_id'] ??= ++$UPDATE;
    [$code] = http($BASE . '/webhook.php', json_encode($update), ['Content-Type: application/json', 'X-Telegram-Bot-Api-Secret-Token: ' . ($secret ?? $app->webhookSecret())]);
    return $code;
}
function msg(int $uid, string $text, array $extra = []): int
{
    return webhook(['message' => ['message_id' => random_int(1, 99999), 'date' => time(), 'chat' => ['id' => $uid, 'type' => 'private'], 'from' => tgUser($uid, 'User' . $uid), 'text' => $text] + $extra]);
}
function cb(int $uid, string $data): int
{
    return webhook(['callback_query' => ['id' => (string) random_int(1, 999999), 'from' => tgUser($uid, 'User' . $uid), 'data' => $data, 'message' => ['message_id' => 77, 'chat' => ['id' => $uid, 'type' => 'private']]]]);
}
function initData(int $uid, ?int $authDate = null, bool $premium = false): string
{
    global $TOKEN;
    return WebAppAuth::sign(['auth_date' => (string) ($authDate ?? time()), 'query_id' => 'AAHtest', 'user' => json_encode(tgUser($uid, 'User' . $uid, $premium), JSON_UNESCAPED_UNICODE), 'signature' => 'xyz'], $TOKEN);
}
function api(int $uid, string $a, array $data = [], ?string $init = null): array
{
    global $BASE;
    [$code, $body] = http($BASE . '/api.php', json_encode(['a' => $a] + $data, JSON_UNESCAPED_UNICODE), ['Content-Type: application/json', 'X-Init-Data: ' . ($init ?? initData($uid))]);
    $j = json_decode($body, true);
    return ['code' => $code, 'json' => is_array($j) ? $j : ['raw' => $body]];
}
function apiUpload(int $uid, array $fields, string $file): array
{
    global $BASE;
    $ch = curl_init($BASE . '/api.php');
    $fields['image'] = new CURLFile($file, 'image/png', 'tpl.png');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields, CURLOPT_HTTPHEADER => ['X-Init-Data: ' . initData($uid)], CURLOPT_RETURNTRANSFER => true]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ['code' => $code, 'json' => json_decode((string) $res, true) ?: ['raw' => $res]];
}
function coins(int $uid): int
{
    global $app;
    return (int) $app->db()->val('SELECT coins FROM users WHERE id = ?', [$uid]);
}
function waitJob(int $uid, int $jobId, int $timeout = 90): array
{
    $end = time() + $timeout;
    do {
        $r = api($uid, 'job', ['id' => $jobId])['json'];
        if (in_array($r['status'] ?? '', ['done', 'failed'], true)) {
            return $r;
        }
        usleep(400_000);
    } while (time() < $end);
    return $r;
}
function dataUriPng(string $src): ?GdImage
{
    if (!str_starts_with($src, 'data:image/png;base64,')) {
        return null;
    }
    $im = @imagecreatefromstring(base64_decode(substr($src, 22)));
    return $im ?: null;
}

// ---------------------------------------------------------------------------------------------
section('Setup');
$report = (new Setup($app))->run();
check(str_contains(implode("\n", $report), '✅ وبهوک'), 'setup registers webhook', $report);
$wh = lastCall('setWebhook');
check(($wh['params']['secret_token'] ?? '') === $app->webhookSecret() && str_ends_with($wh['params']['url'], '/webhook.php'), 'webhook has secret token + url');
$mb = lastCall('setChatMenuButton');
check(($mb['params']['menu_button']['type'] ?? '') === 'web_app' && str_ends_with($mb['params']['menu_button']['web_app']['url'], '/app/'), 'menu button opens mini app');

section('Webhook security');
check(webhook(['message' => []], 'wrong') === 403, 'wrong secret rejected');
[$c] = http($BASE . '/webhook.php', '{}', ['Content-Type: application/json']);
check($c === 403, 'missing secret rejected');

section('/start, referral, duplicates');
msg($ADMIN, '/start');
$sm = lastCall('sendMessage');
$kb = $sm['params']['reply_markup']['inline_keyboard'] ?? [];
check(($kb[0][0]['web_app']['url'] ?? '') === $app->appUrl(), 'welcome has mini app button', $kb);
$before = coins($ADMIN);
msg($U1, '/start ref_' . $ADMIN);
check(coins($ADMIN) === $before + 10, 'referrer got bonus');
check((int) $app->db()->val('SELECT referrer_id FROM users WHERE id = ?', [$U1]) === $ADMIN, 'referrer stored');
msg($U1, '/start ref_' . $ADMIN);
check(coins($ADMIN) === $before + 10, 'referral only once');
$n = count(calls('sendMessage'));
webhook(['update_id' => 424242, 'message' => ['message_id' => 1, 'date' => time(), 'chat' => ['id' => $U1, 'type' => 'private'], 'from' => tgUser($U1), 'text' => '/help']]);
webhook(['update_id' => 424242, 'message' => ['message_id' => 1, 'date' => time(), 'chat' => ['id' => $U1, 'type' => 'private'], 'from' => tgUser($U1), 'text' => '/help']]);
check(count(calls('sendMessage')) === $n + 1, 'duplicate update ignored');
foreach ([$U2, $U3, $U4] as $u) {
    msg($u, '/start');
}

section('Daily gift');
cb($U1, 'gift');
check(coins($U1) === 5, 'gift granted', coins($U1));
cb($U1, 'gift');
check(coins($U1) === 5, 'gift only once per day');

section('Admin panel');
msg($U1, '/admin');
check(!str_contains(json_encode(lastCall('sendMessage')), 'پنل مدیریت'), 'non-admin cannot open panel');
msg($ADMIN, '/admin');
check(str_contains(lastCall('sendMessage')['params']['text'] ?? '', 'پنل مدیریت'), 'admin panel opens');
cb($ADMIN, 'adm:set:price_per_emoji');
msg($ADMIN, '۲');
check($app->db()->val("SELECT v FROM settings WHERE k = 'price_per_emoji'") === '2', 'price set via panel (Persian digits)');
cb($ADMIN, 'adm:stats');
check(str_contains(lastCall('editMessageText')['params']['text'] ?? '', 'کل کاربران'), 'stats shown');
cb($ADMIN, 'adm:user');
msg($ADMIN, (string) $U4);
check(str_contains(lastCall('sendMessage')['params']['text'] ?? '', (string) $U4), 'user card shown');
cb($ADMIN, "adm:u:$U4:add");
msg($ADMIN, '3');
check(coins($U4) === 3, 'admin added coins', coins($U4));
cb($U1, "adm:u:$U4:add");
check(coins($U4) === 3, 'non-admin admin-callback ignored');

section('Forced join');
cb($ADMIN, 'adm:set:join_channel');
msg($ADMIN, '@testchannel');
msg($U2, '/start');
check(($lastJoin = lastCall('sendMessage')) && str_contains($lastJoin['params']['text'], 'join our channel'), 'non-member gets join gate');
cb($U2, 'join');
check((lastCall('answerCallbackQuery')['params']['show_alert'] ?? false) === true, 'join check fails while not member');
$r = api($U2, 'boot');
check(!empty($r['json']['join']['url']), 'mini app boot reports join gate');
$r = api($U2, 'create', ['params' => ['text' => 'x'], 'ids' => ['b_shield']]);
check(($r['json']['code'] ?? '') === 'join', 'create blocked for non-member');
cb($ADMIN, 'adm:set:join_channel');
msg($ADMIN, '-');
check($app->settings()->get('join_channel') === '' || $app->db()->val("SELECT v FROM settings WHERE k='join_channel'") === '', 'join channel cleared');

section('Mini app auth');
check(api($U1, 'boot', [], 'bogus')['code'] === 401, 'invalid initData rejected');
check(api($U1, 'boot', [], initData($U1, time() - 90000))['code'] === 401, 'expired initData rejected');
$tampered = str_replace('User' . $U1, 'Hacker', initData($U1));
check(api($U1, 'boot', [], $tampered)['code'] === 401, 'tampered initData rejected');
$boot = api($U1, 'boot')['json'];
check(($boot['ok'] ?? false) && count($boot['categories']) >= 5 && count($boot['fonts']) === 16, 'boot ok', $boot['error'] ?? null);
check(($boot['categories'][0]['title'] ?? '') === '🔥 Trending' && ($boot['categories'][0]['items'][0]['animated'] ?? false) === true, 'trendy animated category first');
$allItems = array_merge(...array_map(fn ($c) => $c['items'], $boot['categories']));
$trendy = array_filter($allItems, fn ($i) => preg_match('/^a[lsb]_/', $i['id']));
check(count($trendy) === 106 && count(array_filter($trendy, fn ($i) => $i['animated'])) === 106, 'all 106 trendy templates are animated', count($trendy));
check(($boot['config']['latin_only'] ?? false) === true, 'English-only text by default');
check(($boot['config']['price'] ?? 0) === 2, 'price visible in config');

section('Previews');
$r = api($U1, 'preview', ['ids' => ['b_shield', 'b_flag_wave', 'nope'], 'params' => ['text' => 'Numbix', 'font' => 'unbounded', 'c1' => '#2aabee', 'c2' => '#ffffff', 'size' => 1.1, 'dy' => 0.2]])['json'];
$s = isset($r['items']['b_shield']) ? dataUriPng($r['items']['b_shield']['src']) : null;
check($s && imagesx($s) === 176, 'static preview 176px');
$a = isset($r['items']['b_flag_wave']) ? dataUriPng($r['items']['b_flag_wave']['src']) : null;
check($a && imagesx($a) === 128 * 12 && $r['items']['b_flag_wave']['frames'] === 12, 'animated preview sprite 12 frames');
check(!isset($r['items']['nope']), 'unknown template ignored');
$r = api($U1, 'preview', ['ids' => ['as_cat_face', 'ab_rose', 'al_coin'], 'params' => ['text' => 'Sina', 'tint' => true]])['json'];
check(count($r['items'] ?? []) === 3 && ($r['items']['as_cat_face']['frames'] ?? 0) === 12, 'trendy previews (peek/hover/flip) with tint', array_keys($r['items'] ?? []));
$r = api($U1, 'preview', ['ids' => ['b_shield'], 'params' => ['text' => 'سینا']])['json'];
check(($r['code'] ?? '') === 'invalid' && str_contains($r['error'] ?? '', 'English letters'), 'Persian text rejected by default', $r);
$r = api($U1, 'preview', ['ids' => ['b_shield'], 'params' => ['text' => 'hi 😀']])['json'];
check(($r['code'] ?? '') === 'invalid', 'emoji in text rejected', $r);
$r = api($U1, 'preview', ['ids' => ['b_shield'], 'params' => ['text' => str_repeat('a', 30)]])['json'];
check(($r['code'] ?? '') === 'invalid', 'too long text rejected');

cb($ADMIN, 'adm:set:allow_persian');
$r = api($U1, 'preview', ['ids' => ['b_shield'], 'params' => ['text' => 'سینا', 'font' => 'lalezar']])['json'];
check(isset($r['items']['b_shield']), 'Persian allowed after admin enables it');
cb($ADMIN, 'adm:set:allow_persian');

section('Payments (Telegram Stars)');
$inv = api($U1, 'invoice', ['pkg' => 0])['json'];
check(str_starts_with($inv['link'] ?? '', 'https://t.me/$'), 'invoice link created');
$ci = lastCall('createInvoiceLink')['params'];
check($ci['currency'] === 'XTR' && $ci['prices'][0]['amount'] === 25 && $ci['provider_token'] === '', 'invoice uses XTR, 25 stars');
$payload = $ci['payload'];
webhook(['pre_checkout_query' => ['id' => 'pcq1', 'from' => tgUser($U1), 'currency' => 'XTR', 'total_amount' => 25, 'invoice_payload' => $payload]]);
check((lastCall('answerPreCheckoutQuery')['params']['ok'] ?? null) === true, 'valid pre-checkout approved');
webhook(['pre_checkout_query' => ['id' => 'pcq2', 'from' => tgUser($U1), 'currency' => 'XTR', 'total_amount' => 1, 'invoice_payload' => $payload]]);
check((lastCall('answerPreCheckoutQuery')['params']['ok'] ?? null) === false, 'wrong amount rejected');
webhook(['pre_checkout_query' => ['id' => 'pcq3', 'from' => tgUser($U4), 'currency' => 'XTR', 'total_amount' => 25, 'invoice_payload' => $payload]]);
check((lastCall('answerPreCheckoutQuery')['params']['ok'] ?? null) === false, 'someone else\'s invoice rejected');
$pay = ['message_id' => 9, 'date' => time(), 'chat' => ['id' => $U1, 'type' => 'private'], 'from' => tgUser($U1), 'successful_payment' => ['currency' => 'XTR', 'total_amount' => 25, 'invoice_payload' => $payload, 'telegram_payment_charge_id' => 'charge_abc', 'provider_payment_charge_id' => '']];
webhook(['message' => $pay]);
webhook(['message' => $pay]);
check(coins($U1) === 55, 'coins credited exactly once', coins($U1));

section('Create pack');
$params = ['text' => 'Sina Jan', 'font' => 'montserrat', 'c1' => '#ff2d55', 'c2' => '#ffffff', 'size' => 1, 'dy' => 0];
$r = api($U1, 'create', ['params' => $params, 'ids' => ['b_shield', 'as_cat_face', 'b_plain', 'al_coin'], 'title' => 'پک من'])['json'];
check(($r['ok'] ?? false) && $r['cost'] === 8 && $r['coins'] === 47, 'job created, 8 coins charged', $r);
$busy = api($U1, 'create', ['params' => $params, 'ids' => ['b_shield']])['json'];
check(in_array($busy['code'] ?? '', ['busy'], true) || ($busy['ok'] ?? false) === false, 'second concurrent job refused', $busy);
$job = waitJob($U1, (int) ($r['job_id'] ?? 0));
check(($job['status'] ?? '') === 'done' && ($job['result']['added'] ?? 0) === 4, 'job done with 4 emoji', $job);
$cs = lastCall('createNewStickerSet');
check($cs && count($cs['params']['stickers']) === 4, 'createNewStickerSet called with 4 stickers');
$formats = array_column($cs['params']['stickers'] ?? [], 'format');
check($formats === ['static', 'video', 'static', 'video'], 'mixed static/video formats', $formats);
check(str_ends_with($cs['params']['name'] ?? '', '_by_emojitestbot') && ($cs['params']['sticker_type'] ?? '') === 'custom_emoji', 'set name + type valid', $cs['params']['name'] ?? null);
check(($cs['params']['title'] ?? '') === 'پک من', 'title kept');
$sizes = array_column($cs['files'] ?? [], 'size');
check($sizes && max($sizes) <= 65536, 'every file <= 64KB', $sizes);
check(str_contains(lastCall('sendMessage')['params']['text'] ?? '', 'is ready') && !str_contains(lastCall('sendMessage')['params']['text'], 'tg-emoji'), 'user notified (plain fallback after custom emoji refused)');
$ready = array_values(array_filter(calls('sendMessage'), fn ($c) => str_contains($c['params']['text'] ?? '', 'tg-emoji')));
check(count($ready) >= 1 && str_contains($ready[0]['params']['text'], 'emoji-id="5368324170671202'), 'tried to show new emoji inline via tg-emoji');
check(coins($U1) === 47, 'balance after create', coins($U1));

section('Add to pack, list, delete');
$packs = api($U1, 'packs')['json']['packs'] ?? [];
check(count($packs) === 1 && $packs[0]['count'] === 4 && !empty($packs[0]['cover']['src']), 'pack listed with cover', $packs);
$r = api($U1, 'create', ['params' => $params + ['text' => 'Ali'], 'ids' => ['b_star', 'b_coin_flip'], 'pack_id' => $packs[0]['id']])['json'];
$job = waitJob($U1, (int) ($r['job_id'] ?? 0));
check(($job['status'] ?? '') === 'done' && $job['result']['added'] === 2, 'added 2 emoji to existing pack', $job);
check(count(calls('addStickerToSet')) === 2, 'addStickerToSet called twice');
$packs = api($U1, 'packs')['json']['packs'];
check($packs[0]['count'] === 6, 'pack count updated');
$r = api($U4, 'pack_delete', ['id' => $packs[0]['id']])['json'];
check(($r['ok'] ?? true) === false, 'cannot delete someone else\'s pack');
$r = api($U1, 'pack_delete', ['id' => $packs[0]['id']])['json'];
check(($r['ok'] ?? false) && $r['packs'] === [] && lastCall('deleteStickerSet')['params']['name'] === $packs[0]['name'], 'pack deleted in Telegram + DB');

section('Logo packs');
$logoJpg = sys_get_temp_dir() . '/e2e-logo.jpg';
$li = imagecreatetruecolor(400, 400);
imagefill($li, 0, 0, imagecolorallocate($li, 255, 255, 255));
imagefilledellipse($li, 200, 200, 260, 260, imagecolorallocate($li, 20, 20, 20));
imagefilledellipse($li, 200, 200, 150, 150, imagecolorallocate($li, 40, 200, 80));
imagejpeg($li, $logoJpg, 92);
$up = apiUpload($U1, ['a' => 'logo_upload'], $logoJpg)['json'];
$logoRef = (string) ($up['logo'] ?? '');
check(str_starts_with($logoRef, $U1 . '/') && count($up['logos'] ?? []) === 1, 'logo uploaded', $up);
$logoImg = $app->logos()->load($logoRef);
check(((imagecolorat($logoImg, 0, 0) >> 24) & 0x7F) === 127 && imagesx($logoImg) < 300, 'white background removed + trimmed', [imagesx($logoImg), imagesy($logoImg)]);
$lp = ['logo' => $logoRef, 'logo_mode' => 'original', 'c1' => '#0b0b0b', 'c2' => '#22c55e'];
$r = api($U1, 'preview', ['ids' => ['b_shield', 'b_chart_grow', 'as_cat_face'], 'params' => $lp])['json'];
check(count($r['items'] ?? []) === 3 && ($r['items']['b_chart_grow']['frames'] ?? 0) === 12, 'logo previews (shield, animated chart, sign)', $r['error'] ?? null);
$r = api($U4, 'preview', ['ids' => ['b_shield'], 'params' => $lp])['json'];
check(($r['code'] ?? '') === 'invalid', 'cannot use another user\'s logo', $r);
$r = api($U1, 'create', ['params' => $lp + ['logo_mode' => 'c2'], 'ids' => ['b_shield', 'b_chart_grow', 'b_chart_down', 'al_coin'], 'title' => 'Brand'])['json'];
$job = waitJob($U1, (int) ($r['job_id'] ?? 0));
check(($job['status'] ?? '') === 'done' && $job['result']['added'] === 4, 'logo pack created', $job);
$cs = lastCall('createNewStickerSet');
check(array_column($cs['params']['stickers'] ?? [], 'format') === ['static', 'video', 'static', 'video'] && max(array_column($cs['files'], 'size')) <= 65536, 'logo pack formats + sizes ok');
$boot = api($U1, 'boot')['json'];
check(count($boot['logos'] ?? []) === 1 && str_starts_with($boot['logos'][0]['src'], 'data:image/png'), 'recent logos returned by boot');

section('Failures & refunds');
$r = api($U4, 'create', ['params' => $params, 'ids' => ['b_shield', 'b_badge']])['json'];
check(($r['code'] ?? '') === 'coins', 'insufficient coins -> 402', $r);
$c0 = coins($U1);
$r = api($U1, 'create', ['params' => $params, 'ids' => ['b_shield'], 'title' => 'FAILME'])['json'];
$job = waitJob($U1, (int) ($r['job_id'] ?? 0));
check(($job['status'] ?? '') === 'failed' && str_contains((string) $job['error'], 'start the bot'), 'failed job shows friendly error', $job);
check(coins($U1) === $c0, 'coins refunded after failure', [coins($U1), $c0]);

section('Admin templates (mini app)');
check((api($U1, 'adm_list')['json']['code'] ?? '') === 'forbidden', 'non-admin blocked from admin API');
$img = imagecreatetruecolor(300, 300);
imagesavealpha($img, true);
imagealphablending($img, false);
imagefilledrectangle($img, 0, 0, 299, 299, imagecolorallocatealpha($img, 0, 0, 0, 127));
imagealphablending($img, true);
imagefilledellipse($img, 150, 110, 180, 180, imagecolorallocate($img, 20, 20, 20));
imagefilledrectangle($img, 40, 180, 260, 280, imagecolorallocate($img, 240, 240, 240));
imagefilledrectangle($img, 40, 180, 260, 186, imagecolorallocate($img, 20, 20, 20));
$tmpPng = sys_get_temp_dir() . '/tpl-test.png';
imagepng($img, $tmpPng);
$up = apiUpload($ADMIN, ['a' => 'adm_upload', 'kind' => 'base'], $tmpPng)['json'];
$tid = (int) ($up['template']['id'] ?? 0);
check($tid > 0 && $up['template']['enabled'] === false, 'template uploaded (disabled draft)', $up);
$bad = sys_get_temp_dir() . '/not-image.png';
file_put_contents($bad, '<?php echo 1; ?>');
check((apiUpload($ADMIN, ['a' => 'adm_upload', 'kind' => 'base'], $bad)['json']['ok'] ?? true) === false, 'non-image upload rejected');
$cfg = ['box' => [0.5, 0.77, 0.66, 0.26], 'recolor' => 'duotone', 'text_color' => 'c1', 'effect' => 'none', 'angle' => -6];
$pv = api($ADMIN, 'adm_preview', ['id' => $tid, 'config' => $cfg, 'params' => $params])['json'];
check(($pv['frames'] ?? 0) === 1 && dataUriPng($pv['src'] ?? '') !== null, 'admin live preview');
$sv = api($ADMIN, 'adm_save', ['id' => $tid, 'title' => 'پپه تابلو', 'category' => 'Characters', 'emoji' => '🐸', 'sort' => 1, 'enabled' => true, 'config' => $cfg])['json'];
check(($sv['template']['enabled'] ?? false) === true && $sv['template']['config']['box'][1] === 0.77, 'template saved + enabled', $sv);
$sv2 = api($ADMIN, 'adm_save', ['id' => $tid, 'emoji' => 'abc', 'config' => $cfg])['json'];
check(($sv2['ok'] ?? true) === false, 'invalid emoji rejected');
$boot = api($U1, 'boot')['json'];
check(($boot['categories'][0]['title'] ?? '') === 'Characters' && $boot['categories'][0]['items'][0]['id'] === "c$tid", 'custom category shown first to users');
$r = api($U1, 'create', ['params' => $params, 'ids' => ["c$tid"]])['json'];
$job = waitJob($U1, (int) ($r['job_id'] ?? 0));
check(($job['status'] ?? '') === 'done', 'pack from custom template', $job);
$dis = api($ADMIN, 'adm_builtin', ['id' => 'b_star', 'enabled' => false])['json'];
$ids = array_merge(...array_map(fn ($c) => array_column($c['items'], 'id'), api($U1, 'boot')['json']['categories']));
check(!in_array('b_star', $ids, true), 'built-in template can be disabled');
api($ADMIN, 'adm_builtin', ['id' => 'b_star', 'enabled' => true]);

section('Broadcast');
cb($ADMIN, 'adm:bc');
msg($ADMIN, 'سلام به همه 👋');
cb($ADMIN, 'adm:bcgo');
$bc = $app->db()->one('SELECT * FROM broadcasts ORDER BY id DESC LIMIT 1');
check($bc && $bc['status'] === 'running', 'broadcast queued');
(new Worker($app))->tick(20);
$bc = $app->db()->one('SELECT * FROM broadcasts WHERE id = ?', [$bc['id']]);
$users = (int) $app->db()->val('SELECT COUNT(*) FROM users');
check($bc['status'] === 'done' && (int) $bc['sent'] + (int) $bc['failed'] === $users, 'broadcast finished for all users', $bc);
check((int) $app->db()->val('SELECT blocked FROM users WHERE id = ?', [$U3]) === 1, 'user who blocked bot is marked');

section('Maintenance & ban');
cb($ADMIN, 'adm:maint');
check((api($U1, 'create', ['params' => $params, 'ids' => ['b_shield']])['json']['code'] ?? '') === 'maintenance', 'maintenance blocks users');
check((api($ADMIN, 'preview', ['ids' => ['b_shield'], 'params' => $params])['json']['ok'] ?? false) === true, 'admin still works in maintenance');
cb($ADMIN, 'adm:maint');
cb($ADMIN, "adm:u:$U4:ban");
check(api($U4, 'boot')['code'] === 403, 'banned user blocked in mini app');
msg($U4, '/start');
check(str_contains(lastCall('sendMessage')['params']['text'] ?? '', 'blocked'), 'banned user told in chat');
cb($ADMIN, "adm:u:$ADMIN:ban");
check((int) $app->db()->val('SELECT banned FROM users WHERE id = ?', [$ADMIN]) === 0, 'admins cannot be banned');

echo "\n$passes passed, $fails failed\n";
exit($fails ? 1 : 0);
