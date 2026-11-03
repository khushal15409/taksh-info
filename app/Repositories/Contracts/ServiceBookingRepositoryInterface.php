<?php

namespace App\Repositories\Contracts;

use App\Models\ServiceAddress;
use App\Models\ServiceMarketplaceBooking as Booking;
use App\Models\ServiceMarketplacePayment as Payment;
use App\Models\ServiceService;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ServiceBookingRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    /** @return Collection<int, ServiceService> active services, in the admin's order */
    public function activeServices(): Collection;

    public function findService(int $id): ?ServiceService;

    /** Is the service active and a provider-type service? */
    public function serviceIsAssignable(int $serviceId): bool;

    /** Verified, active providers who serve this service in this pincode. */
    public function eligibleProviderCount(int $serviceId, string $pincode): int;

    public function ownedAddress(int $userId, int $addressId): ?ServiceAddress;

    /** Reuse the customer's saved address with the same line and pincode, or save it. */
    public function addressFor(int $userId, array $details, string $pincode): ServiceAddress;

    public function findByIdempotencyKey(int $userId, string $key): ?Booking;

    /** An unpaid, pending booking of the same request made since $since. */
    public function findRecentUnpaid(int $userId, int $serviceId, ?int $addressId, array $details, \DateTimeInterface $since): ?Booking;

    public function createBooking(array $attributes): Booking;

    public function updateBooking(Booking $booking, array $attributes): Booking;

    public function updatePayment(Payment $payment, array $attributes): Payment;

    /** The booking as stored right now. */
    public function fresh(Booking $booking): Booking;

    public function numberExists(string $number): bool;

    public function recordHistory(Booking $booking, ?string $from, string $to, string $byType, ?int $byId, ?string $reason): void;

    public function createPayment(array $attributes): Payment;

    public function initiatedPayment(Booking $booking): ?Payment;

    public function paymentByOrder(string $gatewayOrderId): ?Payment;

    /** Row-locked reads (call inside transaction()). */
    public function lockPayment(int $id): ?Payment;

    public function lockBooking(int $id): ?Booking;

    public function lockPaidPayment(Booking $booking): ?Payment;

    public function paginateForUser(int $userId, ?string $category, int $perPage): LengthAwarePaginator;

    /** The customer's own booking with everything the detail page needs. */
    public function detailForUser(int $userId, int $id): ?Booking;

    /** Booking id or the customer's own booking number -> id (0 when unknown). */
    public function resolveId(int $userId, string $ref): int;

    /** For the invoice: the customer's booking by id or number. */
    public function findForUser(int $userId, string $ref): ?Booking;
}
