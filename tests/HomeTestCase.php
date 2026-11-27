<?php

namespace Tests;

use App\Helpers\VendorCharges;
use App\Repositories\Contracts\BannerRepositoryInterface;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\RecentViewRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Closure;
use Tests\Support\FakeBannerRepository;
use Tests\Support\FakeDashboardRepository;
use Tests\Support\FakeProductRepository;
use Tests\Support\FakeRecentViewRepository;
use Tests\Support\FakeSaleRepository;
use Tests\Support\FakeSettingRepository;

require_once __DIR__.'/Support/HomeFakes.php';

abstract class HomeTestCase extends TestCase
{
    protected FakeSettingRepository $settings;

    protected FakeBannerRepository $banners;

    protected FakeDashboardRepository $dashboard;

    protected FakeProductRepository $products;

    protected FakeSaleRepository $sales;

    protected FakeRecentViewRepository $recent;

    protected function setUp(): void
    {
        parent::setUp();

        config(['taksh.dashboard_cache_seconds' => 0, 'taksh.media_url' => 'https://media.test/storage']);

        $this->app->instance(SettingRepositoryInterface::class, $this->settings = new FakeSettingRepository);
        $this->app->instance(BannerRepositoryInterface::class, $this->banners = new FakeBannerRepository);
        $this->app->instance(DashboardRepositoryInterface::class, $this->dashboard = new FakeDashboardRepository);
        $this->app->instance(ProductRepositoryInterface::class, $this->products = new FakeProductRepository);
        $this->app->instance(SaleRepositoryInterface::class, $this->sales = new FakeSaleRepository);
        $this->app->instance(RecentViewRepositoryInterface::class, $this->recent = new FakeRecentViewRepository);

        // VendorCharges keeps the bands for the whole request; clear them so each test reads its own settings.
        Closure::bind(static function () {
            self::$bands = null;
        }, null, VendorCharges::class)();
    }

    /** Save the Taksh charge bands the way admin does (JSON in settings). */
    protected function useTakshBands(array $bands): void
    {
        $this->settings->values[VendorCharges::BANDS_KEY] = json_encode($bands);
    }
}
