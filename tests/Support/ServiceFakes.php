<?php

namespace Tests\Support;

use App\Models\ServiceAddress;
use App\Models\ServiceMarketplaceBooking as Booking;
use App\Models\ServiceMarketplaceBookingStatusHistory as History;
use App\Models\ServiceMarketplacePayment as Payment;
use App\Models\ServiceMarketplaceRefund as Refund;
use App\Models\ServiceService;
use App\Repositories\Contracts\ServiceBookingRepositoryInterface;
use App\Repositories\Contracts\ServiceRefundRepositoryInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

/** The in-memory "tables" behind the service booking and refund fakes. */
class FakeServiceDb
{
    /** @var array<int, ServiceService> */
    public array $services = [];

    /** @var array<int, ServiceAddress> */
    public array $addresses = [];

    /** @var array<int, Booking> */
    public array $bookings = [];

    /** @var array<int, Payment> */
    public array $payments = [];

    /** @var array<int, Refund> */
    public array $refunds = [];

    /** @var list<History> */
    public array $histories = [];

    public int $providers = 1;

    public bool $assignable = true;

    public int $seq = 100;

    public function service(int $id, array $attrs = []): ServiceService
    {
        return $this->services[$id] = (new ServiceService)->forceFill($attrs + [
            'id' => $id, 'name' => "Service $id", 'slug' => "service-$id", 'service_charge' => 500, 'base_price' => 500, 'is_active' => true,
            'refund_type' => 'percentage', 'refund_value' => 50, 'provider_share_percentage' => 70, 'platform_share_percentage' => 30,
        ]);
    }

