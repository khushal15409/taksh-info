<?php

namespace App\Services\Courier;

use App\Exceptions\ServiceProblemException;
use App\Models\CourierBooking;
use App\Models\CourierPayment;
use App\Models\User;
use App\Repositories\Contracts\CourierBookingRepositoryInterface;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Courier payments through Razorpay, recorded in service_courier_payments. Amounts are integer paise and the booking's stored
 * pricing snapshot is the only source of truth: nothing price-related is read from the request. Before money is taken the selected
 * courier and price are revalidated with Shiprocket; verification checks the signature AND asks Razorpay about the payment.
 */
class CourierPaymentService
{
    public function __construct(
        private readonly CourierBookingRepositoryInterface $bookings,
        private readonly CourierQuoteService $quotes,
        private readonly RazorpayGateway $gateway,
    ) {}

    /** The payment order created together with the booking, or null when it could not be made now (retry with create()). */
    public function startWithBooking(User $user, CourierBooking $booking): ?array
    {
        try {
            $result = $this->createForShiprocket($user, $booking->id);
        } catch (ServiceProblemException $e) {
            Log::warning('Courier payment order not created with the booking', ['booking_id' => $booking->id, 'message' => $e->getMessage()]);

            return null;
        } catch (Throwable $e) {
            Log::error('Courier payment order failed with the booking', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);

            return null;
        }

        $p = $result['payment'];

        return [
            'razorpay_order_id' => $p->gateway_order_id, 'payment_order_id' => $p->gateway_order_id, 'gateway' => $p->gateway,
            'gateway_key' => $this->gateway->keyId(), 'amount' => (float) $result['booking']->total_amount,
            'amount_in_paise' => (int) $p->amount, 'currency' => $p->currency, 'payment_status' => $p->status,
        ];
    }

    /**
     * POST /service/courier/payment/create: the order for a booking (Shiprocket pricing, or the legacy local partner).
     *
     * @return array{data: array<string, mixed>, message: string}
     */
    public function create(User $user, int $bookingId): array
    {
        $booking = $this->bookings->ownedBooking($user->id, $bookingId) ?? throw new ServiceProblemException('Courier booking not found', 404);

        if ($booking->shiprocket_courier_company_id) {
            $r = $this->createForShiprocket($user, $booking->id);
            $p = $r['payment'];

            return ['message' => $r['reused'] ? 'Payment order already exists' : 'Payment order created successfully', 'data' => [
                'courier_booking_id' => $r['booking']->id, 'payment_order_id' => $p->gateway_order_id, 'razorpay_order_id' => $p->gateway_order_id,
                'amount' => (int) $p->amount, 'amount_in_rupees' => $p->amount / 100, 'currency' => $p->currency, 'gateway' => $p->gateway,
                'gateway_key' => $this->gateway->keyId(), 'payment_status' => $p->status,
            ]];
        }

        return $this->createLegacy($user, $booking);
    }

