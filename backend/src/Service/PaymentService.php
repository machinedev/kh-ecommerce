<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Activity;
use App\Domain\OrderStatus;
use App\Domain\PaymentStatus;
use App\Repository\ActivityRepository;
use App\Repository\CouponRepository;
use App\Repository\OrderRepository;
use App\Repository\PaymentRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Support\AppException;
use App\Support\Config;
use App\Support\Database;

final class PaymentService
{
    public function __construct(
        private readonly PaymentRepository $payments = new PaymentRepository(),
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly CouponRepository $coupons = new CouponRepository(),
        private readonly ActivityRepository $activities = new ActivityRepository(),
        private readonly MidtransService $midtrans = new MidtransService()
    ) {
    }

    public function config(): array
    {
        return $this->midtrans->publicConfig();
    }

    public function startMidtrans(int $orderId, float $amount): array
    {
        $order = $this->orders->findById($orderId);
        if ($order === null) {
            throw new AppException('ORDER_NOT_FOUND', 'Pesanan tidak ditemukan.', 404);
        }

        $buyer = $this->users->findById((int) $order['buyer_id']);
        if ($buyer === null) {
            throw new AppException('USER_NOT_FOUND', 'Pengguna tidak ditemukan.', 404);
        }

        $providerOrderId = 'KH-' . $orderId . '-' . strtoupper(bin2hex(random_bytes(4)));
        $paymentId = $this->payments->create(
            $orderId,
            (int) $order['buyer_id'],
            'midtrans',
            $providerOrderId,
            $amount
        );

        try {
            $snap = $this->midtrans->createSnap($providerOrderId, $amount, $buyer);
        } catch (\Throwable $e) {
            $this->failPendingOrder($orderId, PaymentStatus::FAILED);
            throw $e;
        }

        $token = (string) ($snap['token'] ?? '');
        $redirectUrl = isset($snap['redirect_url']) ? (string) $snap['redirect_url'] : null;
        if ($token === '') {
            $this->failPendingOrder($orderId, PaymentStatus::FAILED);
            throw new AppException('PAYMENT_GATEWAY_ERROR', 'Token pembayaran tidak tersedia.', 502);
        }

        try {
            $this->payments->attachSnap($paymentId, $token, $redirectUrl);
        } catch (\Throwable $e) {
            $this->failPendingOrder($orderId, PaymentStatus::FAILED);
            throw $e;
        }

        return [
            'provider_order_id' => $providerOrderId,
            'snap_token' => $token,
            'redirect_url' => $redirectUrl,
        ];    }

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
            $payment = $this->payments->lockByProviderOrderId($providerOrderId);
            if ($payment === null) {
                throw new AppException('PAYMENT_NOT_FOUND', 'Transaksi pembayaran tidak ditemukan.', 404);
            }

