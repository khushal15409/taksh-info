<?php

namespace App\Repositories\Contracts;

use App\Models\DeliveryType;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/** What the cart needs to know about products, vendors and delivery types. */
interface CartCatalogRepositoryInterface
{
    public function variant(int $id): ?ProductVariant;

    /** Free stock of a variant at a vendor: stock minus what is reserved. */
    public function availableStock(int $vendorId, int $variantId): int;

    public function vendorApproved(int $vendorId): bool;

    /** @return Collection<int, int> ids of the approved ones */
    public function approvedVendorIds(array $vendorIds): Collection;

    /** @return Collection<int, string> shop names keyed by vendor id */
    public function vendorNames(array $vendorIds): Collection;

    public function deliveryType(string $code): ?DeliveryType;
}
