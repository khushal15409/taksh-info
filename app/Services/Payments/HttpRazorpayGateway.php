<?php

namespace App\Services\Payments;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Razorpay's REST API (orders, payments) over HTTPS with the key id/secret; no SDK needed. */
class HttpRazorpayGateway implements RazorpayGateway
{
    private const BASE = 'https://api.razorpay.com/v1';

    public function createOrder(int $amount, string $currency = 'INR', array $notes = []): array
    {
        $payload = ['amount' => $amount, 'currency' => $currency, 'receipt' => 'order_'.time().'_'.uniqid(), 'payment_capture' => 1];

        if ($notes !== []) {
            $payload['notes'] = $notes;
        }

        $response = $this->client()->post(self::BASE.'/orders', $payload);

        if ($response->failed()) {
            Log::error('Razorpay order creation failed', ['status' => $response->status(), 'amount' => $amount]);
            throw new Exception('Failed to create Razorpay order');
        }

        $o = $response->json();

        return ['id' => $o['id'], 'amount' => $o['amount'], 'currency' => $o['currency'], 'receipt' => $o['receipt'], 'status' => $o['status'], 'created_at' => $o['created_at']];
    }

    public function verifySignature(string $razorpayOrderId, string $razorpayPaymentId, string $razorpaySignature): bool
    {
        $expected = hash_hmac('sha256', $razorpayOrderId.'|'.$razorpayPaymentId, $this->secret());

        return hash_equals($expected, $razorpaySignature);
    }

    public function fetchPayment(string $razorpayPaymentId): ?array
    {
        try {
            $response = $this->client()->get(self::BASE.'/payments/'.urlencode($razorpayPaymentId));

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::error('Failed to fetch Razorpay payment', ['error' => $e->getMessage(), 'payment_id' => $razorpayPaymentId]);

            return null;
        }
    }

    public function createRefund(string $razorpayPaymentId, int $amount, array $notes = []): array
    {
        $response = $this->client()->post(self::BASE.'/payments/'.urlencode($razorpayPaymentId).'/refund', ['amount' => $amount, 'notes' => $notes]);

        if ($response->failed()) {
            Log::error('Razorpay refund failed', ['status' => $response->status(), 'payment_id' => $razorpayPaymentId]);
            throw new Exception('Failed to create Razorpay refund');
        }

        return (array) $response->json();
    }

    public function fetchRefunds(string $razorpayPaymentId): array
    {
        $response = $this->client()->get(self::BASE.'/payments/'.urlencode($razorpayPaymentId).'/refunds');

        if ($response->failed()) {
            throw new Exception('Failed to fetch Razorpay refunds');
        }

        return (array) ($response->json('items') ?? []);
    }

    public function keyId(): string
    {
        return (string) config('services.razorpay.key_id');
    }

    private function secret(): string
    {
        $secret = (string) config('services.razorpay.key_secret');

        if ($this->keyId() === '' || $secret === '') {
            throw new Exception('Razorpay credentials not configured');
        }

        return $secret;
    }

    private function client()
    {
        return Http::withBasicAuth($this->keyId(), $this->secret())->acceptJson()->timeout((int) config('services.razorpay.timeout'));
    }
}
