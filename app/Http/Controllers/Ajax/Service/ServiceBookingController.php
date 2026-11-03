<?php

namespace App\Http\Controllers\Ajax\Service;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\BookServiceRequest;
use App\Http\Requests\Service\CancelServiceBookingRequest;
use App\Http\Requests\Service\ListServiceBookingsRequest;
use App\Http\Requests\Service\VerifyServicePaymentRequest;
use App\Models\ServiceMarketplaceBooking as Booking;
use App\Models\ServiceMarketplacePayment as Payment;
use App\Repositories\Contracts\ServiceBookingRepositoryInterface;
use App\Services\Marketplace\ServiceBookingCancellationService;
use App\Services\Marketplace\ServiceBookingQueryService;
use App\Services\Marketplace\ServiceBookingService;
use App\Services\Payments\RazorpayGateway;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The customer's home-service bookings - same payloads as the Taksh API (/service/book...). */
class ServiceBookingController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ServiceBookingService $booking,
        private readonly ServiceBookingCancellationService $cancellation,
        private readonly ServiceBookingQueryService $query,
        private readonly ServiceBookingRepositoryInterface $bookings,
        private readonly RazorpayGateway $gateway,
    ) {}

    /** POST /service/book - checks availability, creates the booking and the Razorpay order */
    public function book(BookServiceRequest $request): JsonResponse
    {
        $v = $request->validated();
        $result = $this->booking->book(
            $request->user(), (int) $v['service_id'], isset($v['address_id']) ? (int) $v['address_id'] : null, $request->addressDetails(),
            $v['customer_notes'] ?? null, isset($v['scheduled_for']) ? new \DateTimeImmutable($v['scheduled_for']) : null, $request->idempotencyKey(),
        );

        return $this->success(
            $this->payload($result['booking'], $result['payment']),
            $result['reused'] ? 'Booking already exists for this request.' : 'Booking created. Continue to payment.',
            $result['reused'] ? 200 : 201,
        );
    }

    /** POST /service/book/verify */
    public function verify(VerifyServicePaymentRequest $request): JsonResponse
    {
        $v = $request->validated();
        $result = $this->booking->verify($request->user(), $v['razorpay_order_id'], $v['razorpay_payment_id'], $v['razorpay_signature']);
        $b = $result['booking'];

        return $this->success(
            ['booking_id' => $b->id, 'booking_number' => $b->booking_number, 'status' => $b->status, 'payment_status' => $b->payment_status],
            $result['already'] ? 'Payment already verified' : 'Payment verified successfully',
        );
    }

    /** POST /service/book/{id}/cancel */
    public function cancel(CancelServiceBookingRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        $result = $this->cancellation->cancel($user->id, $this->bookings->resolveId($user->id, $id), $request->validated()['reason'] ?? null);
        $b = $result['booking'];

        return $this->success([
            'booking_id' => $b->id, 'booking_number' => $b->booking_number, 'status' => $b->status,
            'refund_amount' => (float) ($result['refund']->requested_amount ?? 0), 'refund_status' => $b->refund_status,
        ], 'Booking cancelled successfully.');
    }

    /** GET /service/book?category=pending|upcoming|active|completed|cancelled&page=&per_page= */
    public function index(ListServiceBookingsRequest $request): JsonResponse
    {
        return $this->success($this->query->history($request->user(), $request->input('category'), (int) $request->input('per_page', 15)), 'Service bookings fetched successfully.');
    }

    /** GET /service/book/{id} (the booking id or its booking number) */
    public function show(Request $request, string $id): JsonResponse
    {
        return $this->success($this->query->detail($request->user(), $id));
    }

    /** What the browser's Razorpay checkout needs, plus the booking. */
    private function payload(Booking $booking, Payment $payment): array
    {
        return [
            'razorpay_order_id' => $payment->gateway_order_id,
            'amount' => (float) $booking->service_charge,
            'amount_in_paise' => (int) $payment->amount,
            'currency' => $payment->currency,
            'razorpay_key' => $this->gateway->keyId(),
            'gateway' => $payment->gateway,
            'booking' => [
                'id' => $booking->id, 'booking_number' => $booking->booking_number, 'status' => $booking->status, 'payment_status' => $booking->payment_status,
                'service' => ['id' => $booking->service_service_id, 'name' => $booking->service->name ?? null], 'pincode' => $booking->pincode,
                'service_charge' => (float) $booking->service_charge, 'currency' => $booking->currency,
                'scheduled_for' => $booking->scheduled_for?->toIso8601String(), 'paid_at' => $booking->paid_at?->toIso8601String(),
            ],
        ];
    }
}
