<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use App\Support\AppException;
use App\Support\Validator;

final class CartService
{
    public function __construct(
        private readonly CartRepository $carts = new CartRepository(),
        private readonly ProductRepository $products = new ProductRepository()
    ) {
    }

    public function view(int $userId): array
    {
        $cartId = $this->carts->getOrCreateCartId($userId);
        $items = $this->carts->itemsDetailed($cartId);

        $total = 0.0;
        $normalized = [];
        foreach ($items as $item) {
            $total += (float) $item['subtotal'];
            $normalized[] = [
                'id' => (int) $item['item_id'],
                'product_id' => (int) $item['product_id'],
                'title' => $item['title'],
                'price' => (float) $item['price'],
                'quantity' => (int) $item['quantity'],
                'stock' => (int) $item['stock'],
                'subtotal' => (float) $item['subtotal'],
                'seller' => [
                    'id' => (int) $item['seller_id'],
                    'username' => $item['seller_username'],
                    'display_name' => $item['seller_name'],
                ],
            ];
        }

        return ['items' => $normalized, 'total' => $total];
    }

    public function add(int $userId, array $data): array
    {
        Validator::required($data, ['product_id', 'quantity']);
        if (!Validator::positiveInt($data['quantity'])) {
            throw new AppException('VALIDATION_ERROR', 'Jumlah tidak valid.', 422);
        }

        $product = $this->products->findById((int) $data['product_id']);
        if ($product === null) {
            throw new AppException('PRODUCT_NOT_FOUND', 'Produk tidak ditemukan.', 404);
        }
        if ((int) $product['seller_id'] === $userId) {
            throw new AppException('OWN_PRODUCT', 'Tidak dapat membeli produk sendiri.', 422);
        }

        $cartId = $this->carts->getOrCreateCartId($userId);
        $this->carts->addItem($cartId, (int) $product['id'], (int) $data['quantity']);

        return $this->view($userId);
    }

    public function updateItem(int $userId, int $itemId, array $data): array
    {
        if (!Validator::positiveInt($data['quantity'] ?? null)) {
            throw new AppException('VALIDATION_ERROR', 'Jumlah tidak valid.', 422);
        }

        $item = $this->ownedItem($userId, $itemId);
        $this->carts->updateItem((int) $item['id'], (int) $data['quantity']);

        return $this->view($userId);
    }

    public function removeItem(int $userId, int $itemId): array
    {
        $item = $this->ownedItem($userId, $itemId);
        $this->carts->deleteItem((int) $item['id']);

        return $this->view($userId);
    }

    private function ownedItem(int $userId, int $itemId): array
    {
        $item = $this->carts->findItem($itemId);
        if ($item === null) {
            throw new AppException('CART_ITEM_NOT_FOUND', 'Item tidak ditemukan.', 404);
        }

        $cartId = $this->carts->getOrCreateCartId($userId);
        if ((int) $item['cart_id'] !== $cartId) {
            throw new AppException('CART_ITEM_NOT_FOUND', 'Item tidak ditemukan.', 404);
        }

        return $item;
    }
}
