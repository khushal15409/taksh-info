<?php

namespace App\Services\Payments;

interface RazorpayGateway
{
    /** Create a Razorpay order. @param int $amount paise @return array{id: string, amount: int, currency: string, receipt: string, status: string, created_at: int} */
    public function createOrder(int $amount, string $currency = 'INR', array $notes = []): array;

    /** Is this signature the one Razorpay made for the order id and payment id? */
    public function verifySignature(string $razorpayOrderId, string $razorpayPaymentId, string $razorpaySignature): bool;

    /** The payment as Razorpay knows it, or null when it could not be fetched. */
    public function fetchPayment(string $razorpayPaymentId): ?array;

    /** Refund a captured payment (full or partial). @param int $amount paise @return array<string, mixed> the refund */
    public function createRefund(string $razorpayPaymentId, int $amount, array $notes = []): array;

    /** The refunds already made on a payment. @return list<array<string, mixed>> */
    public function fetchRefunds(string $razorpayPaymentId): array;

    /** The public key id the browser's checkout needs. */
    public function keyId(): string;
}
