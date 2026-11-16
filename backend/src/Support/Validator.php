<?php

declare(strict_types=1);

namespace App\Support;

final class Validator
{
    /** @param array<string,mixed> $data */
    public static function required(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                throw new AppException('VALIDATION_ERROR', "Field {$field} wajib diisi.", 422);
            }
        }
    }

    public static function email(mixed $value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function positiveInt(mixed $value): bool
    {
        return is_numeric($value) && (int) $value > 0;
    }

    public static function nonNegativeNumber(mixed $value): bool
    {
        return is_numeric($value) && (float) $value >= 0;
    }
}
