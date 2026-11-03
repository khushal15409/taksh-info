<?php

namespace App\Services;

use App\Exceptions\AddressProblemException;
use App\Http\Resources\Account\AddressResource;
use App\Repositories\Contracts\AddressRepositoryInterface;

/** A customer's delivery addresses, with the same rules as taksh-admin's AddressController. */
class AddressService
{
    public function __construct(
        private readonly AddressRepositoryInterface $addresses,
        private readonly LocationResolver $location,
    ) {}

    /** @return array{default_address: ?array, addresses: array} the default one on its own, the others newest first */
    public function list(int $userId): array
    {
        $all = $this->addresses->forUser($userId);
        $default = $all->firstWhere('is_default', true);

        return [
            'default_address' => $default ? AddressResource::make($default)->resolve() : null,
            'addresses' => AddressResource::collection($all->where('is_default', false)->values())->resolve(),
        ];
    }

    /**
     * Add an address. The first address becomes the default automatically; asking for the default clears the others.
     *
     * @param  array<string, mixed>  $data  validated request data (`type` already mapped: office -> work)
     */
    public function store(int $userId, array $data): int
    {
        $location = $this->location->resolve(
            $data['pincode'], $data['state_id'] ?? null, $data['city_id'] ?? null, $data['area_id'] ?? null,
            $data['state_name'] ?? null, $data['city_name'] ?? null, $data['area_name'] ?? null,
        ) ?? throw AddressProblemException::unknownPincode();

        return $this->addresses->transaction(function () use ($userId, $data, $location) {
            $hasAddresses = $this->addresses->hasAny($userId);
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault) {
                $this->addresses->clearDefaults($userId);
            } elseif (! $hasAddresses) {
                $isDefault = true;
            }

            return $this->addresses->create([
                'user_id' => $userId,
                'state_id' => $location['state_id'],
                'city_id' => $location['city_id'],
                'area_id' => $location['area_id'],
                'name' => $data['name'],
                'mobile' => $data['mobile'],
                'address_line_1' => $data['address_line_1'],
                'address_line_2' => $data['address_line_2'] ?? null,
                'pincode' => $data['pincode'],
                'landmark' => $data['landmark'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'type' => $data['type'] ?? 'home',
                'is_default' => $isDefault,
            ])->id;
        });
    }

    /**
     * Change only the fields that were sent. A changed pincode moves the address to that pincode's state, city and area.
     * An address can be made the default here, but not un-defaulted (set another one as default instead).
     *
     * @return array<string, mixed> the updated address
     */
    public function update(int $userId, int $addressId, array $data): array
    {
        $address = $this->addresses->findOwned($userId, $addressId) ?? throw AddressProblemException::notYours();

        $makeDefault = (bool) ($data['is_default'] ?? false);
        $names = [$data['state_name'] ?? null, $data['city_name'] ?? null, $data['area_name'] ?? null];
        unset($data['is_default'], $data['state_name'], $data['city_name'], $data['area_name']);

        if (isset($data['pincode']) && trim($data['pincode']) !== trim((string) $address->pincode)) {
            $location = $this->location->resolve($data['pincode'], $data['state_id'] ?? null, $data['city_id'] ?? null, $data['area_id'] ?? null, ...$names)
                ?? throw AddressProblemException::unknownPincode();
            $data = array_merge($data, $location);
        }

        $this->addresses->transaction(function () use ($userId, $address, $data, $makeDefault) {
            if ($makeDefault) {
                $this->addresses->clearDefaults($userId);
                $address->is_default = true;
            }

            $this->addresses->save($address, $data);
        });

        return AddressResource::make($this->addresses->fresh($address))->resolve();
    }

    public function setDefault(int $userId, int $addressId): void
    {
        $address = $this->addresses->findOwned($userId, $addressId) ?? throw AddressProblemException::notYours();

        $this->addresses->transaction(function () use ($userId, $address) {
            $this->addresses->clearDefaults($userId);
            $address->is_default = true;
            $this->addresses->save($address);
        });
    }

    /** The only address cannot be deleted; deleting the default makes the newest remaining one the default. */
    public function delete(int $userId, int $addressId): void
    {
        $address = $this->addresses->findOwned($userId, $addressId) ?? throw AddressProblemException::notYours();

        if ($this->addresses->count($userId) <= 1) {
            throw AddressProblemException::onlyAddress();
        }

        $this->addresses->transaction(function () use ($userId, $address) {
            $wasDefault = $address->is_default;
            $this->addresses->delete($address);

            if ($wasDefault && ($latest = $this->addresses->latest($userId))) {
                $latest->is_default = true;
                $this->addresses->save($latest);
            }
        });
    }
}
