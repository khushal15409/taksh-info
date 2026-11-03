<?php

namespace App\Services;

use App\Http\Resources\Home\CategoryResource;
use App\Http\Resources\Home\DashboardBannerResource;
use App\Http\Resources\Home\DashboardProductResource;
use App\Models\AppDashboardSection;
use App\Models\Category;
use App\Repositories\Contracts\BannerRepositoryInterface;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the standard home dashboard exactly like taksh-admin's DashboardController::index:
 * Logo -> Banners -> Categories -> the admin-configured sections, each filled from what is mapped to it.
 */
class DashboardService
{
    public function __construct(
        private readonly DashboardRepositoryInterface $dashboard,
        private readonly BannerRepositoryInterface $banners,
        private readonly SettingRepositoryInterface $settings,
        private readonly MediaUrlService $media,
    ) {}

    /** @return list<array<string, mixed>> */
    public function standard(): array
    {
        $ttl = (int) config('taksh.dashboard_cache_seconds');

        return $ttl > 0
            ? Cache::remember('home:dashboard:standard', $ttl, fn () => $this->build())
            : $this->build();
    }

    private function build(): array
    {
        $banners = $this->bannerRows();
        $categories = $this->categoryRows();

        $sections = [
            ['key' => 'Logo'] + $this->logo(),
            ['key' => 'Banners', 'data' => $banners, 'message' => $banners ? null : 'No data available'],
            ['key' => 'Categories', 'data' => $categories, 'message' => $categories ? null : 'No data available'],
        ];

        foreach ($this->dashboard->activeSections(AppDashboardSection::DASHBOARD_STANDARD) as $section) {
            $filled = $this->fill($section);

            $sections[] = [
                'key' => $section->section_name,
                'data' => $filled['data'],
                'message' => $filled['message'],
            ] + $section->apiConfig();
        }

        return $sections;
    }

    /** @return array{data: array, message: ?string} */
    private function logo(): array
    {
        $logo = $this->settings->activeLogo();

        if ($logo && $logo->logo_url) {
            return ['data' => ['title' => $logo->title ?? 'Express 30', 'logo_url' => $this->media->url($logo->logo_url, 'express30')], 'message' => null];
        }

        $business = $this->settings->value('logo');

        return [
            'data' => [
                'title' => 'Express 30',
                'logo_url' => $this->media->url($business, 'business') ?? asset('assets/img/logo.png'),
            ],
            'message' => null,
        ];
    }

    private function bannerRows(): array
    {
        return DashboardBannerResource::collection($this->banners->standardForDashboard())->resolve();
    }

    private function categoryRows(): array
    {
        return CategoryResource::collection($this->dashboard->parentCategories(Category::DELIVERY_STANDARD))->resolve();
    }

    /** Priority of what a section shows: products, then categories, then banners. */
    private function fill(AppDashboardSection $section): array
    {
        $data = match ($this->dashboard->mappedType($section->id)) {
            DashboardRepositoryInterface::MAPPED_PRODUCTS => DashboardProductResource::collection($this->dashboard->sectionProducts($section))->resolve(),
            DashboardRepositoryInterface::MAPPED_CATEGORIES => CategoryResource::collection($this->dashboard->sectionCategories($section, Category::DELIVERY_STANDARD))->resolve(),
            DashboardRepositoryInterface::MAPPED_BANNERS => $this->bannerRows(),
            default => null,
        };

        if ($data === null) {
            return ['data' => [], 'message' => 'No data mapped to this section'];
        }

        return ['data' => $data, 'message' => $data ? null : 'No data available'];
    }
}
