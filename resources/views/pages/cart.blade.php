@extends('layouts.app')

@section('title', 'Your Shopping Cart | TakshAllInOne')
@section('page', 'cart')

@section('content')

        <h1 class="text-2xl font-extrabold text-gray-900 flex items-center gap-3 mb-5"><i data-lucide="shopping-cart" class="w-7 h-7 text-brand-500"></i><span>Your Shopping Cart</span></h1>
        <div id="cart-tabs" class="flex border-b border-gray-200 gap-6 text-sm font-bold mb-6 overflow-x-auto no-scrollbar"></div>
        <div id="cart-root"></div>
        <section id="cart-recs" class="mt-12"></section>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/pages/cart.js') }}"></script>
@endpush
