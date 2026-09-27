<?php
declare(strict_types=1);

namespace EmojiBot;

use EmojiBot\Packs\Jobs;
use EmojiBot\Packs\PackService;
use EmojiBot\Render\Fonts;
use EmojiBot\Render\Renderer;
use EmojiBot\Render\TemplateRegistry;
use EmojiBot\Render\VideoEncoder;
use RuntimeException;

/**
 * Tiny service container. Every service is created lazily on first use.
 */
final class App
{
    private array $services = [];

    public function __construct(private readonly array $config)
    {
        foreach (['bot_token', 'base_url', 'secret'] as $key) {
            if (empty($config[$key])) {
                throw new RuntimeException("config: '$key' is empty");
            }
        }
        if (!preg_match('/^\d+:[A-Za-z0-9_-]{30,}$/', $config['bot_token'])) {
            throw new RuntimeException("config: 'bot_token' looks invalid");
        }
        if (!preg_match('/^[A-Za-z0-9_-]{32,256}$/', $config['secret'])) {
            throw new RuntimeException("config: 'secret' must be 32-256 chars of A-Z a-z 0-9 _ -");
        }
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    private function service(string $name, callable $factory): mixed
    {
        return $this->services[$name] ??= $factory();
    }

    public function db(): Db
    {
        return $this->service('db', fn () => Db::connect($this->config['db'] ?? ['driver' => 'sqlite']));
    }

    public function tg(): Telegram
    {
        return $this->service('tg', fn () => new Telegram(
            $this->config['bot_token'],
            rtrim((string) ($this->config['api_url'] ?? 'https://api.telegram.org'), '/'),
        ));
    }

    public function settings(): Settings
    {
        return $this->service('settings', fn () => new Settings($this->db()));
    }

    public function users(): Users
    {
        return $this->service('users', fn () => new Users($this->db()));
    }

    public function limiter(): RateLimiter
    {
        return $this->service('limiter', fn () => new RateLimiter($this->db()));
    }

    public function fonts(): Fonts
    {
        return $this->service('fonts', fn () => new Fonts(EMOJIBOT_ROOT . '/assets/fonts', $this->storage('cache')));
    }

    public function templates(): TemplateRegistry
    {
        return $this->service('templates', fn () => new TemplateRegistry(
            $this->db(),
            $this->settings(),
            $this->storage('templates'),
            $this->video()->available(),
            EMOJIBOT_ROOT . '/assets/art',
            $this->storage('cache'),
        ));
    }

    public function video(): VideoEncoder
    {
        return $this->service('video', fn () => new VideoEncoder((string) ($this->config['ffmpeg'] ?? ''), $this->storage('tmp')));
    }

    public function renderer(): Renderer
    {
        return $this->service('renderer', fn () => new Renderer($this->fonts(), $this->storage('cache')));
    }

    public function packs(): PackService
    {
        return $this->service('packs', fn () => new PackService($this));
    }

    public function jobs(): Jobs
    {
        return $this->service('jobs', fn () => new Jobs($this));
    }

    public function isAdmin(int $userId): bool
    {
        return in_array($userId, array_map('intval', (array) ($this->config['admin_ids'] ?? [])), true);
    }

    /** @return int[] */
    public function adminIds(): array
    {
        return array_map('intval', (array) ($this->config['admin_ids'] ?? []));
    }

    public function botId(): int
    {
        return (int) explode(':', $this->config['bot_token'], 2)[0];
    }

    /** Bot username without "@", fetched once through getMe and cached in settings. */
    public function botUsername(): string
    {
        $cached = $this->settings()->get('bot_username');
        if ($cached) {
            return $cached;
        }
        $me = $this->tg()->call('getMe');
        $this->settings()->set('bot_username', (string) $me['username']);
        $this->settings()->set('bot_name', (string) $me['first_name']);
        return (string) $me['username'];
    }

    public function botName(): string
    {
        $name = $this->settings()->get('bot_name');
        if (!$name) {
            $this->botUsername();
            $name = $this->settings()->get('bot_name');
        }
        return (string) $name;
    }

    public function baseUrl(): string
    {
        return rtrim((string) $this->config['base_url'], '/');
    }

    /** Mini app URL; $page (packs, shop, gift, admin) is passed as ?p= because Telegram owns the #fragment. */
    public function appUrl(string $page = ''): string
    {
        return $this->baseUrl() . '/app/' . ($page !== '' ? '?p=' . rawurlencode($page) : '');
    }

    /** Secret sent by Telegram in X-Telegram-Bot-Api-Secret-Token (derived so it never equals the raw secret). */
    public function webhookSecret(): string
    {
        return substr(hash_hmac('sha256', 'webhook', $this->config['secret']), 0, 64);
    }

    /** Key used in setup.php / cron.php URLs. */
    public function urlKey(string $purpose): string
    {
        return substr(hash_hmac('sha256', 'url:' . $purpose, $this->config['secret']), 0, 32);
    }

    public function storage(string $sub = ''): string
    {
        $dir = EMOJIBOT_ROOT . '/storage' . ($sub !== '' ? '/' . $sub : '');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("cannot create $dir");
        }
        return $dir;
    }

    public function log(string $channel, string $message, array $context = []): void
    {
        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            $channel,
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
        );
        @file_put_contents($this->storage('logs') . '/app-' . date('Y-m') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
