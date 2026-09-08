<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Read-only, dot-notation view over the arrays returned by config/*.php.
 * `config('telegram.bot_token')` reads config/telegram.php -> ['bot_token' => ...].
 */
final class Config
{
    /** @var array<string, mixed> */
    private array $items = [];

    public function __construct(string $configDirectory)
    {
        foreach (glob(rtrim($configDirectory, '/') . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            $this->items[$key] = require $file;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function has(string $key): bool
    {
        $sentinel = new \stdClass();

        return $this->get($key, $sentinel) !== $sentinel;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }
}
