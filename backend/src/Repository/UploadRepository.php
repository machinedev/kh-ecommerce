<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Database;

final class UploadRepository
{
    public function create(int $userId, string $originalName, string $storedName, string $mimeType, string $relativePath): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO uploads (user_id, original_name, stored_name, mime_type, relative_path)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $originalName, $storedName, $mimeType, $relativePath]);

        return (int) Database::pdo()->lastInsertId();
    }
}
