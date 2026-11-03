<?php

namespace App\Services\Marketplace;

use App\Exceptions\ServiceProblemException;
use App\Helpers\ServiceRefund as RefundMath;
use App\Models\ServiceMarketplaceBooking as Booking;
use App\Models\ServiceMarketplacePayment as Payment;
use App\Models\ServiceMarketplaceRefund as Refund;
use App\Repositories\Contracts\ServiceBookingRepositoryInterface;
use App\Repositories\Contracts\ServiceRefundRepositoryInterface;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Support\Facades\Log;

/**
 * Cancellation and refund of a prepaid service booking, as taksh-admin does it. A customer may cancel until the service has
 * started. The refund comes from the service's refund policy (snapshotted on the booking) and what was actually paid. The booking is
 * cancelled and the refund row created in one locked transaction; the gateway is called afterwards, claimed with an atomic status
 * update so it can never run twice, and an existing Razorpay refund with our reference is adopted instead of refunding again.
 * A refund Razorpay reports as pending stays "processing" until the admin's refund webhook settles it (same database).
 */
class ServiceBookingCancellationService
{
    public const CUSTOMER_CAN_CANCEL = [
        Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_PROVIDER_ASSIGNED,
        Booking::STATUS_PROVIDER_ACCEPTED, Booking::STATUS_PROVIDER_REJECTED,
    ];

    public function __construct(
        private readonly ServiceBookingRepositoryInterface $bookings,
        private readonly ServiceRefundRepositoryInterface $refunds,
        private readonly RazorpayGateway $gateway,
    ) {}

    /** @return array{booking: Booking, refund: ?Refund} */
    public function cancel(int $userId, int $bookingId, ?string $reason): array
    {
        $refundId = null;

        $booking = $this->bookings->transaction(function () use ($userId, $bookingId, $reason, &$refundId) {
            $booking = $this->bookings->lockBooking($bookingId);
            // someone else's booking looks exactly like a missing one
            if (! $booking || $booking->user_id !== $userId) {
                throw new ServiceProblemException('Booking not found.', 404);
            }
            if ($booking->status === Booking::STATUS_CANCELLED) {
                throw new ServiceProblemException('Booking is already cancelled.', 409);
            }
            if (! in_array($booking->status, self::CUSTOMER_CAN_CANCEL, true) || ! $booking->canMoveTo(Booking::STATUS_CANCELLED)) {
                throw new ServiceProblemException('This booking can no longer be cancelled.', 409);
            }

            $refund = null;
            if ($booking->payment_status === 'paid') {
                $payment = $this->bookings->lockPaidPayment($booking);
                if (! $payment || $this->refunds->existsFor($booking)) {
                    throw new ServiceProblemException('This booking cannot be cancelled automatically. Please contact support.', 409);
                }
                try {
                    $calc = RefundMath::breakdown($booking->refund_type, $booking->refund_value !== null ? (float) $booking->refund_value : null, (float) $booking->service_charge, $payment->amount / 100);
                } catch (\InvalidArgumentException $e) {
                    Log::warning('Service refund policy invalid', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);
                    throw new ServiceProblemException($e->getMessage().' Please contact support.', 400);
                }
                $refund = $this->refunds->create($booking, $payment, $calc, $reason, 'customer', $userId, $calc['refund_amount'] > 0 ? Refund::PENDING : Refund::NOT_REQUIRED);
                $refundId = $calc['refund_amount'] > 0 ? $refund->id : null;
            }

            $from = $booking->status;
            $this->bookings->updateBooking($booking, [
                'status' => Booking::STATUS_CANCELLED, 'cancelled_at' => now(), 'cancelled_by_type' => 'customer', 'cancelled_by_id' => $userId,
                'cancellation_reason' => $reason, 'refund_status' => $refund?->status ?? Refund::NOT_REQUIRED,
            ]);
            $this->bookings->recordHistory($booking, $from, Booking::STATUS_CANCELLED, 'customer', $userId, $reason);

            return $booking;
        });

        if ($refundId) {
            $this->processRefund($refundId);   // only after the cancellation is committed
        }

        $booking = $this->bookings->fresh($booking);

        return ['booking' => $booking, 'refund' => $this->refunds->forBooking($booking)];
    }

