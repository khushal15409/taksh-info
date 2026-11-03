@extends('layouts.app')

@if (! empty($order))
    @section('title', 'Order #'.$order['order_number'].' | TakshAllInOne')
@else
    @section('title', 'Order Tracking | TakshAllInOne')
@endif
@section('page', 'tracking')

@section('content')
@if ($guest)
    <x-empty-state emoji="🔐" title="Login to track your orders" text="Please log in with your mobile number to see this page." cta-label="Login with OTP" :cta-href="route('login', ['next' => request()->getRequestUri()])" />
@elseif (! $order)
    <x-empty-state emoji="📦" title="Order not found" text="We could not find that order in your account." cta-label="My Orders" :cta-href="route('profile', ['tab' => 'orders'])" />
@else
    @php
        $o = $order;
        $tl = \App\Support\Presenters\OrderTimeline::build($o['order_status']);
        $canCancel = in_array($o['order_status'], \App\Support\Presenters\OrderTimeline::CANCELLABLE, true);
        $canReturn = $o['order_status'] === 'delivered';
        $a = $o['address'] ?? null;
        $payable = ($o['payment_status'] ?? null) && $o['payment_status'] !== 'paid' && ($o['payment_method'] ?? null) === 'online' && in_array($o['order_status'], ['pending', 'pending_payment'], true);
        $wa = 'https://wa.me/'.preg_replace('/\D/', '', config('taksh.support_phone')).'?text=';
        $issues = ['Item missing or wrong' => 'an item is missing or wrong', 'Damaged / spoiled item' => 'an item arrived damaged', 'Order is late' => 'my order is late', 'Payment or refund problem' => 'I have a payment/refund problem', 'Something else' => 'I need help'];
        $pretty = fn ($s) => \Illuminate\Support\Str::of((string) $s)->replace('_', ' ')->title();
    @endphp
    <div class="max-w-4xl mx-auto bg-white p-6 lg:p-8 rounded-3xl border border-orange-100 shadow-sm space-y-6">
        <div class="flex flex-wrap items-center justify-between border-b pb-6 border-gray-100 gap-4">
            <div>
                <a href="{{ route('profile', ['tab' => 'orders']) }}" class="text-xs font-bold text-gray-500 hover:text-brand-600">← My Orders</a>
                <div class="mt-2"><x-status-badge :status="$o['order_status']" class="!text-[11px] !px-2.5 !py-1" /></div>
                <h1 class="text-2xl font-extrabold text-gray-900 mt-2">Order #{{ $o['order_number'] }}</h1>
                <p class="text-xs text-gray-400">Placed on {{ \Illuminate\Support\Carbon::parse($o['created_at'])->format('j M Y, h:i A') }}@if ($o['payment_status']) • {{ $pretty($o['payment_method']) }} • {{ $pretty($o['payment_status']) }}@endif @if ($o['estimated_delivery_time']) • Expected {{ \Illuminate\Support\Carbon::parse($o['estimated_delivery_time'])->format('j M, h:i A') }}@endif</p>
            </div>
            <div class="no-print flex flex-wrap gap-2">
                <a href="{{ route('orders.invoice', $o['order_number']) }}" class="border border-emerald-600 text-emerald-700 font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-2 hover:bg-emerald-50"><i data-lucide="download" class="w-4 h-4"></i>Download Invoice</a>
                @if ($canCancel)<button type="button" data-modal-open="cancel-order" class="border border-red-300 text-red-600 hover:bg-red-50 font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-2"><i data-lucide="x-circle" class="w-4 h-4"></i>Cancel Order</button>@endif
                @if ($canReturn)<a href="{{ route('returns', ['id' => $o['id']]) }}" class="border border-brand-500 text-brand-600 hover:bg-orange-50 font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-2"><i data-lucide="undo-2" class="w-4 h-4"></i>Return Items</a>@endif
                @if ($payable)<a href="{{ route('order.pay', ['order_id' => $o['id']]) }}" class="bg-brand-500 hover:bg-brand-600 text-white font-bold px-4 py-2 rounded-xl text-xs">Pay now</a>@endif
            </div>
        </div>

        <div class="space-y-4">
            <h3 class="font-extrabold text-sm text-gray-900">Order Status</h3>
            <div class="relative pl-10 space-y-6">
                <div class="timeline-line" style="left:15px"></div>
                @foreach ($tl['steps'] as $s)
                    @php($dot = match ($s['state']) { 'done' => 'bg-emerald-500 ring-4 ring-emerald-100', 'bad' => 'bg-red-500 ring-4 ring-red-100', 'current' => $tl['terminal'] ? 'bg-brand-500 ring-4 ring-orange-100' : 'bg-emerald-500 ring-4 ring-emerald-100', default => 'bg-gray-300' })
                    <div class="relative">
                        <span class="absolute -left-[33px] top-0 w-4 h-4 rounded-full {{ $dot }}"></span>
                        <h4 class="text-xs font-bold {{ $s['state'] === 'todo' ? 'text-gray-400' : 'text-gray-900' }}">{{ $s['title'] }}@if ($s['state'] === 'current' && ! $tl['terminal'] && $loop->index < count($tl['steps']) - 1) <span class="text-[10px] text-brand-600 ml-1 animate-pulse">● in progress</span>@endif</h4>
                        @if ($s['text'])<p class="text-[11px] {{ $s['state'] === 'todo' ? 'text-gray-400' : 'text-gray-500' }}">{{ $s['text'] }}</p>@elseif ($loop->first)<p class="text-[11px] text-gray-500">{{ \Illuminate\Support\Carbon::parse($o['created_at'])->format('j M, h:i A') }}</p>@endif
                    </div>
                @endforeach
            </div>
        </div>

        @if ($o['is_quick'] && $o['order_status'] === 'out_for_delivery')
            <div class="space-y-2">
                <h3 class="font-extrabold text-sm text-gray-900">Live delivery location</h3>
                <div id="order-map" data-order="{{ $o['id'] }}" @if ($a && $a['latitude'] !== null) data-lat="{{ $a['latitude'] }}" data-lng="{{ $a['longitude'] }}" @endif class="w-full h-64 rounded-2xl border border-orange-100 bg-orange-50/40 flex items-center justify-center text-gray-400 text-xs">Loading map…</div>
                <p id="order-map-note" class="text-[11px] text-gray-500"></p>
            </div>
        @endif

        <div class="grid md:grid-cols-2 gap-6 border-t pt-6 border-gray-100 text-xs">
            <div>
                <h3 class="font-extrabold text-sm text-gray-900 mb-2">Delivery Address</h3>
                @if ($a)
                    <p class="font-bold text-gray-800">{{ $a['name'] }} <span class="bg-brand-500 text-white text-[10px] px-2 py-0.5 rounded-full uppercase ml-1">{{ $a['address_type'] }}</span></p>
                    <p class="text-gray-500 mt-1">{{ collect([$a['address_line_1'], $a['address_line_2'], $a['area'], $a['city'], $a['state']])->filter()->implode(', ') }} - {{ $a['pincode'] }}</p>
                    <p class="text-gray-500">Phone: {{ $a['mobile'] }}</p>
                @else
                    <p class="text-gray-400">Not available</p>
                @endif
                @if (! empty($o['vendor']))<p class="text-gray-500 mt-2"><b>Sold by:</b> {{ $o['vendor']['shop_name'] }}</p>@endif
            </div>
            <div class="space-y-2">
                <h3 class="font-extrabold text-sm text-gray-900 mb-2">Payment Summary</h3>
                <div class="flex justify-between text-gray-600"><span>Items</span><span>@rupee2($o['items_total'] + $o['taksh_charge_total'])</span></div>
                @foreach ($o['charges'] ?? [] as $c)
                    <div class="flex justify-between {{ $c['is_discount'] ? 'text-emerald-600 font-bold' : 'text-gray-600' }}"><span>{{ $c['label'] }}</span><span>{{ $c['is_discount'] ? '−' : '' }}@rupee2(abs($c['amount']))</span></div>
                @endforeach
                <div class="flex justify-between text-gray-600"><span>Delivery</span><span class="text-emerald-600 font-bold">FREE</span></div>
                <div class="flex justify-between font-extrabold text-sm text-gray-900 border-t pt-2"><span>Total</span><span class="text-brand-600">@rupee2($o['grand_total'])</span></div>
                @foreach ($o['refunds'] ?? [] as $r)<div class="flex justify-between text-sky-700 font-bold"><span>Refund ({{ $pretty($r['status']) }})</span><span>@rupee2($r['amount'])</span></div>@endforeach
            </div>
        </div>

        <div class="border-t pt-6 border-gray-100 space-y-3">
            <h3 class="font-extrabold text-sm text-gray-900">Ordered Items</h3>
            @foreach ($o['items'] as $i)
                <div class="flex items-center gap-4 bg-orange-50/40 p-3 rounded-2xl border border-orange-100">
                    @if (! empty($i['image_url']))<img src="{{ $i['image_url'] }}" alt="" loading="lazy" class="w-14 h-14 rounded-xl object-contain bg-white">@else<div class="w-14 h-14 rounded-xl bg-white flex items-center justify-center">🛍️</div>@endif
                    <div class="flex-1 min-w-0"><h4 class="text-xs font-bold text-gray-800">{{ $i['product_name'] ?? 'Item' }}</h4><p class="text-[11px] text-gray-400">Qty: {{ $i['qty'] }} × @rupee2($i['price'])</p></div>
                    <div class="text-sm font-extrabold text-brand-600">@rupee2($i['price'] * $i['qty'])</div>
                </div>
            @endforeach
        </div>

        <div class="border-t pt-6 border-gray-100 space-y-3 no-print">
            <h3 class="font-extrabold text-sm text-gray-900">Need help with this order?</h3>
            <div class="flex flex-wrap gap-2">
                @foreach ($issues as $label => $text)<a target="_blank" rel="noopener" href="{{ $wa.rawurlencode('Hi Taksh, order #'.$o['order_number'].': '.$text.'.') }}" class="text-xs font-bold border border-gray-200 hover:border-brand-500 hover:text-brand-600 rounded-full px-3 py-1.5">{{ $label }}</a>@endforeach
            </div>
            <p class="text-[11px] text-gray-400">Chat on WhatsApp, <a class="underline" href="mailto:{{ config('taksh.support_email', 'care@takshallinone.com') }}?subject={{ rawurlencode('Order #'.$o['order_number']) }}">email us</a> or <a class="underline" href="{{ route('help') }}">request a callback</a>.</p>
        </div>
    </div>

    @if ($canCancel)
        <x-modal id="cancel-order" title="Cancel this order?">
            <form method="POST" action="{{ route('tracking.cancel', $o['id']) }}" class="space-y-4 text-xs">
                @csrf
                <p class="text-gray-500">Orders can be cancelled until they are shipped. @if (($o['payment_status'] ?? null) === 'paid')Your payment will be refunded to you.@endif</p>
                <div class="flex gap-3">
                    <button type="button" data-modal-close class="flex-1 border border-gray-300 text-gray-600 font-bold py-3 rounded-2xl">Keep order</button>
                    <button data-once class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-2xl">Cancel order</button>
                </div>
            </form>
        </x-modal>
    @endif
@endif
@endsection

@push('scripts')

    <script src="{{ asset('assets/js/modules/order.js') }}"></script>

@endpush
