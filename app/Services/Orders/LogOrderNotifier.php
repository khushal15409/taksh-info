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
        }
    }

    public function paymentConfirmed(Order $order): void
    {
        $this->forgetCounts();
        Log::info('Order payment confirmed from the website', ['order_id' => $order->id]);
    }

    public function cancelled(Order $order): void
    {
        $this->forgetCounts();
        Log::info('Order cancelled from the website', ['order_id' => $order->id]);
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
