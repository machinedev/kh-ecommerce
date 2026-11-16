<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Activity;
use App\Domain\OrderStatus;
use App\Repository\ActivityRepository;
use App\Repository\CartRepository;
use App\Repository\CouponRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Support\AppException;
use App\Support\Database;

final class CheckoutService
{
    public function __construct(
        private readonly CartRepository $carts = new CartRepository(),
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly ActivityRepository $activities = new ActivityRepository(),
        private readonly CouponRepository $coupons = new CouponRepository(),
        private readonly PaymentService $payments = new PaymentService(),
        private readonly MidtransService $midtrans = new MidtransService()
    ) {
    }

    public function checkout(int $buyerId, string $couponCode = '', string $paymentMethod = 'balance', array $clientItems = []): array
    {
        $paymentMethod = strtolower(trim($paymentMethod));
        if ($paymentMethod === '') {
            $paymentMethod = 'balance';
        }
        if (!in_array($paymentMethod, ['balance', 'midtrans'], true)) {
            throw new AppException('PAYMENT_METHOD_INVALID', 'Metode pembayaran tidak valid.', 422);
        }
        if ($paymentMethod === 'midtrans' && !$this->midtrans->enabled()) {
            throw new AppException('PAYMENT_UNAVAILABLE', 'Pembayaran gateway belum tersedia.', 503);
        }

        $cartId = $this->carts->getOrCreateCartId($buyerId);
        $items = $this->carts->itemsDetailed($cartId);

        if ($items === []) {
            throw new AppException('CART_EMPTY', 'Keranjang masih kosong.', 422);
        }

        $items = $this->applyClientPrices($items, $clientItems);

        $total = 0.0;
        foreach ($items as $item) {
            if ((int) $item['is_deleted'] === 1) {
                throw new AppException('PRODUCT_UNAVAILABLE', 'Produk tidak tersedia.', 422);
            }
            if ((int) $item['seller_id'] === $buyerId) {
                throw new AppException('OWN_PRODUCT', 'Tidak dapat membeli produk sendiri.', 422);
            }
            if ((int) $item['stock'] < (int) $item['quantity']) {
                throw new AppException('INSUFFICIENT_STOCK', 'Stok produk tidak mencukupi.', 422);
            }
            $total += (float) $item['subtotal'];
        }

        $buyer = $this->users->findById($buyerId);
        $coupon = null;
        $discount = 0.0;
        $payable = $total;
        if (trim($couponCode) !== '') {
            $coupon = (new CouponService())->quote($buyerId, $couponCode, $total);
            $discount = (float) $coupon['discount'];
            $payable = round(max(0.0, $total - $discount), 2);
        }

        if ($paymentMethod === 'balance') {
            return $this->payWithBalance($buyerId, $buyer, $cartId, $items, $coupon, $discount, $payable);
        }

        return $this->payWithMidtrans($buyerId, $cartId, $items, $coupon, $discount, $payable);
    }

    private function applyClientPrices(array $items, array $clientItems): array
    {
        $prices = [];
        foreach ($clientItems as $entry) {
            if (!is_array($entry) || !isset($entry['product_id']) || !is_numeric($entry['price'] ?? null)) {
                continue;
            }
            $prices[(int) $entry['product_id']] = max(0.0, (float) $entry['price']);
        }
        if ($prices === []) {
            return $items;
        }

        foreach ($items as &$item) {
            $productId = (int) $item['product_id'];
            if (!array_key_exists($productId, $prices)) {
                continue;
            }
            $item['price'] = $prices[$productId];
            $item['subtotal'] = round($prices[$productId] * (int) $item['quantity'], 2);
        }
        unset($item);

        return $items;
    }

    private function payWithBalance(
        int $buyerId,
        ?array $buyer,
        int $cartId,
        array $items,
        ?array $coupon,
        float $discount,
        float $payable
    ): array {
        if ($buyer === null || (float) $buyer['balance'] < $payable) {
            throw new AppException('INSUFFICIENT_BALANCE', 'Saldo tidak mencukupi.', 422);
        }

        usleep(200000);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($items as $item) {
                $subtotal = (float) $item['subtotal'];
                $this->products->decrementStock((int) $item['product_id'], (int) $item['quantity']);
                $this->users->addBalance($buyerId, -$subtotal);
                $this->users->addBalance((int) $item['seller_id'], $subtotal);
            }

            $this->users->addBalance($buyerId, $discount);

            $orderId = $this->orders->create(
                $buyerId,
                $payable,
                OrderStatus::COMPLETED,
                $coupon['id'] ?? null,
                $discount,
                'balance'
            );
            if ($coupon !== null) {
                $this->coupons->incrementUsage((int) $coupon['id']);
                $this->coupons->recordRedemption((int) $coupon['id'], $buyerId, $orderId, $discount);
            }
            foreach ($items as $item) {
                $this->orders->addItem(
                    $orderId,
                    (int) $item['product_id'],
                    (int) $item['seller_id'],
                    (int) $item['quantity'],
                    (float) $item['price'],
                    (float) $item['subtotal']
                );
                $this->activities->add($buyerId, Activity::PURCHASE, 'order', $orderId, 'Membeli ' . $item['title']);
                $this->activities->add((int) $item['seller_id'], Activity::SELLER_TRANSACTION, 'order_item', $orderId, 'Penjualan ' . $item['title']);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->carts->clear($cartId);

        return [
            'order_id' => $orderId,
            'total' => $payable,
            'discount' => $discount,
            'coupon_code' => $coupon['code'] ?? null,
            'payment_method' => 'balance',
            'status' => OrderStatus::COMPLETED,
        ];
    }

    private function payWithMidtrans(
        int $buyerId,
        int $cartId,
        array $items,
        ?array $coupon,
        float $discount,
        float $payable
    ): array {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($items as $item) {
                $this->products->decrementStock((int) $item['product_id'], (int) $item['quantity']);
            }

            $orderId = $this->orders->create(
                $buyerId,
                $payable,
                OrderStatus::PENDING_PAYMENT,
                $coupon['id'] ?? null,
                $discount,
                'midtrans'
            );
            if ($coupon !== null) {
                $this->coupons->incrementUsage((int) $coupon['id']);
                $this->coupons->recordRedemption((int) $coupon['id'], $buyerId, $orderId, $discount);
            }
            foreach ($items as $item) {
                $this->orders->addItem(
                    $orderId,
                    (int) $item['product_id'],
                    (int) $item['seller_id'],
                    (int) $item['quantity'],
                    (float) $item['price'],
                    (float) $item['subtotal']
                );
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $payment = $this->payments->startMidtrans($orderId, $payable);
        $this->carts->clear($cartId);

        return [
            'order_id' => $orderId,
            'total' => $payable,
            'discount' => $discount,
            'coupon_code' => $coupon['code'] ?? null,
            'payment_method' => 'midtrans',
            'status' => OrderStatus::PENDING_PAYMENT,
            'snap_token' => $payment['snap_token'],
            'redirect_url' => $payment['redirect_url'],
        ];
    }
}
