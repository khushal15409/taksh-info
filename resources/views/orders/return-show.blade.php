@extends('layouts.app')

@section('title', 'Return status | TakshAllInOne')
@section('page', 'returns')

@section('content')
@if (! $return)
    <x-empty-state emoji="↩️" title="Return not found" text="We could not find that return request in your account." cta-label="My Returns" :cta-href="route('profile', ['tab' => 'returns'])" />
@else
    @php($when = fn ($iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->format('j M Y, h:i A') : '')
    <div class="max-w-3xl mx-auto bg-white p-6 lg:p-8 rounded-3xl border border-orange-100 shadow-sm space-y-5 text-xs">
        <a href="{{ route('profile', ['tab' => 'returns']) }}" class="text-xs font-bold text-gray-500 hover:text-brand-600">← My Returns</a>
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="font-extrabold text-xl text-gray-900">{{ $return['product_name'] ?? 'Item' }} × {{ $track['quantity'] }}</h1>
                <div class="text-gray-400 mt-1">Order #{{ $return['order_number'] ?? '' }} • {{ $return['resolution_label'] ?? '' }}</div>
            </div>
            <x-status-badge :status="$return['status']" :label="$return['status_label']" class="!text-[11px] !px-2.5 !py-1" />
        </div>
        <p class="text-gray-600"><b>Reason:</b> {{ $return['reason'] }}</p>
        @if (! empty($return['rejection_reason']))<p class="text-red-600 font-semibold">Rejected: {{ $return['rejection_reason'] }}</p>@endif
        @if ($track['pickup'])<div class="bg-sky-50 rounded-2xl p-3"><b>Pickup:</b> {{ $track['pickup']['status_label'] }} • Tracking {{ $track['pickup']['tracking_id'] }}</div>@endif
        @if (! empty($return['refund']))
            <div class="bg-emerald-50 rounded-2xl p-3"><b>Refund:</b> @rupee2($return['refund']['amount']) • {{ $return['refund']['status'] ?? '' }}@if (! empty($return['refund']['processed_at'])) • {{ $when($return['refund']['processed_at']) }}@endif</div>
        @endif
        @if (! empty($return['media']))
            <div class="flex gap-2">@foreach ($return['media'] as $m)<a target="_blank" rel="noopener" href="{{ $m['file_url'] }}"><img src="{{ $m['file_url'] }}" alt="" loading="lazy" class="w-16 h-16 rounded-xl object-cover border"></a>@endforeach</div>
        @endif
        <div class="relative pl-8 space-y-4">
            <div class="timeline-line" style="left:11px"></div>
            @foreach ($track['status_history'] as $h)
                <div class="relative">
                    <span class="absolute -left-[29px] top-0 w-3.5 h-3.5 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></span>
                    <div class="font-bold text-gray-900">{{ $h['status_label'] }}</div>
                    <div class="text-gray-400">{{ $when($h['created_at']) }}@if ($h['notes']) • {{ $h['notes'] }}@endif</div>
                </div>
            @endforeach
        </div>
    </div>
@endif
@endsection
