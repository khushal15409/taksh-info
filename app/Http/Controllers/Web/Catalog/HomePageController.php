<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Web\WebController;
use App\Services\DashboardService;
use App\Services\Marketplace\ServiceBookingQueryService;
use App\Services\RecentViewService;
use App\Support\Presenters\HomeLayout;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The home page: banners, categories and the admin-configured sections from the shared catalogue, the first services, recently viewed
 * products for a signed-in customer, and the fixed marketing blocks. When the catalogue has nothing to show yet the page says so.
 */
class HomePageController extends WebController
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly RecentViewService $recent,
        private readonly ServiceBookingQueryService $services,
    ) {}

    public function show(Request $request): View
    {
        $home = HomeLayout::from($this->sections());
        $user = $request->user();

        return $this->page('catalog.home', [
            'home' => $home,
            'recent' => $user ? $this->recent->forUser($user->id, 'standard_delivery') : [],
            'services' => $this->serviceStrip(),
        ]);
    }

    /** The first services for the home strip. The strip is an extra: if it cannot be read the rest of the home still shows. */
    private function serviceStrip(): array
    {
        try {
            return array_slice($this->services->catalog(), 0, 4);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /** The dashboard sections. The home still opens (and says the catalogue is being set up) if they cannot be read. */
    private function sections(): array
    {
        try {
            return $this->dashboard->standard();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }
}
