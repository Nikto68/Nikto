<?php
declare(strict_types=1);

namespace EmojiBot\Bot;

use EmojiBot\App;
use EmojiBot\Settings;

/**
 * Admin panel inside the bot chat (/admin). Template management lives in the mini app.
 */
final class Admin
{
    public function __construct(private readonly App $app)
    {
    }

    private function mainMarkup(): array
    {
        $maint = $this->app->settings()->get('maintenance');
        return ['inline_keyboard' => [
            [['text' => '📊 آمار', 'callback_data' => 'adm:stats'], ['text' => '📢 پیام همگانی', 'callback_data' => 'adm:bc']],
            [['text' => '👤 مدیریت کاربر', 'callback_data' => 'adm:user'], ['text' => '⚙️ تنظیمات', 'callback_data' => 'adm:set']],
            [['text' => '🧩 مدیریت قالب‌ها', 'web_app' => ['url' => $this->app->appUrl('admin')], 'style' => 'primary']],
            [['text' => $maint ? '🛠 حالت تعمیر: روشن' : '🟢 حالت تعمیر: خاموش', 'callback_data' => 'adm:maint', 'style' => $maint ? 'danger' : 'success']],
        ]];
    }

    public function menu(int $chatId, ?int $editId = null): void
    {
        $text = "🛡 <b>پنل مدیریت</b>\n\nیک گزینه را انتخاب کنید:";
        $this->out($chatId, $text, $this->mainMarkup(), $editId);
    }

    private function back(): array
    {
        return [['text' => '🔙 بازگشت', 'callback_data' => 'adm:home']];
    }

    private function out(int $chatId, string $text, array $markup, ?int $editId = null): void
    {
        $params = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', 'reply_markup' => $markup];
        if ($editId) {
            $ok = $this->app->tg()->safe('editMessageText', $params + ['message_id' => $editId]);
            if ($ok !== null) {
                return;
            }
        }
        $this->app->tg()->safe('sendMessage', $params);
    }

