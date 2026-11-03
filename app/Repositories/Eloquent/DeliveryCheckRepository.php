<?php

namespace App\Repositories\Eloquent;

use App\Models\Pincode;
use App\Models\Product;
use App\Models\Vendor;
use App\Repositories\Contracts\DeliveryCheckRepositoryInterface;

class DeliveryCheckRepository implements DeliveryCheckRepositoryInterface
{
    public function product(int $id): ?Product
    {
        return Product::with(['variants', 'vendor'])->where('status', 'active')->where('approval_status', 'approved')->find($id);
    }

    public function approvedVendor(int $vendorId): ?Vendor
    {
        return Vendor::where('id', $vendorId)->where('status', 'approved')->first();
    }

    public function activePincode(string $pincode): ?Pincode
    {
        return Pincode::where('pincode', $pincode)->where('status', true)->first();
    }

    public function vendorHasStock(Product $product, Vendor $vendor, ?int $variantId, int $qty): bool
    {
        return $product->variants()
            ->where('status', 'active')
            ->when($variantId !== null, fn ($q) => $q->where('id', $variantId))
            ->whereHas('vendorProducts', fn ($q) => $q->where('vendor_id', $vendor->id)->whereRaw('(stock_qty - reserved_qty) >= ?', [$qty]))
            ->exists();
    }
}
