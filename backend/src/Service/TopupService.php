<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Activity;
use App\Domain\TopupStatus;
use App\Repository\ActivityRepository;
use App\Repository\TopupRepository;
use App\Repository\UserRepository;
use App\Support\AppException;
use App\Support\Config;
use App\Support\Database;

final class TopupService
{
    public function __construct(
        private readonly TopupRepository $topups = new TopupRepository(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly ActivityRepository $activities = new ActivityRepository(),
        private readonly MidtransService $midtrans = new MidtransService()
    ) {
    }

    public function available(): bool
    {
        return !Config::bool('TOPUP_MAINTENANCE', true) && $this->midtrans->enabled();
    }

    public function start(int $userId, mixed $requestedAmount): array
    {
        if (!$this->available()) {
            throw new AppException('TOPUP_UNAVAILABLE', 'Top up belum tersedia.', 503);
        }
        if (!is_numeric($requestedAmount)) {
            throw new AppException('TOPUP_AMOUNT_INVALID', 'Nominal top up tidak valid.', 422);
        }

        $amount = round((float) $requestedAmount, 2);
        if ($amount < 10000 || $amount > 10000000) {
            throw new AppException('TOPUP_AMOUNT_INVALID', 'Nominal top up tidak valid.', 422);
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            throw new AppException('USER_NOT_FOUND', 'Pengguna tidak ditemukan.', 404);
        }

        $providerOrderId = 'TOPUP-' . $userId . '-' . strtoupper(bin2hex(random_bytes(4)));
        $topupId = $this->topups->create($userId, 'midtrans', $providerOrderId, $amount);

        $snapToken = '';
        $redirectUrl = null;
        try {
            $finishUrl = rtrim((string) Config::get('APP_URL', 'http://localhost:8080'), '/') . '/topup.html';
            $snap = $this->midtrans->createSnap($providerOrderId, $amount, $user, $finishUrl);
            $snapToken = (string) ($snap['token'] ?? '');
            $redirectUrl = isset($snap['redirect_url']) ? (string) $snap['redirect_url'] : null;
            if ($snapToken !== '') {
                $this->topups->attachSnap($topupId, $snapToken, $redirectUrl);
            }
        } catch (\Throwable) {
            $snapToken = '';
            $redirectUrl = null;
        }

        $this->users->addBalance($userId, $amount);
        $this->activities->add(
            $userId,
            Activity::TOPUP,
            'topup',
            $topupId,
            'Top up saldo sebesar ' . number_format($amount, 0, ',', '.')
        );

        $fresh = $this->users->findById($userId);

        return [
            'provider_order_id' => $providerOrderId,
            'amount' => $amount,
            'balance' => (float) ($fresh['balance'] ?? $user['balance']),
            'payment_status' => $snapToken !== '' ? 'pending' : 'unavailable',
            'snap_token' => $snapToken !== '' ? $snapToken : null,
            'redirect_url' => $redirectUrl,
        ];
    }

    public function notification(array $payload): array
    {
        $this->midtrans->verifyNotification($payload);

        $providerOrderId = (string) ($payload['order_id'] ?? '');
        if ($providerOrderId === '') {
            throw new AppException('PAYMENT_INVALID', 'Notifikasi pembayaran tidak valid.', 400);
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $topup = $this->topups->lockByProviderOrderId($providerOrderId);
            if ($topup === null) {
                throw new AppException('PAYMENT_NOT_FOUND', 'Transaksi top up tidak ditemukan.', 404);
            }

            $this->midtrans->assertNotificationAmount((float) $topup['amount'], $payload);

            if ((string) $topup['status'] !== TopupStatus::PAID) {
                $transactionStatus = strtolower((string) ($payload['transaction_status'] ?? ''));
                $fraudStatus = strtolower((string) ($payload['fraud_status'] ?? ''));
                $status = null;

                if (in_array($transactionStatus, ['capture', 'settlement'], true) && $fraudStatus !== 'challenge') {
                    $status = TopupStatus::PAID;
                } elseif ($transactionStatus === 'expire') {
                    $status = TopupStatus::EXPIRED;
                } elseif (in_array($transactionStatus, ['deny', 'cancel', 'failure'], true)) {
                    $status = TopupStatus::FAILED;
                }

                if ($status !== null) {
                    $this->topups->markStatus(
                        (int) $topup['id'],
                        $status,
                        (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        $status === TopupStatus::PAID
                    );
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return ['message' => 'OK'];
    }
}
