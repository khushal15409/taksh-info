{{-- <x-product-card :product="$card" /> - $card is a App\Support\Presenters\ProductCard shape. The wishlist heart and "Add" button carry the
     data attributes the cart / wishlist scripts listen for. --}}
@props(['product'])
@php
    $p = $product;
    $rs = $p['rating_summary'] ?? ['total_reviews' => 0];
    $href = route('product', ['id' => $p['id']]);
    $btn = 'w-full bg-orange-50 hover:bg-brand-500 hover:text-white text-brand-600 border border-brand-500/40 font-bold text-xs py-2 rounded-xl transition';
@endphp
<article class="product-card group bg-white rounded-3xl border border-orange-100 hover:border-brand-500/40 hover:shadow-card-hover transition flex flex-col overflow-hidden text-gray-800">
    <div class="relative">
        @if (($p['discount'] ?? 0) > 0)<span class="absolute top-3 left-3 z-10 bg-brand-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $p['discount'] }}% OFF</span>@endif
        <button type="button" data-wish="{{ $p['id'] }}" aria-label="Wishlist" class="wish-btn {{ app(\App\Services\HeaderStateService::class)->isSaved((int) $p['id']) ? 'is-on' : '' }} absolute top-3 right-3 z-10 w-8 h-8 rounded-full bg-white/90 shadow flex items-center justify-center text-gray-400 hover:text-red-500 transition"><i data-lucide="heart" class="w-4 h-4"></i></button>
        <a href="{{ $href }}" class="block bg-gradient-to-b from-orange-50/60 to-white p-3">
            @if (! empty($p['image_url']))
                <img src="{{ $p['image_url'] }}" alt="{{ $p['name'] }}" loading="lazy" class="w-full aspect-square object-contain group-hover:scale-105 transition duration-300">
            @else
                <div class="w-full aspect-square flex items-center justify-center text-5xl bg-orange-50/60">🛍️</div>
            @endif
        </a>
    </div>
    <div class="p-4 pt-1 flex-1 flex flex-col gap-2">
        @if (! empty($p['brand']))<span class="text-[10px] font-bold text-gray-400 uppercase tracking-wide truncate">{{ $p['brand']['name'] }}</span>@endif
        <a href="{{ $href }}" class="font-bold text-[13px] leading-snug line-clamp-2 hover:text-brand-600 min-h-[2.4rem]">{{ $p['name'] }}</a>
        @if (! empty($rs['total_reviews']))
            <span class="self-start flex items-center gap-1 bg-emerald-50 text-emerald-700 font-bold px-1.5 py-0.5 rounded text-[11px]"><i data-lucide="star" class="w-3 h-3 fill-current"></i>{{ $rs['average_rating'] }} <span class="font-medium text-emerald-600/70">({{ $rs['total_reviews'] }})</span></span>
        @endif
        <div class="flex items-end gap-2 mt-auto pt-1">
            <span class="font-extrabold text-base text-gray-900">@rupee($p['price'])</span>
            @if (($p['discount'] ?? 0) > 0)<span class="text-xs text-gray-400 line-through pb-0.5">@rupee($p['original_price'])</span>@endif
        </div>
        @if (! empty($p['in_stock']) && ! empty($p['variant_id']))
            <button type="button" data-add="{{ $p['variant_id'] }}" class="{{ $btn }} flex items-center justify-center gap-1.5"><i data-lucide="plus" class="w-3.5 h-3.5"></i> Add</button>
        @elseif (! empty($p['in_stock']))
            <a href="{{ $href }}" class="{{ $btn }} text-center">View</a>
        @else
            <span class="w-full text-center bg-gray-100 text-gray-400 font-bold text-xs py-2 rounded-xl">{{ $p['out_of_stock_message'] ?? 'Out of stock' }}</span>
        @endif
    </div>
</article>
