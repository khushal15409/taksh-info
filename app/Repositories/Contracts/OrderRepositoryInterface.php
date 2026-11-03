<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use App\Models\Payment;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    /** Delivery codes that mean a Quick (30 minute) order. */
    public const QUICK_TYPES = ['quick', '30_min', 'express_30'];

    public function transaction(Closure $callback): mixed;

    /**
     * A customer's orders, newest first, with what a list needs.
     *
     * @param  array{order_status?: ?string, delivery_mode?: ?string}  $filters  delivery_mode = quick|standard
     */
    public function paginateForUser(int $userId, array $filters, int $perPage): LengthAwarePaginator;

    /** One of the customer's orders with address, shop, payments, items, charges and refunds. */
    public function findForDetail(int $userId, int $orderId): ?Order;

    /** The customer's order, locked: two cancels (or a cancel racing a payment) are handled one after the other. */
    public function lockedOwned(int $userId, int $orderId): ?Order;

    public function setStatus(Order $order, string $status): void;

    /** A cancelled order no longer uses up the coupon. */
    public function releaseCoupon(int $orderId): void;

    /** The order's latest paid payment. */
    public function paidPayment(Order $order): ?Payment;

    /** The paid online payment that has a gateway payment id to refund. */
    public function refundablePayment(Order $order): ?Payment;

    /** Is there an order with this number that belongs to the customer? */
    public function numberOwnedBy(int $userId, string $orderNumber): bool;

    /** An order for delivery tracking: address, delivery man and last known location. */
    public function forTracking(int $orderId): ?Order;
}
