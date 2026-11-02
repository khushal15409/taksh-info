@extends('layouts.app')

@section('title', 'About Us | TakshAllInOne')
@section('page', 'about')

@section('content')

        <article class="max-w-3xl mx-auto bg-white rounded-3xl border border-orange-100 shadow-sm p-8 lg:p-12 space-y-5">
            <h1 class="text-3xl font-extrabold text-gray-900">About Us</h1>
            <section><h2 class="font-extrabold text-gray-900 mb-1">Who we are</h2><p class="text-sm text-gray-600 leading-relaxed">TakshAllInOne is an all-in-one platform for groceries, 15-minute quick delivery, festive decor, electronics, courier booking and verified home services, built for families across Gujarat and growing across India.</p></section>
            <section><h2 class="font-extrabold text-gray-900 mb-1">What we do</h2><p class="text-sm text-gray-600 leading-relaxed">We connect customers with local sellers, dark stores, delivery partners and background-verified service professionals so everyday needs are met from a single app.</p></section>
            <section><h2 class="font-extrabold text-gray-900 mb-1">Our promise</h2><p class="text-sm text-gray-600 leading-relaxed">Genuine products, transparent pricing, easy returns and friendly support. Every delivery partner and service professional is verified before they join Taksh.</p></section>
            <section><h2 class="font-extrabold text-gray-900 mb-1">Partner with us</h2><p class="text-sm text-gray-600 leading-relaxed">Want to deliver, sell or build with Taksh? Join as a <a class="text-brand-600 font-bold hover:underline" href="{{ route('delivery') }}">delivery partner</a>, become a <a class="text-brand-600 font-bold hover:underline" href="{{ route('vendor') }}">vendor</a> or send a <a class="text-brand-600 font-bold hover:underline" href="{{ route('devrequest') }}">development request</a>.</p></section>
            <section><h2 class="font-extrabold text-gray-900 mb-1">Contact</h2><p class="text-sm text-gray-600 leading-relaxed">care@takshallinone.com · Ghatlodiya, Ahmedabad 380061.</p></section>
        </article>
@endsection
