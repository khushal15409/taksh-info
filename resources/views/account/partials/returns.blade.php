<h2 class="text-xl font-extrabold text-gray-900">My Returns</h2>
<div class="space-y-3 mt-5">
    @forelse ($returns as $r)
        <a href="{{ route('returns.show', $r['id']) }}" class="w-full flex flex-wrap items-center gap-4 p-4 rounded-2xl border border-gray-200 hover:border-brand-500 transition">
            @if (! empty($r['image_url']))<img src="{{ $r['image_url'] }}" alt="" loading="lazy" class="w-12 h-12 rounded-xl bg-orange-50 object-contain">@else<span class="w-12 h-12 rounded-xl bg-orange-50 flex items-center justify-center">🛍️</span>@endif
            <div class="flex-1 min-w-[160px]">
                <div class="font-bold text-sm text-gray-800">{{ $r['product_name'] ?? 'Item' }} × {{ $r['quantity'] ?? 1 }}</div>
                <div class="text-[11px] text-gray-400">#{{ $r['order_number'] ?? '' }} • {{ $r['resolution_label'] ?? '' }} • {{ $r['requested_at'] ? \Illuminate\Support\Carbon::parse($r['requested_at'])->format('j M Y, h:i A') : '' }}</div>
            </div>
            <x-status-badge :status="$r['status']" :label="$r['status_label']" class="!text-[11px] !px-2.5 !py-1" />
        </a>
    @empty
        <x-empty-state emoji="↩️" title="No returns yet" text="Items you send back will be tracked here." cta-label="My Orders" :cta-href="route('profile', ['tab' => 'orders'])" />
    @endforelse
</div>
<x-pagination :paginator="$returns" class="pt-5" />
