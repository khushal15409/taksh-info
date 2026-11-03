@extends('layouts.app')

@section('title', 'Payment Status | TakshAllInOne')
@section('page', 'payresult')

@section('content')

    <div class="max-w-md mx-auto bg-white rounded-3xl border border-orange-100 shadow-sm p-8 text-center space-y-4 fade-up">
        @if ($outcome === 'success')
            <div class="w-24 h-24 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center"><i data-lucide="check" class="w-12 h-12"></i></div>
            <h1 class="text-2xl font-extrabold text-gray-900">Payment Successful</h1>
            <p class="text-sm text-gray-500">Thank you! Your payment was received and your order is confirmed.</p>
            <p class="text-xs text-gray-400">Order ID: <b class="text-gray-700">#{{ $orderId }}</b></p>
            <div class="flex flex-col gap-2 pt-2">
                <a href="{{ route('profile', ['tab' => 'orders']) }}" class="bg-brand-500 hover:bg-brand-600 text-white font-bold py-3 rounded-2xl">My Orders</a>
                <a href="{{ route('listing') }}" class="text-xs font-bold text-gray-500 hover:text-brand-600">Continue shopping</a>
            </div>
        @elseif ($outcome === 'failed')
            <div class="w-24 h-24 mx-auto rounded-full bg-red-100 text-red-500 flex items-center justify-center"><i data-lucide="x" class="w-12 h-12"></i></div>
            <h1 class="text-2xl font-extrabold text-gray-900">Payment Failed</h1>
            <p class="text-sm text-gray-500">{{ $reason ?: "We couldn't process your payment. No money was deducted. Please try again or choose another method." }}</p>
            <div class="flex flex-col gap-2 pt-2">
                <a href="{{ $state && $state['can_pay'] ? route('order.pay', ['order_id' => $orderId]) : route('checkout') }}" class="bg-brand-500 hover:bg-brand-600 text-white font-bold py-3 rounded-2xl">Retry Payment</a>
                <a href="{{ route('cart') }}" class="text-xs font-bold text-gray-500 hover:text-brand-600">← Back to cart</a>
            </div>
        @else
            <div class="w-24 h-24 mx-auto rounded-full bg-amber-100 text-amber-600 flex items-center justify-center"><i data-lucide="help-circle" class="w-12 h-12"></i></div>
            <h1 class="text-2xl font-extrabold text-gray-900">We could not check this payment</h1>
            <p class="text-sm text-gray-500">Log in to see where your order and payment stand.</p>
            <div class="flex flex-col gap-2 pt-2"><a href="{{ route('profile', ['tab' => 'orders']) }}" class="bg-brand-500 hover:bg-brand-600 text-white font-bold py-3 rounded-2xl">My Orders</a></div>
        @endif
    </div>

@endsection

@push('scripts')

@endpush
