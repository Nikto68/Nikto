<?php
declare(strict_types=1);

namespace EmojiBot;

/**
 * User records and the coin ledger. Every balance change goes through here and is logged.
 */
final class Users
{
    public function __construct(private readonly Db $db)
    {
    }

    public function get(int $id): ?array
    {
        return $this->db->one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    /**
     * Creates or refreshes a user from a Telegram User object.
     * @return array{0: array, 1: bool} [row, isNew]
     */
    public function touch(array $tgUser): array
    {
        $id = (int) $tgUser['id'];
        $now = time();
        $data = [
            'first_name' => mb_substr((string) ($tgUser['first_name'] ?? ''), 0, 128),
            'last_name' => mb_substr((string) ($tgUser['last_name'] ?? ''), 0, 128),
            'username' => mb_substr((string) ($tgUser['username'] ?? ''), 0, 64),
            'lang' => mb_substr((string) ($tgUser['language_code'] ?? ''), 0, 16),
            'is_premium' => !empty($tgUser['is_premium']) ? 1 : 0,
        ];
        $isNew = $this->db->insertIgnore('users', ['id' => $id] + $data + ['created_at' => $now, 'last_seen' => $now]);
        if (!$isNew) {
            $this->db->exec(
                'UPDATE users SET first_name = :first_name, last_name = :last_name, username = :username,
                    lang = :lang, is_premium = :is_premium, last_seen = :now, blocked = 0 WHERE id = :id',
                $data + ['now' => $now, 'id' => $id],
            );
        }
        return [$this->get($id), $isNew];
    }

    /** Adds (or with negative delta, forcibly removes) coins. Balance never goes below zero. */
    public function addCoins(int $userId, int $delta, string $reason, string $ref = ''): int
    {
        return $this->db->tx(function (Db $db) use ($userId, $delta, $reason, $ref) {
            if ($delta >= 0) {
                $db->exec('UPDATE users SET coins = coins + ? WHERE id = ?', [$delta, $userId]);
            } else {
                $db->exec('UPDATE users SET coins = CASE WHEN coins + ? < 0 THEN 0 ELSE coins + ? END WHERE id = ?', [$delta, $delta, $userId]);
            }
            $balance = (int) $db->val('SELECT coins FROM users WHERE id = ?', [$userId]);
            $this->log($db, $userId, $delta, $balance, $reason, $ref);
            return $balance;
        });
    }

    /** Atomically spends coins. Returns false if the balance is insufficient. */
    public function spendCoins(int $userId, int $amount, string $reason, string $ref = ''): bool
    {
        if ($amount <= 0) {
            return true;
        }
        return $this->db->tx(function (Db $db) use ($userId, $amount, $reason, $ref) {
            $ok = $db->exec('UPDATE users SET coins = coins - ? WHERE id = ? AND coins >= ?', [$amount, $userId, $amount]) > 0;
            if ($ok) {
                $balance = (int) $db->val('SELECT coins FROM users WHERE id = ?', [$userId]);
                $this->log($db, $userId, -$amount, $balance, $reason, $ref);
            }
            return $ok;
        });
    }

    private function log(Db $db, int $userId, int $delta, int $balance, string $reason, string $ref): void
    {
        $db->insert('coin_log', [
            'user_id' => $userId,
            'delta' => $delta,
            'balance' => $balance,
            'reason' => $reason,
            'ref' => mb_substr($ref, 0, 64),
            'created_at' => time(),
        ]);
    }

    /**
     * Claims the daily gift. Returns the amount granted, or 0 if not available yet.
     */
    public function claimGift(int $userId, int $amount, int $cooldown = 86400): int
    {
        if ($amount <= 0) {
            return 0;
        }
        $now = time();
        $claimed = $this->db->exec(
            'UPDATE users SET last_gift_at = ? WHERE id = ? AND last_gift_at <= ?',
            [$now, $userId, $now - $cooldown],
        ) > 0;
        if (!$claimed) {
            return 0;
        }
        $this->addCoins($userId, $amount, 'gift');
        return $amount;
    }

    public function setState(int $userId, ?string $state, ?array $data = null): void
    {
        $this->db->exec('UPDATE users SET state = ?, state_data = ? WHERE id = ?', [
            $state,
            $data === null ? null : json_encode($data, JSON_UNESCAPED_UNICODE),
            $userId,
        ]);
    }

    public function setBanned(int $userId, bool $banned): bool
    {
        return $this->db->exec('UPDATE users SET banned = ? WHERE id = ?', [$banned ? 1 : 0, $userId]) > 0;
    }

    public function markBlocked(int $userId): void
    {
        $this->db->exec('UPDATE users SET blocked = 1 WHERE id = ?', [$userId]);
    }

    public function displayName(array $user): string
    {
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        return $name !== '' ? $name : ('کاربر ' . $user['id']);
    }
}
