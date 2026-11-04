@extends('layouts.app')

@if ($product)
    @section('title', $product['name'].' | TakshAllInOne')
@else
    @section('title', 'Product not found | TakshAllInOne')
@endif
@section('page', 'product-details')

@section('content')
@if (! $product)
    <x-empty-state emoji="😕" title="Product not found" text="This product may have been removed." cta-label="Browse products" :cta-href="route('listing')" />
@else
    @php
        $v = $variant;
        $rs = $product['rating_summary'];
        $off = \App\Support\Presenters\ProductCard::discount((float) $v['sale_price'], (float) $v['price']);
        $images = collect(! empty($v['images']) ? $v['images'] : $product['images']);
        $main = ($images->firstWhere('is_primary', true) ?? $images->first())['image_url'] ?? null;
        $label = function (array $x) {
            $attrs = collect($x['variant_attributes'] ?? [])->map(fn ($a) => $a['attribute_value']['value'] ?? null)->filter();

            return $attrs->isNotEmpty() ? $attrs->implode(' · ') : ($x['sku'] ?? 'Option '.$x['id']);
        };
        $details = array_filter([
            'Brand' => $product['brand']['name'] ?? null, 'Category' => $product['category']['name'] ?? null, 'Net weight' => $product['net_weight'] ?? null,
            'Country of origin' => $product['country_of_origin'] ?? null, 'Manufacturer' => $product['manufacturer_name'] ?? null, 'Packed by' => $product['packaging'] ?? null,
        ]);
    @endphp
    <nav class="text-xs text-gray-500 flex items-center gap-2 mb-5 flex-wrap" aria-label="Breadcrumb">
        <a href="{{ route('home') }}" class="hover:text-brand-500">Home</a><span>/</span>
        @if ($product['category'])<a href="{{ route('listing', ['category' => $product['category']['id']]) }}" class="hover:text-brand-500">{{ $product['category']['name'] }}</a><span>/</span>@endif
        <span class="text-gray-800 font-bold truncate">{{ $product['name'] }}</span>
    </nav>

    <div id="product-page" data-delivery="{{ ! empty($product['is_express_30']) ? '30_min' : 'normal' }}" data-product-id="{{ $product['id'] }}" data-variant-id="{{ $v['id'] }}" data-max-qty="{{ max(1, (int) ($v['available_stock'] ?? 1)) }}">
        <div class="bg-white rounded-3xl p-6 lg:p-10 border border-orange-100 shadow-sm grid grid-cols-1 lg:grid-cols-2 gap-10">
            <div class="space-y-4">
                <div class="relative bg-gradient-to-b from-orange-50/70 to-white rounded-2xl p-6 border border-orange-100 flex items-center justify-center overflow-hidden">
                    @if ($main)<img id="lp-main" src="{{ $main }}" alt="{{ $product['name'] }}" class="w-full max-w-md aspect-square object-contain">@else<div class="w-full aspect-square flex items-center justify-center text-5xl bg-orange-50/60">🛍️</div>@endif
                    <span class="absolute top-4 left-4 {{ $v['in_stock'] ? 'bg-emerald-600' : 'bg-gray-500' }} text-white text-xs font-bold px-3 py-1 rounded-full shadow">{{ $v['in_stock'] ? 'IN STOCK' : 'OUT OF STOCK' }}</span>
                    @if ($off > 0)<span class="absolute top-4 right-4 bg-brand-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow">{{ $off }}% OFF</span>@endif
                </div>
                @if ($images->count() > 1)
                    <div class="flex gap-2 overflow-x-auto no-scrollbar">
                        @foreach ($images as $i)<button type="button" data-lp-thumb="{{ $i['image_url'] }}" class="w-16 h-16 shrink-0 rounded-xl border border-orange-100 bg-white p-1"><img src="{{ $i['image_url'] }}" alt="" loading="lazy" class="w-full h-full object-contain"></button>@endforeach
                    </div>
                @endif
                <div class="grid grid-cols-3 gap-3 text-center text-[11px] font-semibold text-gray-600">
                    <div class="bg-emerald-50 rounded-xl p-3"><i data-lucide="shield-check" class="w-5 h-5 mx-auto text-emerald-600 mb-1"></i>100% Genuine</div>
                    <div class="bg-orange-50 rounded-xl p-3"><i data-lucide="refresh-cw" class="w-5 h-5 mx-auto text-brand-500 mb-1"></i>{{ $product['return_days'] }} Day Returns</div>
                    <div class="bg-sky-50 rounded-xl p-3"><i data-lucide="truck" class="w-5 h-5 mx-auto text-sky-600 mb-1"></i>Fast Delivery</div>
                </div>
            </div>

            <div class="space-y-5">
                <div>
                    <div class="flex items-center justify-between">
                        @if ($product['brand'])<span class="text-xs font-bold text-brand-600 uppercase tracking-widest">Brand: {{ $product['brand']['name'] }}</span>@else<span></span>@endif
                        <button type="button" data-wish="{{ $product['id'] }}" aria-label="Wishlist" class="wish-btn w-9 h-9 rounded-full border border-gray-200 flex items-center justify-center text-gray-400 hover:text-red-500 transition"><i data-lucide="heart" class="w-4 h-4"></i></button>
                    </div>
                    <h1 class="text-2xl lg:text-3xl font-extrabold text-gray-900 mt-1">{{ $product['name'] }}</h1>
                    @if (! empty($v['sku']))<p class="text-xs text-gray-400 mt-1">SKU: {{ $v['sku'] }}</p>@endif
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <div class="text-3xl font-extrabold text-brand-600">@rupee2($v['sale_price'])</div>
                    @if ($off > 0)
                        <div class="text-sm text-gray-400 line-through">@rupee2($v['price'])</div>
                        <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2.5 py-1 rounded-lg">You save @rupee($v['price'] - $v['sale_price'])</span>
                    @endif
                    @if ($rs['total_reviews'])
                        <div class="flex items-center gap-1 bg-amber-100 text-amber-800 text-xs font-bold px-2.5 py-1 rounded-lg"><i data-lucide="star" class="w-3.5 h-3.5 fill-current text-amber-500"></i>{{ $rs['average_rating'] }} ({{ $rs['total_reviews'] }} reviews)</div>
                    @endif
                </div>
                <p class="text-xs text-gray-500">Inclusive of all taxes.</p>

                @if (count($product['variants']) > 1)
                    <div class="space-y-2">
                        <div class="text-xs font-bold text-gray-500">Choose option</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($product['variants'] as $x)
                                <a href="{{ route('product', ['id' => $product['id'], 'variant' => $x['id']]) }}" class="px-4 py-2 rounded-xl border-2 text-xs font-bold {{ $x['id'] === $v['id'] ? 'border-brand-500 bg-orange-50 text-brand-600' : 'border-gray-200 text-gray-600 hover:border-brand-500/50' }} {{ $x['in_stock'] ? '' : 'opacity-50' }}">{{ $label($x) }} · @rupee($x['sale_price'])</a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="bg-emerald-50/60 p-4 rounded-2xl border border-emerald-200 space-y-2">
                    <div class="text-xs font-bold text-emerald-900 flex items-center gap-2"><i data-lucide="map-pin" class="w-4 h-4 text-emerald-600"></i>Check Delivery Availability</div>
                    <form id="lp-pin" class="flex gap-2"><input id="lp-pin-input" maxlength="10" inputmode="numeric" class="bg-white border border-emerald-300 rounded-xl px-3 py-1.5 text-xs font-bold text-gray-800 w-36 focus:outline-none"><button class="bg-emerald-600 text-white px-4 py-1.5 rounded-xl text-xs font-bold hover:bg-emerald-700 transition">Check</button></form>
                    <p id="lp-pin-result" class="text-[11px] font-medium text-emerald-700"></p>
                </div>

                <div class="pt-2 flex flex-wrap items-center gap-3">
                    <div class="flex items-center border-2 border-orange-200 rounded-2xl bg-white p-1">
                        <button type="button" data-lp-q="-1" class="w-9 h-9 rounded-xl hover:bg-orange-100 font-bold text-brand-600" aria-label="Decrease">−</button>
                        <span id="lp-qty" class="w-10 text-center text-sm font-extrabold text-gray-800">1</span>
                        <button type="button" data-lp-q="1" class="w-9 h-9 rounded-xl hover:bg-orange-100 font-bold text-brand-600" aria-label="Increase">+</button>
                    </div>
                    <button type="button" data-lp-add @disabled(! ($v['in_stock'] && $v['id'])) class="flex-1 min-w-[150px] bg-gradient-to-r from-brand-500 to-brand-600 text-white font-extrabold py-3 px-6 rounded-2xl shadow-md hover:brightness-110 transition disabled:opacity-50 flex items-center justify-center gap-2"><i data-lucide="shopping-cart" class="w-5 h-5"></i>{{ $v['in_stock'] ? 'Add to Cart' : 'Out of Stock' }}</button>
                    <p id="lp-in-cart" class="w-full text-[11px] text-emerald-700 font-semibold"></p>
                </div>

                <div class="border-t border-gray-100 pt-4 text-xs text-gray-500 space-y-1">
                    @if (! empty($product['vendor_name']))<p><strong>Seller:</strong> {{ $product['vendor_name'] }}@if (! empty($product['vendor_address'])) ({{ $product['vendor_address'] }})@endif</p>@endif
                    <p><strong>Returns:</strong> {{ $product['return_days'] }} Days Easy Return Policy</p>
                    @if (! empty($product['origin']))<p><strong>Origin:</strong> {{ $product['origin'] }}</p>@endif
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl border border-orange-100 shadow-sm mt-8">
            <div class="flex gap-6 px-6 pt-4 border-b border-gray-100 text-sm font-bold" id="lp-tabs" role="tablist">
                @foreach (['Description', 'Details', 'Reviews ('.$rs['total_reviews'].')', 'Q&A'] as $i => $t)
                    <button type="button" role="tab" data-lp-tab="{{ $i }}" class="pb-3 border-b-2 {{ $i === 0 ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-400 hover:text-gray-700' }}">{{ $t }}</button>
                @endforeach
            </div>
            <div class="p-6 text-sm text-gray-600 leading-relaxed">
                <div data-lp-panel="0"><p>{{ $product['description'] ?: ($product['short_description'] ?: 'No description available.') }}</p></div>
                <div data-lp-panel="1" hidden>
                    <dl class="grid grid-cols-2 gap-y-2 max-w-md text-xs">@foreach ($details as $k => $val)<dt class="text-gray-400 font-bold">{{ $k }}</dt><dd class="font-semibold">{{ $val }}</dd>@endforeach</dl>
                </div>
                <div data-lp-panel="2" hidden>@include('catalog.partials.reviews')</div>
                <div data-lp-panel="3" hidden>
                    @forelse ($product['questions_answers'] as $q)
                        <div class="max-w-2xl border-b border-gray-100 pb-4 mb-4">
                            <p class="text-xs font-extrabold text-gray-900">Q: {{ $q['question'] }}</p>
                            @forelse ($q['answers'] as $a)<p class="text-xs text-gray-600 mt-1">A: {{ $a['answer'] }}</p>@empty<p class="text-xs text-gray-400 italic mt-1">Awaiting an answer from the seller</p>@endforelse
                        </div>
                    @empty
                        <p class="text-xs text-gray-400">No questions have been answered for this product yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @if ($similar)
        <section class="mt-10">
            <h2 class="text-xl font-extrabold text-gray-900 mb-4">Similar Products</h2>
            <div class="rail">@foreach ($similar as $card)<x-product-card :product="$card" />@endforeach</div>
        </section>
    @endif
@endif
@endsection

@push('scripts')

    <script src="{{ asset('assets/js/modules/product.js') }}"></script>

@endpush