    public function booking(array $attrs = []): Booking
    {
        $id = $attrs['id'] ?? ++$this->seq;
        $b = (new Booking)->forceFill($attrs + [
            'id' => $id, 'booking_number' => 'SMB'.$id, 'user_id' => 5, 'service_service_id' => 1, 'pincode' => '380001', 'service_charge' => 500,
            'currency' => 'INR', 'status' => 'pending', 'payment_status' => 'pending', 'refund_type' => 'percentage', 'refund_value' => 50,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $b->setRelation('service', $this->services[$b->service_service_id] ?? $this->service($b->service_service_id));
        $b->setRelation('provider', null);

        return $this->bookings[$id] = $b;
    }

    public function payment(Booking $b, array $attrs = []): Payment
    {
        $id = $attrs['id'] ?? ++$this->seq;

        return $this->payments[$id] = (new Payment)->forceFill($attrs + [
            'id' => $id, 'service_marketplace_booking_id' => $b->id, 'gateway' => 'razorpay', 'gateway_order_id' => 'order_x'.$id,
            'amount' => (int) round($b->service_charge * 100), 'currency' => 'INR', 'status' => 'initiated',
        ]);
    }
}

class FakeServiceBookingRepository implements ServiceBookingRepositoryInterface
{
    public function __construct(private readonly FakeServiceDb $db) {}

    public function transaction(Closure $callback): mixed
    {
        return $callback();
    }

    public function activeServices(): Collection
    {
        return collect($this->db->services)->filter(fn ($s) => $s->is_active)->values();
    }

    public function findService(int $id): ?ServiceService
    {
        return $this->db->services[$id] ?? null;
    }

    public function serviceIsAssignable(int $serviceId): bool
    {
        return $this->db->assignable;
    }

    public function eligibleProviderCount(int $serviceId, string $pincode): int
    {
        return $this->db->providers;
    }

    public function ownedAddress(int $userId, int $addressId): ?ServiceAddress
    {
        $a = $this->db->addresses[$addressId] ?? null;

        return $a && $a->user_id === $userId ? $a : null;
    }

    public function addressFor(int $userId, array $details, string $pincode): ServiceAddress
    {
        $id = ++$this->db->seq;

        return $this->db->addresses[$id] = (new ServiceAddress)->forceFill($details + ['id' => $id, 'user_id' => $userId, 'pincode' => $pincode, 'type' => 'home']);
    }

    public function findByIdempotencyKey(int $userId, string $key): ?Booking
    {
        return collect($this->db->bookings)->first(fn ($b) => $b->user_id === $userId && $b->idempotency_key === $key);
    }

    public function findRecentUnpaid(int $userId, int $serviceId, ?int $addressId, array $details, \DateTimeInterface $since): ?Booking
    {
        return collect($this->db->bookings)->first(fn ($b) => $b->user_id === $userId && $b->service_service_id === $serviceId && $b->status === 'pending'
            && $b->payment_status !== 'paid' && ($addressId !== null ? $b->service_address_id === $addressId : $b->pincode === ($details['pincode'] ?? '')));
    }

    public function createBooking(array $attributes): Booking
    {
        return $this->db->booking($attributes + ['created_at' => now(), 'updated_at' => now()]);
    }

    public function updateBooking(Booking $booking, array $attributes): Booking
    {
        return $booking->forceFill($attributes);
    }

    public function updatePayment(Payment $payment, array $attributes): Payment
    {
        return $payment->forceFill($attributes);
    }

    public function fresh(Booking $booking): Booking
    {
        return $booking;
    }

    public function numberExists(string $number): bool
    {
        return false;
    }

    public function recordHistory(Booking $booking, ?string $from, string $to, string $byType, ?int $byId, ?string $reason): void
    {
        $this->db->histories[] = (new History)->forceFill([
            'id' => count($this->db->histories) + 1, 'service_marketplace_booking_id' => $booking->id, 'from_status' => $from, 'to_status' => $to,
            'changed_by_type' => $byType, 'changed_by_id' => $byId, 'reason' => $reason, 'created_at' => now(),
        ]);
    }

    public function createPayment(array $attributes): Payment
    {
        $b = $this->db->bookings[$attributes['service_marketplace_booking_id']];

        return $this->db->payment($b, $attributes);
    }

    public function initiatedPayment(Booking $booking): ?Payment
    {
        return collect($this->db->payments)->first(fn ($p) => $p->service_marketplace_booking_id === $booking->id && $p->status === 'initiated');
    }

    public function paymentByOrder(string $gatewayOrderId): ?Payment
    {
        $p = collect($this->db->payments)->first(fn ($p) => $p->gateway_order_id === $gatewayOrderId);

        return $p?->setRelation('booking', $this->db->bookings[$p->service_marketplace_booking_id]);
    }

    public function lockPayment(int $id): ?Payment
    {
        return $this->db->payments[$id] ?? null;
    }

    public function lockBooking(int $id): ?Booking
    {
        return $this->db->bookings[$id] ?? null;
    }

    public function lockPaidPayment(Booking $booking): ?Payment
    {
        return collect($this->db->payments)->first(fn ($p) => $p->service_marketplace_booking_id === $booking->id && $p->status === 'paid');
    }

    public function paginateForUser(int $userId, ?string $category, int $perPage): LengthAwarePaginator
    {
        $rows = collect($this->db->bookings)->filter(fn ($b) => $b->user_id === $userId && (! $category || in_array($b->status, Booking::CATEGORIES[$category])))
            ->sortByDesc('id')->values()->each(fn ($b) => $b->setRelation('latestPayment', null)->setRelation('refund', null));

        return new Paginator($rows, $rows->count(), $perPage, 1);
    }

    public function detailForUser(int $userId, int $id): ?Booking
    {
        $b = $this->db->bookings[$id] ?? null;
        if (! $b || $b->user_id !== $userId) {
            return null;
        }

        return $b->setRelation('histories', collect($this->db->histories)->where('service_marketplace_booking_id', $id)->values())
            ->setRelation('latestPayment', collect($this->db->payments)->where('service_marketplace_booking_id', $id)->last())
            ->setRelation('refund', collect($this->db->refunds)->where('service_marketplace_booking_id', $id)->first())
            ->setRelation('assignments', collect());
    }

    public function resolveId(int $userId, string $ref): int
    {
        if (ctype_digit($ref)) {
            return (int) $ref;
        }

        return (int) (collect($this->db->bookings)->first(fn ($b) => $b->booking_number === $ref && $b->user_id === $userId)?->id ?? 0);
    }

    public function findForUser(int $userId, string $ref): ?Booking
    {
        $b = $this->db->bookings[$this->resolveId($userId, $ref)] ?? null;

        return $b && $b->user_id === $userId ? $b->setRelation('latestPayment', collect($this->db->payments)->where('service_marketplace_booking_id', $b->id)->last()) : null;
    }
}

class FakeServiceRefundRepository implements ServiceRefundRepositoryInterface
{
    public function __construct(private readonly FakeServiceDb $db) {}

    public function transaction(Closure $callback): mixed
    {
        return $callback();
    }

    public function create(Booking $booking, Payment $payment, array $calc, ?string $reason, string $byType, ?int $byId, string $status): Refund
    {
        $id = ++$this->db->seq;

        return $this->db->refunds[$id] = (new Refund)->forceFill([
            'id' => $id, 'service_marketplace_booking_id' => $booking->id, 'service_marketplace_payment_id' => $payment->id, 'reference' => 'SMRTEST'.$id,
            'gateway' => 'razorpay', 'gateway_payment_id' => (string) $payment->gateway_payment_id, 'paid_amount' => $calc['original_amount'],
            'requested_amount' => $calc['refund_amount'], 'refunded_amount' => 0, 'non_refundable_amount' => $calc['non_refundable_amount'],
            'status' => $status, 'reason' => $reason, 'requested_by_type' => $byType, 'requested_by_id' => $byId,
        ]);
    }

    public function existsFor(Booking $booking): bool
    {
        return $this->forBooking($booking) !== null;
    }

    public function forBooking(Booking $booking): ?Refund
    {
        return collect($this->db->refunds)->first(fn ($r) => $r->service_marketplace_booking_id === $booking->id);
    }

    public function updateRefund(Refund $refund, array $attributes): Refund
    {
        return $refund->forceFill($attributes);
    }

    public function updateBooking(Booking $booking, array $attributes): Booking
    {
        return $booking->forceFill($attributes);
    }

    public function updatePayment(Payment $payment, array $attributes): Payment
    {
        return $payment->forceFill($attributes);
    }

    public function find(int $id): ?Refund
    {
        $r = $this->db->refunds[$id] ?? null;

        return $r?->setRelation('booking', $this->db->bookings[$r->service_marketplace_booking_id]);
    }

    public function claim(int $id): bool
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
        $this->db->refunds[$id]->gateway_refund_id = $gatewayRefundId;
    }

    public function lock(int $id): ?Refund
    {
        return $this->db->refunds[$id] ?? null;
    }

    public function lockPayment(int $id): ?Payment
    {
        return $this->db->payments[$id] ?? null;
    }

    public function lockBooking(int $id): ?Booking
    {
        return $this->db->bookings[$id] ?? null;
    }
}
