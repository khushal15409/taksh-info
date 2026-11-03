@extends('layouts.app')

@section('title', 'Home Services, Courier & Help | TakshAllInOne')
@section('page', 'services')

@section('content')

    @php
        $tints = ['bg-purple-100', 'bg-blue-100', 'bg-emerald-100', 'bg-orange-100', 'bg-pink-100', 'bg-sky-100'];
        $tabs = ['' => 'All', 'pending' => 'Pending', 'upcoming' => 'Upcoming', 'active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
        $phone = config('taksh.support_phone');
        $email = config('taksh.support_email');
    @endphp
    <div class="space-y-14">
        @if ($banners)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($banners as $b)<a href="{{ route('services') }}" class="block rounded-3xl overflow-hidden border border-purple-100 shadow-sm hover:shadow-md transition"><img src="{{ $b['image_url'] }}" alt="{{ $b['title'] ?? 'Service offer' }}" loading="lazy" class="w-full object-cover"></a>@endforeach
            </div>
        @endif

        <section class="text-center max-w-xl mx-auto space-y-2">
            <span class="bg-purple-100 text-purple-700 text-xs font-extrabold px-3 py-1 rounded-full">Taksh Home Care</span>
            <h1 class="text-3xl font-extrabold text-gray-900">Expert Services at Your Doorstep</h1>
            <p class="text-sm text-gray-500">Verified professionals arriving within 60 minutes</p>
        </section>

        <section id="services">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @forelse ($services as $s)
                    @php($ready = $s['service_charge'] > 0)
                    <div class="bg-white p-6 rounded-3xl border border-gray-100 hover:border-purple-400 hover:shadow-lg transition text-center space-y-3 flex flex-col">
                        <div class="w-16 h-16 mx-auto rounded-2xl {{ $tints[$loop->index % count($tints)] }} flex items-center justify-center text-3xl overflow-hidden">@if ($s['image'])<img src="{{ $s['image'] }}" alt="" loading="lazy" class="w-full h-full object-cover">@else 🛠️ @endif</div>
                        <h3 class="font-extrabold text-base text-gray-800">{{ $s['name'] }}</h3>
                        <p class="text-xs text-gray-400 flex-1">{{ $s['short_description'] }}</p>
                        <div class="flex items-center justify-center gap-3 text-xs"><span class="font-bold text-brand-600">@if ($ready) @rupee($s['service_charge']) @else Price on request @endif</span>@if ($s['duration'])<span class="text-gray-400">• {{ $s['duration'] }}</span>@endif</div>
                        @if ($ready)
                            <a href="{{ route('services.book', $s['id']) }}" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs py-2.5 rounded-xl transition">Book Now</a>
                        @else
                            <span class="w-full bg-gray-300 text-white font-bold text-xs py-2.5 rounded-xl">Unavailable</span>
                        @endif
                    </div>
                @empty
                    <p class="col-span-full text-center text-sm text-gray-400 py-10">No services are available right now.</p>
                @endforelse
            </div>
        </section>

        @if ($bookings)
            <section id="bookings" class="space-y-3 max-w-3xl mx-auto w-full">
                <h2 class="text-lg font-extrabold text-gray-900">My Service Bookings</h2>
                <div class="flex gap-4 border-b border-gray-100 pb-2 text-xs font-bold overflow-x-auto no-scrollbar">
                    @foreach ($tabs as $k => $l)<a href="{{ route('services', array_filter(['category' => $k])) }}#bookings" class="pb-1 border-b-2 whitespace-nowrap {{ ($category ?? '') === $k ? 'text-brand-600 border-brand-500' : 'text-gray-400 border-transparent hover:text-gray-600' }}">{{ $l }}</a>@endforeach
                </div>
                <div class="space-y-2">
                    @forelse ($bookings as $b)
                        <a href="{{ route('services.bookings.show', $b['id']) }}" class="w-full flex items-center gap-3 p-3 rounded-2xl bg-white border border-gray-100 hover:border-purple-300 text-xs">
                            <span class="text-2xl">🛠️</span>
                            <div class="flex-1 min-w-0"><div class="font-bold text-gray-800 truncate">{{ $b['service']['name'] ?? 'Service' }}</div><div class="text-gray-400">{{ \Illuminate\Support\Carbon::parse($b['scheduled_for'] ?? $b['created_at'])->format('j M Y, h:i A') }} • @rupee($b['service_charge'])</div></div>
                            <span class="font-bold text-gray-400 hidden sm:inline">#{{ $b['booking_number'] }}</span>
                            <x-status-badge :status="$b['status']" />
                        </a>
                    @empty
                        <p class="text-center text-sm text-gray-400 py-6">No bookings here yet.</p>
                    @endforelse
                </div>
                <x-pagination :paginator="$bookings" class="pt-2" />
            </section>
        @endif

        <section id="courier" class="scroll-mt-40 bg-gradient-to-br from-blue-50 to-white rounded-3xl border border-blue-100 p-6 lg:p-10 grid lg:grid-cols-2 gap-10 items-start">
            <div class="space-y-3">
                <span class="bg-blue-100 text-blue-700 text-xs font-extrabold px-3 py-1 rounded-full">Courier Booking</span>
                <h2 class="text-2xl lg:text-3xl font-extrabold text-gray-900">Send parcels anywhere, pick-up from your door 📮</h2>
                <p class="text-sm text-gray-500">Documents, parcels and fragile items - see live rates from our courier partners and book a pickup in seconds.</p>
                @if ($courierBookings)
                    <div class="space-y-2 pt-3">
                        <h3 class="text-sm font-extrabold text-gray-900">My Courier Bookings</h3>
                        @foreach ($courierBookings as $c)
                            <a href="{{ route('services.courier.show', $c['id']) }}" class="flex items-center gap-3 p-3 rounded-2xl bg-white border border-gray-100 hover:border-blue-300 text-xs">
                                <span class="text-2xl">📮</span>
                                <div class="flex-1 min-w-0"><div class="font-bold text-gray-800 truncate">{{ $c['pickup_pincode'] }} → {{ $c['delivery_pincode'] }}@if ($c['courier_name']) • {{ $c['courier_name'] }}@endif</div><div class="text-gray-400">{{ \Illuminate\Support\Carbon::parse($c['created_at'])->format('j M, h:i A') }} • @rupee($c['total_amount'])@if ($c['awb_code']) • AWB {{ $c['awb_code'] }}@endif</div></div>
                                <x-status-badge :status="$c['status'] === 'cancelled' ? 'cancelled' : ($c['shipment_status'] ?: ($c['payment_status'] ?: $c['status']))" :label="$c['status'] === 'cancelled' ? 'Cancelled' : ($c['shipment_status_label'] ?: ($c['payment_status'] === 'paid' ? 'Paid' : 'Payment pending'))" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
            @include('courier.partials.form')
        </section>

        <section id="help" class="scroll-mt-40 space-y-6">
            <div class="text-center"><h2 class="text-2xl font-extrabold text-gray-900">Help Centre &amp; Customer Care</h2><p class="text-sm text-gray-500 mt-1">We're here 24x7 for you</p></div>
            <div class="grid md:grid-cols-3 gap-5 max-w-4xl mx-auto w-full">
                <div class="bg-white rounded-2xl border border-gray-100 p-5 text-center"><div class="text-3xl mb-2">📞</div><div class="font-bold text-sm">Call us</div><div class="text-xs text-gray-500 mt-1">{{ $phone }}</div></div>
                <div class="bg-white rounded-2xl border border-gray-100 p-5 text-center"><div class="text-3xl mb-2">✉️</div><div class="font-bold text-sm">Email</div><div class="text-xs text-gray-500 mt-1">{{ $email }}</div></div>
                <div class="bg-white rounded-2xl border border-gray-100 p-5 text-center"><div class="text-3xl mb-2">📍</div><div class="font-bold text-sm">Visit</div><div class="text-xs text-gray-500 mt-1">Ghatlodiya, Ahmedabad 380061</div></div>
            </div>
            <div class="max-w-3xl mx-auto w-full space-y-3">
                @foreach ($faqs as $f)
                    <details class="bg-white rounded-2xl border border-gray-100 p-4"><summary class="flex items-center justify-between font-bold text-sm text-gray-800">{{ $f['q'] }}<i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i></summary><p class="text-xs text-gray-500 mt-3 leading-relaxed">{{ $f['a'] }}</p></details>
                @endforeach
            </div>
        </section>
    </div>

@endsection

@push('scripts')

    <script src="{{ asset('assets/js/modules/courier.js') }}"></script>

@endpush
