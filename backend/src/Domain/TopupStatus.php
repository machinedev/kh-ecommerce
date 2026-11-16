<?php

declare(strict_types=1);

namespace App\Domain;

final class TopupStatus
{
    public const CREDITED = 'credited';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const EXPIRED = 'expired';
}
