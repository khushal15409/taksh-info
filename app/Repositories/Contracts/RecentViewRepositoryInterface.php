<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface RecentViewRepositoryInterface
{
    /**
     * Products a customer looked at, latest first (each row has its `product` with active variants and images).
     * `$deliveryType` = standard_delivery | express_30 keeps each tab to its own products; null = all.
     */
    public function forUser(int $userId, ?string $deliveryType = null): Collection;

    /** Remember that the customer opened a product: it moves to the top and only the latest twenty are kept. */
    public function record(int $userId, int $productId): void;
}
