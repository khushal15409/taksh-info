{{-- A server-rendered dialog: <x-modal id="cancel-order" title="Cancel this order?"> ...form... </x-modal>.
     Open it with any element carrying data-modal-open="cancel-order"; close with data-modal-close (or Esc / the backdrop). --}}
@props(['id', 'title' => null])
<div id="{{ $id }}" data-modal hidden class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @if ($title) aria-labelledby="{{ $id }}-title" @endif>
    <div data-modal-close class="absolute inset-0 bg-black/50"></div>
    <div {{ $attributes->merge(['class' => 'relative bg-white rounded-3xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6']) }}>
        <div class="flex items-start justify-between gap-4 mb-4">
            @if ($title)<h3 id="{{ $id }}-title" class="text-lg font-extrabold text-gray-900">{{ $title }}</h3>@else<span></span>@endif
            <button type="button" data-modal-close class="text-gray-400 hover:text-gray-700 text-xl leading-none" aria-label="Close">&times;</button>
        </div>
        {{ $slot }}
    </div>
</div>
