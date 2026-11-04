{{-- <x-product-card :product="$card" /> - $card is a App\Support\Presenters\ProductCard shape. The wishlist heart and "Add to Cart" button carry the
     data attributes the cart / wishlist scripts listen for. Corner tag and footer line are derived from the product's own data. --}}
@props(['product', 'compact' => false])
@php
    $p = $product;
    $rs = $p['rating_summary'] ?? ['total_reviews' => 0];
    $href = route('product', ['id' => $p['id']]);
    $reviews = (int) ($rs['total_reviews'] ?? 0);
    $avg = (float) ($rs['average_rating'] ?? 0);
    $off = (int) ($p['discount'] ?? 0);
    $quick = ! empty($p['is_quick']);
    $reviewText = $reviews >= 1000 ? floor($reviews / 1000).'k+' : $reviews;

    if ($quick) { $tag = ['30-Min', 'tag-quick']; }
    elseif ($reviews && $avg >= 4.5) { $tag = ['Top Rated', 'tag-top']; }
    elseif ($reviews >= 10) { $tag = ['Popular', 'tag-pop']; }
    elseif ($off >= 15) { $tag = ['Best Seller', 'tag-best']; }
    else { $tag = null; }

    if ($quick) { $foot = ['zap', 'Fast Delivery', 'text-emerald-600']; }
    elseif ($reviews && $avg >= 4.5) { $foot = ['shield-check', 'Trusted Brand', 'text-emerald-600']; }
    elseif ($off > 0) { $foot = ['badge-percent', 'Great Savings', 'text-brand-500']; }
    else { $foot = ['award', 'Best Quality', 'text-brand-500']; }
@endphp
<article class="product-card pcard {{ $compact ? 'pcard-sm' : '' }} group text-gray-800">
    @if ($tag)<span class="pcard-tag {{ $tag[1] }}">{{ $tag[0] }}</span>@endif
    <button type="button" data-wish="{{ $p['id'] }}" aria-label="Wishlist" class="wish-btn {{ app(\App\Services\HeaderStateService::class)->isSaved((int) $p['id']) ? 'is-on' : '' }} pcard-heart"><i data-lucide="heart" class="w-4 h-4"></i></button>
    <a href="{{ $href }}" class="pcard-media">
        @if (! empty($p['image_url']))
            <div class="pcard-img">
                <img src="{{ $p['image_url'] }}" alt="" aria-hidden="true" loading="lazy" class="pcard-img-bg">
                <img src="{{ $p['image_url'] }}" alt="{{ $p['name'] }}" loading="lazy" class="pcard-img-fg group-hover:scale-105 transition duration-300">
            </div>
        @else
            <div class="pcard-img flex items-center justify-center text-5xl">🛍️</div>
        @endif
    </a>
    <div class="px-4 pb-4 pt-2 flex-1 flex flex-col gap-1.5">
        @if (! empty($p['brand']))<span class="text-[10px] font-bold text-gray-400 uppercase tracking-wide truncate">{{ $p['brand']['name'] }}</span>@endif
        <a href="{{ $href }}" class="font-bold text-[13px] leading-snug line-clamp-2 hover:text-brand-600 min-h-[2.4rem]">{{ $p['name'] }}</a>
        <span class="flex items-center gap-1 text-[11px] font-semibold text-gray-500"><i data-lucide="star" class="w-3.5 h-3.5 fill-amber-400 text-amber-400"></i><b class="text-amber-500">{{ $reviews ? $p['rating_summary']['average_rating'] : '3.5' }}</b>@if ($reviews) ({{ $reviewText }} Reviews)@endif</span>
        <div class="flex items-center flex-wrap gap-x-2 gap-y-1 mt-auto pt-1">
            <span class="font-extrabold text-lg text-gray-900">@rupee($p['price'])</span>
            @if ($off > 0)<span class="text-xs text-gray-400 line-through">@rupee($p['original_price'])</span><span class="pcard-off">{{ $off }}% OFF</span>@endif
        </div>
        @if (! empty($p['in_stock']) && ! empty($p['variant_id']))
            <button type="button" data-add="{{ $p['variant_id'] }}" data-delivery="{{ $quick ? '30_min' : 'normal' }}" class="pcard-add mt-1"><i data-lucide="shopping-cart" class="w-4 h-4"></i> Add to Cart</button>
        @elseif (! empty($p['in_stock']))
            <a href="{{ $href }}" class="pcard-add mt-1">View</a>
        @else
            <span class="w-full text-center bg-gray-100 text-gray-400 font-bold text-xs py-2.5 rounded-xl mt-1">{{ $p['out_of_stock_message'] ?? 'Out of stock' }}</span>
        @endif
        @if (isset($slot) && trim((string) $slot) !== '')<div class="mt-1">{{ $slot }}</div>@else<div class="pcard-foot"><i data-lucide="{{ $foot[0] }}" class="w-3.5 h-3.5 {{ $foot[2] }}"></i>{{ $foot[1] }}</div>@endif
    </div>
</article>
