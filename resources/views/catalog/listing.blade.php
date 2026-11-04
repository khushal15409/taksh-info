@extends('layouts.app')


    @section('title', (($filters->q ? 'Results for “'.$filters->q.'”' : (count($pickedCategories) === 1 ? head($pickedCategories) : ($filters->quick ? 'Quick Products' : 'Shop Groceries & Essentials'))).' | TakshAllInOne'))

@section('page', 'listing')

@section('content')

    @php($heading = $filters->q ? 'Results for “'.$filters->q.'”' : (count($pickedCategories) === 1 ? head($pickedCategories) : ($filters->quick ? 'Quick Products' : 'All Products')))
    <nav class="text-xs text-gray-500 flex items-center gap-2 mb-5" aria-label="Breadcrumb">
        <a href="{{ route('home') }}" class="hover:text-brand-500">Home</a><span>/</span><span class="text-gray-800 font-bold">{{ $heading }}</span>
    </nav>
    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900">{{ $heading }}</h1>
            <p class="text-xs text-gray-500 mt-1">Showing <strong class="text-gray-900">{{ $products->total() }}</strong> products</p>
        </div>
        <button type="button" id="filter-toggle" class="lg:hidden flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-4 py-2 text-xs font-bold"><i data-lucide="sliders-horizontal" class="w-4 h-4"></i>Filters</button>
    </div>

    @if ($quickLocation)
        <div class="mb-5 rounded-2xl border px-4 py-3 text-xs flex flex-wrap items-center justify-between gap-3 {{ $quickLocation['serviceable'] ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
            <span class="font-semibold">
                @if (! $quickLocation['located'])⚡ Choose your delivery location to see the Quick products that reach you in 30 minutes.
                @elseif (! $quickLocation['serviceable'])⚡ Quick delivery is not available at {{ $quickLocation['pincode'] ? 'pincode '.$quickLocation['pincode'] : 'your location' }} yet. We deliver within {{ \App\Helpers\QuickDelivery::RADIUS_KM }} km of our shops.
                @else⚡ Quick products delivered in 30 minutes{{ $quickLocation['pincode'] ? ' to '.$quickLocation['pincode'] : '' }}.
                @endif
            </span>
            <form method="GET" action="{{ route('listing') }}" class="flex items-center gap-2">
                <input type="hidden" name="quick" value="1">
                <input name="pincode" value="{{ $quickLocation['pincode'] }}" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="Pincode" aria-label="Delivery pincode" class="w-28 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-800">
                <button class="font-bold underline">{{ $quickLocation['located'] ? 'Change' : 'Check' }}</button>
                @auth<a href="{{ route('listing', ['quick' => 1, 'location' => 'saved']) }}" class="underline">Saved address</a>@endauth
            </form>
        </div>
    @endif

    @if ($filters->any())
        <div class="flex flex-wrap gap-2 mb-5">
            @foreach ($pickedCategories as $id => $name)<a href="{{ route('listing', $filters->query([], $id)) }}" class="flex items-center gap-1.5 bg-orange-100 text-brand-700 text-[11px] font-bold px-3 py-1 rounded-full hover:bg-orange-200">{{ $name }} ✕</a>@endforeach
            @if ($filters->q)<a href="{{ route('listing', $filters->query('q')) }}" class="flex items-center gap-1.5 bg-orange-100 text-brand-700 text-[11px] font-bold px-3 py-1 rounded-full hover:bg-orange-200">“{{ $filters->q }}” ✕</a>@endif
            @if ($filters->max)<a href="{{ route('listing', $filters->query('max')) }}" class="flex items-center gap-1.5 bg-orange-100 text-brand-700 text-[11px] font-bold px-3 py-1 rounded-full hover:bg-orange-200">Under @rupee($filters->max) ✕</a>@endif
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <form id="filters" method="GET" action="{{ route('listing') }}" class="hidden lg:block bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-6 h-fit lg:sticky lg:top-40">
            @if ($filters->q)<input type="hidden" name="q" value="{{ $filters->q }}">@endif
            @if ($filters->quick)<input type="hidden" name="quick" value="1">@endif
            <div class="flex items-center justify-between border-b pb-4 border-gray-100">
                <h3 class="font-extrabold text-base text-gray-900 flex items-center gap-2"><i data-lucide="filter" class="w-4 h-4 text-brand-500"></i>Filters</h3>
                @if ($filters->any())<a href="{{ route('listing', $filters->quick ? ['quick' => 1] : []) }}" class="text-xs font-semibold text-brand-600 hover:underline">Reset All</a>@endif
            </div>
            <div class="space-y-2">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Categories</h4>
                <div class="space-y-1.5 text-xs">
                    @foreach ($tree as $c)
                        <div class="space-y-1">
                            <label class="flex items-center gap-2 cursor-pointer font-semibold text-gray-700"><input type="checkbox" name="category[]" value="{{ $c['id'] }}" data-parent="{{ $c['id'] }}" class="accent-brand-500" @checked(in_array($c['id'], $checked, true))><span>{{ $c['name'] }}</span>@if ($quickLocation)<span class="ml-auto text-[10px] font-bold text-gray-400">{{ $totals[$c['id']] ?? 0 }}</span>@endif</label>
                            @foreach ($c['children'] ?? [] as $k)
                                <label class="flex items-center gap-2 cursor-pointer text-gray-500 pl-6"><input type="checkbox" name="category[]" value="{{ $k['id'] }}" data-child-of="{{ $c['id'] }}" class="accent-brand-500" @checked(in_array($k['id'], $checked, true))><span>{{ $k['name'] }}</span>@if ($quickLocation)<span class="ml-auto text-[10px] font-bold text-gray-400">{{ $counts[$k['id']] ?? 0 }}</span>@endif</label>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="space-y-2">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Max Price</h4>
                <input id="price-range" type="range" name="max" min="0" max="{{ $priceMax }}" step="50" value="{{ $filters->max ?: $priceMax }}" class="w-full" data-max="{{ $priceMax }}">
                <div class="flex justify-between text-xs text-gray-500 font-semibold"><span>Any</span><span id="price-val" class="text-brand-600 font-extrabold">{{ $filters->max ? \App\Helpers\Rupee::format($filters->max) : 'Any' }}</span><span>@rupee($priceMax)</span></div>
            </div>
            <button class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs py-2.5 rounded-xl">Apply filters</button>
        </form>

        <section class="lg:col-span-3 min-h-[300px]">
            @if ($cards)
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 lg:gap-5">@foreach ($cards as $card)<x-product-card :product="$card" />@endforeach</div>
                <x-pagination :paginator="$products" class="pt-8" />
            @else
                <x-empty-state emoji="{{ $quickLocation ? '⚡' : '🔍' }}" title="{{ $quickLocation ? 'No quick products near you' : 'No products found' }}" text="{{ $quickLocation && ! $quickLocation['serviceable'] ? 'Quick products appear once a shop that delivers in 30 minutes serves your location.' : 'Try changing or clearing some filters.' }}"  cta-label="Clear all filters" :cta-href="route('listing', $filters->quick ? ['quick' => 1] : [])" />
            @endif
        </section>
    </div>

@endsection

@push('scripts')

    <script src="{{ asset('assets/js/modules/listing.js') }}"></script>

@endpush
