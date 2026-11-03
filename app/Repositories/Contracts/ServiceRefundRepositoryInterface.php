<?php

namespace App\Repositories\Contracts;

use App\Models\ServiceMarketplaceBooking as Booking;
use App\Models\ServiceMarketplacePayment as Payment;
use App\Models\ServiceMarketplaceRefund as Refund;
use Closure;

interface ServiceRefundRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    public function create(Booking $booking, Payment $payment, array $calc, ?string $reason, string $byType, ?int $byId, string $status): Refund;

    public function existsFor(Booking $booking): bool;

    public function updateRefund(Refund $refund, array $attributes): Refund;

    public function updateBooking(Booking $booking, array $attributes): Booking;

    public function updatePayment(Payment $payment, array $attributes): Payment;

    /** The refund of this booking, if any. */
    public function forBooking(Booking $booking): ?Refund;

    public function find(int $id): ?Refund;

    /** Atomically move pending/failed -> processing; false when someone else already has it. */
    public function claim(int $id): bool;

    public function setGatewayRefundId(int $id, string $gatewayRefundId): void;

    public function lock(int $id): ?Refund;

    public function lockPayment(int $id): ?Payment;

    public function lockBooking(int $id): ?Booking;
}
