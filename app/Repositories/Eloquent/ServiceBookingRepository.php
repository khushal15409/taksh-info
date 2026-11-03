<?php

namespace App\Repositories\Eloquent;

use App\Models\ServiceAddress;
use App\Models\ServiceMarketplaceBooking as Booking;
use App\Models\ServiceMarketplaceBookingStatusHistory as History;
use App\Models\ServiceMarketplacePayment as Payment;
use App\Models\ServiceProvider;
use App\Models\ServiceService;
use App\Repositories\Contracts\ServiceBookingRepositoryInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ServiceBookingRepository implements ServiceBookingRepositoryInterface
{
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function activeServices(): Collection
    {
        return ServiceService::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    public function findService(int $id): ?ServiceService
    {
        return ServiceService::find($id);
    }

    public function serviceIsAssignable(int $serviceId): bool
    {
        return ServiceService::where('is_active', true)->where('service_type', ServiceService::TYPE_PROVIDER)->whereKey($serviceId)->exists();
    }

    public function eligibleProviderCount(int $serviceId, string $pincode): int
    {
        return ServiceProvider::query()
            ->where('is_active', true)
            ->where('verification_status', 'verified')
            ->whereHas('services', fn ($q) => $q->where('service_services.id', $serviceId)->where('service_provider_services.is_active', true))
            ->where(function ($q) use ($serviceId, $pincode) {
                // Explicit service areas win; a provider with none for this service serves its own profile pincode.
                $q->whereHas('serviceAreas', fn ($a) => $a->where('service_service_id', $serviceId)
                    ->whereHas('pincode', fn ($p) => $p->where('pincode', $pincode)->where('status', 1)))
                    ->orWhere(fn ($f) => $f->where('service_providers.pincode', $pincode)
                        ->whereDoesntHave('serviceAreas', fn ($a) => $a->where('service_service_id', $serviceId)));
            })->count();
    }

    public function ownedAddress(int $userId, int $addressId): ?ServiceAddress
    {
        return ServiceAddress::where('id', $addressId)->where('user_id', $userId)->first();
    }

    public function addressFor(int $userId, array $details, string $pincode): ServiceAddress
    {
        return ServiceAddress::firstOrCreate(
            ['user_id' => $userId, 'address_line_1' => $details['address_line_1'], 'pincode' => $pincode],
            $details + ['type' => 'home', 'user_id' => $userId],
        );
    }

    public function findByIdempotencyKey(int $userId, string $key): ?Booking
    {
        return Booking::where('user_id', $userId)->where('idempotency_key', $key)->first();
    }

    public function findRecentUnpaid(int $userId, int $serviceId, ?int $addressId, array $details, \DateTimeInterface $since): ?Booking
    {
        $query = Booking::where('user_id', $userId)->where('service_service_id', $serviceId)
            ->where('status', Booking::STATUS_PENDING)->where('payment_status', '!=', 'paid')->where('created_at', '>=', $since);

        if ($addressId !== null) {
            $query->where('service_address_id', $addressId);
        } else {
            $query->where('pincode', $details['pincode'] ?? '')->where('address_snapshot->address_line_1', $details['address_line_1'] ?? '');
        }

        return $query->latest('id')->first();
    }

    public function createBooking(array $attributes): Booking
    {
        return Booking::create($attributes);
    }

    public function updateBooking(Booking $booking, array $attributes): Booking
    {
        $booking->update($attributes);

        return $booking;
    }

    public function updatePayment(Payment $payment, array $attributes): Payment
    {
        $payment->update($attributes);

        return $payment;
    }

    public function fresh(Booking $booking): Booking
    {
        return $booking->refresh();
    }

    public function numberExists(string $number): bool
    {
        return Booking::where('booking_number', $number)->exists();
    }

    public function recordHistory(Booking $booking, ?string $from, string $to, string $byType, ?int $byId, ?string $reason): void
    {
        History::create(['service_marketplace_booking_id' => $booking->id, 'from_status' => $from, 'to_status' => $to, 'changed_by_type' => $byType, 'changed_by_id' => $byId, 'reason' => $reason]);
    }

    public function createPayment(array $attributes): Payment
    {
        return Payment::create($attributes);
    }

    public function initiatedPayment(Booking $booking): ?Payment
    {
        return $booking->payments()->where('status', 'initiated')->latest('id')->first();
    }

    public function paymentByOrder(string $gatewayOrderId): ?Payment
    {
        return Payment::with('booking')->where('gateway_order_id', $gatewayOrderId)->first();
    }

    public function lockPayment(int $id): ?Payment
    {
        return Payment::whereKey($id)->lockForUpdate()->first();
    }

    public function lockBooking(int $id): ?Booking
    {
        return Booking::whereKey($id)->lockForUpdate()->first();
    }

    public function lockPaidPayment(Booking $booking): ?Payment
    {
        return $booking->payments()->where('status', 'paid')->latest('id')->lockForUpdate()->first();
    }

    public function paginateForUser(int $userId, ?string $category, int $perPage): LengthAwarePaginator
    {
        return Booking::with(['service:id,name,image', 'provider.user:id,name', 'latestPayment', 'refund'])
            ->where('user_id', $userId)
            ->when($category, fn ($q) => $q->whereIn('status', Booking::CATEGORIES[$category]))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage);
    }

    public function detailForUser(int $userId, int $id): ?Booking
    {
        return Booking::with(['service:id,name,short_description,image', 'latestPayment', 'provider.user:id,name,mobile', 'refund', 'histories', 'assignments'])
            ->where('id', $id)->where('user_id', $userId)->first();
    }

    public function resolveId(int $userId, string $ref): int
    {
        if (ctype_digit($ref)) {
            return (int) $ref;
        }

        return (int) Booking::where('booking_number', $ref)->where('user_id', $userId)->value('id');
    }

    public function findForUser(int $userId, string $ref): ?Booking
    {
        return Booking::with(['service:id,name', 'latestPayment'])->where('user_id', $userId)
            ->where(fn ($q) => ctype_digit($ref) ? $q->where('id', (int) $ref) : $q->where('booking_number', $ref))->first();
    }
}
