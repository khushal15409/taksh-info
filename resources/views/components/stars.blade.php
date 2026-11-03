{{-- <x-stars :rating="4.5" size="w-5 h-5" /> - five stars, filled up to the rounded rating. --}}
@props(['rating' => 0, 'size' => 'w-3.5 h-3.5'])
@for ($i = 0; $i < 5; $i++)<i data-lucide="star" class="{{ $size }} {{ $i < round((float) $rating) ? 'fill-amber-400 text-amber-400' : 'text-gray-300' }}"></i>@endfor
