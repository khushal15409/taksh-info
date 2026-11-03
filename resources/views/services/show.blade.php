@extends('layouts.app')

@if ($booking)
    @section('title', 'Booking #'.$booking['booking_number'].' | TakshAllInOne')
@else
    @section('title', 'Booking not found | TakshAllInOne')
@endif
@section('page', 'services')

@section('content')
@if (! $booking)
    <x-empty-state emoji="🛠️" title="Booking not found" text="We could not find that booking in your account." cta-label="My bookings" :cta-href="route('services').'#bookings'" />
@else
    @php
        $b = $booking;
        $a = $b['address'] ?? [];
        $p = $b['payment'] ?? [];
        $paid = $b['payment_status'] === 'paid';
        $when = fn ($iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->format('j M Y, h:i A') : '';
        $pretty = fn ($s) => \Illuminate\Support\Str::of((string) $s)->replace('_', ' ')->title();
    @endphp
    <div class="max-w-2xl mx-auto space-y-4">
        <a href="{{ route('services') }}#bookings" class="text-xs font-bold text-gray-500 hover:text-brand-600">← My bookings</a>
        <div class="bg-white p-6 lg:p-8 rounded-3xl border border-purple-100 shadow-sm space-y-5 text-xs">
            <div class="flex items-center justify-between gap-4">
                <div><h1 class="font-extrabold text-xl text-gray-900">{{ $b['service']['name'] }}</h1><div class="text-gray-400 mt-1">#{{ $b['booking_number'] }} • {{ $when($b['scheduled_for']) ?: 'Visit time to be confirmed' }}</div></div>
                <x-status-badge :status="$b['status']" />
            </div>

            @if ($b['pending_payment'])
                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 space-y-2">
                    <div class="font-bold text-amber-900">Payment pending</div>
                    <p class="text-amber-800">Pay @rupee($b['service_charge']) to confirm this booking.</p>
                    <button type="button" id="pay-now" data-kind="service" data-start="{{ $autostart ? 1 : 0 }}" data-key="{{ $key }}" data-order="{{ $b['pending_payment']['razorpay_order_id'] }}" data-amount="{{ $b['pending_payment']['amount_in_paise'] }}" data-currency="{{ $b['pending_payment']['currency'] }}" data-description="{{ $b['service']['name'] }} booking"
                        class="bg-brand-500 hover:bg-brand-600 text-white font-bold px-5 py-2.5 rounded-xl disabled:opacity-60">Pay now</button>
                </div>
            @elseif (! $paid && $b['status'] === 'pending')
                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 space-y-2">
                    <div class="font-bold text-amber-900">Payment not completed</div>
                    <form method="POST" action="{{ route('services.bookings.repay', $b['id']) }}" data-lock>@csrf<button class="bg-brand-500 hover:bg-brand-600 text-white font-bold px-5 py-2.5 rounded-xl disabled:opacity-60">Pay again</button></form>
                </div>
            @endif

            <div class="bg-gray-50 rounded-2xl p-3 space-y-1"><div class="font-bold text-gray-600">Address</div><div class="text-gray-500">{{ collect([$a['address_line_1'] ?? null, $a['address_line_2'] ?? null, $a['landmark'] ?? null, $a['city'] ?? null, $a['state'] ?? null, $a['pincode'] ?? null])->filter()->implode(', ') }}</div></div>
            @if ($b['provider'])
                <div class="bg-purple-50 rounded-2xl p-3"><div class="font-bold text-gray-700">{{ $b['provider']['name'] ?? 'Your professional' }} <span class="text-purple-600">({{ $b['provider']['status'] }})</span></div>
                    @if (! empty($b['provider']['mobile']))<a class="text-brand-600 font-bold" href="tel:{{ $b['provider']['mobile'] }}">📞 {{ $b['provider']['mobile'] }}</a>@else<div class="text-gray-400">Contact number is shared once they accept the job.</div>@endif</div>
            @endif
            <div class="flex justify-between"><span class="text-gray-500">Service charge</span><b>@rupee($b['service_charge'])</b></div>
            <div class="flex justify-between items-center"><span class="text-gray-500">Payment</span><span><x-status-badge :status="$p['status'] ?? $b['payment_status']" /> @if (! empty($p['paid_amount'])) @rupee($p['paid_amount']) @endif</span></div>
            @if (! empty($p['refund']))<div class="bg-amber-50 rounded-2xl p-3">Refund @rupee($p['refund']['amount']) • {{ $pretty($p['refund']['status']) }}@if ($p['refund']['refunded_amount']) (@rupee($p['refund']['refunded_amount']) refunded)@endif</div>@endif
            @if ($b['customer_notes'])<div class="text-gray-500">📝 {{ $b['customer_notes'] }}</div>@endif

            <ul class="space-y-3 border-t border-gray-100 pt-3">
                @foreach ($b['timeline'] as $t)
                    <li class="flex gap-3"><span class="w-2 h-2 rounded-full bg-brand-500 mt-1.5 shrink-0"></span><div><div class="font-bold text-gray-800">{{ $t['title'] }}</div>@if ($t['reason'])<div class="text-gray-500">{{ $t['reason'] }}</div>@endif<div class="text-[10px] text-gray-400">{{ $when($t['at']) }}</div></div></li>
                @endforeach
            </ul>

            <div class="flex gap-3 pt-2">
                @if ($paid || ($p['status'] ?? null) === 'paid')<a href="{{ route('service.invoice', $b['id']) }}" target="_blank" rel="noopener" class="flex-1 text-center border border-brand-500 text-brand-600 font-bold py-2.5 rounded-xl">Invoice</a>@endif
                @if ($b['can_cancel'])<button type="button" data-modal-open="cancel-booking" class="flex-1 bg-red-50 text-red-600 font-bold py-2.5 rounded-xl">Cancel booking</button>@endif
            </div>
        </div>
    </div>

    @if ($b['can_cancel'])
        <x-modal id="cancel-booking" title="Cancel this booking?">
            <form method="POST" action="{{ route('services.bookings.cancel', $b['id']) }}" data-lock class="space-y-3 text-xs">
                @csrf
                <p class="text-gray-500">@if ($paid)You paid online, so you get back what this service's refund policy allows - the exact amount is shown after cancelling.@else This booking is not paid, so nothing is charged.@endif</p>
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
