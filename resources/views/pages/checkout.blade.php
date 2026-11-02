@extends('layouts.app')

@section('title', 'Checkout | TakshAllInOne')
@section('page', 'checkout')

@section('content')

        <h1 class="text-2xl font-extrabold text-gray-900 mb-6">Review &amp; Place Order</h1>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                <section class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-extrabold text-base text-gray-900 flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-brand-500 text-white text-xs flex items-center justify-center">1</span>Delivery Address</h3>
                        <button id="add-address" class="text-xs font-bold text-brand-600 hover:underline">+ Add New</button>
                    </div>
                    <div id="addr-list" class="space-y-3"></div>
                </section>
                <section class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-4">
                    <h3 class="font-extrabold text-base text-gray-900 flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-brand-500 text-white text-xs flex items-center justify-center">2</span>Select Payment Method</h3>
                    <div id="pay-list" class="space-y-3"></div>
                </section>
                <section class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-3">
                    <h3 class="font-extrabold text-base text-gray-900 flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-brand-500 text-white text-xs flex items-center justify-center">3</span>Delivery Instructions <span class="text-xs font-medium text-gray-400">(optional)</span></h3>
                    <textarea id="order-note" rows="2" class="field" placeholder="e.g. Leave at the door, call before arriving"></textarea>
                </section>
            </div>
            <aside class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-5 h-fit lg:sticky lg:top-40">
                <h3 class="font-extrabold text-base text-gray-900 border-b pb-3 border-gray-100">Final Order Total</h3>
                <div id="co-items" class="space-y-3 max-h-60 overflow-y-auto pr-1"></div>
                <div id="co-totals" class="space-y-3 text-xs border-t pt-4 border-gray-100"></div>
                <button id="place-order" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold py-3.5 rounded-2xl shadow-lg transition flex items-center justify-center gap-2"><i data-lucide="check-circle" class="w-5 h-5"></i>Confirm Order</button>
                <a href="{{ route('cart') }}" class="block text-center text-xs font-bold text-gray-500 hover:text-brand-600">← Back to cart</a>
            </aside>
        </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/pages/checkout.js') }}"></script>
@endpush
