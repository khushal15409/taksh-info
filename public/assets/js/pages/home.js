/* Home page */
ready(() => {
    initHero();
    renderCategories();
    renderDeals();
    initCountdown();
    initTrending();
    renderQuick();
    renderServices();
    renderTestimonials();
    initNewsletter();
    qsa("[data-play-badge]").forEach(el => { el.innerHTML = playBadge(); });
    refreshIcons();
});

function initHero() {
    const hero = qs("#hero");
    hero.innerHTML = HERO_SLIDES.map((s, i) => `
        <div class="hero-slide ${i === 0 ? "is-active" : ""} bg-gradient-to-r ${s.bg} text-white p-5 pb-12 sm:p-8 sm:pb-12 md:pl-16 lg:p-12 lg:pl-16 flex items-center">
            <div class="absolute -right-10 -bottom-10 w-96 h-96 bg-yellow-300/20 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute right-6 lg:right-16 top-1/2 -translate-y-1/2 text-[90px] sm:text-[130px] lg:text-[190px] opacity-15 sm:opacity-25 lg:opacity-40 select-none pointer-events-none">${s.emoji}</div>
            <div class="relative z-10 max-w-xl space-y-4">
                <span class="inline-flex bg-black/20 backdrop-blur-md px-3 py-1 rounded-full text-xs font-semibold text-yellow-300 border border-yellow-300/30">${s.badge}</span>
                <h1 class="text-2xl sm:text-3xl lg:text-5xl font-festive font-extrabold leading-tight">${s.title}</h1>
                <p class="text-orange-50 text-sm lg:text-base font-medium">${s.text}</p>
                <div class="pt-2 flex flex-wrap gap-3">
                    <a href="${url(s.cta.page, s.cta.params, s.cta.hash)}" class="bg-white text-brand-600 font-extrabold px-5 sm:px-6 py-2.5 sm:py-3 text-sm rounded-2xl shadow-lg hover:bg-orange-50 transition hover:-translate-y-0.5">${s.cta.label}</a>
                    <a href="${url(s.cta2.page, s.cta2.params, s.cta2.hash)}" class="bg-black/30 backdrop-blur-md border border-white/40 font-bold px-5 sm:px-6 py-2.5 sm:py-3 text-sm rounded-2xl hover:bg-black/40 transition">${s.cta2.label}</a>
                </div>
            </div>
        </div>`).join("") + `
        <button data-hero="prev" class="hidden md:flex absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white/20 hover:bg-white/40 text-white flex items-center justify-center z-20 backdrop-blur" aria-label="Previous"><i data-lucide="chevron-left" class="w-5 h-5"></i></button>
        <button data-hero="next" class="hidden md:flex absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white/20 hover:bg-white/40 text-white flex items-center justify-center z-20 backdrop-blur" aria-label="Next"><i data-lucide="chevron-right" class="w-5 h-5"></i></button>
        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2 z-20">${HERO_SLIDES.map((_, i) => `<button data-dot="${i}" class="hero-dot ${i === 0 ? "is-active" : ""}" aria-label="Slide ${i + 1}"></button>`).join("")}</div>`;

    const slides = qsa(".hero-slide", hero), dots = qsa(".hero-dot", hero);
    let cur = 0, timer;
    const go = (n) => {
        cur = (n + slides.length) % slides.length;
        slides.forEach((s, i) => s.classList.toggle("is-active", i === cur));
        dots.forEach((d, i) => d.classList.toggle("is-active", i === cur));
    };
    const start = () => { timer = setInterval(() => go(cur + 1), 5500); };
    hero.addEventListener("click", e => {
        const b = e.target.closest("[data-hero], [data-dot]");
        if (!b) return;
        go(b.dataset.dot !== undefined ? Number(b.dataset.dot) : cur + (b.dataset.hero === "next" ? 1 : -1));
    });
    hero.addEventListener("mouseenter", () => clearInterval(timer));
    hero.addEventListener("mouseleave", start);
    start();
}

