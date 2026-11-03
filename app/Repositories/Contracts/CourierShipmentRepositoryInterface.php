<?php

namespace App\Repositories\Contracts;

use App\Models\CourierBooking;
use App\Models\CourierPayment;
use App\Models\CourierRefund;
use App\Models\CourierShipment;
use Carbon\Carbon;
use Closure;

/** Shipments, their tracking events and the refunds of cancelled bookings. */
interface CourierShipmentRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    public function shipmentOf(int $bookingId): ?CourierShipment;

    /** Creates the one shipment row of a booking; when a concurrent request won the race, that row is returned. */
    public function createShipment(array $attributes): CourierShipment;

    public function updateShipment(CourierShipment $shipment, array $attributes): CourierShipment;

    public function freshShipment(CourierShipment $shipment): CourierShipment;

    /** @return array{0: ?CourierBooking, 1: ?CourierShipment} both rows locked */
    public function lockBookingAndShipment(int $bookingId): array;

    public function lockShipment(int $shipmentId): ?CourierShipment;

    public function updateBooking(CourierBooking $booking, array $attributes): CourierBooking;

    /** Mirror a shipment's current status on its booking. */
    public function syncBookingShipmentStatus(int $bookingId, string $status): void;

    // tracking events
    public function eventExists(int $shipmentId, string $dedupeKey): bool;

    public function latestEventTime(int $shipmentId): ?Carbon;

    /** @return bool false when a concurrent writer stored the same event first */
    public function createEvent(array $attributes): bool;

    public function setTrackingUrl(CourierShipment $shipment, string $url): void;

    // refunds (service_courier_refunds; `amount` in rupees)
    public function firstRefund(int $bookingId): ?CourierRefund;

    public function ensureRefund(string $reference, array $attributes): CourierRefund;

    public function findRefund(int $id): ?CourierRefund;

    /** Atomically move pending/failed -> processing; false when someone else already has it. */
    public function claimRefund(int $id): bool;

    public function setGatewayRefundId(int $id, string $gatewayRefundId): void;

    public function markRefundSuccess(CourierRefund $refund): void;

    /** Never overrides a success. */
    public function markRefundFailed(int $id, string $reason): void;

    /** A processing refund that never reached the gateway goes back to failed so the next resume creates it. */
    public function failUnsentRefund(int $id): void;

    public function paidPayment(int $bookingId): ?CourierPayment;
}
