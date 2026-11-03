{{-- Site footer: contact, a few shop categories (from the catalogue), quick links, partner links, help and legal. --}}
<footer class="bg-gray-900 text-white mt-16 border-t border-gray-800">
    <div class="max-w-7xl mx-auto px-4 lg:px-8 py-12 grid grid-cols-2 lg:grid-cols-5 gap-8 text-xs">
        <div class="col-span-2 space-y-4">
            <a href="{{ route('home') }}" class="inline-block bg-white rounded-2xl px-4 py-2.5 shadow-lg shadow-black/20" aria-label="TakshAllInOne - Home">
                <img src="{{ asset('assets/img/logo.png') }}" alt="Taksh - All in one Ecommerce, Logistics & Services" class="h-12 sm:h-14 w-auto">
            </a>
            <p class="text-gray-400 max-w-xs leading-relaxed">India's premier festive e-commerce platform offering groceries, instant delivery & verified local services.</p>
            <div class="space-y-1.5 text-gray-400">
                <p class="flex items-center gap-2"><i data-lucide="phone" class="w-3.5 h-3.5 text-brand-500"></i>{{ config('taksh.support_phone') }}</p>
                <p class="flex items-center gap-2"><i data-lucide="mail" class="w-3.5 h-3.5 text-brand-500"></i>{{ config('taksh.support_email') }}</p>
                <p class="flex items-center gap-2"><i data-lucide="map-pin" class="w-3.5 h-3.5 text-brand-500"></i>Ghatlodiya, Ahmedabad 380061</p>
            </div>
        </div>
        <div>
            <h4 class="font-bold mb-3 uppercase tracking-wider">Shop</h4>
            <ul class="space-y-2 text-gray-400">
                @forelse ($footerCategories as $c)<li><a href="{{ route('listing', ['category' => $c['id']]) }}" class="hover:text-brand-500">{{ $c['name'] }}</a></li>@empty<li><a href="{{ route('listing') }}" class="hover:text-brand-500">All products</a></li>@endforelse
            </ul>
        </div>
        <div>
            <h4 class="font-bold mb-3 uppercase tracking-wider">Quick Links</h4>
            <ul class="space-y-2 text-gray-400">
                <li><a href="{{ route('home') }}" class="hover:text-brand-500">Home</a></li>
                <li><a href="{{ route('services') }}" class="hover:text-brand-500">Local Services</a></li>
                <li><a href="{{ route('services') }}#courier" class="hover:text-brand-500">Courier Booking</a></li>
                <li><a href="{{ route('tracking') }}" class="hover:text-brand-500">Track Order</a></li>
                <li><a href="{{ route('wishlist') }}" class="hover:text-brand-500">Wishlist</a></li>
                <li><a href="{{ route('sales') }}" class="hover:text-brand-500">Sales &amp; Offers</a></li>
                <li><a href="{{ route('wallet') }}" class="hover:text-brand-500">Wallet</a></li>
                <li><a href="{{ route('profile') }}" class="hover:text-brand-500">My Account</a></li>
            </ul>
            <h4 class="font-bold mt-5 mb-3 uppercase tracking-wider">Partner With Us</h4>
            <ul class="space-y-2 text-gray-400">
                <li><a href="{{ route('delivery') }}" class="hover:text-brand-500">Join as Delivery Partner</a></li>
                <li><a href="{{ route('vendor') }}" class="hover:text-brand-500">Become a Vendor</a></li>
                <li><a href="{{ route('devrequest') }}" class="hover:text-brand-500">Development Request</a></li>
            </ul>
        </div>
        <div>
            <h4 class="font-bold mb-3 uppercase tracking-wider">Help &amp; Legal</h4>
            <ul class="space-y-2 text-gray-400">
                <li><a href="{{ route('help') }}" class="hover:text-brand-500">Help &amp; Support</a></li>
                <li><a href="{{ route('services') }}#help" class="hover:text-brand-500">Help Centre &amp; FAQs</a></li>
                <li><a href="{{ route('about') }}" class="hover:text-brand-500">About Us</a></li>
                <li><a href="{{ route('privacy') }}" class="hover:text-brand-500">Privacy Policy</a></li>
                <li><a href="{{ route('terms') }}" class="hover:text-brand-500">Terms &amp; Conditions</a></li>
                <li><a href="{{ route('refund') }}" class="hover:text-brand-500">Refund Policy</a></li>
            </ul>
            <h4 class="font-bold mt-5 mb-3 uppercase tracking-wider">Download App</h4>
            <a href="{{ route('home') }}#app" class="inline-block" aria-label="Taksh app coming soon on Google Play"><span data-play-badge></span></a>
            <p class="text-[11px] text-gray-500 mt-2">Android app launching soon - <a href="{{ route('home') }}#app" class="text-brand-500 hover:underline">notify me</a></p>
        </div>
    </div>
    <div class="border-t border-gray-800">
        <div class="max-w-7xl mx-auto px-4 lg:px-8 py-5 flex flex-col sm:flex-row items-center justify-between gap-3 text-[11px] text-gray-500">
            <p>© {{ date('Y') }} TakshAllInOne. All rights reserved.</p>
            <div class="flex items-center gap-2 font-bold text-gray-400">
                <span class="bg-gray-800 px-2.5 py-1 rounded">UPI</span><span class="bg-gray-800 px-2.5 py-1 rounded">VISA</span><span class="bg-gray-800 px-2.5 py-1 rounded">MasterCard</span><span class="bg-gray-800 px-2.5 py-1 rounded">COD</span>
            </div>
        </div>
    </div>
</footer>
