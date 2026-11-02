/* Home services, courier booking, help centre */
ready(() => {
    renderServices();
    renderBookings();
    qs("#faq-list").innerHTML = FAQS.map(f => `
        <details class="bg-white rounded-2xl border border-gray-100 p-4 group">
            <summary class="flex items-center justify-between font-bold text-sm text-gray-800">${f.q}<i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 faq-chevron transition"></i></summary>
            <p class="text-xs text-gray-500 mt-3 leading-relaxed">${f.a}</p></details>`).join("");
    qs("#services-grid").addEventListener("click", e => { const b = e.target.closest("[data-book]"); if (b) openBooking(b.dataset.book); });
    initCourier();
    refreshIcons();
    const h = location.hash;
    if (h.startsWith("#book-")) openBooking(h.slice(6));
    else if (h) { const el = qs(h); if (el) setTimeout(() => el.scrollIntoView({ behavior: "smooth", block: "start" }), 100); }
});

function renderServices() {
    qs("#services-grid").innerHTML = SERVICES.map(s => `
        <div class="bg-white p-6 rounded-3xl border border-gray-100 hover:border-purple-400 hover:shadow-lg transition text-center space-y-3 flex flex-col">
            <div class="w-16 h-16 mx-auto rounded-2xl ${s.tint} flex items-center justify-center text-3xl">${s.emoji}</div>
            <h3 class="font-extrabold text-base text-gray-800">${s.name}</h3>
            <p class="text-xs text-gray-400 flex-1">${s.desc}</p>
            <div class="flex items-center justify-center gap-3 text-xs"><span class="font-bold text-brand-600">From ${money(s.price)}</span>
                <span class="flex items-center gap-1 text-amber-600 font-bold"><i data-lucide="star" class="w-3 h-3 fill-current"></i>${s.rating}</span></div>
            <button data-book="${s.id}" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs py-2.5 rounded-xl transition">Book Now</button></div>`).join("");
}

function openBooking(id) {
    const s = SERVICES.find(x => x.id === id);
    if (!s) return;
    const today = new Date().toISOString().slice(0, 10);
    const m = Modal.open({
        title: `Book ${s.name}`,
        body: `<form id="book-form" class="grid grid-cols-2 gap-4 text-xs">
            <p class="col-span-2 text-gray-500">${s.desc}. Starting at <b>${money(s.price)}</b>. A verified professional will contact you to confirm.</p>
            <div><label class="font-bold text-gray-500">Name</label><input name="name" class="field mt-1" value="${esc(Store.state.profile.name)}" required></div>
            <div><label class="font-bold text-gray-500">Phone</label><input name="phone" class="field mt-1" value="${esc(Store.state.profile.phone)}" required></div>
            <div><label class="font-bold text-gray-500">Date</label><input name="date" type="date" min="${today}" value="${today}" class="field mt-1" required></div>
            <div><label class="font-bold text-gray-500">Time slot</label><select name="slot" class="field mt-1"><option>9 AM - 12 PM</option><option>12 PM - 3 PM</option><option>3 PM - 6 PM</option><option>6 PM - 9 PM</option></select></div>
            <div class="col-span-2"><label class="font-bold text-gray-500">Address</label><input name="address" class="field mt-1" value="${esc((Store.state.addresses[0] || {}).line || "")}" required></div>
            <div class="col-span-2"><label class="font-bold text-gray-500">Notes (optional)</label><textarea name="notes" rows="2" class="field mt-1"></textarea></div>
            <button class="col-span-2 bg-purple-600 hover:bg-purple-700 text-white font-bold text-sm py-3 rounded-2xl">Confirm Booking</button></form>`
    });
    qs("#book-form", m).addEventListener("submit", e => {
        e.preventDefault();
        const f = Object.fromEntries(new FormData(e.target));
        Store.state.bookings.unshift({ id: "SRV" + Date.now().toString(36).toUpperCase().slice(-6), kind: "service", title: s.name, emoji: s.emoji, when: `${f.date}, ${f.slot}`, createdAt: Date.now() });
        Store.save(); Modal.close(); renderBookings();
        toast(`⚡ ${s.name} booked! Vendor will contact you.`);
    });
}

function initCourier() {
    const form = qs("#courier-form"), out = qs("#courier-est");
    const calc = () => {
        const f = Object.fromEntries(new FormData(form));
        const kg = Math.max(0.5, Number(f.weight) || 0.5);
        const price = 40 + Math.ceil(kg) * 25 + (f.speed === "express" ? 60 : 0) + (f.type === "fragile" ? 30 : 0);
        out.innerHTML = `${money(price)} <span class="text-xs font-semibold text-gray-400">• ${f.speed === "express" ? "Same day" : "1-3 days"}</span>`;
        return price;
    };
    form.addEventListener("input", calc);
    calc();
    form.addEventListener("submit", e => {
        e.preventDefault();
        const f = Object.fromEntries(new FormData(form));
        if (!/^\d{6}$/.test(f.from) || !/^\d{6}$/.test(f.to)) return toast("Enter valid 6-digit pickup and drop pincodes", "error");
        const price = calc();
        Store.state.bookings.unshift({ id: "CUR" + Date.now().toString(36).toUpperCase().slice(-6), kind: "courier", title: `Courier ${f.from} → ${f.to}`, emoji: "📮", when: `${money(price)} • ${f.speed === "express" ? "Same day" : "1-3 days"}`, createdAt: Date.now() });
        Store.save(); renderBookings();
        toast("📮 Courier pickup booked!");
    });
}

function renderBookings() {
    const list = Store.state.bookings;
    const box = qs("#bookings");
    box.classList.toggle("hidden", !list.length);
    qs("#bookings-list").innerHTML = list.slice(0, 5).map(b => `
        <div class="flex items-center gap-3 p-3 rounded-2xl bg-white border border-gray-100 text-xs"><span class="text-2xl">${b.emoji}</span>
            <div class="flex-1"><div class="font-bold text-gray-800">${esc(b.title)}</div><div class="text-gray-400">${esc(b.when)}</div></div>
            <span class="font-bold text-gray-400">#${b.id}</span><span class="bg-emerald-100 text-emerald-700 font-bold px-2 py-0.5 rounded-full">Confirmed</span></div>`).join("");
}
