{{-- The reviews tab: rating summary, the customer's own review form, and the latest approved reviews. --}}
@php($breakup = (array) ($rs['rating_breakup'] ?? []))
<div class="grid md:grid-cols-3 gap-8">
    <div class="space-y-3">
        <div class="text-5xl font-extrabold text-gray-900">{{ $rs['average_rating'] }}</div>
        <div class="flex gap-0.5"><x-stars :rating="$rs['average_rating']" size="w-5 h-5" /></div>
        <p class="text-xs text-gray-400">Based on {{ $rs['total_reviews'] }} review(s)</p>
        @foreach ([5, 4, 3, 2, 1] as $s)
            @php($n = $breakup[$s] ?? $breakup[(string) $s] ?? 0)
            <div class="flex items-center gap-2 text-xs"><span class="w-6">{{ $s }}★</span><div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden"><div class="h-full bg-amber-400" style="width:{{ $rs['total_reviews'] ? round($n / $rs['total_reviews'] * 100) : 0 }}%"></div></div><span class="w-8 text-right text-gray-400">{{ $n }}</span></div>
        @endforeach
    </div>
    <div class="md:col-span-2 space-y-4">
        @if (! auth()->check())
            <p class="text-xs text-gray-500 bg-orange-50/60 rounded-2xl p-4"><a class="font-bold text-brand-600 underline" href="{{ route('login', ['next' => request()->getRequestUri()]) }}">Log in</a> to rate this product after it has been delivered to you.</p>
        @elseif (! ($reviewState['can_review'] ?? false))
            <p class="text-xs text-gray-500 bg-orange-50/60 rounded-2xl p-4">You can rate this product after it has been delivered to you.</p>
        @else
            @php($mine = $reviewState['review'] ?? ['rating' => 5, 'title' => '', 'review' => ''])
            <form id="lp-rf" class="bg-orange-50/60 rounded-2xl p-4 space-y-3 text-xs">
                <div class="font-bold text-gray-800">{{ $reviewState['has_reviewed'] ? 'Edit your review' : 'Rate this product' }}</div>
                <div class="flex gap-1">
                    @foreach ([1, 2, 3, 4, 5] as $n)<label class="cursor-pointer"><input type="radio" name="rating" value="{{ $n }}" class="sr-only peer" @checked($n === (int) $mine['rating'])><span class="text-2xl text-gray-300 peer-checked:text-amber-400">★</span></label>@endforeach
                </div>
                <input name="review_title" maxlength="150" class="field" placeholder="Title (optional)" value="{{ $mine['title'] ?? '' }}">
                <textarea name="review_text" maxlength="2000" rows="3" class="field" placeholder="Write your review (optional)">{{ $mine['review'] ?? '' }}</textarea>
                <button class="bg-brand-500 hover:bg-brand-600 text-white font-bold px-5 py-2.5 rounded-xl">{{ $reviewState['has_reviewed'] ? 'Update review' : 'Submit review' }}</button>
            </form>
        @endif

        @forelse ($product['reviews'] as $r)
            <div class="border-b border-gray-100 pb-4">
                <div class="flex items-center gap-2">
                    <span class="flex gap-0.5"><x-stars :rating="$r['rating']" size="w-3.5 h-3.5" /></span>
                    @if (! empty($r['verified']))<span class="text-[10px] font-bold text-emerald-600">✔ Verified purchase</span>@endif
                    <span class="text-[11px] text-gray-400 ml-auto">{{ $r['created_at'] ?? '' }}</span>
                </div>
                @if (! empty($r['title']))<div class="text-xs font-bold text-gray-800 mt-1">{{ $r['title'] }}</div>@endif
                @if (! empty($r['review']))<p class="text-xs text-gray-600 mt-1">{{ $r['review'] }}</p>@endif
            </div>
        @empty
            <p class="text-xs text-gray-400">No reviews yet.</p>
        @endforelse
    </div>
</div>
