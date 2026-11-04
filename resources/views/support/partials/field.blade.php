{{-- One field of an enquiry form. $f = [name, label, type, options?, value?, hint?, wide?, maxlength?]; $bag holds this form's errors, $defaults what we know of the customer. --}}
@php
    $name = $f['name'];
    // a select can be preselected by the link that brought the customer here (?kind=Create Website)
    $value = old($name, ($f['type'] === 'select' ? request()->query($name) : null) ?? $f['value'] ?? ($defaults[$name] ?? ''));
    $error = $bag->first($name);
    $base = 'field mt-1'.($error ? ' border-red-400' : '');
    $span = ! empty($f['wide']) ? 'col-span-2' : 'col-span-2 sm:col-span-1';
@endphp
@if ($f['type'] === 'checkbox')
    <div class="{{ $span }}">
        <label class="flex items-start gap-2 text-xs text-gray-600 cursor-pointer"><input type="checkbox" name="{{ $name }}" value="1" class="accent-brand-500 mt-0.5" @checked(old($name))><span>{{ $f['label'] }}</span></label>
        @if ($error)<p class="text-red-500 mt-1">{{ $error }}</p>@endif
    </div>
@else
    <div class="{{ $span }}">
        <label for="f-{{ $name }}" class="font-bold text-gray-500">{{ $f['label'] }}</label>
        @if ($f['type'] === 'select')
            <select id="f-{{ $name }}" name="{{ $name }}" class="{{ $base }}">
                @foreach ($f['options'] as $k => $label)
                    @php($optValue = array_is_list($f['options']) ? $label : $k)
                    <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $label }}</option>
                @endforeach
            </select>
        @elseif ($f['type'] === 'textarea')
            <textarea id="f-{{ $name }}" name="{{ $name }}" rows="3" maxlength="1500" class="{{ $base }}">{{ $value }}</textarea>
        @else
            <input id="f-{{ $name }}" name="{{ $name }}" type="{{ $f['type'] }}" value="{{ $value }}" @if (! empty($f['maxlength'])) maxlength="{{ $f['maxlength'] }}" @endif @if (! empty($f['inputmode'])) inputmode="{{ $f['inputmode'] }}" @endif class="{{ $base }}">
        @endif
        @if ($error)<p class="text-red-500 mt-1">{{ $error }}</p>@elseif (! empty($f['hint']))<p class="text-[11px] text-gray-400 mt-1">{{ $f['hint'] }}</p>@endif
    </div>
@endif
