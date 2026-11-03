<?php

namespace App\Support\Presenters;

/**
 * One shape for every product card: {id, name, price, original_price, discount, brand, rating_summary, in_stock, out_of_stock_message,
 * image_url, variant_id}. The dashboard sections already come in this shape; the catalogue list and "similar products" are converted.
 */
final class ProductCard
{
    /** A catalogue-list item: the card shows its cheapest in-stock variant. */
    public static function fromList(array $p): array
    {
        $active = collect($p['variants'] ?? [])->filter(fn ($v) => ($v['status'] ?? null) === 'active')->values();
        $pool = $active->filter(fn ($v) => $v['in_stock'] ?? false)->values();
        $pool = $pool->isNotEmpty() ? $pool : $active;
        $v = $pool->sortBy(fn ($x) => (float) $x['sale_price'])->first();

        $price = $v ? (float) $v['sale_price'] : 0.0;
        $mrp = $v ? (float) $v['price'] : 0.0;
        $images = collect($p['images'] ?? []);

        return [
            'id' => $p['id'], 'name' => $p['name'], 'brand' => $p['brand'] ?? null, 'price' => $price, 'original_price' => $mrp,
            'variant_id' => $active->count() === 1 && $v && ($v['in_stock'] ?? false) ? $v['id'] : null,
            'discount' => self::discount($price, $mrp),
            'rating_summary' => $p['rating_summary'] ?? null, 'in_stock' => (bool) ($p['in_stock'] ?? false), 'out_of_stock_message' => $p['out_of_stock_message'] ?? null,
            'image_url' => ($images->firstWhere('is_primary', true) ?? $images->first())['image_url'] ?? null,
        ];
    }

    /** A "similar products" item. */
    public static function fromSimilar(array $s): array
    {
        $price = (float) ($s['discount_price'] ?: $s['price']);

        return [
            'id' => $s['product_id'], 'name' => $s['product_name'], 'brand' => null, 'price' => $price, 'original_price' => (float) $s['price'],
            'discount' => self::discount($price, (float) $s['price']), 'variant_id' => null,
            'rating_summary' => ['average_rating' => $s['rating'], 'total_reviews' => $s['total_reviews']],
            'in_stock' => ($s['stock_status'] ?? null) === 'in_stock', 'out_of_stock_message' => null, 'image_url' => $s['primary_image'] ?? null,
        ];
    }

    public static function discount(float $price, float $mrp): int
    {
        return $mrp > 0 && $price > 0 && $price < $mrp ? (int) round(($mrp - $price) / $mrp * 100) : 0;
    }
}
