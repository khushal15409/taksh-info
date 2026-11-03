<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\ProductSearchRepositoryInterface;
use App\Repositories\Contracts\RecentSearchRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Search as taksh-admin does it: which products match is cached 5-10 minutes, price and stock are always read fresh. */
class ProductSearchService
{
    private const CACHE_PREFIX = 'product_search:v2:';

    public function __construct(
        private readonly ProductSearchRepositoryInterface $search,
        private readonly RecentSearchRepositoryInterface $recent,
        private readonly PricingService $pricing,
    ) {}

    /** @return array{status: bool, keyword: string, count: int, data: array<int, array<string, mixed>>} */
    public function search(string $keyword, ?int $vendorId = null, int $limit = 10, ?string $deliveryType = null, ?User $user = null): array
    {
        $matches = $this->matches($keyword, $vendorId, $limit, $deliveryType);
        $rows = $this->format($matches);

        if ($user) {
            $this->recent->record($user->id, $keyword);
        }

        return ['status' => true, 'keyword' => $keyword, 'count' => count($rows), 'data' => $rows];
    }

    /** @return array<int, array{id: int, brand_name: ?string}> */
    private function matches(string $keyword, ?int $vendorId, int $limit, ?string $deliveryType): array
    {
        $key = self::CACHE_PREFIX.md5(Str::lower(trim($keyword)))
            .($vendorId !== null ? ':vendor:'.$vendorId : '')
            .($deliveryType ? ':dt:'.($deliveryType === '30_min' ? 'express' : 'standard') : '')
            .':limit:'.$limit;

        try {
            $cached = Cache::get($key);
            if ($cached !== null) {
                return $cached;
            }
        } catch (\Throwable $e) {
            Log::warning('Cache unavailable, proceeding without cache', ['error' => $e->getMessage()]);
        }

        $matches = $this->search->match($keyword, $vendorId, $limit, $deliveryType);

        try {
            Cache::put($key, $matches, now()->addMinutes(random_int(5, 10)));
        } catch (\Throwable $e) {
            Log::warning('Unable to cache search results', ['error' => $e->getMessage()]);
        }

        return $matches;
    }

    private function format(array $matches): array
    {
        $ids = array_column($matches, 'id');
        $products = $this->search->productsWithRelations($ids);
        $ratings = $this->search->ratings($products->keys()->all());
        $rows = [];

        foreach ($matches as $match) {
            $product = $products->get($match['id']);
            if (! $product) {
                continue;
            }

            // the active variant that is cheapest for the customer; quick products use the lowest MRP (already first)
            $variant = $product->is_express_30
                ? $product->variants->first()
                : $product->variants->sortBy(fn ($v) => $this->pricing->finalPrice($product, $v))->first();

            if (! $variant) {
                continue;   // skip products without active variants
            }

            $rating = $ratings->get($product->id);

            $rows[] = [
                'id' => $product->id,
                'name' => $product->name,
                'brand' => $match['brand_name'],
                'price' => (float) $this->pricing->finalPrice($product, $variant),
                'mrp' => (float) max($this->pricing->mrp($variant), 0),
                'image' => $product->images->first()?->image_url,
                'in_stock' => $this->inStock($product),
                'is_express_30' => (bool) $product->is_express_30,
                'average_rating' => $rating ? round((float) $rating->average, 1) : 0,
                'total_reviews' => $rating ? (int) $rating->total : 0,
            ];
        }

        return $rows;
    }

    /** Some active variant has free stock at the product's vendor; with no vendor, an active variant is enough. */
    private function inStock($product): bool
    {
        return $product->vendor_id
            ? $this->search->vendorHasFreeStock($product->vendor_id, $product->variants->pluck('id')->all())
            : $product->variants->isNotEmpty();
    }
}
