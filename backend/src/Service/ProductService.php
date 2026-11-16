<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Activity;
use App\Repository\ActivityRepository;
use App\Repository\CategoryRepository;
use App\Repository\CommentRepository;
use App\Repository\ProductRepository;
use App\Support\AppException;
use App\Support\FileStorage;
use App\Support\Validator;

final class ProductService
{
    public function __construct(
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly CommentRepository $comments = new CommentRepository(),
        private readonly CategoryRepository $categories = new CategoryRepository(),
        private readonly ActivityRepository $activities = new ActivityRepository()
    ) {
    }

    public function list(int $page, int $limit, string $q, ?int $categoryId): array
    {
        $page = max(1, $page);
        $limit = min(50, max(1, $limit));
        $result = $this->products->paginate($page, $limit, trim($q), $categoryId);

        return [
            'items' => array_map([$this, 'view'], $result['items']),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $result['total'],
                'pages' => (int) ceil($result['total'] / $limit),
            ],
        ];
    }

    public function home(): array
    {
        return [
            'featured' => array_map([$this, 'view'], $this->products->featured()),
            'latest' => array_map([$this, 'view'], $this->products->latest()),
            'categories' => $this->categories->all(),
        ];
    }

    public function show(string $id): array
    {
        $product = $this->products->findByIdPath($id);
        if ($product === null) {
            throw new AppException('PRODUCT_NOT_FOUND', 'Produk tidak ditemukan.', 404);
        }

        return $this->view($product);
    }

    public function create(int $sellerId, array $data): array
    {
        Validator::required($data, ['title', 'description', 'price', 'stock']);
        $this->assertNumbers($data);

        $categoryId = isset($data['category_id']) && $data['category_id'] !== '' ? (int) $data['category_id'] : null;
        $imageUrl = isset($data['image_url']) && trim((string) $data['image_url']) !== '' ? (string) $data['image_url'] : null;

        $id = $this->products->create(
            $sellerId,
            $categoryId,
            trim((string) $data['title']),
            (string) $data['description'],
            (float) $data['price'],
            (int) $data['stock'],
            $imageUrl
        );

        $this->activities->add($sellerId, Activity::PRODUCT_CREATED, 'product', $id, 'Menambahkan produk ' . trim((string) $data['title']));

        return $this->view($this->products->findById($id));
    }

    public function update(int $id, array $data): array
    {
        $product = $this->products->findById($id);
        if ($product === null) {
            throw new AppException('PRODUCT_NOT_FOUND', 'Produk tidak ditemukan.', 404);
        }

        $fields = [];
        foreach (['title', 'description', 'image_url'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[$field] = (string) $data[$field];
            }
        }
        if (array_key_exists('category_id', $data)) {
            $fields['category_id'] = $data['category_id'] === '' || $data['category_id'] === null ? null : (int) $data['category_id'];
        }
        if (array_key_exists('price', $data)) {
            if (!Validator::nonNegativeNumber($data['price'])) {
                throw new AppException('VALIDATION_ERROR', 'Harga tidak boleh negatif.', 422);
            }
            $fields['price'] = (float) $data['price'];
        }
        if (array_key_exists('stock', $data)) {
            if (!is_numeric($data['stock']) || (int) $data['stock'] < 0) {
                throw new AppException('VALIDATION_ERROR', 'Stok tidak boleh negatif.', 422);
            }
            $fields['stock'] = (int) $data['stock'];
        }

        $this->products->update($id, $fields);
        $this->activities->add((int) $product['seller_id'], Activity::PRODUCT_UPDATED, 'product', $id, 'Memperbarui produk ' . $product['title']);

        return $this->view($this->products->findById($id));
    }

    public function delete(int $id): void
    {
        $product = $this->products->findById($id);
        if ($product === null) {
            throw new AppException('PRODUCT_NOT_FOUND', 'Produk tidak ditemukan.', 404);
        }

        $this->products->softDelete($id);
    }

    public function comments(int $productId): array
    {
        return array_map(static function (array $c): array {
            return [
                'id' => (int) $c['id'],
                'body' => $c['body'],
                'created_at' => $c['created_at'],
                'user' => [
                    'id' => (int) $c['user_id'],
                    'username' => $c['username'],
                    'display_name' => $c['display_name'],
                    'avatar_url' => $c['avatar_url'],
                ],
            ];
        }, $this->comments->byProduct($productId));
    }

    public function addComment(int $productId, int $userId, array $data): array
    {
        Validator::required($data, ['body']);
        $product = $this->products->findById($productId);
        if ($product === null) {
            throw new AppException('PRODUCT_NOT_FOUND', 'Produk tidak ditemukan.', 404);
        }

        $body = (string) $data['body'];
        $id = $this->comments->create($productId, $userId, $body);
        $this->activities->add($userId, Activity::COMMENT, 'comment', $id, 'Mengomentari ' . $product['title']);

        return ['id' => $id, 'body' => $body];
    }

    /**
     * @return array<string,mixed>
     */
    public function importDocument(int $productId, string $xml): array
    {
        $product = $this->products->findById($productId);
        if ($product === null) {
            throw new AppException('PRODUCT_NOT_FOUND', 'Produk tidak ditemukan.', 404);
        }
        if (trim($xml) === '') {
            throw new AppException('DOCUMENT_INVALID', 'Dokumen kosong.', 422);
        }

        $stored = bin2hex(random_bytes(8)) . '.xml';
        $dir = FileStorage::baseDir() . '/documents';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir . '/' . $stored, $xml);

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_DTDLOAD);
        libxml_clear_errors();

        if ($doc === false) {
            throw new AppException('DOCUMENT_INVALID', 'Dokumen tidak dapat diproses.', 422);
        }

        $relative = 'documents/' . $stored;
        $this->products->setDocument($productId, $relative);

        return [
            'product_id' => $productId,
            'document_url' => '/uploads/' . $relative,
            'parsed' => [
                'title' => isset($doc->title) ? (string) $doc->title : null,
                'content' => trim((string) $doc),
            ],
        ];
    }

    public function filePath(string $relativePath): string
    {
        $path = FileStorage::pathFor($relativePath);
        if (!is_file($path)) {
            throw new AppException('DOCUMENT_NOT_FOUND', 'Dokumen tidak ditemukan.', 404);
        }

        return $path;
    }

    /** @param array<string,mixed> $data */
    private function assertNumbers(array $data): void
    {
        if (!Validator::nonNegativeNumber($data['price'])) {
            throw new AppException('VALIDATION_ERROR', 'Harga tidak boleh negatif.', 422);
        }
        if (!is_numeric($data['stock']) || (int) $data['stock'] < 0) {
            throw new AppException('VALIDATION_ERROR', 'Stok tidak boleh negatif.', 422);
        }
    }

    /** @param array<string,mixed> $p */
    public function view(array $p): array
    {
        return [
            'id' => (int) $p['id'],
            'seller_id' => (int) $p['seller_id'],
            'title' => $p['title'],
            'description' => $p['description'],
            'price' => (float) $p['price'],
            'stock' => (int) $p['stock'],
            'category_id' => $p['category_id'] !== null ? (int) $p['category_id'] : null,
            'category_name' => $p['category_name'] ?? null,
            'image_url' => $p['image_url'],
            'document_url' => $p['document_path'] !== null ? '/uploads/' . $p['document_path'] : null,
            'seller' => [
                'id' => (int) $p['seller_id'],
                'username' => $p['seller_username'] ?? null,
                'display_name' => $p['seller_name'] ?? null,
            ],
            'created_at' => $p['created_at'],
        ];
    }
}
