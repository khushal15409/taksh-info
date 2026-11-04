{{-- <x-category-tile :category="$c" /> - a round category link for the home strip. --}}
@props(['category'])
@php($c = $category)
<a href="{{ route('listing', ['category' => $c['id']]) }}" class="group snap-start flex-none w-[92px] sm:w-[104px] lg:w-[112px] cat-tile p-2.5 text-center">
    <div class="w-11 h-11 sm:w-12 sm:h-12 mx-auto rounded-full bg-orange-100/70 flex items-center justify-center overflow-hidden mb-1.5 group-hover:scale-110 transition">
        @if (! empty($c['image_url'] ?? $c['icon_url'] ?? null))<img src="{{ $c['image_url'] ?? $c['icon_url'] }}" alt="" loading="lazy" class="w-full h-full object-cover">@else 🛒 @endif
    </div>
    <div class="text-[11px] font-bold text-gray-800 leading-tight line-clamp-2">{{ $c['name'] }}</div>
</a>
