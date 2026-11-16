<?php

declare(strict_types=1);

namespace App\Domain;

final class Activity
{
    public const PRODUCT_CREATED = 'product_created';
    public const PRODUCT_UPDATED = 'product_updated';
    public const PURCHASE = 'purchase';
    public const COMMENT = 'comment';
    public const SELLER_TRANSACTION = 'seller_transaction';
    public const ORDER_CANCELLED = 'order_cancelled';
    public const REFUND = 'refund';
    public const PAYMENT_RECEIVED = 'payment_received';
    public const TOPUP = 'topup';
}
