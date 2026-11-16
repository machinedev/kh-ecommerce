<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Database;

final class PaymentRepository
{
    public function create(
        int $orderId,
        int $userId,
        string $provider,
        string $providerOrderId,
        float $amount,
        string $status = 'pending'
    ): int {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO payment_transactions
                (order_id, user_id, provider, provider_order_id, amount, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$orderId, $userId, $provider, $providerOrderId, $amount, $status]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function attachSnap(int $paymentId, string $token, ?string $redirectUrl): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE payment_transactions SET snap_token = ?, redirect_url = ? WHERE id = ?'
        );
        $stmt->execute([$token, $redirectUrl, $paymentId]);
    }

    public function findByOrderId(int $orderId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM payment_transactions WHERE order_id = ? LIMIT 1'
        );
        $stmt->execute([$orderId]);

        return $stmt->fetch() ?: null;
    }

    public function findByProviderOrderId(string $providerOrderId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM payment_transactions WHERE provider_order_id = ? LIMIT 1'
        );
        $stmt->execute([$providerOrderId]);

        return $stmt->fetch() ?: null;
    }

    public function lockByProviderOrderId(string $providerOrderId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM payment_transactions WHERE provider_order_id = ? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$providerOrderId]);

        return $stmt->fetch() ?: null;
    }

    public function lockByOrderId(int $orderId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM payment_transactions WHERE order_id = ? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$orderId]);

        return $stmt->fetch() ?: null;
    }

    public function markStatus(
        int $paymentId,
        string $status,
        ?string $rawPayload = null,
        bool $paid = false
    ): void {
        $sql = 'UPDATE payment_transactions SET status = ?';
        $params = [$status];

        if ($paid) {
            $sql .= ', paid_at = NOW()';
        }
        if ($rawPayload !== null) {
            $sql .= ', raw_payload = ?';
            $params[] = $rawPayload;
        }
        $sql .= ' WHERE id = ?';
        $params[] = $paymentId;

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
    }
}
