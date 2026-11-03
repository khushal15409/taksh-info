<?php

namespace App\Services;

use App\Helpers\RatingSummary;
use App\Http\Resources\Catalog\ProductDetailResource;
use App\Http\Resources\Catalog\ProductListResource;
use App\Http\Resources\Catalog\SimilarProductResource;
use App\Http\Resources\Catalog\VariantResource;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\RecentViewRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/** The Standard catalogue as taksh-admin's ProductController serves it: price = what the customer pays, MRP struck through. */
class CatalogService
{
    /** Largest page a client may ask for. */
    public const MAX_PAGE_SIZE = 100;

    public function __construct(
        private readonly CatalogRepositoryInterface $catalog,
        private readonly CategoryRepositoryInterface $categories,
        private readonly RecentViewRepositoryInterface $recentViews,
        private readonly SaleRepositoryInterface $sales,
        private readonly PricingService $pricing,
        private readonly SaleService $saleService,
    ) {}

    // ---------------------------------------------------------------- list (C2)

    /** @param array{category_id?: ?int, search?: ?string, min_price?: ?float, max_price?: ?float, limit?: ?int} $filters */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = [
            'search' => $filters['search'] ?? null,
            'min_price' => $filters['min_price'] ?? null,
            'max_price' => $filters['max_price'] ?? null,
        ];

        // A parent category also lists the products of its sub-categories; a sub-category lists only its own.
        if (! empty($filters['category_id'])) {
            $query['category_ids'] = $this->categories->standardIdsWithChildren((int) $filters['category_id']);
        }

        $perPage = min(max((int) ($filters['limit'] ?? 15), 1), self::MAX_PAGE_SIZE);
        $page = $this->catalog->paginate($query, $perPage);

        $variantIds = $page->getCollection()->flatMap(fn ($p) => $p->variants->pluck('id'))->unique()->values()->all();
        $stock = $this->catalog->vendorStock($variantIds);
        $reviews = $this->catalog->approvedReviews($page->getCollection()->pluck('id')->all());

        return $page->through(function (Product $product) use ($stock, $reviews) {
            $rows = $this->variantRows($product, $stock);

            return ProductListResource::make((object) [
                'product' => $product,
                'variants' => $rows,
                'reviews' => $reviews->get($product->id, collect()),
                'in_stock' => $this->anyInStock($rows),
            ])->resolve();
        });
    }

    // ---------------------------------------------------------------- detail (C3, C4, C6)

    /** @return array<string, mixed>|null null when there is no such active product */
    public function detail(int $id, ?User $user = null, ?float $latitude = null, ?float $longitude = null): ?array
    {
        $product = $this->catalog->findForDetail($id);

        if (! $product) {
            return null;
        }

        if ($user) {
            $this->recentViews->record($user->id, $product->id);
        }

        $fcId = $latitude !== null && $longitude !== null ? $this->sales->nearestFulfillmentCenterId($latitude, $longitude, 15) : null;

        // A campaign sale is applied to quick (30 min) products only; Standard shows the price the cart charges.
        $sale = $product->is_express_30 ? $this->saleService->applySale($product, $fcId) : null;

        $stock = $this->catalog->vendorStock($product->variants->pluck('id')->all());
        $rows = $this->variantRows($product, $stock);
        $variant = $product->is_express_30
            ? $product->variants->sortBy('price')->first()
            : $product->variants->sortBy(fn ($v) => $this->pricing->finalPrice($product, $v))->first();

        $approved = $this->catalog->approvedReviews([$product->id])->get($product->id, collect());
        $seller = $this->catalog->vendor($product->vendor_id);

        return ProductDetailResource::make((object) [
            'product' => $product,
            'variants' => $rows,
            'reviews' => $approved,
            'latest_reviews' => $this->catalog->latestReviews($product->id, 10),
            'questions' => $this->catalog->latestQuestions($product->id, 5),
            'seller' => $seller,
            'headline' => $variant ? [
                'original_price' => $this->pricing->mrp($variant),
                'sale_price' => $this->pricing->finalPrice($product, $variant, $sale['sale_price'] ?? null),
                'discount_label' => $sale ? $sale['discount_label'] : null,
                'sale_id' => $sale ? $sale['sale_id'] : null,
            ] : ['original_price' => 0, 'sale_price' => 0, 'discount_label' => null, 'sale_id' => null],
            'in_stock' => $this->anyInStock($rows),
            'return_days' => (int) ($product->return_days ?: \App\Helpers\VendorCharges::value('default_return_days')),
        ])->resolve();
    }

    // ---------------------------------------------------------------- similar (C8)

    /** @return array{found: bool, approved: bool, data: array} */
    public function similar(int $id): array
    {
        $current = $this->catalog->findActiveWithCategory($id);

        if (! $current) {
            return ['found' => false, 'data' => []];
        }

        if (! $current->category) {
            return ['found' => true, 'data' => []];
        }

        return ['found' => true, 'data' => SimilarProductResource::collection($this->catalog->similar($current))->resolve()];
    }

    public function attributes(): array
    {
        return $this->catalog->attributes()->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'slug' => \Illuminate\Support\Str::slug($a->name),
            'values' => $a->values->map(fn ($v) => ['id' => $v->id, 'value' => $v->value])->all(),
        ])->all();
    }

    // ---------------------------------------------------------------- shared

    /**
     * Each variant with the price the customer pays and its stock at the product's vendor.
     * Without a vendor a variant is in stock while it is active.
     *
     * @return array<int, array<string, mixed>>
     */
    private function variantRows(Product $product, Collection $stock): array
    {
        return $product->variants->map(function ($variant) use ($product, $stock) {
            $vp = $product->vendor_id ? $stock->get($product->vendor_id.'_'.$variant->id) : null;
            $qty = (int) ($vp->stock_qty ?? 0);
            $reserved = (int) ($vp->reserved_qty ?? 0);
            $available = $product->vendor_id ? max(0, $qty - $reserved) : 0;

            return VariantResource::make((object) [
                'variant' => $variant,
                'mrp' => $this->pricing->mrp($variant),
                'payable' => $this->pricing->finalPrice($product, $variant),
                'stock_qty' => $product->vendor_id ? $qty : 0,
                'reserved_qty' => $product->vendor_id ? $reserved : 0,
                'available_stock' => $available,
                'in_stock' => $product->vendor_id ? ($variant->status === 'active' && $available > 0) : $variant->status === 'active',
            ])->resolve();
        })->values()->all();
    }

    private function anyInStock(array $rows): bool
    {
        return collect($rows)->contains(fn ($row) => $row['in_stock']);
    }

    /** Rating summary of approved reviews (shared by list and detail resources). */
    public static function ratingSummary(Collection $reviews): array
    {
        return RatingSummary::for($reviews);
    }
}
