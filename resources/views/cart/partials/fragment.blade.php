{{-- The part of the cart page that changes with every action: the Standard / Quick tabs and the cart itself. Refreshed in place by modules/cart.js. --}}
@php
    $cart = $carts[$type];
    $isNormal = $type === 'normal';
    $tabs = ['normal' => ['Standard Delivery', 'truck'], '30_min' => ['Quick Delivery', 'zap']];
@endphp
<div class="flex border-b border-gray-200 gap-6 text-sm font-bold mb-6 overflow-x-auto no-scrollbar">
    @foreach ($tabs as $key => [$label, $icon])
        <a href="{{ route('cart', ['type' => $key]) }}" class="pb-3 border-b-2 flex items-center gap-2 {{ $type === $key ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-400 hover:text-gray-600' }}"><i data-lucide="{{ $icon }}" class="w-4 h-4"></i>{{ $label }}<span class="text-[10px] bg-gray-100 text-gray-500 rounded-full px-1.5">{{ count($carts[$key]['items']) }}</span></a>
    @endforeach
</div>

@if (! $cart['items'])
    <x-empty-state emoji="🛒" :title="$isNormal ? 'Your Cart is Currently Empty' : 'No Quick Delivery items'"
        :text="$isNormal ? 'Looks like you haven\'t added anything yet. Explore top deals!' : 'Add Quick (30-minute) products from the Quick Products page to see them here.'"
        cta-label="Start Shopping" :cta-href="route('listing')" />
@else
    @php
        $count = collect($cart['items'])->sum('qty');
        $coupon = $cart['coupon'] ?? null;
    @endphp
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-4">
            @if (! ($isNormal))
                <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 text-xs font-semibold text-emerald-900">⚡ Quick delivery: your order reaches you in about 30 minutes, to an address one of these shops serves.</div>
            @endif

            @foreach ($cart['items'] as $i)
                <div class="bg-white p-4 rounded-3xl border border-orange-100 flex items-center gap-4 {{ $i['in_stock'] ? '' : 'opacity-70' }}">
                    <a href="{{ route('product', ['id' => $i['product_id']]) }}">
                        @if (! empty($i['image']))<img src="{{ $i['image'] }}" alt="" class="w-20 h-20 rounded-2xl object-contain bg-orange-50/50 p-1">@else<div class="w-20 h-20 rounded-2xl bg-orange-50/50 flex items-center justify-center text-2xl">🛍️</div>@endif
                    </a>
                    <div class="flex-1 min-w-0">
                        <a href="{{ route('product', ['id' => $i['product_id']]) }}" class="font-bold text-sm text-gray-800 hover:text-brand-600 line-clamp-2">{{ $i['product_name'] }}</a>
                        <p class="text-[11px] text-gray-400">
                            @if (! empty($i['sku']))SKU: {{ $i['sku'] }} • @endif
                            @rupee2($i['price']) each
                            @if (! empty($i['vendor_name']))• {{ $i['vendor_name'] }}@endif
                        </p>
                        @if (! ($i['in_stock']))<p class="text-[11px] font-bold text-red-500 mt-1">Currently unavailable - remove it to continue</p>@endif
                        <form method="POST" action="{{ route('cart.line.remove', $i['id']) }}" data-cart-form class="inline">@csrf @method('DELETE')<input type="hidden" name="type" value="{{ $type }}"><button class="mt-1.5 text-[11px] font-bold text-gray-400 hover:text-red-500">Remove</button></form>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <div class="font-extrabold text-brand-600">@rupee2($i['total'])</div>
                        <div class="flex items-center gap-2 border rounded-xl px-1 py-0.5">
                            <form method="POST" action="{{ route('cart.line.update', $i['id']) }}" data-cart-form>@csrf<input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="qty" value="{{ $i['qty'] - 1 }}"><button class="w-7 h-7 text-brand-600 font-bold" aria-label="Decrease">−</button></form>
                            <span class="text-xs font-bold w-5 text-center">{{ $i['qty'] }}</span>
                            <form method="POST" action="{{ route('cart.line.update', $i['id']) }}" data-cart-form>@csrf<input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="qty" value="{{ $i['qty'] + 1 }}"><button @disabled($i['qty'] >= $i['available_stock']) class="w-7 h-7 text-brand-600 font-bold disabled:opacity-30" aria-label="Increase">+</button></form>
                        </div>
                    </div>
                </div>
            @endforeach

            <form method="POST" action="{{ route('cart.empty') }}" data-cart-form>@csrf @method('DELETE')<input type="hidden" name="type" value="{{ $type }}"><button class="text-xs font-bold text-gray-400 hover:text-red-500">Clear {{ $isNormal ? 'cart' : 'quick items' }}</button></form>
        </div>

        <aside class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-5 h-fit lg:sticky lg:top-40">
            <h3 class="font-extrabold text-base text-gray-900 border-b pb-3 border-gray-100">Order Summary</h3>

            @if ($coupon && $coupon['valid'])
                <form method="POST" action="{{ route('cart.coupon.delete') }}" data-cart-form class="flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-xl px-3 py-2 text-xs">@csrf @method('DELETE')<input type="hidden" name="type" value="{{ $type }}">
                    <span class="font-bold text-emerald-700">🎟️ {{ $coupon['code'] }} applied</span><button class="text-red-500 font-bold">Remove</button></form>
            @else
                <form method="POST" action="{{ route('cart.coupon.save') }}" data-cart-form class="flex gap-2">@csrf<input type="hidden" name="type" value="{{ $type }}">
                    <input name="coupon_code" placeholder="Have a coupon code?" class="field" autocomplete="off" maxlength="50"><button class="bg-brand-500 hover:bg-brand-600 text-white text-xs font-bold px-4 rounded-xl">Apply</button></form>
                @if ($coupon && ! $coupon['valid'])<p class="text-[11px] text-red-500 font-semibold">{{ $coupon['code'] }}: {{ $coupon['message'] ?? 'no longer valid' }}</p>@endif
            @endif

            <div class="space-y-3 text-xs">
                <div class="flex justify-between text-gray-600"><span>Subtotal ({{ $count }} items)</span><span class="font-bold text-gray-900">@rupee2($cart['total'])</span></div>
                <div class="flex justify-between text-gray-600"><span>Delivery Charges</span><span class="font-bold text-emerald-600 uppercase">Free</span></div>
                @foreach ($cart['charges'] as $ch)
                    <div class="flex justify-between {{ $ch['is_discount'] ? 'text-emerald-600 font-bold' : 'text-gray-600' }}"><span>{{ $ch['label'] }}</span><span>{{ $ch['is_discount'] ? '−' : '' }}@rupee2(abs($ch['amount']))</span></div>
                @endforeach
                <div class="border-t pt-3 flex justify-between text-base font-extrabold text-gray-900"><span>Total Amount</span><span class="text-brand-600">@rupee2($cart['total_with_delivery'])</span></div>
            </div>

            <a href="{{ route('checkout', $isNormal ? [] : ['type' => '30_min']) }}" class="w-full bg-gradient-to-r from-brand-500 to-brand-600 hover:brightness-110 text-white font-extrabold py-3.5 rounded-2xl shadow-md transition flex items-center justify-center gap-2"><i data-lucide="lock" class="w-4 h-4"></i>Proceed to Checkout</a>
        </aside>
    </div>
@endif
