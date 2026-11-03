@php
    $testimonials = [
        ['Hetal Patel', 'Ahmedabad', 'Ordered Diwali groceries and decor in one go - everything arrived the same evening. Prices are better than the local market!', 5],
        ['Rohan Mehta', 'Gandhinagar', 'The 15-minute delivery is real. Milk and bread reached before my chai got cold. Super convenient.', 5],
        ['Sneha Shah', 'Vadodara', 'Booked an electrician before the festival - on time, polite, and upfront pricing. Will use again.', 4],
    ];
@endphp
<section>
    <h2 class="text-xl font-extrabold text-gray-900 mb-4">Loved by 1,00,000+ happy customers</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        @foreach ($testimonials as [$name, $city, $text, $rating])
            <figure class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-3">
                <div class="flex gap-0.5"><x-stars :rating="$rating" size="w-4 h-4" /></div>
                <blockquote class="text-sm text-gray-600 leading-relaxed">“{{ $text }}”</blockquote>
                <figcaption class="flex items-center gap-3 pt-1">
                    <div class="w-9 h-9 rounded-full bg-brand-500 text-white font-bold flex items-center justify-center text-sm">{{ mb_substr($name, 0, 1) }}</div>
                    <div><div class="text-xs font-bold text-gray-800">{{ $name }}</div><div class="text-[11px] text-gray-400">{{ $city }}</div></div>
                </figcaption>
            </figure>
        @endforeach
    </div>
</section>
