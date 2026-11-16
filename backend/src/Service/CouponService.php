<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\CouponRepository;
use App\Support\AppException;

final class CouponService
{
    public function __construct(private readonly CouponRepository $coupons = new CouponRepository())
    {
    }

    /**
     * @return array{id:int, code:string, discount:float}
     */
    public function quote(int $userId, string $code, float $subtotal): array
    {
        $normalized = strtoupper(trim($code));
        if ($normalized === '') {
            throw new AppException('COUPON_INVALID', 'Kode promo tidak valid.', 422);
        }

        $coupon = $this->coupons->findByCode($normalized);
        if ($coupon === null || (int) $coupon['is_active'] !== 1) {
            throw new AppException('COUPON_INVALID', 'Kode promo tidak valid.', 422);
        }

        $now = time();
        if (!empty($coupon['starts_at']) && strtotime((string) $coupon['starts_at']) > $now) {
            throw new AppException('COUPON_INVALID', 'Kode promo belum berlaku.', 422);
        }
        if (!empty($coupon['expires_at']) && strtotime((string) $coupon['expires_at']) < $now) {
            throw new AppException('COUPON_EXPIRED', 'Kode promo sudah berakhir.', 422);
        }
        if ((int) $coupon['max_uses'] > 0 && (int) $coupon['used_count'] >= (int) $coupon['max_uses']) {
            throw new AppException('COUPON_EXHAUSTED', 'Kuota kode promo sudah habis.', 422);
        }
        if ((int) $coupon['per_user_limit'] > 0
            && $this->coupons->redemptionsByUser((int) $coupon['id'], $userId) >= (int) $coupon['per_user_limit']
        ) {
            throw new AppException('COUPON_ALREADY_USED', 'Kode promo sudah pernah digunakan.', 422);
        }

        $value = (float) $coupon['discount_value'];
        $discount = (string) $coupon['discount_type'] === 'percent'
            ? $subtotal * $value / 100
            : $value;

        if ($coupon['max_discount'] !== null) {
            $discount = min($discount, (float) $coupon['max_discount']);
        }

        $discount = round(min($discount, $subtotal), 2);
        if ($discount <= 0) {
            throw new AppException('COUPON_INVALID', 'Kode promo tidak dapat digunakan.', 422);
        }

        return [
            'id' => (int) $coupon['id'],
            'code' => (string) $coupon['code'],
            'discount' => $discount,
        ];
    }
}
