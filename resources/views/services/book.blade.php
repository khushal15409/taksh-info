@extends('layouts.app')

@if ($service)
    @section('title', 'Book '.$service['name'].' | TakshAllInOne')
@else
    @section('title', 'Service not found | TakshAllInOne')
@endif
@section('page', 'services')

@section('content')
@if (! $service)
    <x-empty-state emoji="😕" title="Service not found" text="This service may no longer be available." cta-label="All services" :cta-href="route('services')" />
@else
    @php($e = fn ($n) => $errors->first($n))
    <div class="max-w-xl mx-auto space-y-5">
        <a href="{{ route('services') }}" class="text-xs font-bold text-gray-500 hover:text-brand-600">← All services</a>
        <div class="bg-white p-6 lg:p-8 rounded-3xl border border-purple-100 shadow-sm space-y-4">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-900">Book {{ $service['name'] }}</h1>
                @if ($service['short_description'])<p class="text-sm text-gray-500 mt-1">{{ $service['short_description'] }}</p>@endif
                <p class="text-sm mt-2">Service charge <b class="text-brand-600">@rupee($service['service_charge'])</b>, paid online to confirm your booking.</p>
            </div>
            <form method="POST" action="{{ route('services.book.store', $service['id']) }}" data-lock class="grid grid-cols-2 gap-4 text-xs" novalidate>
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $key) }}">
                @php($rows = [
                    ['name', 'Name', 'text', 'col-span-2 sm:col-span-1', []], ['mobile', 'Mobile', 'text', 'col-span-2 sm:col-span-1', ['maxlength' => 10, 'inputmode' => 'numeric']],
                    ['address_line_1', 'Address', 'text', 'col-span-2', []], ['landmark', 'Landmark (optional)', 'text', 'col-span-2', []],
                    ['pincode', 'Pincode', 'text', 'col-span-2 sm:col-span-1', ['maxlength' => 6, 'inputmode' => 'numeric']], ['city', 'City', 'text', 'col-span-2 sm:col-span-1', []],
                    ['scheduled_for', 'Preferred date & time (optional)', 'datetime-local', 'col-span-2', []],
                ])
                @foreach ($rows as [$name, $label, $type, $span, $attrs])
                    <div class="{{ $span }}">
                        <label for="b-{{ $name }}" class="font-bold text-gray-500">{{ $label }}</label>
                        <input id="b-{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'datetime-local' ? str_replace(' ', 'T', old($name, '')) : old($name, $defaults[$name] ?? '') }}" class="field mt-1 @if ($e($name)) border-red-400 @endif" @foreach ($attrs as $k => $v) {{ $k }}="{{ $v }}" @endforeach>
                        @if ($e($name))<p class="text-red-500 mt-1">{{ $e($name) }}</p>@endif
                    </div>
                @endforeach
                <div class="col-span-2"><label for="b-notes" class="font-bold text-gray-500">Notes (optional)</label><textarea id="b-notes" name="customer_notes" rows="2" maxlength="500" class="field mt-1">{{ old('customer_notes') }}</textarea>@if ($e('customer_notes'))<p class="text-red-500 mt-1">{{ $e('customer_notes') }}</p>@endif</div>
                @if ($e('idempotency_key'))<p class="col-span-2 text-red-500">{{ $e('idempotency_key') }}</p>@endif
                <button class="col-span-2 bg-purple-600 hover:bg-purple-700 text-white font-bold text-sm py-3 rounded-2xl transition disabled:opacity-60">Pay @rupee($service['service_charge']) &amp; book</button>
            </form>
        </div>
    </div>
@endif
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/modules/support.js') }}"></script>
@endpush
