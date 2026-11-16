<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Database;

final class ActivityRepository
{
    public function add(int $userId, string $type, ?string $referenceType, ?int $referenceId, string $description): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO activities (user_id, activity_type, reference_type, reference_id, description)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $type, $referenceType, $referenceId, $description]);
    }

    public function byUser(int $userId, int $limit = 50): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, activity_type, reference_type, reference_id, description, created_at
             FROM activities
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }
}
