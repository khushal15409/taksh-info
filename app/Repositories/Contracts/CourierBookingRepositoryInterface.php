<?php

namespace App\Repositories\Contracts;

use App\Models\CourierBooking;
use App\Models\CourierDeliveryPartner;
use App\Models\CourierPayment;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CourierBookingRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    /** @return Collection<int, CourierDeliveryPartner> active local partners, in the admin's order */
    public function activePartners(): Collection;

    public function activePartner(int $id): ?CourierDeliveryPartner;

    /** The admin's service_charge on the "Courier Booking" service (null when not set). */
    public function courierServiceCharge(): mixed;

    public function createBooking(array $attributes): CourierBooking;

    public function ownedBooking(int $userId, int $id): ?CourierBooking;

    /** Row-locked (call inside transaction()). */
    public function lockOwnedBooking(int $userId, int $id): ?CourierBooking;

    public function updateBooking(CourierBooking $booking, array $attributes): CourierBooking;

    public function fresh(CourierBooking $booking): CourierBooking;

    public function paginateForUser(int $userId, int $perPage): LengthAwarePaginator;

    /** The customer's booking with partner, shipment + ordered tracking events, paid/refunded payments and refunds. */
    public function detailForUser(int $userId, int $id): ?CourierBooking;

    // payments (service_courier_payments; `amount` in paise)
    public function lockPaymentByOrder(int $bookingId, string $orderId): ?CourierPayment;

    public function openPayment(int $bookingId, string $orderId, int $amountPaise): ?CourierPayment;

    public function paidPayment(int $bookingId): ?CourierPayment;

    public function createPayment(array $attributes): CourierPayment;

    public function updatePayment(CourierPayment $payment, array $attributes): CourierPayment;
}
