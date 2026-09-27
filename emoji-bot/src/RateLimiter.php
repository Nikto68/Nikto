<?php
declare(strict_types=1);

namespace EmojiBot;

/**
 * Fixed-window rate limiter backed by the database.
 */
final class RateLimiter
{
    public function __construct(private readonly Db $db)
    {
    }

    /** Registers a hit; returns false when the limit for the current window is exceeded. */
    public function hit(string $key, int $limit, int $windowSeconds, int $weight = 1): bool
    {
        $win = intdiv(time(), $windowSeconds) * $windowSeconds; // window start timestamp
        $new = $this->db->newValue('win');
        $this->db->upsert(
            'rate_limits',
            ['k' => $key, 'win' => $win, 'hits' => $weight],
            ['k'],
            // "hits" must be computed before "win" is overwritten (MySQL applies assignments in order).
            [
                'hits' => "CASE WHEN win = $new THEN hits + " . $this->db->newValue('hits') . ' ELSE ' . $this->db->newValue('hits') . ' END',
                'win' => $new,
            ],
        );
        $hits = (int) $this->db->val('SELECT hits FROM rate_limits WHERE k = ?', [$key]);
        return $hits <= $limit;
    }

    public function cleanup(): void
    {
        // Windows are at most one day long; anything that started two days ago is dead.
        $this->db->exec('DELETE FROM rate_limits WHERE win < ?', [time() - 2 * 86400]);
    }
}
