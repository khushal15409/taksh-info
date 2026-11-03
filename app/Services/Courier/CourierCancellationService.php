<?php

namespace App\Services\Courier;

use App\Exceptions\ServiceProblemException;
use App\Models\CourierBooking;
use App\Models\CourierPayment;
use App\Models\CourierRefund;
use App\Models\CourierShipment;
use App\Repositories\Contracts\CourierBookingRepositoryInterface;
use App\Repositories\Contracts\CourierShipmentRepositoryInterface;
use App\Services\Shiprocket\ShiprocketApi;
use App\Services\Shiprocket\ShiprocketException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cancels a Shiprocket courier booking for the customer: eligibility -> Shiprocket cancel (only if an order exists) -> local cancel
 * -> refund. Every step can be repeated: Shiprocket is skipped once the shipment is cancelled, the refund row is unique per
 * payment, and an already-cancelled booking simply resumes its refund. No DB transaction is open during a Shiprocket or Razorpay
 * call. Scope: Shiprocket bookings only; legacy bookings (local partner pricing) are not cancellable online.
 */
class CourierCancellationService
{
    /** shipment_status values from which cancellation is still possible (before pickup). */
    private const CANCELLABLE = [null, 'pending', 'order_created', 'awb_assigned', 'pickup_requested', 'cancelled'];

    public function __construct(
        private readonly CourierBookingRepositoryInterface $bookings,
        private readonly CourierShipmentRepositoryInterface $shipments,
        private readonly ShiprocketApi $shiprocket,
        private readonly CourierRefundService $refunds,
    ) {}

    /** @return array<string, mixed> normalized state */
    public function cancel(int $userId, int $bookingId, ?string $reason): array
    {
        $booking = $this->bookings->ownedBooking($userId, $bookingId) ?? throw new ServiceProblemException('Courier booking not found', 404);

        // the same lock the shipment flow uses, so a cancel never races order/AWB/pickup creation
        $lock = Cache::lock("courier-shipment:{$booking->id}", 240);
        if (! $lock->get()) {
            throw new ServiceProblemException('This booking is being processed. Please try again shortly.', 409);
        }

        try {
            $booking = $this->bookings->fresh($booking);

            if ($booking->status === 'cancelled') {
                // duplicate request: no second Shiprocket call, no second refund; just continue an unfinished refund
                if ($refund = $this->shipments->firstRefund($booking->id)) {
                    $this->refunds->resume($refund);
                }

                return $this->state($this->bookings->fresh($booking));
            }

            $shipment = $this->shipments->shipmentOf($booking->id);
            $payment = $this->assertCancellable($booking, $shipment);

            // Shiprocket first: if it refuses, nothing local changes and nothing is refunded
            if ($shipment && $shipment->shiprocket_order_id && $shipment->shipment_status !== 'cancelled') {
                $this->cancelAtShiprocket($booking, $shipment);
            }

            if ($refund = $this->persistCancellation($booking->id, $payment, $reason)) {
                $this->refunds->resume($refund);
            }

            return $this->state($this->bookings->fresh($booking));
        } finally {
            $lock->release();
        }
    }

    /** @return ?CourierPayment the paid payment to refund, if any */
    private function assertCancellable(CourierBooking $booking, ?CourierShipment $shipment): ?CourierPayment
    {
        if (! $booking->shiprocket_courier_company_id) {
            throw new ServiceProblemException('This booking cannot be cancelled online.', 409);
        }
        if ($shipment && $shipment->failure_reason === CourierShipmentService::ORDER_SENT) {
            // a create-order request may still be live at Shiprocket: wait for it to resolve
            throw new ServiceProblemException('The shipment is still being created. Please try again shortly.', 409);
        }

        $stage = $shipment?->shipment_status ?? $booking->shipment_status;
        if (! in_array($stage, self::CANCELLABLE, true)) {
            throw new ServiceProblemException('Cancellation is no longer available for this shipment.', 409);
        }

        if ($booking->status === 'paid' && $booking->payment_status === 'paid') {
            $payment = $this->shipments->paidPayment($booking->id);
            if (! $payment || ! $payment->gateway_payment_id) {
                throw new ServiceProblemException('This booking cannot be cancelled online. Please contact support.', 409);
            }
            $this->refunds->assertPolicyConfigured();

            return $payment;
        }

        if ($booking->status === 'pending' && $booking->payment_status === 'pending' && ! $booking->payment_order_id) {
            return null;   // never paid and no gateway order exists: nothing to refund
        }

        throw new ServiceProblemException('This booking cannot be cancelled right now.', 409);
    }

    private function cancelAtShiprocket(CourierBooking $booking, CourierShipment $shipment): void
    {
        try {
            $this->shiprocket->cancelOrder($shipment->shiprocket_order_id);
        } catch (ShiprocketException $e) {
            Log::warning('Courier cancel at Shiprocket failed', ['booking_id' => $booking->id, 'category' => $e->category, 'status' => $e->httpStatus]);
            if ($e->category === ShiprocketException::CLIENT_ERROR) {
                throw new ServiceProblemException('Cancellation is no longer available for this shipment.', 409);
            }
            throw new ServiceProblemException('Cancellation is temporarily unavailable. Please try again shortly.', 503);
        }
    }

    /** Short transaction: re-check state, mark cancelled, create the one refund row. */
    private function persistCancellation(int $bookingId, ?CourierPayment $payment, ?string $reason): ?CourierRefund
    {
        return $this->shipments->transaction(function () use ($bookingId, $payment, $reason) {
            [$booking, $shipment] = $this->shipments->lockBookingAndShipment($bookingId);

            if ($booking->status !== 'cancelled') {
                $this->shipments->updateBooking($booking, [
                    'status' => 'cancelled', 'shipment_status' => $shipment ? 'cancelled' : $booking->shipment_status, 'cancelled_at' => now(),
                    'cancelled_by_type' => 'customer', 'cancellation_reason' => $reason ? mb_substr($reason, 0, 500) : null,
                ]);
                if ($shipment) {
                    $this->shipments->updateShipment($shipment, ['shipment_status' => 'cancelled', 'failure_reason' => null]);
                }
            }

            return $payment ? $this->refunds->ensureRefund($booking, $payment, $reason) : null;
        });
    }

    /** Normalized state returned to the client (never raw gateway or Shiprocket data). */
    public function state(CourierBooking $booking): array
    {
        $refund = $this->shipments->firstRefund($booking->id);

        return [
            'booking_id' => $booking->id, 'booking_status' => $booking->status, 'shipment_status' => $booking->shipment_status,
            'cancellation_status' => $booking->status === 'cancelled' ? 'cancelled' : null,
            'refund_status' => $refund?->status, 'refund_amount' => $refund ? (float) $refund->amount : null,
        ];
    }
}
