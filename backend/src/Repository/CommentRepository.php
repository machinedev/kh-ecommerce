<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Database;

final class CommentRepository
{
    public function byProduct(int $productId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT c.id, c.body, c.created_at, c.user_id, u.username, u.display_name, u.avatar_url
             FROM comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.product_id = ?
             ORDER BY c.created_at ASC, c.id ASC'
        );
        $stmt->execute([$productId]);

        return $stmt->fetchAll();
    }

    public function create(int $productId, int $userId, string $body): int
    {
        $stmt = Database::pdo()->prepare('INSERT INTO comments (product_id, user_id, body) VALUES (?, ?, ?)');
        $stmt->execute([$productId, $userId, $body]);

        return (int) Database::pdo()->lastInsertId();
    }
}
