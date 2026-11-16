<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ProductService;
use App\Support\ApiResponse;
use App\Support\AppException;
use App\Support\HttpClient;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ProductController
{
    public function __construct(private readonly ProductService $products = new ProductService())
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = (int) ($params['page'] ?? 1);
        $limit = (int) ($params['limit'] ?? 12);
        $q = (string) ($params['q'] ?? '');
        $category = isset($params['category']) && $params['category'] !== '' ? (int) $params['category'] : null;

        return ApiResponse::success($this->products->list($page, $limit, $q, $category));
    }

    public function home(Request $request, Response $response): Response
    {
        return ApiResponse::success($this->products->home());
    }

    public function search(Request $request, Response $response): Response
    {
        $q = (string) ($request->getQueryParams()['q'] ?? '');
        $data = $this->products->list(1, 24, $q, null);
        $heading = $q === '' ? 'Cari produk' : 'Hasil pencarian untuk "' . $q . '"';

        return ApiResponse::success(['heading' => $heading, 'items' => $data['items']]);
    }

    public function categories(Request $request, Response $response): Response
    {
        return ApiResponse::success($this->products->home()['categories']);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        return ApiResponse::success($this->products->show((string) $args['id']));
    }

    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        return ApiResponse::success($this->products->create($this->currentUserId($request), $data), 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        return ApiResponse::success($this->products->update((int) $args['id'], (array) $request->getParsedBody()));
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $this->products->delete((int) $args['id']);

        return ApiResponse::success(['message' => 'Produk dihapus.']);
    }

    public function comments(Request $request, Response $response, array $args): Response
    {
        return ApiResponse::success($this->products->comments((int) $args['id']));
    }

    public function addComment(Request $request, Response $response, array $args): Response
    {
        $data = (array) $request->getParsedBody();

        return ApiResponse::success($this->products->addComment((int) $args['id'], $this->currentUserId($request), $data), 201);
    }

    public function uploadDocument(Request $request, Response $response, array $args): Response
    {
        $xml = (string) $request->getBody()->getContents();

        return ApiResponse::success($this->products->importDocument((int) $args['id'], $xml));
    }

    public function downloadDocument(Request $request, Response $response, array $args): Response
    {
        $product = $this->products->show((string) $args['id']);
        if (empty($product['document_url'])) {
            throw new AppException('DOCUMENT_NOT_FOUND', 'Dokumen tidak ditemukan.', 404);
        }

        $path = $this->products->filePath(ltrim(str_replace('/uploads/', '', $product['document_url']), '/'));
        $response->getBody()->write((string) file_get_contents($path));

        return $response
            ->withHeader('Content-Type', 'application/xml')
            ->withHeader('Content-Disposition', 'inline; filename="document.xml"');
    }

    public function imagePreview(Request $request, Response $response): Response
    {
        $url = (string) ($request->getQueryParams()['url'] ?? '');
        $result = HttpClient::fetch($url);

        return ApiResponse::success([
            'url' => $url,
            'status' => $result['status'],
            'content' => $result['body'],
        ]);
    }

    private function currentUserId(Request $request): int
    {
        return (int) $request->getAttribute('user')['id'];
    }
}
