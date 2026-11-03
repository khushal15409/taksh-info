@extends('layouts.app')

@section('title', $title.' | TakshAllInOne')
@section('page', $page)

@section('content')

    <div class="max-w-4xl mx-auto space-y-6">
        <section class="rounded-3xl bg-gradient-to-r {{ $form['tint'] }} text-white p-8 lg:p-10 relative overflow-hidden">
            <div class="absolute -right-4 -bottom-6 text-[8rem] opacity-20 select-none">{{ $form['emoji'] }}</div>
            <h1 class="text-2xl lg:text-3xl font-extrabold font-festive relative">{{ $form['title'] }}</h1>
            <p class="text-sm text-white/90 max-w-xl mt-2 relative">{{ $form['intro'] }}</p>
            <div class="flex flex-wrap gap-3 mt-5 relative">
                @foreach ($form['perks'] as [$icon, $label])<span class="bg-white/20 backdrop-blur px-3 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5"><i data-lucide="{{ $icon }}" class="w-3.5 h-3.5"></i>{{ $label }}</span>@endforeach
            </div>
        </section>

        <section class="bg-white p-6 lg:p-8 rounded-3xl border border-orange-100 shadow-sm">
            @if ($done)
                @include('support.partials.thanks', ['done' => $done])
            @else
                <form method="POST" action="{{ route($form['route']) }}" data-lock class="grid grid-cols-2 gap-4 text-xs" novalidate>
                    @csrf
                    @foreach ($form['fields'] as $f)
                        @include('support.partials.field', ['f' => $f])
                    @endforeach
                    <button class="col-span-2 bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm py-3 rounded-2xl transition disabled:opacity-60">{{ $form['submit'] }}</button>
                </form>
            @endif
        </section>
    </div>

@endsection

@push('scripts')

    <script src="{{ asset('assets/js/modules/support.js') }}"></script>

@endpush