    public function onCallback(array $q, string $data): void
    {
        $app = $this->app;
        $uid = (int) $q['from']['id'];
        $mid = (int) ($q['message']['message_id'] ?? 0);
        $parts = explode(':', $data);
        $action = $parts[1] ?? '';

        switch ($action) {
            case 'home':
                $app->users()->setState($uid, null);
                $this->menu($uid, $mid);
                return;
            case 'stats':
                $this->out($uid, $this->statsText(), ['inline_keyboard' => [[['text' => '🔄 بروزرسانی', 'callback_data' => 'adm:stats']], $this->back()]], $mid);
                return;
            case 'maint':
                $app->settings()->set('maintenance', !$app->settings()->get('maintenance'));
                $this->menu($uid, $mid);
                return;
            case 'bc':
                $app->users()->setState($uid, 'adm_bc');
                $this->out($uid, "📢 پیامی که می‌خواهید برای همه ارسال شود را بفرستید (متن، عکس، ویدیو و ...).\n\nبرای لغو: /cancel", ['inline_keyboard' => [$this->back()]], $mid);
                return;
            case 'bcgo':
                $user = $app->users()->get($uid);
                $st = json_decode((string) ($user['state_data'] ?? ''), true);
                if (($user['state'] ?? '') !== 'adm_bc_confirm' || empty($st['mid'])) {
                    $this->menu($uid, $mid);
                    return;
                }
                $app->users()->setState($uid, null);
                $id = (new Broadcaster($app))->create($uid, $uid, (int) $st['mid']);
                $this->out($uid, "✅ ارسال همگانی #$id در صف قرار گرفت و در پس‌زمینه (cron) انجام می‌شود.\nپس از پایان گزارش ارسال می‌شود.", ['inline_keyboard' => [$this->back()]], $mid);
                return;
            case 'user':
                $app->users()->setState($uid, 'adm_user');
                $this->out($uid, "👤 آیدی عددی یا یوزرنیم (@username) کاربر را بفرستید.\n\nبرای لغو: /cancel", ['inline_keyboard' => [$this->back()]], $mid);
                return;
            case 'u':
                $target = (int) ($parts[2] ?? 0);
                $op = $parts[3] ?? '';
                if ($op === 'ban' || $op === 'unban') {
                    if ($app->isAdmin($target)) {
                        return;
                    }
                    $app->users()->setBanned($target, $op === 'ban');
                } elseif ($op === 'add' || $op === 'sub') {
                    $app->users()->setState($uid, 'adm_coins', ['uid' => $target, 'op' => $op]);
                    $this->out($uid, ($op === 'add' ? '➕' : '➖') . ' تعداد سکه را بفرستید:', ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => "adm:u:$target:show"]]]], $mid);
                    return;
                }
                $this->userCard($uid, $target, $mid);
                return;
            case 'set':
                $key = $parts[2] ?? '';
                if ($key === '') {
                    $this->settingsMenu($uid, $mid);
                    return;
                }
                if (!isset(Settings::EDITABLE[$key])) {
                    return;
                }
                if (Settings::EDITABLE[$key][1] === 'bool') {
                    $app->settings()->set($key, !$app->settings()->get($key));
                    $this->settingsMenu($uid, $mid);
                    return;
                }
                $app->users()->setState($uid, 'adm_set', ['key' => $key]);
                $hint = Settings::EDITABLE[$key][1] === 'str' ? "\nبرای خالی کردن، علامت - بفرستید." : '';
                $this->out($uid, '✏️ مقدار جدید «' . Settings::EDITABLE[$key][2] . '» را بفرستید.' . $hint . "\n\nمقدار فعلی: <code>" . Texts::e((string) $app->settings()->get($key)) . '</code>', ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'adm:set']]]], $mid);
                return;
        }
        $this->menu($uid, $mid);
    }

    public function onState(array $user, array $m): void
    {
        $app = $this->app;
        $uid = (int) $user['id'];
        $text = trim((string) ($m['text'] ?? ''));
        $data = json_decode((string) ($user['state_data'] ?? ''), true) ?: [];

        switch ($user['state']) {
            case 'adm_bc':
            case 'adm_bc_confirm':
                $app->users()->setState($uid, 'adm_bc_confirm', ['mid' => (int) $m['message_id']]);
                $total = (int) $app->db()->val('SELECT COUNT(*) FROM users WHERE blocked = 0 AND banned = 0');
                $app->tg()->safe('copyMessage', ['chat_id' => $uid, 'from_chat_id' => $uid, 'message_id' => (int) $m['message_id']]);
                $this->out($uid, '👆 پیش‌نمایش پیام. برای <b>' . Texts::num($total) . '</b> کاربر ارسال شود؟', ['inline_keyboard' => [
                    [['text' => '✅ ارسال', 'callback_data' => 'adm:bcgo', 'style' => 'success'], ['text' => '❌ لغو', 'callback_data' => 'adm:home', 'style' => 'danger']],
                ]]);
                return;

            case 'adm_user':
                $target = null;
                if (preg_match('/^\d{3,20}$/', $text)) {
                    $target = $app->users()->get((int) $text);
                } elseif (preg_match('/^@?([A-Za-z0-9_]{4,32})$/', $text, $mm)) {
                    $target = $app->db()->one('SELECT * FROM users WHERE LOWER(username) = ?', [strtolower($mm[1])]);
                } elseif (isset($m['forward_origin']['sender_user']['id'])) {
                    $target = $app->users()->get((int) $m['forward_origin']['sender_user']['id']);
                }
                if (!$target) {
                    $this->out($uid, '❌ کاربر پیدا نشد. دوباره بفرستید یا /cancel', ['inline_keyboard' => [$this->back()]]);
                    return;
                }
                $app->users()->setState($uid, null);
                $this->userCard($uid, (int) $target['id']);
                return;

            case 'adm_coins':
                $n = (int) strtr($text, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
                if ($n <= 0 || $n > 10_000_000) {
                    $this->out($uid, '❌ یک عدد مثبت بفرستید.', ['inline_keyboard' => [$this->back()]]);
                    return;
                }
                $target = (int) ($data['uid'] ?? 0);
                $app->users()->setState($uid, null);
                $delta = ($data['op'] ?? '') === 'sub' ? -$n : $n;
                $balance = $app->users()->addCoins($target, $delta, 'admin', (string) $uid);
                if ($delta > 0) {
                    $app->tg()->safe('sendMessage', ['chat_id' => $target, 'text' => '🎁 ' . Texts::num($n) . " سکه از طرف مدیریت به حساب شما اضافه شد.\n💰 موجودی: " . Texts::num($balance)]);
                }
                $this->userCard($uid, $target);
                return;

            case 'adm_set':
                $key = (string) ($data['key'] ?? '');
                $err = $app->settings()->setFromAdmin($key, $text);
                if ($err !== null) {
                    $this->out($uid, '❌ ' . Texts::e($err), ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'adm:set']]]]);
                    return;
                }
                $app->users()->setState($uid, null);
                if ($key === 'join_channel' && $app->settings()->get('join_channel') !== '') {
                    $this->out($uid, "⚠️ حتماً ربات را در کانال <b>ادمین</b> کنید تا بتواند عضویت را بررسی کند.", ['inline_keyboard' => []]);
                }
                $this->settingsMenu($uid);
                return;
        }
        $app->users()->setState($uid, null);
        $this->menu($uid);
    }

    private function settingsMenu(int $uid, ?int $mid = null): void
    {
        $s = $this->app->settings();
        $rows = [];
        foreach (Settings::EDITABLE as $key => [$default, $type, $label]) {
            $v = $s->get($key);
            $shown = match ($type) {
                'bool' => $v ? 'روشن' : 'خاموش',
                'int' => Texts::num((int) $v),
                default => $v === '' ? '—' : mb_substr((string) $v, 0, 18),
            };
            $rows[] = [['text' => "$label: $shown", 'callback_data' => "adm:set:$key"]];
        }
        $rows[] = $this->back();
        $this->out($uid, "⚙️ <b>تنظیمات</b>\nروی هر مورد بزنید تا تغییر کند.", ['inline_keyboard' => $rows], $mid);
    }

    private function userCard(int $adminId, int $target, ?int $mid = null): void
    {
        $u = $this->app->users()->get($target);
        if (!$u) {
            $this->out($adminId, '❌ کاربر پیدا نشد.', ['inline_keyboard' => [$this->back()]], $mid);
            return;
        }
        $paid = (int) $this->app->db()->val('SELECT COALESCE(SUM(stars), 0) FROM payments WHERE user_id = ? AND refunded = 0', [$target]);
        $refs = (int) $this->app->db()->val('SELECT COUNT(*) FROM users WHERE referrer_id = ?', [$target]);
        $text = "👤 <b>" . Texts::e($this->app->users()->displayName($u)) . "</b>\n"
            . "🆔 <code>{$u['id']}</code>" . ($u['username'] !== '' ? ' — @' . Texts::e($u['username']) : '') . "\n"
            . '💰 سکه: ' . Texts::num((int) $u['coins']) . "\n"
            . '📦 پک: ' . Texts::num((int) $u['packs_count']) . ' — ایموجی: ' . Texts::num((int) $u['emojis_count']) . "\n"
            . '⭐️ پرداخت: ' . Texts::num($paid) . ' ستاره — 👥 دعوت: ' . Texts::num($refs) . "\n"
            . '💎 پریمیوم: ' . ((int) $u['is_premium'] ? 'بله' : 'خیر') . "\n"
            . '📅 عضویت: ' . date('Y-m-d H:i', (int) $u['created_at']) . "\n"
            . '🕒 آخرین بازدید: ' . date('Y-m-d H:i', (int) $u['last_seen']) . "\n"
            . ((int) $u['banned'] ? '🚫 <b>مسدود</b>' : '✅ فعال') . ((int) $u['blocked'] ? ' — ربات را بلاک کرده' : '');
        $banBtn = (int) $u['banned']
            ? ['text' => '✅ رفع مسدودی', 'callback_data' => "adm:u:$target:unban", 'style' => 'success']
            : ['text' => '🚫 مسدود کردن', 'callback_data' => "adm:u:$target:ban", 'style' => 'danger'];
        $this->out($adminId, $text, ['inline_keyboard' => [
            [['text' => '➕ افزودن سکه', 'callback_data' => "adm:u:$target:add"], ['text' => '➖ کسر سکه', 'callback_data' => "adm:u:$target:sub"]],
            [$banBtn],
            $this->back(),
        ]], $mid);
    }

    private function statsText(): string
    {
        $db = $this->app->db();
        $today = strtotime('today');
        $q = fn (string $sql, array $p = []) => (int) $db->val($sql, $p);
        $bc = $db->one("SELECT * FROM broadcasts WHERE status = 'running' ORDER BY id LIMIT 1");
        return "📊 <b>آمار ربات</b>\n\n"
            . '👥 کل کاربران: ' . Texts::num($q('SELECT COUNT(*) FROM users')) . "\n"
            . '🆕 کاربران امروز: ' . Texts::num($q('SELECT COUNT(*) FROM users WHERE created_at >= ?', [$today])) . "\n"
            . '🔥 فعال ۲۴ ساعت: ' . Texts::num($q('SELECT COUNT(*) FROM users WHERE last_seen >= ?', [time() - 86400])) . "\n"
            . '💎 پریمیوم: ' . Texts::num($q('SELECT COUNT(*) FROM users WHERE is_premium = 1')) . "\n"
            . '🚫 مسدود: ' . Texts::num($q('SELECT COUNT(*) FROM users WHERE banned = 1')) . ' — ⛔️ بلاک‌کرده: ' . Texts::num($q('SELECT COUNT(*) FROM users WHERE blocked = 1')) . "\n\n"
            . '📦 کل پک‌ها: ' . Texts::num($q('SELECT COUNT(*) FROM packs WHERE deleted = 0')) . "\n"
            . '✨ کل ایموجی‌ها: ' . Texts::num($q('SELECT COALESCE(SUM(emoji_count), 0) FROM packs WHERE deleted = 0')) . "\n"
            . '🛠 ساخت امروز: ' . Texts::num($q("SELECT COUNT(*) FROM jobs WHERE created_at >= ? AND status = 'done'", [$today]))
            . ' — ناموفق: ' . Texts::num($q("SELECT COUNT(*) FROM jobs WHERE created_at >= ? AND status = 'failed'", [$today])) . "\n"
            . '⏳ در صف: ' . Texts::num($q("SELECT COUNT(*) FROM jobs WHERE status IN ('pending', 'running')")) . "\n\n"
            . '⭐️ درآمد کل: ' . Texts::num($q('SELECT COALESCE(SUM(stars), 0) FROM payments WHERE refunded = 0')) . ' ستاره'
            . ' — امروز: ' . Texts::num($q('SELECT COALESCE(SUM(stars), 0) FROM payments WHERE refunded = 0 AND created_at >= ?', [$today])) . "\n"
            . '🪙 سکه در گردش: ' . Texts::num($q('SELECT COALESCE(SUM(coins), 0) FROM users'))
            . ($bc ? "\n\n📢 ارسال همگانی #{$bc['id']}: " . Texts::num((int) $bc['sent'] + (int) $bc['failed']) . '/' . Texts::num((int) $bc['total']) : '')
            . "\n\n🕒 " . date('Y-m-d H:i');
    }
}