    /**
     * Create (or reuse) the Razorpay order for a Shiprocket booking.
     *
     * @return array{booking: CourierBooking, payment: CourierPayment, reused: bool}
     */
    public function createForShiprocket(User $user, int $bookingId): array
    {
        return $this->bookings->transaction(function () use ($user, $bookingId) {
            // the row lock serialises concurrent create calls for the same booking
            $booking = $this->bookings->lockOwnedBooking($user->id, $bookingId) ?? throw new ServiceProblemException('Courier booking not found', 404);

            if ($booking->payment_status === 'paid' || $booking->status === 'paid') {
                throw new ServiceProblemException('Courier booking is already paid', 400);
            }
            if ($booking->status === 'cancelled') {
                throw new ServiceProblemException('Courier booking is cancelled', 400);
            }
            if (! $booking->shiprocket_courier_company_id || $booking->shiprocket_estimated_cost === null || $booking->platform_charge === null) {
                throw new ServiceProblemException('No courier has been selected for this booking', 400);
            }

            $paise = CourierQuoteService::toPaise($booking->total_amount);
            if ($paise <= 0 || $paise !== CourierQuoteService::toPaise($booking->shiprocket_estimated_cost) + CourierQuoteService::toPaise($booking->platform_charge)) {
                Log::error('Courier booking pricing snapshot is inconsistent', ['booking_id' => $booking->id]);
                throw new ServiceProblemException('Invalid booking amount', 400);
            }

            if ($booking->payment_status === 'initiated' && $booking->payment_order_id
                && ($existing = $this->bookings->openPayment($booking->id, $booking->payment_order_id, $paise))) {
                return ['booking' => $booking, 'payment' => $existing, 'reused' => true];
            }

            // revalidate the selected courier and price with Shiprocket before taking money
            $current = $this->quotes->priceSelection(
                $booking->pickup_pincode, $booking->delivery_pincode, (float) $booking->weight_grams, (float) $booking->length, (float) $booking->width,
                (float) $booking->height, $booking->dimension_unit, $booking->shiprocket_courier_company_id,
            );
            if ($current['total_paise'] !== $paise || $current['rate_paise'] !== CourierQuoteService::toPaise($booking->shiprocket_estimated_cost)) {
                throw ServiceProblemException::quoteChanged('The courier price has changed. Please refresh the quote.');
            }

            try {
                $order = $this->gateway->createOrder($paise, 'INR', ['courier_booking_id' => (string) $booking->id, 'booking_number' => $booking->booking_number, 'user_id' => (string) $user->id]);
            } catch (Throwable $e) {
                Log::error('Courier payment order creation failed', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);
                throw new ServiceProblemException('Could not start the payment. Please try again.', 502);
            }
            if ((int) ($order['amount'] ?? 0) !== $paise) {
                Log::error('Courier payment order amount mismatch', ['booking_id' => $booking->id, 'expected' => $paise, 'got' => $order['amount'] ?? null]);
                throw new ServiceProblemException('Could not start the payment. Please try again.', 502);
            }

            $payment = $this->bookings->createPayment([
                'courier_booking_id' => $booking->id, 'gateway' => 'razorpay', 'gateway_order_id' => $order['id'],
                'amount' => $paise, 'currency' => $order['currency'] ?? 'INR', 'status' => 'initiated',
            ]);
            $this->bookings->updateBooking($booking, ['payment_gateway' => 'razorpay', 'payment_order_id' => $order['id'], 'payment_status' => 'initiated']);

            return ['booking' => $booking, 'payment' => $payment, 'reused' => false];
        });
    }

    /**
     * A legacy booking priced by a local partner: the order is kept on the booking itself.
     *
     * @return array{data: array<string, mixed>, message: string}
     */
    private function createLegacy(User $user, CourierBooking $booking): array
    {
        if (($booking->payment_status ?? 'pending') === 'paid' || $booking->status === 'paid') {
            throw new ServiceProblemException('Courier booking is already paid', 400);
        }

        if (($booking->payment_status ?? null) === 'initiated' && $booking->payment_order_id) {
            return ['message' => 'Payment order already exists', 'data' => [
                'courier_booking_id' => $booking->id, 'payment_order_id' => $booking->payment_order_id, 'razorpay_order_id' => $booking->payment_order_id,
                'amount' => CourierQuoteService::toPaise($booking->total_amount), 'amount_in_rupees' => (float) $booking->total_amount,
                'currency' => 'INR', 'gateway' => $booking->payment_gateway ?? 'razorpay', 'gateway_key' => $this->gateway->keyId(),
            ]];
        }

        $paise = CourierQuoteService::toPaise($booking->total_amount);
        if ($paise <= 0) {
            throw new ServiceProblemException('Invalid booking amount', 400);
        }

        try {
            $order = $this->gateway->createOrder($paise, 'INR', ['courier_booking_id' => (string) $booking->id, 'booking_number' => $booking->booking_number, 'user_id' => (string) $user->id]);
        } catch (Throwable $e) {
            Log::error('Failed to create courier payment order', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);
            throw new ServiceProblemException('Failed to create payment order. Please try again.', 500);
        }
        $this->bookings->updateBooking($booking, ['payment_gateway' => 'razorpay', 'payment_order_id' => $order['id'], 'payment_status' => 'initiated']);

        return ['message' => 'Payment order created successfully', 'data' => [
            'courier_booking_id' => $booking->id, 'payment_order_id' => $order['id'], 'razorpay_order_id' => $order['id'],
            'amount' => (int) $order['amount'], 'amount_in_rupees' => $order['amount'] / 100, 'currency' => $order['currency'] ?? 'INR',
            'gateway' => 'razorpay', 'gateway_key' => $this->gateway->keyId(),
        ]];
    }

