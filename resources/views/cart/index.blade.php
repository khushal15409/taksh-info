@extends('layouts.app')

@section('title', 'Your Shopping Cart | TakshAllInOne')
@section('page', 'cart')

@section('content')

    <h1 class="text-2xl font-extrabold text-gray-900 flex items-center gap-3 mb-5"><i data-lucide="shopping-cart" class="w-7 h-7 text-brand-500"></i><span>Your Shopping Cart</span></h1>
    <div id="cart-fragment">
        @include('cart.partials.fragment')
    </div>

@endsection

@push('scripts')

    <script src="{{ asset('assets/js/modules/cart.js') }}"></script>

@endpush
