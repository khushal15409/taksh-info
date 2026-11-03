<?php

namespace App\Services\Logistics;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * taksh-admin's ShipmentStatusSyncService maps order statuses onto the logistic database's shipments. The website only ever
 * moves an order to `cancelled`, which maps to `cancelled_by_customer` on the forward shipment (a return has its own shipment).
 */
class LogisticShipmentStatusSync implements ShipmentStatusSync
{
    private const CANCELLED = 'cancelled_by_customer';

    public function cancelled(Order $order): void
    {
        try {
            DB::connection('logistic')->transaction(function () use ($order) {
                $shipment = DB::connection('logistic')->table('shipments')->where('order_id', $order->id)->where('shipment_type', 'Forward')->lockForUpdate()->first();

                if (! $shipment || $shipment->status === self::CANCELLED) {
                    return;
                }

                DB::connection('logistic')->table('shipments')->where('id', $shipment->id)->update(['status' => self::CANCELLED, 'updated_at' => now()]);
            });
        } catch (\Throwable $e) {
            Log::error('Shipment status sync failed', ['order_id' => $order->id, 'order_status' => 'cancelled', 'error' => $e->getMessage()]);
        }
    }
}
