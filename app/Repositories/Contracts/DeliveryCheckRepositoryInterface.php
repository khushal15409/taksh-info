<?php

namespace App\Repositories\Contracts;

use App\Models\Pincode;
use App\Models\Product;
use App\Models\Vendor;

interface DeliveryCheckRepositoryInterface
{
    /** An active, approved product with its variants. */
    public function product(int $id): ?Product;

    /** The vendor, only when approved. */
    public function approvedVendor(int $vendorId): ?Vendor;

    /** The pincode record, only when active. */
    public function activePincode(string $pincode): ?Pincode;

    /** Has the vendor at least $qty free pieces of an active variant (of the given variant when one is chosen)? */
    public function vendorHasStock(Product $product, Vendor $vendor, ?int $variantId, int $qty): bool;
}
