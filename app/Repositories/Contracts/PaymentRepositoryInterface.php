<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use App\Models\Payment;
use Closure;
use Illuminate\Support\Collection;

/** Payment rows and the order rows they settle. */
interface PaymentRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    public function hasCheckoutGroupColumn(): bool;

    /** An order, only when it belongs to the customer. */
    public function ownedOrder(int $userId, int $orderId): ?Order;

    /** The order's online (Razorpay) payment record, created when it was placed - the oldest one. */
    public function onlinePayment(int $orderId): ?Payment;

    public function isPaid(int $orderId): bool;

    /** The newest Razorpay order of this order that is still waiting for payment. */
    public function openRazorpayPayment(int $orderId): ?Payment;

    /** Orders placed together (one per vendor) by this customer, oldest first. */
    public function groupOrders(string $groupId, int $userId): Collection;

    /** Payments still waiting on a Razorpay order, for these orders, grouped by Razorpay order id. */
    public function openPaymentsByRazorpayOrder(array $orderIds): Collection;

    public function createPayment(array $attributes): Payment;

    /** Lock these orders in id order (a concurrent attempt waits and then reuses what the first stored). */
    public function lockOrders(array $orderIds): void;

    /** The payment (with its order) behind a Razorpay order id. */
    public function findByRazorpayOrder(string $razorpayOrderId): ?Payment;

    /** One payment per order that the same Razorpay order covers; empty when it covers a single order. @return Collection<int, Payment> */
    public function groupPayments(Payment $payment): Collection;

    public function anyOrderOfOtherUser(array $orderIds, int $userId): bool;

    /** Lock the order, then the payment (the same order a cancel uses, so the two never deadlock). @return array{0: ?Order, 1: Payment} */
    public function lockedOrderAndPayment(int $paymentId): array;

    /** A payment row, locked. */
    public function lockedPayment(int $paymentId): ?Payment;

    public function updatePayment(Payment $payment, array $attributes): void;

    public function updateOrder(Order $order, array $attributes): void;

    /** The order with its payments and latest payment. */
    public function orderWithPayments(int $userId, int $orderId): ?Order;
}
