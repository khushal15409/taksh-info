{{-- One shop on the Shops page: logo, name with a verified tick, rating (when it has reviews), product count, categories, city and the two buttons.
     $s is an App\Support\Presenters\ShopCard shape. --}}
<article class="shop-list-card group relative bg-white rounded-3xl border border-orange-100 shadow-sm hover:shadow-card-hover transition p-4 flex flex-col">
    <span class="absolute top-4 right-4 text-gray-300" aria-hidden="true"><i data-lucide="heart" class="w-4 h-4"></i></span>
    <a href="{{ $s['url'] }}" class="flex gap-4 items-start min-w-0" aria-label="{{ $s['name'] }}">
        <div class="w-20 h-20 sm:w-24 sm:h-24 shrink-0 rounded-full overflow-hidden bg-orange-50 border border-orange-100 flex items-center justify-center">
            @if ($s['logo_url'])<img src="{{ $s['logo_url'] }}" alt="" loading="lazy" class="w-full h-full object-cover">@else<span class="text-3xl font-extrabold text-brand-600">{{ $s['initial'] }}</span>@endif
        </div>
        <div class="min-w-0 flex-1 pr-6 space-y-1">
            <h3 class="flex items-center gap-1.5 font-extrabold text-[15px] sm:text-base text-gray-900 leading-tight"><span class="truncate">{{ $s['name'] }}</span><i data-lucide="badge-check" class="w-4 h-4 text-sky-500 shrink-0"></i></h3>
            @if ($s['rating'])<div class="flex items-center gap-1 text-xs text-gray-500"><i data-lucide="star" class="w-3.5 h-3.5 text-amber-400 fill-current"></i><b class="text-gray-800">{{ number_format($s['rating']['average'], 1) }}</b><span>({{ number_format($s['rating']['total']) }} {{ $s['rating']['total'] === 1 ? 'Review' : 'Reviews' }})</span></div>@endif
            <div class="flex items-center gap-1.5 text-xs text-gray-600"><i data-lucide="package" class="w-3.5 h-3.5 text-brand-500"></i>{{ number_format($s['products']) }} {{ $s['products'] === 1 ? 'Product' : 'Products' }}</div>
        </div>
    </a>
    @if ($s['categories'])
        <div class="flex flex-wrap gap-1.5 mt-3">@foreach ($s['categories'] as $c)<span class="text-[10px] sm:text-[11px] font-semibold text-brand-700 bg-orange-50 border border-orange-100 rounded-full px-2.5 py-1 leading-none">{{ $c }}</span>@endforeach</div>
    @endif
    @if ($s['location'])<div class="flex items-center gap-1.5 text-xs text-gray-500 mt-3"><i data-lucide="map-pin" class="w-3.5 h-3.5 text-brand-500 shrink-0"></i><span class="truncate">{{ $s['location'] }}</span></div>@endif
    <div class="grid grid-cols-2 gap-2 mt-auto pt-4">
        <a href="{{ $s['url'] }}" class="text-center text-xs font-bold text-gray-800 bg-white border border-gray-200 hover:border-brand-500 hover:text-brand-600 rounded-xl py-2.5 transition">View Shop</a>
        <a href="{{ $s['url'] }}" class="text-center text-xs font-extrabold btn-saffron rounded-xl py-2.5 inline-flex items-center justify-center gap-1">Shop Now<i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a>
    </div>
</article>
