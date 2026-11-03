@extends('layouts.app')

@if ($booking)
    @section('title', 'Courier #'.$booking['booking_number'].' | TakshAllInOne')
@else
    @section('title', 'Courier booking not found | TakshAllInOne')
@endif
@section('page', 'services')

@section('content')
@if (! $booking)
    <x-empty-state emoji="📮" title="Courier booking not found" text="We could not find that booking in your account." cta-label="Services" :cta-href="route('services').'#courier'" />
@else
    @php
        $b = $booking;
        $p = $b['pricing'] ?? [];
        $cancelled = $b['status'] === 'cancelled';
        $paid = $b['payment_status'] === 'paid';
        $unpaid = ! $paid && ! $cancelled && ! empty($b['courier']['courier_company_id']);
        $when = fn ($s) => $s ? \Illuminate\Support\Carbon::parse($s)->format('j M, h:i A') : '';
        $pretty = fn ($s) => \Illuminate\Support\Str::of((string) $s)->replace('_', ' ')->title();
    @endphp
    <div class="max-w-2xl mx-auto space-y-4">
        <a href="{{ route('services') }}#courier" class="text-xs font-bold text-gray-500 hover:text-brand-600">← Courier</a>
        <div class="bg-white p-6 lg:p-8 rounded-3xl border border-blue-100 shadow-sm space-y-4 text-xs">
            <div class="flex items-center justify-between gap-4">
                <div class="font-extrabold text-xl text-gray-900">{{ $b['pickup_pincode'] }} → {{ $b['delivery_pincode'] }}<div class="text-xs font-medium text-gray-400 mt-1">#{{ $b['booking_number'] }}</div></div>
                <x-status-badge :status="$cancelled ? 'cancelled' : ($b['shipment']['status'] ?: $b['payment_status'])" :label="$cancelled ? 'Cancelled' : ($b['shipment']['status_label'] ?: ($paid ? 'Paid' : 'Payment pending'))" />
            </div>

            @if ($unpaid)
                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 space-y-2">
                    <div class="font-bold text-amber-900">Payment pending</div>
                    <p class="text-amber-800">Pay @rupee($b['total_amount']) to book the pickup.</p>
                    <button type="button" id="pay-now" data-kind="courier" data-booking="{{ $b['id'] }}" data-start="{{ $autostart ? 1 : 0 }}" data-description="Courier booking" class="bg-brand-500 hover:bg-brand-600 text-white font-bold px-5 py-2.5 rounded-xl disabled:opacity-60">Pay now</button>
                </div>
            @endif

            <div class="grid grid-cols-2 gap-3">
                <div class="bg-gray-50 rounded-2xl p-3"><div class="font-bold text-gray-600">Pickup</div><div class="text-gray-500">{{ $b['customer_name'] }}, {{ $b['customer_mobile'] }}<br>{{ $b['pickup_address'] }}</div></div>
                <div class="bg-gray-50 rounded-2xl p-3"><div class="font-bold text-gray-600">Deliver to</div><div class="text-gray-500">{{ $b['delivery_name'] }}, {{ $b['delivery_mobile'] }}<br>{{ $b['delivery_address'] }}</div></div>
            </div>
            @if (! empty($b['courier']['name']))<div class="flex justify-between"><span class="text-gray-500">Courier</span><b>{{ $b['courier']['name'] }}@if ($b['courier']['estimated_delivery_days']) • {{ $b['courier']['estimated_delivery_days'] }} day(s)@endif</b></div>@endif
            @if ($b['awb_code'])<div class="flex justify-between"><span class="text-gray-500">AWB</span><b>{{ $b['awb_code'] }}</b></div>@endif
            @if ($b['tracking_url'])<a href="{{ $b['tracking_url'] }}" target="_blank" rel="noopener" class="text-brand-600 font-bold">Open courier tracking page ↗</a>@endif
            <div class="flex justify-between"><span class="text-gray-500">Chargeable weight</span><b>{{ (float) $b['chargeable_weight_grams'] }} g</b></div>
            @if (($p['shiprocket_cost'] ?? null) !== null)<div class="flex justify-between"><span class="text-gray-500">Shipping + service charge</span><span>@rupee($p['shiprocket_cost']) + @rupee($p['service_charge'])</span></div>@endif
            <div class="flex justify-between"><span class="text-gray-500">Total</span><b>@rupee($b['total_amount'])</b></div>
            @if (! empty($b['item_photo_path']))<img src="{{ $b['item_photo_path'] }}" alt="Parcel" loading="lazy" class="w-24 h-24 rounded-xl object-cover border">@endif
            @if ($b['refund'])<div class="bg-amber-50 rounded-2xl p-3">Refund @rupee($b['refund']['amount']) • {{ $pretty($b['refund']['status']) }}</div>@endif
            @if ($cancelled)<div class="text-gray-500">Cancelled {{ $when($b['cancellation']['cancelled_at']) }}@if ($b['cancellation']['reason']) • {{ $b['cancellation']['reason'] }}@endif</div>@endif

            @if ($b['tracking_timeline'])
                <ul class="space-y-3 border-t border-gray-100 pt-3">
                    @foreach (array_reverse($b['tracking_timeline']) as $t)
                        <li class="flex gap-3"><span class="w-2 h-2 rounded-full bg-blue-500 mt-1.5 shrink-0"></span><div><div class="font-bold text-gray-800">{{ $t['title'] }}</div>@if ($t['description'])<div class="text-gray-500">{{ $t['description'] }}</div>@endif<div class="text-[10px] text-gray-400">{{ collect([$t['location'], $when($t['event_time'])])->filter()->implode(' • ') }}</div></div></li>
                    @endforeach
                </ul>
            @else
                <p class="text-gray-400 border-t border-gray-100 pt-3">Tracking updates will appear here once the courier picks up your parcel.</p>
            @endif

            <div class="flex flex-wrap gap-3 pt-2">
                @if ($b['awb_code'] && ! $cancelled)<form method="POST" action="{{ route('services.courier.track', $b['id']) }}" data-lock class="flex-1">@csrf<button class="w-full border border-blue-500 text-blue-600 font-bold py-2.5 rounded-xl disabled:opacity-60">Refresh tracking</button></form>@endif
                @if ($paid)<a href="{{ route('service.courier.invoice', $b['id']) }}" target="_blank" rel="noopener" class="flex-1 text-center border border-brand-500 text-brand-600 font-bold py-2.5 rounded-xl">Invoice</a>@endif
                @if ($b['can_cancel'])<button type="button" data-modal-open="cancel-courier" class="flex-1 bg-red-50 text-red-600 font-bold py-2.5 rounded-xl">Cancel</button>@endif
            </div>
        </div>
    </div>

    @if ($b['can_cancel'])
        <x-modal id="cancel-courier" title="Cancel this courier booking?">
            <form method="POST" action="{{ route('services.courier.cancel', $b['id']) }}" data-lock class="space-y-3 text-xs">
                @csrf
                <p class="text-gray-500">You can cancel until the courier picks up the parcel. Your payment is refunded to the original payment method as per our refund policy.</p>
                <textarea name="reason" rows="2" maxlength="500" class="field" placeholder="Reason (optional)"></textarea>
                <button class="w-full bg-red-600 hover:bg-red-700 text-white font-bold text-sm py-3 rounded-2xl disabled:opacity-60">Yes, cancel booking</button>
            </form>
        </x-modal>
    @endif
@endif
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/pay.js') }}"></script>
    <script src="{{ asset('assets/js/modules/service-pay.js') }}"></script>
    <script src="{{ asset('assets/js/modules/support.js') }}"></script>
@endpush
