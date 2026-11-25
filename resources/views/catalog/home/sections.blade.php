{{-- The home page from the shared catalogue: top banners, categories, the admin's sections, services and the fixed marketing blocks. --}}
<div class="space-y-8">
    @unless ($home['has_content'])
        <x-empty-state emoji="🛍️" title="Our catalogue is being set up" text="Banners, categories and offers will appear here as soon as they are published. You can already explore the shop." cta-label="Browse the shop" :cta-href="route('listing')" />
    @endunless

    @if ($home['categories'])
        <section id="categories-section" aria-label="Shop by Category">
            <div id="categories-grid" class="cat-track flex gap-3 overflow-x-auto no-scrollbar snap-x snap-mandatory scroll-smooth py-1">
                @foreach ($home['categories'] as $c)<x-category-tile :category="$c" />@endforeach
            </div>
        </section>
    @endif

    {{-- Only banners published in the admin (desktop) are shown; nothing is drawn when there are none. --}}
    @if ($home['hero'])
        <section id="home-slider" class="hero-slider" aria-roledescription="carousel" aria-label="Offers">
            @foreach ($home['hero'] as $b)
                <a href="{{ \App\Support\Presenters\HomeLayout::bannerUrl($b) }}" class="hero-slide {{ $loop->first ? 'is-active' : '' }}"><span class="slide-bg"><img src="{{ $b['image_url'] ?? '' }}" alt="{{ $b['title'] ?? 'Offer' }}" @if (! $loop->first) loading="lazy" @endif></span></a>
            @endforeach
            @if (count($home['hero']) > 1)
                <div class="hero-dots" role="tablist">@for ($i = 0; $i < count($home['hero']); $i++)<button type="button" data-slide="{{ $i }}" aria-label="Slide {{ $i + 1 }}" class="hero-dot {{ $i === 0 ? 'is-active' : '' }}"></button>@endfor</div>
            @endif
        </section>
    @endif

    {{-- Shop by Store: the approved shops (vendors); each opens that shop's own products page --}}
    @if (! empty($shops))
        <section aria-label="Shop by Store">
            <div class="flex flex-wrap items-center gap-3 bg-white/80 border border-orange-100 rounded-3xl shadow-sm px-4 py-4 mb-8 text-xs font-bold text-gray-700 justify-around">
                <span class="flex items-center gap-2"><i data-lucide="store" class="w-5 h-5 text-brand-500"></i>Wide Range of Shops</span>
                <span class="flex items-center gap-2"><i data-lucide="badge-check" class="w-5 h-5 text-brand-500"></i>Trusted Local &amp; Brand Stores</span>
                <span class="flex items-center gap-2"><i data-lucide="heart" class="w-5 h-5 text-brand-500"></i>Shop by Your Favorite Store</span>
                <span class="flex items-center gap-2"><i data-lucide="truck" class="w-5 h-5 text-brand-500"></i>Fast &amp; Safe Delivery</span>
            </div>
            <div class="flex items-center justify-between mb-4"><div><h2 class="sec-title"><span class="sec-ico"><i data-lucide="store" class="w-4 h-4"></i></span>Shop by Store</h2><p class="text-xs text-gray-500 mt-1 ml-10">Explore products from your favorite shops</p></div><a href="{{ route('shops') }}" class="sec-link">View All Shops <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a></div>
            <div class="flex gap-3 overflow-x-auto no-scrollbar snap-x snap-mandatory py-1">@foreach ($shops as $s)@include('shops.partials.card', ['s' => $s, 'rail' => true])@endforeach</div>
        </section>
    @endif


    @foreach ($home['blocks'] as $block)
        @if ($block['type'] === 'banner')
            @include('catalog.home.banner', ['b' => $block['banner']])
        @else
            @php($section = $block['section'])
            @php($look = \App\Support\Presenters\HomeLayout::design($section['design'] ?? null))
            @php($items = $section['data'])
            <section class="{{ $look['box'] }}">
                <div class="flex items-center justify-between mb-4"><h2 class="sec-title {{ $look['title'] }}"><span class="sec-ico"><i data-lucide="star" class="w-4 h-4 fill-current"></i></span>{{ $section['key'] }}</h2><a href="{{ route('listing') }}" class="sec-link">View All <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a></div>
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
            <h2 class="sec-title mb-4"><span class="sec-ico"><i data-lucide="history" class="w-4 h-4"></i></span>Recently Viewed</h2>
            <div class="rail">
                @foreach ($recent as $r)
                    <a href="{{ route('product', ['id' => $r['id']]) }}" class="pcard p-3">
                        @if (! empty($r['thumbnail']))<img src="{{ $r['thumbnail'] }}" alt="" loading="lazy" class="w-full aspect-square object-contain">@else<div class="w-full aspect-square flex items-center justify-center text-5xl bg-orange-50/60">🛍️</div>@endif
                        <div class="text-xs font-bold text-gray-800 mt-2 line-clamp-2">{{ $r['name'] }}</div>
                        <div class="text-sm font-extrabold text-brand-600">@rupee($r['price'])</div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @include('catalog.home.app-newsletter')
</div>
