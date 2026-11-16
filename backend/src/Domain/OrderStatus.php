<?php

declare(strict_types=1);

namespace App\Domain;

final class OrderStatus
{
    public const PENDING_PAYMENT = 'pending_payment';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
}
