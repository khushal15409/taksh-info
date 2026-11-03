<?php

namespace App\Repositories\Eloquent;

use App\Models\CourierBooking;
use App\Models\CourierPayment;
use App\Models\CourierRefund;
use App\Models\CourierShipment;
use App\Models\CourierTrackingEvent;
use App\Repositories\Contracts\CourierShipmentRepositoryInterface;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CourierShipmentRepository implements CourierShipmentRepositoryInterface
{
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function shipmentOf(int $bookingId): ?CourierShipment
    {
        return CourierShipment::where('courier_booking_id', $bookingId)->first();
    }

    public function createShipment(array $attributes): CourierShipment
    {
        try {
            return CourierShipment::create($attributes);
        } catch (QueryException $e) {
            // lost a race on the unique booking id: use the winner's row
            return $this->shipmentOf($attributes['courier_booking_id']) ?? throw $e;
        }
    }

    public function updateShipment(CourierShipment $shipment, array $attributes): CourierShipment
    {
        $shipment->update($attributes);

        return $shipment;
    }

    public function freshShipment(CourierShipment $shipment): CourierShipment
    {
        return $shipment->refresh();
    }

    public function lockBookingAndShipment(int $bookingId): array
    {
        return [
            CourierBooking::whereKey($bookingId)->lockForUpdate()->first(),
            CourierShipment::where('courier_booking_id', $bookingId)->lockForUpdate()->first(),
        ];
    }

    public function lockShipment(int $shipmentId): ?CourierShipment
    {
        return CourierShipment::whereKey($shipmentId)->lockForUpdate()->first();
    }

    public function updateBooking(CourierBooking $booking, array $attributes): CourierBooking
    {
        $booking->update($attributes);

        return $booking;
    }

    public function syncBookingShipmentStatus(int $bookingId, string $status): void
    {
        CourierBooking::whereKey($bookingId)->update(['shipment_status' => $status]);
    }

    public function eventExists(int $shipmentId, string $dedupeKey): bool
    {
        return CourierTrackingEvent::where('courier_shipment_id', $shipmentId)->where('dedupe_key', $dedupeKey)->exists();
    }

    public function latestEventTime(int $shipmentId): ?Carbon
    {
        $latest = CourierTrackingEvent::where('courier_shipment_id', $shipmentId)->max('event_time');

        return $latest ? Carbon::parse($latest) : null;
    }

    public function createEvent(array $attributes): bool
    {
        try {
            CourierTrackingEvent::create($attributes);

            return true;
        } catch (QueryException) {
            return false;
        }
    }

    public function setTrackingUrl(CourierShipment $shipment, string $url): void
    {
        $shipment->update(['tracking_url' => $url]);
        CourierBooking::whereKey($shipment->courier_booking_id)->whereNull('tracking_url')->update(['tracking_url' => $url]);
    }

    public function firstRefund(int $bookingId): ?CourierRefund
    {
        return CourierRefund::where('courier_booking_id', $bookingId)->orderBy('id')->first();
    }

    public function ensureRefund(string $reference, array $attributes): CourierRefund
    {
        try {
            return CourierRefund::firstOrCreate(['reference' => $reference], $attributes);
        } catch (QueryException) {
            return CourierRefund::where('reference', $reference)->first();
        }
    }

    public function findRefund(int $id): ?CourierRefund
    {
        return CourierRefund::with('payment')->find($id);
    }

    public function claimRefund(int $id): bool
    {
        return (bool) CourierRefund::whereKey($id)->whereIn('status', ['pending', 'failed'])->update(['status' => 'processing', 'failure_reason' => null]);
    }

    public function setGatewayRefundId(int $id, string $gatewayRefundId): void
    {
        CourierRefund::whereKey($id)->whereNull('gateway_refund_id')->update(['gateway_refund_id' => $gatewayRefundId]);
    }

    public function markRefundSuccess(CourierRefund $refund): void
    {
        CourierRefund::whereKey($refund->id)->where('status', '!=', 'success')->update(['status' => 'success', 'processed_at' => now(), 'failure_reason' => null]);
        CourierPayment::whereKey($refund->courier_payment_id)->where('status', 'paid')->update(['status' => 'refunded']);
    }

    public function markRefundFailed(int $id, string $reason): void
    {
        CourierRefund::whereKey($id)->where('status', '!=', 'success')->update(['status' => 'failed', 'failure_reason' => $reason]);
    }

    public function failUnsentRefund(int $id): void
    {
        CourierRefund::whereKey($id)->where('status', 'processing')->whereNull('gateway_refund_id')->update(['status' => 'failed', 'failure_reason' => 'The refund was not found at the gateway.']);
    }

    public function paidPayment(int $bookingId): ?CourierPayment
    {
        return CourierPayment::where('courier_booking_id', $bookingId)->where('status', 'paid')->first();
    }
}
