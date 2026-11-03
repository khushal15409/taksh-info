<?php

namespace Tests;

use App\Models\User;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\DeliveryCheckRepositoryInterface;
use App\Repositories\Contracts\ProductSearchRepositoryInterface;
use App\Repositories\Contracts\RecentSearchRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Tests\Support\FakeCatalogRepository;
use Tests\Support\FakeCategoryRepository;
use Tests\Support\FakeDeliveryCheckRepository;
use Tests\Support\FakeExistsVerifier;
use Tests\Support\FakeProductSearchRepository;
use Tests\Support\FakeRecentSearchRepository;
use Tests\Support\FakeReviewRepository;

require_once __DIR__.'/Support/CatalogFakes.php';

abstract class CatalogTestCase extends HomeTestCase
{
    protected FakeCategoryRepository $categories;

    protected FakeCatalogRepository $catalog;

    protected FakeDeliveryCheckRepository $delivery;

    protected FakeProductSearchRepository $search;

    protected FakeRecentSearchRepository $recentSearches;

    protected FakeReviewRepository $reviews;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(CategoryRepositoryInterface::class, $this->categories = new FakeCategoryRepository);
        $this->app->instance(CatalogRepositoryInterface::class, $this->catalog = new FakeCatalogRepository);
        $this->app->instance(DeliveryCheckRepositoryInterface::class, $this->delivery = new FakeDeliveryCheckRepository);
        $this->app->instance(ProductSearchRepositoryInterface::class, $this->search = new FakeProductSearchRepository);
        $this->app->instance(RecentSearchRepositoryInterface::class, $this->recentSearches = new FakeRecentSearchRepository);
        $this->app->instance(ReviewRepositoryInterface::class, $this->reviews = new FakeReviewRepository);
        $this->app['validator']->setPresenceVerifier(new FakeExistsVerifier);
    }

    protected function customer(int $id = 5): User
    {
        $user = new User(['mobile' => '9876543210']);
        $user->id = $id;

        return $user;
    }
}
