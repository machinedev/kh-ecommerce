<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Config;
use App\Support\Database;

final class UserRepository
{
    public function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function findByIdPath(string $id): ?array
    {
        $sql = 'SELECT * FROM users WHERE id = ' . $id . ' LIMIT 1';

        return Database::pdo()->query($sql)->fetch() ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);

        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);

        return $stmt->fetch() ?: null;
    }

    public function create(string $username, string $email, string $passwordHash, string $displayName): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO users (username, email, password_hash, display_name, balance) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$username, $email, $passwordHash, $displayName, (float) Config::get('DEFAULT_BALANCE', 1000000)]);

        return (int) Database::pdo()->lastInsertId();
    }

    /** @param array<string,mixed> $fields */
    public function updateProfile(int $id, array $fields): void
    {
        if ($fields === []) {
            return;
        }

        $columns = [];
        $values = [];
        foreach ($fields as $column => $value) {
            $columns[] = $column . ' = ?';
            $values[] = $value;
        }
        $values[] = $id;

        $stmt = Database::pdo()->prepare('UPDATE users SET ' . implode(', ', $columns) . ' WHERE id = ?');
        $stmt->execute($values);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$passwordHash, $id]);
    }

    public function addBalance(int $id, float $amount): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
        $stmt->execute([$amount, $id]);
    }

    public function listSellers(int $limit = 8): array
    {
        $sql = 'SELECT u.id, u.username, u.display_name, u.avatar_url, COUNT(p.id) AS product_count
                FROM users u
                JOIN products p ON p.seller_id = u.id AND p.is_deleted = 0
                GROUP BY u.id, u.username, u.display_name, u.avatar_url
                ORDER BY product_count DESC, u.id ASC
                LIMIT ' . (int) $limit;

        return Database::pdo()->query($sql)->fetchAll();
    }
}
