<?php

namespace App\Repositories\Eloquent;

use App\Models\CourierBooking;
use App\Models\CourierDeliveryPartner;
use App\Models\CourierPayment;
use App\Models\ServiceService;
use App\Repositories\Contracts\CourierBookingRepositoryInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CourierBookingRepository implements CourierBookingRepositoryInterface
{
    public const COURIER_SERVICE_SLUG = 'courier-booking';

    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function activePartners(): Collection
    {
        return CourierDeliveryPartner::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    public function activePartner(int $id): ?CourierDeliveryPartner
    {
        return CourierDeliveryPartner::where('id', $id)->where('is_active', true)->first();
    }

    public function courierServiceCharge(): mixed
    {
        return ServiceService::where('slug', self::COURIER_SERVICE_SLUG)->value('service_charge');
    }

    public function createBooking(array $attributes): CourierBooking
    {
        return CourierBooking::create($attributes);
    }

    public function ownedBooking(int $userId, int $id): ?CourierBooking
    {
        return CourierBooking::whereKey($id)->where('user_id', $userId)->first();
    }

    public function lockOwnedBooking(int $userId, int $id): ?CourierBooking
    {
        return CourierBooking::whereKey($id)->where('user_id', $userId)->lockForUpdate()->first();
    }

    public function updateBooking(CourierBooking $booking, array $attributes): CourierBooking
    {
        $booking->update($attributes);

        return $booking;
    }

    public function fresh(CourierBooking $booking): CourierBooking
    {
        return $booking->refresh();
    }

    public function paginateForUser(int $userId, int $perPage): LengthAwarePaginator
    {
        return CourierBooking::where('user_id', $userId)->with('deliveryPartner:id,name,slug')->orderByDesc('created_at')->paginate($perPage);
    }

    public function detailForUser(int $userId, int $id): ?CourierBooking
    {
        return CourierBooking::where('user_id', $userId)->where('id', $id)->with([
            'deliveryPartner:id,name,slug,base_charge,charge_per_kg',
            'shipment',
            // oldest -> newest: the order a timeline is drawn in
            'shipment.trackingEvents' => fn ($q) => $q->orderBy('event_time')->orderBy('id'),
            'payments' => fn ($q) => $q->whereIn('status', ['paid', 'refunded'])->orderByDesc('id'),
            'refunds' => fn ($q) => $q->orderByDesc('id'),
        ])->first();
    }

    public function lockPaymentByOrder(int $bookingId, string $orderId): ?CourierPayment
    {
        return CourierPayment::where('courier_booking_id', $bookingId)->where('gateway_order_id', $orderId)->lockForUpdate()->first();
    }

    public function openPayment(int $bookingId, string $orderId, int $amountPaise): ?CourierPayment
    {
        return CourierPayment::where('courier_booking_id', $bookingId)->where('gateway_order_id', $orderId)->where('status', 'initiated')->where('amount', $amountPaise)->first();
    }

    public function paidPayment(int $bookingId): ?CourierPayment
    {
        return CourierPayment::where('courier_booking_id', $bookingId)->where('status', 'paid')->first();
    }

    public function createPayment(array $attributes): CourierPayment
    {
        return CourierPayment::create($attributes);
    }

    public function updatePayment(CourierPayment $payment, array $attributes): CourierPayment
    {
        $payment->update($attributes);

        return $payment;
    }
}
