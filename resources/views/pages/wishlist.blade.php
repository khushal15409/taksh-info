@extends('layouts.app')

@section('title', 'My Wishlist | TakshAllInOne')
@section('page', 'wishlist')

@section('content')

        <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
            <div><h1 class="text-2xl font-extrabold text-gray-900">My Wishlist ❤️</h1><p id="wish-count" class="text-xs text-gray-500 mt-1"></p></div>
            <button id="add-all" class="bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs px-5 py-2.5 rounded-xl">Add all to cart</button>
        </div>
        <div id="wish-root"></div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/pages/wishlist.js') }}"></script>
@endpush
