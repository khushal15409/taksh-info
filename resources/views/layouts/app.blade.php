<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#FFFBEF]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>try{if(JSON.parse(localStorage.getItem("taksh_v2")).theme==="dark")document.documentElement.classList.add("dark")}catch(e){}</script>
    @if (app()->getLocale() !== 'en')<script>window.TAKSH_I18N = @json(app(\App\Services\HindiPage::class)->dictionary());</script>@endif
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'TakshAllInOne')</title>
    <meta name="description" content="TakshAllInOne - groceries, 15-minute delivery, festive decor, electronics and verified home services in one app.">
    <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/img/favicon.png') }}">

    <!-- Tailwind CSS (CDN) + theme config -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('assets/js/tailwind-config.js') }}"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom styles -->
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <style>[hidden]{display:none!important}</style>
</head>
<body class="font-sans antialiased text-gray-800 bg-[#FFFBEF] min-h-screen flex flex-col selection:bg-brand-500 selection:text-white" data-root="{{ url('/') }}/" data-page="@yield('page', 'home')" data-quick="{{ request()->routeIs('listing') && request()->query('quick') === '1' ? 1 : 0 }}" data-version="{{ config('taksh.version') }}" data-cart-count="{{ $headerState['cart_count'] }}" data-cart-total="{{ $headerState['cart_total'] }}" data-wish-ids="{{ json_encode($headerState['wish_ids']) }}" data-otp-length="{{ \App\Helpers\Otp::length() }}" data-user="{{ $authUser ? json_encode($authUser) : '' }}">

    @include('layouts.partials.header')

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 lg:px-8 py-6">
@yield('content')
    </main>

    <div id="site-footer">@include('layouts.partials.footer')</div>

    <!-- Scripts: shared client code (preferences, auth, API calls, the header counters and hearts), then the page's own -->
    <script src="{{ asset('assets/js/prefs.js') }}"></script>
    <script src="{{ asset('assets/js/auth.js') }}"></script>
    <script src="{{ asset('assets/js/api.js') }}"></script>
    <script src="{{ asset('assets/js/header-state.js') }}"></script>
    <script src="{{ asset('assets/js/addresses.js') }}"></script>
    <script src="{{ asset('assets/js/i18n-hi.js') }}"></script>
    <script src="{{ asset('assets/js/ui.js') }}"></script>
    <script src="{{ asset('assets/js/modules/components.js') }}"></script>
    @stack('scripts')
    <x-flash />
</body>
</html>
