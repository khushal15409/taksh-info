@php
    $all = collect([$addressBook['default_address'], ...$addressBook['addresses']])->filter()->values();
    $line = fn (array $a) => collect([$a['address_line_1'], $a['address_line_2'], $a['landmark'] ? 'Near '.$a['landmark'] : null, $a['area'], $a['city'], $a['state']])->filter()->implode(', ').($a['pincode'] ? ' - '.$a['pincode'] : '');
@endphp
<div class="flex items-center justify-between">
    <h2 class="text-xl font-extrabold text-gray-900">Saved Addresses</h2>
    <button type="button" data-address-new class="bg-brand-500 text-white font-bold text-xs px-4 py-2 rounded-xl hover:bg-brand-600">+ Add New</button>
</div>
<div class="grid md:grid-cols-2 gap-4">
    @forelse ($all as $a)
        <div class="p-4 rounded-2xl border-2 {{ $a['is_default'] ? 'border-brand-500 bg-orange-50/40' : 'border-gray-200' }} text-xs space-y-1">
            <div class="flex items-center gap-2">
                <span class="bg-brand-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">{{ $a['address_type'] }}</span>
                @if ($a['is_default'])<span class="text-emerald-600 font-bold">Default</span>@endif
            </div>
            <h4 class="font-bold text-sm text-gray-800 pt-1">{{ $a['name'] }}</h4>
            <p class="text-gray-500">{{ $line($a) }}</p>
            <p class="text-gray-500">Phone: {{ $a['mobile'] }}</p>
            <div class="flex gap-3 pt-2 font-bold items-center">
                <button type="button" data-address-edit='@json($a)' class="text-brand-600 hover:underline">Edit</button>
                @if (! ($a['is_default']))
                    <form method="POST" action="{{ route('profile.addresses.default', $a['id']) }}">@csrf<button class="text-gray-500 hover:underline">Set default</button></form>
                @endif
                @if ($all->count() > 1)
                    <form method="POST" action="{{ route('profile.addresses.destroy', $a['id']) }}" onsubmit="return confirm('Delete this address?')">@csrf @method('DELETE')<button class="text-red-500 hover:underline">Delete</button></form>
                @endif
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-400">No saved addresses yet.</p>
    @endforelse
</div>
