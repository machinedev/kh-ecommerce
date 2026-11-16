<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Database;

final class ProductRepository
{
    public function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, u.username AS seller_username, u.display_name AS seller_name, c.name AS category_name
             FROM products p
             JOIN users u ON u.id = p.seller_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.id = ? AND p.is_deleted = 0 LIMIT 1'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function findByIdPath(string $id): ?array
    {
        $sql = 'SELECT p.*, u.username AS seller_username, u.display_name AS seller_name, c.name AS category_name
                FROM products p
                JOIN users u ON u.id = p.seller_id
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.id = ' . $id . ' AND p.is_deleted = 0 LIMIT 1';

        return Database::pdo()->query($sql)->fetch() ?: null;
    }

    public function paginate(int $page, int $limit, string $q, ?int $categoryId): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $where = ['p.is_deleted = 0'];
        $params = [];

        if ($q !== '') {
            $where[] = '(p.title LIKE ? OR p.description LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if ($categoryId !== null) {
            $where[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countStmt = Database::pdo()->prepare('SELECT COUNT(*) FROM products p ' . $whereSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = 'SELECT p.*, u.username AS seller_username, u.display_name AS seller_name, c.name AS category_name
                FROM products p
                JOIN users u ON u.id = p.seller_id
                LEFT JOIN categories c ON c.id = p.category_id
                ' . $whereSql . '
                ORDER BY p.created_at DESC
                LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    public function latest(int $limit = 8): array
    {
        $sql = 'SELECT p.*, u.username AS seller_username, u.display_name AS seller_name, c.name AS category_name
                FROM products p
                JOIN users u ON u.id = p.seller_id
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.is_deleted = 0
                ORDER BY p.created_at DESC, p.id DESC
                LIMIT ' . (int) $limit;

        return Database::pdo()->query($sql)->fetchAll();
    }

    public function featured(int $limit = 6): array
    {
        $sql = 'SELECT p.*, u.username AS seller_username, u.display_name AS seller_name, c.name AS category_name
                FROM products p
                JOIN users u ON u.id = p.seller_id
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.is_deleted = 0 AND p.stock > 0
                ORDER BY p.stock ASC, p.id ASC
                LIMIT ' . (int) $limit;

        return Database::pdo()->query($sql)->fetchAll();
    }

    public function bySeller(int $sellerId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.seller_id = ? AND p.is_deleted = 0
             ORDER BY p.created_at DESC'
        );
        $stmt->execute([$sellerId]);

        return $stmt->fetchAll();
    }

    public function create(int $sellerId, ?int $categoryId, string $title, string $description, float $price, int $stock, ?string $imageUrl): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO products (seller_id, category_id, title, description, price, stock, image_url)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$sellerId, $categoryId, $title, $description, $price, $stock, $imageUrl]);

        return (int) Database::pdo()->lastInsertId();
    }

    /** @param array<string,mixed> $fields */
    public function update(int $id, array $fields): void
    {
        if ($fields === []) {
            return;
        }

        $columns = [];
        $values = [];
        foreach ($fields as $column => $value) {
            $columns[] = $column . ' = ?';
            $values[] = $value;
        }
        $values[] = $id;

        $stmt = Database::pdo()->prepare('UPDATE products SET ' . implode(', ', $columns) . ' WHERE id = ?');
        $stmt->execute($values);
    }

    public function softDelete(int $id): void
    {
        $stmt = Database::pdo()->prepare('UPDATE products SET is_deleted = 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function setDocument(int $id, string $path): void
    {
        $stmt = Database::pdo()->prepare('UPDATE products SET document_path = ? WHERE id = ?');
        $stmt->execute([$path, $id]);
    }

    public function decrementStock(int $id, int $quantity): void
    {
        $stmt = Database::pdo()->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
        $stmt->execute([$quantity, $id]);
    }

    public function incrementStock(int $id, int $quantity): void
    {
        $stmt = Database::pdo()->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
        $stmt->execute([$quantity, $id]);
    }
}
