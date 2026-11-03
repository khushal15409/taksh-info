<?php

namespace Tests\Support;

use App\Models\CourierBooking;
use App\Models\CourierDeliveryPartner;
use App\Models\CourierPayment;
use App\Models\CourierRefund;
use App\Models\CourierShipment;
use App\Models\CourierTrackingEvent;
use App\Models\User;
use App\Repositories\Contracts\CourierBookingRepositoryInterface;
use App\Repositories\Contracts\CourierShipmentRepositoryInterface;
use App\Services\Courier\CourierPhotoStore;
use App\Services\Shiprocket\ShiprocketApi;
use App\Services\Shiprocket\ShiprocketException;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

/** The in-memory "tables" behind the courier fakes. */
class FakeCourierDb
{
    /** @var array<int, CourierBooking> */
    public array $bookings = [];

    /** @var array<int, CourierPayment> */
    public array $payments = [];

    /** @var array<int, CourierShipment> */
    public array $shipments = [];

    /** @var array<int, CourierRefund> */
    public array $refunds = [];

    /** @var list<CourierTrackingEvent> */
    public array $events = [];

    /** @var list<CourierDeliveryPartner> */
    public array $partners = [];

    public mixed $serviceCharge = 20;

    public int $seq = 0;

    public function booking(array $attrs = []): CourierBooking
    {
        $id = $attrs['id'] ?? ++$this->seq + 200;

        return $this->bookings[$id] = (new CourierBooking)->forceFill($attrs + [
            'id' => $id, 'booking_number' => 'CRB-'.$id, 'user_id' => 5, 'customer_name' => 'Asha', 'customer_mobile' => '9876543210',
            'pickup_address' => '1 Pickup Rd', 'pickup_pincode' => '380001', 'delivery_name' => 'Ravi', 'delivery_mobile' => '9123456780',
            'delivery_address' => '2 Drop St', 'delivery_pincode' => '400001', 'weight_grams' => 500, 'dimension_unit' => 'cm', 'length' => 10, 'width' => 10, 'height' => 10,
            'volumetric_weight_grams' => 200, 'chargeable_weight_grams' => 500, 'chargeable_weight_source' => 'actual', 'total_amount' => 100, 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** A Shiprocket booking: rate 80 + service charge 20 = 100. */
    public function shiprocketBooking(array $attrs = []): CourierBooking
    {
        return $this->booking($attrs + ['shiprocket_courier_company_id' => '12', 'courier_name' => 'Delhivery', 'shiprocket_estimated_cost' => 80, 'platform_charge' => 20, 'shiprocket_estimated_delivery_days' => 3]);
    }

    public function payment(CourierBooking $b, array $attrs = []): CourierPayment
    {
        $id = $attrs['id'] ?? ++$this->seq + 500;

        return $this->payments[$id] = (new CourierPayment)->forceFill($attrs + [
            'id' => $id, 'courier_booking_id' => $b->id, 'gateway' => 'razorpay', 'gateway_order_id' => 'order_c'.$id, 'amount' => (int) round($b->total_amount * 100), 'currency' => 'INR', 'status' => 'initiated',
        ]);
    }

    /** A booking paid through Razorpay, with its payment row. */
    public function paidBooking(array $attrs = []): CourierBooking
    {
        $b = $this->shiprocketBooking($attrs + ['status' => 'paid', 'payment_status' => 'paid', 'paid_at' => now(), 'payment_order_id' => 'order_paid']);
        $this->payment($b, ['status' => 'paid', 'gateway_order_id' => 'order_paid', 'gateway_payment_id' => 'pay_c1', 'paid_at' => now()]);

        return $b;
    }
}

class FakeCourierBookingRepository implements CourierBookingRepositoryInterface
{
    public function __construct(private readonly FakeCourierDb $db) {}

    public function transaction(Closure $callback): mixed
    {
        return $callback();
    }

    public function activePartners(): Collection
    {
        return collect($this->db->partners)->filter(fn ($p) => $p->is_active)->values();
    }

    public function activePartner(int $id): ?CourierDeliveryPartner
    {
        return $this->activePartners()->firstWhere('id', $id);
    }

    public function courierServiceCharge(): mixed
    {
        return $this->db->serviceCharge;
    }

    public function createBooking(array $attributes): CourierBooking
    {
        return $this->db->booking($attributes + ['created_at' => now(), 'updated_at' => now()]);
    }

    public function ownedBooking(int $userId, int $id): ?CourierBooking
    {
        $b = $this->db->bookings[$id] ?? null;

        return $b && $b->user_id === $userId ? $b : null;
    }

    public function lockOwnedBooking(int $userId, int $id): ?CourierBooking
    {
        return $this->ownedBooking($userId, $id);
    }

    public function updateBooking(CourierBooking $booking, array $attributes): CourierBooking
    {
        return $booking->forceFill($attributes);
    }

    public function fresh(CourierBooking $booking): CourierBooking
    {
        return $booking;
    }

    public function paginateForUser(int $userId, int $perPage): LengthAwarePaginator
    {
        $rows = collect($this->db->bookings)->filter(fn ($b) => $b->user_id === $userId)->sortByDesc('id')->values()->each(fn ($b) => $b->setRelation('deliveryPartner', null));

        return new Paginator($rows, $rows->count(), $perPage, 1);
    }

    public function detailForUser(int $userId, int $id): ?CourierBooking
    {
        $b = $this->ownedBooking($userId, $id);
        if (! $b) {
            return null;
        }
        $shipment = collect($this->db->shipments)->first(fn ($s) => $s->courier_booking_id === $id);
        $shipment?->setRelation('trackingEvents', collect($this->db->events)->where('courier_shipment_id', $shipment->id)->sortBy('event_time')->values());

        return $b->setRelation('deliveryPartner', null)->setRelation('shipment', $shipment)
            ->setRelation('payments', collect($this->db->payments)->filter(fn ($p) => $p->courier_booking_id === $id && in_array($p->status, ['paid', 'refunded']))->sortByDesc('id')->values())
            ->setRelation('refunds', collect($this->db->refunds)->filter(fn ($r) => $r->courier_booking_id === $id)->sortByDesc('id')->values());
    }

    public function lockPaymentByOrder(int $bookingId, string $orderId): ?CourierPayment
    {
        return collect($this->db->payments)->first(fn ($p) => $p->courier_booking_id === $bookingId && $p->gateway_order_id === $orderId);
    }

    public function openPayment(int $bookingId, string $orderId, int $amountPaise): ?CourierPayment
    {
        return collect($this->db->payments)->first(fn ($p) => $p->courier_booking_id === $bookingId && $p->gateway_order_id === $orderId && $p->status === 'initiated' && $p->amount === $amountPaise);
    }

    public function paidPayment(int $bookingId): ?CourierPayment
    {
        return collect($this->db->payments)->first(fn ($p) => $p->courier_booking_id === $bookingId && $p->status === 'paid');
    }

    public function createPayment(array $attributes): CourierPayment
    {
        return $this->db->payment($this->db->bookings[$attributes['courier_booking_id']], $attributes);
    }

    public function updatePayment(CourierPayment $payment, array $attributes): CourierPayment
    {
        return $payment->forceFill($attributes);
    }
}

class FakeCourierShipmentRepository implements CourierShipmentRepositoryInterface
{
    public function __construct(private readonly FakeCourierDb $db) {}

    public function transaction(Closure $callback): mixed
    {
        return $callback();
    }

    public function shipmentOf(int $bookingId): ?CourierShipment
    {
        return collect($this->db->shipments)->first(fn ($s) => $s->courier_booking_id === $bookingId);
    }

    public function createShipment(array $attributes): CourierShipment
    {
        $id = ++$this->db->seq + 800;

        return $this->db->shipments[$id] = (new CourierShipment)->forceFill($attributes + ['id' => $id]);
    }

    public function updateShipment(CourierShipment $shipment, array $attributes): CourierShipment
    {
        return $shipment->forceFill($attributes);
    }

    public function freshShipment(CourierShipment $shipment): CourierShipment
    {
        return $shipment;
    }

    public function lockBookingAndShipment(int $bookingId): array
    {
        return [$this->db->bookings[$bookingId] ?? null, $this->shipmentOf($bookingId)];
    }

    public function lockShipment(int $shipmentId): ?CourierShipment
    {
        return $this->db->shipments[$shipmentId] ?? null;
    }

    public function updateBooking(CourierBooking $booking, array $attributes): CourierBooking
    {
        return $booking->forceFill($attributes);
    }

    public function syncBookingShipmentStatus(int $bookingId, string $status): void
    {
        $this->db->bookings[$bookingId]->shipment_status = $status;
    }

    public function eventExists(int $shipmentId, string $dedupeKey): bool
    {
        return collect($this->db->events)->contains(fn ($e) => $e->courier_shipment_id === $shipmentId && $e->dedupe_key === $dedupeKey);
    }

    public function latestEventTime(int $shipmentId): ?Carbon
    {
        $t = collect($this->db->events)->where('courier_shipment_id', $shipmentId)->max('event_time');

        return $t ? Carbon::parse($t) : null;
    }

    public function createEvent(array $attributes): bool
    {
        $this->db->events[] = (new CourierTrackingEvent)->forceFill($attributes + ['id' => count($this->db->events) + 1]);

        return true;
    }

    public function setTrackingUrl(CourierShipment $shipment, string $url): void
    {
        $shipment->tracking_url = $url;
        $this->db->bookings[$shipment->courier_booking_id]->tracking_url ??= $url;
    }

    public function firstRefund(int $bookingId): ?CourierRefund
    {
        return collect($this->db->refunds)->first(fn ($r) => $r->courier_booking_id === $bookingId);
    }

    public function ensureRefund(string $reference, array $attributes): CourierRefund
    {
        $existing = collect($this->db->refunds)->first(fn ($r) => $r->reference === $reference);
        if ($existing) {
            return $existing;
        }
        $id = ++$this->db->seq + 900;

        return $this->db->refunds[$id] = (new CourierRefund)->forceFill($attributes + ['id' => $id, 'reference' => $reference])
            ->setRelation('payment', $this->db->payments[$attributes['courier_payment_id']] ?? null);
    }

    public function findRefund(int $id): ?CourierRefund
    {
        return $this->db->refunds[$id] ?? null;
    }

    public function claimRefund(int $id): bool
    {
        $r = $this->db->refunds[$id];
        if (! in_array($r->status, ['pending', 'failed'], true)) {
            return false;
        }
        $r->forceFill(['status' => 'processing', 'failure_reason' => null]);

        return true;
    }

    public function setGatewayRefundId(int $id, string $gatewayRefundId): void
    {
        $this->db->refunds[$id]->gateway_refund_id ??= $gatewayRefundId;
    }

    public function markRefundSuccess(CourierRefund $refund): void
    {
        $refund->forceFill(['status' => 'success', 'processed_at' => now()]);
        $p = $this->db->payments[$refund->courier_payment_id] ?? null;
        if ($p && $p->status === 'paid') {
            $p->status = 'refunded';
        }
    }

    public function markRefundFailed(int $id, string $reason): void
    {
        $r = $this->db->refunds[$id];
        if ($r->status !== 'success') {
            $r->forceFill(['status' => 'failed', 'failure_reason' => $reason]);
        }
    }

    public function failUnsentRefund(int $id): void
    {
        $r = $this->db->refunds[$id];
        if ($r->status === 'processing' && ! $r->gateway_refund_id) {
            $r->status = 'failed';
        }
    }

    public function paidPayment(int $bookingId): ?CourierPayment
    {
        return collect($this->db->payments)->first(fn ($p) => $p->courier_booking_id === $bookingId && $p->status === 'paid');
    }
}

/** A scripted Shiprocket: set what each call answers (or that it fails) and read what was called. */
class FakeShiprocketApi implements ShiprocketApi
{
    public array $couriers = [
        ['courier_company_id' => '12', 'courier_name' => 'Delhivery', 'rate' => 80.0, 'estimated_delivery_days' => 3, 'estimated_delivery_date' => null, 'chargeable_weight' => 0.5, 'service_type' => 'surface'],
        ['courier_company_id' => '7', 'courier_name' => 'Blue Dart', 'rate' => 120.0, 'estimated_delivery_days' => 1, 'estimated_delivery_date' => null, 'chargeable_weight' => 0.5, 'service_type' => 'air'],
    ];

    public ?ShiprocketException $quoteFails = null;

    public ?ShiprocketException $orderFails = null;

    public ?ShiprocketException $awbFails = null;

    public ?ShiprocketException $pickupFails = null;

    public ?ShiprocketException $cancelFails = null;

    public ?ShiprocketException $trackFails = null;

    public ?array $existingOrder = null;

    public array $calls = [];

    public array $events = [];

    public function quote(string $pickup, string $delivery, float $weightGrams, float $length, float $width, float $height, string $unit): array
    {
        $this->calls[] = 'quote';
        if ($this->quoteFails) {
            throw $this->quoteFails;
        }

        return ['weight_kg' => round($weightGrams / 1000, 3), 'couriers' => $this->couriers];
    }

    public function createOrder(CourierBooking $booking, ?User $user): array
    {
        $this->calls[] = 'order';
        if ($this->orderFails) {
            throw $this->orderFails;
        }

        return ['order_id' => '9001', 'shipment_id' => '7001', 'awb_code' => null, 'courier_name' => 'Delhivery'];
    }

    public function findOrderByChannelId(string $channelOrderId): ?array
    {
        $this->calls[] = 'find';

        return $this->existingOrder;
    }

    public function assignAwb(string $shipmentId, string $courierCompanyId): array
    {
        $this->calls[] = 'awb';
        if ($this->awbFails) {
            throw $this->awbFails;
        }

        return ['awb_code' => 'AWB123', 'courier_name' => 'Delhivery'];
    }

    public function requestPickup(string $shipmentId): void
    {
        $this->calls[] = 'pickup';
        if ($this->pickupFails) {
            throw $this->pickupFails;
        }
    }

    public function cancelOrder(string $shiprocketOrderId): void
    {
        $this->calls[] = 'cancel';
        if ($this->cancelFails) {
            throw $this->cancelFails;
        }
    }

    public function track(string $awb): array
    {
        $this->calls[] = 'track';
        if ($this->trackFails) {
            throw $this->trackFails;
        }

        return ['events' => $this->events, 'tracking_url' => 'https://track.test/'.$awb];
    }
}

class FakeCourierPhotoStore implements CourierPhotoStore
{
    public array $stored = [];

    public function put(UploadedFile $file): string
    {
        return $this->stored[] = 'courier-bookings/items/010125/item_'.count($this->stored).'.jpg';
    }
}