    /**
     * Server-side verification for any courier booking (Shiprocket or legacy).
     *
     * @return array{booking: CourierBooking, payment: CourierPayment, already: bool}
     */
    public function verify(User $user, int $bookingId, string $orderId, string $paymentId, string $signature): array
    {
        $outcome = $this->bookings->transaction(function () use ($user, $bookingId, $orderId, $paymentId, $signature) {
            $booking = $this->bookings->lockOwnedBooking($user->id, $bookingId) ?? throw new ServiceProblemException('Courier booking not found', 404);
            $payment = $this->bookings->lockPaymentByOrder($booking->id, $orderId);

            // legacy bookings were paid before payment rows existed: derive the row from the booking's own order
            if (! $payment && ! $booking->shiprocket_courier_company_id && $booking->payment_order_id === $orderId) {
                $payment = $this->bookings->createPayment([
                    'courier_booking_id' => $booking->id, 'gateway' => $booking->payment_gateway ?? 'razorpay', 'gateway_order_id' => $orderId,
                    'amount' => CourierQuoteService::toPaise($booking->total_amount), 'currency' => 'INR', 'status' => 'initiated',
                ]);
            }
            if (! $payment) {
                throw new ServiceProblemException('Payment order does not match this booking', 400);
            }

            // duplicate / replayed verification: report the existing success, change nothing
            if ($payment->status === 'paid') {
                return ['result' => 'already', 'booking' => $booking, 'payment' => $payment];
            }
            if ($booking->payment_status === 'paid' || $booking->status === 'paid') {
                throw new ServiceProblemException('Courier booking is already paid', 409);
            }

            $failure = null;
            $entity = null;
            if (! $this->gateway->verifySignature($orderId, $paymentId, $signature)) {
                $failure = 'Payment signature verification failed';
            } else {
                $entity = $this->gateway->fetchPayment($paymentId);
                if (! $entity || ! $this->entityMatches($payment, $entity, $paymentId)) {
                    $failure = 'Payment details did not match the order';
                    Log::warning('Courier payment verification mismatch', ['booking_id' => $booking->id, 'payment_id' => $payment->id]);
                }
            }
            if ($failure) {
                $this->bookings->updatePayment($payment, ['status' => 'failed', 'failure_reason' => $failure]);
                $this->bookings->updateBooking($booking, ['payment_status' => 'failed']);

                return ['result' => 'failed'];
            }

            $this->bookings->updatePayment($payment, ['status' => 'paid', 'gateway_payment_id' => $paymentId, 'method' => $entity['method'] ?? null, 'failure_reason' => null, 'paid_at' => now()]);
            $this->bookings->updateBooking($booking, ['payment_status' => 'paid', 'status' => 'paid', 'payment_transaction_id' => $paymentId, 'paid_at' => $payment->paid_at]);

            return ['result' => 'ok', 'booking' => $booking, 'payment' => $payment];
        });

        if ($outcome['result'] === 'failed') {
            throw new ServiceProblemException('Payment verification failed.', 400);
        }

        return ['booking' => $outcome['booking'], 'payment' => $outcome['payment'], 'already' => $outcome['result'] === 'already'];
    }

    /** Razorpay's payment id, order id, amount, currency and status must equal what we stored. */
    private function entityMatches(CourierPayment $payment, array $entity, string $paymentId): bool
    {
        return ($entity['id'] ?? null) === $paymentId
            && ($entity['order_id'] ?? null) === $payment->gateway_order_id
            && (int) ($entity['amount'] ?? -1) === (int) $payment->amount
            && strtoupper((string) ($entity['currency'] ?? '')) === strtoupper($payment->currency)
            && in_array($entity['status'] ?? null, ['captured', 'authorized'], true);
    }
}
