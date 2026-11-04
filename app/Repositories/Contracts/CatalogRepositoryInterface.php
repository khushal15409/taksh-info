<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/** The Standard (non 30-minute) product catalogue: list, detail, similar items and attributes. */
interface CatalogRepositoryInterface
{
    /**
     * Active, approved, non-quick products, oldest first (stable paging).
     *
     * @param  array{category_ids?: int[], search?: ?string, min_price?: ?float, max_price?: ?float}  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    /** How many quick products each category has among those the given vendors can sell now (category id => count). @return array<int, int> */
    public function quickCategoryCounts(array $vendorIds): array;

    /** vendor_product rows of these variants, keyed "<vendor_id>_<variant_id>". */
    public function vendorStock(array $variantIds): Collection;

    /** Approved reviews of these products, grouped by product id. */
    public function approvedReviews(array $productIds): Collection;

    /** An active product with brand, category, active variants (+attributes, images) and images. */
    public function findForDetail(int $id): ?Product;

    /** Latest approved reviews of a product. */
    public function latestReviews(int $productId, int $limit): Collection;

    /** Latest approved questions (with approved answers). */
    public function latestQuestions(int $productId, int $limit): Collection;

    public function vendor(?int $vendorId): ?Vendor;

    /** An active product with its category, for the similar-products lookup. */
    public function findActiveWithCategory(int $id): ?Product;

    /** Up to six similar active, approved products (same category first, then siblings / parent). */
    public function similar(Product $current): Collection;

    /** Attributes with their values, in admin order. */
    public function attributes(): Collection;
}
