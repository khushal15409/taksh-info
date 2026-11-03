{{-- The home page from the shared catalogue: top banners, categories, the admin's sections, services and the fixed marketing blocks. --}}
<div class="space-y-12">
    @unless ($home['has_content'])
        <x-empty-state emoji="🛍️" title="Our catalogue is being set up" text="Banners, categories and offers will appear here as soon as they are published. You can already explore the shop." cta-label="Browse the shop" :cta-href="route('listing')" />
    @endunless

    @if ($home['hero'])
        <section class="relative rounded-3xl overflow-hidden shadow-xl border border-orange-200/50">
            <div class="flex overflow-x-auto snap-x snap-mandatory no-scrollbar">
                @foreach ($home['hero'] as $b)
                    <a href="{{ \App\Support\Presenters\HomeLayout::bannerUrl($b) }}" class="snap-center shrink-0 w-full"><img src="{{ $b['image_url'] ?? '' }}" alt="{{ $b['title'] ?? 'Offer' }}" @if (! $loop->first) loading="lazy" @endif class="w-full h-[220px] sm:h-[320px] lg:h-[420px] object-cover"></a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($home['categories'])
        <section id="categories-section">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-extrabold text-gray-900">Shop by Category 🛒</h2>
                <a href="{{ route('listing') }}" class="text-xs font-bold text-brand-600 hover:underline">View All</a>
            </div>
            <div class="relative group/cat">
                <button type="button" data-cat-nav="prev" class="cat-arrow hidden md:flex absolute -left-3 top-1/2 -translate-y-1/2 z-10 w-9 h-9 rounded-full bg-white border border-orange-200 shadow-md text-brand-600 items-center justify-center hover:bg-brand-500 hover:text-white transition" aria-label="Previous categories"><i data-lucide="chevron-left" class="w-5 h-5"></i></button>
                <div id="categories-grid" class="cat-track flex gap-3 overflow-x-auto no-scrollbar snap-x snap-mandatory scroll-smooth py-1">
                    @foreach ($home['categories'] as $c)<x-category-tile :category="$c" />@endforeach
                </div>
                <button type="button" data-cat-nav="next" class="cat-arrow hidden md:flex absolute -right-3 top-1/2 -translate-y-1/2 z-10 w-9 h-9 rounded-full bg-white border border-orange-200 shadow-md text-brand-600 items-center justify-center hover:bg-brand-500 hover:text-white transition" aria-label="Next categories"><i data-lucide="chevron-right" class="w-5 h-5"></i></button>
            </div>
        </section>
    @endif

    @include('catalog.home.delivery-modes')

    @foreach ($home['blocks'] as $block)
        @if ($block['type'] === 'banner')
            @include('catalog.home.banner', ['b' => $block['banner']])
        @else
            @php($section = $block['section'])
            @php($look = \App\Support\Presenters\HomeLayout::design($section['design'] ?? null))
            @php($items = $section['data'])
            <section class="{{ $look['box'] }}">
                <div class="flex items-center justify-between mb-4"><h2 class="text-xl font-extrabold {{ $look['title'] }}">{{ $section['key'] }}</h2></div>
                @if ($block['kind'] === 'products')
                    @if (($section['design'] ?? null) === 'spotlight')
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                            <div class="lg:col-span-1"><x-product-card :product="$items[0]" /></div>
                            <div class="lg:col-span-2 grid grid-cols-2 md:grid-cols-3 gap-4">@foreach (array_slice($items, 1, 6) as $p)<x-product-card :product="$p" />@endforeach</div>
                        </div>
                    @else
                        <div class="rail">@foreach ($items as $p)<x-product-card :product="$p" />@endforeach</div>
                    @endif
                @elseif ($block['kind'] === 'categories')
                    <div class="flex gap-3 overflow-x-auto no-scrollbar py-1">@foreach ($items as $c)<x-category-tile :category="$c" />@endforeach</div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">@foreach ($items as $b)@include('catalog.home.banner', ['b' => $b])@endforeach</div>
                @endif
            </section>
        @endif
    @endforeach

    @if ($recent)
        <section>
            <h2 class="text-xl font-extrabold text-gray-900 mb-4">Recently Viewed</h2>
            <div class="rail">
                @foreach ($recent as $r)
                    <a href="{{ route('product', ['id' => $r['id']]) }}" class="bg-white rounded-3xl border border-orange-100 p-3 hover:border-brand-500/40 transition block">
                        @if (! empty($r['thumbnail']))<img src="{{ $r['thumbnail'] }}" alt="" loading="lazy" class="w-full aspect-square object-contain">@else<div class="w-full aspect-square flex items-center justify-center text-5xl bg-orange-50/60">🛍️</div>@endif
                        <div class="text-xs font-bold text-gray-800 mt-2 line-clamp-2">{{ $r['name'] }}</div>
                        <div class="text-sm font-extrabold text-brand-600">@rupee($r['price'])</div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($services)
        <section>
            <div class="flex items-center justify-between mb-4">
                <div><h2 class="text-xl font-extrabold text-gray-900">Expert Services at Your Doorstep</h2><p class="text-xs text-gray-500">Verified professionals arriving within 60 minutes</p></div>
                <a href="{{ route('services') }}" class="text-xs font-bold text-brand-600 hover:underline">All services →</a>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
                @foreach ($services as $s)
                    <a href="{{ route('services.book', $s['id']) }}" class="group bg-white p-6 rounded-3xl border border-gray-100 hover:border-purple-400 hover:shadow-lg transition text-center space-y-2">
                        <div class="w-16 h-16 mx-auto rounded-2xl bg-purple-100 flex items-center justify-center text-3xl overflow-hidden group-hover:scale-110 transition">@if ($s['image'])<img src="{{ $s['image'] }}" alt="" loading="lazy" class="w-full h-full object-cover">@else 🛠️ @endif</div>
                        <h3 class="font-extrabold text-sm text-gray-800">{{ $s['name'] }}</h3>
                        <p class="text-xs text-gray-400">{{ $s['short_description'] }}</p>
                        @if ($s['service_charge'] > 0)<p class="text-xs font-bold text-brand-600">From @rupee($s['service_charge'])</p>@endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @include('catalog.home.testimonials')
    @include('catalog.home.app-newsletter')
</div>
