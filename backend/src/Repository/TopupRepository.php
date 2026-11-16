<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Database;

final class TopupRepository
{
    public function create(
        int $userId,
        string $provider,
        string $providerOrderId,
        float $amount,
        string $status = 'credited'
    ): int {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO topup_transactions
                (user_id, provider, provider_order_id, amount, status)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $provider, $providerOrderId, $amount, $status]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function attachSnap(int $topupId, string $token, ?string $redirectUrl): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE topup_transactions SET snap_token = ?, redirect_url = ? WHERE id = ?'
        );
        $stmt->execute([$token, $redirectUrl, $topupId]);
    }

    public function findByProviderOrderId(string $providerOrderId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM topup_transactions WHERE provider_order_id = ? LIMIT 1'
        );
        $stmt->execute([$providerOrderId]);

        return $stmt->fetch() ?: null;
    }

    public function lockByProviderOrderId(string $providerOrderId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM topup_transactions WHERE provider_order_id = ? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$providerOrderId]);

        return $stmt->fetch() ?: null;
    }

    public function markStatus(
        int $topupId,
        string $status,
        ?string $rawPayload = null,
        bool $paid = false
    ): void {
        $sql = 'UPDATE topup_transactions SET status = ?';
        $params = [$status];

        if ($paid) {
            $sql .= ', paid_at = NOW()';
        }
        if ($rawPayload !== null) {
            $sql .= ', raw_payload = ?';
            $params[] = $rawPayload;
        }
        $sql .= ' WHERE id = ?';
        $params[] = $topupId;

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
    }
}
