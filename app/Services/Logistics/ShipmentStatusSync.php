<?php

namespace App\Services\Logistics;

use App\Models\Order;

/** Keeps a taksh-logistic shipment in step with the order it belongs to (best effort; never throws into the order). */
interface ShipmentStatusSync
{
    /** The customer cancelled the order: its forward shipment is cancelled too. */
    public function cancelled(Order $order): void;
}