            $this->midtrans->assertNotificationAmount((float) $payment['amount'], $payload);
            $status = $this->applyPaymentUpdate($payment, $payload);
            $this->payments->markStatus(
                (int) $payment['id'],
                $status,
                (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $status === PaymentStatus::PAID
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return ['message' => 'OK'];
    }

    public function syncOrder(int $orderId, int $userId): array
    {
        $order = $this->orders->findForBuyer($orderId, $userId);
        if ($order === null) {
            throw new AppException('ORDER_NOT_FOUND', 'Pesanan tidak ditemukan.', 404);
        }

        $payment = $this->payments->findByOrderId($orderId);
        if ($payment !== null
            && in_array((string) $payment['status'], [PaymentStatus::PENDING], true)
            && $this->midtrans->enabled()
        ) {
            try {
                $payload = $this->midtrans->status((string) $payment['provider_order_id']);
                $this->applyRemoteStatus((string) $payment['provider_order_id'], $payload);
            } catch (\Throwable) {
                // Keep the local state when the gateway cannot be reached.
            }
        }

        $order = $this->orders->findForBuyer($orderId, $userId);
        $payment = $this->payments->findByOrderId($orderId);

        return [
            'order_id' => $orderId,
            'status' => $order['status'] ?? OrderStatus::PENDING_PAYMENT,
            'total' => (float) ($order['total_amount'] ?? 0),
            'discount' => (float) ($order['discount_amount'] ?? 0),
            'payment_method' => $order['payment_method'] ?? 'balance',
            'payment_status' => $payment['status'] ?? null,
            'snap_token' => $payment['snap_token'] ?? null,
            'redirect_url' => $payment['redirect_url'] ?? null,
        ];
    }

    public function cancelPending(int $orderId): array
    {
        $order = $this->orders->findById($orderId);
        if ($order === null) {
            throw new AppException('ORDER_NOT_FOUND', 'Pesanan tidak ditemukan.', 404);
        }

        $payment = $this->payments->findByOrderId($orderId);
        if ($payment !== null && $this->midtrans->enabled()) {
            try {
                $this->midtrans->expire((string) $payment['provider_order_id']);
            } catch (\Throwable) {
                // Local state still has to be released when the gateway is unreachable.
            }
        }

        $this->failPendingOrder($orderId, PaymentStatus::CANCELLED);

        return [
            'order_id' => $orderId,
            'status' => OrderStatus::CANCELLED,
            'refund_amount' => 0.0,
        ];
    }

    private function applyRemoteStatus(string $providerOrderId, array $payload): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $payment = $this->payments->lockByProviderOrderId($providerOrderId);
            if ($payment === null) {
                throw new AppException('PAYMENT_NOT_FOUND', 'Transaksi pembayaran tidak ditemukan.', 404);
            }

            $this->midtrans->assertNotificationAmount((float) $payment['amount'], $payload);
            $status = $this->applyPaymentUpdate($payment, $payload);
            $this->payments->markStatus(
                (int) $payment['id'],
                $status,
                (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $status === PaymentStatus::PAID
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function applyPaymentUpdate(array $payment, array $payload): string
    {
        if ((string) $payment['status'] === PaymentStatus::PAID) {
            return PaymentStatus::PAID;
        }

        $transactionStatus = strtolower((string) ($payload['transaction_status'] ?? ''));
        $fraudStatus = strtolower((string) ($payload['fraud_status'] ?? ''));

        if ($transactionStatus === 'capture' && $fraudStatus !== 'accept') {
            return (string) $payment['status'];
        }

        if (in_array($transactionStatus, ['capture', 'settlement'], true)) {
            return $this->settle($payment);
        }

        if ($transactionStatus === 'expire') {
            $this->fail($payment, PaymentStatus::EXPIRED);
            return PaymentStatus::EXPIRED;
        }

        if (in_array($transactionStatus, ['deny', 'cancel', 'failure'], true)) {
            $this->fail($payment, PaymentStatus::FAILED);
            return PaymentStatus::FAILED;
        }

        return (string) $payment['status'];
    }

    private function settle(array $payment): string
    {
        if ((string) $payment['status'] === PaymentStatus::PAID) {
            return PaymentStatus::PAID;
        }

        $orderId = (int) $payment['order_id'];
        $order = $this->orders->lockById($orderId);
        if ($order === null || (string) $order['status'] !== OrderStatus::PENDING_PAYMENT) {
            return (string) $payment['status'];
        }

        $items = $this->orders->items($orderId);
        foreach ($items as $item) {
            $subtotal = (float) $item['subtotal'];
            $this->users->addBalance((int) $item['seller_id'], $subtotal);
            $this->activities->add(
                (int) $item['seller_id'],
                Activity::SELLER_TRANSACTION,
                'order_item',
                $orderId,
                'Penjualan ' . $item['title']
            );
            $this->activities->add(
                (int) $order['buyer_id'],
                Activity::PURCHASE,
                'order',
                $orderId,
                'Membeli ' . $item['title']
            );
        }

        $this->activities->add(
            (int) $order['buyer_id'],
            Activity::PAYMENT_RECEIVED,
            'order',
            $orderId,
            'Pembayaran pesanan #' . $orderId . ' diterima'
        );
        $this->orders->markCompleted($orderId);

        return PaymentStatus::PAID;
    }

    private function fail(array $payment, string $status): void
    {
        if ((string) $payment['status'] === PaymentStatus::PAID) {
            return;
        }

        $orderId = (int) $payment['order_id'];
        $order = $this->orders->lockById($orderId);
        if ($order !== null && (string) $order['status'] === OrderStatus::PENDING_PAYMENT) {
            foreach ($this->orders->items($orderId) as $item) {
                $this->products->incrementStock((int) $item['product_id'], (int) $item['quantity']);
            }
            if (!empty($order['coupon_id'])) {
                $this->coupons->releaseRedemption((int) $order['coupon_id'], $orderId);
            }
            $this->orders->markCancelled($orderId, 0.0);
        }
    }

    private function failPendingOrder(int $orderId, string $status): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $payment = $this->payments->lockByOrderId($orderId);
            $order = $this->orders->lockById($orderId);

            if ($order !== null && (string) $order['status'] === OrderStatus::PENDING_PAYMENT) {
                foreach ($this->orders->items($orderId) as $item) {
                    $this->products->incrementStock((int) $item['product_id'], (int) $item['quantity']);
                }
                if (!empty($order['coupon_id'])) {
                    $this->coupons->releaseRedemption((int) $order['coupon_id'], $orderId);
                }
                $this->orders->markCancelled($orderId, 0.0);
            }

            if ($payment !== null && (string) $payment['status'] !== PaymentStatus::PAID) {
                $this->payments->markStatus((int) $payment['id'], $status);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
