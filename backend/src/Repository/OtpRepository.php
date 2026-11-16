<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Database;

final class OtpRepository
{
    public function create(int $userId, string $code, string $expiresAt): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO password_reset_otps (user_id, otp_code, expires_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $code, $expiresAt]);
    }

    public function findValid(int $userId, string $code): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM password_reset_otps
             WHERE user_id = ? AND otp_code = ? AND used_at IS NULL AND expires_at >= NOW()
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId, $code]);

        return $stmt->fetch() ?: null;
    }

    public function markUsed(int $id): void
    {
        $stmt = Database::pdo()->prepare('UPDATE password_reset_otps SET used_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }
}
