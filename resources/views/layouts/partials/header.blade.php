{{-- Site header: the "app coming soon" strip, the offer bar, the main bar (logo, delivery location, search, bell, wishlist, cart, account) and the main navigation.
     Counts come from the server with the page ($headerState); the bell dot and the delivery-location chip are filled in by ui.js. --}}
@php
    $page = trim($__env->yieldContent('page', 'home'));
    $isHome = $page === 'home';
    $isQuick = $page === 'listing' && request()->query('quick') === '1';
    $isServices = $page === 'services';
    $name = trim((string) ($authUser['name'] ?? ''));
    $cartCount = (int) $headerState['cart_count'];
    $wishCount = count($headerState['wish_ids']);
    $tabs = [
        [route('home'), 'home', 'Home', $isHome, 'bg-orange-100 text-brand-600'],
        [route('listing', ['quick' => 1]), 'zap', 'Quick Products', $isQuick, 'bg-emerald-100 text-emerald-600'],
        [route('services'), 'wrench', 'Services', $isServices, 'bg-purple-100 text-purple-600'],
    ];
@endphp
<div id="coming-soon" class="coming-soon-banner text-center text-white text-xs sm:text-sm font-bold tracking-wide py-2.5 px-10 relative z-50">
    <a href="{{ route('home') }}#app" class="hover:underline underline-offset-4 decoration-yellow-300">
        <span class="mr-1">📱</span><span class="uppercase tracking-widest">App Coming Soon</span>
        <span class="mx-2 text-yellow-300 hidden sm:inline">•</span>
        <span class="font-medium text-orange-100 hidden sm:inline">Taksh app is coming soon to Google Play - <u>Get notified</u></span>
    </a>
    <button type="button" data-close-banner class="absolute right-3 top-1/2 -translate-y-1/2 text-orange-200 hover:text-white w-6 h-6" aria-label="Dismiss">✕</button>
</div>

<div class="header-gradient text-white text-xs py-2 px-4 relative z-40">
    <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
        <div class="flex items-center gap-2 min-w-0">
            <span class="text-yellow-300">✨</span>
            <span class="truncate"><b>Festive Sparkle Sale:</b> Up to 50% OFF + 15-Min Delivery | Code: <b class="underline decoration-yellow-300 decoration-2">TAKSHDIWALI</b></span>
        </div>
        <div class="hidden md:flex items-center gap-5 text-orange-100 font-medium shrink-0">
            <a href="{{ route('help') }}" class="hover:text-white">24x7 Customer Care</a>
            <a href="{{ route('tracking') }}" class="hover:text-white">Track Order</a>
            <button type="button" data-open-prefs class="flex items-center gap-1 hover:text-white" aria-label="Language and theme"><i data-lucide="globe" class="w-3.5 h-3.5"></i>{{ app()->getLocale() === 'hi' ? 'हिं' : 'EN' }} / ₹ INR</button>
        </div>
    </div>
</div>

