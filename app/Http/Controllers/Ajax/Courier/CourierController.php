<?php

namespace App\Http\Controllers\Ajax\Courier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Courier\CancelCourierBookingRequest;
use App\Http\Requests\Courier\CourierPaymentRequest;
use App\Http\Requests\Courier\CourierQuoteRequest;
use App\Http\Requests\Courier\CreateCourierBookingRequest;
use App\Services\Courier\CourierBookingService;
use App\Services\Courier\CourierCancellationService;
use App\Services\Courier\CourierPaymentService;
use App\Services\Courier\CourierQueryService;
use App\Services\Courier\CourierQuoteService;
use App\Services\Courier\CourierShipmentService;
use App\Services\Courier\CourierTrackingService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Courier booking - same paths and payloads as the Taksh API (/service/courier/...). */
class CourierController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly CourierQuoteService $quotes,
        private readonly CourierBookingService $booking,
        private readonly CourierPaymentService $payments,
        private readonly CourierShipmentService $shipments,
        private readonly CourierCancellationService $cancellation,
        private readonly CourierTrackingService $tracking,
        private readonly CourierQueryService $query,
    ) {}

    /** GET /service/courier/delivery-partners - the admin's local delivery partners */
    public function partners(): JsonResponse
    {
        return $this->success($this->query->partners(), 'Delivery partners fetched successfully');
    }

    /** POST /service/courier/quote - compares couriers and prices without creating anything */
    public function quote(CourierQuoteRequest $request): JsonResponse
    {
        $r = $this->quotes->quote($request->validated());

        return $this->success($r['data'], $r['message']);
    }

    /** POST /service/courier/bookings */
    public function store(CreateCourierBookingRequest $request): JsonResponse
    {
        return $this->success($this->booking->create($request->user(), $request->validated(), $request->file('item_photo')), 'Courier booking created successfully', 201);
    }

    /** GET /service/courier/bookings?per_page= */
    public function index(Request $request): JsonResponse
    {
        return $this->success($this->query->history($request->user(), (int) $request->query('per_page', 15)), 'Courier bookings fetched successfully');
    }

    /** GET /service/courier/bookings/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        return $this->success($this->query->detail($request->user(), $id), 'Courier booking details fetched successfully');
    }

    /** POST /service/courier/payment/create {courier_booking_id} */
    public function createPayment(CourierPaymentRequest $request): JsonResponse
    {
        $r = $this->payments->create($request->user(), (int) $request->validated('courier_booking_id'));

        return $this->success($r['data'], $r['message']);
    }

    /** POST /service/courier/payment/verify - once paid, the Shiprocket order, AWB and pickup are created right away */
    public function verifyPayment(CourierPaymentRequest $request): JsonResponse
    {
        $v = $request->validated();
        $user = $request->user();
        $r = $this->payments->verify($user, (int) $v['courier_booking_id'], $v['razorpay_order_id'], $v['razorpay_payment_id'], $v['razorpay_signature']);
        $booking = $r['booking'];

        // a failure here never undoes the payment; the client can retry with POST /service/courier/bookings/{id}/shiprocket
        $shipment = null;
        if ($booking->shiprocket_courier_company_id) {
            try {
                $shipment = $this->shipments->ensureShipment($user, $booking->id);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $message = $r['already'] ? 'Payment already verified and booking confirmed' : 'Payment verified successfully. Courier booking confirmed.';

        return $this->success([
            'courier_booking_id' => $booking->id, 'status' => $booking->status, 'payment_status' => $booking->payment_status,
            'transaction_id' => $booking->payment_transaction_id, 'paid_at' => $booking->paid_at?->toDateTimeString(),
            'amount_in_rupees' => (float) $booking->total_amount, 'courier_name' => $booking->courier_name, 'shipment' => $shipment, 'message' => $message,
        ], $message);
    }

    /** POST /service/courier/bookings/{id}/shiprocket - create (or resume) the order, AWB and pickup of a paid booking */
    public function createShipment(Request $request, int $id): JsonResponse
    {
        $state = $this->shipments->ensureShipment($request->user(), $id);

        return $this->success($state, match (true) {
            $state['in_progress'] => 'Shipment is already being created. Please check again shortly.',
            $state['action_required'] === null => 'Shipment created and pickup requested.',
            $state['action_required'] === 'pickup' => 'Shipment created. Pickup request is pending, please retry shortly.',
            default => 'Shipment created. Courier assignment is pending, please retry shortly.',
        });
    }

    /** POST /service/courier/bookings/{id}/cancel {reason?} */
    public function cancel(CancelCourierBookingRequest $request, int $id): JsonResponse
    {
        $state = $this->cancellation->cancel($request->user()->id, $id, $request->validated()['reason'] ?? null);
        $message = match ($state['refund_status']) {
            null => 'Booking cancelled.',
            'success' => 'Booking cancelled and refund processed.',
            'failed' => 'Booking cancelled. The refund could not be completed yet and will be retried.',
            default => 'Booking cancelled. Your refund is being processed.',
        };

        return $this->success($state + ['message' => $message], $message);
    }

    /** POST /service/courier/bookings/{id}/track - pulls the newest tracking from Shiprocket and stores what is new */
    public function track(Request $request, int $id): JsonResponse
    {
        $result = $this->tracking->refresh($request->user(), $id);

        return $this->success($result + ['booking' => $this->query->detail($request->user(), $id)], 'Tracking updated');
    }
}
