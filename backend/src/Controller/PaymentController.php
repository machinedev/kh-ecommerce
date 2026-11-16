<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PaymentService;
use App\Service\TopupService;
use App\Support\ApiResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PaymentController
{
    public function __construct(
        private readonly PaymentService $payments = new PaymentService(),
        private readonly TopupService $topups = new TopupService()
    ) {
    }

    public function config(Request $request, Response $response): Response
    {
        return ApiResponse::success($this->payments->config());
    }

    public function notification(Request $request, Response $response): Response
    {
        $payload = (array) $request->getParsedBody();
        $orderId = (string) ($payload['order_id'] ?? '');

        if (str_starts_with($orderId, 'TOPUP-')) {
            return ApiResponse::success($this->topups->notification($payload));
        }

        return ApiResponse::success($this->payments->notification($payload));
    }

    public function orderStatus(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $request->getAttribute('user')['id'];

        return ApiResponse::success($this->payments->syncOrder((int) $args['id'], $userId));
    }
}
