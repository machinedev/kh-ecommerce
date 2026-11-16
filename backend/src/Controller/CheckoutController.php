<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CheckoutService;
use App\Support\ApiResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class CheckoutController
{
    public function __construct(private readonly CheckoutService $checkout = new CheckoutService())
    {
    }

    public function store(Request $request, Response $response): Response
    {
        $userId = (int) $request->getAttribute('user')['id'];
        $data = (array) $request->getParsedBody();
        $couponCode = isset($data['coupon_code']) ? (string) $data['coupon_code'] : '';
        $paymentMethod = isset($data['payment_method']) ? (string) $data['payment_method'] : 'balance';
        $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

        return ApiResponse::success($this->checkout->checkout($userId, $couponCode, $paymentMethod, $items), 201);
    }
}
