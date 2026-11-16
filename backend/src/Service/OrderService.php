<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Activity;
use App\Domain\OrderStatus;
use App\Repository\ActivityRepository;
use App\Repository\OrderRepository;
use App\Repository\PaymentRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Support\AppException;
use App\Support\Database;

final class OrderService
{
    public function __construct(
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly ActivityRepository $activities = new ActivityRepository(),
        private readonly PaymentRepository $payments = new PaymentRepository(),
        private readonly PaymentService $paymentService = new PaymentService()
    ) {
    }

    public function listForBuyer(int $buyerId): array
    {
        $orders = $this->orders->byBuyer($buyerId);

        return array_map(function (array $order): array {
            return [
                'id' => (int) $order['id'],
                'total' => (float) $order['total_amount'],
                'discount' => (float) $order['discount_amount'],
                'refund_amount' => (float) $order['refund_amount'],
                'payment_method' => $order['payment_method'] ?? 'balance',
                'payment_status' => $order['payment_status'] ?? null,
                'snap_token' => $order['snap_token'] ?? null,
                'redirect_url' => $order['redirect_url'] ?? null,
                'status' => $order['status'],
                'cancelled_at' => $order['cancelled_at'],
                'created_at' => $order['created_at'],
                'items' => $this->orders->items((int) $order['id']),
            ];
        }, $orders);
    }

    public function detail(int $orderId, int $buyerId): array
    {
        $order = $this->orders->findForBuyer($orderId, $buyerId);
        if ($order === null) {
            throw new AppException('ORDER_NOT_FOUND', 'Pesanan tidak ditemukan.', 404);
        }

        $payment = $this->payments->findByOrderId($orderId);

        return [
            'id' => (int) $order['id'],
            'total' => (float) $order['total_amount'],
            'discount' => (float) $order['discount_amount'],
            'refund_amount' => (float) $order['refund_amount'],
            'payment_method' => $order['payment_method'] ?? 'balance',
            'payment_status' => $payment['status'] ?? null,
            'snap_token' => $payment['snap_token'] ?? null,
            'redirect_url' => $payment['redirect_url'] ?? null,
            'status' => $order['status'],
            'cancelled_at' => $order['cancelled_at'],
            'created_at' => $order['created_at'],
            'items' => $this->orders->items($orderId),
        ];
    }

    public function cancel(int $orderId, int $userId): array
    {
        $order = $this->orders->findById($orderId);
        if ($order === null) {
            throw new AppException('ORDER_NOT_FOUND', 'Pesanan tidak ditemukan.', 404);
        }
        if ((string) $order['status'] === OrderStatus::CANCELLED) {
            throw new AppException('ORDER_ALREADY_CANCELLED', 'Pesanan sudah dibatalkan.', 409);
        }

        if (($order['payment_method'] ?? 'balance') === 'midtrans') {
            if ((string) $order['status'] === OrderStatus::PENDING_PAYMENT) {
                return $this->paymentService->cancelPending($orderId);
            }

            throw new AppException(
                'CANCEL_NOT_SUPPORTED',
                'Pembatalan pesanan dengan pembayaran gateway belum tersedia.',
                422
            );
        }

        usleep(150000);

        $items = $this->orders->items($orderId);
        $refund = (float) $order['total_amount'];
        $buyerId = (int) $order['buyer_id'];

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $this->users->addBalance($buyerId, $refund);

            foreach ($items as $item) {
                $subtotal = (float) $item['subtotal'];
                $this->users->addBalance((int) $item['seller_id'], -$subtotal);
                $this->products->incrementStock((int) $item['product_id'], (int) $item['quantity']);
            }

            $this->orders->markCancelled($orderId, $refund);
            $this->activities->add($buyerId, Activity::ORDER_CANCELLED, 'order', $orderId, 'Membatalkan pesanan #' . $orderId);
            $this->activities->add($userId, Activity::REFUND, 'order', $orderId, 'Menerima pengembalian dana pesanan #' . $orderId);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return [
            'order_id' => $orderId,
            'status' => OrderStatus::CANCELLED,
            'refund_amount' => $refund,
        ];
    }
}
