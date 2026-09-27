<?php
declare(strict_types=1);

namespace EmojiBot;

use EmojiBot\Bot\Broadcaster;

/**
 * Periodic background work, run every minute by cron (bin/worker.php or public/cron.php).
 */
final class Worker
{
    public function __construct(private readonly App $app)
    {
    }

    public function tick(int $seconds = 50): array
    {
        @set_time_limit($seconds + 30);
        $start = time();
        $this->app->jobs()->recoverStale();
        $jobs = $this->app->jobs()->drain(max(5, $seconds - 10));
        $left = $seconds - (time() - $start);
        if ($left > 3) {
            (new Broadcaster($this->app))->run($left);
        }
        $last = (int) $this->app->settings()->get('last_cleanup', 0);
        if ($last < time() - 3600) {
            $this->app->settings()->set('last_cleanup', time());
            $this->cleanup();
        }
        return ['jobs' => $jobs, 'seconds' => time() - $start];
    }

    public function cleanup(): void
    {
        $db = $this->app->db();
        $this->app->limiter()->cleanup();
        $db->exec('DELETE FROM updates_seen WHERE at < ?', [time() - 3 * 86400]);
        // Old previews / recolored templates: regenerated on demand.
        self::prune($this->app->storage('cache') . '/preview', time() - 3 * 86400);
        foreach (glob($this->app->storage('cache') . '/tpl-*.png') ?: [] as $f) {
            if (@filemtime($f) < time() - 7 * 86400) {
                @unlink($f);
            }
        }
        // Temp dirs of crashed jobs.
        foreach (glob($this->app->storage('tmp') . '/{job,enc}-*', GLOB_BRACE | GLOB_ONLYDIR) ?: [] as $dir) {
            if (@filemtime($dir) < time() - 86400) {
                self::prune($dir, PHP_INT_MAX);
                @rmdir($dir);
            }
        }
    }

    private static function prune(string $dir, int $olderThan): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            if ($f->isFile() && $f->getMTime() < $olderThan) {
                @unlink($f->getPathname());
            } elseif ($f->isDir()) {
                @rmdir($f->getPathname()); // only succeeds when empty
            }
        }
    }
}
