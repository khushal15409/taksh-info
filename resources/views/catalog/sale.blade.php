@extends('layouts.app')

@if (! empty($sale))
    @section('title', $sale['name'].' | TakshAllInOne')
@else
    @section('title', 'Sale | TakshAllInOne')
@endif
@section('page', 'sales')

@section('content')
@if (! $sale)
    <x-empty-state emoji="😕" title="This sale has ended" text="It may no longer be running." cta-label="All offers" :cta-href="route('sales')" />
@else
    <div class="space-y-6">
        <div>
            <a href="{{ route('sales') }}" class="text-xs font-bold text-gray-500 hover:text-brand-600">← All offers</a>
            <div class="rounded-3xl bg-gradient-to-r from-red-600 to-orange-500 text-white p-8 mt-2">
                <span class="bg-white/20 text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full">{{ $sale['discount_label'] }}</span>
                <h1 class="font-festive font-extrabold text-2xl lg:text-3xl mt-3">{{ $sale['name'] }}</h1>
                <p class="text-xs text-orange-100 mt-1">Valid till {{ \Illuminate\Support\Carbon::parse($sale['end_at'])->format('j M Y') }}</p>
            </div>
        </div>
        @if ($products)
            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 lg:gap-5">
                @foreach ($products as $p)
                    <article class="product-card group bg-white rounded-3xl border border-orange-100 hover:border-brand-500/40 hover:shadow-card-hover transition flex flex-col overflow-hidden">
                        <a href="{{ route('product', ['id' => $p['id']]) }}" class="block bg-gradient-to-b from-orange-50/60 to-white p-3 relative">
                            @if (! empty($p['discount_label']))<span class="absolute top-3 left-3 z-10 bg-brand-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $p['discount_label'] }}</span>@endif
                            @if (! empty($p['image_url']))<img src="{{ $p['image_url'] }}" alt="{{ $p['name'] }}" loading="lazy" class="w-full aspect-square object-contain">@else<div class="w-full aspect-square flex items-center justify-center text-5xl">🛍️</div>@endif
                        </a>
                        <div class="p-4 pt-1 space-y-2">
                            <a href="{{ route('product', ['id' => $p['id']]) }}" class="font-bold text-[13px] text-gray-800 line-clamp-2 hover:text-brand-600 min-h-[2.4rem] block">{{ $p['name'] }}</a>
                            <div class="flex items-end gap-2"><span class="font-extrabold text-base text-gray-900">@rupee($p['sale_price'])</span>@if ($p['sale_price'] < $p['original_price'])<span class="text-xs text-gray-400 line-through pb-0.5">@rupee($p['original_price'])</span>@endif</div>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <x-empty-state emoji="🏷️" title="No products to show yet" text="Products for this offer will appear here." cta-label="Start Shopping" :cta-href="route('listing')" />
        @endif
        @if (! empty($sale['categories']))
            <div>
                <h2 class="font-extrabold text-gray-900 mb-3">Categories on offer</h2>
                <div class="flex flex-wrap gap-2">@foreach ($sale['categories'] as $c)<a href="{{ route('listing', ['category' => $c['id']]) }}" class="bg-white border border-orange-200 text-brand-600 font-bold text-xs px-4 py-2 rounded-full hover:bg-orange-50">{{ $c['name'] }}</a>@endforeach</div>
            </div>
        @endif
    </div>
@endif
@endsection

@push('scripts')

@endpush
