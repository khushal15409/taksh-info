{{-- One shop tile (round logo, name with a verified tick, product count). $s is an App\Support\Presenters\ShopCard shape. $rail = fixed width for a horizontal strip. --}}
@php($rail = $rail ?? false)
<a href="{{ $s['url'] }}" class="shop-card group relative block bg-white rounded-3xl border border-orange-100 shadow-sm hover:shadow-card-hover hover:-translate-y-0.5 transition p-4 text-center {{ $rail ? 'flex-none w-[150px] sm:w-[170px] snap-start' : '' }}">
    <span class="absolute top-3 right-3 text-gray-300"><i data-lucide="heart" class="w-4 h-4"></i></span>
    <div class="w-20 h-20 sm:w-24 sm:h-24 mx-auto rounded-full overflow-hidden bg-orange-50 border border-orange-100 flex items-center justify-center mb-3 group-hover:scale-105 transition">
        @if ($s['logo_url'])<img src="{{ $s['logo_url'] }}" alt="{{ $s['name'] }}" loading="lazy" class="w-full h-full object-cover">@else<span class="text-3xl font-extrabold text-brand-600">{{ $s['initial'] }}</span>@endif
    </div>
    <div class="flex items-center justify-center gap-1 font-bold text-sm text-gray-900"><span class="truncate">{{ $s['name'] }}</span><i data-lucide="badge-check" class="w-4 h-4 text-sky-500 shrink-0"></i></div>
    <div class="text-xs text-gray-500 mt-1">{{ number_format($s['products']) }} {{ $s['products'] === 1 ? 'Product' : 'Products' }}</div>
</a>
