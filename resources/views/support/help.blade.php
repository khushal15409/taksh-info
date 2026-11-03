@extends('layouts.app')

@section('title', 'Help & Support | TakshAllInOne')
@section('page', 'help')

@section('content')

    @php
        $phone = config('taksh.support_phone');
        $email = config('taksh.support_email');
        $wa = 'https://wa.me/'.preg_replace('/\D/', '', $phone).'?text='.rawurlencode('Hi Taksh, I need help with my order.');
        $mail = 'mailto:'.$email.'?subject='.rawurlencode('Taksh support request');
        $cards = [
            [$wa, 'message-circle', 'bg-emerald-100 text-emerald-600', 'Live chat on WhatsApp', 'Chat with a Taksh care executive', true],
            [$mail, 'mail', 'bg-sky-100 text-sky-600', 'Email support', $email, false],
            ['tel:'.preg_replace('/\s/', '', $phone), 'phone', 'bg-orange-100 text-brand-600', 'Call us', $phone, false],
        ];
    @endphp
    <div class="max-w-5xl mx-auto space-y-8">
        <div><h1 class="text-2xl font-extrabold text-gray-900">Help &amp; Support</h1><p class="text-sm text-gray-500 mt-1">Choose how you'd like to reach us. We're here 24x7.</p></div>
        <div class="grid md:grid-cols-3 gap-4">
            @foreach ($cards as [$href, $icon, $tint, $title, $text, $blank])
                <a href="{{ $href }}" @if ($blank) target="_blank" rel="noopener" @endif class="bg-white p-5 rounded-3xl border border-orange-100 hover:border-brand-500/50 hover:shadow-card-hover transition flex items-start gap-4">
                    <div class="w-11 h-11 rounded-2xl {{ $tint }} flex items-center justify-center shrink-0"><i data-lucide="{{ $icon }}" class="w-5 h-5"></i></div>
                    <div><h3 class="font-extrabold text-sm text-gray-900">{{ $title }}</h3><p class="text-xs text-gray-500 mt-0.5">{{ $text }}</p></div>
                </a>
            @endforeach
        </div>

        <div class="grid lg:grid-cols-5 gap-6">
            <section class="lg:col-span-2 bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-4 h-fit">
                <div><h2 class="font-extrabold text-base text-gray-900">Request a callback</h2><p class="text-xs text-gray-500">We'll call you back on the number registered with your account.</p></div>
                @if ($done)
                    @include('support.partials.thanks', ['done' => $done])
                @elseif (! $user)
                    <a href="{{ route('login', ['next' => request()->getRequestUri()]) }}" class="block text-center bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm py-3 rounded-2xl">Log in so we know whom to call</a>
                @else
                    <form method="POST" action="{{ route('support.callback.store') }}" data-lock>@csrf<button class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm py-3 rounded-2xl transition disabled:opacity-60">Request Callback</button></form>
                @endif
            </section>
            <section class="lg:col-span-3 space-y-3">
                <h2 class="font-extrabold text-base text-gray-900">Frequently asked questions</h2>
                @foreach ($faqs as $f)
                    <details class="bg-white rounded-2xl border border-gray-100 p-4"><summary class="flex items-center justify-between font-bold text-sm text-gray-800 cursor-pointer">{{ $f['q'] }}<i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i></summary><p class="text-xs text-gray-500 mt-3 leading-relaxed">{{ $f['a'] }}</p></details>
                @endforeach
            </section>
        </div>

        <div class="grid sm:grid-cols-3 gap-4 text-xs font-bold">
            <a href="{{ route('delivery') }}" class="bg-emerald-50 text-emerald-800 rounded-2xl p-4 hover:shadow">🛵 Join as Delivery Partner →</a>
            <a href="{{ route('vendor') }}" class="bg-purple-50 text-purple-800 rounded-2xl p-4 hover:shadow">🏪 Become a Vendor →</a>
            <a href="{{ route('devrequest') }}" class="bg-sky-50 text-sky-800 rounded-2xl p-4 hover:shadow">💻 Development Request →</a>
        </div>
    </div>

@endsection

@push('scripts')

    <script src="{{ asset('assets/js/modules/support.js') }}"></script>

@endpush
