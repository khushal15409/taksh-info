<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductReturn;
use App\Models\ReturnMedia;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ReturnRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    public function ownedOrder(int $userId, int $orderId): ?Order;

    /** A line of the order, with its variant and product. */
    public function orderItem(Order $order, int $itemId): ?OrderItem;

    /** Pieces of the line already on a return request that was not rejected. */
    public function alreadyReturned(int $orderItemId): int;

    /** Is a request for this line still being worked on? */
    public function hasOpenReturn(int $orderItemId): bool;

    public function create(array $attributes): ProductReturn;

    public function addItem(ProductReturn $return, array $attributes): void;

    public function addStatusLog(ProductReturn $return, array $attributes): void;

    public function ownedReturn(int $userId, int $returnId): ?ProductReturn;

    public function addMedia(ProductReturn $return, array $attributes): ReturnMedia;

    public function paginate(int $userId, ?string $status, int $perPage): LengthAwarePaginator;

    /** A return with order, line, pieces, pictures, refund and status history. */
    public function detail(int $userId, int $returnId): ?ProductReturn;

    /** @return Collection<int, \App\Models\ReturnStatusLog> oldest first */
    public function statusHistory(ProductReturn $return): Collection;

    /** The pickup booked for this return in the logistics database, if there is one (and the database can be reached). */
    public function pickup(int $returnId): ?object;
}
