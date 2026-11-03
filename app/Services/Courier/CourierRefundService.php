<?php

namespace App\Services\Courier;

use App\Exceptions\ServiceProblemException;
use App\Models\CourierBooking;
use App\Models\CourierPayment;
use App\Models\CourierRefund;
use App\Repositories\Contracts\CourierShipmentRepositoryInterface;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Support\Facades\Log;

/**
 * Refunds for cancelled courier bookings through Razorpay. One refund per payment: the reference is derived from booking and
 * payment ids, is unique in service_courier_refunds and is also sent to Razorpay as a note, so after a lost response the refund
 * is found again instead of created twice. service_courier_refunds.amount is RUPEES; payment amounts are paise.
 */
class CourierRefundService
{
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const SUCCESS = 'success';
    public const FAILED = 'failed';

    public function __construct(
        private readonly CourierShipmentRepositoryInterface $shipments,
        private readonly RazorpayGateway $gateway,
    ) {}

    /** The refund policy is a business decision with no default: unset means "do not refund yet". */
    public function assertPolicyConfigured(): void
    {
        if (! in_array(config('shiprocket.refund_policy'), ['full', 'exclude_platform_charge'], true)) {
            Log::error('Courier refund policy is not configured (SHIPROCKET_REFUND_POLICY)');
            throw new ServiceProblemException('Cancellation is temporarily unavailable. Please contact support.', 503);
        }
    }

    /** Server-side refundable paise: never above what was paid, never from client input. */
    public function refundablePaise(CourierBooking $booking, CourierPayment $payment): int
    {
        $this->assertPolicyConfigured();
        $paid = (int) $payment->amount;
        $keep = config('shiprocket.refund_policy') === 'exclude_platform_charge' ? CourierQuoteService::toPaise($booking->platform_charge) : 0;

        return max(0, min($paid, $paid - $keep));
    }

    /** Create (or find) the one refund row for this payment. Call inside the booking's row lock. */
    public function ensureRefund(CourierBooking $booking, CourierPayment $payment, ?string $reason): ?CourierRefund
    {
        $paise = $this->refundablePaise($booking, $payment);
        if ($paise <= 0) {
            return null;
        }

        return $this->shipments->ensureRefund('CRF'.$booking->id.'P'.$payment->id, [
            'courier_booking_id' => $booking->id, 'courier_payment_id' => $payment->id, 'gateway' => $payment->gateway, 'amount' => $paise / 100,
            'status' => self::PENDING, 'reason' => $reason ? mb_substr($reason, 0, 500) : null, 'initiated_at' => now(),
        ]);
    }

    /** Continue whatever state the refund is in. Safe to call any number of times. */
    public function resume(CourierRefund $refund): CourierRefund
    {
        if (in_array($refund->status, [self::PENDING, self::FAILED], true)) {
            $this->process($refund->id);
        } elseif ($refund->status === self::PROCESSING) {
            $this->reconcile($refund->id);
        }

        return $this->shipments->findRefund($refund->id);
    }

    /** Claims the refund atomically, then refunds once at Razorpay (adopting an existing Razorpay refund with our reference). */
    public function process(int $refundId): void
    {
        if (! $this->shipments->claimRefund($refundId)) {
            return;   // someone else is processing it, or it already finished
        }

        $refund = $this->shipments->findRefund($refundId);
        try {
            $paymentId = (string) $refund->payment?->gateway_payment_id;
            if ($paymentId === '') {
                throw new \RuntimeException('No gateway payment id on the paid payment.');
            }
            $result = $this->find($paymentId, $refund->reference) ?? $this->normalize($this->gateway->createRefund($paymentId, CourierQuoteService::toPaise($refund->amount), [
                'reference' => $refund->reference, 'type' => 'courier', 'courier_booking_id' => (string) $refund->courier_booking_id,
            ]));
        } catch (\Throwable $e) {
            Log::error('Courier refund gateway error', ['refund_id' => $refund->id, 'error' => $e->getMessage()]);
            $this->shipments->markRefundFailed($refund->id, 'The payment gateway could not process the refund.');

            return;
        }

        $this->apply($refund, $result);
    }

    /** Ask Razorpay where a processing refund stands (lookup only; never creates). */
    public function reconcile(int $refundId): void
    {
        $refund = $this->shipments->findRefund($refundId);
        if (! $refund || $refund->status !== self::PROCESSING) {
            return;
        }

        try {
            $result = $this->find((string) $refund->payment?->gateway_payment_id, $refund->reference);
        } catch (\Throwable $e) {
            Log::warning('Courier refund reconcile error', ['refund_id' => $refund->id, 'error' => $e->getMessage()]);

            return;
        }

        if ($result === null) {
            $this->shipments->failUnsentRefund($refund->id);   // never reached Razorpay: let the next resume create it

            return;
        }
        $this->apply($refund, $result);
    }

    /** @param array{refund_id: ?string, status: ?string, amount: int} $result */
    private function apply(CourierRefund $refund, array $result): void
    {
        if (! empty($result['refund_id'])) {
            $this->shipments->setGatewayRefundId($refund->id, $result['refund_id']);
        }

        // success only when Razorpay confirms it and the amount is exactly what we asked; 'pending' stays processing for reconcile()
        if (($result['status'] ?? null) === 'processed' && (int) ($result['amount'] ?? 0) === CourierQuoteService::toPaise($refund->amount)) {
            $this->shipments->markRefundSuccess($refund);
        } elseif (($result['status'] ?? null) === 'failed') {
            $this->shipments->markRefundFailed($refund->id, 'The payment gateway rejected the refund.');
        }
    }

    /** @return array{refund_id: ?string, status: ?string, amount: int}|null a Razorpay refund that already carries our reference */
    private function find(string $paymentId, string $reference): ?array
    {
        $hit = collect($this->gateway->fetchRefunds($paymentId))->first(fn ($r) => ($r['notes']['reference'] ?? null) === $reference);

        return $hit ? $this->normalize($hit) : null;
    }

    private function normalize(array $r): array
    {
        return ['refund_id' => isset($r['id']) ? (string) $r['id'] : null, 'status' => $r['status'] ?? null, 'amount' => (int) ($r['amount'] ?? 0)];
    }
}
