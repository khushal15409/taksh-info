@extends('layouts.app')

@section('title', $shop['name'].' | Shops | TakshAllInOne')
@section('page', 'shops')

@php
    $filtered = $picked || $min || $max;
    $self = route('shop', ['id' => $shop['id']]);
    $keep = array_filter(['q' => $q ?: null]);   // a tab / "Clear All" keeps what was typed in the search box
@endphp

@section('content')
    <nav class="text-[11px] text-gray-500 mb-3" aria-label="Breadcrumb"><a href="{{ route('home') }}" class="hover:text-brand-600">Home</a> <span class="mx-1">›</span> <a href="{{ route('shops') }}" class="hover:text-brand-600">Shops</a> <span class="mx-1">›</span> <span class="font-bold text-brand-600">{{ $shop['name'] }}</span></nav>

    {{-- Banner: dark tone on the left, the photo fading in from the right; logo card, name, categories, rating / products, Share --}}
    <section class="relative overflow-hidden rounded-[28px] shadow-xl mb-4" style="background: {{ $tone }}">
        <div class="absolute inset-y-0 right-0 w-full md:w-[54%]">
            <img src="{{ $banner }}" alt="" class="w-full h-full object-cover" style="-webkit-mask-image: linear-gradient(to right, transparent 0%, #000 55%); mask-image: linear-gradient(to right, transparent 0%, #000 55%);">
        </div>
        <div class="absolute inset-0 md:hidden" style="background: linear-gradient(90deg, {{ $tone }} 30%, transparent)"></div>
        <svg class="absolute inset-y-0 left-[41%] h-full w-24 hidden md:block" viewBox="0 0 100 300" preserveAspectRatio="none" aria-hidden="true"><path d="M70 0 C 20 90, 20 210, 70 300" fill="none" stroke="#FF8A1F" stroke-width="5" stroke-linecap="round" opacity=".9"/></svg>
        <div class="relative z-10 flex flex-wrap items-center gap-x-6 gap-y-4 px-5 sm:px-8 py-6 sm:py-8 min-h-[220px]">
            <div class="w-28 h-28 sm:w-36 sm:h-36 rounded-[28px] bg-white shadow-2xl flex items-center justify-center overflow-hidden shrink-0">
                @if ($shop['logo_url'])<img src="{{ $shop['logo_url'] }}" alt="{{ $shop['name'] }}" class="w-full h-full object-cover">
                @else<span class="relative text-brand-500"><i data-lucide="store" class="w-20 h-20 sm:w-24 sm:h-24"></i><b class="absolute inset-x-0 bottom-2 text-center text-2xl sm:text-3xl text-brand-600 font-extrabold leading-none">{{ $shop['initial'] }}</b></span>@endif
            </div>
            <div class="min-w-0 basis-72 flex-1 max-w-xl text-white">
                <h1 class="flex items-center gap-2 font-festive font-extrabold text-3xl sm:text-5xl leading-tight"><span class="truncate">{{ $shop['name'] }}</span><i data-lucide="badge-check" class="w-7 h-7 sm:w-9 sm:h-9 text-sky-400 shrink-0"></i></h1>
                @if ($categories)<div class="flex flex-wrap items-center text-xs sm:text-sm mt-2 text-white/90">@foreach (array_slice($categories, 0, 3) as $c)<span class="{{ $loop->first ? 'pr-3' : 'px-3 border-l border-white/40' }}">{{ $c['name'] }}</span>@endforeach</div>@endif
                <div class="flex flex-wrap items-center gap-2.5 mt-4 text-xs sm:text-sm">
                    @if ($rating['total'])<span class="inline-flex items-center gap-2 rounded-xl bg-black/25 ring-1 ring-white/15 backdrop-blur px-3.5 py-2"><i data-lucide="star" class="w-4 h-4 text-amber-400 fill-current"></i><b>{{ number_format($rating['average'], 1) }}</b><span class="text-white/80">({{ number_format($rating['total']) }} Reviews)</span></span>@endif
                    <span class="inline-flex items-center gap-2 rounded-xl bg-black/25 ring-1 ring-white/15 backdrop-blur px-3.5 py-2"><i data-lucide="shopping-bag" class="w-4 h-4 text-amber-400"></i><b>{{ number_format($total) }}</b>{{ $total === 1 ? 'Product' : 'Products' }}</span>
                </div>
                <div class="flex flex-wrap items-center gap-3 mt-4">
                    <button type="button" id="shop-share" data-url="{{ $shareUrl }}" data-title="{{ $shop['name'] }}" class="inline-flex items-center gap-2 rounded-xl bg-white hover:bg-orange-50 text-gray-900 font-bold text-sm px-5 py-3 shadow-lg transition active:scale-95"><i data-lucide="share-2" class="w-4 h-4 text-brand-500"></i><span data-label>Share</span></button>
                </div>
            </div>
        </div>
    </section>

    {{-- Trust strip --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 bg-white border border-orange-100 rounded-3xl shadow-sm mb-4 lg:divide-x divide-orange-100">
        @foreach ([['shield-check', '100% Genuine Products', 'Original & quality assured'], ['award', 'Brand Authorized Store', 'Trusted & verified seller'], ['truck', 'Fast & Reliable Delivery', 'Across serviceable locations'], ['refresh-cw', 'Easy Returns & Support', 'Hassle-free experience']] as [$icon, $title, $sub])
            <div class="flex items-center justify-center gap-3 px-4 py-4">
                <i data-lucide="{{ $icon }}" class="w-9 h-9 text-brand-500 shrink-0" stroke-width="1.5"></i>
                <div class="min-w-0"><div class="text-xs sm:text-sm font-extrabold text-gray-900 leading-tight">{{ $title }}</div><div class="text-[11px] text-gray-500 leading-tight mt-0.5">{{ $sub }}</div></div>
            </div>
        @endforeach
    </div>

    {{-- Category tiles with pictures and counts --}}
    <div class="mb-6 overflow-x-auto no-scrollbar -mx-1 px-1 py-1">
        <div class="flex min-w-max gap-3">
            <a href="{{ route('shop', ['id' => $shop['id']] + $keep) }}" class="w-[122px] sm:w-[138px] shrink-0 rounded-2xl border-2 px-2 py-3 text-center transition {{ ! $picked ? 'border-brand-500 bg-orange-50 shadow-md' : 'border-orange-100 bg-white hover:border-brand-500' }}">
                <span class="mx-auto w-12 h-12 flex items-center justify-center text-brand-500"><i data-lucide="layout-grid" class="w-10 h-10" stroke-width="1.6"></i></span>
                <span class="block mt-1.5 text-xs font-extrabold text-gray-900 leading-tight">All Products</span>
                <span class="block text-[11px] text-gray-500">({{ number_format($total) }})</span>
            </a>
            @foreach ($categories as $c)
                <a href="{{ route('shop', ['id' => $shop['id'], 'category' => [$c['id']]] + $keep) }}" class="w-[122px] sm:w-[138px] shrink-0 rounded-2xl border-2 px-2 py-3 text-center transition {{ $picked === [$c['id']] ? 'border-brand-500 bg-orange-50 shadow-md' : 'border-orange-100 bg-white hover:border-brand-500' }}">
                    <span class="mx-auto w-12 h-12 flex items-center justify-center">@if ($c['image_url'])<img src="{{ $c['image_url'] }}" alt="" loading="lazy" class="max-w-full max-h-full object-contain">@else<i data-lucide="package" class="w-9 h-9 text-brand-500" stroke-width="1.6"></i>@endif</span>
                    <span class="block mt-1.5 text-xs font-bold text-gray-900 leading-tight line-clamp-2 min-h-[2rem]">{{ $c['name'] }}</span>
                    <span class="block text-[11px] text-gray-500">({{ number_format($c['count']) }})</span>
                </a>
            @endforeach
        </div>
    </div>

    <div class="flex justify-end mb-3 lg:hidden"><button type="button" id="filter-toggle" class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-4 py-2 text-xs font-bold"><i data-lucide="sliders-horizontal" class="w-4 h-4"></i>Filters</button></div>

    <div class="grid grid-cols-1 lg:grid-cols-[270px_minmax(0,1fr)] gap-6 lg:gap-8">
        <form id="filters" method="POST" action="{{ $self }}" class="hidden lg:block bg-white p-5 rounded-3xl border border-orange-100 shadow-sm h-fit lg:sticky lg:top-40">
            @csrf
            @if ($q !== '')<input type="hidden" name="q" value="{{ $q }}">@endif
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="font-extrabold text-base text-gray-900 flex items-center gap-2"><i data-lucide="filter" class="w-4 h-4 text-brand-500"></i>Filters</h3>
                @if ($filtered)<a href="{{ route('shop', ['id' => $shop['id']] + $keep) }}" class="text-xs font-bold text-brand-600 hover:underline">Clear All</a>@endif
            </div>

            <details open class="py-4 border-b border-gray-100 group">
                <summary class="flex items-center justify-between cursor-pointer list-none font-extrabold text-sm text-gray-900">Category<i data-lucide="chevron-up" class="w-4 h-4 text-gray-500 transition group-open:rotate-0 rotate-180"></i></summary>
                <div class="space-y-2.5 text-xs mt-3">
                    <label class="flex items-center gap-2.5 font-semibold text-gray-800"><input type="checkbox" data-all-products class="accent-brand-500 w-4 h-4" @checked(! $picked)><span>All Products</span><span class="ml-auto text-gray-400">({{ number_format($total) }})</span></label>
                    @foreach ($categories as $c)
                        <label class="flex items-center gap-2.5 cursor-pointer text-gray-700"><input type="checkbox" name="category[]" value="{{ $c['id'] }}" class="accent-brand-500 w-4 h-4" @checked(in_array($c['id'], $picked, true))><span>{{ $c['name'] }}</span><span class="ml-auto text-gray-400">({{ number_format($c['count']) }})</span></label>
                    @endforeach
                </div>
            </details>

            <details open class="pt-4 group">
                <summary class="flex items-center justify-between cursor-pointer list-none font-extrabold text-sm text-gray-900">Price Range<i data-lucide="chevron-up" class="w-4 h-4 text-gray-500 transition group-open:rotate-0 rotate-180"></i></summary>
                <div class="mt-4" data-price data-max="{{ $priceMax }}">
                    <div class="dual-range"><div class="dual-fill"></div>
                        <input type="range" data-r="min" min="0" max="{{ $priceMax }}" step="500" value="{{ $min }}" aria-label="Minimum price">
                        <input type="range" data-r="max" min="0" max="{{ $priceMax }}" step="500" value="{{ $max ?: $priceMax }}" aria-label="Maximum price">
                    </div>
                    <div class="flex items-center gap-2 mt-4 text-xs">
                        <label class="flex-1 flex items-center gap-1 border border-gray-200 rounded-xl px-3 py-2 bg-white focus-within:border-brand-500"><span class="text-gray-500">₹</span><input type="number" name="min" min="0" max="{{ $priceMax }}" value="{{ $min ?: 0 }}" data-n="min" class="w-full bg-transparent focus:outline-none font-semibold" aria-label="Minimum price"></label>
                        <span class="text-gray-400">-</span>
                        <label class="flex-1 flex items-center gap-1 border border-gray-200 rounded-xl px-3 py-2 bg-white focus-within:border-brand-500"><span class="text-gray-500">₹</span><input type="number" name="max" min="0" max="{{ $priceMax }}" value="{{ $max ?: $priceMax }}" data-n="max" class="w-full bg-transparent focus:outline-none font-semibold" aria-label="Maximum price"></label>
                    </div>
                </div>
            </details>
            <noscript><button class="w-full mt-4 bg-brand-500 text-white font-bold text-xs py-2.5 rounded-xl">Apply filters</button></noscript>
        </form>

        <section class="min-w-0 min-h-[300px]">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <h2 class="font-extrabold text-xl text-gray-900"><span id="live-count">{{ number_format($products->total()) }} {{ $products->total() === 1 ? 'Product' : 'Products' }} from {{ $shop['name'] }}</span></h2>
                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <form method="POST" action="{{ $self }}" role="search" data-live-search class="flex-1 sm:w-[320px] flex items-center bg-white border border-orange-100 focus-within:border-brand-500 rounded-full overflow-hidden shadow-sm">
            @csrf
                        @foreach ($picked as $id)<input type="hidden" name="category[]" value="{{ $id }}">@endforeach
                        @if ($min)<input type="hidden" name="min" value="{{ $min }}">@endif
                        @if ($max)<input type="hidden" name="max" value="{{ $max }}">@endif
                        <i data-lucide="search" class="w-4 h-4 text-gray-400 ml-4"></i>
                        <input type="search" name="q" value="{{ $q }}" autocomplete="off" placeholder="Search in {{ $shop['name'] }}..." aria-label="Search products in this shop" class="flex-1 px-3 py-3 text-sm bg-transparent focus:outline-none">
                    </form>
                    <div class="flex items-center gap-1 bg-white border border-orange-100 rounded-xl p-1 shadow-sm shrink-0" role="group" aria-label="View">
                        <button type="button" data-view="grid" aria-label="Grid view" class="view-btn w-9 h-9 rounded-lg flex items-center justify-center"><i data-lucide="layout-grid" class="w-4 h-4"></i></button>
                        <button type="button" data-view="list" aria-label="List view" class="view-btn w-9 h-9 rounded-lg flex items-center justify-center"><i data-lucide="list" class="w-4 h-4"></i></button>
                    </div>
                </div>
            </div>
            <div id="live-area" class="shop-products">
                @if ($cards)
                    <div class="shop-grid grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3 lg:gap-4">@foreach ($cards as $card)<x-product-card :product="$card" />@endforeach</div>
                    <x-pagination :paginator="$products" class="pt-8" />
                @else
                    <x-empty-state emoji="🛍️" title="No products found" text="This shop has no products for these filters." cta-label="Clear filters" :cta-href="$self" />
                @endif
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/modules/shops.js') }}"></script>
@endpush
