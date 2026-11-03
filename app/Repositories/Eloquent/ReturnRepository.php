<?php

namespace App\Repositories\Eloquent;

use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductReturn;
use App\Models\ReturnMedia;
use App\Repositories\Contracts\ReturnRepositoryInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnRepository implements ReturnRepositoryInterface
{
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function ownedOrder(int $userId, int $orderId): ?Order
    {
        return Order::where('id', $orderId)->where('user_id', $userId)->first();
    }

    public function orderItem(Order $order, int $itemId): ?OrderItem
    {
        return $order->items()->with('productVariant.product')->where('id', $itemId)->first();
    }

    public function alreadyReturned(int $orderItemId): int
    {
        return (int) DB::table('return_items')
            ->join('returns', 'returns.id', '=', 'return_items.return_id')
            ->where('returns.order_item_id', $orderItemId)
            ->where('returns.status', '<>', ReturnStatus::REJECTED->value)
            ->sum('return_items.quantity');
    }

    public function hasOpenReturn(int $orderItemId): bool
    {
        return ProductReturn::where('order_item_id', $orderItemId)->whereIn('status', ReturnStatus::open())->exists();
    }

    public function create(array $attributes): ProductReturn
    {
        return ProductReturn::create($attributes);
    }

    public function addItem(ProductReturn $return, array $attributes): void
    {
        $return->items()->create($attributes);
    }

    public function addStatusLog(ProductReturn $return, array $attributes): void
    {
        $return->statusLogs()->create($attributes);
    }

    public function ownedReturn(int $userId, int $returnId): ?ProductReturn
    {
        return ProductReturn::where('id', $returnId)->where('customer_id', $userId)->first();
    }

    public function addMedia(ProductReturn $return, array $attributes): ReturnMedia
    {
        return $return->media()->create($attributes);
    }

    public function paginate(int $userId, ?string $status, int $perPage): LengthAwarePaginator
    {
        return ProductReturn::where('customer_id', $userId)
            ->with(['order', 'orderItem.productVariant.product', 'items', 'media', 'refund', 'statusLogs'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function detail(int $userId, int $returnId): ?ProductReturn
    {
        return ProductReturn::where('id', $returnId)->where('customer_id', $userId)
            ->with(['order.address', 'orderItem.productVariant.product.images', 'items', 'media', 'refund', 'statusLogs'])
            ->first();
    }

    public function statusHistory(ProductReturn $return): Collection
    {
        return $return->statusLogs()->orderBy('created_at', 'asc')->get();
    }

    public function pickup(int $returnId): ?object
    {
        try {
            return DB::connection('logistic')->table('shipments')->where('return_id', $returnId)->first(['tracking_id', 'status', 'origin_center']);
        } catch (QueryException $e) {
            Log::error('Return track: logistic database unavailable', ['return_id' => $returnId]);

            return null;
        }
    }
}
