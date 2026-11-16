<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Database;

final class OrderRepository
{
    public function create(
        int $buyerId,
        float $total,
        string $status,
        ?int $couponId = null,
        float $discount = 0.0,
        string $paymentMethod = 'balance'
    ): int {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO orders (buyer_id, total_amount, status, coupon_id, discount_amount, payment_method)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$buyerId, $total, $status, $couponId, $discount, $paymentMethod]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function addItem(int $orderId, int $productId, int $sellerId, int $quantity, float $unitPrice, float $subtotal): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO order_items (order_id, product_id, seller_id, quantity, unit_price, subtotal)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$orderId, $productId, $sellerId, $quantity, $unitPrice, $subtotal]);
    }

    public function byBuyer(int $buyerId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT o.id, o.total_amount, o.discount_amount, o.refund_amount, o.payment_method,
                    o.status, o.cancelled_at, o.created_at,
                    pt.status AS payment_status, pt.snap_token, pt.redirect_url
             FROM orders o
             LEFT JOIN payment_transactions pt ON pt.order_id = o.id
             WHERE o.buyer_id = ? ORDER BY o.id DESC'
        );
        $stmt->execute([$buyerId]);

        return $stmt->fetchAll();
    }

    public function findById(int $orderId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$orderId]);

        return $stmt->fetch() ?: null;
    }

    public function lockById(int $orderId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$orderId]);

        return $stmt->fetch() ?: null;
    }

    public function findForBuyer(int $orderId, int $buyerId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM orders WHERE id = ? AND buyer_id = ? LIMIT 1');
        $stmt->execute([$orderId, $buyerId]);

        return $stmt->fetch() ?: null;
    }

    public function items(int $orderId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT oi.id, oi.product_id, oi.seller_id, oi.quantity, oi.unit_price, oi.subtotal,
                    p.title, u.username AS seller_username, u.display_name AS seller_name
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             JOIN users u ON u.id = oi.seller_id
             WHERE oi.order_id = ?
             ORDER BY oi.id ASC'
        );
        $stmt->execute([$orderId]);

        return $stmt->fetchAll();
    }

    public function markCancelled(int $orderId, float $refundAmount): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE orders SET status = ?, cancelled_at = NOW(), refund_amount = ? WHERE id = ?'
        );
        $stmt->execute(['cancelled', $refundAmount, $orderId]);
    }

    public function markCompleted(int $orderId): void
    {
        $stmt = Database::pdo()->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute(['completed', $orderId]);
    }
}
