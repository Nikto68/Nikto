<?php
declare(strict_types=1);

namespace EmojiBot;

/**
 * Admin-editable key/value settings stored in the database.
 */
final class Settings
{
    /** Editable settings: key => [default, type, persian label, min, max] */
    public const EDITABLE = [
        'price_per_emoji'   => [0,   'int',  'قیمت هر ایموجی (سکه)', 0, 100000],
        'start_coins'       => [0,   'int',  'سکه هدیه شروع', 0, 100000],
        'daily_gift'        => [5,   'int',  'هدیه روزانه (سکه)', 0, 100000],
        'referral_bonus'    => [10,  'int',  'پاداش دعوت (سکه)', 0, 100000],
        'max_per_pack'      => [60,  'int',  'حداکثر ایموجی در هر ساخت', 1, 200],
        'daily_job_limit'   => [15,  'int',  'حداکثر ساخت روزانه هر کاربر', 1, 1000],
        'text_max'          => [20,  'int',  'حداکثر طول متن', 1, 40],
        'allow_persian'     => [0,   'bool', 'اجازه متن فارسی روی ایموجی', 0, 1],
        'title_suffix'      => ['',  'str',  'پسوند عنوان پک (مثلا @MyBot)', 0, 30],
        'join_channel'      => ['',  'str',  'کانال جوین اجباری (@username)', 0, 64],
        'maintenance'       => [0,   'bool', 'حالت تعمیر', 0, 1],
    ];

    private array $cache = [];
    private bool $loaded = false;

    public function __construct(private readonly Db $db)
    {
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }
        foreach ($this->db->all('SELECT k, v FROM settings') as $row) {
            $this->cache[$row['k']] = $row['v'];
        }
        $this->loaded = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->load();
        if (array_key_exists($key, $this->cache)) {
            $value = $this->cache[$key];
            $type = self::EDITABLE[$key][1] ?? 'str';
            return match ($type) {
                'int' => (int) $value,
                'bool' => (bool) (int) $value,
                default => $value,
            };
        }
        if ($default === null && isset(self::EDITABLE[$key])) {
            return self::EDITABLE[$key][0];
        }
        return $default;
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function set(string $key, mixed $value): void
    {
        $this->load();
        $value = is_bool($value) ? (string) (int) $value : (string) $value;
        $this->db->upsert('settings', ['k' => $key, 'v' => $value], ['k']);
        $this->cache[$key] = $value;
    }

    /**
     * Validates and stores an admin-provided value. Returns an error message or null.
     */
    public function setFromAdmin(string $key, string $raw): ?string
    {
        if (!isset(self::EDITABLE[$key])) {
            return 'تنظیم نامعتبر';
        }
        [, $type, , $min, $max] = self::EDITABLE[$key];
        $raw = trim($raw);
        if ($type === 'int' || $type === 'bool') {
            $raw = strtr($raw, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
            if (!preg_match('/^\d+$/', $raw)) {
                return 'فقط عدد بفرستید.';
            }
            $n = (int) $raw;
            if ($n < $min || $n > $max) {
                return "عدد باید بین $min و $max باشد.";
            }
            $this->set($key, $n);
            return null;
        }
        if ($raw === '-' || $raw === '0') {
            $raw = '';
        }
        if (mb_strlen($raw) > $max) {
            return "حداکثر $max کاراکتر.";
        }
        if ($key === 'join_channel' && $raw !== '' && !preg_match('/^@[A-Za-z][A-Za-z0-9_]{3,31}$|^-100\d{5,20}$/', $raw)) {
            return 'فرمت کانال: @username یا آیدی عددی -100...';
        }
        if ($key === 'title_suffix' && preg_match('/[\x00-\x1F]/u', $raw)) {
            return 'کاراکتر نامعتبر.';
        }
        $this->set($key, $raw);
        return null;
    }
}
