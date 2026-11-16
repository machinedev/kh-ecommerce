<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BalanceService;
use App\Service\TopupService;
use App\Support\ApiResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class BalanceController
{
    public function __construct(
        private readonly BalanceService $balance = new BalanceService(),
        private readonly TopupService $topups = new TopupService()
    ) {
    }

    public function show(Request $request, Response $response): Response
    {
        return ApiResponse::success($this->balance->balance($this->currentUserId($request)));
    }

    public function topup(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $amount = $data['amount'] ?? null;

        return ApiResponse::success($this->topups->start($this->currentUserId($request), $amount), 201);
    }

    private function currentUserId(Request $request): int
    {
        return (int) $request->getAttribute('user')['id'];
    }
}
