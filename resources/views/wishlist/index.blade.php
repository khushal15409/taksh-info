@extends('layouts.app')

@section('title', 'My Wishlist | TakshAllInOne')
@section('page', 'wishlist')

@section('content')
@if ($items === null)
    <div class="flex flex-wrap items-end justify-between gap-3 mb-6"><h1 class="text-2xl font-extrabold text-gray-900">My Wishlist ❤️</h1></div>
    <x-empty-state emoji="🔐" title="Login to see your wishlist" text="Log in with your mobile number to save products and find them again on any device."
        cta-label="Login with OTP" :cta-href="route('login', ['next' => request()->getRequestUri()])" />
@else
    <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
        <div><h1 class="text-2xl font-extrabold text-gray-900">My Wishlist ❤️</h1><p class="text-xs text-gray-500 mt-1">{{ $paginator->total() }} saved item(s)</p></div>
    </div>
    @if ($items)
        <div id="wish-grid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 lg:gap-5">
            @foreach ($items as $i)
                @php($card = ['id' => $i['product_id'], 'name' => $i['name'], 'price' => $i['price'], 'original_price' => $i['original_price'], 'discount' => \App\Support\Presenters\ProductCard::discount((float) $i['price'], (float) $i['original_price']), 'brand' => null, 'rating_summary' => ['total_reviews' => 0], 'in_stock' => $i['in_stock'], 'variant_id' => null, 'out_of_stock_message' => null, 'image_url' => $i['thumbnail']])
                <div class="relative" data-wish-card="{{ $i['product_id'] }}">
                    <x-product-card :product="$card" />
                    <form method="POST" action="{{ route('wishlist.forget', $i['product_id']) }}" class="mt-2 text-center">@csrf @method('DELETE')<button class="text-[11px] font-bold text-gray-400 hover:text-red-500">Remove</button></form>
                </div>
            @endforeach
        </div>
        <x-pagination :paginator="$paginator" class="pt-8" />
    @else
        <x-empty-state emoji="💝" title="Your wishlist is empty" text="Tap the heart on any product to save it here for later." cta-label="Explore Products" :cta-href="route('listing')" />
    @endif
@endif
@endsection

@push('scripts')

    <script src="{{ asset('assets/js/modules/wishlist.js') }}"></script>

@endpush
