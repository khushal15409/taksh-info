<?php

namespace App\Repositories\Eloquent;

use App\Models\DeliveryType;
use App\Models\ProductVariant;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Repositories\Contracts\CartCatalogRepositoryInterface;
use Illuminate\Support\Collection;

class CartCatalogRepository implements CartCatalogRepositoryInterface
{
    public function variant(int $id): ?ProductVariant
    {
        return ProductVariant::with('product')->find($id);
    }

    public function availableStock(int $vendorId, int $variantId): int
    {
        $vp = VendorProduct::where('vendor_id', $vendorId)->where('product_variant_id', $variantId)->first();

        return $vp ? max(0, (int) $vp->stock_qty - (int) $vp->reserved_qty) : 0;
    }

    public function vendorApproved(int $vendorId): bool
    {
        return Vendor::where('id', $vendorId)->where('status', 'approved')->exists();
    }

    public function approvedVendorIds(array $vendorIds): Collection
    {
        return Vendor::whereIn('id', $vendorIds)->where('status', 'approved')->pluck('id');
    }

    public function vendorNames(array $vendorIds): Collection
    {
        return Vendor::whereIn('id', $vendorIds)->pluck('shop_name', 'id');
    }

    public function deliveryType(string $code): ?DeliveryType
    {
        return DeliveryType::where('code', $code)->first();
    }
}
