<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Database;

final class CartRepository
{
    public function getOrCreateCartId(int $userId): int
    {
        $stmt = Database::pdo()->prepare('SELECT id FROM carts WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $id = $stmt->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        $insert = Database::pdo()->prepare('INSERT INTO carts (user_id) VALUES (?)');
        $insert->execute([$userId]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function addItem(int $cartId, int $productId, int $quantity): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
        );
        $stmt->execute([$cartId, $productId, $quantity]);
    }

    public function updateItem(int $itemId, int $quantity): void
    {
        $stmt = Database::pdo()->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
        $stmt->execute([$quantity, $itemId]);
    }

    public function deleteItem(int $itemId): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM cart_items WHERE id = ?');
        $stmt->execute([$itemId]);
    }

    public function clear(int $cartId): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM cart_items WHERE cart_id = ?');
        $stmt->execute([$cartId]);
    }

    public function findItem(int $itemId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM cart_items WHERE id = ? LIMIT 1');
        $stmt->execute([$itemId]);

        return $stmt->fetch() ?: null;
    }

    public function itemsDetailed(int $cartId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT ci.id AS item_id, ci.quantity, ci.product_id,
                    p.title, p.price, p.stock, p.is_deleted, p.seller_id, p.image_url,
                    u.username AS seller_username, u.display_name AS seller_name,
                    (p.price * ci.quantity) AS subtotal
             FROM cart_items ci
             JOIN products p ON p.id = ci.product_id
             JOIN users u ON u.id = p.seller_id
             WHERE ci.cart_id = ?
             ORDER BY ci.id ASC'
        );
        $stmt->execute([$cartId]);

        return $stmt->fetchAll();
    }
}
