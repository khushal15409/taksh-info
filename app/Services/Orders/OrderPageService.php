<?php

namespace App\Services\Orders;

use App\Http\Resources\Order\OrderResource;
use App\Models\User;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** What the order pages read: the customer's order list (filters, pages) and one order with its bill, in the same shape as GET /order/orders. */
class OrderPageService
{
    public const PER_PAGE = 15;

    public function __construct(private readonly OrderRepositoryInterface $orders) {}

    /**
     * One page of the customer's orders, newest first. The dates narrow the orders of the page being shown (YYYY-MM-DD, inclusive).
     *
     * @param  array{order_status?: ?string, delivery_mode?: ?string, from?: ?string, to?: ?string}  $filters
     */
    public function list(User $user, array $filters): LengthAwarePaginator
    {
        $page = $this->orders->paginateForUser($user->id, array_filter([
            'order_status' => $filters['order_status'] ?? null,
            'delivery_mode' => $filters['delivery_mode'] ?? null,
        ]), self::PER_PAGE);

        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        $rows = collect($page->items())->map(fn ($o) => OrderResource::make($o)->resolve())
            ->filter(fn (array $o) => (! $from || substr($o['created_at'], 0, 10) >= $from) && (! $to || substr($o['created_at'], 0, 10) <= $to))
            ->values();

        return $page->setCollection($rows);
    }

    /** @return array<string, mixed>|null null when it is not the customer's order */
    public function detail(User $user, int $id): ?array
    {
        $order = $this->orders->findForDetail($user->id, $id);

        return $order ? OrderResource::make($order)->resolve() : null;
    }
}
