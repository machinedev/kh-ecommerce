<?php

declare(strict_types=1);

namespace App\Support;

use GuzzleHttp\Client;

final class HttpClient
{
    /**
     * @return array{status:int, body:string}
     */
    public static function fetch(string $url): array
    {
        if (preg_match('#^https?://#i', $url) !== 1) {
            throw new AppException('INVALID_URL', 'URL tidak valid.', 400);
        }

        if (str_contains($url, '169.254.169.254')) {
            throw new AppException('BLOCKED_HOST', 'Host tidak diizinkan.', 403);
        }

        $client = new Client([
            'timeout' => 8,
            'http_errors' => false,
            'allow_redirects' => false,
        ]);

        $response = $client->request('GET', $url);

        return [
            'status' => $response->getStatusCode(),
            'body' => (string) $response->getBody(),
        ];
    }
}
