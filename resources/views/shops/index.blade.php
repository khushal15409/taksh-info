@extends('layouts.app')

@section('title', 'Shops | TakshAllInOne')
@section('page', 'shops')

@section('content')
    <nav class="text-[11px] text-gray-500 mb-3" aria-label="Breadcrumb"><a href="{{ route('home') }}" class="hover:text-brand-600">Home</a> <span class="mx-1">›</span> <span class="font-bold text-brand-600">Shops</span></nav>

    {{-- Title with the shop picture --}}
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-orange-100/80 via-orange-50 to-amber-50 border border-orange-100 mb-5 px-5 sm:px-8 py-5 sm:py-6">
        <div class="relative flex items-center justify-between gap-4">
            <div class="flex items-center gap-4 min-w-0">
                <span class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl btn-saffron flex items-center justify-center shrink-0"><i data-lucide="store" class="w-6 h-6 sm:w-7 sm:h-7"></i></span>
                <div class="min-w-0"><h1 class="font-festive font-extrabold text-2xl sm:text-4xl text-gray-900 leading-tight">Shops</h1><p class="text-xs sm:text-sm text-gray-600">Discover and shop from a wide range of trusted stores</p></div>
            </div>
            <img src="{{ asset('assets/img/shops-hero.svg') }}" alt="" class="hidden sm:block h-24 md:h-32 w-auto shrink-0 -my-2" loading="lazy">
        </div>
    </section>

    {{-- Count, category, location and search: one POST form, so the choices never go into the address bar (live: it updates as you pick or type) --}}
    <form action="{{ route('shops') }}" method="POST" role="search" data-live-search class="mb-5 grid grid-cols-1 sm:grid-cols-2 lg:flex lg:flex-nowrap items-stretch lg:items-center gap-3">
        @csrf
        <div class="sm:col-span-2 lg:col-auto flex items-center gap-2.5 bg-orange-50 border border-orange-100 rounded-2xl px-4 py-2.5 text-sm font-extrabold text-brand-700 shrink-0"><i data-lucide="shopping-basket" class="w-5 h-5 text-brand-500"></i><span id="live-count">{{ number_format($page->total()) }} {{ $page->total() === 1 ? 'Shop' : 'Shops' }} Found</span></div>

        <label class="relative flex items-center bg-white border border-gray-200 focus-within:border-brand-500 rounded-2xl shadow-sm min-w-0 lg:w-56">
            <i data-lucide="layout-grid" class="w-4 h-4 text-gray-500 absolute left-3.5 pointer-events-none"></i>
            <select name="category" aria-label="Category" class="w-full appearance-none bg-transparent pl-10 pr-9 py-3 text-sm font-semibold text-gray-800 focus:outline-none cursor-pointer truncate">
                <option value="">All Categories</option>
                @foreach ($categories as $c)<option value="{{ $c['id'] }}" @selected($category === $c['id'])>{{ $c['name'] }}</option>@endforeach
            </select>
            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-500 absolute right-3.5 pointer-events-none"></i>
        </label>

        <label class="relative flex items-center bg-white border border-gray-200 focus-within:border-brand-500 rounded-2xl shadow-sm min-w-0 lg:w-56">
            <i data-lucide="map-pin" class="w-4 h-4 text-gray-500 absolute left-3.5 pointer-events-none"></i>
            <select name="location" aria-label="Location" class="w-full appearance-none bg-transparent pl-10 pr-9 py-3 text-sm font-semibold text-gray-800 focus:outline-none cursor-pointer truncate">
                <option value="">All Locations</option>
                @foreach ($locations as $l)<option value="{{ $l['value'] }}" @selected($location !== null && mb_strtolower($location) === mb_strtolower($l['value']))>{{ $l['label'] }}</option>@endforeach
            </select>
            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-500 absolute right-3.5 pointer-events-none"></i>
        </label>

        <div class="sm:col-span-2 lg:col-auto lg:flex-1 flex items-center bg-white border border-gray-200 focus-within:border-brand-500 rounded-2xl overflow-hidden shadow-sm min-w-0">
            <i data-lucide="search" class="w-4 h-4 text-gray-400 ml-3.5 shrink-0"></i>
            <input type="search" name="q" value="{{ $q }}" autocomplete="off" placeholder="Search shops by name" aria-label="Search shops" class="flex-1 min-w-0 px-3 py-3 text-sm bg-transparent focus:outline-none">
            <button class="btn-saffron px-4 py-3 shrink-0" aria-label="Search shops"><i data-lucide="search" class="w-4 h-4"></i></button>
        </div>
    </form>

    <section id="live-area" class="min-w-0 min-h-[300px]">
        @if ($cards)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-4">@foreach ($cards as $s)@include('shops.partials.list-card', ['s' => $s])@endforeach</div>
            <x-pagination :paginator="$page" class="pt-8" />
        @else
            <x-empty-state emoji="🏪" title="No shops found" text="Try another category, location or name." cta-label="Show all shops" :cta-href="route('shops')" />
        @endif
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/modules/shops.js') }}"></script>
@endpush
