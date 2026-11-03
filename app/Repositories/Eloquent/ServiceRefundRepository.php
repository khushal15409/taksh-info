<?php

namespace App\Repositories\Eloquent;

use App\Models\ServiceMarketplaceBooking as Booking;
use App\Models\ServiceMarketplacePayment as Payment;
use App\Models\ServiceMarketplaceRefund as Refund;
use App\Repositories\Contracts\ServiceRefundRepositoryInterface;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceRefundRepository implements ServiceRefundRepositoryInterface
{
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function create(Booking $booking, Payment $payment, array $calc, ?string $reason, string $byType, ?int $byId, string $status): Refund
    {
        return Refund::create([
            'service_marketplace_booking_id' => $booking->id,
            'service_marketplace_payment_id' => $payment->id,
            'reference' => 'SMR'.strtoupper(Str::random(20)),
            'gateway' => $payment->gateway,
            'gateway_payment_id' => (string) $payment->gateway_payment_id,
            'paid_amount' => $calc['original_amount'],
            'requested_amount' => $calc['refund_amount'],
            'refunded_amount' => 0,
            'non_refundable_amount' => $calc['non_refundable_amount'],
            'refund_type' => $calc['refund_type'],
            'refund_value' => $calc['refund_value'],
            'status' => $status,
            'reason' => $reason,
            'requested_by_type' => $byType,
            'requested_by_id' => $byId,
        ]);
    }

    public function existsFor(Booking $booking): bool
    {
        return $booking->refund()->exists();
    }

    public function updateRefund(Refund $refund, array $attributes): Refund
    {
        $refund->update($attributes);

        return $refund;
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

    public function forBooking(Booking $booking): ?Refund
    {
        return Refund::where('service_marketplace_booking_id', $booking->id)->first();
    }

    public function find(int $id): ?Refund
    {
        return Refund::with('booking')->find($id);
    }

    public function claim(int $id): bool
    {
        return (bool) Refund::whereKey($id)->whereIn('status', [Refund::PENDING, Refund::FAILED])->update(['status' => Refund::PROCESSING, 'failure_reason' => null]);
    }

    public function setGatewayRefundId(int $id, string $gatewayRefundId): void
    {
        Refund::whereKey($id)->update(['gateway_refund_id' => $gatewayRefundId]);
    }

    public function lock(int $id): ?Refund
    {
        return Refund::whereKey($id)->lockForUpdate()->first();
    }

    public function lockPayment(int $id): ?Payment
    {
        return Payment::whereKey($id)->lockForUpdate()->first();
    }

    public function lockBooking(int $id): ?Booking
    {
        return Booking::whereKey($id)->lockForUpdate()->first();
    }
}
