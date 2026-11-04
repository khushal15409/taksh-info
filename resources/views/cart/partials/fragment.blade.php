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
                <div class="bg-emerald-50 border border-emerald-200 rounded-2xl px-4 py-3 text-xs font-semibold text-emerald-900">⚡ Quick delivery: your order reaches you in about 30 minutes, to an address one of these shops serves.</div>
            @endif

            @foreach ($cart['items'] as $i)
                <div class="cart-line {{ $i['in_stock'] ? '' : 'opacity-70' }}">
                    <a href="{{ route('product', ['id' => $i['product_id']]) }}" class="cart-line-media">
                        <span class="cart-badge {{ ! empty($i['is_express_30']) ? 'cart-badge-quick' : 'cart-badge-std' }}"><i data-lucide="{{ ! empty($i['is_express_30']) ? 'zap' : 'truck' }}" class="w-3 h-3"></i>{{ ! empty($i['is_express_30']) ? '30-Min' : 'Standard' }}</span>
                        <span class="pcard-img">
                            @if (! empty($i['image']))
                                <img src="{{ $i['image'] }}" alt="" aria-hidden="true" class="pcard-img-bg"><img src="{{ $i['image'] }}" alt="{{ $i['product_name'] }}" class="pcard-img-fg">
                            @else
                                <span class="absolute inset-0 flex items-center justify-center text-3xl">🛍️</span>
                            @endif
                        </span>
                    </a>
                    <div class="flex-1 min-w-0 flex flex-col gap-1.5">
                        <a href="{{ route('product', ['id' => $i['product_id']]) }}" class="font-bold text-sm text-gray-900 hover:text-brand-600 line-clamp-2 leading-snug">{{ $i['product_name'] }}</a>
                        <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
                            <span class="cart-chip">@rupee2($i['price']) each</span>
                            @if (! empty($i['vendor_name']))<span class="cart-chip cart-chip-soft"><i data-lucide="store" class="w-3 h-3"></i>{{ $i['vendor_name'] }}</span>@endif
                            @if (! empty($i['sku']))<span class="cart-chip cart-chip-soft">SKU: {{ $i['sku'] }}</span>@endif
                        </div>
                        @if (! ($i['in_stock']))<p class="text-[11px] font-bold text-red-500">Currently unavailable - remove it to continue</p>@endif
                        <form method="POST" action="{{ route('cart.line.remove', $i['id']) }}" data-cart-form class="mt-auto">@csrf @method('DELETE')<input type="hidden" name="type" value="{{ $type }}"><button class="inline-flex items-center gap-1 text-[11px] font-bold text-gray-400 hover:text-red-500"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i>Remove</button></form>
                    </div>
                    <div class="flex flex-col items-end justify-between gap-2">
                        <div class="font-extrabold text-lg text-gray-900">@rupee2($i['total'])</div>
                        <div class="qty-pill">
                            <form method="POST" action="{{ route('cart.line.update', $i['id']) }}" data-cart-form>@csrf<input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="qty" value="{{ $i['qty'] - 1 }}"><button aria-label="Decrease">−</button></form>
                            <span>{{ $i['qty'] }}</span>
                            <form method="POST" action="{{ route('cart.line.update', $i['id']) }}" data-cart-form>@csrf<input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="qty" value="{{ $i['qty'] + 1 }}"><button @disabled($i['qty'] >= $i['available_stock']) aria-label="Increase">+</button></form>
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
