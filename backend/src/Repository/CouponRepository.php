<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Database;

final class CouponRepository
{
    public function findByCode(string $code): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM coupons WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);

        return $stmt->fetch() ?: null;
    }

    public function redemptionsByUser(int $couponId, int $userId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM coupon_redemptions WHERE coupon_id = ? AND user_id = ?'
        );
        $stmt->execute([$couponId, $userId]);

        return (int) $stmt->fetchColumn();
    }

    public function incrementUsage(int $couponId): void
    {
        $stmt = Database::pdo()->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?');
        $stmt->execute([$couponId]);
    }

    public function recordRedemption(int $couponId, int $userId, int $orderId, float $discount): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO coupon_redemptions (coupon_id, user_id, order_id, discount_amount) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$couponId, $userId, $orderId, $discount]);
    }

    public function releaseRedemption(int $couponId, int $orderId): void
    {
        $stmt = Database::pdo()->prepare(
            'DELETE FROM coupon_redemptions WHERE coupon_id = ? AND order_id = ?'
        );
        $stmt->execute([$couponId, $orderId]);

        if ($stmt->rowCount() > 0) {
            $update = Database::pdo()->prepare(
                'UPDATE coupons SET used_count = GREATEST(used_count - 1, 0) WHERE id = ?'
            );
            $update->execute([$couponId]);
        }
    }
}
