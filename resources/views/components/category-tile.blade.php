{{-- <x-category-tile :category="$c" /> - a round category link for the home strip. --}}
@props(['category'])
@php($c = $category)
<a href="{{ route('listing', ['category' => $c['id']]) }}" class="group snap-start flex-none w-[118px] sm:w-[136px] lg:w-[144px] bg-white p-4 rounded-2xl border border-gray-100 hover:border-brand-500/50 text-center hover:shadow-md transition">
    <div class="w-16 h-16 mx-auto rounded-full bg-orange-50 flex items-center justify-center overflow-hidden mb-2 group-hover:scale-110 transition">
        @if (! empty($c['image_url'] ?? $c['icon_url'] ?? null))<img src="{{ $c['image_url'] ?? $c['icon_url'] }}" alt="" loading="lazy" class="w-full h-full object-cover">@else 🛒 @endif
    </div>
    <div class="text-xs font-bold text-gray-800 leading-tight">{{ $c['name'] }}</div>
    @if (isset($c['products_count']))<div class="text-[10px] text-gray-400 mt-0.5">{{ $c['products_count'] }} items</div>@endif
</a>
