<?php

declare(strict_types=1);

namespace App\Support;

final class FileStorage
{
    public static function baseDir(): string
    {
        return rtrim((string) Config::get('UPLOAD_DIR', '/var/www/uploads'), '/');
    }

    /**
     * Persist an uploaded file and return storage metadata.
     *
     * @return array{stored_name:string, relative_path:string, mime_type:string, original_name:string}
     */
    public static function store(array $file, string $subdir = ''): array
    {
        $original = (string) ($file['name'] ?? 'upload');
        $tmp = (string) ($file['tmp_name'] ?? '');
        $mime = (string) ($file['type'] ?? 'application/octet-stream');

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new AppException('UPLOAD_FAILED', 'File tidak dapat diproses.', 400);
        }

        $ext = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));
        $stored = bin2hex(random_bytes(8)) . ($ext !== '' ? '.' . $ext : '');

        $dir = self::baseDir() . ($subdir !== '' ? '/' . trim($subdir, '/') : '');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (!move_uploaded_file($tmp, $dir . '/' . $stored)) {
            throw new AppException('UPLOAD_FAILED', 'File tidak dapat disimpan.', 500);
        }

        return [
            'stored_name' => $stored,
            'relative_path' => ($subdir !== '' ? trim($subdir, '/') . '/' : '') . $stored,
            'mime_type' => $mime,
            'original_name' => $original,
        ];
    }

    public static function pathFor(string $relativePath): string
    {
        return self::baseDir() . '/' . ltrim($relativePath, '/');
    }
}
