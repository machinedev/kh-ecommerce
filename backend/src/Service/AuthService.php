<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\OtpRepository;
use App\Repository\UserRepository;
use App\Support\AppException;
use App\Support\Jwt;
use App\Support\Validator;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly OtpRepository $otps = new OtpRepository()
    ) {
    }

    public function register(array $data): array
    {
        Validator::required($data, ['username', 'email', 'password', 'display_name']);

        $username = trim((string) $data['username']);
        $email = trim((string) $data['email']);
        $displayName = trim((string) $data['display_name']);
        $password = (string) $data['password'];

        if (!Validator::email($email)) {
            throw new AppException('VALIDATION_ERROR', 'Format email tidak valid.', 422);
        }
        if (strlen($password) < 8) {
            throw new AppException('VALIDATION_ERROR', 'Password minimal 8 karakter.', 422);
        }
        if ($this->users->findByUsername($username) !== null) {
            throw new AppException('USERNAME_TAKEN', 'Username sudah digunakan.', 409);
        }
        if ($this->users->findByEmail($email) !== null) {
            throw new AppException('EMAIL_TAKEN', 'Email sudah digunakan.', 409);
        }

        $userId = $this->users->create($username, $email, password_hash($password, PASSWORD_BCRYPT), $displayName);
        $user = $this->users->findById($userId);

        return ['user' => $this->publicUser($user), 'token' => Jwt::issue($user)];
    }

    public function login(array $data): array
    {
        Validator::required($data, ['username', 'password']);

        $identifier = trim((string) $data['username']);
        $password = (string) $data['password'];

        $user = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? $this->users->findByEmail($identifier)
            : $this->users->findByUsername($identifier);

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            throw new AppException('INVALID_CREDENTIALS', 'Username atau password salah.', 401);
        }

        $next = isset($data['next']) ? (string) $data['next'] : null;

        return [
            'user' => $this->publicUser($user),
            'token' => Jwt::issue($user),
            'redirect' => $this->resolveRedirect($next),
        ];
    }

    private function resolveRedirect(?string $next): string
    {
        if ($next === null || $next === '') {
            return '/';
        }

        return $next;
    }

    public function forgotPassword(array $data): array
    {
        Validator::required($data, ['email']);
        $email = trim((string) $data['email']);
        $user = $this->users->findByEmail($email);

        if ($user !== null) {
            $digest = md5((string) $user['id'] . '|' . time());
            $code = str_pad((string) (hexdec(substr($digest, 0, 8)) % 10000), 4, '0', STR_PAD_LEFT);
            $expires = (new \DateTimeImmutable('+15 minutes'))->format('Y-m-d H:i:s');
            $this->otps->create((int) $user['id'], $code, $expires);
        }

        return ['message' => 'Jika email terdaftar, kode verifikasi telah dikirim.'];
    }

    public function verifyOtp(array $data): array
    {
        Validator::required($data, ['email', 'otp']);
        $user = $this->users->findByEmail(trim((string) $data['email']));

        if ($user === null) {
            throw new AppException('OTP_INVALID', 'Kode verifikasi salah atau kadaluarsa.', 400);
        }

        $otp = $this->otps->findValid((int) $user['id'], (string) $data['otp']);
        if ($otp === null) {
            throw new AppException('OTP_INVALID', 'Kode verifikasi salah atau kadaluarsa.', 400);
        }

        $this->otps->markUsed((int) $otp['id']);

        return ['reset_token' => Jwt::issueResetToken((int) $user['id'])];
    }

    public function resetPassword(array $data): array
    {
        Validator::required($data, ['reset_token', 'new_password']);

        $payload = Jwt::decode((string) $data['reset_token']);
        if ($payload === null || ($payload['purpose'] ?? null) !== 'password_reset') {
            throw new AppException('RESET_INVALID', 'Token reset tidak valid.', 400);
        }
        if (strlen((string) $data['new_password']) < 8) {
            throw new AppException('VALIDATION_ERROR', 'Password minimal 8 karakter.', 422);
        }

        $this->users->updatePassword((int) $payload['sub'], password_hash((string) $data['new_password'], PASSWORD_BCRYPT));

        return ['message' => 'Password berhasil diperbarui.'];
    }

    public function publicUser(?array $user): ?array
    {
        if ($user === null) {
            return null;
        }

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
}
