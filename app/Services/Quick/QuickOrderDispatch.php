<?php

namespace App\Services\Quick;

use App\Models\DeliveryMan;
use App\Models\Order;
use App\Models\OrderStatusLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * What a Quick order needs to enter taksh-admin's delivery workflow (the same as its QuickOrderDispatch): the first row of the order's
 * timeline and, when a rider is genuinely free, the least-loaded auto-assignment. With nobody online and free the order simply stays
 * unassigned for the admin panel. Callers treat any failure here as non-fatal: it must never undo an order or a payment.
 */
class QuickOrderDispatch
{
    public const QUICK_TYPES = ['quick', '30_min', 'express_30'];

    /** Order statuses after which nobody needs to be assigned any more. */
    private const CLOSED = ['delivered', 'cancelled', 'return_to_seller', 'rt_completed', 'rvp_completed', 'pickup_cancelled_by_seller'];

    /** Orders that count against a rider's load. */
    private const ACTIVE = ['confirmed', 'packed', 'ready_for_pickup', 'out_for_delivery'];

    public static function isQuick(?string $deliveryType, bool $isExpress = false): bool
    {
        return $isExpress || in_array($deliveryType, self::QUICK_TYPES, true);
    }

    /** First timeline entry; written once. */
    public function logPlaced(Order $order): void
    {
        if (OrderStatusLog::where('order_id', $order->id)->exists()) {
            return;
        }

        OrderStatusLog::create(['order_id' => $order->id, 'status' => $order->order_status ?: 'pending', 'changed_by' => 'system', 'notes' => 'Order placed']);
    }

    /** Admin delivery_men ids of the quick field executives of the order's logistic center (nobody when none is set up). */
    public static function centerQuickRiderIds(?int $logisticCenterId)
    {
        if (! $logisticCenterId) {
            return collect();
        }

        try {
            $logistic = DB::connection('logistic')->table('users');
            $centerName = (clone $logistic)->where('id', $logisticCenterId)->value('name');

            if (! $centerName) {
                return collect();
            }

            return $logistic->where('center_name', $centerName)->where('designation', 'Field Executive')->where('is_active', 1)
                ->where('quick_delivery', true)->whereNotNull('admin_delivery_man_id')->pluck('admin_delivery_man_id');
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Quick auto-assign: logistic database unavailable, nobody assigned', ['error' => $e->getMessage()]);

            return collect();
        }
    }

    /** Assign the least-loaded online quick executive who is under the active-order limit. Idempotent. */
    public function tryAssignRider(Order $order): ?int
    {
        $assigned = $order->getAttribute('delivery_man_id');
        if (! self::isQuick($order->delivery_type, (bool) $order->is_express) || $assigned || in_array($order->order_status, self::CLOSED, true)) {
            return null;
        }

        $riderIds = self::centerQuickRiderIds($order->getAttribute('logistic_center_id'));
        if ($riderIds->isEmpty()) {
            return null;
        }

        $best = null;
        $bestLoad = null;
        foreach (DeliveryMan::whereIn('id', $riderIds)->where('status', 'approved')->where('is_online', true)->get() as $rider) {
            $load = Order::where('delivery_man_id', $rider->id)->whereIn('order_status', self::ACTIVE)->count();
            if ($load >= (int) $rider->max_active_orders) {
                continue;
            }
            if ($best === null || $load < $bestLoad) {
                [$best, $bestLoad] = [$rider, $load];
            }
        }

        if (! $best) {
            return null;
        }

        $order->forceFill(['delivery_man_id' => $best->id])->save();
        OrderStatusLog::create(['order_id' => $order->id, 'status' => $order->order_status, 'changed_by' => 'system', 'notes' => 'Delivery man auto-assigned: '.$best->name]);

        return $best->id;
    }
}
