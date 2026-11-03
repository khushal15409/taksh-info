<?php

namespace Tests\Support;

use App\Models\Address;
use App\Models\Area;
use App\Models\City;
use App\Models\Pincode;
use App\Models\Product;
use App\Models\State;
use App\Models\Wishlist;
use App\Repositories\Contracts\AddressRepositoryInterface;
use App\Repositories\Contracts\LocationRepositoryInterface;
use App\Repositories\Contracts\WishlistRepositoryInterface;
use App\Services\Location\PlaceLookup;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class FakeWishlistRepository implements WishlistRepositoryInterface
{
    /** @var array<int, Wishlist> */
    public array $rows = [];

    public array $products = [];

    public int $lastPerPage = 0;

    public function paginate(int $userId, int $perPage): LengthAwarePaginator
    {
        $this->lastPerPage = $perPage;
        $rows = collect($this->rows)->where('user_id', $userId)->values();

        return new Paginator($rows, $rows->count(), $perPage, 1);
    }

    public function add(int $userId, int $productId): void
    {
        $exists = collect($this->rows)->contains(fn ($r) => $r->user_id === $userId && $r->product_id === $productId);
        if (! $exists) {
            $this->rows[] = (new Wishlist)->forceFill(['id' => count($this->rows) + 1, 'user_id' => $userId, 'product_id' => $productId]);
        }
    }

    public function remove(int $userId, int $productId): bool
    {
        $before = count($this->rows);
        $this->rows = array_values(array_filter($this->rows, fn ($r) => ! ($r->user_id === $userId && $r->product_id === $productId)));

        return count($this->rows) < $before;
    }

    public function product(int $productId): ?Product
    {
        return $this->products[$productId] ?? null;
    }

    public function productIds(int $userId): array
    {
        return collect($this->rows)->where('user_id', $userId)->pluck('product_id')->map(fn ($id) => (int) $id)->values()->all();
    }
}

class FakeAddressRepository implements AddressRepositoryInterface
{
    /** @var array<int, Address> */
    public array $items = [];

    private int $next = 1;

    public function seed(int $userId, array $attrs = []): Address
    {
        $a = (new Address)->forceFill($attrs + [
            'id' => $this->next++, 'user_id' => $userId, 'name' => 'Asha', 'mobile' => '9876543210', 'address_line_1' => '12 Ring Road',
            'pincode' => '395001', 'type' => 'home', 'is_default' => false, 'created_at' => now()->addSeconds($this->next),
        ]);
        $a->setRelation('state', (new State)->forceFill(['name' => 'Gujarat']))->setRelation('city', (new City)->forceFill(['name' => 'Surat']))->setRelation('area', (new Area)->forceFill(['name' => 'Athwa']));

        return $this->items[$a->id] = $a;
    }

    public function forUser(int $userId): Collection
    {
        return collect($this->items)->where('user_id', $userId)->sortBy([['is_default', 'desc'], ['created_at', 'desc']])->values();
    }

    public function findOwned(int $userId, int $addressId): ?Address
    {
        $a = $this->items[$addressId] ?? null;

        return $a && $a->user_id === $userId ? $a : null;
    }

    public function hasAny(int $userId): bool
    {
        return $this->count($userId) > 0;
    }

    public function count(int $userId): int
    {
        return collect($this->items)->where('user_id', $userId)->count();
    }

    public function create(array $attributes): Address
    {
        return $this->seed($attributes['user_id'], $attributes);
    }

    public function save(Address $address, array $attributes = []): Address
    {
        $address->forceFill($attributes);

        return $this->items[$address->id] = $address;
    }

    public function clearDefaults(int $userId): void
    {
        foreach ($this->items as $a) {
            if ($a->user_id === $userId) {
                $a->is_default = false;
            }
        }
    }

    public function latest(int $userId): ?Address
    {
        return collect($this->items)->where('user_id', $userId)->sortByDesc('created_at')->first();
    }

    public function delete(Address $address): void
    {
        unset($this->items[$address->id]);
    }

    public function fresh(Address $address): Address
    {
        return $address;
    }

    public function transaction(Closure $callback): mixed
    {
        return $callback();
    }
}

class FakeLocationRepository implements LocationRepositoryInterface
{
    /** @var array<string, Area> */
    public array $areas = [];

    /** @var array<string, Pincode> */
    public array $directory = [];

    public array $registered = [];

    public array $created = [];

    public function areaForPincode(string $pincode): ?Area
    {
        return $this->areas[trim($pincode)] ?? null;
    }

    public function directoryEntry(string $pincode): ?Pincode
    {
        return $this->directory[$pincode] ?? null;
    }

    public function registerPincode(array $attributes): Pincode
    {
        $this->registered[] = $attributes;

        return (new Pincode)->forceFill($attributes);
    }

    public function createLocation(string $stateName, string $cityName, string $areaName, string $pincode): array
    {
        $this->created[] = [$stateName, $cityName, $areaName, $pincode];

        return ['state_id' => 100, 'city_id' => 200, 'area_id' => 300];
    }

    public static function area(int $id, int $cityId, int $stateId, string $pincode): Area
    {
        return (new Area)->forceFill(['id' => $id, 'city_id' => $cityId, 'pincode' => $pincode])->setRelation('city', (new City)->forceFill(['id' => $cityId, 'state_id' => $stateId]));
    }
}

class FakePlaceLookup implements PlaceLookup
{
    public array $places = [];

    public ?array $reverse = null;

    public function search(string $query, int $limit = 6): array
    {
        return array_slice($this->places, 0, $limit);
    }

    public function reverse(float $latitude, float $longitude): ?array
    {
        return $this->reverse;
    }
}
