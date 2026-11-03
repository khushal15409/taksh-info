{{-- The courier booking form. "Get delivery rates" is a small call (modules/courier.js); the booking itself is a normal multipart post. --}}
@php
    $err = fn (string $n) => $courierBag->first($n);
    $fields = [
        ['Pickup', null, []],
        ['customer_name', 'Your name', ['value' => $defaults['name'] ?? '']], ['customer_mobile', 'Mobile', ['value' => $defaults['mobile'] ?? '', 'maxlength' => 10, 'inputmode' => 'numeric']],
        ['pickup_address', 'Pickup address', ['wide' => true]], ['pickup_pincode', 'Pickup pincode', ['maxlength' => 6, 'inputmode' => 'numeric']],
        ['Deliver to', null, []],
        ['delivery_name', 'Receiver name', []], ['delivery_mobile', 'Receiver mobile', ['maxlength' => 10, 'inputmode' => 'numeric']],
        ['delivery_address', 'Delivery address', ['wide' => true]], ['delivery_pincode', 'Delivery pincode', ['maxlength' => 6, 'inputmode' => 'numeric']],
    ];
@endphp
<form id="courier-form" method="POST" action="{{ route('services.courier.store') }}" enctype="multipart/form-data" data-lock class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 grid grid-cols-2 gap-4 text-xs" novalidate>
    @csrf
    @if (! auth()->check())
        <p class="col-span-2 bg-orange-50/70 rounded-2xl p-3 text-gray-600">Log in to book a courier. <a class="font-bold text-brand-600 underline" href="{{ route('login', ['next' => route('services').'#courier']) }}">Login with OTP</a></p>
    @endif
    @foreach ($fields as [$name, $label, $o])
        @if ($label === null)
            <div class="col-span-2 font-extrabold text-gray-700 {{ $loop->first ? '' : 'pt-2' }}">{{ $name }}</div>
        @else
            <div class="{{ ! empty($o['wide']) ? 'col-span-2' : '' }}">
                <label class="font-bold text-gray-500" for="c-{{ $name }}">{{ $label }}</label>
                <input id="c-{{ $name }}" name="{{ $name }}" value="{{ old($name, $o['value'] ?? '') }}" @if (! empty($o['maxlength'])) maxlength="{{ $o['maxlength'] }}" @endif @if (! empty($o['inputmode'])) inputmode="{{ $o['inputmode'] }}" @endif class="field mt-1 @if ($err($name)) border-red-400 @endif">
                @if ($err($name))<p class="text-red-500 mt-1">{{ $err($name) }}</p>@endif
            </div>
        @endif
    @endforeach

    <div class="col-span-2 font-extrabold text-gray-700 pt-2">Parcel</div>
    <div><label class="font-bold text-gray-500" for="c-weight_grams">Weight (grams)</label><input id="c-weight_grams" name="weight_grams" type="number" min="1" step="1" value="{{ old('weight_grams', 500) }}" class="field mt-1 @if ($err('weight_grams')) border-red-400 @endif">@if ($err('weight_grams'))<p class="text-red-500 mt-1">{{ $err('weight_grams') }}</p>@endif</div>
    <div><label class="font-bold text-gray-500" for="c-dimension_unit">Size unit</label><select id="c-dimension_unit" name="dimension_unit" class="field mt-1"><option value="cm" @selected(old('dimension_unit') !== 'inch')>cm</option><option value="inch" @selected(old('dimension_unit') === 'inch')>inch</option></select></div>
    <div class="col-span-2 grid grid-cols-3 gap-3">
        @foreach (['length' => 'Length', 'width' => 'Width', 'height' => 'Height'] as $n => $l)
            <div><label class="font-bold text-gray-500" for="c-{{ $n }}">{{ $l }}</label><input id="c-{{ $n }}" name="{{ $n }}" type="number" min="0.01" step="0.01" value="{{ old($n, 10) }}" class="field mt-1 @if ($err($n)) border-red-400 @endif">@if ($err($n))<p class="text-red-500 mt-1">{{ $err($n) }}</p>@endif</div>
        @endforeach
    </div>
    <div class="col-span-2"><label class="font-bold text-gray-500" for="c-product_details">What are you sending? (optional)</label><input id="c-product_details" name="product_details" maxlength="500" value="{{ old('product_details') }}" class="field mt-1"></div>
    <div class="col-span-2"><label class="font-bold text-gray-500" for="c-customer_notes">Packaging / notes (optional)</label><input id="c-customer_notes" name="customer_notes" maxlength="500" value="{{ old('customer_notes') }}" class="field mt-1"></div>
    <div class="col-span-2"><label class="font-bold text-gray-500" for="c-item_photo">Parcel photo (optional)</label><input id="c-item_photo" name="item_photo" type="file" accept="image/*" class="field mt-1">@if ($err('item_photo'))<p class="text-red-500 mt-1">{{ $err('item_photo') }}</p>@endif</div>

    <input type="hidden" name="shiprocket_courier_company_id" value="">
    <input type="hidden" name="quoted_rate" value="">
    <button id="courier-rates" type="button" class="col-span-2 border-2 border-blue-600 text-blue-700 hover:bg-blue-50 font-bold text-sm py-3 rounded-2xl">Get delivery rates</button>
    <div id="courier-options" class="col-span-2 space-y-2"></div>
    <button id="courier-book" class="col-span-2 bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm py-3 rounded-2xl disabled:opacity-60" @disabled(! auth()->check())>Book pickup &amp; pay</button>
    <p class="col-span-2 text-[11px] text-gray-400">If you don't choose a courier, the cheapest available one is booked. The price is always worked out by Taksh at booking time.</p>
</form>