<header class="bg-white sticky top-0 z-40 border-b border-orange-100 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 lg:px-8 py-3 flex flex-wrap lg:flex-nowrap items-center gap-x-4 gap-y-3">
        <a href="{{ route('home') }}" class="flex items-center shrink-0" aria-label="TakshAllInOne - Home">
            <img src="{{ asset('assets/img/logo.png') }}" alt="Taksh - All in one Ecommerce, Logistics & Services" class="h-9 min-[360px]:h-10 sm:h-14 w-auto">
        </a>

        <button type="button" data-open-location class="hidden xl:flex items-center gap-2 text-left bg-orange-50/60 hover:bg-orange-100/80 px-3 py-2 rounded-xl border border-orange-200/60 transition shrink-0">
            <div class="w-8 h-8 rounded-lg bg-white flex items-center justify-center text-brand-500 shadow-sm"><i data-lucide="map-pin" class="w-4 h-4"></i></div>
            <div>
                <div class="text-[10px] text-gray-500 font-semibold uppercase tracking-wider flex items-center gap-1">Deliver to <i data-lucide="chevron-down" class="w-3 h-3"></i></div>
                <div id="hdr-location" data-server="1" data-pincode="{{ $deliverTo['pincode'] }}" data-address-id="{{ $deliverTo['address_id'] ?? '' }}" class="text-xs font-bold text-gray-800 truncate max-w-[130px]">{{ $deliverTo['label'] ?: 'Set location' }}</div>
            </div>
        </button>

        <form id="search-form" action="{{ route('listing') }}" method="GET" class="order-last lg:order-none w-full lg:w-auto lg:flex-1 relative" role="search" autocomplete="off">
            <div class="flex items-center bg-gray-50 focus-within:bg-white rounded-2xl border-2 border-orange-200/80 focus-within:border-brand-500 transition overflow-hidden">
                <i data-lucide="search" class="w-5 h-5 text-gray-400 ml-4 mr-2 shrink-0"></i>
                <input id="search-input" name="q" type="search" value="{{ request()->routeIs('listing') ? request()->query('q') : '' }}" placeholder="Search for products, brands and more" class="w-full py-2.5 text-sm bg-transparent focus:outline-none text-gray-800 placeholder-gray-400 font-medium">
                <button type="submit" class="bg-gradient-to-r from-brand-500 to-brand-600 text-white px-5 py-2.5 font-semibold text-sm hover:brightness-110 transition">Search</button>
            </div>
            <div id="search-popup" class="hidden absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl shadow-2xl border border-gray-100 z-50 p-2 max-h-96 overflow-y-auto"></div>
        </form>

        <div class="flex items-center gap-0.5 sm:gap-3 ml-auto lg:ml-0 shrink-0">
            <a href="{{ route('notifications') }}" class="relative p-2 sm:p-2.5 rounded-xl hover:bg-orange-50 text-gray-700 hover:text-brand-600 transition" aria-label="Notifications">
                <i data-lucide="bell" class="w-6 h-6"></i>
                <span id="hdr-notif-dot" class="hidden absolute top-2 right-2 w-2.5 h-2.5 bg-brand-500 rounded-full ring-2 ring-white"></span>
            </a>
            <a href="{{ route('wishlist') }}" class="relative p-2 sm:p-2.5 rounded-xl hover:bg-orange-50 text-gray-700 hover:text-brand-600 transition" aria-label="Wishlist">
                <i data-lucide="heart" class="w-6 h-6"></i>
                <span id="hdr-wish-count" class="{{ $wishCount ? '' : 'hidden' }} absolute -top-0.5 -right-0.5 bg-gray-900 text-white text-[10px] font-bold min-w-[20px] h-5 px-1 rounded-full flex items-center justify-center border-2 border-white">{{ $wishCount }}</span>
            </a>
            <a href="{{ route('cart') }}" class="flex items-center gap-3 bg-gradient-to-r from-brand-500 to-brand-600 text-white px-3 sm:px-4 py-2 sm:py-2.5 rounded-2xl mx-1 sm:mx-0 shadow-md hover:shadow-lg hover:brightness-105 transition active:scale-95" aria-label="My cart">
                <div class="relative">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                    <span id="hdr-cart-count" class="{{ $cartCount ? '' : 'hidden' }} absolute -top-2 -right-2 bg-yellow-400 text-gray-900 text-[10px] font-extrabold min-w-[16px] h-4 px-1 rounded-full flex items-center justify-center shadow">{{ $cartCount }}</span>
                </div>
                <div class="text-left leading-tight hidden sm:block">
                    <div class="text-[10px] uppercase font-bold text-orange-200">My Cart</div>
                    <div id="hdr-cart-total" class="text-xs font-extrabold">@rupee($headerState['cart_total'])</div>
                </div>
            </a>
            <a href="{{ $name !== '' ? route('profile') : route('login') }}" class="flex items-center gap-2 sm:pl-3 sm:border-l border-gray-200 hover:opacity-80 transition" aria-label="My Account">
                <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-orange-100 border-2 border-brand-500/40 text-brand-600 font-bold flex items-center justify-center">{{ $name !== '' ? \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($name, 0, 1)) : '👤' }}</div>
                <div class="hidden lg:block text-left leading-tight">
                    <div class="text-xs font-bold text-gray-800">{{ $name !== '' ? $name : 'Login / Sign up' }}</div>
                    <div class="text-[10px] text-gray-400 font-medium">My Account</div>
                </div>
            </a>
        </div>
    </div>

    <nav id="main-nav" class="bg-orange-50/60 border-t border-orange-100/80" aria-label="Main">
        <div class="max-w-7xl mx-auto px-4 lg:px-8 py-3 grid grid-cols-3 gap-2 sm:gap-4">
            @foreach ($tabs as [$href, $icon, $label, $active, $tint])
                <a href="{{ $href }}" @if ($active) aria-current="page" @endif class="nav-tab group flex items-center justify-center gap-2 sm:gap-3 px-2 sm:px-4 py-2.5 rounded-2xl text-xs min-[400px]:text-sm leading-tight text-center transition {{ $active ? 'bg-brand-500 text-white font-bold shadow' : 'text-gray-700 hover:bg-white font-semibold' }}">
                    <span class="hidden min-[400px]:flex w-8 h-8 shrink-0 rounded-xl items-center justify-center transition {{ $active ? 'bg-white/20 text-white' : $tint.' group-hover:scale-110' }}"><i data-lucide="{{ $icon }}" class="w-4 h-4"></i></span>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </nav>
</header>
