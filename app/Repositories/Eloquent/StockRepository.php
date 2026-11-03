<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Models\VendorProduct;
use App\Models\WarehouseProduct;
use App\Repositories\Contracts\StockRepositoryInterface;
use Generator;
use Illuminate\Support\Facades\Schema;

class StockRepository implements StockRepositoryInterface
{
    public function hasCommitMarker(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('orders', 'stock_committed_at');
    }

    public function lockedRows(Order $order, ?string $only = null): Generator
    {
        foreach ($order->items()->orderBy('product_variant_id')->get() as $item) {
            if ($order->warehouse_id && $only !== 'vendor') {
                $row = WarehouseProduct::where('warehouse_id', $order->warehouse_id)->where('product_variant_id', $item->product_variant_id)->lockForUpdate()->first();
            } elseif ($order->vendor_id && $only !== 'warehouse' && ! $order->warehouse_id) {
                $row = VendorProduct::where('vendor_id', $order->vendor_id)->where('product_variant_id', $item->product_variant_id)->lockForUpdate()->first();
            } else {
                $row = null;
            }

            if ($row) {
                yield [$item, $row];
            }
        }
    }

    public function saveRow($row): void
    {
        $row->save();
    }

    public function hasPaidPayment(Order $order): bool
    {
        return $order->payments()->whereIn('status', ['paid', 'success'])->exists();
    }

    public function markCommitted(Order $order): void
    {
        $order->forceFill(['stock_committed_at' => now()])->save();
    }
}