function renderCategories() {
    const track = qs("#categories-grid");
    track.innerHTML = CATEGORIES.map(c => {
        const n = PRODUCTS.filter(p => p.cat === c.id).length;
        return `<a href="${url("listing", { cat: c.id })}" class="group snap-start flex-none w-[118px] sm:w-[136px] lg:w-[144px] bg-white p-4 rounded-2xl border border-gray-100 ${c.ring} text-center hover:shadow-md transition">
            <div class="w-16 h-16 mx-auto rounded-full ${c.tint} flex items-center justify-center text-3xl mb-2 group-hover:scale-110 transition">${c.emoji}</div>
            <div class="text-xs font-bold text-gray-800 leading-tight">${c.name}</div>
            <div class="text-[10px] text-gray-400 mt-0.5">${n} items</div></a>`;
    }).join("");

    const step = () => Math.max(160, track.clientWidth * 0.8);
    const atEnd = () => track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
    const next = () => track.scrollTo({ left: atEnd() ? 0 : track.scrollLeft + step(), behavior: "smooth" });
    qsa("[data-cat-nav]").forEach(b => b.addEventListener("click", () => {
        track.scrollBy({ left: b.dataset.catNav === "next" ? step() : -step(), behavior: "smooth" });
    }));

    // gentle auto-slide; pauses on hover/touch and for reduced-motion users
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
    let timer = setInterval(next, 3500);
    const pause = () => clearInterval(timer);
    const resume = () => { clearInterval(timer); timer = setInterval(next, 3500); };
    ["mouseenter", "touchstart", "focusin"].forEach(ev => track.addEventListener(ev, pause, { passive: true }));
    ["mouseleave", "touchend", "focusout"].forEach(ev => track.addEventListener(ev, resume, { passive: true }));
}

function renderDeals() {
    const deals = [...PRODUCTS].sort((a, b) => discountPct(b) - discountPct(a)).slice(0, 8);
    qs("#deals-rail").innerHTML = deals.map(productCard).join("");
}

function initCountdown() {
    const el = qs("#deal-timer");
    const tick = () => {
        const now = new Date(), end = new Date(now);
        end.setHours(23, 59, 59, 999);
        const s = Math.floor((end - now) / 1000);
        const pad = n => String(n).padStart(2, "0");
        el.innerHTML = [Math.floor(s / 3600), Math.floor(s % 3600 / 60), s % 60]
            .map(v => `<span class="bg-gray-900 text-yellow-400 font-extrabold text-sm rounded-lg px-2 py-1 tabular-nums">${pad(v)}</span>`).join('<span class="font-bold text-gray-400">:</span>');
    };
    tick();
    setInterval(tick, 1000);
}

function initTrending() {
    const filters = {
        bestseller: p => p.tags.includes("bestseller"),
        new: p => p.tags.includes("new"),
        festive: p => ["decor", "snacks"].includes(p.cat),
        electronics: p => p.cat === "electronics"
    };
    const grid = qs("#trending-grid");
    const show = (key) => {
        grid.innerHTML = productGrid(PRODUCTS.filter(filters[key]).slice(0, 8), "grid-cols-2 md:grid-cols-3 lg:grid-cols-4");
        qsa("[data-tab]").forEach(b => {
            const on = b.dataset.tab === key;
            b.className = `px-4 py-2 rounded-full text-xs font-bold transition ${on ? "bg-brand-500 text-white shadow" : "bg-white text-gray-600 border border-gray-200 hover:border-brand-500"}`;
        });
        refreshIcons();
    };
    qsa("[data-tab]").forEach(b => b.addEventListener("click", () => show(b.dataset.tab)));
    show("bestseller");
}

function renderQuick() {
    qs("#quick-rail").innerHTML = PRODUCTS.filter(p => p.quick).slice(0, 10).map(productCard).join("");
}

function renderServices() {
    qs("#services-grid").innerHTML = SERVICES.slice(0, 4).map(s => `
        <a href="${url("services", null, "#book-" + s.id)}" class="group bg-white p-6 rounded-3xl border border-gray-100 hover:border-purple-400 hover:shadow-lg transition text-center space-y-2">
            <div class="w-16 h-16 mx-auto rounded-2xl ${s.tint} flex items-center justify-center text-3xl group-hover:scale-110 transition">${s.emoji}</div>
            <h3 class="font-extrabold text-sm text-gray-800">${s.name}</h3>
            <p class="text-xs text-gray-400">${s.desc}</p>
            <p class="text-xs font-bold text-brand-600">From ${money(s.price)}</p></a>`).join("");
}

function renderTestimonials() {
    qs("#testimonials").innerHTML = TESTIMONIALS.map(t => `
        <figure class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-3">
            <div class="flex gap-0.5">${stars(t.rating, "w-4 h-4")}</div>
            <blockquote class="text-sm text-gray-600 leading-relaxed">“${t.text}”</blockquote>
            <figcaption class="flex items-center gap-3 pt-1">
                <div class="w-9 h-9 rounded-full bg-brand-500 text-white font-bold flex items-center justify-center text-sm">${t.name.charAt(0)}</div>
                <div><div class="text-xs font-bold text-gray-800">${t.name}</div><div class="text-[11px] text-gray-400">${t.city}</div></div>
            </figcaption></figure>`).join("");
}

function initNewsletter() {
    qs("#newsletter-form").addEventListener("submit", e => {
        e.preventDefault();
        const email = e.target.email.value.trim();
        if (!/^\S+@\S+\.\S+$/.test(email)) return toast("Enter a valid email address", "error");
        e.target.reset();
        toast("🎉 Subscribed! Festive offers are on the way.");
    });
}
