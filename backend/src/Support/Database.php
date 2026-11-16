<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $host = (string) Config::get('DB_HOST', 'db');
            $port = (string) Config::get('DB_PORT', '3306');
            $name = (string) Config::get('DB_DATABASE', 'kh_commerce');
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

            self::$pdo = new PDO(
                $dsn,
                (string) Config::get('DB_USERNAME', 'kh'),
                (string) Config::get('DB_PASSWORD', 'kh_local_password'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }

        return self::$pdo;
    }
}
