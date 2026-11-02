@extends('layouts.app')

@section('title', 'Home Services, Courier & Help | TakshAllInOne')
@section('page', 'services')

@section('content')

        <div class="space-y-14">
        <section class="text-center max-w-xl mx-auto space-y-2">
            <span class="bg-purple-100 text-purple-700 text-xs font-extrabold px-3 py-1 rounded-full">Taksh Home Care</span>
            <h1 class="text-3xl font-extrabold text-gray-900">Expert Services at Your Doorstep</h1>
            <p class="text-sm text-gray-500">Verified professionals arriving within 60 minutes</p>
        </section>
        <section id="services"><div id="services-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6"></div></section>

        <section id="bookings" class="hidden space-y-3 max-w-3xl mx-auto w-full">
            <h2 class="text-lg font-extrabold text-gray-900">My Recent Bookings</h2><div id="bookings-list" class="space-y-2"></div>
        </section>

        <section id="courier" class="scroll-mt-40 bg-gradient-to-br from-blue-50 to-white rounded-3xl border border-blue-100 p-6 lg:p-10 grid lg:grid-cols-2 gap-10 items-center">
            <div class="space-y-3">
                <span class="bg-blue-100 text-blue-700 text-xs font-extrabold px-3 py-1 rounded-full">Courier Booking</span>
                <h2 class="text-2xl lg:text-3xl font-extrabold text-gray-900">Send parcels anywhere, pick-up from your door 📮</h2>
                <p class="text-sm text-gray-500">Documents, parcels and fragile items - get an instant estimate and book a pickup in seconds.</p>
                <div class="bg-white rounded-2xl border border-blue-100 p-4 inline-block"><div class="text-[11px] font-bold text-gray-400 uppercase">Estimated price</div><div id="courier-est" class="text-3xl font-extrabold text-brand-600"></div></div>
            </div>
            <form id="courier-form" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 grid grid-cols-2 gap-4 text-xs">
                <div><label class="font-bold text-gray-500">Pickup pincode</label><input name="from" maxlength="6" inputmode="numeric" class="field mt-1" value="380061" required></div>
                <div><label class="font-bold text-gray-500">Drop pincode</label><input name="to" maxlength="6" inputmode="numeric" class="field mt-1" placeholder="e.g. 400001" required></div>
                <div><label class="font-bold text-gray-500">Weight (kg)</label><input name="weight" type="number" min="0.5" step="0.5" value="1" class="field mt-1"></div>
                <div><label class="font-bold text-gray-500">Type</label><select name="type" class="field mt-1"><option value="document">Document</option><option value="parcel">Parcel</option><option value="fragile">Fragile</option></select></div>
                <div class="col-span-2"><label class="font-bold text-gray-500">Speed</label><select name="speed" class="field mt-1"><option value="standard">Standard (1-3 days)</option><option value="express">Express (same day)</option></select></div>
                <button class="col-span-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm py-3 rounded-2xl">Book Pickup</button>
            </form>
        </section>

        <section id="help" class="scroll-mt-40 space-y-6">
            <div class="text-center"><h2 class="text-2xl font-extrabold text-gray-900">Help Centre &amp; Customer Care</h2><p class="text-sm text-gray-500 mt-1">We're here 24x7 for you</p></div>
            <div class="grid md:grid-cols-3 gap-5 max-w-4xl mx-auto w-full">
                <div class="bg-white rounded-2xl border border-gray-100 p-5 text-center"><div class="text-3xl mb-2">📞</div><div class="font-bold text-sm">Call us</div><div class="text-xs text-gray-500 mt-1">+91 99999 12345</div></div>
                <div class="bg-white rounded-2xl border border-gray-100 p-5 text-center"><div class="text-3xl mb-2">✉️</div><div class="font-bold text-sm">Email</div><div class="text-xs text-gray-500 mt-1">care@takshallinone.com</div></div>
                <div class="bg-white rounded-2xl border border-gray-100 p-5 text-center"><div class="text-3xl mb-2">📍</div><div class="font-bold text-sm">Visit</div><div class="text-xs text-gray-500 mt-1">Ghatlodiya, Ahmedabad 380061</div></div>
            </div>
            <div id="faq-list" class="max-w-3xl mx-auto w-full space-y-3"></div>
        </section>
        </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/pages/services.js') }}"></script>
@endpush
