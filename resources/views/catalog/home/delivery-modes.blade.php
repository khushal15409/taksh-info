        <section class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-6">
            <a href="{{ route('listing') }}" class="group bg-white p-4 md:p-6 rounded-3xl border border-orange-100 shadow-sm hover:shadow-festive transition relative overflow-hidden flex md:block items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-orange-100 text-brand-500 flex items-center justify-center mb-0 md:mb-4 shrink-0 group-hover:scale-110 transition"><i data-lucide="truck" class="w-8 h-8"></i></div>
                <span class="hidden md:block absolute top-4 right-4 bg-orange-100 text-brand-700 text-[10px] font-bold px-2.5 py-1 rounded-full">Standard</span>
                <div class="min-w-0">
                    <h3 class="font-extrabold text-base md:text-lg text-gray-900 group-hover:text-brand-500 transition">Standard E-Commerce</h3>
                    <p class="text-xs text-gray-500 mt-1">Wide range of 50,000+ national products delivered with trusted care.</p>
                </div>
            </a>
            <a href="{{ route('listing') }}?quick=1" class="group bg-white p-4 md:p-6 rounded-3xl border border-emerald-100 shadow-sm hover:shadow-festive transition relative overflow-hidden flex md:block items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center mb-0 md:mb-4 shrink-0 group-hover:scale-110 transition"><i data-lucide="zap" class="w-8 h-8"></i></div>
                <span class="hidden md:block absolute top-4 right-4 bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2.5 py-1 rounded-full">15-Mins</span>
                <div class="min-w-0">
                    <h3 class="font-extrabold text-base md:text-lg text-gray-900 group-hover:text-emerald-600 transition">Quick 15-Min Delivery</h3>
                    <p class="text-xs text-gray-500 mt-1">Fresh milk, vegetables, snacks &amp; daily essentials at your doorstep instantly.</p>
                </div>
            </a>
            <a href="{{ route('services') }}" class="group bg-white p-4 md:p-6 rounded-3xl border border-purple-100 shadow-sm hover:shadow-festive transition relative overflow-hidden flex md:block items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-purple-100 text-purple-600 flex items-center justify-center mb-0 md:mb-4 shrink-0 group-hover:scale-110 transition"><i data-lucide="wrench" class="w-8 h-8"></i></div>
                <span class="hidden md:block absolute top-4 right-4 bg-purple-100 text-purple-800 text-[10px] font-bold px-2.5 py-1 rounded-full">Verified Pros</span>
                <div class="min-w-0">
                    <h3 class="font-extrabold text-base md:text-lg text-gray-900 group-hover:text-purple-600 transition">Home &amp; Local Services</h3>
                    <p class="text-xs text-gray-500 mt-1">Electricians, plumbers, salon at home, cleaners &amp; more.</p>
                </div>
            </a>
        </section>
