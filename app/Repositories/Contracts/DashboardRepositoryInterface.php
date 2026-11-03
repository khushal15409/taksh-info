<?php

namespace App\Repositories\Contracts;

use App\Models\AppDashboardSection;
use Illuminate\Support\Collection;

/** Sections, their mapped products/categories and the parent-category strip of the standard home. */
interface DashboardRepositoryInterface
{
    public const MAPPED_PRODUCTS = 'products';
    public const MAPPED_CATEGORIES = 'categories';
    public const MAPPED_BANNERS = 'banners';

    /** Active sections of one home tab in admin order (logo, banners and categories are built separately). */
    public function activeSections(string $dashboardType): Collection;

    /** What a section is filled with: products first, then categories, then banners; null when nothing is mapped. */
    public function mappedType(int $sectionId): ?string;

    /** Products mapped to the section (standard home: never 30-minute items), newest first. */
    public function sectionProducts(AppDashboardSection $section): Collection;

    /** Categories mapped to the section that really have products. */
    public function sectionCategories(AppDashboardSection $section, string $deliveryType): Collection;

    /** Top-level categories offered for the delivery type that hold products, with `products_count`. */
    public function parentCategories(string $deliveryType): Collection;
}
