<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\OrderService;
use App\Support\ApiResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class OrderController
{
    public function __construct(private readonly OrderService $orders = new OrderService())
    {
    }

    public function index(Request $request, Response $response): Response
    {
        return ApiResponse::success($this->orders->listForBuyer($this->currentUserId($request)));
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        return ApiResponse::success($this->orders->detail((int) $args['id'], $this->currentUserId($request)));
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        return ApiResponse::success($this->orders->cancel((int) $args['id'], $this->currentUserId($request)));
    }

    private function currentUserId(Request $request): int
    {
        return (int) $request->getAttribute('user')['id'];
    }
}
