@extends('layouts.app')

@section('title', 'Pay for your order | TakshAllInOne')
@section('page', 'order-pay')

@section('content')

    @php($orders = route('profile', ['tab' => 'orders']))
    <div class="max-w-md mx-auto bg-white rounded-3xl border border-orange-100 shadow-sm p-8 text-center space-y-4 fade-up">
        @if (! $orderId)
            <x-empty-state emoji="🧾" title="No order selected" text="Open an order from My Orders to pay for it." cta-label="My Orders" :cta-href="$orders" />
        @elseif (! $state)
            <x-empty-state emoji="😕" title="Order not found" text="We could not find that order in your account." cta-label="My Orders" :cta-href="$orders" />
        @elseif ($state['paid'])
            <div class="text-5xl">✅</div><h1 class="text-xl font-extrabold text-gray-900">This order is already paid</h1>
            <a href="{{ $orders }}" class="inline-block bg-brand-500 text-white font-bold px-6 py-3 rounded-2xl">My Orders</a>
        @elseif (! $state['can_pay'])
            <div class="text-5xl">ℹ️</div><h1 class="text-xl font-extrabold text-gray-900">Nothing to pay for this order</h1>
            <p class="text-sm text-gray-500">It is {{ $state['order_status'] }}@if ($state['cod']) and will be paid on delivery @endif.</p>
            <a href="{{ $orders }}" class="inline-block bg-brand-500 text-white font-bold px-6 py-3 rounded-2xl">My Orders</a>
        @else
            <div class="w-16 h-16 mx-auto rounded-2xl bg-brand-500 text-white flex items-center justify-center"><i data-lucide="credit-card" class="w-8 h-8"></i></div>
            <h1 class="text-xl font-extrabold text-gray-900">Pay for your order</h1>
            <p class="text-sm text-gray-500">@if ($state['amount'])Amount payable <b class="text-gray-900">@rupee2($state['amount'])</b>. @endif Pay securely online with Razorpay.</p>
            @if ($state['payment_status'] === 'failed')<p class="text-xs font-semibold text-red-500">Your last attempt did not go through. Nothing was charged for it.</p>@endif
            <button type="button" id="pay-now" data-order="{{ $orderId }}" data-start="{{ $start ? 1 : 0 }}" data-placed-url="{{ route('order.placed', ['order' => $orderId]) }}" data-result-url="{{ route('payresult') }}"
                class="w-full bg-brand-500 hover:bg-brand-600 text-white font-extrabold py-3.5 rounded-2xl transition disabled:opacity-60">Pay now</button>
            <a href="{{ $orders }}" class="block text-xs font-bold text-gray-500 hover:text-brand-600">Pay later from My Orders</a>
        @endif
    </div>

@endsection

@push('scripts')

    <script src="{{ asset('assets/js/pay.js') }}"></script>
    <script src="{{ asset('assets/js/modules/pay.js') }}"></script>

@endpush
