<?php
declare(strict_types=1);

namespace EmojiBot\Bot;

use EmojiBot\App;
use EmojiBot\TelegramError;

/**
 * Sends a copied admin message to every user, in resumable batches (run from the worker).
 */
final class Broadcaster
{
    public function __construct(private readonly App $app)
    {
    }

    public function create(int $adminId, int $fromChatId, int $messageId): int
    {
        $total = (int) $this->app->db()->val('SELECT COUNT(*) FROM users WHERE blocked = 0 AND banned = 0');
        return $this->app->db()->insert('broadcasts', [
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId,
            'status' => 'running',
            'total' => $total,
            'created_by' => $adminId,
            'created_at' => time(),
        ]);
    }

    public function run(int $seconds): void
    {
        $lock = fopen($this->app->storage('tmp') . '/broadcast.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return; // another worker is broadcasting
        }
        try {
            $deadline = microtime(true) + $seconds;
            $db = $this->app->db();
            while (microtime(true) < $deadline) {
                $b = $db->one("SELECT * FROM broadcasts WHERE status = 'running' ORDER BY id ASC LIMIT 1");
                if (!$b) {
                    return;
                }
                $users = $db->all(
                    'SELECT id FROM users WHERE id > ? AND blocked = 0 AND banned = 0 ORDER BY id ASC LIMIT 25',
                    [(int) $b['cursor_id']],
                );
                if (!$users) {
                    $db->exec("UPDATE broadcasts SET status = 'done', finished_at = ? WHERE id = ?", [time(), $b['id']]);
                    $this->app->tg()->safe('sendMessage', [
                        'chat_id' => (int) $b['created_by'],
                        'text' => "📢 ارسال همگانی تمام شد.\n✅ موفق: " . Texts::num((int) $b['sent']) . "\n❌ ناموفق: " . Texts::num((int) $b['failed']),
                    ]);
                    continue;
                }
                foreach ($users as $u) {
                    if (microtime(true) >= $deadline) {
                        return;
                    }
                    $ok = $this->sendOne((int) $u['id'], $b);
                    $db->exec(
                        'UPDATE broadcasts SET cursor_id = ?, sent = sent + ?, failed = failed + ? WHERE id = ?',
                        [(int) $u['id'], $ok ? 1 : 0, $ok ? 0 : 1, $b['id']],
                    );
                    usleep(40_000); // ~25 messages per second, below Telegram's broadcast limit
                }
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function sendOne(int $userId, array $b): bool
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $this->app->tg()->call('copyMessage', [
                    'chat_id' => $userId,
                    'from_chat_id' => (int) $b['from_chat_id'],
                    'message_id' => (int) $b['message_id'],
                ], 15, 0);
                return true;
            } catch (TelegramError $e) {
                if ($e->retryAfter > 0 && $attempt === 0) {
                    sleep(min(30, $e->retryAfter));
                    continue;
                }
                if ($e->isUnreachableUser()) {
                    $this->app->users()->markBlocked($userId);
                }
                return false;
            }
        }
        return false;
    }
}
