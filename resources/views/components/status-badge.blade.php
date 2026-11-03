{{-- <x-status-badge status="provider_assigned" /> or with a label: <x-status-badge status="paid" label="Paid online" /> --}}
@props(['status', 'label' => null])
@php
    $tones = [
        'success' => ['delivered', 'completed', 'paid', 'success', 'processed', 'refunded', 'approved', 'captured'],
        'info' => ['confirmed', 'provider_assigned', 'shipped', 'in_transit', 'out_for_delivery', 'processing', 'picked_up', 'pickup_requested', 'awb_assigned', 'order_created', 'authorized'],
        'purple' => ['provider_accepted', 'in_progress'],
        'warn' => ['pending', 'initiated', 'pending_payment', 'provider_rejected', 'partially_refunded', 'open'],
        'danger' => ['cancelled', 'failed', 'rejected', 'returned', 'exception', 'rto_initiated', 'rto_in_transit', 'rto_delivered'],
    ];
    $classes = [
        'success' => 'bg-emerald-100 text-emerald-700', 'info' => 'bg-sky-100 text-sky-700', 'purple' => 'bg-purple-100 text-purple-700',
        'warn' => 'bg-amber-100 text-amber-700', 'danger' => 'bg-red-100 text-red-700',
    ];
    $tone = collect($tones)->search(fn ($list) => in_array($status, $list, true));
    $text = $label ?? \Illuminate\Support\Str::of($status)->replace('_', ' ')->title();
@endphp
<span {{ $attributes->merge(['class' => ($classes[$tone] ?? 'bg-gray-100 text-gray-600').' text-[10px] font-extrabold px-2 py-0.5 rounded-full uppercase whitespace-nowrap']) }}>{{ $text }}</span>
