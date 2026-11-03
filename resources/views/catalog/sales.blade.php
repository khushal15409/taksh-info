@extends('layouts.app')

@section('title', 'Sales & Offers | TakshAllInOne')
@section('page', 'sales')

@section('content')
@if (! $sales)
    <x-empty-state emoji="🏷️" title="No sales running right now" text="Check back soon for new offers." cta-label="Start Shopping" :cta-href="route('listing')" />
@else
    <div class="space-y-6">
        <div><h1 class="text-2xl font-extrabold text-gray-900">Sales &amp; Offers 🏷️</h1><p class="text-xs text-gray-400">Running right now</p></div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($sales as $s)
                <a href="{{ route('sales.detail', $s['id']) }}" class="relative overflow-hidden rounded-3xl p-6 bg-gradient-to-r from-red-600 to-orange-500 text-white group hover:shadow-xl transition">
                    <div class="absolute -right-3 -bottom-5 text-[100px] opacity-25 group-hover:scale-110 transition">🏷️</div>
                    <span class="inline-block bg-white/20 text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full">{{ $s['discount_label'] }}</span>
                    <h3 class="font-festive font-extrabold text-xl mt-3 relative">{{ $s['name'] }}</h3>
                    <p class="text-xs text-orange-100 mt-1 relative">Ends {{ \Illuminate\Support\Carbon::parse($s['end_at'])->format('j M Y') }}</p>
                    <span class="inline-block mt-4 bg-white text-red-600 font-bold text-xs px-4 py-2 rounded-xl relative">View offer</span>
                </a>
            @endforeach
        </div>
    </div>
@endif
@endsection

@push('scripts')

@endpush
