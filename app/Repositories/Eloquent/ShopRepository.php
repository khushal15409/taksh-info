<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use App\Repositories\Contracts\ShopRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShopRepository implements ShopRepositoryInterface
{
    public function paginate(string $q, array $categoryIds, string $sort, int $perPage): LengthAwarePaginator
    {
        $query = $this->listed();

        if ($q !== '') {
            $like = '%'.addcslashes($q, '\\%_').'%';
            $query->where(fn ($w) => $w->where('shop_name', 'like', $like)->orWhere('vendor_name', 'like', $like));
        }

        if ($categoryIds) {
            $query->where(function ($w) use ($categoryIds) {
                foreach ($categoryIds as $id) {
                    $w->orWhereRaw('FIND_IN_SET(?, REPLACE(vendors.category_id, " ", ""))', [(int) $id]);
                }
            });
        }

        match ($sort) {
            'name' => $query->orderByRaw('COALESCE(NULLIF(shop_name, ""), vendor_name) asc'),
            'newest' => $query->orderByDesc('vendors.id'),
            default => $query->orderByDesc('product_count')->orderBy('vendors.id'),
        };

        return $query->paginate($perPage);
    }

    public function featured(int $limit): Collection
    {
        return $this->listed()->orderByDesc('product_count')->orderBy('vendors.id')->limit($limit)->get();
    }

    public function find(int $id): ?Vendor
    {
        return $this->listed()->whereKey($id)->first();
    }

    public function total(): int
    {
        return Vendor::query()->where('status', 'approved')->count();
    }

    public function categoryShopCounts(): array
    {
        $counts = [];
        foreach (Vendor::query()->where('status', 'approved')->whereNotNull('category_id')->pluck('category_id') as $csv) {
            foreach (array_unique(array_filter(array_map('intval', explode(',', (string) $csv)))) as $id) {
                $counts[$id] = ($counts[$id] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function productCategoryCounts(int $vendorId): array
    {
        return $this->products($vendorId)->selectRaw('products.category_id, COUNT(*) as total')->groupBy('products.category_id')
            ->pluck('total', 'category_id')->map(fn ($n) => (int) $n)->all();
    }

    public function categoryProductImages(int $vendorId): array
    {
        return ProductImage::query()
            ->join('products', 'products.id', '=', 'product_images.product_id')
            ->where('products.vendor_id', $vendorId)->where('products.status', 'active')->where('products.approval_status', 'approved')->where('products.is_express_30', false)
            ->select('product_images.*', 'products.category_id as shop_category_id')
            ->orderByDesc('product_images.is_primary')->orderBy('product_images.sort_order')
            ->get()->unique('shop_category_id')
            ->mapWithKeys(fn ($img) => [(int) $img->shop_category_id => $img->image_url])->filter()->all();
    }

    public function productCount(int $vendorId): int
    {
        return $this->products($vendorId)->count();
    }

    public function rating(int $vendorId): array
    {
        $row = DB::table('product_reviews')
            ->join('products', 'products.id', '=', 'product_reviews.product_id')
            ->where('products.vendor_id', $vendorId)
            ->where('product_reviews.is_approved', true)
            ->selectRaw('COUNT(*) as total, AVG(product_reviews.rating) as average')
            ->first();

        return ['average' => round((float) ($row->average ?? 0), 1), 'total' => (int) ($row->total ?? 0)];
    }

    /** Approved shops, each with the number of standard products on sale. */
    private function listed()
    {
        return Vendor::query()
            ->where('vendors.status', 'approved')
            ->select('vendors.*')
            ->selectSub(
                DB::table('products')->selectRaw('COUNT(*)')->whereColumn('products.vendor_id', 'vendors.id')
                    ->where('products.status', 'active')->where('products.approval_status', 'approved')->where('products.is_express_30', false),
                'product_count'
            );
    }

    /** The shop's products as the standard shop lists them. */
    private function products(int $vendorId)
    {
        return Product::query()->where('products.vendor_id', $vendorId)
            ->where('products.status', 'active')->where('products.approval_status', 'approved')->where('products.is_express_30', false);
    }
}
