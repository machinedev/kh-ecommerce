<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\ApiResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class HealthController
{
    public function health(Request $request, Response $response): Response
    {
        return ApiResponse::success([
            'status' => 'ok',
            'service' => 'kh-commerce',
            'time' => date(DATE_ATOM),
        ]);
    }
}
