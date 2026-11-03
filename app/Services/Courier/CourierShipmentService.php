<?php

namespace App\Services\Courier;

use App\Exceptions\ServiceProblemException;
use App\Models\CourierBooking;
use App\Models\CourierShipment;
use App\Models\User;
use App\Repositories\Contracts\CourierBookingRepositoryInterface;
use App\Repositories\Contracts\CourierShipmentRepositoryInterface;
use App\Services\Shiprocket\ShiprocketApi;
use App\Services\Shiprocket\ShiprocketException;
use App\Services\Shiprocket\ShiprocketStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Drives a PAID courier booking through Shiprocket: order -> AWB -> pickup, as taksh-admin does.
 *
 * The database is the source of truth and every step is skipped once its result is stored, so the whole call can be repeated
 * safely. shipment_status: pending -> order_created -> awb_assigned -> pickup_requested. failure_reason holds the last safe failure
 * text; ORDER_SENT marks "create-order request sent, outcome unknown": the next attempt looks the order up at Shiprocket before
 * creating another. A per-booking cache lock keeps concurrent requests from calling Shiprocket twice, and no DB transaction is held
 * open across a Shiprocket call.
 */
class CourierShipmentService
{
    public const ORDER_SENT = 'order_request_sent';

    public function __construct(
        private readonly CourierBookingRepositoryInterface $bookings,
        private readonly CourierShipmentRepositoryInterface $shipments,
        private readonly ShiprocketApi $shiprocket,
    ) {}

    /** @return array<string, mixed> normalized state (see statePayload) */
    public function ensureShipment(User $user, int $bookingId): array
    {
        $booking = $this->bookings->ownedBooking($user->id, $bookingId) ?? throw new ServiceProblemException('Courier booking not found', 404);

        return $this->run($booking, $user);
    }

    private function run(CourierBooking $booking, User $user): array
    {
        $this->assertShippable($booking);

        $lock = Cache::lock("courier-shipment:{$booking->id}", 240);
        if (! $lock->get()) {
            // another request is already talking to Shiprocket for this booking
            return $this->statePayload($this->bookings->fresh($booking), $this->shipments->shipmentOf($booking->id), inProgress: true);
        }

        try {
            $shipment = $this->shipments->shipmentOf($booking->id) ?? $this->createRow($booking);

            if (! $shipment->shiprocket_order_id) {
                $this->createOrderStep($booking, $shipment, $user);
            }
            if ($shipment->shiprocket_order_id && ! $shipment->awb_code) {
                $this->awbStep($booking, $shipment);
            }
            if ($shipment->awb_code && $shipment->pickup_status !== 'requested') {
                $this->pickupStep($booking, $shipment);
            }

            return $this->statePayload($this->bookings->fresh($booking), $this->shipments->freshShipment($shipment));
        } finally {
            $lock->release();
        }
    }

    // ---- steps -------------------------------------------------------------------------------

