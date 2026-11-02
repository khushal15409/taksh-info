@extends('layouts.app')

@section('title', 'Refund Policy | TakshAllInOne')
@section('page', 'refund')

@section('content')

        <article class="max-w-3xl mx-auto bg-white rounded-3xl border border-orange-100 shadow-sm p-8 lg:p-12 space-y-5">
            <h1 class="text-3xl font-extrabold text-gray-900">Refund Policy</h1>
            <p class="text-xs text-gray-400">Last updated: 2 November 2026</p>
            <section><h2 class="font-extrabold text-gray-900 mb-1">Returns window</h2><p class="text-sm text-gray-600 leading-relaxed">Most products can be returned within 7 days of delivery. Fresh and perishable items can be reported within 24 hours if they are not fresh on delivery.</p></section>
            <section><h2 class="font-extrabold text-gray-900 mb-1">How to request a return</h2><p class="text-sm text-gray-600 leading-relaxed">Open the delivered order from My Orders, choose Return Items, select the products and reason and attach clear photos or a short video of the issue.</p></section>
            <section><h2 class="font-extrabold text-gray-900 mb-1">Eligibility</h2><p class="text-sm text-gray-600 leading-relaxed">Items must be unused, in original packaging and with all tags/accessories. Personal-care items that have been opened and custom service bookings are not eligible.</p></section>
            <section><h2 class="font-extrabold text-gray-900 mb-1">Refunds</h2><p class="text-sm text-gray-600 leading-relaxed">Approved refunds are credited to the original payment method in 5-7 working days. Cash on Delivery orders are refunded to your Taksh Wallet or bank account.</p></section>
            <section><h2 class="font-extrabold text-gray-900 mb-1">Cancellations</h2><p class="text-sm text-gray-600 leading-relaxed">Orders can be cancelled free of charge until they are shipped. After that, please place a return request once the order is delivered.</p></section>
            <section><h2 class="font-extrabold text-gray-900 mb-1">Contact</h2><p class="text-sm text-gray-600 leading-relaxed">Need help? Visit the <a class="text-brand-600 font-bold hover:underline" href="{{ route('help') }}">Help &amp; Support</a> page or write to care@takshallinone.com.</p></section>
        </article>
@endsection
