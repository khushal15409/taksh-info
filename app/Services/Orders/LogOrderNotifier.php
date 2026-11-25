<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The order observer of taksh-admin clears its cached order counts and queues customer pushes. The website
 * clears the same cache key (it takes effect when the cache store is shared with taksh-admin) and records
 * the event in the log; the push itself is sent by taksh-admin (TAKSH_ORDER_PUSH is off by default).
 */
class LogOrderNotifier implements OrderNotifier
{
    public function placed(iterable $orders): void
    {
        $orders = collect($orders)->values();
        $this->forgetCounts();

        if ($orders->isNotEmpty()) {
            Log::info('Order placed from the website', ['order_ids' => $orders->pluck('id')->all()]);

            // the same words as the app's push: one message for a whole checkout
            $first = $orders->first();
            OrderInbox::add((int) $first->user_id, 'Order placed', $orders->count() > 1
                ? "Your {$orders->count()} orders have been placed."
                : "Your order {$first->order_number} has been placed.");
        }
    }

    public function paymentConfirmed(Order $order): void
    {
        $this->forgetCounts();
        Log::info('Order payment confirmed from the website', ['order_id' => $order->id]);
        OrderInbox::add((int) $order->user_id, 'Payment received', "We received your payment for order {$order->order_number}.");
    }

    public function cancelled(Order $order): void
    {
        $this->forgetCounts();
        Log::info('Order cancelled from the website', ['order_id' => $order->id]);
        OrderInbox::add((int) $order->user_id, 'Order cancelled', "Your order {$order->order_number} has been cancelled.");
    }

    private function forgetCounts(): void
    {
        try {
            Cache::forget('admin_order_counts');
        } catch (\Throwable $e) {
            // never let a cache problem touch the order
        }
    }
}