    private function createOrderStep(CourierBooking $booking, CourierShipment $shipment, User $user): void
    {
        // config problems are ours to fix: no call, no state change
        if (! filled(config('shiprocket.pickup_location')) || ! is_numeric(config('shiprocket.default_declared_value'))) {
            Log::error('Shiprocket shipment config missing (SHIPROCKET_PICKUP_LOCATION / SHIPROCKET_DEFAULT_DECLARED_VALUE)', ['booking_id' => $booking->id]);
            throw new ServiceProblemException('Shipment creation is temporarily unavailable. Please try again later.', 503);
        }

        $order = null;
        if ($shipment->failure_reason === self::ORDER_SENT) {
            // a previous attempt may have created the order at Shiprocket without us hearing back;
            // if the lookup itself fails we cannot tell, so we must not create: try again later
            try {
                $order = $this->shiprocket->findOrderByChannelId($shipment->channel_order_id);
            } catch (ShiprocketException $e) {
                $this->logFailure('order-lookup', $booking, $e);
                throw new ServiceProblemException('Shipment creation is temporarily unavailable. Please try again shortly.', 503);
            }
        }

        if ($order === null) {
            $this->shipments->updateShipment($shipment, ['failure_reason' => self::ORDER_SENT]);
            try {
                $order = $this->shiprocket->createOrder($booking, $user);
            } catch (ShiprocketException $e) {
                $this->logFailure('order', $booking, $e);
                if ($this->isDefinite($e)) {
                    // Shiprocket refused the request: nothing was created, so the next attempt may create
                    $this->shipments->updateShipment($shipment, ['failure_reason' => 'Shiprocket rejected the order']);
                    throw new ServiceProblemException('The shipment could not be created. Please contact support.', 422);
                }
                // timeout / 5xx / garbage: outcome unknown, the ORDER_SENT marker stays
                throw new ServiceProblemException('Shipment creation is temporarily unavailable. Please try again shortly.', 503);
            }
        }

        $this->persist($booking->id, function (CourierBooking $b, CourierShipment $s) use ($order) {
            if ($s->shiprocket_order_id) {
                return;   // a concurrent writer stored it first
            }
            $this->shipments->updateShipment($s, [
                'shiprocket_order_id' => $order['order_id'], 'shiprocket_shipment_id' => $order['shipment_id'],
                'courier_name' => $order['courier_name'] ?? $s->courier_name ?? $b->courier_name,
                'awb_code' => $order['awb_code'] ?? $s->awb_code,
                'shipment_status' => $this->forward($s->shipment_status, $order['awb_code'] ? 'awb_assigned' : 'order_created'),
                'failure_reason' => null,
            ]);
            $this->syncBooking($b, $s);
        });
        $this->shipments->freshShipment($shipment);
    }

    private function awbStep(CourierBooking $booking, CourierShipment $shipment): void
    {
        try {
            $awb = $this->shiprocket->assignAwb($shipment->shiprocket_shipment_id, (string) $booking->shiprocket_courier_company_id);
        } catch (ShiprocketException $e) {
            $this->logFailure('awb', $booking, $e);
            $this->shipments->updateShipment($shipment, ['failure_reason' => 'AWB assignment failed']);

            return;   // the order exists; the state tells the caller to retry AWB
        }

        $this->persist($booking->id, function (CourierBooking $b, CourierShipment $s) use ($awb) {
            if ($s->awb_code) {
                return;
            }
            $this->shipments->updateShipment($s, [
                'awb_code' => $awb['awb_code'], 'courier_name' => $awb['courier_name'] ?? $s->courier_name ?? $b->courier_name,
                'shipment_status' => $this->forward($s->shipment_status, 'awb_assigned'), 'failure_reason' => null,
            ]);
            $this->syncBooking($b, $s);
        });
        $this->shipments->freshShipment($shipment);
    }

    private function pickupStep(CourierBooking $booking, CourierShipment $shipment): void
    {
        try {
            $this->shiprocket->requestPickup($shipment->shiprocket_shipment_id);
        } catch (ShiprocketException $e) {
            $this->logFailure('pickup', $booking, $e);
            $this->shipments->updateShipment($shipment, ['failure_reason' => 'Pickup request failed']);

            return;
        }

        $this->persist($booking->id, function (CourierBooking $b, CourierShipment $s) {
            $this->shipments->updateShipment($s, ['pickup_status' => 'requested', 'shipment_status' => $this->forward($s->shipment_status, 'pickup_requested'), 'failure_reason' => null]);
            $this->syncBooking($b, $s);
        });
        $this->shipments->freshShipment($shipment);
    }

    // ---- helpers -----------------------------------------------------------------------------

