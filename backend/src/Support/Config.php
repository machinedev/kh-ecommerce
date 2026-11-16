<?php

declare(strict_types=1);

namespace App\Support;

final class Config
{
    /** @var array<string,mixed> */
    private static array $values = [];

    /** @param array<string,mixed> $values */
    public static function load(array $values): void
    {
        self::$values = $values;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }

        $env = getenv($key);

        return $env === false ? $default : $env;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return $value === null || $value === '' ? $default : (int) $value;
    }

    public static function isLocal(): bool
    {
        return strtolower((string) self::get('APP_ENV', 'local')) === 'local';
    }

    public static function labMode(): bool
    {
        return self::isLocal() && self::bool('LAB_MODE', false);
    }

    public static function debug(): bool
    {
        return self::bool('APP_DEBUG', false);
    }
}
