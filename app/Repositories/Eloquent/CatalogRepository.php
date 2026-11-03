<?php

namespace App\Repositories\Eloquent;

use App\Helpers\VendorCharges;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductQuestion;
use App\Models\ProductReview;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CatalogRepository implements CatalogRepositoryInterface
{
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Product::with(['brand', 'category', 'variants', 'images'])
            ->where('status', 'active')
            ->where('approval_status', 'approved')
            ->where('is_express_30', false);                       // a 30 minute product is sold through the quick screens only

        if (isset($filters['category_ids'])) {
            $query->whereIn('category_id', $filters['category_ids']);
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            // % and _ typed by the customer are searched for literally, not as wildcards.
            $search = addcslashes($filters['search'], '\\%_');
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
        }

        // min and max are judged on the SAME active variant, at the price the customer pays for it.
        $min = $filters['min_price'] ?? null;
        $max = $filters['max_price'] ?? null;
        if ($min !== null || $max !== null) {
            $query->whereHas('variants', function ($q) use ($min, $max) {
                $q->where('status', 'active');
                if ($min !== null) {
                    $q->whereRaw($this->payablePriceSql().' >= ?', [$min]);
                }
                if ($max !== null) {
                    $q->whereRaw($this->payablePriceSql().' <= ?', [$max]);
                }
            });
        }

        return $query->orderBy('products.id', 'asc')->paginate($perPage);
    }

    /** SQL for what a customer pays for one piece of a variant: selling price (a sale price only counts above zero) + the Taksh charge. */
    private function payablePriceSql(): string
    {
        $selling = '(CASE WHEN product_variants.sale_price IS NOT NULL AND product_variants.sale_price > 0 '
            .'THEN product_variants.sale_price ELSE product_variants.price END)';

        $band = '(CASE WHEN '.$selling.' < '.VendorCharges::value('taksh_charge_threshold')
            .' THEN '.VendorCharges::value('taksh_charge_below')
            .' ELSE '.VendorCharges::value('taksh_charge_above').' END)';

        return '('.$selling.' + COALESCE((SELECT p.taksh_charge FROM products p WHERE p.id = product_variants.product_id), '.$band.'))';
    }

    public function vendorStock(array $variantIds): Collection
    {
        if ($variantIds === []) {
            return collect();
        }

        return VendorProduct::whereIn('product_variant_id', $variantIds)->get()
            ->keyBy(fn ($vp) => $vp->vendor_id.'_'.$vp->product_variant_id);
    }

    public function approvedReviews(array $productIds): Collection
    {
        return ProductReview::whereIn('product_id', $productIds)->where('is_approved', true)->get()->groupBy('product_id');
    }

    public function findForDetail(int $id): ?Product
    {
        return Product::with([
            'brand',
            'category',
            'variants' => fn ($q) => $q->where('status', 'active')->with(['variantAttributes.attribute', 'variantAttributes.attributeValue', 'images']),
            'images' => fn ($q) => $q->orderBy('sort_order')->orderBy('is_primary', 'desc'),
        ])->where('status', 'active')->find($id);
    }

    public function latestReviews(int $productId, int $limit): Collection
    {
        return ProductReview::where('product_id', $productId)->where('is_approved', true)->orderBy('created_at', 'desc')->limit($limit)->get();
    }

    public function latestQuestions(int $productId, int $limit): Collection
    {
        return ProductQuestion::where('product_id', $productId)
            ->where('is_approved', true)
            ->with(['answers' => fn ($q) => $q->where('is_approved', true)->orderBy('created_at', 'asc')])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function vendor(?int $vendorId): ?Vendor
    {
        return $vendorId ? Vendor::find($vendorId) : null;
    }

    public function findActiveWithCategory(int $id): ?Product
    {
        $product = Product::with('category')->find($id);

        return $product && $product->status === 'active' ? $product : null;
    }

    public function similar(Product $current): Collection
    {
        $category = $current->category;
        $parentId = $category->parent_id;

        $base = Product::with([
            'variants' => fn ($q) => $q->where('status', 'active')->orderBy('price', 'asc'),
            'images' => fn ($q) => $q->orderBy('is_primary', 'desc')->orderBy('sort_order', 'asc')->limit(1),
            'reviews' => fn ($q) => $q->where('is_approved', true)->select('product_id', 'rating'),
        ])->where('status', 'active')->where('approval_status', 'approved')->where('id', '!=', $current->id);

        // Same category first; in a sub-category its siblings too.
        $ids = [$category->id];
        if ($parentId) {
            $ids = array_merge($ids, Category::where('parent_id', $parentId)->where('id', '!=', $category->id)->where('status', 'active')->pluck('id')->all());
        }

        $similar = (clone $base)->whereIn('category_id', $ids)
            ->orderByRaw('CASE WHEN category_id = ? THEN 0 ELSE 1 END', [$category->id])
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();

        if ($similar->count() < 6) {
            $exclude = $similar->pluck('id')->push($current->id)->all();
            $more = (clone $base)
                ->when($parentId, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('parent_id', $parentId)), fn ($q) => $q->where('category_id', $category->id))
                ->whereNotIn('id', $exclude)
                ->orderBy('created_at', 'desc')
                ->limit(6 - $similar->count())
                ->get();

            $similar = $similar->merge($more);
        }

        return $similar->take(6)->values();
    }

    public function attributes(): Collection
    {
        return ProductAttribute::with(['values' => fn ($q) => $q->select('id', 'product_attribute_id', 'value')->orderBy('id', 'asc')])
            ->select('id', 'name')
            ->orderBy('id', 'asc')
            ->get();
    }
}
