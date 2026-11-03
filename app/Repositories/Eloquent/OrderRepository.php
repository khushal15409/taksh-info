<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Models\Payment;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderRepository implements OrderRepositoryInterface
{
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function paginateForUser(int $userId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Order::where('user_id', $userId);

        if (! empty($filters['order_status'])) {
            $query->where('order_status', $filters['order_status']);
        }

        // server-side delivery filter (works across every page): quick = a stored Quick code or the express flag; standard = the rest
        $mode = $filters['delivery_mode'] ?? null;
        if ($mode === 'quick' || $mode === 'standard') {
            $query->where(function ($q) use ($mode) {
                if ($mode === 'quick') {
                    $q->whereIn('delivery_type', self::QUICK_TYPES)->orWhere('is_express', true);
                } else {
                    $q->where(fn ($s) => $s->whereNull('delivery_type')->orWhereNotIn('delivery_type', self::QUICK_TYPES))
                        ->where(fn ($s) => $s->whereNull('is_express')->orWhere('is_express', false));
                }
            });
        }

        return $query->with(array_merge(['items.productVariant', 'address', 'vendor', 'latestPayment'], $this->itemRelations()))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findForDetail(int $userId, int $orderId): ?Order
    {
        return Order::where('id', $orderId)->where('user_id', $userId)
            ->with(array_merge(['address.state', 'address.city', 'address.area', 'vendor', 'payments', 'latestPayment', 'charges', 'refundTransactions'], $this->itemRelations()))
            ->first();
    }

    /** Relations a line needs to show its product name and image without a query per product. */
    private function itemRelations(): array
    {
        return [
            'items.productVariant.product:id,name',
            'items.productVariant.product.images' => fn ($q) => $q->select('id', 'product_id', 'image_url', 'is_primary')->orderByDesc('is_primary')->orderBy('sort_order'),
        ];
    }

    public function lockedOwned(int $userId, int $orderId): ?Order
    {
        return Order::where('id', $orderId)->where('user_id', $userId)->lockForUpdate()->first();
    }

    public function setStatus(Order $order, string $status): void
    {
        $order->update(['order_status' => $status]);
    }

    public function releaseCoupon(int $orderId): void
    {
        DB::table('coupon_usages')->where('order_id', $orderId)->delete();
    }

    public function paidPayment(Order $order): ?Payment
    {
        return $order->payments()->whereIn('status', ['paid', 'success'])->orderByDesc('id')->first();
    }

    public function refundablePayment(Order $order): ?Payment
    {
        return Payment::where('order_id', $order->id)->where('gateway', 'razorpay')->whereIn('status', ['paid', 'success'])
            ->whereNotNull('razorpay_payment_id')->orderByDesc('id')->first();
    }

    public function numberOwnedBy(int $userId, string $orderNumber): bool
    {
        return Order::where('user_id', $userId)->where('order_number', $orderNumber)->exists();
    }

    public function forTracking(int $orderId): ?Order
    {
        return Order::where('id', $orderId)->with(['address', 'deliveryMan', 'deliveryLocation.deliveryBoy'])->first();
    }
}
