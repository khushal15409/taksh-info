<?php

namespace App\Services\Marketplace;

use App\Exceptions\ServiceProblemException;
use App\Models\ServiceMarketplaceBooking as Booking;
use App\Models\ServiceMarketplacePayment as Payment;
use App\Models\User;
use App\Repositories\Contracts\ServiceBookingRepositoryInterface;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Prepaid home-service booking, as taksh-admin's ServiceBookingService does it: availability -> pending booking -> Razorpay order,
 * then a verified payment marks the booking paid (the admin confirms and assigns it). Availability is checked on every booking,
 * before anything is created. Replays of the same request (same idempotency key, or the same unpaid request within 15 minutes)
 * resume the booking instead of duplicating it.
 */
class ServiceBookingService
{
    private const DUPLICATE_WINDOW_MINUTES = 15;

    public function __construct(
        private readonly ServiceBookingRepositoryInterface $bookings,
        private readonly RazorpayGateway $gateway,
        private readonly ServiceBookingCancellationService $cancellation,
    ) {}

    /** @return array{booking: Booking, payment: Payment, reused: bool} */
    public function book(User $user, int $serviceId, ?int $addressId, array $details, ?string $notes, ?\DateTimeInterface $scheduledFor, ?string $key): array
    {
        $service = $this->bookings->findService($serviceId) ?? throw new ServiceProblemException('Service not found.', 404);

        $address = null;
        if ($addressId !== null) {
            // ownership is part of the query: another customer's address looks the same as a missing one
            $address = $this->bookings->ownedAddress($user->id, $addressId) ?? throw new ServiceProblemException('Selected address does not belong to you.', 403);
            $pincode = trim((string) $address->pincode);
        } else {
            $pincode = trim((string) ($details['pincode'] ?? ''));
        }

        if ($existing = $this->findDuplicate($user, $serviceId, $address?->id, $details, $key)) {
            return $this->resume($existing);
        }

        if (! preg_match('/^[0-9]{6}$/', $pincode)) {
            throw new ServiceProblemException('Selected address has an invalid pincode.', 400);
        }

        if (! $this->bookings->serviceIsAssignable($serviceId)) {
            throw new ServiceProblemException('Service is currently unavailable.', 400, ['available' => false, 'provider_count' => 0]);
        }
        if ($this->bookings->eligibleProviderCount($serviceId, $pincode) === 0) {
            throw new ServiceProblemException('Service is not available in your area.', 400, ['available' => false, 'provider_count' => 0]);
        }

        $charge = $service->effective_charge;
        if ($charge <= 0) {
            throw new ServiceProblemException('Service charge is not set for this service.', 400, ['available' => false, 'provider_count' => 0]);
        }
        $providerPct = $service->provider_share_percentage !== null ? (float) $service->provider_share_percentage : null;

        $address ??= $this->bookings->addressFor($user->id, $details, $pincode);

        try {
            $booking = $this->bookings->createBooking([
                'booking_number' => $this->newNumber(),
                'user_id' => $user->id,
                'service_service_id' => $service->id,
                'service_address_id' => $address->id,
                'pincode' => $pincode,
                'address_snapshot' => $address->only(['name', 'mobile', 'address_line_1', 'address_line_2', 'landmark', 'city', 'state', 'pincode', 'latitude', 'longitude', 'type']),
                'customer_notes' => $notes,
                'scheduled_for' => $scheduledFor,
                'service_charge' => $charge,
                'provider_share_percentage' => $providerPct,
                'platform_share_percentage' => $service->platform_share_percentage,
                'provider_amount' => $providerPct !== null ? round($charge * min(100.0, max(0.0, $providerPct)) / 100, 2) : null,
                'platform_amount' => $providerPct !== null ? round($charge - round($charge * min(100.0, max(0.0, $providerPct)) / 100, 2), 2) : null,
                'refund_type' => $service->refund_type,
                'refund_value' => $service->refund_value,
                'currency' => 'INR',
                'status' => Booking::STATUS_PENDING,
                'payment_status' => 'pending',
                'idempotency_key' => $key,
            ]);
        } catch (QueryException $e) {
            // lost a race on the unique idempotency key: the other request owns the booking
            if ($key && ($existing = $this->bookings->findByIdempotencyKey($user->id, $key))) {
                return $this->resume($existing);
            }
            throw $e;
        }

        $this->bookings->recordHistory($booking, null, Booking::STATUS_PENDING, 'customer', $user->id, 'Booking created');

        // the gateway is called outside any transaction; if it fails the booking stays pending and a retry reuses it
        return ['booking' => $this->bookings->fresh($booking), 'payment' => $this->createPaymentOrder($booking), 'reused' => false];
    }

    /**
     * A new Razorpay order for the customer's own unpaid booking (the last attempt failed or was never finished). The booking is resumed, not
     * duplicated; one that is paid or cancelled is refused.
     *
     * @return array{booking: Booking, payment: Payment, reused: bool}
     */
    public function retryPayment(User $user, string $ref): array
    {
        $booking = $this->bookings->findForUser($user->id, $ref) ?? throw new ServiceProblemException('Booking not found.', 404);

        return $this->resume($booking);
    }

