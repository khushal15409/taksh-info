@php
    $tabs = ['profile' => ['My Profile', 'user'], 'addresses' => ['Addresses', 'map-pin'], 'orders' => ['My Orders', 'package'], 'returns' => ['My Returns', 'undo-2']];
    $link = 'w-full px-3 py-2 rounded-xl flex items-center gap-2 text-gray-600 hover:bg-gray-50';
    $name = (string) $user->name;
@endphp
<div class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-4 h-fit">
    <div class="flex items-center gap-3 border-b pb-4 border-gray-100">
        <div class="w-12 h-12 rounded-2xl bg-brand-500 text-white font-extrabold text-xl flex items-center justify-center">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($name, 0, 1)) }}</div>
        <div class="min-w-0"><h3 class="font-extrabold text-sm text-gray-900 truncate">{{ $name }}</h3><p class="text-[11px] text-gray-400 truncate">{{ $user->email }}</p></div>
    </div>
    <div class="space-y-1 text-xs font-semibold">
        @foreach ($tabs as $key => [$label, $icon])
            <a href="{{ route('profile', ['tab' => $key]) }}" class="w-full text-left px-3 py-2 rounded-xl flex items-center gap-2 {{ $tab === $key ? 'bg-orange-50 text-brand-600 font-bold' : 'text-gray-600 hover:bg-gray-50' }}"><i data-lucide="{{ $icon }}" class="w-4 h-4"></i>{{ $label }}</a>
        @endforeach
        <a href="{{ route('wishlist') }}" class="{{ $link }}"><i data-lucide="heart" class="w-4 h-4"></i>Wishlist</a>
        <a href="{{ route('notifications') }}" class="{{ $link }}"><i data-lucide="bell" class="w-4 h-4"></i>Notifications</a>
        <a href="{{ route('wallet') }}" class="{{ $link }}"><i data-lucide="wallet" class="w-4 h-4"></i>Wallet</a>
        <a href="{{ route('help') }}" class="{{ $link }}"><i data-lucide="life-buoy" class="w-4 h-4"></i>Help &amp; Support</a>
        <button type="button" data-open-prefs class="{{ $link }} text-left"><i data-lucide="settings" class="w-4 h-4"></i>Language &amp; Theme</button>
        <a href="{{ route('about') }}" class="{{ $link }}"><i data-lucide="info" class="w-4 h-4"></i>About, Refund &amp; Legal</a>
        <div class="border-t border-gray-100 my-2"></div>
        <a href="{{ route('delivery') }}" class="{{ $link }}"><i data-lucide="bike" class="w-4 h-4"></i>Join as Delivery Partner</a>
        <a href="{{ route('vendor') }}" class="{{ $link }}"><i data-lucide="store" class="w-4 h-4"></i>Become a Vendor</a>
        <a href="{{ route('devrequest') }}" class="{{ $link }}"><i data-lucide="code-2" class="w-4 h-4"></i>Development Request</a>
        <div class="border-t border-gray-100 my-2"></div>
        <form method="POST" action="{{ route('logout') }}">@csrf
            <button class="w-full text-left px-3 py-2 rounded-xl flex items-center gap-2 text-red-500 hover:bg-red-50"><i data-lucide="log-out" class="w-4 h-4"></i>Logout</button>
        </form>
    </div>
</div>
