<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use Generator;

/** Stock rows of an order's items, locked for the surrounding transaction (always in variant id order). */
interface StockRepositoryInterface
{
    /** Is orders.stock_committed_at there? */
    public function hasCommitMarker(): bool;

    /**
     * @param  string|null  $only  'warehouse' or 'vendor' to look at one kind of stock only
     * @return Generator<int, array{0: \App\Models\OrderItem, 1: \Illuminate\Database\Eloquent\Model}> [item, stock row]
     */
    public function lockedRows(Order $order, ?string $only = null): Generator;

    public function saveRow($row): void;

    /** Did a paid payment exist for this order (to tell a committed warehouse order apart)? */
    public function hasPaidPayment(Order $order): bool;

    public function markCommitted(Order $order): void;
}
