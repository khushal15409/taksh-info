@extends('layouts.app')

@section('title', 'TakshAllInOne - Premium Festive E-Commerce | Groceries, Quick Delivery & Services')
@section('page', 'home')

@section('content')

        <div class="space-y-12">
        <!-- Hero + promo tiles -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div id="hero" class="lg:col-span-2 relative rounded-3xl overflow-hidden shadow-xl border border-orange-200/50 h-[440px] lg:h-[420px]"></div>
            <div class="grid grid-cols-2 lg:grid-cols-1 lg:grid-rows-2 gap-5">
                <a href="{{ route('listing') }}?cat=decor" class="relative overflow-hidden rounded-3xl p-4 sm:p-6 min-h-[150px] bg-gradient-to-br from-gray-900 via-[#4A1500] to-gray-900 text-white flex flex-col justify-end hover:shadow-xl transition group">
                    <div class="absolute right-2 top-2 text-4xl sm:text-6xl opacity-40 sm:opacity-60 group-hover:scale-110 transition">🪔</div>
                    <span class="text-[10px] font-bold text-yellow-400 uppercase tracking-widest">Festive Decor</span>
                    <h3 class="font-festive font-extrabold text-base sm:text-xl leading-tight">Light up your Diwali</h3>
                    <span class="text-xs text-orange-200 mt-1">Diyas, lights &amp; rangoli from ₹129 →</span>
                </a>
                <a href="{{ route('listing') }}?cat=electronics" class="relative overflow-hidden rounded-3xl p-4 sm:p-6 min-h-[150px] bg-gradient-to-br from-blue-700 to-indigo-600 text-white flex flex-col justify-end hover:shadow-xl transition group">
                    <div class="absolute right-2 top-2 text-4xl sm:text-6xl opacity-40 sm:opacity-60 group-hover:scale-110 transition">🎧</div>
                    <span class="text-[10px] font-bold text-sky-200 uppercase tracking-widest">Electronics</span>
                    <h3 class="font-festive font-extrabold text-base sm:text-xl leading-tight">Gadgets up to 65% off</h3>
                    <span class="text-xs text-blue-100 mt-1">Audio, power banks &amp; more →</span>
                </a>
            </div>
        </section>

        <!-- Categories -->
        <section>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-extrabold text-gray-900">Shop by Category 🛒</h2>
                <a href="{{ route('listing') }}" class="text-xs font-bold text-brand-600 hover:underline">View All</a>
            </div>
            <div class="relative group/cat">
                <button data-cat-nav="prev" class="cat-arrow hidden md:flex absolute -left-3 top-1/2 -translate-y-1/2 z-10 w-9 h-9 rounded-full bg-white border border-orange-200 shadow-md text-brand-600 items-center justify-center hover:bg-brand-500 hover:text-white transition" aria-label="Previous categories"><i data-lucide="chevron-left" class="w-5 h-5"></i></button>
                <div id="categories-grid" class="cat-track flex gap-3 overflow-x-auto no-scrollbar snap-x snap-mandatory scroll-smooth py-1"></div>
                <button data-cat-nav="next" class="cat-arrow hidden md:flex absolute -right-3 top-1/2 -translate-y-1/2 z-10 w-9 h-9 rounded-full bg-white border border-orange-200 shadow-md text-brand-600 items-center justify-center hover:bg-brand-500 hover:text-white transition" aria-label="Next categories"><i data-lucide="chevron-right" class="w-5 h-5"></i></button>
            </div>
        </section>

        <!-- Delivery modes -->
        <section class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-6">
            <a href="{{ route('listing') }}" class="group bg-white p-4 md:p-6 rounded-3xl border border-orange-100 shadow-sm hover:shadow-festive transition relative overflow-hidden flex md:block items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-orange-100 text-brand-500 flex items-center justify-center mb-0 md:mb-4 shrink-0 group-hover:scale-110 transition"><i data-lucide="truck" class="w-8 h-8"></i></div>
                <span class="hidden md:block absolute top-4 right-4 bg-orange-100 text-brand-700 text-[10px] font-bold px-2.5 py-1 rounded-full">Standard</span>
                <div class="min-w-0">
                    <h3 class="font-extrabold text-base md:text-lg text-gray-900 group-hover:text-brand-500 transition">Standard E-Commerce</h3>
                    <p class="text-xs text-gray-500 mt-1">Wide range of 50,000+ national products delivered with trusted care.</p>
                </div>
            </a>
            <a href="{{ route('listing') }}?quick=1" class="group bg-white p-4 md:p-6 rounded-3xl border border-emerald-100 shadow-sm hover:shadow-festive transition relative overflow-hidden flex md:block items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center mb-0 md:mb-4 shrink-0 group-hover:scale-110 transition"><i data-lucide="zap" class="w-8 h-8"></i></div>
                <span class="hidden md:block absolute top-4 right-4 bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2.5 py-1 rounded-full">15-Mins</span>
                <div class="min-w-0">
                    <h3 class="font-extrabold text-base md:text-lg text-gray-900 group-hover:text-emerald-600 transition">Quick 15-Min Delivery</h3>
                    <p class="text-xs text-gray-500 mt-1">Fresh milk, vegetables, snacks &amp; daily essentials at your doorstep instantly.</p>
                </div>
            </a>
            <a href="{{ route('services') }}" class="group bg-white p-4 md:p-6 rounded-3xl border border-purple-100 shadow-sm hover:shadow-festive transition relative overflow-hidden flex md:block items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-purple-100 text-purple-600 flex items-center justify-center mb-0 md:mb-4 shrink-0 group-hover:scale-110 transition"><i data-lucide="wrench" class="w-8 h-8"></i></div>
                <span class="hidden md:block absolute top-4 right-4 bg-purple-100 text-purple-800 text-[10px] font-bold px-2.5 py-1 rounded-full">Verified Pros</span>
                <div class="min-w-0">
                    <h3 class="font-extrabold text-base md:text-lg text-gray-900 group-hover:text-purple-600 transition">Home &amp; Local Services</h3>
                    <p class="text-xs text-gray-500 mt-1">Electricians, plumbers, salon at home, cleaners &amp; more.</p>
                </div>
            </a>
        </section>

        <!-- Deals of the day -->
        <section class="bg-gradient-to-r from-orange-100/70 to-yellow-50 rounded-3xl p-5 lg:p-6 border border-orange-200/60">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-4 flex-wrap">
                    <h2 class="text-xl font-extrabold text-gray-900">🔥 Deals of the Day</h2>
                    <div class="flex items-center gap-1.5 text-xs font-bold text-gray-500">Ends in <div id="deal-timer" class="flex items-center gap-1"></div></div>
                </div>
                <a href="{{ route('listing') }}?deals=1&sort=discount" class="text-xs font-bold text-brand-600 hover:underline">See all deals →</a>
            </div>
            <div id="deals-rail" class="rail"></div>
        </section>

        <!-- Promo banners -->
        <section class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <a href="{{ route('listing') }}?cat=snacks" class="relative overflow-hidden rounded-3xl p-8 bg-gradient-to-r from-red-600 to-orange-500 text-white group hover:shadow-xl transition">
                <div class="absolute -right-4 -bottom-6 text-[120px] opacity-30 group-hover:scale-110 transition">🍬</div>
                <span class="text-[10px] font-bold uppercase tracking-widest text-yellow-200">Mithai &amp; Gifting</span>
                <h3 class="font-festive font-extrabold text-2xl mt-1">Sweeten the festival</h3>
                <p class="text-sm text-orange-100 mt-1">Kaju katli, soan papdi &amp; gift boxes</p>
                <span class="inline-block mt-4 bg-white text-red-600 font-bold text-xs px-4 py-2 rounded-xl">Shop sweets</span>
            </a>
            <a href="{{ route('listing') }}?cat=fresh" class="relative overflow-hidden rounded-3xl p-8 bg-gradient-to-r from-emerald-700 to-lime-500 text-white group hover:shadow-xl transition">
                <div class="absolute -right-4 -bottom-6 text-[120px] opacity-30 group-hover:scale-110 transition">🥦</div>
                <span class="text-[10px] font-bold uppercase tracking-widest text-lime-100">Farm Fresh</span>
                <h3 class="font-festive font-extrabold text-2xl mt-1">Fresh in 15 minutes</h3>
                <p class="text-sm text-emerald-50 mt-1">Fruits &amp; vegetables hand-picked daily</p>
                <span class="inline-block mt-4 bg-white text-emerald-700 font-bold text-xs px-4 py-2 rounded-xl">Shop fresh</span>
            </a>
        </section>

        <!-- Trending with tabs -->
        <section>
            <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
                <div><h2 class="text-xl font-extrabold text-gray-900">Featured Diwali Deals &amp; Essentials</h2><p class="text-xs text-gray-500">Handpicked items from the Taksh catalogue</p></div>
                <div class="flex flex-wrap gap-2">
                    <button data-tab="bestseller">Bestsellers</button><button data-tab="new">New Arrivals</button>
                    <button data-tab="festive">Festive Specials</button><button data-tab="electronics">Electronics</button>
                </div>
            </div>
            <div id="trending-grid"></div>
            <div class="text-center mt-6"><a href="{{ route('listing') }}" class="inline-block border-2 border-brand-500 text-brand-600 font-bold text-sm px-8 py-3 rounded-2xl hover:bg-brand-500 hover:text-white transition">Browse all products</a></div>
        </section>

        <!-- Quick delivery -->
        <section>
            <div class="flex items-center justify-between mb-4">
                <div><h2 class="text-xl font-extrabold text-gray-900">⚡ Delivered in 15 Minutes</h2><p class="text-xs text-gray-500">Dairy, snacks, fresh produce &amp; more from your nearest dark store</p></div>
                <a href="{{ route('listing') }}?quick=1" class="text-xs font-bold text-brand-600 hover:underline">See all →</a>
            </div>
            <div id="quick-rail" class="rail"></div>
        </section>

        <!-- Services -->
        <section>
            <div class="flex items-center justify-between mb-4">
                <div><h2 class="text-xl font-extrabold text-gray-900">Expert Services at Your Doorstep</h2><p class="text-xs text-gray-500">Verified professionals arriving within 60 minutes</p></div>
                <a href="{{ route('services') }}" class="text-xs font-bold text-brand-600 hover:underline">All services →</a>
            </div>
            <div id="services-grid" class="grid grid-cols-2 lg:grid-cols-4 gap-5"></div>
        </section>

        <!-- Testimonials -->
        <section>
            <h2 class="text-xl font-extrabold text-gray-900 mb-4">Loved by 1,00,000+ happy customers</h2>
            <div id="testimonials" class="grid grid-cols-1 md:grid-cols-3 gap-5"></div>
        </section>

        <!-- App coming soon -->
        <section id="app" class="scroll-mt-40 relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand-600 via-brand-500 to-brand-gold text-white shadow-xl">
            <div class="absolute -left-16 -bottom-20 w-72 h-72 bg-yellow-300/20 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -right-10 -top-10 w-72 h-72 bg-red-500/20 rounded-full blur-3xl pointer-events-none"></div>
            <div class="relative grid md:grid-cols-2 gap-8 items-center p-6 sm:p-10 lg:p-14">
                <div class="space-y-5 min-w-0">
                    <span class="inline-flex items-center gap-2 bg-black/25 backdrop-blur px-3 py-1 rounded-full text-xs font-bold text-yellow-300 border border-yellow-300/30">📱 Android App</span>
                    <h2 class="font-festive font-extrabold text-3xl lg:text-4xl xl:text-5xl leading-tight">Taksh App <span class="text-yellow-300">Coming Soon</span> 🚀</h2>
                    <p class="text-orange-50 text-sm lg:text-base max-w-md">Groceries, quick delivery and home services - all in your pocket. Be the first to know when we land on Google Play.</p>
                    <ul class="grid sm:grid-cols-2 gap-2 text-sm font-semibold">
                        <li class="flex items-center gap-2"><i data-lucide="zap" class="w-4 h-4 text-yellow-300"></i>15-min delivery</li>
                        <li class="flex items-center gap-2"><i data-lucide="badge-percent" class="w-4 h-4 text-yellow-300"></i>App-only offers</li>
                        <li class="flex items-center gap-2"><i data-lucide="map-pin" class="w-4 h-4 text-yellow-300"></i>Live order tracking</li>
                        <li class="flex items-center gap-2"><i data-lucide="bell-ring" class="w-4 h-4 text-yellow-300"></i>Instant order alerts</li>
                    </ul>
                    <div class="pt-1"><span data-play-badge></span></div>
                    <form data-notify-form class="flex flex-col sm:flex-row gap-2 max-w-md" novalidate>
                        <input name="contact" type="text" inputmode="email" placeholder="Email or mobile number" aria-label="Email or mobile number" class="flex-1 min-w-0 w-full rounded-2xl px-4 py-3 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-yellow-300">
                        <button class="bg-gray-900 hover:bg-black text-yellow-300 font-extrabold px-5 py-3 rounded-2xl transition whitespace-nowrap">🔔 Notify me</button>
                    </form>
                </div>

                <!-- Phone mockup -->
                <div class="flex justify-center" aria-hidden="true">
                  <div class="relative">
                    <div class="relative w-[210px] sm:w-[240px] rounded-[2.4rem] bg-gray-900 p-2.5 shadow-2xl shadow-black/40 rotate-3 hover:rotate-0 transition duration-500">
                        <div class="absolute top-3.5 left-1/2 -translate-x-1/2 w-16 h-4 bg-gray-900 rounded-full z-10"></div>
                        <div class="rounded-[1.9rem] bg-[#FFFBEF] overflow-hidden text-gray-800">
                            <div class="header-gradient pt-8 pb-3 px-3">
                                <img src="assets/img/logo.png" alt="" class="h-6 w-auto bg-white rounded-lg px-1.5 py-0.5">
                                <div class="mt-2 bg-white/95 rounded-lg px-2 py-1.5 text-[9px] text-gray-400">Search atta, rice, diya...</div>
                            </div>
                            <div class="p-3 space-y-3">
                                <div class="flex gap-1.5 text-[9px] font-bold">
                                    <span class="bg-brand-500 text-white rounded-full px-2 py-1">⚡ 15 min</span>
                                    <span class="bg-orange-100 text-brand-600 rounded-full px-2 py-1">🪔 Diwali</span>
                                    <span class="bg-orange-100 text-brand-600 rounded-full px-2 py-1">🛠 Services</span>
                                </div>
                                <div class="rounded-xl bg-gradient-to-r from-emerald-600 to-lime-500 text-white p-2.5 text-[10px] font-bold leading-tight">Fresh in 15 minutes<div class="text-[8px] font-medium text-emerald-50">Free delivery above ₹299</div></div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="bg-white rounded-xl border border-orange-100 p-1.5"><img src="assets/img/products/p-1.svg" alt="" class="w-full aspect-square object-contain"><div class="text-[8px] font-bold truncate">Chakki Atta 5 kg</div><div class="text-[9px] font-extrabold text-brand-600">₹364</div></div>
                                    <div class="bg-white rounded-xl border border-orange-100 p-1.5"><img src="assets/img/products/p-13.svg" alt="" class="w-full aspect-square object-contain"><div class="text-[8px] font-bold truncate">Amul Gold Milk</div><div class="text-[9px] font-extrabold text-brand-600">₹72</div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="absolute -left-6 sm:-left-12 bottom-8 bg-white text-gray-800 rounded-2xl shadow-xl px-3 py-2 text-[11px] font-bold flex items-center gap-2 -rotate-3"><span class="text-lg">🚀</span>Launching soon</div>
                  </div>
                </div>
            </div>
        </section>

        <!-- Newsletter -->
        <section class="festive-banner-bg rounded-3xl p-6 sm:p-8 lg:p-12 text-white grid md:grid-cols-2 gap-6 items-center gold-border-glow">
            <div class="min-w-0"><h2 class="font-festive font-extrabold text-2xl lg:text-3xl">Get festive offers first 🎁</h2><p class="text-sm text-orange-200 mt-2">Subscribe for exclusive Diwali deals, coupons and new arrivals. No spam, ever.</p></div>
            <form id="newsletter-form" class="flex flex-col sm:flex-row gap-2 min-w-0" novalidate>
                <input name="email" type="email" placeholder="Enter your email address" class="flex-1 min-w-0 w-full rounded-2xl px-4 py-3 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-brand-gold" aria-label="Email">
                <button class="bg-gradient-to-r from-brand-500 to-brand-gold text-white font-extrabold px-6 py-3 rounded-2xl hover:brightness-110">Subscribe</button>
            </form>
        </section>
        </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/pages/home.js') }}"></script>
@endpush
