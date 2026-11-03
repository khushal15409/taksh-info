{{-- <x-empty-state emoji="🛒" title="Your cart is empty" text="..." cta-label="Shop now" :cta-href="route('listing')" /> --}}
@props(['emoji' => '😕', 'title', 'text' => null, 'ctaLabel' => null, 'ctaHref' => null])
<div {{ $attributes->merge(['class' => 'text-center py-16 space-y-3']) }}>
    <div class="text-5xl">{{ $emoji }}</div>
    <h3 class="text-lg font-extrabold text-gray-900">{{ $title }}</h3>
    @if ($text)<p class="text-sm text-gray-500 max-w-sm mx-auto">{{ $text }}</p>@endif
    @if ($ctaLabel && $ctaHref)
        <a href="{{ $ctaHref }}" class="inline-block bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm px-6 py-3 rounded-2xl">{{ $ctaLabel }}</a>
    @endif
</div>