    /** Paid, owned, fully snapshotted: otherwise Shiprocket is never called. */
    private function assertShippable(CourierBooking $booking): void
    {
        $paid = $this->shipments->paidPayment($booking->id);
        if ($booking->payment_status !== 'paid' || $booking->status !== 'paid' || ! $paid || (int) $paid->amount !== CourierQuoteService::toPaise($booking->total_amount)) {
            throw new ServiceProblemException('Payment has not been completed for this booking.', 409);
        }
        if (! $booking->shiprocket_courier_company_id || $booking->shiprocket_estimated_cost === null || $booking->platform_charge === null) {
            throw new ServiceProblemException('No courier has been selected for this booking.', 409);
        }
        if (! $booking->pickup_pincode || ! $booking->delivery_pincode || ! $booking->delivery_address || ! $booking->delivery_name || ! $booking->delivery_mobile
            || (float) $booking->weight_grams <= 0 || (float) $booking->length <= 0 || (float) $booking->width <= 0 || (float) $booking->height <= 0) {
            throw new ServiceProblemException('This booking is missing shipment details.', 422);
        }
    }

    private function createRow(CourierBooking $booking): CourierShipment
    {
        return $this->shipments->createShipment([
            'courier_booking_id' => $booking->id, 'provider' => 'shiprocket', 'channel_order_id' => $booking->booking_number,
            'courier_company_id' => $booking->shiprocket_courier_company_id, 'courier_name' => $booking->courier_name, 'shipment_status' => 'pending',
        ]);
    }

    /** Keep a status already set by tracking if it is further along than the step that just finished. */
    private function forward(?string $current, string $step): string
    {
        return ShiprocketStatus::rank($current) > ShiprocketStatus::rank($step) || ShiprocketStatus::isTerminal($current) ? $current : $step;
    }

    /** Short transaction with the booking and shipment rows locked; re-reads so duplicate results are skipped. */
    private function persist(int $bookingId, callable $write): void
    {
        $this->shipments->transaction(function () use ($bookingId, $write) {
            [$b, $s] = $this->shipments->lockBookingAndShipment($bookingId);
            $write($b, $s);
        });
    }

    private function syncBooking(CourierBooking $b, CourierShipment $s): void
    {
        $this->shipments->updateBooking($b, [
            'shiprocket_order_id' => $s->shiprocket_order_id, 'shiprocket_shipment_id' => $s->shiprocket_shipment_id, 'awb_code' => $s->awb_code,
            'courier_name' => $s->courier_name ?? $b->courier_name, 'shipment_status' => $s->shipment_status, 'pickup_status' => $s->pickup_status,
        ]);
    }

    /** True when Shiprocket answered and refused (4xx): nothing was created. */
    private function isDefinite(ShiprocketException $e): bool
    {
        return in_array($e->category, [ShiprocketException::CLIENT_ERROR, ShiprocketException::AUTHENTICATION, ShiprocketException::NOT_CONFIGURED], true);
    }

    private function logFailure(string $step, CourierBooking $booking, ShiprocketException $e): void
    {
        Log::warning('Courier shipment step failed', ['booking_id' => $booking->id, 'step' => $step, 'category' => $e->category, 'status' => $e->httpStatus]);
    }

    /** What the API may return: no raw Shiprocket data. */
    private function statePayload(CourierBooking $booking, ?CourierShipment $s, bool $inProgress = false): array
    {
        $action = match (true) {
            $s?->pickup_status === 'requested' => null,
            $s?->awb_code !== null && $s?->awb_code !== '' => 'pickup',
            $s?->shiprocket_order_id !== null => 'awb',
            default => 'order',
        };

        return [
            'booking_id' => $booking->id, 'payment_status' => $booking->payment_status, 'shipment_status' => $s?->shipment_status ?? 'pending',
            'shiprocket_order_id' => $s?->shiprocket_order_id, 'shiprocket_shipment_id' => $s?->shiprocket_shipment_id, 'awb_code' => $s?->awb_code,
            'courier_name' => $s?->courier_name ?? $booking->courier_name, 'pickup_status' => $s?->pickup_status, 'action_required' => $action, 'in_progress' => $inProgress,
        ];
    }
}
