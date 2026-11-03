<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\VendorProduct;
use App\Repositories\Contracts\ProductSearchRepositoryInterface;
use Illuminate\Support\Collection;

class ProductSearchRepository implements ProductSearchRepositoryInterface
{
    public function match(string $keyword, ?int $vendorId, int $limit, ?string $deliveryType): array
    {
        $terms = $this->searchTerms($keyword);
        // % and _ typed by the customer must match themselves, not act as LIKE wildcards.
        $like = '%'.addcslashes($keyword, '\\%_').'%';

        $query = Product::query()
            ->select(['products.id', 'products.name', 'products.status', 'products.vendor_id', 'brands.name as brand_name', 'categories.name as category_name'])
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('products.status', 'active');

        if ($vendorId !== null) {
            $query->where('products.vendor_id', $vendorId);
        }

        // Keep standard and quick products apart when the caller says which one it is browsing.
        if ($deliveryType !== null && $deliveryType !== '') {
            $query->where('products.is_express_30', $deliveryType === '30_min');
        }

        $query->where(function ($q) use ($terms, $like) {
            if ($terms !== '') {
                $q->whereRaw('MATCH(products.name, products.keywords) AGAINST(? IN BOOLEAN MODE)', [$terms])
                    ->orWhere('products.name', 'LIKE', $like);
            } else {
                // nothing left for FULLTEXT after cleaning the keyword ("-", "*"): LIKE only
                $q->where('products.name', 'LIKE', $like);
            }

            $q->orWhere('brands.name', 'LIKE', $like)->orWhere('categories.name', 'LIKE', $like);
        });

        $score = $terms !== '' ? 'IFNULL(MATCH(products.name, products.keywords) AGAINST(? IN BOOLEAN MODE), 0)' : '0';
        $query->selectRaw(
            "GREATEST({$score}, CASE WHEN products.name LIKE ? THEN 0.5 ELSE 0 END, CASE WHEN brands.name LIKE ? THEN 0.3 ELSE 0 END, CASE WHEN categories.name LIKE ? THEN 0.2 ELSE 0 END) as relevance",
            array_merge($terms !== '' ? [$terms] : [], [$like, $like, $like])
        );

        return $query->orderBy('relevance', 'desc')->orderBy('products.name', 'asc')->limit($limit)->get()
            ->map(fn ($p) => ['id' => $p->id, 'brand_name' => $p->brand_name])->values()->all();
    }

    /** "milk 1l" -> "+milk* +1l*": only letters, marks and digits are searchable; everything else separates words. */
    private function searchTerms(string $keyword): string
    {
        $words = preg_split('/[^\p{L}\p{M}\p{N}]+/u', $keyword, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(fn ($w) => '+'.$w.'*', $words));
    }

    public function productsWithRelations(array $productIds): Collection
    {
        return Product::with([
            'variants' => fn ($q) => $q->where('status', 'active')->orderBy('price', 'asc'),
            'images' => fn ($q) => $q->orderBy('is_primary', 'desc')->orderBy('sort_order', 'asc'),
        ])->whereIn('id', $productIds)->get()->keyBy('id');
    }

    public function ratings(array $productIds): Collection
    {
        return ProductReview::query()
            ->where('is_approved', true)
            ->whereIn('product_id', $productIds)
            ->selectRaw('product_id, AVG(rating) as average, COUNT(*) as total')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');
    }

    public function vendorHasFreeStock(int $vendorId, array $variantIds): bool
    {
        return VendorProduct::where('vendor_id', $vendorId)
            ->whereIn('product_variant_id', $variantIds)
            ->whereRaw('(stock_qty - reserved_qty) > 0')
            ->exists();
    }
}
