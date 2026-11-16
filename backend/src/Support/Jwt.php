<?php

declare(strict_types=1);

namespace App\Support;

final class Jwt
{
    public static function issue(array $user): string
    {
        $now = time();

        return self::encode([
            'sub' => (int) $user['id'],
            'username' => (string) $user['username'],
            'iat' => $now,
            'exp' => $now + Config::int('JWT_TTL', 3600),
        ]);
    }

    public static function issueResetToken(int $userId): string
    {
        $now = time();

        return self::encode([
            'sub' => $userId,
            'purpose' => 'password_reset',
            'iat' => $now,
            'exp' => $now + 900,
        ]);
    }

    public static function encode(array $payload): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $segments = [
            self::base64UrlEncode((string) json_encode($header)),
            self::base64UrlEncode((string) json_encode($payload)),
        ];
        $signature = hash_hmac('sha256', implode('.', $segments), self::secret(), true);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;
        $expected = self::base64UrlEncode(hash_hmac('sha256', $header . '.' . $payload, self::secret(), true));
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $decoded = json_decode(self::base64UrlDecode($payload), true);
        if (!is_array($decoded)) {
            return null;
        }
        if (isset($decoded['exp']) && (int) $decoded['exp'] < time()) {
            return null;
        }

        return $decoded;
    }

    public static function secret(): string
    {
        return (string) Config::get('JWT_SECRET', 'khcommerce123');
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
