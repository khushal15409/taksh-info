<?php

namespace App\Repositories\Eloquent;

use App\Models\Address;
use App\Repositories\Contracts\AddressRepositoryInterface;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AddressRepository implements AddressRepositoryInterface
{
    public function forUser(int $userId): Collection
    {
        return Address::where('user_id', $userId)->with(['state', 'city', 'area'])->orderBy('is_default', 'desc')->orderBy('created_at', 'desc')->get();
    }

    public function findOwned(int $userId, int $addressId): ?Address
    {
        return Address::where('id', $addressId)->where('user_id', $userId)->first();
    }

    public function hasAny(int $userId): bool
    {
        return Address::where('user_id', $userId)->exists();
    }

    public function count(int $userId): int
    {
        return Address::where('user_id', $userId)->count();
    }

    public function create(array $attributes): Address
    {
        return Address::create($attributes);
    }

    public function save(Address $address, array $attributes = []): Address
    {
        $address->fill($attributes)->save();

        return $address;
    }

    public function clearDefaults(int $userId): void
    {
        Address::where('user_id', $userId)->update(['is_default' => false]);
    }

    public function latest(int $userId): ?Address
    {
        return Address::where('user_id', $userId)->orderBy('created_at', 'desc')->first();
    }

    public function delete(Address $address): void
    {
        $address->delete();
    }

    public function fresh(Address $address): Address
    {
        return $address->fresh(['state', 'city', 'area']);
    }

    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}
