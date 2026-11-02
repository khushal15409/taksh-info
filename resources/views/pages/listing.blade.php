@extends('layouts.app')

@section('title', 'Shop Groceries & Essentials | TakshAllInOne')
@section('page', 'listing')

@section('content')

        <nav class="text-xs text-gray-500 flex items-center gap-2 mb-5" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="hover:text-brand-500">Home</a><span>/</span><span id="crumb-current" class="text-gray-800 font-bold">Products</span>
        </nav>
        <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
            <div><h1 id="page-title" class="text-2xl font-extrabold text-gray-900">All Products</h1>
                <p class="text-xs text-gray-500 mt-1">Showing <strong id="count-label" class="text-gray-900">0</strong> products</p></div>
            <div class="flex items-center gap-3">
                <button id="filter-toggle" class="lg:hidden flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-4 py-2 text-xs font-bold"><i data-lucide="sliders-horizontal" class="w-4 h-4"></i>Filters</button>
                <div class="flex items-center gap-2 text-xs"><span class="text-gray-400 font-medium">Sort by:</span>
                    <select id="sort-select" class="bg-white border border-gray-200 rounded-xl py-2 px-3 text-xs font-bold text-gray-800 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        <option value="relevance">Relevance</option><option value="popular">Popularity</option><option value="price-asc">Price: Low to High</option>
                        <option value="price-desc">Price: High to Low</option><option value="rating">Top Rated</option><option value="discount">Biggest Discount</option><option value="name">Name (A-Z)</option>
                    </select></div>
            </div>
        </div>
        <div id="chips" class="flex flex-wrap gap-2 mb-5 empty:hidden"></div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <aside id="filters" class="hidden lg:block bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-6 h-fit lg:sticky lg:top-40">
                <div class="flex items-center justify-between border-b pb-4 border-gray-100">
                    <h3 class="font-extrabold text-base text-gray-900 flex items-center gap-2"><i data-lucide="filter" class="w-4 h-4 text-brand-500"></i>Filters</h3>
                    <button id="reset-filters" class="text-xs font-semibold text-brand-600 hover:underline">Reset All</button>
                </div>
                <div class="space-y-2.5 text-xs">
                    <label class="flex items-center gap-2 cursor-pointer font-semibold text-gray-700"><input id="f-quick" type="checkbox" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500"><span>⚡ Quick delivery (15 min)</span></label>
                    <label class="flex items-center gap-2 cursor-pointer font-semibold text-gray-700"><input id="f-deals" type="checkbox" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500"><span>🪔 15%+ discount only</span></label>
                </div>
                <div class="space-y-2"><h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Categories</h4><div id="filter-cats" class="space-y-1.5 text-xs"></div></div>
                <div class="space-y-2"><h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Brand</h4><div id="filter-brands" class="space-y-1.5 text-xs max-h-52 overflow-y-auto pr-1"></div></div>
                <div class="space-y-2"><h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Max Price</h4>
                    <input id="price-range" type="range" min="50" step="50" class="w-full">
                    <div class="flex justify-between text-xs text-gray-500 font-semibold"><span>₹50</span><span id="price-val" class="text-brand-600 font-extrabold"></span><span id="price-max-label"></span></div></div>
                <div class="space-y-2"><h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Customer Rating</h4>
                    <div class="space-y-1.5 text-xs"><label class="flex items-center gap-2 cursor-pointer text-gray-600"><input type="radio" name="rating" value="0" class="accent-brand-500"><span>All ratings</span></label><label class="flex items-center gap-2 cursor-pointer text-gray-600"><input type="radio" name="rating" value="4" class="accent-brand-500"><span>4★ &amp; above</span></label><label class="flex items-center gap-2 cursor-pointer text-gray-600"><input type="radio" name="rating" value="4.5" class="accent-brand-500"><span>4.5★ &amp; above</span></label></div></div>
            </aside>
            <section id="results" class="lg:col-span-3 min-h-[300px]"></section>
        </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/pages/listing.js') }}"></script>
@endpush
