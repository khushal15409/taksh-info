@extends('layouts.app')

@section('title', $notification['title'].' | TakshAllInOne')
@section('page', 'notifications')

@section('content')
    @php
        $icons = ['offer' => '🏷️', 'promotion' => '🎁', 'order' => '📦', 'delivery' => '🚚', 'payment' => '💳', 'review' => '⭐', 'app' => '🔔'];
        $cta = ['offer' => ['listing', 'Shop now'], 'promotion' => ['listing', 'Shop now'], 'order' => ['profile', 'View my orders'], 'delivery' => ['profile', 'View my orders']][$notification['type']] ?? null;
    @endphp
    <div class="max-w-xl mx-auto space-y-4">
        <a href="{{ route('notifications') }}" class="text-xs font-bold text-gray-500 hover:text-brand-600">← All notifications</a>
        <div class="bg-white rounded-3xl p-8 border border-orange-100 shadow-sm text-center space-y-4">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-brand-500 text-white flex items-center justify-center text-3xl">{{ $icons[$notification['type']] ?? $icons['app'] }}</div>
            <h1 class="text-xl font-extrabold text-gray-900">{{ $notification['title'] }}</h1>
            <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line">{{ $notification['message'] }}</p>
            <p class="text-xs text-gray-400">{{ $notification['sent_at'] ? \Illuminate\Support\Carbon::parse($notification['sent_at'])->format('j M Y, h:i A') : '' }}</p>
            @if ($cta)
                <a href="{{ route($cta[0]) }}" class="inline-block bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm px-6 py-3 rounded-2xl">{{ $cta[1] }}</a>
            @endif
        </div>
    </div>
@endsection
