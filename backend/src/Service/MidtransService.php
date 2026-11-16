<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\AppException;
use App\Support\Config;
use GuzzleHttp\Client;

final class MidtransService
{
    public function enabled(): bool
    {
        return Config::bool('MIDTRANS_ENABLED', false)
            && trim((string) Config::get('MIDTRANS_SERVER_KEY', '')) !== ''
            && trim((string) Config::get('MIDTRANS_CLIENT_KEY', '')) !== '';
    }

    public function publicConfig(): array
    {
        return [
            'enabled' => $this->enabled(),
            'client_key' => $this->enabled() ? (string) Config::get('MIDTRANS_CLIENT_KEY', '') : '',
            'snap_js_url' => (string) Config::get('MIDTRANS_SNAP_JS_URL', 'https://app.sandbox.midtrans.com/snap/snap.js'),
        ];
    }

    public function createSnap(string $providerOrderId, float $amount, array $buyer, ?string $finishUrl = null): array
    {
        if ($finishUrl === null || $finishUrl === '') {
            $finishUrl = (string) Config::get('MIDTRANS_FINISH_URL', '');
            if ($finishUrl === '') {
                $finishUrl = rtrim((string) Config::get('APP_URL', 'http://localhost:8080'), '/') . '/orders.html';
            }
        }

        $payload = [
            'transaction_details' => [
                'order_id' => $providerOrderId,
                'gross_amount' => (int) round($amount),
            ],
            'customer_details' => [
                'first_name' => (string) ($buyer['display_name'] ?? $buyer['username'] ?? 'Pembeli'),
                'email' => (string) ($buyer['email'] ?? ''),
            ],
            'callbacks' => [
                'finish' => $finishUrl,
            ],
            'expiry' => [
                'unit' => 'hours',
                'duration' => 24,
            ],
        ];

        return $this->request(
            'POST',
            rtrim($this->snapBaseUrl(), '/') . '/snap/v1/transactions',
            $payload
        );
    }

    public function status(string $providerOrderId): array
    {
        return $this->request(
            'GET',
            rtrim($this->apiBaseUrl(), '/') . '/v2/' . rawurlencode($providerOrderId) . '/status'
        );
    }

    public function expire(string $providerOrderId): array
    {
        return $this->request(
            'POST',
            rtrim($this->apiBaseUrl(), '/') . '/v2/' . rawurlencode($providerOrderId) . '/expire'
        );
    }

    public function verifyNotification(array $payload): void
    {
        $serverKey = (string) Config::get('MIDTRANS_SERVER_KEY', '');
        if ($serverKey === '') {
            throw new AppException('PAYMENT_UNAVAILABLE', 'Pembayaran gateway belum dikonfigurasi.', 503);
        }

        $raw = (string) ($payload['order_id'] ?? '')
            . (string) ($payload['status_code'] ?? '')
            . (string) ($payload['gross_amount'] ?? '')
            . $serverKey;
        $expected = hash('sha512', $raw);
        $actual = strtolower((string) ($payload['signature_key'] ?? ''));

        if (!hash_equals($expected, $actual)) {
            throw new AppException('PAYMENT_SIGNATURE_INVALID', 'Signature pembayaran tidak valid.', 403);
        }
    }

    public function assertNotificationAmount(float $expectedAmount, array $payload): void
    {
        $gross = $payload['gross_amount'] ?? null;
        if ($gross === null || abs((float) $gross - $expectedAmount) > 0.01) {
            throw new AppException('PAYMENT_AMOUNT_MISMATCH', 'Nominal pembayaran tidak sesuai.', 400);
        }
    }

    private function request(string $method, string $url, ?array $payload = null): array
    {
        if (!$this->enabled()) {
            throw new AppException('PAYMENT_UNAVAILABLE', 'Pembayaran gateway belum tersedia.', 503);
        }

        $options = [
            'auth' => [(string) Config::get('MIDTRANS_SERVER_KEY', ''), ''],
            'headers' => ['Accept' => 'application/json'],
            'timeout' => 15,
            'http_errors' => false,
        ];
        if ($payload !== null) {
            $options['json'] = $payload;
        }

        $client = new Client();
        $response = $client->request($method, $url, $options);
        $body = json_decode((string) $response->getBody(), true);
        $body = is_array($body) ? $body : [];

        if ($response->getStatusCode() >= 300) {
            $message = $body['status_message'] ?? $body['error_messages'][0] ?? 'Gateway menolak permintaan pembayaran.';
            throw new AppException('PAYMENT_GATEWAY_ERROR', (string) $message, 502);
        }

        return $body;
    }

    private function snapBaseUrl(): string
    {
        $configured = trim((string) Config::get('MIDTRANS_SNAP_BASE_URL', ''));
        if ($configured !== '') {
            return $configured;
        }

        return Config::bool('MIDTRANS_IS_PRODUCTION', false)
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';
    }

    private function apiBaseUrl(): string
    {
        $configured = trim((string) Config::get('MIDTRANS_API_BASE_URL', ''));
        if ($configured !== '') {
            return $configured;
        }

        return Config::bool('MIDTRANS_IS_PRODUCTION', false)
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
    }
}
