<?php

declare(strict_types=1);

namespace App\Payment;

use App\Support\Env;
use App\Support\Logger;
use RuntimeException;

final class ZarinpalGateway implements PaymentGateway
{
    private string $merchantId;
    private string $baseUrl;

    public function __construct()
    {
        $this->merchantId = trim(Env::get('ZARINPAL_MERCHANT_ID', '') ?? '');
        $this->baseUrl = Env::bool('ZARINPAL_SANDBOX', false)
            ? 'https://sandbox.zarinpal.com'
            : 'https://payment.zarinpal.com';
        if ($this->merchantId === '' || !function_exists('curl_init')) {
            throw new RuntimeException('Zarinpal is not configured on this server.');
        }
    }

    public function request(int $amount, string $callbackUrl, string $description, array $customer = []): array
    {
        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be positive.');
        }
        $data = $this->post('/pg/v4/payment/request.json', [
            'merchant_id' => $this->merchantId,
            'amount' => $amount,
            'callback_url' => $callbackUrl,
            'description' => $description,
            'metadata' => [
                'mobile' => $customer['mobile'] ?? null,
                'email' => $customer['email'] ?? null,
            ],
            'currency' => $customer['currency'] ?? 'IRR',
        ]);
        $authority = trim((string) ($data['authority'] ?? ''));
        if ($authority === '') {
            throw new RuntimeException('Payment provider did not return an authority.');
        }
        return [
            'authority' => $authority,
            'redirect_url' => $this->baseUrl . '/pg/StartPay/' . rawurlencode($authority),
            'raw' => $data,
        ];
    }

    public function verify(int $amount, string $authority): array
    {
        $data = $this->post('/pg/v4/payment/verify.json', [
            'merchant_id' => $this->merchantId,
            'amount' => $amount,
            'authority' => trim($authority),
        ]);
        $code = (int) ($data['code'] ?? 0);
        return [
            'success' => in_array($code, [100, 101], true),
            'reference' => isset($data['ref_id']) ? (string) $data['ref_id'] : null,
            'raw' => $data,
        ];
    }

    private function post(string $path, array $payload): array
    {
        $handle = curl_init($this->baseUrl . $path);
        if ($handle === false) {
            throw new RuntimeException('Payment connection could not be initialized.');
        }
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
        ]);
        $response = curl_exec($handle);
        $httpStatus = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($response === false || $error !== '') {
            Logger::error('Payment network error', ['error' => $error]);
            throw new RuntimeException('Payment provider is temporarily unavailable.');
        }
        $decoded = json_decode((string) $response, true);
        if ($httpStatus !== 200 || !is_array($decoded) || !is_array($decoded['data'] ?? null)) {
            Logger::error('Payment provider rejected a request', ['status' => $httpStatus]);
            throw new RuntimeException('Payment provider rejected the request.');
        }
        return $decoded['data'];
    }
}
