<?php
declare(strict_types=1);

namespace EmojiBot\Bot;

use EmojiBot\App;

/**
 * Coin purchases with Telegram Stars (currency XTR, no payment provider needed).
 */
final class Payments
{
    public function __construct(private readonly App $app)
    {
    }

    /** @return array<int, array{stars: int, coins: int}> */
    public function packages(): array
    {
        $out = [];
        foreach ((array) $this->app->config('stars_packages', []) as $i => $p) {
            $stars = (int) ($p['stars'] ?? 0);
            $coins = (int) ($p['coins'] ?? 0);
            if ($stars >= 1 && $stars <= 10000 && $coins > 0) {
                $out[(int) $i] = ['stars' => $stars, 'coins' => $coins];
            }
        }
        return $out;
    }

    public function invoiceLink(int $userId, int $index): string
    {
        $pkg = $this->packages()[$index] ?? null;
        if (!$pkg) {
            throw new \InvalidArgumentException('بسته نامعتبر است.');
        }
        $label = Texts::num($pkg['coins']) . ' سکه';
        return (string) $this->app->tg()->call('createInvoiceLink', [
            'title' => mb_substr('خرید ' . $label, 0, 32),
            'description' => 'افزایش موجودی برای ساخت ایموجی پریمیوم اختصاصی (' . $label . ')',
            'payload' => sprintf('c:%d:%d:%s', $index, $userId, bin2hex(random_bytes(4))),
            'provider_token' => '',
            'currency' => 'XTR',
            'prices' => [['label' => $label, 'amount' => $pkg['stars']]],
        ]);
    }

    /** @return array{0: int, 1: int, 2: array}|null [index, userId, package] */
    private function parsePayload(string $payload): ?array
    {
        if (!preg_match('/^c:(\d{1,3}):(\d{1,20}):[a-f0-9]{8}$/', $payload, $m)) {
            return null;
        }
        $pkg = $this->packages()[(int) $m[1]] ?? null;
        return $pkg ? [(int) $m[1], (int) $m[2], $pkg] : null;
    }

    public function preCheckout(array $q): void
    {
        $parsed = $this->parsePayload((string) ($q['invoice_payload'] ?? ''));
        $fromId = (int) ($q['from']['id'] ?? 0);
        $user = $this->app->users()->get($fromId);
        $error = match (true) {
            $parsed === null => 'این فاکتور منقضی شده است. دوباره از مینی‌اپ اقدام کنید.',
            ($q['currency'] ?? '') !== 'XTR' || (int) $q['total_amount'] !== $parsed[2]['stars'] => 'مبلغ فاکتور معتبر نیست.',
            $parsed[1] !== $fromId => 'این فاکتور متعلق به شما نیست.',
            !$user || (int) $user['banned'] === 1 => 'حساب شما مجاز به خرید نیست.',
            default => null,
        };
        $this->app->tg()->call('answerPreCheckoutQuery', array_filter([
            'pre_checkout_query_id' => $q['id'],
            'ok' => $error === null,
            'error_message' => $error,
        ], fn ($v) => $v !== null), 8, 0);
    }

    public function onSuccess(array $message): void
    {
        $sp = $message['successful_payment'];
        $userId = (int) $message['from']['id'];
        $parsed = $this->parsePayload((string) $sp['invoice_payload']);
        $chargeId = (string) $sp['telegram_payment_charge_id'];
        if ($parsed === null || ($sp['currency'] ?? '') !== 'XTR') {
            $this->app->log('payment', 'unexpected payment', ['sp' => $sp, 'user' => $userId]);
            foreach ($this->app->adminIds() as $admin) {
                $this->app->tg()->safe('sendMessage', ['chat_id' => $admin, 'text' => "⚠️ پرداخت ناشناخته از کاربر $userId\ncharge: $chargeId"]);
            }
            return;
        }
        $coins = $parsed[2]['coins'];
        $inserted = $this->app->db()->insertIgnore('payments', [
            'user_id' => $userId,
            'stars' => (int) $sp['total_amount'],
            'coins' => $coins,
            'charge_id' => $chargeId,
            'payload' => (string) $sp['invoice_payload'],
            'created_at' => time(),
        ]);
        if (!$inserted) {
            return; // duplicate update
        }
        $balance = $this->app->users()->addCoins($userId, $coins, 'purchase', substr($chargeId, 0, 64));
        $this->app->tg()->safe('sendMessage', [
            'chat_id' => $userId,
            'text' => '✅ پرداخت موفق بود! <b>' . Texts::num($coins) . "</b> سکه به حساب شما اضافه شد.\n💰 موجودی: <b>" . Texts::num($balance) . '</b> سکه',
            'parse_mode' => 'HTML',
        ]);
    }

    /** Admin-only refund. Returns a status message. */
    public function refund(string $chargeId): string
    {
        $row = $this->app->db()->one('SELECT * FROM payments WHERE charge_id = ?', [$chargeId]);
        if (!$row) {
            return 'پرداختی با این شناسه پیدا نشد.';
        }
        if ((int) $row['refunded'] === 1) {
            return 'این پرداخت قبلا بازگشت داده شده است.';
        }
        $this->app->tg()->call('refundStarPayment', ['user_id' => (int) $row['user_id'], 'telegram_payment_charge_id' => $chargeId]);
        $this->app->db()->exec('UPDATE payments SET refunded = 1 WHERE id = ?', [$row['id']]);
        $this->app->users()->addCoins((int) $row['user_id'], -(int) $row['coins'], 'refund_purchase', substr($chargeId, 0, 64));
        return '✅ ' . $row['stars'] . ' ستاره به کاربر ' . $row['user_id'] . ' برگشت داده شد.';
    }
}