    /** A payment arrived for a booking cancelled while unpaid: queue a full refund. The caller holds the booking lock. */
    public function registerLatePayment(Booking $booking, Payment $payment): ?int
    {
        if ($this->refunds->existsFor($booking)) {
            return null;
        }

        $paid = $payment->amount / 100;
        $calc = ['original_amount' => $paid, 'refund_type' => 'fixed', 'refund_value' => $paid, 'refund_amount' => round($paid, 2), 'non_refundable_amount' => 0.0];
        $refund = $this->refunds->create($booking, $payment, $calc, 'Payment received after the booking was cancelled', 'system', null, Refund::PENDING);
        $this->bookings->updateBooking($booking, ['refund_status' => Refund::PENDING, 'payment_status' => 'paid']);
        $this->bookings->recordHistory($booking, $booking->status, $booking->status, 'system', null, 'Payment received after cancellation; full refund queued');

        return $refund->id;
    }

    /** Claims the refund atomically, calls the gateway once, records the outcome. Never throws. */
    public function processRefund(int $refundId): void
    {
        if (! $this->refunds->claim($refundId)) {
            return;   // someone else is processing it, or it is already finished
        }

        $refund = $this->refunds->find($refundId);
        $this->refunds->updateBooking($refund->booking, ['refund_status' => Refund::PROCESSING]);
        $this->bookings->recordHistory($refund->booking, 'refund_'.Refund::PENDING, 'refund_'.Refund::PROCESSING, $refund->requested_by_type, $refund->requested_by_id, null);

        try {
            // after a timeout the refund may already exist at the gateway: adopt it instead of refunding twice
            $result = collect($this->gateway->fetchRefunds($refund->gateway_payment_id))->first(fn ($r) => ($r['notes']['reference'] ?? null) === $refund->reference)
                ?? $this->gateway->createRefund($refund->gateway_payment_id, (int) round($refund->requested_amount * 100), [
                    'reference' => $refund->reference, 'type' => 'service', 'service_booking_id' => (string) $refund->service_marketplace_booking_id,
                ]);
        } catch (\Throwable $e) {
            Log::error('Service refund gateway error', ['refund_id' => $refund->id, 'error' => $e->getMessage()]);
            $this->markFailed($refund->id, 'The payment gateway could not process the refund.');

            return;
        }

        $this->refunds->setGatewayRefundId($refund->id, (string) $result['id']);
        if (($result['status'] ?? null) === 'processed') {
            $this->markSuccess($refund->id, (int) ($result['amount'] ?? round($refund->requested_amount * 100)));
        } elseif (($result['status'] ?? null) === 'failed') {
            $this->markFailed($refund->id, 'The payment gateway rejected the refund.');
        }
        // 'pending' stays processing until the refund webhook arrives
    }

    /** Idempotent: a refund that already succeeded is never touched again. */
    private function markSuccess(int $refundId, int $amountPaise): void
    {
        $this->refunds->transaction(function () use ($refundId, $amountPaise) {
            $refund = $this->refunds->lock($refundId);
            if ($refund->status === Refund::SUCCESS) {
                return;
            }
            $amount = round($amountPaise / 100, 2);
            $was = $refund->status;
            $this->refunds->updateRefund($refund, ['status' => Refund::SUCCESS, 'refunded_amount' => $amount, 'processed_at' => now(), 'failure_reason' => null]);
            $payment = $this->refunds->lockPayment($refund->service_marketplace_payment_id);
            $payStatus = $amount >= round($payment->amount / 100, 2) ? 'refunded' : 'partially_refunded';
            $this->refunds->updatePayment($payment, ['status' => $payStatus]);
            $booking = $this->refunds->lockBooking($refund->service_marketplace_booking_id);
            $this->refunds->updateBooking($booking, ['refund_status' => Refund::SUCCESS, 'payment_status' => $payStatus]);
            $this->bookings->recordHistory($booking, 'refund_'.$was, 'refund_'.Refund::SUCCESS, 'system', null, null);
        });
    }

    /** Never overrides a success. */
    private function markFailed(int $refundId, string $reason): void
    {
        $this->refunds->transaction(function () use ($refundId, $reason) {
            $refund = $this->refunds->lock($refundId);
            if (in_array($refund->status, [Refund::SUCCESS, Refund::FAILED], true)) {
                return;
            }
            $this->refunds->updateRefund($refund, ['status' => Refund::FAILED, 'failure_reason' => $reason]);
            $booking = $this->refunds->lockBooking($refund->service_marketplace_booking_id);
            $this->refunds->updateBooking($booking, ['refund_status' => Refund::FAILED]);
            $this->bookings->recordHistory($booking, 'refund_'.Refund::PROCESSING, 'refund_'.Refund::FAILED, 'system', null, $reason);
        });
    }
}
