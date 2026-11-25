@extends('layouts.app')

@section('title', 'Shops | TakshAllInOne')
@section('page', 'shops')

@section('content')
    <nav class="text-[11px] text-gray-500 mb-3" aria-label="Breadcrumb"><a href="{{ route('home') }}" class="hover:text-brand-600">Home</a> <span class="mx-1">›</span> <span class="font-bold text-brand-600">Shops</span></nav>

    <div class="flex items-center gap-3 mb-6">
        <span class="w-12 h-12 rounded-2xl btn-saffron flex items-center justify-center"><i data-lucide="store" class="w-6 h-6"></i></span>
        <div><h1 class="font-festive font-extrabold text-2xl text-gray-900">Shops</h1><p class="text-xs text-gray-500">Discover and shop from a wide range of trusted stores</p></div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <h2 class="font-extrabold text-sm text-gray-900"><span id="live-count">{{ number_format($page->total()) }} {{ $page->total() === 1 ? 'Shop' : 'Shops' }} Found</span></h2>
        <form action="{{ route('shops') }}" method="POST" role="search" data-live-search class="w-full sm:w-[380px] flex items-center bg-white border border-orange-200 focus-within:border-brand-500 rounded-2xl overflow-hidden shadow-sm">
            @csrf
            <i data-lucide="search" class="w-4 h-4 text-gray-400 ml-3"></i>
            <input type="search" name="q" value="{{ $q }}" autocomplete="off" placeholder="Search for shops (e.g. Reliance, Croma, DMart...)" aria-label="Search shops" class="flex-1 px-3 py-3 text-sm bg-transparent focus:outline-none">
            <button class="btn-saffron px-4 py-3" aria-label="Search shops"><i data-lucide="search" class="w-4 h-4"></i></button>
        </form>
    </div>

    <section id="live-area" class="min-w-0 min-h-[300px]">
        @if ($cards)
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 lg:gap-4">@foreach ($cards as $s)@include('shops.partials.card', ['s' => $s])@endforeach</div>
            <x-pagination :paginator="$page" class="pt-8" />
        @else
            <x-empty-state emoji="🏪" title="No shops found" text="Try a different name." cta-label="Show all shops" :cta-href="route('shops')" />
        @endif
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/modules/shops.js') }}"></script>
@endpush
