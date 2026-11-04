<?php

namespace App\Repositories\Eloquent;

use App\Models\Vendor;
use App\Repositories\Contracts\QuickVendorRepositoryInterface;
use Illuminate\Support\Collection;

class QuickVendorRepository implements QuickVendorRepositoryInterface
{
    public function approvedInLatitudeBand(float $lat, float $delta): Collection
    {
        return Vendor::query()
            ->where('status', 'approved')
            ->whereNotNull('shop_latitude')
            ->whereNotNull('shop_longitude')
            ->whereBetween('shop_latitude', [$lat - $delta, $lat + $delta])
            ->get(['id', 'shop_latitude', 'shop_longitude']);
    }

    public function vendor(int $id): ?Vendor
    {
        return Vendor::find($id);
    }

    public function approvedIdsByShopPincode(string $pincode): array
    {
        return Vendor::query()->where('status', 'approved')->where('shop_pincode', $pincode)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
