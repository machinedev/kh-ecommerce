<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\UploadService;
use App\Support\ApiResponse;
use App\Support\AppException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class UploadController
{
    public function __construct(private readonly UploadService $uploads = new UploadService())
    {
    }

    public function editor(Request $request, Response $response): Response
    {
        $file = $request->getUploadedFiles()['file'] ?? null;
        if ($file === null) {
            throw new AppException('VALIDATION_ERROR', 'File wajib diunggah.', 422);
        }

        $tmp = $file->getStream()->getMetadata('uri');
        if (!is_string($tmp) || $tmp === '') {
            throw new AppException('UPLOAD_FAILED', 'File tidak dapat diproses.', 400);
        }

        return ApiResponse::success(
            $this->uploads->editorUpload($this->currentUserId($request), [
                'name' => $file->getClientFilename(),
                'tmp_name' => $tmp,
                'type' => $file->getClientMediaType(),
            ])
        );
    }

    private function currentUserId(Request $request): int
    {
        return (int) $request->getAttribute('user')['id'];
    }
}
