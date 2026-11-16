<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Repository\UserRepository;
use App\Support\ApiResponse;
use App\Support\Jwt;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = self::userFromRequest($request);
        if ($user === null) {
            return ApiResponse::error('UNAUTHENTICATED', 'Autentikasi diperlukan.', 401);
        }

        return $handler->handle($request->withAttribute('user', $user));
    }

    public static function tokenFromRequest(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        if (preg_match('/Bearer\s+(.+)/i', $header, $m) === 1) {
            return trim($m[1]);
        }

        $token = $request->getQueryParams()['token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    public static function userFromRequest(ServerRequestInterface $request): ?array
    {
        $token = self::tokenFromRequest($request);
        if ($token === null) {
            return null;
        }

        $payload = Jwt::decode($token);
        if ($payload === null || !isset($payload['sub'])) {
            return null;
        }

        $user = (new UserRepository())->findById((int) $payload['sub']);

        return $user === null ? null : $user;
    }
}
