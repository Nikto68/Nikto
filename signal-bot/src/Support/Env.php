<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Typed accessors over the environment variables loaded by phpdotenv.
 * All config/*.php files read through here instead of calling getenv() directly.
 */
final class Env
{
    public static function string(string $key, ?string $default = null): ?string
    {
        $value = self::raw($key);

        return $value === null ? $default : $value;
    }

    public static function int(string $key, ?int $default = null): ?int
    {
        $value = self::raw($key);

        return $value === null || $value === '' ? $default : (int) $value;
    }

    public static function float(string $key, ?float $default = null): ?float
    {
        $value = self::raw($key);

        return $value === null || $value === '' ? $default : (float) $value;
    }

    public static function bool(string $key, ?bool $default = null): ?bool
    {
        $value = self::raw($key);
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * @return string[]
     */
    public static function list(string $key, string $separator = ','): array
    {
        $value = self::raw($key);
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode($separator, $value)), static fn (string $v): bool => $v !== ''));
    }

    private static function raw(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return $value === false ? null : $value;
    }
}
