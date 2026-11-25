@extends('layouts.app')

@section('title', 'Contact Us | TakshAllInOne')
@section('page', 'contact')

@section('content')
        <article class="max-w-3xl mx-auto bg-white rounded-3xl border border-orange-100 shadow-sm p-6 sm:p-10 space-y-6">
            <h1 class="text-3xl font-extrabold font-festive text-gray-900">Contact Us</h1>
            <p class="text-sm text-gray-600 leading-relaxed">We are happy to help with orders, payments, returns and anything else.</p>
            <div class="space-y-3 text-sm text-gray-700">
                <p class="flex items-center gap-2"><i data-lucide="phone" class="w-4 h-4 text-brand-500"></i><a href="tel:{{ preg_replace('/[^\d+]/', '', config('taksh.support_phone')) }}" class="font-semibold">{{ config('taksh.support_phone') }}</a></p>
                <p class="flex items-center gap-2"><i data-lucide="mail" class="w-4 h-4 text-brand-500"></i><a href="mailto:{{ config('taksh.support_email') }}" class="font-semibold">{{ config('taksh.support_email') }}</a></p>
                <p class="flex items-center gap-2"><i data-lucide="map-pin" class="w-4 h-4 text-brand-500"></i>{{ config('taksh.support_address') }}</p>
            </div>
            <a href="{{ route('help') }}" target="_blank" rel="noopener" class="inline-block bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm px-5 py-3 rounded-2xl transition">Help &amp; Support</a>
        </article>
@endsection