    /**
     * Server-side verification: our own records decide ownership, amount and currency; the browser only supplies the Razorpay ids.
     *
     * @return array{booking: Booking, already: bool}
     */
    public function verify(User $user, string $orderId, string $paymentId, string $signature): array
    {
        $payment = $this->bookings->paymentByOrder($orderId);
        $booking = $payment?->booking;
        // same answer for "missing" and "not yours": no probing of other customers' orders
        if (! $payment || ! $booking || $booking->user_id !== $user->id) {
            throw new ServiceProblemException('Payment not found.', 404);
        }

        if ($payment->isPaid()) {
            return ['booking' => $booking, 'already' => true];
        }
        if (in_array($payment->status, ['refunded', 'partially_refunded'], true)) {
            throw new ServiceProblemException('Payment has already been processed.', 409);
        }

        if (! $this->gateway->verifySignature($orderId, $paymentId, $signature)) {
            // a bad signature is not proof the payment failed, so the booking stays retryable
            throw new ServiceProblemException('Payment verification failed.', 400);
        }

        $entity = $this->gateway->fetchPayment($paymentId);
        if (! $entity || ! $this->entityMatches($payment, $entity, $paymentId)) {
            Log::warning('Service payment verification mismatch', ['booking_id' => $booking->id, 'payment_id' => $payment->id]);
            throw new ServiceProblemException('Payment verification failed.', 400);
        }

        return ['booking' => $this->markPaid($payment->id, $paymentId, $entity['method'] ?? null, $entity), 'already' => false];
    }

    // ------------------------------------------------------------------

    private function findDuplicate(User $user, int $serviceId, ?int $addressId, array $details, ?string $key): ?Booking
    {
        if ($key && ($byKey = $this->bookings->findByIdempotencyKey($user->id, $key))) {
            return $byKey;
        }

        return $this->bookings->findRecentUnpaid($user->id, $serviceId, $addressId, $details, now()->subMinutes(self::DUPLICATE_WINDOW_MINUTES));
    }

    /** @return array{booking: Booking, payment: Payment, reused: bool} */
    private function resume(Booking $booking): array
    {
        if ($booking->payment_status === 'paid' || $booking->status === Booking::STATUS_CANCELLED) {
            throw new ServiceProblemException('Booking already exists for this request.', 409);
        }

        $payment = $this->bookings->initiatedPayment($booking);
        if (! $payment) {
            $payment = $this->createPaymentOrder($booking);
            // a fresh order after a failed attempt makes the booking payable again; the status itself does not change
            if ($booking->payment_status !== 'pending') {
                $this->bookings->updateBooking($booking, ['payment_status' => 'pending']);
                $this->bookings->recordHistory($booking, $booking->status, $booking->status, 'system', null, 'New payment order');
            }
        }

        return ['booking' => $booking, 'payment' => $payment, 'reused' => true];
    }

    private function createPaymentOrder(Booking $booking): Payment
    {
        try {
            $order = $this->gateway->createOrder((int) round($booking->service_charge * 100), 'INR', [
                'type' => 'service', 'service_booking_id' => (string) $booking->id,
                'booking_number' => $booking->booking_number, 'customer_id' => (string) $booking->user_id,
            ]);

            return $this->bookings->createPayment([
                'service_marketplace_booking_id' => $booking->id, 'gateway' => 'razorpay', 'gateway_order_id' => $order['id'],
                'amount' => (int) $order['amount'], 'currency' => $order['currency'] ?? 'INR', 'status' => 'initiated', 'raw_response' => $order,
            ]);
        } catch (\Throwable $e) {
            Log::error('Service booking payment order failed', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);
            throw new ServiceProblemException('Unable to initiate payment.', 502);
        }
    }

    /** Payment id, order id, amount and currency from Razorpay must equal what we stored. */
    private function entityMatches(Payment $payment, array $entity, string $paymentId): bool
    {
        return ($entity['id'] ?? null) === $paymentId
            && ($entity['order_id'] ?? null) === $payment->gateway_order_id
            && (int) ($entity['amount'] ?? -1) === (int) $payment->amount
            && strtoupper((string) ($entity['currency'] ?? '')) === strtoupper($payment->currency)
            && in_array($entity['status'] ?? null, ['captured', 'authorized'], true);
    }

    /** Idempotent: a payment already marked paid (or refunded) is left alone. */
    private function markPaid(int $paymentId, string $gatewayPaymentId, ?string $method, array $entity): Booking
    {
        $lateRefundId = null;

        $booking = $this->bookings->transaction(function () use ($paymentId, $gatewayPaymentId, $method, $entity, &$lateRefundId) {
            $payment = $this->bookings->lockPayment($paymentId);
            $booking = $this->bookings->lockBooking($payment->service_marketplace_booking_id);

            if ($payment->isPaid() || in_array($payment->status, ['refunded', 'partially_refunded'], true)) {
                return $this->bookings->fresh($booking);
            }

            $this->bookings->updatePayment($payment, [
                'gateway_payment_id' => $gatewayPaymentId, 'status' => 'paid', 'method' => $method, 'failure_reason' => null,
                'paid_at' => now(), 'raw_response' => array_merge((array) $payment->raw_response, ['payment' => $entity]),
            ]);

            if ($booking->status === Booking::STATUS_CANCELLED) {
                // cancelled while the payment was in flight: it stays cancelled and a full refund is queued
                $lateRefundId = $this->cancellation->registerLatePayment($booking, $payment);

                return $this->bookings->fresh($booking);
            }

            // only the payment is updated: an admin confirms the booking
            $this->bookings->updateBooking($booking, ['payment_status' => 'paid', 'paid_at' => now()]);
            $this->bookings->recordHistory($booking, $booking->status, $booking->status, 'system', null, 'Payment verified');

            return $this->bookings->fresh($booking);
        });

        if ($lateRefundId) {
            $this->cancellation->processRefund($lateRefundId);
            $booking = $this->bookings->fresh($booking);
        }

        return $booking;
    }

    private function newNumber(): string
    {
        do {
            $number = 'SMB'.now()->format('ymd').strtoupper(Str::random(6));
        } while ($this->bookings->numberExists($number));

        return $number;
    }
}
