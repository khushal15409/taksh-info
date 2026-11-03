<?php

namespace App\Repositories\Contracts;

use App\Models\Address;
use Closure;
use Illuminate\Support\Collection;

interface AddressRepositoryInterface
{
    /** All of a customer's addresses with state, city and area: the default first, then newest. */
    public function forUser(int $userId): Collection;

    public function findOwned(int $userId, int $addressId): ?Address;

    public function hasAny(int $userId): bool;

    public function count(int $userId): int;

    public function create(array $attributes): Address;

    public function save(Address $address, array $attributes = []): Address;

    public function clearDefaults(int $userId): void;

    /** The newest address (used to pick a new default after the default was deleted). */
    public function latest(int $userId): ?Address;

    public function delete(Address $address): void;

    public function fresh(Address $address): Address;

    /** Run several writes as one. */
    public function transaction(Closure $callback): mixed;
}
