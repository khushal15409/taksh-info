<?php

namespace Tests\Support;

use App\Models\AppDashboardSection;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Express30LogoSetting;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RecentView;
use App\Models\Sale;
use App\Repositories\Contracts\BannerRepositoryInterface;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\RecentViewRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Illuminate\Support\Collection;

class FakeSettingRepository implements SettingRepositoryInterface
{
    public array $values = [];

    public ?Express30LogoSetting $logo = null;

    public function value(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function activeLogo(): ?Express30LogoSetting
    {
        return $this->logo;
    }
}

class FakeBannerRepository implements BannerRepositoryInterface
{
    public Collection $banners;

    public array $lastFilters = [];

    public function __construct()
    {
        $this->banners = collect();
    }

    public function active(array $filters = []): Collection
    {
        $this->lastFilters = $filters;

        return $this->banners->when(
            ! empty($filters['type']) && in_array($filters['type'], Banner::TYPES, true),
            fn ($c) => $c->where('type', $filters['type'])
        )->values();
    }

    public function standardForDashboard(): Collection
    {
        return $this->banners->where('type', 'standard')->values();
    }
}

class FakeDashboardRepository implements DashboardRepositoryInterface
{
    public Collection $sections;

    /** @var array<int, string> sectionId => products|categories|banners */
    public array $mapped = [];

    public array $products = [];

    public array $categories = [];

    public Collection $parents;

    public function __construct()
    {
        $this->sections = collect();
        $this->parents = collect();
    }

    public function activeSections(string $dashboardType): Collection
    {
        return $this->sections;
    }

    public function mappedType(int $sectionId): ?string
    {
        return $this->mapped[$sectionId] ?? null;
    }

    public function sectionProducts(AppDashboardSection $section): Collection
    {
        return collect($this->products[$section->id] ?? []);
    }

    public function sectionCategories(AppDashboardSection $section, string $deliveryType): Collection
    {
        return collect($this->categories[$section->id] ?? []);
    }

    public function parentCategories(string $deliveryType): Collection
    {
        return $this->parents;
    }
}

class FakeProductRepository implements ProductRepositoryInterface
{
    public array $stock = [];

    public function stockInfo(Product $product): array
    {
        $in = $this->stock[$product->id] ?? true;

        return ['in_stock' => $in, 'out_of_stock_message' => $in ? null : 'Out of stock'];
    }

    public function cheapestActiveVariant(Product $product): ?ProductVariant
    {
        return $product->relationLoaded('variants') ? $product->variants->sortBy('price')->first() : null;
    }

    public Collection $inCategories;

    public function __construct()
    {
        $this->inCategories = collect();
    }

    public function activeInCategories(array $categoryIds): Collection
    {
        return $this->inCategories->whereIn('category_id', $categoryIds)->values();
    }

    public function activeLimited(int $limit): Collection
    {
        return $this->inCategories->take($limit)->values();
    }

    public array $fcProducts = [];

    public function activeForFulfillmentCenter(int $fcId, bool $quickOnly = false): Collection
    {
        return collect($this->fcProducts[$fcId] ?? []);
    }
}

class FakeSaleRepository implements SaleRepositoryInterface
{
    public Collection $sales;

    public array $categoryIds = [];      // saleId => [..]

    public array $productIds = [];       // saleId => [..]

    public array $fcIds = [];            // saleId => [..]

    public array $saleProducts = [];     // saleId => Collection

    public array $saleCategories = [];

    public array $saleCenters = [];

    public ?int $nearest = null;

    public array $expressCenters = [];

    public function __construct()
    {
        $this->sales = collect();
    }

    public function active(): Collection
    {
        return $this->sales->sortByDesc('priority')->values();
    }

    public function findActive(int $id): ?Sale
    {
        return $this->sales->firstWhere('id', $id);
    }

    public function categoryIds(Sale $sale): array
    {
        return $this->categoryIds[$sale->id] ?? [];
    }

