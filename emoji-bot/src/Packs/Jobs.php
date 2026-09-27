<?php
declare(strict_types=1);

namespace EmojiBot\Packs;

use EmojiBot\App;
use EmojiBot\Bot\Texts;
use EmojiBot\Render\Params;
use Throwable;

/**
 * Background queue for pack creation. Jobs are claimed atomically, so the API request that
 * created a job, the cron worker and any other process can all safely help drain the queue.
 */
final class Jobs
{
    public const STALE_AFTER = 900; // a running job older than this is considered crashed

    public function __construct(private readonly App $app)
    {
    }

    public function activeForUser(int $userId): ?array
    {
        return $this->app->db()->one(
            "SELECT * FROM jobs WHERE user_id = ? AND status IN ('pending', 'running') ORDER BY id DESC LIMIT 1",
            [$userId],
        );
    }

    public function create(int $userId, string $type, array $payload, int $total, int $cost): int
    {
        return $this->app->db()->insert('jobs', [
            'user_id' => $userId,
            'type' => $type,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'progress' => 0,
            'total' => $total,
            'cost' => $cost,
            'created_at' => time(),
        ]);
    }

    public function get(int $id, int $userId): ?array
    {
        return $this->app->db()->one('SELECT * FROM jobs WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public function todayCount(int $userId): int
    {
        $midnight = strtotime('today');
        return (int) $this->app->db()->val(
            "SELECT COUNT(*) FROM jobs WHERE user_id = ? AND created_at >= ? AND status <> 'failed'",
            [$userId, $midnight],
        );
    }

    public function runningCount(): int
    {
        return (int) $this->app->db()->val("SELECT COUNT(*) FROM jobs WHERE status = 'running'");
    }

    /** Atomically moves one pending job to running. */
    private function claim(?int $id = null): ?array
    {
        $db = $this->app->db();
        $row = $id !== null
            ? $db->one("SELECT id FROM jobs WHERE id = ? AND status = 'pending'", [$id])
            : $db->one("SELECT id FROM jobs WHERE status = 'pending' ORDER BY id ASC LIMIT 1");
        if (!$row) {
            return null;
        }
        $won = $db->exec("UPDATE jobs SET status = 'running', started_at = ? WHERE id = ? AND status = 'pending'", [time(), $row['id']]) > 0;
        return $won ? $db->one('SELECT * FROM jobs WHERE id = ?', [$row['id']]) : null;
    }

    /**
     * Processes pending jobs until the queue is empty or the time budget is spent,
     * never running more than max_parallel_jobs at once.
     */
    public function drain(int $seconds, ?int $preferId = null): int
    {
        $deadline = time() + $seconds;
        $max = max(1, (int) $this->app->config('max_parallel_jobs', 2));
        $done = 0;
        while (time() < $deadline) {
            if ($this->runningCount() >= $max) {
                break;
            }
            $job = $this->claim($preferId) ?? $this->claim();
            $preferId = null;
            if (!$job) {
                break;
            }
            $this->run($job);
            $done++;
        }
        return $done;
    }

    /** Fails jobs whose process died (e.g. the PHP worker was killed) and refunds them. */
    public function recoverStale(): void
    {
        $rows = $this->app->db()->all("SELECT * FROM jobs WHERE status = 'running' AND started_at < ?", [time() - self::STALE_AFTER]);
        foreach ($rows as $job) {
            $this->fail($job, 'This took too long and was cancelled. Your coins were refunded.');
        }
    }

    private function update(int $id, array $fields): void
    {
        $set = implode(', ', array_map(fn ($k) => "$k = :$k", array_keys($fields)));
        $this->app->db()->exec("UPDATE jobs SET $set WHERE id = :id", $fields + ['id' => $id]);
    }

    private function run(array $job): void
    {
        @set_time_limit(0);
        $app = $this->app;
        $payload = json_decode((string) $job['payload'], true) ?: [];
        $dir = $app->storage('tmp') . '/job-' . $job['id'] . '-' . bin2hex(random_bytes(4));
        @mkdir($dir, 0775, true);
        try {
            $params = Params::fromArray($payload['params'] ?? [], $app->fonts(), 40);
            $items = [];
            $used = [];
            $total = count($payload['templates'] ?? []);
            foreach (($payload['templates'] ?? []) as $i => $tid) {
                $tpl = $app->templates()->get((string) $tid);
                if (!$tpl) {
                    continue;
                }
                try {
                    $items[] = $app->packs()->renderItem($tpl, $params, $dir, $i);
                    $used[] = $tpl;
                } catch (Throwable $e) {
                    $app->log('render', 'item failed', ['job' => $job['id'], 'tpl' => $tid, 'err' => $e->getMessage()]);
                }
                $this->update((int) $job['id'], ['progress' => $i + 1, 'result' => json_encode(['stage' => 'render'])]);
            }
            if (!$items) {
                throw new \RuntimeException('no emoji could be rendered');
            }
            $this->update((int) $job['id'], ['progress' => 0, 'total' => count($items), 'result' => json_encode(['stage' => 'upload'])]);
            $onUploaded = fn (int $n) => $this->update((int) $job['id'], ['progress' => $n]);

            $db = $app->db();
            $userId = (int) $job['user_id'];
            if ($job['type'] === 'add') {
                $pack = $db->one('SELECT * FROM packs WHERE id = ? AND user_id = ? AND deleted = 0', [(int) $payload['pack_id'], $userId]);
                if (!$pack) {
                    throw new \RuntimeException('STICKERSET_INVALID');
                }
                $added = $app->packs()->addItems($userId, $pack['name'], $items, false, $onUploaded);
                $db->exec('UPDATE packs SET emoji_count = emoji_count + ?, updated_at = ? WHERE id = ?', [$added, time(), $pack['id']]);
                $packId = (int) $pack['id'];
                $name = $pack['name'];
                $title = $pack['title'];
            } else {
                $title = (string) $payload['title'];
                $res = $app->packs()->createSet($userId, $title, $items, $onUploaded);
                $added = $res['added'];
                $name = $res['name'];
                $packId = $db->insert('packs', [
                    'user_id' => $userId,
                    'name' => $name,
                    'title' => $title,
                    'emoji_count' => $added,
                    'cover' => json_encode(['tid' => $used[0]->id, 'params' => $params->toArray()], JSON_UNESCAPED_UNICODE),
                    'created_at' => time(),
                    'updated_at' => time(),
                ]);
                $db->exec('UPDATE users SET packs_count = packs_count + 1 WHERE id = ?', [$userId]);
            }
            foreach (array_slice($used, 0, $added) as $i => $tpl) {
                $db->insert('pack_items', [
                    'pack_id' => $packId,
                    'template_id' => $tpl->id,
                    'params' => json_encode($params->toArray(), JSON_UNESCAPED_UNICODE),
                    'format' => $items[$i]['format'],
                    'created_at' => time(),
                ]);
            }
            $db->exec('UPDATE users SET emojis_count = emojis_count + ? WHERE id = ?', [$added, $userId]);

            // Refund emoji that could not be rendered/uploaded.
            $unitPrice = $total > 0 ? intdiv((int) $job['cost'], $total) : 0;
            $refund = $unitPrice * ($total - $added);
            if ($refund > 0) {
                $app->users()->addCoins($userId, $refund, 'refund', 'job:' . $job['id']);
            }

            $result = ['stage' => 'done', 'name' => $name, 'link' => PackService::link($name), 'title' => $title, 'added' => $added, 'pack_id' => $packId];
            $this->update((int) $job['id'], [
                'status' => 'done',
                'progress' => $added,
                'result' => json_encode($result, JSON_UNESCAPED_UNICODE),
                'finished_at' => time(),
            ]);
            $message = [
                'chat_id' => $userId,
                'text' => Texts::packReady($title, $added, $job['type'] === 'add'),
                'parse_mode' => 'HTML',
                'reply_markup' => ['inline_keyboard' => [
                    [['text' => '➕ Add to Telegram', 'url' => PackService::link($name), 'style' => 'success']],
                    [['text' => '🎨 Create another pack', 'web_app' => ['url' => $app->appUrl()]]],
                ]],
            ];
            // Show the new animated emoji right in the message. Telegram only allows custom emoji
            // in bot messages in some cases (e.g. the bot owner has Premium), so fall back to plain text.
            $preview = $app->packs()->emojiPreview($name, $added);
            $sent = $preview !== '' ? $app->tg()->safe('sendMessage', ['text' => $message['text'] . "\n\n" . $preview] + $message) : null;
            if ($sent === null) {
                $app->tg()->safe('sendMessage', $message);
            }
        } catch (Throwable $e) {
            $app->log('job', 'failed', ['job' => $job['id'], 'err' => $e->getMessage()]);
            $this->fail($job, PackService::friendlyError($e));
        } finally {
            foreach (glob($dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }

    private function fail(array $job, string $message): void
    {
        $changed = $this->app->db()->exec(
            "UPDATE jobs SET status = 'failed', error = ?, finished_at = ? WHERE id = ? AND status IN ('pending', 'running')",
            [mb_substr($message, 0, 250), time(), $job['id']],
        ) > 0;
        if ($changed && (int) $job['cost'] > 0) {
            $this->app->users()->addCoins((int) $job['user_id'], (int) $job['cost'], 'refund', 'job:' . $job['id']);
        }
    }
}
