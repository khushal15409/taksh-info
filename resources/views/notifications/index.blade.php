@extends('layouts.app')

@section('title', 'Notifications | TakshAllInOne')
@section('page', 'notifications')

@section('content')

    @php
        $icons = ['offer' => '🏷️', 'promotion' => '🎁', 'order' => '📦', 'delivery' => '🚚', 'payment' => '💳', 'review' => '⭐', 'app' => '🔔'];
        $tabs = ['all' => 'All Notifications', 'offers' => 'Offers & Sales', 'orders' => 'Order Updates', 'unread' => 'Unread'];
    @endphp
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-extrabold text-gray-900">Notifications Hub</h1>
            @if ($notifications && $unread > 0)
                <form method="POST" action="{{ route('notifications.mark-all-read') }}">@csrf<button class="text-xs font-bold text-brand-600 hover:underline">Mark all as read</button></form>
            @endif
        </div>
        @if (! $notifications)
            <div class="bg-white rounded-3xl p-6 border border-orange-100 shadow-sm">
                <x-empty-state emoji="🔔" title="Log in to see your notifications" text="Offers, order updates and more will show up here."
                    cta-label="Log in" :cta-href="route('login', ['next' => request()->getRequestUri()])" />
            </div>
        @else
            <div class="bg-white rounded-3xl p-6 border border-orange-100 shadow-sm space-y-4">
                <div class="flex gap-5 border-b border-gray-100 pb-3 text-xs font-bold overflow-x-auto no-scrollbar">
                    @foreach ($tabs as $key => $label)
                        <a href="{{ route('notifications', ['filter' => $key]) }}" class="pb-1 border-b-2 whitespace-nowrap {{ $filter === $key ? 'text-brand-600 border-brand-500' : 'text-gray-400 border-transparent hover:text-gray-600' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <div class="space-y-3">
                    @forelse ($notifications as $n)
                        <a href="{{ route('notifications.show', $n['id']) }}" class="p-4 rounded-2xl border flex items-start gap-4 hover:border-brand-500/40 {{ $n['is_read'] ? 'bg-white border-gray-100' : 'bg-orange-50/60 border-orange-100' }}">
                            <div class="w-10 h-10 rounded-xl bg-brand-500 text-white flex items-center justify-center text-lg shrink-0">{{ $icons[$n['type']] ?? $icons['app'] }}</div>
                            <div class="flex-1 min-w-0">
                                <h4 class="text-xs font-extrabold text-gray-900">{{ $n['title'] }}</h4>
                                <p class="text-xs text-gray-500 line-clamp-2">{{ $n['message'] }}</p>
                                <span class="text-[10px] text-gray-400">{{ $n['sent_at'] ? \Illuminate\Support\Carbon::parse($n['sent_at'])->format('j M, h:i A') : '' }}</span>
                            </div>
                            @if (! ($n['is_read']))<span class="w-2 h-2 rounded-full bg-brand-500 mt-1.5"></span>@endif
                        </a>
                    @empty
                        <p class="text-center text-sm text-gray-400 py-10">No notifications here yet.</p>
                    @endforelse
                </div>
                <x-pagination :paginator="$notifications" />
            </div>
        @endif
    </div>

@endsection

@push('scripts')

@endpush
