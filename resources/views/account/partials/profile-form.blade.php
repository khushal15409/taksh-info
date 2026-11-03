@php($bag = $errors->getBag('profile'))
<h2 class="text-xl font-extrabold text-gray-900">Account Details</h2>
<form method="POST" action="{{ route('profile.update') }}" class="space-y-5" novalidate>
    @csrf
    @method('PUT')
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
        @foreach ([
            ['first_name', 'First Name', 'text', 'f_name', ['maxlength' => 100]],
            ['last_name', 'Last Name', 'text', 'l_name', ['maxlength' => 100]],
            ['mobile', 'Mobile Number', 'text', 'mobile', ['maxlength' => 10, 'inputmode' => 'numeric']],
            ['email', 'Email', 'email', 'email', []],
        ] as [$name, $label, $type, $key, $attrs])
            <div>
                <label for="p-{{ $name }}" class="text-gray-400 font-bold">{{ $label }}</label>
                <input id="p-{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $user->{$key}) }}" class="field mt-1 @if ($bag->has($name)) border-red-400 @endif"
                    @foreach ($attrs as $a => $v) {{ $a }}="{{ $v }}" @endforeach @if ($name !== 'email') required @endif>
                @if ($bag->has($name))<p class="text-red-500 mt-1">{{ $bag->first($name) }}</p>@endif
            </div>
        @endforeach
    </div>
    <button class="bg-brand-500 text-white font-bold text-xs px-6 py-3 rounded-xl hover:bg-brand-600 transition">Save Changes</button>
</form>
