<?php

namespace Tests;

use App\Repositories\Contracts\AddressRepositoryInterface;
use App\Repositories\Contracts\LocationRepositoryInterface;
use App\Repositories\Contracts\WishlistRepositoryInterface;
use App\Services\Location\PlaceLookup;
use Tests\Support\FakeAddressRepository;
use Tests\Support\FakeLocationRepository;
use Tests\Support\FakePlaceLookup;
use Tests\Support\FakeWishlistRepository;

require_once __DIR__.'/Support/AccountFakes.php';

abstract class AccountTestCase extends CatalogTestCase
{
    protected FakeWishlistRepository $wishlist;

    protected FakeAddressRepository $addresses;

    protected FakeLocationRepository $locations;

    protected FakePlaceLookup $places;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(WishlistRepositoryInterface::class, $this->wishlist = new FakeWishlistRepository);
        $this->app->instance(AddressRepositoryInterface::class, $this->addresses = new FakeAddressRepository);
        $this->app->instance(LocationRepositoryInterface::class, $this->locations = new FakeLocationRepository);
        $this->app->instance(PlaceLookup::class, $this->places = new FakePlaceLookup);
    }
}
