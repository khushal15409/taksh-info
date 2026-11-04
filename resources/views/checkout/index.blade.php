@extends('layouts.app')

@section('title', 'Checkout | TakshAllInOne')
@section('page', 'checkout')

@section('content')

    @php($err = fn ($k) => $errors->first($k))
    <h1 class="text-2xl font-extrabold text-gray-900 mb-6">Review &amp; Place Order
        @if ($quick)<span class="ml-2 align-middle text-xs font-bold bg-emerald-100 text-emerald-700 px-2 py-1 rounded-full">⚡ 30-minute delivery</span>@endif
    </h1>
    <form id="checkout-form" method="POST" action="{{ route('checkout.place') }}" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        @csrf
        <input type="hidden" name="checkout_token" value="{{ $token }}">
        <input type="hidden" name="type" value="{{ $type }}">

        <div class="lg:col-span-2 space-y-6">
            <section class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-extrabold text-base text-gray-900 flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-brand-500 text-white text-xs flex items-center justify-center">1</span>Delivery Address</h3>
                    <button type="button" data-address-new class="text-xs font-bold text-brand-600 hover:underline">+ Add New</button>
                </div>
                <div class="space-y-3">
                    @forelse ($addresses as $a)
                        @php($ok = ! $quick || ($serviceable[$a['id']] ?? false))
                        <label class="block p-4 rounded-2xl border-2 transition {{ $ok ? 'cursor-pointer' : 'opacity-60 cursor-not-allowed' }} {{ (int) $selectedAddress === $a['id'] ? 'border-brand-500 bg-orange-50/40' : 'border-gray-200 '.($ok ? 'hover:border-brand-500/50' : '') }}">
                            <div class="flex justify-between items-start gap-3">
                                <div class="flex gap-3">
                                    <input type="radio" name="address_id" value="{{ $a['id'] }}" class="accent-brand-500 mt-1" @checked((int) $selectedAddress === $a['id']) @disabled(! $ok)>
                                    <div>
                                        <span class="bg-brand-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">{{ $a['address_type'] }}</span>
                                        <h4 class="font-bold text-sm text-gray-800 mt-2">{{ $a['name'] }}</h4>
                                        <p class="text-xs text-gray-500 mt-1">{{ collect([$a['address_line_1'], $a['address_line_2'], $a['area'], $a['city'], $a['state']])->filter()->implode(', ') }} - {{ $a['pincode'] }}</p>
                                        <p class="text-xs text-gray-500 mt-0.5">Phone: {{ $a['mobile'] }}</p>
                                        @if (! $ok)<p class="text-[11px] text-amber-700 font-semibold mt-1">30-minute delivery is not available at this address.</p>@endif
                                    </div>
                                </div>
                                <button type="button" data-address-edit='@json($a)' class="text-xs font-bold text-brand-600 hover:underline">Edit</button>
                            </div>
                        </label>
                    @empty
                        <p class="text-sm text-gray-500">No saved addresses yet. Add one to continue.</p>
                    @endforelse
                </div>
            </section>

            <section class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-4">
                <h3 class="font-extrabold text-base text-gray-900 flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-brand-500 text-white text-xs flex items-center justify-center">2</span>Select Payment Method</h3>
                <div class="space-y-3">
                    @php($labels = ['cod' => ['Cash on Delivery (COD)', 'Pay with cash upon delivery', 'FREE', 'text-emerald-600'], 'online' => ['Pay Online (Razorpay)', 'UPI, cards, netbanking & wallets', 'Secure', 'text-blue-600']])
                    @forelse ($methods as $m)
                        <label class="flex items-center justify-between p-4 rounded-2xl border-2 cursor-pointer transition {{ $method === $m ? 'border-brand-500 bg-orange-50/40' : 'border-gray-200 hover:border-brand-500/50' }}">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="payment_method" value="{{ $m }}" class="accent-brand-500" @checked($method === $m)>
                                <div><div class="text-sm font-bold text-gray-800">{{ $labels[$m][0] }}</div><div class="text-xs text-gray-500">{{ $labels[$m][1] }}</div></div>
                            </div>
                            <span class="text-xs font-bold {{ $labels[$m][3] }}">{{ $labels[$m][2] }}</span>
                        </label>
                    @empty
                        <p class="text-sm text-red-500 font-semibold">No payment method is available right now. Please try again later.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-5 h-fit lg:sticky lg:top-40">
            <h3 class="font-extrabold text-base text-gray-900 border-b pb-3 border-gray-100">Final Order Total</h3>
            <div class="space-y-3 max-h-60 overflow-y-auto pr-1">
                @foreach ($cart['items'] as $i)
                    <div class="flex items-center gap-3">
                        @if (! empty($i['image']))<img src="{{ $i['image'] }}" alt="" class="w-12 h-12 rounded-xl object-contain bg-orange-50/60">@else<div class="w-12 h-12 rounded-xl bg-orange-50/60 flex items-center justify-center">🛍️</div>@endif
                        <div class="flex-1 min-w-0"><div class="text-xs font-bold text-gray-800 truncate">{{ $i['product_name'] }}</div><div class="text-[11px] text-gray-400">Qty {{ $i['qty'] }} × @rupee($i['price'])</div></div>
                        <div class="text-xs font-extrabold text-gray-900">@rupee($i['total'])</div>
                    </div>
                @endforeach
            </div>
            <div class="space-y-3 text-xs border-t pt-4 border-gray-100">
                <div class="flex justify-between text-gray-600"><span>Item Total</span><span class="font-bold text-gray-900">@rupee2($cart['total'])</span></div>
                <div class="flex justify-between text-gray-600"><span>Delivery Fee</span><span class="font-bold text-emerald-600">FREE</span></div>
                @foreach ($cart['charges'] as $c)
                    <div class="flex justify-between {{ $c['is_discount'] ? 'text-emerald-600 font-bold' : 'text-gray-600' }}"><span>{{ $c['label'] }}</span><span>{{ $c['is_discount'] ? '−' : '' }}@rupee2(abs($c['amount']))</span></div>
                @endforeach
                <div class="border-t pt-3 flex justify-between text-base font-extrabold text-gray-900"><span>Grand Total</span><span class="text-brand-600">@rupee2($cart['total_with_delivery'])</span></div>
                @if ($unavailable)<p class="text-[11px] text-red-500 font-semibold">Some items are no longer available. Remove them from your cart to continue.</p>@endif
            </div>
            <button id="place-order" @disabled($unavailable || ! $addresses || ! $methods || ($quick && ! in_array(true, $serviceable, true))) class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold py-3.5 rounded-2xl shadow-lg transition flex items-center justify-center gap-2 disabled:opacity-50"><i data-lucide="check-circle" class="w-5 h-5"></i><span>Confirm Order</span></button>
            <a href="{{ route('cart', ['type' => $type]) }}" class="block text-center text-xs font-bold text-gray-500 hover:text-brand-600">← Back to cart</a>
        </aside>
    </form>

@endsection

@push('scripts')

    <script src="{{ asset('assets/js/modules/checkout.js') }}"></script>

@endpush
