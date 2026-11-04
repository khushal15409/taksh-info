<?php

namespace Tests\Support;

use App\Models\Vendor;
use App\Repositories\Contracts\QuickVendorRepositoryInterface;
use Illuminate\Support\Collection;

class FakeQuickVendors implements QuickVendorRepositoryInterface
{
    /** @var array<int, array{0: float, 1: float}> id => [lat, lng] */
    public array $shops = [];

    /** @var array<string, int[]> */
    public array $byPincode = [];

    public function approvedInLatitudeBand(float $lat, float $delta): Collection
    {
        return collect($this->shops)
            ->filter(fn ($p) => abs($p[0] - $lat) <= $delta)
            ->map(fn ($p, $id) => (new Vendor)->forceFill(['id' => $id, 'shop_latitude' => $p[0], 'shop_longitude' => $p[1]]))->values();
    }

    /** @var array<int, Vendor> */
    public array $vendorRows = [];

    public function vendor(int $id): ?Vendor
    {
        return $this->vendorRows[$id] ?? null;
    }

    public function approvedIdsByShopPincode(string $pincode): array
    {
        return $this->byPincode[$pincode] ?? [];
    }
}

class FakeQuickDispatch extends \App\Services\Quick\QuickOrderDispatch
{
    /** @var int[] */
    public array $logged = [];

    /** @var int[] */
    public array $assigned = [];

    public function logPlaced(\App\Models\Order $order): void
    {
        $this->logged[] = (int) $order->id;
    }

    public function tryAssignRider(\App\Models\Order $order): ?int
    {
        $this->assigned[] = (int) $order->id;

        return null;
    }
}
