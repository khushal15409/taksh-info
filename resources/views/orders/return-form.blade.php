@extends('layouts.app')

@section('title', 'Return Request | TakshAllInOne')
@section('page', 'returns')

@section('content')
@if ($state === 'guest')
    <x-empty-state emoji="🔐" title="Login to return an item" text="Please log in with your mobile number to see this page." cta-label="Login with OTP" :cta-href="route('login', ['next' => request()->getRequestUri()])" />
@elseif ($state === 'missing')
    <x-empty-state emoji="📦" title="Order not found" text="Open a delivered order from My Orders to request a return." cta-label="My Orders" :cta-href="route('profile', ['tab' => 'orders'])" />
@elseif ($state === 'not_delivered')
    <x-empty-state emoji="⏳" title="Not delivered yet" text="You can return items once your order has been delivered." cta-label="View Order" :cta-href="route('tracking', ['id' => $order['id']])" />
@else
    @php
        $o = $order;
        $resolutions = ['refund' => ['Refund', 'Money back to your original payment method'], 'wallet' => ['Wallet credit', 'Instant credit as Taksh points'], 'replacement' => ['Replacement', 'We send you a new item']];
        $picked = (int) old('order_item_id', $o['items'][0]['id'] ?? 0);
    @endphp
    <form id="return-form" method="POST" action="{{ route('returns.store') }}" enctype="multipart/form-data" class="max-w-3xl mx-auto space-y-6" novalidate>
        @csrf
        <input type="hidden" name="order_id" value="{{ $o['id'] }}">
        <div>
            <a href="{{ route('tracking', ['id' => $o['id']]) }}" class="text-xs font-bold text-gray-500 hover:text-brand-600">← Back to order</a>
            <h1 class="text-2xl font-extrabold text-gray-900 mt-1">Return Items</h1>
            <p class="text-xs text-gray-400">Order #{{ $o['order_number'] }} • <a class="underline" href="{{ route('refund') }}">Refund policy</a></p>
        </div>

        <section class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-3">
            <h2 class="font-extrabold text-sm text-gray-900">1. Which item?</h2>
            @foreach ($o['items'] as $i)
                <label class="flex items-center gap-4 bg-orange-50/40 p-3 rounded-2xl border border-orange-100 cursor-pointer has-[:checked]:border-brand-500">
                    <input type="radio" name="order_item_id" value="{{ $i['id'] }}" data-qty="{{ $i['qty'] }}" class="accent-brand-500" @checked($picked === $i['id'])>
                    @if (! empty($i['image_url']))<img src="{{ $i['image_url'] }}" alt="" class="w-14 h-14 rounded-xl object-contain bg-white">@else<span class="w-14 h-14 rounded-xl bg-white flex items-center justify-center">🛍️</span>@endif
                    <div class="flex-1 min-w-0"><h4 class="text-xs font-bold text-gray-800">{{ $i['product_name'] ?? 'Item' }}</h4><p class="text-[11px] text-gray-400">Qty {{ $i['qty'] }} × @rupee2($i['price'])</p></div>
                </label>
            @endforeach
            <div class="flex items-center gap-3 text-xs">
                <label class="font-bold text-gray-500" for="return-qty">How many pieces?</label>
                <select name="quantity" id="return-qty" data-old="{{ old('quantity', 1) }}" class="field !w-24 !py-2"><option>1</option></select>
            </div>
        </section>

        <section class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-3 text-xs">
            <h2 class="font-extrabold text-sm text-gray-900">2. What went wrong?</h2>
            <textarea name="reason" rows="3" maxlength="1000" class="field" placeholder="Tell us what's wrong with the item (at least 10 characters)">{{ old('reason') }}</textarea>
        </section>

        <section class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-3 text-xs">
            <h2 class="font-extrabold text-sm text-gray-900">3. What would you like?</h2>
            @foreach ($resolutions as $v => [$t, $d])
                <label class="flex items-center gap-3 p-4 rounded-2xl border-2 border-gray-200 has-[:checked]:border-brand-500 has-[:checked]:bg-orange-50/40 cursor-pointer">
                    <input type="radio" name="resolution" value="{{ $v }}" class="accent-brand-500" @checked(old('resolution', 'refund') === $v)>
                    <div><div class="text-sm font-bold text-gray-800">{{ $t }}</div><div class="text-gray-500">{{ $d }}</div></div>
                </label>
            @endforeach
        </section>

        <section class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-3 text-xs">
            <h2 class="font-extrabold text-sm text-gray-900">4. Add photos <span class="font-medium text-gray-400">(optional, up to 5)</span></h2>
            <label class="block border-2 border-dashed border-orange-200 rounded-2xl p-6 text-center cursor-pointer hover:border-brand-500 transition">
                <i data-lucide="upload-cloud" class="w-7 h-7 mx-auto text-brand-500"></i><span class="block font-bold text-gray-700 mt-1">Click to upload</span><span class="text-gray-400">JPG, PNG, WEBP or GIF • max 5 MB each</span>
                <input id="return-photos" name="images[]" type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple class="sr-only">
            </label>
            <div id="return-previews" class="grid grid-cols-5 gap-3"></div>
        </section>

        <button id="return-submit" class="w-full bg-brand-500 hover:bg-brand-600 text-white font-extrabold py-3.5 rounded-2xl shadow-lg transition disabled:opacity-60">Submit Return Request</button>
    </form>
@endif
@endsection

@push('scripts')

    <script src="{{ asset('assets/js/modules/return.js') }}"></script>

@endpush
