<?php
declare(strict_types=1);

namespace EmojiBot\Bot;

use EmojiBot\App;
use EmojiBot\Packs\PackService;

/**
 * Entry point for every Telegram update (webhook or long polling).
 */
final class Handler
{
    public function __construct(private readonly App $app)
    {
    }

    public function handle(array $update): void
    {
        if (isset($update['update_id']) && !$this->app->db()->insertIgnore('updates_seen', ['update_id' => (int) $update['update_id'], 'at' => time()])) {
            return; // Telegram re-delivered an update we already processed
        }
        if (isset($update['message'])) {
            $this->onMessage($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->onCallback($update['callback_query']);
        } elseif (isset($update['pre_checkout_query'])) {
            (new Payments($this->app))->preCheckout($update['pre_checkout_query']);
        } elseif (isset($update['my_chat_member'])) {
            $this->onMyChatMember($update['my_chat_member']);
        }
    }

    private function onMessage(array $m): void
    {
        if (($m['chat']['type'] ?? '') !== 'private' || empty($m['from']) || !empty($m['from']['is_bot'])) {
            return;
        }
        $app = $this->app;
        [$user, $isNew] = $app->users()->touch($m['from']);
        $uid = (int) $user['id'];

        if (isset($m['successful_payment'])) {
            (new Payments($app))->onSuccess($m);
            return;
        }
        if (!$app->limiter()->hit("msg:$uid", 30, 60)) {
            return; // flooding
        }
        if ((int) $user['banned'] === 1) {
            if ($app->limiter()->hit("banmsg:$uid", 1, 300)) {
                $this->send($uid, Texts::banned());
            }
            return;
        }

        $text = trim((string) ($m['text'] ?? ''));
        $cmd = null;
        $arg = '';
        if (preg_match('~^/([A-Za-z_]+)(?:@\w+)?(?:\s+(.*))?$~s', $text, $mm)) {
            $cmd = strtolower($mm[1]);
            $arg = trim($mm[2] ?? '');
        }

        $isAdmin = $app->isAdmin($uid);
        $admin = new Admin($app);
        if ($cmd === 'cancel') {
            $app->users()->setState($uid, null);
            $this->send($uid, '❌ Cancelled.', $this->mainKeyboard());
            return;
        }
        if ($isAdmin && $user['state'] && $cmd === null) {
            $admin->onState($user, $m);
            return;
        }
        if ($isAdmin && $user['state'] && $cmd !== null) {
            $app->users()->setState($uid, null);
        }

        if (!$isAdmin && $app->settings()->get('maintenance')) {
            $this->send($uid, Texts::maintenance());
            return;
        }

        switch ($cmd) {
            case 'start':
                $this->start($user, $isNew, $arg);
                return;
            case 'help':
                $this->send($uid, Texts::help(), $this->mainKeyboard());
                return;
            case 'packs':
                $this->sendPacks($uid);
                return;
            case 'admin':
            case 'panel':
                if ($isAdmin) {
                    $admin->menu($uid);
                    return;
                }
                break;
            case 'refund':
                if ($isAdmin) {
                    $this->send($uid, Texts::e((new Payments($app))->refund($arg)));
                    return;
                }
                break;
        }
        $this->send($uid, Texts::hint(), $this->mainKeyboard());
    }

    private function start(array $user, bool $isNew, string $arg): void
    {
        $app = $this->app;
        $uid = (int) $user['id'];
        if ($isNew) {
            $startCoins = $app->settings()->int('start_coins');
            if ($startCoins > 0) {
                $app->users()->addCoins($uid, $startCoins, 'start');
            }
            if (preg_match('/^ref_(\d{1,20})$/', $arg, $m)) {
                $this->referral($uid, (int) $m[1], $user);
            }
        }
        if (!Membership::isMember($app, $uid, true)) {
            $this->send($uid, Texts::joinRequired(), Membership::keyboard($app));
            return;
        }
        $this->welcome($uid);
    }

    private function referral(int $uid, int $refId, array $user): void
    {
        $app = $this->app;
        if ($refId === $uid || !$app->users()->get($refId)) {
            return;
        }
        $set = $app->db()->exec('UPDATE users SET referrer_id = ? WHERE id = ? AND referrer_id IS NULL', [$refId, $uid]) > 0;
        $bonus = $app->settings()->int('referral_bonus');
        if ($set && $bonus > 0) {
            $balance = $app->users()->addCoins($refId, $bonus, 'referral', (string) $uid);
            $app->tg()->safe('sendMessage', [
                'chat_id' => $refId,
                'text' => '🎉 <b>' . Texts::e($app->users()->displayName($user)) . '</b> joined with your invite link!'
                    . "\n➕ " . Texts::num($bonus) . ' coins — balance: ' . Texts::num($balance),
                'parse_mode' => 'HTML',
            ]);
        }
    }

    private function welcome(int $uid, ?int $editMessageId = null): void
    {
        $user = $this->app->users()->get($uid);
        $text = Texts::welcome($this->app->users()->displayName($user), (int) $user['coins']);
        if ($editMessageId !== null) {
            $this->app->tg()->safe('editMessageText', [
                'chat_id' => $uid,
                'message_id' => $editMessageId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => $this->mainKeyboard(),
            ]);
            return;
        }
        $this->send($uid, $text, $this->mainKeyboard());
    }

    public function mainKeyboard(): array
    {
        $app = $this->app;
        return ['inline_keyboard' => [
            [['text' => '🎨 Create emoji', 'web_app' => ['url' => $app->appUrl()], 'style' => 'primary']],
            [['text' => '📦 My packs', 'web_app' => ['url' => $app->appUrl('packs')]], ['text' => '💰 Buy coins', 'web_app' => ['url' => $app->appUrl('shop')]]],
            [['text' => '🎁 Daily gift', 'callback_data' => 'gift'], ['text' => '👥 Invite friends', 'callback_data' => 'invite']],
            [['text' => '📖 Help', 'callback_data' => 'help']],
        ]];
    }

    private function sendPacks(int $uid): void
    {
        $packs = $this->app->db()->all('SELECT * FROM packs WHERE user_id = ? AND deleted = 0 ORDER BY id DESC LIMIT 20', [$uid]);
        if (!$packs) {
            $this->send($uid, "You haven't made any packs yet. 👇", $this->mainKeyboard());
            return;
        }
        $rows = [];
        foreach ($packs as $p) {
            $rows[] = [['text' => '✨ ' . mb_substr($p['title'], 0, 40) . ' (' . Texts::num((int) $p['emoji_count']) . ')', 'url' => PackService::link($p['name'])]];
        }
        $rows[] = [['text' => '⚙️ Manage packs', 'web_app' => ['url' => $this->app->appUrl('packs')]]];
        $this->send($uid, '📦 <b>Your packs</b>', ['inline_keyboard' => $rows]);
    }

    private function onCallback(array $q): void
    {
        $app = $this->app;
        $from = $q['from'] ?? null;
        $data = (string) ($q['data'] ?? '');
        if (!$from || !empty($from['is_bot'])) {
            return;
        }
        [$user] = $app->users()->touch($from);
        $uid = (int) $user['id'];
        $answer = fn (string $text = '', bool $alert = false) => $app->tg()->safe('answerCallbackQuery', array_filter([
            'callback_query_id' => $q['id'],
            'text' => $text !== '' ? $text : null,
            'show_alert' => $alert ?: null,
        ]));

        if ((int) $user['banned'] === 1) {
            $answer(Texts::banned(), true);
            return;
        }
        if (!$app->limiter()->hit("cb:$uid", 40, 60)) {
            $answer('⏳ Slow down a little!');
            return;
        }
        if (str_starts_with($data, 'adm:')) {
            if (!$app->isAdmin($uid)) {
                $answer('⛔️');
                return;
            }
            (new Admin($app))->onCallback($q, $data);
            $answer();
            return;
        }
        if (!$app->isAdmin($uid) && $app->settings()->get('maintenance')) {
            $answer(Texts::maintenance(), true);
            return;
        }
        $messageId = (int) ($q['message']['message_id'] ?? 0);
        switch ($data) {
            case 'join':
                if (Membership::isMember($app, $uid, true)) {
                    $answer('✅ Membership confirmed');
                    $this->welcome($uid, $messageId ?: null);
                } else {
                    $answer("You haven't joined the channel yet ❗️", true);
                }
                return;
            case 'gift':
                $amount = $app->settings()->int('daily_gift');
                $got = $app->users()->claimGift($uid, $amount);
                if ($got > 0) {
                    $answer('🎁 You got ' . Texts::num($got) . ' coins!', true);
                } else {
                    $wait = 86400 - (time() - (int) $app->db()->val('SELECT last_gift_at FROM users WHERE id = ?', [$uid]));
                    $answer($amount > 0 ? '⏳ Next gift in ' . Texts::num(max(1, (int) ceil($wait / 3600))) . ' hour(s)' : 'The daily gift is not active.', true);
                }
                return;
            case 'invite':
                $link = 'https://t.me/' . $app->botUsername() . '?start=ref_' . $uid;
                $share = 'https://t.me/share/url?' . http_build_query(['url' => $link, 'text' => '✨ Create your own custom premium emoji with this bot!']);
                $this->send($uid, Texts::invite($link, $app->settings()->int('referral_bonus')), ['inline_keyboard' => [
                    [['text' => '📤 Share with friends', 'url' => $share]],
                ]]);
                $answer();
                return;
            case 'help':
                $this->send($uid, Texts::help(), $this->mainKeyboard());
                $answer();
                return;
        }
        $answer();
    }

    private function onMyChatMember(array $u): void
    {
        if (($u['chat']['type'] ?? '') !== 'private') {
            return;
        }
        $uid = (int) $u['chat']['id'];
        $status = $u['new_chat_member']['status'] ?? '';
        if ($status === 'kicked') {
            $this->app->users()->markBlocked($uid);
        } elseif ($status === 'member') {
            $this->app->db()->exec('UPDATE users SET blocked = 0 WHERE id = ?', [$uid]);
        }
    }

    private function send(int $chatId, string $text, ?array $markup = null): void
    {
        $this->app->tg()->safe('sendMessage', array_filter([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => $markup,
            'link_preview_options' => ['is_disabled' => true],
        ], fn ($v) => $v !== null));
    }
}
