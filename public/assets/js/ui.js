/* TakshAllInOne - shared browser code: helpers, toast, modal, the header's live numbers, product search suggestions, the delivery-location
   and language/theme dialogs. (The pages, header and footer themselves are rendered by the server.) */

const ROOT = document.body.dataset.root || "";
const PAGE = document.body.dataset.page || "home";

// Page paths (what url("name") builds); they mirror routes/web.php
const PAGES = {
    home: "", listing: "shop", product: "product", cart: "cart", checkout: "checkout", tracking: "tracking", profile: "profile", notifications: "notifications",
    services: "services", wishlist: "wishlist", privacy: "privacy-policy", terms: "terms", login: "login", wallet: "wallet", help: "help", about: "about",
    refund: "refund-policy", delivery: "join/delivery-partner", vendor: "join/vendor", devrequest: "development-request", returns: "return",
    payresult: "payment/result", sales: "sales", orderplaced: "order/placed", orderpay: "order/pay"
};

// ---------- Helpers ----------
function url(page, params, hash) {
    let u = ROOT + PAGES[page];
    const q = params ? new URLSearchParams(params).toString() : "";
    if (q) u += "?" + q;
    return u + (hash || "");
}
const qs = (sel, root = document) => root.querySelector(sel);
const qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
const money = (n) => "₹" + Math.round(n).toLocaleString("en-IN");
const money2 = (n) => "₹" + Number(n).toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const esc = (s) => String(s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
function ready(fn) {
    if (document.readyState !== "loading") fn();
    else document.addEventListener("DOMContentLoaded", fn);
}
function refreshIcons() { if (window.lucide) lucide.createIcons(); }
const lang = () => (document.documentElement.lang === "hi" ? "hi" : "en");
function applyTheme() { document.documentElement.classList.toggle("dark", Prefs.state.theme === "dark"); }

// ---------- Toast ----------
function toast(message, type = "success") {
    let box = qs("#toast-container");
    if (!box) {
        box = document.createElement("div");
        box.id = "toast-container";
        box.className = "fixed bottom-6 right-6 z-[90] flex flex-col space-y-2 pointer-events-none";
        document.body.appendChild(box);
    }
    const el = document.createElement("div");
    const tone = type === "error" ? "border-red-400/40 text-red-200" : "border-yellow-500/30 text-yellow-300";
    el.className = `toast-in bg-gray-900 ${tone} text-xs font-bold px-4 py-3 rounded-2xl shadow-2xl border pointer-events-auto max-w-xs`;
    el.textContent = message;
    box.appendChild(el);
    setTimeout(() => { el.classList.add("opacity-0", "transition"); setTimeout(() => el.remove(), 300); }, 2800);
}

// ---------- Modal (built in the browser: the address, location and preferences dialogs; server pages use <x-modal>) ----------
const Modal = {
    open({ title, body, wide = false }) {
        this.close();
        const root = document.createElement("div");
        root.id = "modal-root";
        root.className = "fixed inset-0 z-[80] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 fade-in";
        root.innerHTML = `
            <div class="bg-white rounded-3xl ${wide ? "max-w-3xl" : "max-w-lg"} w-full max-h-[90vh] overflow-y-auto shadow-2xl" role="dialog" aria-modal="true">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white rounded-t-3xl z-10">
                    <h3 class="font-extrabold text-lg text-gray-900">${esc(title)}</h3>
                    <button data-modal-close class="w-8 h-8 rounded-full hover:bg-gray-100 text-gray-500 flex items-center justify-center" aria-label="Close">✕</button>
                </div>
                <div class="p-6">${body}</div>
            </div>`;
        root.addEventListener("click", e => { if (e.target === root || e.target.closest("[data-modal-close]")) Modal.close(); });
        document.body.appendChild(root);
        document.body.classList.add("overflow-hidden");
        refreshIcons();
        return root;
    },
    close() {
        const root = qs("#modal-root");
        if (root) root.remove();
        document.body.classList.remove("overflow-hidden");
    }
};
document.addEventListener("keydown", e => { if (e.key === "Escape") Modal.close(); });

function emptyState({ emoji, title, text, ctaLabel, ctaHref }) {
    return `<div class="text-center py-16 space-y-4">
        <div class="w-28 h-28 mx-auto bg-orange-100 rounded-full flex items-center justify-center text-5xl">${emoji}</div>
        <h2 class="text-2xl font-extrabold text-gray-900">${title}</h2>
        <p class="text-sm text-gray-500 max-w-sm mx-auto">${text}</p>
        ${ctaLabel ? `<a href="${ctaHref}" class="inline-block bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm px-6 py-3 rounded-2xl shadow">${ctaLabel}</a>` : ""}
    </div>`;
}

// ---------- The header's live numbers ----------
function updateHeader() {
    const t = Cart.totals();
    const set = (id, v) => { const el = qs("#" + id); if (el) el.textContent = v; };
    set("hdr-cart-count", t.count);
    set("hdr-cart-total", money(t.total));
    set("hdr-wish-count", Wishlist.count());
    set("hdr-location", [Prefs.state.city, Prefs.state.pincode].filter(Boolean).join(" ") || "Set location");
    const cc = qs("#hdr-cart-count"); if (cc) cc.classList.toggle("hidden", t.count === 0);
    const wc = qs("#hdr-wish-count"); if (wc) wc.classList.toggle("hidden", Wishlist.count() === 0);
    const dot = qs("#hdr-notif-dot"); if (dot) dot.classList.toggle("hidden", NotifBadge.count() === 0);
}

// ---------- App "coming soon" ----------
// Placeholder badge (not the official Google artwork) - swap for the official badge + Play Store link at launch.
function playBadge(cls = "") {
    return `<span class="relative inline-flex items-center gap-3 bg-black text-white rounded-xl pl-3 pr-5 py-2 border border-gray-600 ${cls}">
        <svg viewBox="0 0 24 24" class="w-7 h-7 shrink-0" aria-hidden="true"><path fill="#00D7FE" d="M3.6 2.3 13.3 12 3.6 21.7a1.5 1.5 0 0 1-.6-1.2V3.5c0-.5.2-.9.6-1.2z"/><path fill="#00F076" d="m16.5 8.8-3.2 3.2L3.6 2.3a1.5 1.5 0 0 1 1.6.1z"/><path fill="#FF3A44" d="m16.5 15.2-11.3 6.4a1.5 1.5 0 0 1-1.6.1l9.7-9.7z"/><path fill="#FFD500" d="m20.3 10.8-3.8-2 -3.2 3.2 3.2 3.2 3.8-2.1c1-.6 1-1.7 0-2.3z"/></svg>
        <span class="text-left leading-tight"><span class="block text-[9px] uppercase tracking-wider text-gray-300">Get it on</span><span class="block text-base font-bold">Google Play</span></span>
        <span class="absolute -top-2 -right-2 bg-brand-500 text-white text-[9px] font-extrabold uppercase tracking-wider px-2 py-0.5 rounded-full shadow">Soon</span>
    </span>`;
}

// "Notify me" forms (any <form data-notify-form>) - kept in this browser until a launch list exists
document.addEventListener("submit", e => {
    const form = e.target.closest("[data-notify-form]");
    if (!form) return;
    e.preventDefault();
    const v = form.elements.contact.value.trim();
    if (!/^\S+@\S+\.\S+$/.test(v) && !/^\+?[\d\s-]{10,14}$/.test(v)) return toast("Enter a valid email or mobile number", "error");
    if (!Prefs.state.notify.includes(v)) Prefs.state.notify.push(v);
    Prefs.save();
    form.reset();
    toast("🔔 You're on the list! We'll notify you when the app launches.");
});

// ---------- Search suggestions ----------
function initSearch() {
    const form = qs("#search-form");
    if (!form) return;
    const input = qs("#search-input");
    const popup = qs("#search-popup");
    const initial = new URLSearchParams(location.search).get("q");

    form.addEventListener("submit", e => {
        e.preventDefault();
        const q = input.value.trim();
        if (q) { Prefs.state.searches = [q, ...(Prefs.state.searches || []).filter(x => x.toLowerCase() !== q.toLowerCase())].slice(0, 6); Prefs.save(); }
        location.href = q ? url("listing", { q }) : url("listing");
    });

    // Live catalogue: suggestions come from GET /products/search, recent searches from the account when logged in
    let suggestTimer = null, suggestSeq = 0;
    const suggest = (raw) => {
        clearTimeout(suggestTimer);
        suggestTimer = setTimeout(async () => {
            const seq = ++suggestSeq;
            let rows = [];
            try { rows = await Api.search(raw, 6); } catch (e) { /* keep the popup quiet */ }
            if (seq !== suggestSeq || input.value.trim() !== raw) return;     // a newer keystroke won
            popup.innerHTML = (rows.length ? `<p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-3 pt-2 pb-1">Products</p>` +
                rows.map(r => `<a href="${url("product", { id: r.id })}" class="flex items-center gap-3 p-2 hover:bg-orange-50 rounded-xl">
                    ${r.image ? `<img src="${esc(r.image)}" alt="" class="w-10 h-10 rounded-lg object-contain bg-orange-50/60">` : `<span class="w-10 h-10 rounded-lg bg-orange-50/60 flex items-center justify-center">🛍️</span>`}
                    <div class="min-w-0 flex-1"><div class="text-xs font-bold text-gray-800 truncate">${esc(r.name)}</div><div class="text-[11px] text-gray-400">${r.brand ? esc(r.brand) : ""}${r.in_stock ? "" : " · Out of stock"}</div></div>
                    <div class="text-xs font-extrabold text-brand-600">${money(r.price)}</div></a>`).join("")
                : `<div class="p-4 text-xs text-gray-400">No products found matching "${esc(raw)}"</div>`) +
                `<a href="${url("listing", { q: raw })}" class="block text-center text-xs font-bold text-brand-600 py-2.5 hover:underline">See all results for "${esc(raw)}"</a>`;
            popup.classList.remove("hidden");
        }, 250);
    };

    // Recent searches (shown when the empty box is focused)
    const showRecent = async () => {
        let list = Prefs.state.searches || [];
        if (Auth.loggedIn) { try { list = await Api.recentSearches(); } catch (e) { /* fall back to this browser's list */ } }
        if (input.value.trim() || !list.length) return;
        popup.innerHTML = `<div class="flex items-center justify-between px-3 pt-2 pb-1"><p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Recent searches</p><button type="button" data-clear-searches class="text-[10px] font-bold text-brand-600 hover:underline">Clear</button></div>` +
            list.map(q => `<a href="${url("listing", { q })}" class="flex items-center gap-3 p-2.5 hover:bg-orange-50 rounded-xl text-sm font-semibold text-gray-700"><i data-lucide="history" class="w-4 h-4 text-gray-400"></i>${esc(q)}</a>`).join("");
        popup.classList.remove("hidden"); refreshIcons();
    };
    input.addEventListener("focus", showRecent);
    popup.addEventListener("click", e => { if (e.target.closest("[data-clear-searches]")) { Prefs.state.searches = []; Prefs.save(); popup.classList.add("hidden"); } });

    input.addEventListener("input", () => {
        const q = input.value.trim().toLowerCase();
        if (!q) { popup.classList.add("hidden"); showRecent(); return; }
        suggest(input.value.trim());
    });
    document.addEventListener("click", e => { if (!form.contains(e.target)) popup.classList.add("hidden"); });
}

// ---------- Location + address modals ----------
function openLocationModal() {
    if (Auth.loggedIn) { location.href = url("profile", { tab: "addresses" }); return; }   // saved addresses are managed in the account
    const m = Modal.open({
        title: "Choose delivery location",
        body: `<form id="loc-form" class="space-y-4">
            <p class="text-sm text-gray-500">Enter your pincode to see delivery options and availability.</p>
            <div><label class="text-xs font-bold text-gray-500">Pincode</label>
                <input name="pincode" maxlength="6" inputmode="numeric" value="${esc(Prefs.state.pincode)}" class="field mt-1" placeholder="e.g. 380061"></div>
            <div><label class="text-xs font-bold text-gray-500">City</label>
                <input name="city" value="${esc(Prefs.state.city)}" class="field mt-1"></div>
            <button type="button" id="loc-detect" class="w-full border-2 border-brand-500 text-brand-600 hover:bg-orange-50 font-bold py-2.5 rounded-2xl flex items-center justify-center gap-2"><i data-lucide="crosshair" class="w-4 h-4"></i>Use my current location</button>
            <button class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold py-3 rounded-2xl">Update Location</button></form>`
    });
    qs("#loc-detect", m).addEventListener("click", ev => detectLocation(m, ev.currentTarget));
    qs("#loc-form", m).addEventListener("submit", e => {
        e.preventDefault();
        const f = new FormData(e.target);
        if (!/^\d{6}$/.test(f.get("pincode"))) return toast("Enter a valid 6-digit pincode", "error");
        Prefs.state.pincode = f.get("pincode");
        Prefs.state.city = f.get("city").trim() || Prefs.state.city;
        Prefs.save();
        Modal.close();
        toast("📍 Delivery location updated");
    });
}

// Browser geolocation + OpenStreetMap reverse lookup to prefill pincode / city (asks permission; manual entry always works)
function detectLocation(modal, btn) {
    if (!navigator.geolocation) return toast("Location is not supported on this browser", "error");
    const label = btn.innerHTML;
    btn.disabled = true; btn.textContent = "Detecting…";
    const reset = () => { btn.disabled = false; btn.innerHTML = label; refreshIcons(); };
    navigator.geolocation.getCurrentPosition(async pos => {
        try {
            const { latitude: la, longitude: lo } = pos.coords;
            // asked through our own /location endpoint (identified, cached) rather than from the browser
            const a = ((await Api.reverseGeocode(la, lo)).place || {}).address || {};
            const form = qs("#loc-form", modal);
            if (a.pincode) form.elements.pincode.value = a.pincode.slice(0, 6);
            if (a.city) form.elements.city.value = a.city;
            toast("📍 Location detected - review and update");
        } catch (e) { toast("Couldn't look up your address. Please enter your pincode.", "error"); }
        reset();
    }, err => { toast(err.code === 1 ? "Location permission denied. Enter your pincode instead." : "Couldn't detect location", "error"); reset(); }, { timeout: 10000 });
}

// Language + theme preferences
function openPrefsModal() {
    const m = Modal.open({
        title: "Preferences",
        body: `<form id="prefs-form" class="space-y-5 text-xs">
            <div><p class="font-bold text-gray-500 mb-2">Language</p><div class="grid grid-cols-2 gap-3">${[["en", "English"], ["hi", "हिन्दी (Hindi)"]].map(([k, l]) => `<label class="flex items-center gap-2 p-3 rounded-xl border-2 border-gray-200 cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:bg-orange-50/40"><input type="radio" name="lang" value="${k}" class="accent-brand-500" ${lang() === k ? "checked" : ""}><span class="font-bold text-gray-700">${l}</span></label>`).join("")}</div>
                <p class="text-[11px] text-gray-400 mt-2">The whole page is shown in the language you choose.</p></div>
            <div><p class="font-bold text-gray-500 mb-2">Theme</p><div class="grid grid-cols-2 gap-3">${[["light", "☀️ Light"], ["dark", "🌙 Dark"]].map(([k, l]) => `<label class="flex items-center gap-2 p-3 rounded-xl border-2 border-gray-200 cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:bg-orange-50/40"><input type="radio" name="theme" value="${k}" class="accent-brand-500" ${Prefs.state.theme === k ? "checked" : ""}><span class="font-bold text-gray-700">${l}</span></label>`).join("")}</div></div>
            <button class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold py-3 rounded-2xl">Save preferences</button>
            <p class="text-center text-[11px] text-gray-400">TakshAllInOne Web • version ${esc(document.body.dataset.version || "1.0.0")}</p></form>`
    });
    qs("#prefs-form", m).addEventListener("submit", e => {
        e.preventDefault();
        const f = new FormData(e.target);
        const langChanged = lang() !== f.get("lang");
        Prefs.state.lang = f.get("lang"); Prefs.state.theme = f.get("theme");
        document.cookie = `taksh_lang=${f.get("lang")}; path=/; max-age=31536000; SameSite=Lax`;   // the server renders the page in this language
        Prefs.save(); applyTheme(); Modal.close();
        if (langChanged) { location.reload(); return; }
        toast("✅ Preferences saved");
    });
}

// ---------- Global event delegation ----------
document.addEventListener("click", e => {
    const t = e.target.closest("[data-open-location], [data-close-banner], [data-open-prefs]");
    if (!t) return;
    if (t.matches("[data-open-location]")) return openLocationModal();
    if (t.matches("[data-open-prefs]")) return openPrefsModal();
    if (t.matches("[data-close-banner]")) {
        qs("#coming-soon").classList.add("hidden");
        try { sessionStorage.setItem("taksh_banner_closed", "1"); } catch (err) { /* ignore */ }
    }
});

ready(() => {
    try { if (sessionStorage.getItem("taksh_banner_closed") === "1") qs("#coming-soon").classList.add("hidden"); } catch (e) { /* ignore */ }
    initSearch();
    updateHeader();
    Wishlist.paint();
    Addresses.refresh();      // the default address drives the "Deliver to" chip
    NotifBadge.refresh();
    Prefs.subscribe(updateHeader);
    if (typeof startHindiPage === "function") startHindiPage();
    qsa("[data-play-badge]").forEach(el => { el.innerHTML = playBadge(); });
    refreshIcons();
});
