<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UploadRepository;
use App\Support\FileStorage;

final class UploadService
{
    public function __construct(private readonly UploadRepository $uploads = new UploadRepository())
    {
    }

    public function editorUpload(int $userId, array $file): array
    {
        $stored = FileStorage::store($file);
        $this->uploads->create(
            $userId,
            $stored['original_name'],
            $stored['stored_name'],
            $stored['mime_type'],
            $stored['relative_path']
        );

        return ['url' => '/uploads/' . $stored['relative_path']];
    }
}
