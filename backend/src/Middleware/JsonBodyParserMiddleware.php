<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class JsonBodyParserMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (stripos($contentType, 'application/json') !== false) {
            $raw = (string) $request->getBody();
            $request->getBody()->rewind();
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $request = $request->withParsedBody($decoded);
            }
        }

        return $handler->handle($request);
    }
}
