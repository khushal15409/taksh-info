<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Support\Facades\Log;

/**
 * Refund of a paid ONLINE order payment through Razorpay, as taksh-admin does it. A payment is only ever set to `refunded`
 * after Razorpay reports the refund as processed; a pending or failed refund leaves it `paid` with the refund recorded, so the
 * stored state never claims more than happened. The gateway is called OUTSIDE any transaction, and a refund is created at most
 * once per order: it carries the reference `order-refund-{order id}` and an existing refund with it is adopted.
 */
class OrderRefundService
{
    public const REFERENCE_PREFIX = 'order-refund-';

    public const PROCESSED = 'processed';
    public const PENDING = 'pending';
    public const FAILED = 'failed';
    public const NOT_APPLICABLE = 'not_applicable';
    public const MANUAL_REQUIRED = 'manual_required';

    public function __construct(private readonly PaymentRepositoryInterface $payments, private readonly RazorpayGateway $gateway) {}

    /** Is automatic refunding on customer cancellation switched on? */
    public function enabled(): bool
    {
        return filter_var(config('services.razorpay.refund_on_cancel'), FILTER_VALIDATE_BOOLEAN);
    }

    /** Start (or adopt) the refund for a cancelled order's paid payment. Never throws. @return string PROCESSED, PENDING or FAILED */
    public function refund(Payment $payment, Order $order): string
    {
        $reference = self::REFERENCE_PREFIX.$order->id;

        try {
            $existing = collect($this->gateway->fetchRefunds($payment->razorpay_payment_id))->first(fn ($r) => ($r['notes']['reference'] ?? null) === $reference);

            $refund = $existing ?? $this->gateway->createRefund($payment->razorpay_payment_id, (int) $payment->amount, [
                'reference' => $reference, 'type' => 'order', 'order_id' => (string) $order->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Order refund request failed; payment left as paid, refund still required', ['order_id' => $order->id, 'payment_id' => $payment->id, 'error' => $e->getMessage()]);
            $this->note($payment->id, ['refund_required' => true, 'refund_error' => 'gateway request failed', 'refund_attempted_at' => now()->toDateTimeString()]);

            return self::FAILED;
        }

        $status = ($refund['status'] ?? self::PENDING) === self::PROCESSED ? self::PROCESSED : self::PENDING;
        $this->record($payment->id, $refund, $status === self::PROCESSED);

        return $status;
    }

    /** Record that a paid order was cancelled and still needs a refund (no gateway call). */
    public function flagRefundRequired(Payment $payment): void
    {
        $this->note($payment->id, ['refund_required' => true, 'refund_requested_at' => now()->toDateTimeString()]);
    }

    /** Save the refund entity; mark the payment refunded only when it is processed. */
    private function record(int $paymentId, array $refund, bool $processed): void
    {
        $this->payments->transaction(function () use ($paymentId, $refund, $processed) {
            $payment = $this->payments->lockedPayment($paymentId);

            if (! $payment || $payment->status === 'refunded') {
                return;
            }

            $this->payments->updatePayment($payment, array_filter([
                'status' => $processed ? 'refunded' : null,
                'raw_response' => array_merge($payment->raw_response ?? [], ['refund' => $refund, 'refund_required' => ! $processed]),
            ], fn ($v) => $v !== null));
        });
    }

    /** Add notes to raw_response without changing the payment status. */
    private function note(int $paymentId, array $extra): void
    {
        $this->payments->transaction(function () use ($paymentId, $extra) {
            $payment = $this->payments->lockedPayment($paymentId);

            if ($payment && $payment->status !== 'refunded') {
                $this->payments->updatePayment($payment, ['raw_response' => array_merge($payment->raw_response ?? [], $extra)]);
            }
        });
    }
}
