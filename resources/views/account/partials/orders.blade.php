{{-- "My Orders": a GET filter form (status, delivery, dates), the orders of the page, and the pages. --}}
@php
    $statuses = ['' => 'All statuses', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'shipped' => 'Shipped', 'out_for_delivery' => 'Out for delivery', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
    $modes = ['' => 'All delivery', 'standard' => 'Standard', 'quick' => 'Quick'];
@endphp
<h2 class="text-xl font-extrabold text-gray-900">My Orders</h2>
<form method="GET" action="{{ route('profile') }}" data-auto-submit class="flex flex-wrap gap-2 text-xs mt-4">
    <input type="hidden" name="tab" value="orders">
    <select name="status" class="field !w-auto !py-2">@foreach ($statuses as $v => $l)<option value="{{ $v }}" @selected($filters->status === $v)>{{ $l }}</option>@endforeach</select>
    <select name="mode" class="field !w-auto !py-2">@foreach ($modes as $v => $l)<option value="{{ $v }}" @selected($filters->mode === $v)>{{ $l }}</option>@endforeach</select>
    <label class="flex items-center gap-1 text-gray-500 font-semibold">From <input name="from" type="date" value="{{ $filters->from }}" class="field !w-auto !py-2"></label>
    <label class="flex items-center gap-1 text-gray-500 font-semibold">To <input name="to" type="date" value="{{ $filters->to }}" class="field !w-auto !py-2"></label>
    <button class="bg-brand-500 hover:bg-brand-600 text-white font-bold px-4 rounded-xl">Filter</button>
    @if ($filters->any())<a href="{{ route('profile', ['tab' => 'orders']) }}" class="self-center font-bold text-brand-600 hover:underline">Clear</a>@endif
</form>

<div class="space-y-3 mt-5">
    @forelse ($orders as $o)
        <a href="{{ route('tracking', ['id' => $o['id']]) }}" class="flex flex-wrap items-center gap-4 p-4 rounded-2xl border border-gray-200 hover:border-brand-500 transition">
            <div class="flex -space-x-3">
                @foreach (array_slice($o['items'], 0, 3) as $i)
                    @if (! empty($i['image_url']))<img src="{{ $i['image_url'] }}" alt="" loading="lazy" class="w-12 h-12 rounded-xl bg-orange-50 border-2 border-white object-contain">@else<span class="w-12 h-12 rounded-xl bg-orange-50 border-2 border-white flex items-center justify-center">🛍️</span>@endif
                @endforeach
            </div>
            <div class="flex-1 min-w-[160px]">
                <div class="font-bold text-sm text-gray-800">#{{ $o['order_number'] }} @if ($o['is_quick'])<span class="text-emerald-600 text-[10px]">⚡ Quick</span>@endif</div>
                <div class="text-[11px] text-gray-400">{{ \Illuminate\Support\Carbon::parse($o['created_at'])->format('j M Y') }} • {{ collect($o['items'])->sum('qty') }} item(s)</div>
            </div>
            <x-status-badge :status="$o['order_status']" class="!text-[11px] !px-2.5 !py-1" />
            <div class="font-extrabold text-brand-600">@rupee2($o['grand_total'])</div>
        </a>
    @empty
        <x-empty-state emoji="📦" title="No orders found" :text="$filters->any() ? 'Try changing the filters.' : 'Your placed orders will show up here.'" cta-label="Start Shopping" :cta-href="route('listing')" />
    @endforelse
</div>
<x-pagination :paginator="$orders" class="pt-5" />
