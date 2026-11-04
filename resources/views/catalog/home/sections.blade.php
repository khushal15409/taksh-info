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

    @php
        // three offer banners (used when the admin has not published banners of their own)
        $slides = [
            ['festive-gifts', '🪔 Festive Sparkle Sale', 'UP TO 50% OFF', 'Gifts, sweets & festive essentials', 'TAKSHDIWALI', route('listing'), 'Shop the Sale', 'text-brand-500'],
            ['quick-delivery', '⚡ Quick Delivery Offer', '30-MIN DELIVERY', 'Daily needs from shops near you', 'QUICK30', route('listing', ['quick' => 1]), 'Order Quick', 'text-emerald-600'],
            ['electronics', '🎧 Mega Gadget Deals', 'BIG SAVINGS', 'Phones, audio & smart accessories', 'TAKSHDIWALI', route('listing'), 'Explore Deals', 'text-indigo-600'],
        ];
    @endphp
    <section id="home-slider" class="hero-slider" aria-roledescription="carousel" aria-label="Offers">
        @if ($home['hero'])
            @foreach ($home['hero'] as $b)
                <a href="{{ \App\Support\Presenters\HomeLayout::bannerUrl($b) }}" class="hero-slide {{ $loop->first ? 'is-active' : '' }}"><span class="slide-bg"><img src="{{ $b['image_url'] ?? '' }}" alt="{{ $b['title'] ?? 'Offer' }}" @if (! $loop->first) loading="lazy" @endif class="w-full h-full object-cover"></span></a>
            @endforeach
            @php($count = count($home['hero']))
        @else
            @foreach ($slides as [$artName, $badge, $offer, $text, $code, $href, $cta, $accent])
                <div class="hero-slide {{ $loop->first ? 'is-active' : '' }}">
                    <span class="slide-bg" style="background-image:url('{{ asset('assets/img/banners/'.$artName.'-bg.png') }}')"></span>
                    <img src="{{ asset('assets/img/banners/'.$artName.'-art.png') }}" alt="" aria-hidden="true" class="slide-art" @if (! $loop->first) loading="lazy" @endif>
                    <a href="{{ $href }}" class="absolute inset-0" aria-label="{{ $offer }} - {{ $cta }}"></a>
                    <div class="relative h-full flex flex-col justify-center gap-1.5 sm:gap-2.5 px-6 sm:px-12 max-w-[62%] sm:max-w-[56%] pointer-events-none">
                        <span class="self-start bg-white/85 text-gray-700 text-[10px] sm:text-xs font-bold px-3 py-1 rounded-full border border-white">{{ $badge }}</span>
                        <h2 class="font-festive font-extrabold text-2xl sm:text-4xl lg:text-5xl leading-none tracking-tight {{ $accent }}">{{ $offer }}</h2>
                        <p class="text-xs sm:text-sm lg:text-base font-semibold text-gray-700">{{ $text }}</p>
                        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                            <span class="btn-saffron inline-flex items-center gap-2 text-xs sm:text-sm font-extrabold px-5 py-2 sm:py-2.5 rounded-xl">{{ $cta }} <i data-lucide="arrow-right" class="w-4 h-4"></i></span>
                            <span class="hidden sm:inline-flex items-center gap-1 border-2 border-dashed border-gray-400/70 text-gray-700 text-[11px] font-extrabold tracking-wider px-3 py-1.5 rounded-lg bg-white/60">CODE: {{ $code }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
            @php($count = count($slides))
        @endif
        @if ($count > 1)
            <div class="hero-dots" role="tablist">@for ($i = 0; $i < $count; $i++)<button type="button" data-slide="{{ $i }}" aria-label="Slide {{ $i + 1 }}" class="hero-dot {{ $i === 0 ? 'is-active' : '' }}"></button>@endfor</div>
        @endif
    </section>

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
