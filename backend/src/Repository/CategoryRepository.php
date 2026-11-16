<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Database;

final class CategoryRepository
{
    public function all(): array
    {
        return Database::pdo()->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
    }

    public function exists(int $id): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM categories WHERE id = ?');
        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
