<?php

declare(strict_types=1);

namespace App\Support;

use Slim\Psr7\Response;

final class ApiResponse
{
    public static function success(mixed $data = null, int $status = 200): Response
    {
        return self::json([
            'success' => true,
            'data' => $data,
            'request_id' => RequestContext::requestId(),
        ], $status);
    }

    /** @param array<string,mixed> $extra */
    public static function error(string $code, string $message, int $status = 400, array $extra = []): Response
    {
        $error = ['code' => $code, 'message' => $message];

        if ($extra !== []) {
            $error += $extra;
        }

        return self::json([
            'success' => false,
            'error' => $error,
            'request_id' => RequestContext::requestId(),
        ], $status);
    }

    /** @param array<string,mixed> $payload */
    public static function json(array $payload, int $status = 200): Response
    {
        $response = new Response($status);
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public static function html(string $html, int $status = 200): Response
    {
        $response = new Response($status);
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
