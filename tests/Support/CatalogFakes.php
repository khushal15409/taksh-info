<?php

namespace Tests\Support;

use App\Models\Category;
use App\Models\Pincode;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Vendor;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\DeliveryCheckRepositoryInterface;
use App\Repositories\Contracts\ProductSearchRepositoryInterface;
use App\Repositories\Contracts\RecentSearchRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\PresenceVerifierInterface;

class FakeCategoryRepository implements CategoryRepositoryInterface
{
    public array $treeCalls = [];

    public Collection $tree;

    public Collection $parentRows;

    public ?Category $parent = null;

    public Collection $childRows;

    public array $standardIds = [];

    public function __construct()
    {
        $this->tree = collect();
        $this->parentRows = collect();
        $this->childRows = collect();
    }

    public function activeTree(?string $type = null, bool $untypedOnly = false): Collection
    {
        $this->treeCalls[] = [$type, $untypedOnly];

        return $this->tree;
    }

    public function parents(?string $type = null): Collection
    {
        return $this->parentRows;
    }

    public function findParent(int $id, ?string $type = null): ?Category
    {
        return $this->parent && $this->parent->id === $id ? $this->parent : null;
    }

    public function children(int $parentId): Collection
    {
        return $this->childRows;
    }

    public function standardIdsWithChildren(int $categoryId): array
    {
        return $this->standardIds[$categoryId] ?? [$categoryId];
    }

    public function idsWithChildren(int $categoryId, string $type): array
    {
        return $this->standardIdsWithChildren($categoryId);
    }
}

class FakeCatalogRepository implements CatalogRepositoryInterface
{
    public Collection $products;

    public array $lastFilters = [];

    public int $lastPerPage = 0;

    public Collection $stock;

    public Collection $reviews;

    public ?Product $detail = null;

    public Collection $latestReviews;

    public Collection $questions;

    public ?Vendor $vendor = null;

    public ?Product $active = null;

    public Collection $similarRows;

    public Collection $attributeRows;

    public function __construct()
    {
        $this->products = collect();
        $this->stock = collect();
        $this->reviews = collect();
        $this->latestReviews = collect();
        $this->questions = collect();
        $this->similarRows = collect();
        $this->attributeRows = collect();
    }

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $this->lastFilters = $filters;
        $this->lastPerPage = $perPage;

        return new Paginator($this->products->values(), $this->products->count(), $perPage, 1, ['path' => 'http://localhost/ecommerce/products']);
    }

    /** @var array<int, int> */
    public array $quickCounts = [];

    public function quickCategoryCounts(array $vendorIds): array
    {
        return $vendorIds ? $this->quickCounts : [];
    }

    public function vendorStock(array $variantIds): Collection
    {
        return $this->stock;
    }

    public function approvedReviews(array $productIds): Collection
    {
        return $this->reviews;
    }

    public function findForDetail(int $id): ?Product
    {
        return $this->detail && $this->detail->id === $id ? $this->detail : null;
    }

    public function latestReviews(int $productId, int $limit): Collection
    {
        return $this->latestReviews;
    }

    public function latestQuestions(int $productId, int $limit): Collection
    {
        return $this->questions;
    }

    public function vendor(?int $vendorId): ?Vendor
    {
        return $this->vendor;
    }

    public function findActiveWithCategory(int $id): ?Product
    {
        return $this->active && $this->active->id === $id ? $this->active : null;
    }

    public function similar(Product $current): Collection
    {
        return $this->similarRows;
    }

    public function attributes(): Collection
    {
        return $this->attributeRows;
    }
}

class FakeDeliveryCheckRepository implements DeliveryCheckRepositoryInterface
{
    public ?Product $product = null;

    public ?Vendor $vendor = null;

    public ?Pincode $pincode = null;

    public bool $stock = true;

    public array $stockArgs = [];

    public function product(int $id): ?Product
    {
        return $this->product && $this->product->id === $id ? $this->product : null;
    }

    public function approvedVendor(int $vendorId): ?Vendor
    {
        return $this->vendor;
    }

    public function activePincode(string $pincode): ?Pincode
    {
        return $this->pincode;
    }

    public function vendorHasStock(Product $product, Vendor $vendor, ?int $variantId, int $qty): bool
    {
        $this->stockArgs = [$variantId, $qty];

        return $this->stock;
    }
}

/** A vendor whose service area can be switched. */
class FakeVendor extends Vendor
{
    public bool $serves = true;

    public function servesPincode(?string $pincode): bool
    {
        return $this->serves;
    }
}

class FakeProductSearchRepository implements ProductSearchRepositoryInterface
{
    public array $matches = [];

    public int $matchCalls = 0;

    public Collection $products;

    public Collection $ratingRows;

    public bool $freeStock = true;

    public function __construct()
    {
        $this->products = collect();
        $this->ratingRows = collect();
    }

    public function match(string $keyword, ?int $vendorId, int $limit, ?string $deliveryType): array
    {
        $this->matchCalls++;

        return $this->matches;
    }

    public function productsWithRelations(array $productIds): Collection
    {
        return $this->products;
    }

    public function ratings(array $productIds): Collection
    {
        return $this->ratingRows;
    }

    public function vendorHasFreeStock(int $vendorId, array $variantIds): bool
    {
        return $this->freeStock;
    }
}

class FakeRecentSearchRepository implements RecentSearchRepositoryInterface
{
    public array $list = [];

    public array $recorded = [];

    public function keywords(int $userId): array
    {
        return $this->list;
    }

    public function record(int $userId, string $keyword): void
    {
        $this->recorded[] = [$userId, $keyword];
    }
}

class FakeReviewRepository implements ReviewRepositoryInterface
{
    public bool $productExists = true;

    public bool $delivered = true;

    public ?ProductReview $existing = null;

    public array $saved = [];

    public array $summary = ['average_rating' => 4.0, 'total_reviews' => 3];

    public Collection $pending;

    public Collection $productsById;

    public function __construct()
    {
        $this->pending = collect();
        $this->productsById = collect();
    }

    public function productExists(int $productId): bool
    {
        return $this->productExists;
    }

    public function hasDelivered(int $userId, int $productId): bool
    {
        return $this->delivered;
    }

    public function findByUser(int $userId, int $productId): ?ProductReview
    {
        return $this->existing;
    }

    public function save(int $userId, int $productId, array $attributes): ProductReview
    {
        $this->saved = [$userId, $productId, $attributes];

        return (new ProductReview)->forceFill($attributes + ['id' => 9, 'user_id' => $userId, 'product_id' => $productId, 'created_at' => now()]);
    }

    public function summary(int $productId): array
    {
        return $this->summary;
    }

    public function pendingRows(int $userId, int $withinDays, int $limit): Collection
    {
        return $this->pending;
    }

    public function productsWithImages(array $productIds): Collection
    {
        return $this->productsById;
    }
}

/** `exists:` rules pass (the row is assumed to be there). */
class FakeExistsVerifier implements PresenceVerifierInterface
{
    public function getCount($collection, $column, $value, $excludeId = null, $idColumn = null, array $extra = []): int
    {
        return 1;
    }

    public function getMultiCount($collection, $column, array $values, array $extra = []): int
    {
        return count($values);
    }
}
