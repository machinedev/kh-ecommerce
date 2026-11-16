<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepository;
use App\Support\AppException;

final class BalanceService
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly TopupService $topups = new TopupService()
    ) {
    }

    public function balance(int $userId): array
    {
        $user = $this->users->findById($userId);
        if ($user === null) {
            throw new AppException('USER_NOT_FOUND', 'Pengguna tidak ditemukan.', 404);
        }

        return [
            'balance' => (float) $user['balance'],
            'topup_available' => $this->topups->available(),
        ];
    }
}
