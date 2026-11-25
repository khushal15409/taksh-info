@extends('layouts.app')

@section('title', 'Delete Account | TakshAllInOne')
@section('page', 'delete-account')

@section('content')
        <article class="max-w-3xl mx-auto bg-white rounded-3xl border border-orange-100 shadow-sm p-6 sm:p-10 space-y-6">
            <h1 class="text-3xl font-extrabold font-festive text-gray-900">Delete Account</h1>
            <section class="space-y-2"><h2 class="font-extrabold text-gray-900 text-base">How to request deletion</h2>
                <p class="text-sm text-gray-600 leading-relaxed">To delete your TakshAllInOne account and associated data, email {{ config('taksh.support_email') }} from your registered email, or call {{ config('taksh.support_phone') }}, with the subject "Delete my account" and your registered mobile number.</p></section>
            <section class="space-y-2"><h2 class="font-extrabold text-gray-900 text-base">What gets deleted</h2>
                <p class="text-sm text-gray-600 leading-relaxed">Your profile, saved addresses, wishlist and notification preferences are permanently removed once the request is verified.</p></section>
            <section class="space-y-2"><h2 class="font-extrabold text-gray-900 text-base">What we may retain</h2>
                <p class="text-sm text-gray-600 leading-relaxed">Order, invoice and payment records are kept for the period required by Indian tax and accounting laws. Any wallet balance should be used or withdrawn before deletion.</p></section>
            <section class="space-y-2"><h2 class="font-extrabold text-gray-900 text-base">Timeline</h2>
                <p class="text-sm text-gray-600 leading-relaxed">Requests are verified and processed within 7 working days. You will get a confirmation once it is complete. Deletion cannot be undone.</p></section>
        </article>
@endsection
