<?php
declare(strict_types=1);

namespace EmojiBot\Bot;

use EmojiBot\App;
use EmojiBot\TelegramError;

/**
 * Optional "join our channel first" gate.
 */
final class Membership
{
    private const CACHE_SECONDS = 600;

    public static function channel(App $app): string
    {
        return trim((string) $app->settings()->get('join_channel'));
    }

    public static function isMember(App $app, int $userId, bool $force = false): bool
    {
        $channel = self::channel($app);
        if ($channel === '' || $app->isAdmin($userId)) {
            return true;
        }
        $checked = (int) $app->db()->val('SELECT join_checked_at FROM users WHERE id = ?', [$userId]);
        if (!$force && $checked > time() - self::CACHE_SECONDS) {
            return true;
        }
        try {
            $m = $app->tg()->call('getChatMember', ['chat_id' => $channel, 'user_id' => $userId], 10, 0);
        } catch (TelegramError $e) {
            // Misconfiguration (bot is not admin, wrong channel): don't lock everybody out.
            $app->log('join', 'getChatMember failed', ['channel' => $channel, 'err' => $e->getMessage()]);
            return true;
        }
        $status = $m['status'] ?? 'left';
        $ok = in_array($status, ['creator', 'administrator', 'member'], true) || ($status === 'restricted' && !empty($m['is_member']));
        $app->db()->exec('UPDATE users SET join_checked_at = ? WHERE id = ?', [$ok ? time() : 0, $userId]);
        return $ok;
    }

    public static function url(App $app): string
    {
        $channel = self::channel($app);
        if (str_starts_with($channel, '@')) {
            return 'https://t.me/' . substr($channel, 1);
        }
        $cached = (string) $app->settings()->get('join_link', '');
        if ($cached !== '' && $app->settings()->get('join_link_for') === $channel) {
            return $cached;
        }
        try {
            // createChatInviteLink adds a link; exportChatInviteLink would revoke the channel's primary link.
            $res = $app->tg()->call('createChatInviteLink', ['chat_id' => $channel, 'name' => 'emoji-bot'], 10, 0);
            $link = (string) ($res['invite_link'] ?? '');
            if ($link === '') {
                return 'https://t.me/';
            }
            $app->settings()->set('join_link', $link);
            $app->settings()->set('join_link_for', $channel);
            return $link;
        } catch (TelegramError) {
            return 'https://t.me/';
        }
    }

    public static function keyboard(App $app): array
    {
        return ['inline_keyboard' => [
            [['text' => '📢 عضویت در کانال', 'url' => self::url($app)]],
            [['text' => '✅ عضو شدم', 'callback_data' => 'join', 'style' => 'success']],
        ]];
    }
}
