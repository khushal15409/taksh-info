<?php

namespace App\Support\Presenters;

use App\Support\UrlKey;
use App\Models\Vendor;
use App\Services\MediaUrlService;

/** One shape for a shop on the home strip, the shops page and a shop's own page: {id, name, logo_url, cover_url, initial, phone, products, url}. */
final class ShopCard
{
    public static function from(Vendor $v): array
    {
        $media = app(MediaUrlService::class);
        $images = array_values(array_filter((array) $v->shop_images));
        $name = trim((string) ($v->shop_name ?: $v->vendor_name)) ?: 'Shop';

        return [
            'id' => $v->id,
            'name' => $name,
            'initial' => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($name, 0, 1)),
            'logo_url' => isset($images[0]) ? $media->url($images[0]) : null,
            'cover_url' => isset($images[1]) ? $media->url($images[1]) : (isset($images[0]) ? $media->url($images[0]) : null),
            'phone' => preg_replace('/[^0-9+]/', '', (string) $v->mobile_number) ?: null,
            'products' => (int) ($v->product_count ?? 0),
            'url' => route('shop', ['id' => UrlKey::enabled() ? UrlKey::slug('shop', (int) $v->id, $name) : $v->id]),
        ];
    }
}
