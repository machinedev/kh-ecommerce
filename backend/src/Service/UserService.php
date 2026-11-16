<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ActivityRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Support\AppException;

final class UserService
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly ActivityRepository $activities = new ActivityRepository()
    ) {
    }

    public function find(int $id): array
    {
        $user = $this->users->findById($id);
        if ($user === null) {
            throw new AppException('USER_NOT_FOUND', 'Pengguna tidak ditemukan.', 404);
        }

        return $this->publicView($user);
    }

    public function findByPath(string $id): array
    {
        $user = $this->users->findByIdPath($id);
        if ($user === null) {
            throw new AppException('USER_NOT_FOUND', 'Pengguna tidak ditemukan.', 404);
        }

        return $this->publicView($user);
    }

    public function update(int $id, array $data): array
    {
        $user = $this->users->findById($id);
        if ($user === null) {
            throw new AppException('USER_NOT_FOUND', 'Pengguna tidak ditemukan.', 404);
        }

        $fields = [];
        foreach (['display_name', 'bio', 'avatar_url', 'phone', 'address', 'email'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[$field] = (string) $data[$field];
            }
        }
        if (isset($data['password']) && strlen((string) $data['password']) >= 8) {
            $this->users->updatePassword($id, password_hash((string) $data['password'], PASSWORD_BCRYPT));
        }

        $this->users->updateProfile($id, $fields);

        return $this->publicView($this->users->findById($id));
    }

    public function activity(int $id): array
    {
        $this->find($id);

        return $this->activities->byUser($id);
    }

    public function products(int $id): array
    {
        $this->find($id);

        return array_map([$this, 'publicProduct'], $this->products->bySeller($id));
    }

    public function sellers(): array
    {
        return $this->users->listSellers();
    }

    /** @param array<string,mixed> $user */
    private function publicView(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
            'bio' => $user['bio'],
            'avatar_url' => $user['avatar_url'],
            'phone' => $user['phone'],
            'address' => $user['address'],
            'balance' => (float) $user['balance'],
            'created_at' => $user['created_at'],
        ];
    }

    /** @param array<string,mixed> $p */
    private function publicProduct(array $p): array
    {
        return [
            'id' => (int) $p['id'],
            'title' => $p['title'],
            'price' => (float) $p['price'],
            'stock' => (int) $p['stock'],
            'category_name' => $p['category_name'] ?? null,
            'created_at' => $p['created_at'],
        ];
    }
}
