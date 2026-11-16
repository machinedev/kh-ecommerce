<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\RequestContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestIdMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestId = bin2hex(random_bytes(8));
        RequestContext::setRequestId($requestId);

        $response = $handler->handle($request);

        return $response->withHeader('X-Request-Id', $requestId);
    }
}
