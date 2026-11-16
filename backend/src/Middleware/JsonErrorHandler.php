<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\RequestContext;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Interfaces\ErrorHandlerInterface;
use Slim\Psr7\Response as SlimResponse;
use Throwable;

final class JsonErrorHandler implements ErrorHandlerInterface
{
    public function __invoke(
        Request $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): Response {
        if ($exception instanceof \App\Support\AppException) {
            return \App\Support\ApiResponse::error(
                $exception->errorCode(),
                $exception->getMessage(),
                $exception->status(),
                $exception->context()
            );
        }

        $status = $exception instanceof \Slim\Exception\HttpNotFoundException ? 404 : 500;
        $code = $status === 404 ? 'NOT_FOUND' : 'SERVER_ERROR';
        $message = $status === 404 ? 'Endpoint tidak ditemukan.' : 'Terjadi kesalahan pada server.';

        $extra = [];
        if (\App\Support\Config::debug()) {
            $extra['debug'] = [
                'type' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => explode("\n", $exception->getTraceAsString()),
            ];
            if ($exception instanceof \PDOException) {
                $code = 'DATABASE_ERROR';
                $extra['debug']['database'] = [
                    'sqlstate' => $exception->getCode(),
                    'driver' => $exception->errorInfo[1] ?? null,
                ];
            }
        }

        error_log(sprintf('[%s] %s: %s', RequestContext::requestId(), $exception::class, $exception->getMessage()));

        return \App\Support\ApiResponse::error($code, $message, $status, $extra);
    }
}