    public function includesProduct(Sale $sale, int $productId): bool
    {
        return in_array($productId, $this->productIds[$sale->id] ?? []);
    }

    public function fulfillmentCenterIds(Sale $sale): array
    {
        return $this->fcIds[$sale->id] ?? [];
    }

    public function products(Sale $sale): Collection
    {
        return collect($this->saleProducts[$sale->id] ?? []);
    }

    public function categories(Sale $sale): Collection
    {
        return collect($this->saleCategories[$sale->id] ?? []);
    }

    public function fulfillmentCenters(Sale $sale): Collection
    {
        return collect($this->saleCenters[$sale->id] ?? []);
    }

    public function nearestFulfillmentCenterId(float $latitude, float $longitude, float $radiusKm = 15): ?int
    {
        return $this->nearest;
    }

    public function fulfillmentCenterSupportsExpress(int $fcId): bool
    {
        return in_array($fcId, $this->expressCenters);
    }
}

class FakeRecentViewRepository implements RecentViewRepositoryInterface
{
    public Collection $rows;

    public array $calls = [];

    public function __construct()
    {
        $this->rows = collect();
    }

    public function forUser(int $userId, ?string $deliveryType = null): Collection
    {
        $this->calls[] = [$userId, $deliveryType];

        return $this->rows;
    }

    public array $recorded = [];

    public function record(int $userId, int $productId): void
    {
        $this->recorded[] = [$userId, $productId];
    }
}

/** Builders for in-memory models with their relations already loaded. */
class Make
{
    public static function variant(array $attrs = []): ProductVariant
    {
        return (new ProductVariant)->forceFill($attrs + ['price' => 100, 'sale_price' => null, 'customer_price' => 0, 'status' => 'active']);
    }

    public static function product(int $id, array $attrs = [], array $variants = [], array $images = [], array $reviews = []): Product
    {
        $p = (new Product)->forceFill($attrs + ['id' => $id, 'name' => "Product $id", 'slug' => "product-$id", 'status' => 'active', 'taksh_charge' => null, 'category_id' => 1]);
        $p->setRelation('variants', collect($variants ?: [self::variant()]));
        $p->setRelation('images', collect($images));
        $p->setRelation('reviews', collect($reviews));
        $p->setRelation('brand', null);
        $p->setRelation('category', null);

        return $p;
    }

    public static function sale(int $id, array $attrs = []): Sale
    {
        return (new Sale)->forceFill($attrs + [
            'id' => $id, 'name' => "Sale $id", 'sale_type' => 'global', 'discount_type' => 'percentage', 'discount_value' => 10,
            'start_at' => now()->subDay(), 'end_at' => now()->addDay(), 'is_active' => true, 'priority' => 1,
        ]);
    }

    public static function banner(int $id, array $attrs = []): Banner
    {
        $b = (new Banner)->forceFill($attrs + ['id' => $id, 'type' => 'standard', 'title' => "Banner $id", 'image_url' => "https://cdn.test/b$id.jpg", 'position' => 'home_top', 'is_active' => true]);
        $b->setRelation('afterSection', null);

        return $b;
    }

    public static function section(int $id, array $attrs = []): AppDashboardSection
    {
        return (new AppDashboardSection)->forceFill($attrs + ['id' => $id, 'section_key' => "key_$id", 'section_name' => "Section $id", 'dashboard_type' => 'standard', 'is_active' => true, 'design' => null, 'rows' => null, 'items_per_row' => null]);
    }

    public static function category(int $id, array $attrs = []): Category
    {
        return (new Category)->forceFill($attrs + ['id' => $id, 'name' => "Cat $id", 'slug' => "cat-$id", 'image_url' => null, 'icon_url' => null]);
    }

    public static function recentView(Product $product): RecentView
    {
        return (new RecentView)->setRelation('product', $product);
    }
}
