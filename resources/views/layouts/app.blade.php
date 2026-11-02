<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#FFFBEF]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
</head>
<body class="font-sans antialiased text-gray-800 bg-[#FFFBEF] min-h-screen flex flex-col selection:bg-brand-500 selection:text-white" data-root="{{ url('/') }}/" data-page="@yield('page', 'home')">

    <!-- Header, nav & coming-soon banner are rendered by public/assets/js/ui.js -->
    <div id="site-header" class="contents"></div>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 lg:px-8 py-6">
@yield('content')
    </main>

    <!-- Footer is rendered by public/assets/js/ui.js -->
    <div id="site-footer"></div>

    <!-- Scripts: catalogue -> data -> store -> shared UI -> page logic -->
    <script src="{{ asset('assets/js/products.js') }}"></script>
    <script src="{{ asset('assets/js/data.js') }}"></script>
    <script src="{{ asset('assets/js/store.js') }}"></script>
    <script src="{{ asset('assets/js/ui.js') }}"></script>
    @stack('scripts')
</body>
</html>
