<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function hasCheckoutGroupColumn(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('orders', 'checkout_group_id');
    }

    public function ownedOrder(int $userId, int $orderId): ?Order
    {
        return Order::where('id', $orderId)->where('user_id', $userId)->first();
    }

    public function onlinePayment(int $orderId): ?Payment
    {
        return Payment::where('order_id', $orderId)->where('gateway', 'razorpay')->orderBy('id')->first();
    }

    public function isPaid(int $orderId): bool
    {
        return Payment::where('order_id', $orderId)->whereIn('status', ['paid', 'success'])->exists();
    }

    public function openRazorpayPayment(int $orderId): ?Payment
    {
        return Payment::where('order_id', $orderId)->where('status', 'initiated')->whereNotNull('razorpay_order_id')->orderByDesc('id')->first();
    }

    public function groupOrders(string $groupId, int $userId): Collection
    {
        return Order::where('checkout_group_id', $groupId)->where('user_id', $userId)->orderBy('id')->get();
    }

    public function openPaymentsByRazorpayOrder(array $orderIds): Collection
    {
        return Payment::whereIn('order_id', $orderIds)->where('status', 'initiated')->whereNotNull('razorpay_order_id')->get()->groupBy('razorpay_order_id');
    }

    public function createPayment(array $attributes): Payment
    {
        return Payment::create($attributes);
    }

    public function lockOrders(array $orderIds): void
    {
        Order::whereIn('id', $orderIds)->orderBy('id')->lockForUpdate()->get();
    }

    public function findByRazorpayOrder(string $razorpayOrderId): ?Payment
    {
        return Payment::where('razorpay_order_id', $razorpayOrderId)->with('order')->first();
    }

    public function groupPayments(Payment $payment): Collection
    {
        if (! $payment->razorpay_order_id) {
            return collect();
        }

        $rows = Payment::where('razorpay_order_id', $payment->razorpay_order_id)->where('gateway', 'razorpay')->orderBy('id')->get();

        if ($rows->pluck('order_id')->unique()->count() < 2) {
            return collect();
        }

        return $rows->groupBy('order_id')->map(fn (Collection $perOrder) => $perOrder->last())->values();
    }

    public function anyOrderOfOtherUser(array $orderIds, int $userId): bool
    {
        return Order::whereIn('id', $orderIds)->where('user_id', '!=', $userId)->exists();
    }

    public function lockedOrderAndPayment(int $paymentId): array
    {
        $orderId = Payment::whereKey($paymentId)->value('order_id');
        $order = $orderId ? Order::whereKey($orderId)->lockForUpdate()->first() : null;
        $payment = Payment::whereKey($paymentId)->lockForUpdate()->firstOrFail();

        return [$order, $payment];
    }

    public function lockedPayment(int $paymentId): ?Payment
    {
        return Payment::whereKey($paymentId)->lockForUpdate()->first();
    }

    public function updatePayment(Payment $payment, array $attributes): void
    {
        $payment->update($attributes);
    }

    public function updateOrder(Order $order, array $attributes): void
    {
        $order->update($attributes);
    }

    public function orderWithPayments(int $userId, int $orderId): ?Order
    {
        return Order::where('id', $orderId)->where('user_id', $userId)->with(['payments', 'latestPayment'])->first();
    }
}
