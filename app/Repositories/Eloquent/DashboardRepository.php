<?php

namespace App\Repositories\Eloquent;

use App\Models\AppDashboardSection;
use App\Models\Category;
use App\Models\Product;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardRepository implements DashboardRepositoryInterface
{
    public function activeSections(string $dashboardType): Collection
    {
        return AppDashboardSection::where('is_active', true)
            ->forDashboard($dashboardType)
            ->whereNotIn('section_key', ['logo', 'banners', 'categories'])
            ->orderBy('sort_order')
            ->get();
    }

    public function mappedType(int $sectionId): ?string
    {
        foreach ([
            self::MAPPED_PRODUCTS => 'product_section_mappings',
            self::MAPPED_CATEGORIES => 'category_section_mappings',
            self::MAPPED_BANNERS => 'banner_section_mappings',
        ] as $type => $table) {
            if (DB::table($table)->where('section_id', $sectionId)->exists()) {
                return $type;
            }
        }

        return null;
    }

    public function sectionProducts(AppDashboardSection $section): Collection
    {
        return Product::with([
            'brand',
            'category',
            'variants' => fn ($q) => $q->where('status', 'active')->orderBy('sale_price', 'asc')->limit(1),
            'images' => fn ($q) => $q->where('is_primary', true)->limit(1),
            'reviews' => fn ($q) => $q->where('is_approved', true),
        ])
            ->where('status', 'active')
            ->where('is_express_30', false)                       // the standard home never lists 30-minute products
            ->whereHas('sectionMappings', fn ($q) => $q->where('section_id', $section->id))
            ->whereHas('variants', fn ($q) => $q->where('status', 'active'))
            ->orderBy('created_at', 'desc')
            ->limit($section->productLimit())
            ->get();
    }

    public function sectionCategories(AppDashboardSection $section, string $deliveryType): Collection
    {
        return Category::forDeliveryType($deliveryType)
            ->where('categories.status', 'active')
            ->whereHas('sectionMappings', fn ($q) => $q->where('section_id', $section->id))
            ->where(fn ($q) => $this->hasStandardProducts($q))
            ->orderBy('name', 'asc')
            ->get();
    }

    public function parentCategories(string $deliveryType): Collection
    {
        return Category::forDeliveryType($deliveryType)
            ->select('categories.*')
            ->distinct()
            ->where('categories.status', 'active')
            ->whereNull('categories.parent_id')
            ->where(fn ($q) => $this->hasStandardProducts($q))
            ->withCount(['products' => fn ($q) => $q->where('status', 'active')->where('is_express_30', false)])
            ->orderBy('categories.name', 'asc')
            ->get();
    }

    /** Products directly in the category, or in one of its sub-categories (30-minute items belong to the quick screens). */
    private function hasStandardProducts(Builder $query): Builder
    {
        $standard = fn ($q) => $q->where('status', 'active')->where('is_express_30', false);

        return $query->whereHas('products', $standard)->orWhereHas('children.products', $standard);
    }
}
