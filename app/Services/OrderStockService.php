<?php

namespace App\Services;

use App\Models\Order;
use App\Repositories\Contracts\StockRepositoryInterface;

/**
 * Stock lifecycle of an order's items (same rules as taksh-admin):
 *   place order -> reserved_qty += qty        (done while placing, under row locks)
 *   paid        -> commit(): reserved_qty -= qty and stock_qty -= qty   (once; marked by orders.stock_committed_at)
 *   cancelled   -> release(): not paid yet -> reserved_qty -= qty;  already paid -> stock_qty += qty
 * Both methods must run inside a transaction that already holds the order row lock.
 */
class OrderStockService
{
    public function __construct(private readonly StockRepositoryInterface $stock) {}

    /** Turn the reservation into sold stock. False when it was already committed, so nothing is deducted twice. */
    public function commit(Order $order): bool
    {
        if (! $this->stock->hasCommitMarker()) {
            // the marker column is not migrated: only warehouse orders were ever committed on payment
            if ($order->warehouse_id) {
                $this->apply($order, fn ($row, int $qty) => $this->sell($row, $qty), 'warehouse');
            }

            return false;
        }

        if ($order->stock_committed_at !== null) {
            return false;
        }

        $this->apply($order, fn ($row, int $qty) => $this->sell($row, $qty));
        $this->stock->markCommitted($order);

        return true;
    }

    /** Give the order's stock back (cancellation). Callers guard against running it twice under the order lock. */
    public function release(Order $order): void
    {
        if (! $this->stock->hasCommitMarker()) {
            if (! $order->warehouse_id) {
                $this->apply($order, fn ($row, int $qty) => $this->unreserve($row, $qty), 'vendor');
            }

            return;
        }

        // warehouse orders paid before the marker existed were committed without one; a paid payment tells them apart
        $committed = $order->stock_committed_at !== null || ($order->warehouse_id && $this->stock->hasPaidPayment($order));

        $this->apply($order, $committed
            ? function ($row, int $qty) {
                $row->stock_qty = (int) $row->stock_qty + $qty;
                $this->stock->saveRow($row);
            }
            : fn ($row, int $qty) => $this->unreserve($row, $qty));
    }

    private function sell($row, int $qty): void
    {
        $row->reserved_qty = max(0, (int) $row->reserved_qty - $qty);
        $row->stock_qty = max(0, (int) $row->stock_qty - $qty);
        $this->stock->saveRow($row);
    }

    private function unreserve($row, int $qty): void
    {
        $row->reserved_qty = max(0, (int) $row->reserved_qty - $qty);
        $this->stock->saveRow($row);
    }

    private function apply(Order $order, callable $change, ?string $only = null): void
    {
        foreach ($this->stock->lockedRows($order, $only) as [$item, $row]) {
            $change($row, (int) $item->qty);
        }
    }
}
