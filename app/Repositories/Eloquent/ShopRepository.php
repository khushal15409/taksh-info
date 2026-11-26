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
    public function paginate(string $q, array $categoryIds, ?string $location, string $sort, int $perPage): LengthAwarePaginator
    {
        $query = $this->listed();

        if ($q !== '') {
            $like = '%'.addcslashes($q, '\\%_').'%';
            $query->where(fn ($w) => $w->where('shop_name', 'like', $like)->orWhere('vendor_name', 'like', $like));
        }

        if ($categoryIds) {
            $ids = array_values(array_unique(array_map('intval', $categoryIds)));
            $query->where(function ($w) use ($ids) {
                $w->whereExists(fn ($e) => $e->selectRaw('1')->from('products as cp')
                    ->whereColumn('cp.vendor_id', 'vendors.id')->where('cp.status', 'active')->where('cp.approval_status', 'approved')->where('cp.is_express_30', false)
                    ->whereIn('cp.category_id', $ids));
                foreach ($ids as $id) {
                    $w->orWhereRaw('FIND_IN_SET(?, REPLACE(vendors.category_id, " ", ""))', [$id]);
                }
            });
        }

        if ($location !== null && $location !== '') {
            $query->whereRaw($this->cityExpr().' = ?', [$location]);
        }

        match ($sort) {
            'name' => $query->orderByRaw('COALESCE(NULLIF(shop_name, ""), vendor_name) asc'),
            'newest' => $query->orderByDesc('vendors.id'),
            default => $query->orderByDesc('product_count')->orderBy('vendors.id'),
        };

        return $query->paginate($perPage);
    }

    public function locationOptions(): array
    {
        $rows = Vendor::query()->where('vendors.status', 'approved')
            ->selectRaw($this->cityExpr().' as city, '.$this->stateExpr().' as state')->get();

        $by = [];
        foreach ($rows as $r) {
            $city = mb_convert_case(mb_strtolower(trim((string) $r->city)), MB_CASE_TITLE);
            if ($city === '') {
                continue;
            }
            $state = mb_convert_case(mb_strtolower(trim((string) $r->state)), MB_CASE_TITLE);
            $key = mb_strtolower($city);
            $by[$key] ??= ['value' => $city, 'label' => $city.($state !== '' ? ', '.$state : ''), 'count' => 0];
            $by[$key]['count']++;
        }
        usort($by, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return array_values($by);
    }

    public function ratings(array $vendorIds): array
    {
        if ($vendorIds === []) {
            return [];
        }

        return DB::table('product_reviews')
            ->join('products', 'products.id', '=', 'product_reviews.product_id')
            ->whereIn('products.vendor_id', $vendorIds)->where('product_reviews.is_approved', true)
            ->groupBy('products.vendor_id')
            ->selectRaw('products.vendor_id as vid, COUNT(*) as total, AVG(product_reviews.rating) as average')
            ->get()->mapWithKeys(fn ($r) => [(int) $r->vid => ['average' => round((float) $r->average, 1), 'total' => (int) $r->total]])->all();
    }

    public function categoryNames(array $vendorIds, int $limit = 3): array
    {
        if ($vendorIds === []) {
            return [];
        }

        // the categories their products are in, most products first
        $fromProducts = DB::table('products')->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('products.vendor_id', $vendorIds)->where('products.status', 'active')->where('products.approval_status', 'approved')->where('products.is_express_30', false)
            ->groupBy('products.vendor_id', 'categories.id', 'categories.name')
            ->orderByRaw('COUNT(*) desc')
            ->get(['products.vendor_id as vid', 'categories.name as name']);

        $out = [];
        foreach ($fromProducts as $r) {
            $out[(int) $r->vid][] = $r->name;
        }

        // the store categories the vendor chose at registration come first
        $chosen = Vendor::query()->whereIn('id', $vendorIds)->whereNotNull('category_id')->pluck('category_id', 'id');
        $ids = $chosen->flatMap(fn ($csv) => array_filter(array_map('intval', explode(',', (string) $csv))))->unique()->values()->all();
        $names = $ids ? DB::table('categories')->whereIn('id', $ids)->pluck('name', 'id') : collect();
        foreach ($chosen as $vid => $csv) {
            $mine = collect(array_filter(array_map('intval', explode(',', (string) $csv))))->map(fn ($id) => $names[$id] ?? null)->filter()->all();
            $out[(int) $vid] = array_merge($mine, $out[(int) $vid] ?? []);
        }

        return array_map(fn ($list) => array_slice(array_values(array_unique($list)), 0, $limit), $out);
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
        $per = [];   // vendor id => set of category ids

        foreach (DB::table('products')->join('vendors', 'vendors.id', '=', 'products.vendor_id')
            ->where('vendors.status', 'approved')->where('products.status', 'active')->where('products.approval_status', 'approved')->where('products.is_express_30', false)
            ->whereNotNull('products.category_id')->distinct()->get(['products.vendor_id as vid', 'products.category_id as cid']) as $r) {
            $per[(int) $r->vid][(int) $r->cid] = true;
        }

        foreach (Vendor::query()->where('status', 'approved')->whereNotNull('category_id')->get(['id', 'category_id']) as $v) {
            foreach (array_filter(array_map('intval', explode(',', (string) $v->category_id))) as $cid) {
                $per[(int) $v->id][$cid] = true;
            }
        }

        $counts = [];
        foreach ($per as $cats) {
            foreach (array_keys($cats) as $cid) {
                $counts[$cid] = ($counts[$cid] ?? 0) + 1;
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
            )
            ->selectRaw($this->cityExpr().' as loc_city')
            ->selectRaw($this->stateExpr().' as loc_state');
    }

    /** The shop's city: the vendor record's, else the district of its pincode (taksh-admin's pincode directory). */
    private function cityExpr(): string
    {
        return 'COALESCE(NULLIF((SELECT c.name FROM cities c WHERE c.id = vendors.city_id), ""), '
            .'(SELECT p.district FROM pincodes p WHERE p.pincode = vendors.shop_pincode AND p.district IS NOT NULL ORDER BY p.id LIMIT 1))';
    }

    private function stateExpr(): string
    {
        return 'COALESCE(NULLIF((SELECT s.name FROM states s WHERE s.id = vendors.state_id), ""), '
            .'(SELECT p.statename FROM pincodes p WHERE p.pincode = vendors.shop_pincode AND p.statename IS NOT NULL ORDER BY p.id LIMIT 1))';
    }

    /** The shop's products as the standard shop lists them. */
    private function products(int $vendorId)
    {
        return Product::query()->where('products.vendor_id', $vendorId)
            ->where('products.status', 'active')->where('products.approval_status', 'approved')->where('products.is_express_30', false);
    }
}
