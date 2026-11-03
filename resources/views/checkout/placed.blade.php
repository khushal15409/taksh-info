@extends('layouts.app')

@section('title', 'Order Placed | TakshAllInOne')
@section('page', 'order-placed')

@section('content')

    @php($unpaid = $state['unpaid_online'])
    <div class="max-w-xl mx-auto bg-white rounded-3xl border border-orange-100 shadow-sm p-8 text-center space-y-4 fade-up">
        <div class="w-24 h-24 mx-auto rounded-full {{ $unpaid ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600' }} flex items-center justify-center"><i data-lucide="{{ $unpaid ? 'clock' : 'check' }}" class="w-12 h-12"></i></div>
        <h1 class="text-2xl font-extrabold text-gray-900">{{ $unpaid ? 'Order placed - payment pending' : 'Order placed successfully 🎉' }}</h1>
        <p class="text-sm text-gray-500">
            @if ($state['paid']) Payment received. We're getting your order ready.
            @elseif ($state['cod']) Pay cash when your order arrives.
            @elseif ($unpaid) Your order is saved but not paid yet. Complete the payment to confirm it.
            @else Thank you for shopping with Taksh.
            @endif
        </p>
        @if ($orders)
            <div class="text-left space-y-2">
                @foreach ($orders as $o)
                    <div class="flex items-center justify-between bg-orange-50/60 rounded-2xl px-4 py-3 text-xs">
                        <div><div class="font-bold text-gray-800">#{{ $o['order_number'] }}</div><div class="text-gray-400">{{ $o['shop_name'] }} • {{ $o['items_count'] }} item(s)</div></div>
                        <div class="font-extrabold text-brand-600">@rupee2($o['grand_total'])</div>
                    </div>
                @endforeach
            </div>
        @endif
        <div class="flex flex-col gap-2 pt-2">
            @if ($unpaid && $state['can_pay'])<a href="{{ route('order.pay', ['order_id' => $orderId]) }}" class="bg-brand-500 hover:bg-brand-600 text-white font-bold py-3 rounded-2xl">Pay now</a>@endif
            <a href="{{ route('profile', ['tab' => 'orders']) }}" class="{{ $unpaid ? 'text-xs font-bold text-gray-500 hover:text-brand-600' : 'bg-brand-500 hover:bg-brand-600 text-white font-bold py-3 rounded-2xl' }}">My Orders</a>
            <a href="{{ route('listing') }}" class="text-xs font-bold text-gray-500 hover:text-brand-600">Continue shopping</a>
        </div>
    </div>

@endsection

@push('scripts')

@endpush
