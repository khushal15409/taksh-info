<?php

namespace App\Services\Courier;

use App\Exceptions\ServiceProblemException;
use App\Models\CourierShipment;
use App\Models\User;
use App\Repositories\Contracts\CourierBookingRepositoryInterface;
use App\Repositories\Contracts\CourierShipmentRepositoryInterface;
use App\Services\Shiprocket\ShiprocketApi;
use App\Services\Shiprocket\ShiprocketException;
use App\Services\Shiprocket\ShiprocketStatus;
use Illuminate\Support\Facades\Log;

/**
 * Stores tracking events for a shipment and moves its current status, as taksh-admin's webhook and sync do (same tables, so both
 * can run side by side). Shiprocket events carry no id, so the dedupe key is sha256(shipment | normalized status | event time to
 * the second): the same scan arriving twice collapses into one row. The current status only moves via ShiprocketStatus::shouldApply,
 * so late or repeated events are kept as history without sending the shipment backwards.
 */
class CourierTrackingService
{
    public function __construct(
        private readonly CourierBookingRepositoryInterface $bookings,
        private readonly CourierShipmentRepositoryInterface $shipments,
        private readonly ShiprocketApi $shiprocket,
    ) {}

    /**
     * Ask Shiprocket for the customer's own shipment right now and store what is new.
     *
     * @return array{stored: int, duplicates: int, status: ?string}
     */
    public function refresh(User $user, int $bookingId): array
    {
        $booking = $this->bookings->ownedBooking($user->id, $bookingId) ?? throw new ServiceProblemException('Courier booking not found', 404);
        $shipment = $this->shipments->shipmentOf($booking->id);

        if (! $shipment || ! $shipment->awb_code) {
            return ['stored' => 0, 'duplicates' => 0, 'status' => $shipment?->shipment_status];   // nothing to track until an AWB exists
        }
        if (ShiprocketStatus::isTerminal($shipment->shipment_status)) {
            return ['stored' => 0, 'duplicates' => 0, 'status' => $shipment->shipment_status];
        }

        try {
            $result = $this->shiprocket->track($shipment->awb_code);
        } catch (ShiprocketException $e) {
            Log::warning('Courier tracking fetch failed', ['booking_id' => $booking->id, 'category' => $e->category, 'status' => $e->httpStatus]);
            throw new ServiceProblemException('Tracking is temporarily unavailable. Please try again shortly.', 503);
        }

        return $this->ingest($shipment, $result['events'], 'website', $result['tracking_url']);
    }

    /**
     * @param  list<array{label: ?string, status_code?: ?string, description?: ?string, location?: ?string, time?: ?\Carbon\Carbon}>  $events
     * @return array{stored: int, duplicates: int, status: ?string}
     */
    public function ingest(CourierShipment $shipment, array $events, string $source, ?string $trackingUrl = null): array
    {
        // oldest first so the shipment ends on the newest status
        usort($events, fn ($a, $b) => ($a['time']?->getTimestamp() ?? PHP_INT_MAX) <=> ($b['time']?->getTimestamp() ?? PHP_INT_MAX));

        $stored = 0;
        $duplicates = 0;
        foreach ($events as $event) {
            $this->ingestOne($shipment->id, $event, $source) ? $stored++ : $duplicates++;
        }

        $fresh = $this->shipments->freshShipment($shipment);
        if ($trackingUrl && ! $fresh->tracking_url && preg_match('#^https?://#i', $trackingUrl)) {
            $this->shipments->setTrackingUrl($fresh, $trackingUrl);
        }

        return ['stored' => $stored, 'duplicates' => $duplicates, 'status' => $fresh->shipment_status];
    }

    /** @return bool true when a new event row was stored */
    private function ingestOne(int $shipmentId, array $event, string $source): bool
    {
        $label = trim((string) ($event['label'] ?? ''));
        $normalized = ShiprocketStatus::normalize($label);
        $time = $event['time'] ?? null;
        $key = hash('sha256', $shipmentId.'|'.($normalized ?? strtolower($label)).'|'.($time?->format('Y-m-d H:i:s') ?? 'notime'));

        return $this->shipments->transaction(function () use ($shipmentId, $event, $source, $label, $normalized, $time, $key) {
            // the shipment row lock serialises concurrent writers for this shipment
            $shipment = $this->shipments->lockShipment($shipmentId);
            if ($this->shipments->eventExists($shipmentId, $key)) {
                return false;
            }

            $latest = $this->shipments->latestEventTime($shipmentId);
            $created = $this->shipments->createEvent([
                'courier_shipment_id' => $shipmentId, 'dedupe_key' => $key,
                'status' => mb_substr($label !== '' ? $label : 'UNKNOWN', 0, 100), 'normalized_status' => $normalized,
                'status_code' => isset($event['status_code']) ? mb_substr((string) $event['status_code'], 0, 50) : null,
                'description' => isset($event['description']) ? mb_substr((string) $event['description'], 0, 1000) : null,
                'location' => isset($event['location']) ? mb_substr((string) $event['location'], 0, 255) : null,
                'event_time' => $time, 'source' => $source,
            ]);
            if (! $created) {
                return false;   // lost a race on the unique key
            }

            if (ShiprocketStatus::shouldApply($shipment->shipment_status, $normalized, $time, $latest)) {
                $this->shipments->updateShipment($shipment, ['shipment_status' => $normalized]);
                $this->shipments->syncBookingShipmentStatus($shipment->courier_booking_id, $normalized);
            }

            return true;
        });
    }
}
