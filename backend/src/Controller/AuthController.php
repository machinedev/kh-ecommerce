<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthService;
use App\Support\ApiResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController
{
    public function __construct(private readonly AuthService $auth = new AuthService())
    {
    }

    public function register(Request $request, Response $response): Response
    {
        return ApiResponse::success($this->auth->register((array) $request->getParsedBody()), 201);
    }

    public function login(Request $request, Response $response): Response
    {
        return ApiResponse::success($this->auth->login((array) $request->getParsedBody()));
    }

    public function forgotPassword(Request $request, Response $response): Response
    {
        return ApiResponse::success($this->auth->forgotPassword((array) $request->getParsedBody()));
    }

    public function verifyOtp(Request $request, Response $response): Response
    {
        return ApiResponse::success($this->auth->verifyOtp((array) $request->getParsedBody()));
    }

    public function resetPassword(Request $request, Response $response): Response
    {
        return ApiResponse::success($this->auth->resetPassword((array) $request->getParsedBody()));
    }
}
