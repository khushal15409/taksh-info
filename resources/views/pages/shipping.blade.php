@extends('layouts.app')

@section('title', 'Shipping Policy | TakshAllInOne')
@section('page', 'shipping')

@section('content')
        <article class="max-w-3xl mx-auto bg-white rounded-3xl border border-orange-100 shadow-sm p-6 sm:p-10 space-y-6">
            <h1 class="text-3xl font-extrabold font-festive text-gray-900">Shipping Policy</h1>
            <section class="space-y-2"><h2 class="font-extrabold text-gray-900 text-base">Delivery Areas</h2>
                <p class="text-sm text-gray-600 leading-relaxed">We deliver to most serviceable pincodes across India. Enter your pincode on the product page to check availability and the estimated delivery date.</p></section>
            <section class="space-y-2"><h2 class="font-extrabold text-gray-900 text-base">Delivery Timelines</h2>
                <p class="text-sm text-gray-600 leading-relaxed">Quick-delivery items are delivered the same day or within a few hours in eligible areas. Standard orders are usually delivered within 3-7 business days, depending on your location and the seller.</p></section>
            <section class="space-y-2"><h2 class="font-extrabold text-gray-900 text-base">Shipping Charges</h2>
                <p class="text-sm text-gray-600 leading-relaxed">Shipping charges, if any, are shown at checkout before you pay. Free delivery may apply on eligible orders or offers.</p></section>
            <section class="space-y-2"><h2 class="font-extrabold text-gray-900 text-base">Order Tracking</h2>
                <p class="text-sm text-gray-600 leading-relaxed">Once your order is shipped you can follow it from the Track Order page or from Your Orders in your account.</p></section>
            <section class="space-y-2"><h2 class="font-extrabold text-gray-900 text-base">Delays &amp; Failed Deliveries</h2>
                <p class="text-sm text-gray-600 leading-relaxed">Delivery may be delayed by weather, strikes or incorrect address details. If delivery fails, our partner will try again; undelivered orders are returned and refunded as per our Refund Policy.</p></section>
            <p class="text-sm text-gray-600 leading-relaxed">Questions? Write to {{ config('taksh.support_email') }} or call {{ config('taksh.support_phone') }}.</p>
        </article>
@endsection
