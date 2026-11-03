<?php

namespace App\Services\Orders;

use App\Models\Order;

/** What the rest of Taksh is told when the website places or pays an order. */
interface OrderNotifier
{
    /** One notice for a whole checkout (several vendor orders read as one). @param iterable<Order> $orders */
    public function placed(iterable $orders): void;

    public function paymentConfirmed(Order $order): void;

    public function cancelled(Order $order): void;
}
