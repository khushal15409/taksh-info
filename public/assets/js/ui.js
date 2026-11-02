/* TakshAllInOne - shared UI: layout (header/footer), product card, modal, toast, helpers */

const ROOT = document.body.dataset.root || "";
const PAGE = document.body.dataset.page || "home";

const PAGES = {
    home: "",
    listing: "shop",
    product: "product",
    cart: "cart",
    checkout: "checkout",
    tracking: "tracking",
    profile: "profile",
    notifications: "notifications",
    services: "services",
    wishlist: "wishlist",
    privacy: "privacy-policy",
    terms: "terms"
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
const discountPct = (p) => Math.round((1 - p.price / p.mrp) * 100);
const esc = (s) => String(s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
const catById = (id) => CATEGORIES.find(c => c.id === id);
const imgUrl = (p) => ROOT + p.img;
function ready(fn) {
    if (document.readyState !== "loading") fn();
    else document.addEventListener("DOMContentLoaded", fn);
}
function refreshIcons() { if (window.lucide) lucide.createIcons(); }
function stars(r, size = "w-3.5 h-3.5") {
    return Array.from({ length: 5 }, (_, i) =>
        `<i data-lucide="star" class="${size} ${i < Math.round(r) ? "fill-amber-400 text-amber-400" : "text-gray-300"}"></i>`).join("");
}
function productImg(p, cls = "") {
    return `<img src="${imgUrl(p)}" alt="${esc(p.name)}" loading="lazy" class="${cls}">`;
}

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

// ---------- Modal ----------
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

// ---------- Product card ----------
function cardAction(id) {
    const q = Cart.qty(id);
    if (q === 0) {
        return `<button data-action="add" data-id="${id}" class="w-full bg-orange-50 hover:bg-brand-500 hover:text-white text-brand-600 border border-brand-500/40 font-bold text-xs py-2 rounded-xl transition flex items-center justify-center gap-1.5">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add</button>`;
    }
    return `<div class="flex items-center justify-between bg-brand-500 text-white rounded-xl font-bold text-xs">
                <button data-action="dec" data-id="${id}" class="w-9 h-8 hover:bg-brand-600 rounded-l-xl" aria-label="Decrease">−</button>
                <span>${q}</span>
                <button data-action="inc" data-id="${id}" class="w-9 h-8 hover:bg-brand-600 rounded-r-xl" aria-label="Increase">+</button>
            </div>`;
}

function wishBtn(id, cls = "") {
    const on = Wishlist.has(id);
    return `<button data-action="wish" data-id="${id}" data-wish-btn="${id}" aria-label="Wishlist"
        class="wish-btn ${on ? "is-on" : ""} w-8 h-8 rounded-full bg-white/90 shadow flex items-center justify-center text-gray-400 hover:text-red-500 transition ${cls}">
        <i data-lucide="heart" class="w-4 h-4"></i></button>`;
}

function productCard(p) {
    const off = discountPct(p);
    const badge = p.tags.includes("new")
        ? `<span class="bg-emerald-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">NEW</span>`
        : p.tags.includes("bestseller") ? `<span class="bg-gray-900 text-yellow-400 text-[10px] font-bold px-2 py-0.5 rounded-full">BESTSELLER</span>` : "";
    return `
    <article class="product-card group bg-white rounded-3xl border border-orange-100 hover:border-brand-500/40 hover:shadow-card-hover transition flex flex-col overflow-hidden">
        <div class="relative">
            <div class="absolute top-3 left-3 z-10 flex flex-col gap-1 items-start">
                ${off > 0 ? `<span class="bg-brand-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">${off}% OFF</span>` : ""}
                ${badge}
            </div>
            ${wishBtn(p.id, "absolute top-3 right-3 z-10")}
            <a href="${url("product", { id: p.id })}" class="block bg-gradient-to-b from-orange-50/60 to-white p-3">
                ${productImg(p, "w-full aspect-square object-contain group-hover:scale-105 transition duration-300")}
            </a>
        </div>
        <div class="p-4 pt-1 flex-1 flex flex-col gap-2">
            <div class="flex items-center gap-1.5 text-[10px] font-bold">
                ${p.quick ? `<span class="text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded flex items-center gap-0.5"><i data-lucide="zap" class="w-3 h-3"></i>15 min</span>` : ""}
                <span class="text-gray-400 uppercase tracking-wide truncate">${esc(p.brand)}</span>
            </div>
            <a href="${url("product", { id: p.id })}" class="font-bold text-[13px] text-gray-800 leading-snug line-clamp-2 hover:text-brand-600 min-h-[2.4rem]">${esc(p.name)}</a>
            <div class="flex items-center justify-between text-[11px] text-gray-500">
                <span>${esc(p.unit)}</span>
                <span class="flex items-center gap-1 bg-emerald-50 text-emerald-700 font-bold px-1.5 py-0.5 rounded"><i data-lucide="star" class="w-3 h-3 fill-current"></i>${p.rating}</span>
            </div>
            <div class="flex items-end gap-2 mt-auto pt-1">
                <span class="font-extrabold text-base text-gray-900">${money(p.price)}</span>
                ${off > 0 ? `<span class="text-xs text-gray-400 line-through pb-0.5">${money(p.mrp)}</span>` : ""}
            </div>
            <div data-card-action="${p.id}">${cardAction(p.id)}</div>
        </div>
    </article>`;
}

function productGrid(list, cls = "grid-cols-2 md:grid-cols-3 xl:grid-cols-4") {
    return `<div class="grid ${cls} gap-4 lg:gap-5">${list.map(productCard).join("")}</div>`;
}

function emptyState({ emoji, title, text, ctaLabel, ctaHref }) {
    return `<div class="text-center py-16 space-y-4">
        <div class="w-28 h-28 mx-auto bg-orange-100 rounded-full flex items-center justify-center text-5xl">${emoji}</div>
        <h2 class="text-2xl font-extrabold text-gray-900">${title}</h2>
        <p class="text-sm text-gray-500 max-w-sm mx-auto">${text}</p>
        ${ctaLabel ? `<a href="${ctaHref}" class="inline-block bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm px-6 py-3 rounded-2xl shadow">${ctaLabel}</a>` : ""}
    </div>`;
}

// ---------- Live updates (header counters, card buttons) ----------
function refreshCardActions() {
    qsa("[data-card-action]").forEach(el => { el.innerHTML = cardAction(Number(el.dataset.cardAction)); });
    qsa("[data-wish-btn]").forEach(el => el.classList.toggle("is-on", Wishlist.has(Number(el.dataset.wishBtn))));
    refreshIcons();
}

function unreadNotifCount() {
    const all = allNotifications();
    return all.filter(n => !Store.state.readNotifs.includes(n.id)).length;
}

function allNotifications() {
    const orderNotifs = Store.state.orders.map(o => ({
        id: "o-" + o.id, kind: "order", icon: "📦", title: `Order ${o.id} confirmed`,
        text: `Your order of ${o.items.length} item(s) worth ${money(o.totals.total)} has been placed successfully.`,
        time: new Date(o.createdAt).toLocaleString("en-IN", { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" }),
        href: url("tracking", { id: o.id })
    }));
    return [...orderNotifs, ...BASE_NOTIFICATIONS];
}

function updateHeader() {
    const t = Cart.totals();
    const set = (id, v) => { const el = qs("#" + id); if (el) el.textContent = v; };
    set("hdr-cart-count", t.count);
    set("hdr-cart-total", money(t.total));
    set("hdr-wish-count", Store.state.wishlist.length);
    set("hdr-location", `${Store.state.city} ${Store.state.pincode}`);
    const cc = qs("#hdr-cart-count"); if (cc) cc.classList.toggle("hidden", t.count === 0);
    const wc = qs("#hdr-wish-count"); if (wc) wc.classList.toggle("hidden", Store.state.wishlist.length === 0);
    const dot = qs("#hdr-notif-dot"); if (dot) dot.classList.toggle("hidden", unreadNotifCount() === 0);
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

// "Notify me" forms (any <form data-notify-form>) - saved locally until a backend is connected
document.addEventListener("submit", e => {
    const form = e.target.closest("[data-notify-form]");
    if (!form) return;
    e.preventDefault();
    const v = form.elements.contact.value.trim();
    if (!/^\S+@\S+\.\S+$/.test(v) && !/^\+?[\d\s-]{10,14}$/.test(v)) return toast("Enter a valid email or mobile number", "error");
    const list = Store.state.notify = Store.state.notify || [];
    if (!list.includes(v)) list.push(v);
    Store.save();
    form.reset();
    toast("🔔 You're on the list! We'll notify you when the app launches.");
});

// ---------- Layout ----------
function navTab(href, icon, label, active, tint = "bg-orange-100 text-brand-600") {
    return `<a href="${href}" ${active ? 'aria-current="page"' : ""} class="nav-tab group flex items-center justify-center gap-2 sm:gap-3 px-2 sm:px-4 py-2.5 rounded-2xl text-xs min-[400px]:text-sm leading-tight text-center transition-all duration-200 ${active ? "bg-gradient-to-r from-brand-500 to-brand-600 text-white font-extrabold shadow-md shadow-orange-300/50" : "bg-white border border-orange-100 text-gray-700 font-bold hover:border-brand-500/50 hover:text-brand-600 hover:-translate-y-0.5 hover:shadow-md"}">
        <span class="hidden min-[400px]:flex w-8 h-8 shrink-0 rounded-xl items-center justify-center transition ${active ? "bg-white/20 text-white" : tint + " group-hover:scale-110"}"><i data-lucide="${icon}" class="w-4 h-4"></i></span>
        <span>${label}</span></a>`;
}

function renderHeader() {
    const host = qs("#site-header");
    if (!host) return;
    const params = new URLSearchParams(location.search);
    const isListing = PAGE === "listing";
    const act = {
        home: PAGE === "home",
        quick: isListing && params.get("quick") === "1",
        services: PAGE === "services"
    };
    let bannerHidden = false;
    try { bannerHidden = sessionStorage.getItem("taksh_banner_closed") === "1"; } catch (e) { /* ignore */ }

    host.innerHTML = `
    <div id="coming-soon" class="${bannerHidden ? "hidden" : ""} coming-soon-banner text-center text-white text-xs sm:text-sm font-bold tracking-wide py-2.5 px-10 relative z-50">
        <a href="${url("home", null, "#app")}" class="hover:underline underline-offset-4 decoration-yellow-300">
            <span class="mr-1">📱</span><span class="uppercase tracking-widest">App Coming Soon</span>
            <span class="mx-2 text-yellow-300 hidden sm:inline">•</span>
            <span class="font-medium text-orange-100 hidden sm:inline">Taksh app is coming soon to Google Play - <u>Get notified</u></span>
        </a>
        <button data-close-banner class="absolute right-3 top-1/2 -translate-y-1/2 text-orange-200 hover:text-white w-6 h-6" aria-label="Dismiss">✕</button>
    </div>

    <div class="header-gradient text-white text-xs py-2 px-4 relative z-40">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
            <div class="flex items-center gap-2 min-w-0">
                <span class="text-yellow-300">✨</span>
                <span class="truncate"><b>Festive Sparkle Sale:</b> Up to 50% OFF + 15-Min Delivery | Code: <b class="underline decoration-yellow-300 decoration-2">TAKSHDIWALI</b></span>
            </div>
            <div class="hidden md:flex items-center gap-5 text-orange-100 font-medium shrink-0">
                <a href="${url("services", null, "#help")}" class="hover:text-white">24x7 Customer Care</a>
                <a href="${url("tracking")}" class="hover:text-white">Track Order</a>
                <span class="flex items-center gap-1"><i data-lucide="globe" class="w-3.5 h-3.5"></i>EN / ₹ INR</span>
            </div>
        </div>
    </div>

    <header class="bg-white sticky top-0 z-40 border-b border-orange-100 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 lg:px-8 py-3 flex flex-wrap lg:flex-nowrap items-center gap-x-4 gap-y-3">

            <a href="${url("home")}" class="flex items-center shrink-0" aria-label="TakshAllInOne - Home">
                <img src="${ROOT}assets/img/logo.png" alt="Taksh - All in one Ecommerce, Logistics & Services" class="h-9 min-[360px]:h-10 sm:h-14 w-auto">
            </a>

            <button data-open-location class="hidden xl:flex items-center gap-2 text-left bg-orange-50/60 hover:bg-orange-100/80 px-3 py-2 rounded-xl border border-orange-200/60 transition shrink-0">
                <div class="w-8 h-8 rounded-lg bg-white flex items-center justify-center text-brand-500 shadow-sm"><i data-lucide="map-pin" class="w-4 h-4"></i></div>
                <div>
                    <div class="text-[10px] text-gray-500 font-semibold uppercase tracking-wider flex items-center gap-1">Deliver to <i data-lucide="chevron-down" class="w-3 h-3"></i></div>
                    <div id="hdr-location" class="text-xs font-bold text-gray-800 truncate max-w-[130px]"></div>
                </div>
            </button>

            <form id="search-form" class="order-last lg:order-none w-full lg:w-auto lg:flex-1 relative" role="search" autocomplete="off">
                <div class="flex items-center bg-gray-50 focus-within:bg-white rounded-2xl border-2 border-orange-200/80 focus-within:border-brand-500 transition overflow-hidden">
                    <i data-lucide="search" class="w-5 h-5 text-gray-400 ml-4 mr-2 shrink-0"></i>
                    <input id="search-input" name="q" type="search" placeholder="Search ${PRODUCTS.length}+ items (e.g. Atta, Rice, Diya)..." class="w-full py-2.5 text-sm bg-transparent focus:outline-none text-gray-800 placeholder-gray-400 font-medium">
                    <button type="submit" class="bg-gradient-to-r from-brand-500 to-brand-600 text-white px-5 py-2.5 font-semibold text-sm hover:brightness-110 transition">Search</button>
                </div>
                <div id="search-popup" class="hidden absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl shadow-2xl border border-gray-100 z-50 p-2 max-h-96 overflow-y-auto"></div>
            </form>

            <div class="flex items-center gap-0.5 sm:gap-3 ml-auto lg:ml-0 shrink-0">
                <a href="${url("notifications")}" class="relative p-2 sm:p-2.5 rounded-xl hover:bg-orange-50 text-gray-700 hover:text-brand-600 transition" aria-label="Notifications">
                    <i data-lucide="bell" class="w-6 h-6"></i>
                    <span id="hdr-notif-dot" class="absolute top-2 right-2 w-2.5 h-2.5 bg-brand-500 rounded-full ring-2 ring-white"></span>
                </a>
                <a href="${url("wishlist")}" class="relative p-2 sm:p-2.5 rounded-xl hover:bg-orange-50 text-gray-700 hover:text-brand-600 transition" aria-label="Wishlist">
                    <i data-lucide="heart" class="w-6 h-6"></i>
                    <span id="hdr-wish-count" class="absolute -top-0.5 -right-0.5 bg-gray-900 text-white text-[10px] font-bold min-w-[20px] h-5 px-1 rounded-full flex items-center justify-center border-2 border-white">0</span>
                </a>
                <a href="${url("cart")}" class="flex items-center gap-3 bg-gradient-to-r from-brand-500 to-brand-600 text-white px-3 sm:px-4 py-2 sm:py-2.5 rounded-2xl mx-1 sm:mx-0 shadow-md hover:shadow-lg hover:brightness-105 transition active:scale-95">
                    <div class="relative">
                        <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                        <span id="hdr-cart-count" class="absolute -top-2 -right-2 bg-yellow-400 text-gray-900 text-[10px] font-extrabold min-w-[16px] h-4 px-1 rounded-full flex items-center justify-center shadow">0</span>
                    </div>
                    <div class="text-left leading-tight hidden sm:block">
                        <div class="text-[10px] uppercase font-bold text-orange-200">My Cart</div>
                        <div id="hdr-cart-total" class="text-xs font-extrabold">₹0</div>
                    </div>
                </a>
                <a href="${url("profile")}" class="flex items-center gap-2 sm:pl-3 sm:border-l border-gray-200 hover:opacity-80 transition" aria-label="My Account">
                    <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-orange-100 border-2 border-brand-500/40 text-brand-600 font-bold flex items-center justify-center">${esc(Store.state.profile.name.charAt(0).toUpperCase())}</div>
                    <div class="hidden lg:block text-left leading-tight">
                        <div class="text-xs font-bold text-gray-800">${esc(Store.state.profile.name)}</div>
                        <div class="text-[10px] text-gray-400 font-medium">My Account</div>
                    </div>
                </a>
            </div>
        </div>

        <nav id="main-nav" class="bg-orange-50/60 border-t border-orange-100/80" aria-label="Main">
            <div class="max-w-7xl mx-auto px-4 lg:px-8 py-3 grid grid-cols-3 gap-2 sm:gap-4">
                ${navTab(url("home"), "home", "Home", act.home)}
                ${navTab(url("listing", { quick: 1 }), "zap", "Quick Products", act.quick, "bg-emerald-100 text-emerald-600")}
                ${navTab(url("services"), "wrench", "Services", act.services, "bg-purple-100 text-purple-600")}
            </div>
        </nav>
    </header>`;
    refreshIcons();
}

function renderFooter() {
    const host = qs("#site-footer");
    if (!host) return;
    host.innerHTML = `
    <footer class="bg-gray-900 text-white mt-16 border-t border-gray-800">
        <div class="max-w-7xl mx-auto px-4 lg:px-8 py-12 grid grid-cols-2 lg:grid-cols-5 gap-8 text-xs">
            <div class="col-span-2 space-y-4">
                <a href="${url("home")}" class="inline-block bg-white rounded-2xl px-4 py-2.5 shadow-lg shadow-black/20" aria-label="TakshAllInOne - Home">
                    <img src="${ROOT}assets/img/logo.png" alt="Taksh - All in one Ecommerce, Logistics & Services" class="h-12 sm:h-14 w-auto">
                </a>
                <p class="text-gray-400 max-w-xs leading-relaxed">India's premier festive e-commerce platform offering groceries, instant delivery & verified local services.</p>
                <div class="space-y-1.5 text-gray-400">
                    <p class="flex items-center gap-2"><i data-lucide="phone" class="w-3.5 h-3.5 text-brand-500"></i>${SITE.supportPhone}</p>
                    <p class="flex items-center gap-2"><i data-lucide="mail" class="w-3.5 h-3.5 text-brand-500"></i>${SITE.supportEmail}</p>
                    <p class="flex items-center gap-2"><i data-lucide="map-pin" class="w-3.5 h-3.5 text-brand-500"></i>Ghatlodiya, Ahmedabad 380061</p>
                </div>
            </div>
            <div>
                <h4 class="font-bold mb-3 uppercase tracking-wider">Shop</h4>
                <ul class="space-y-2 text-gray-400">
                    ${CATEGORIES.slice(0, 6).map(c => `<li><a href="${url("listing", { cat: c.id })}" class="hover:text-brand-500">${c.name}</a></li>`).join("")}
                </ul>
            </div>
            <div>
                <h4 class="font-bold mb-3 uppercase tracking-wider">Quick Links</h4>
                <ul class="space-y-2 text-gray-400">
                    <li><a href="${url("home")}" class="hover:text-brand-500">Home</a></li>
                    <li><a href="${url("services")}" class="hover:text-brand-500">Local Services</a></li>
                    <li><a href="${url("services", null, "#courier")}" class="hover:text-brand-500">Courier Booking</a></li>
                    <li><a href="${url("tracking")}" class="hover:text-brand-500">Track Order</a></li>
                    <li><a href="${url("wishlist")}" class="hover:text-brand-500">Wishlist</a></li>
                    <li><a href="${url("profile")}" class="hover:text-brand-500">My Account</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold mb-3 uppercase tracking-wider">Help & Legal</h4>
                <ul class="space-y-2 text-gray-400">
                    <li><a href="${url("services", null, "#help")}" class="hover:text-brand-500">Help Centre & FAQs</a></li>
                    <li><a href="${url("privacy")}" class="hover:text-brand-500">Privacy Policy</a></li>
                    <li><a href="${url("terms")}" class="hover:text-brand-500">Terms & Conditions</a></li>
                </ul>
                <h4 class="font-bold mt-5 mb-3 uppercase tracking-wider">Download App</h4>
                <a href="${url("home", null, "#app")}" class="inline-block" aria-label="Taksh app coming soon on Google Play">${playBadge()}</a>
                <p class="text-[11px] text-gray-500 mt-2">Android app launching soon - <a href="${url("home", null, "#app")}" class="text-brand-500 hover:underline">notify me</a></p>
            </div>
        </div>
        <div class="border-t border-gray-800">
            <div class="max-w-7xl mx-auto px-4 lg:px-8 py-5 flex flex-col sm:flex-row items-center justify-between gap-3 text-[11px] text-gray-500">
                <p>© ${new Date().getFullYear()} TakshAllInOne. All rights reserved.</p>
                <div class="flex items-center gap-2 font-bold text-gray-400">
                    <span class="bg-gray-800 px-2.5 py-1 rounded">UPI</span><span class="bg-gray-800 px-2.5 py-1 rounded">VISA</span>
                    <span class="bg-gray-800 px-2.5 py-1 rounded">MasterCard</span><span class="bg-gray-800 px-2.5 py-1 rounded">COD</span>
                </div>
            </div>
        </div>
    </footer>`;
}

// ---------- Search suggestions ----------
function initSearch() {
    const form = qs("#search-form");
    if (!form) return;
    const input = qs("#search-input");
    const popup = qs("#search-popup");
    const initial = new URLSearchParams(location.search).get("q");
    if (initial && PAGE === "listing") input.value = initial;

    form.addEventListener("submit", e => {
        e.preventDefault();
        const q = input.value.trim();
        location.href = q ? url("listing", { q }) : url("listing");
    });

    input.addEventListener("input", () => {
        const q = input.value.trim().toLowerCase();
        if (!q) { popup.classList.add("hidden"); return; }
        const prods = PRODUCTS.filter(p => (p.name + " " + p.brand).toLowerCase().includes(q)).slice(0, 6);
        const cats = CATEGORIES.filter(c => c.name.toLowerCase().includes(q)).slice(0, 3);
        popup.innerHTML = (cats.length ? `<p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-3 pt-2 pb-1">Categories</p>` +
            cats.map(c => `<a href="${url("listing", { cat: c.id })}" class="flex items-center gap-3 p-2.5 hover:bg-orange-50 rounded-xl text-sm font-semibold text-gray-700"><span class="text-lg">${c.emoji}</span>${c.name}</a>`).join("") : "") +
            (prods.length ? `<p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-3 pt-2 pb-1">Products</p>` +
            prods.map(p => `<a href="${url("product", { id: p.id })}" class="flex items-center gap-3 p-2 hover:bg-orange-50 rounded-xl">
                ${productImg(p, "w-10 h-10 rounded-lg object-contain bg-orange-50/60")}
                <div class="min-w-0 flex-1"><div class="text-xs font-bold text-gray-800 truncate">${esc(p.name)}</div><div class="text-[11px] text-gray-400">${esc(p.unit)}</div></div>
                <div class="text-xs font-extrabold text-brand-600">${money(p.price)}</div></a>`).join("") : "") +
            (!cats.length && !prods.length ? `<div class="p-4 text-xs text-gray-400">No products found matching "${esc(input.value)}"</div>` : "") +
            `<a href="${url("listing", { q: input.value.trim() })}" class="block text-center text-xs font-bold text-brand-600 py-2.5 hover:underline">See all results for "${esc(input.value.trim())}"</a>`;
        popup.classList.remove("hidden");
    });
    document.addEventListener("click", e => { if (!form.contains(e.target)) popup.classList.add("hidden"); });
}

// ---------- Location + address modals ----------
function openLocationModal() {
    const m = Modal.open({
        title: "Choose delivery location",
        body: `<form id="loc-form" class="space-y-4">
            <p class="text-sm text-gray-500">Enter your pincode to see delivery options and availability.</p>
            <div><label class="text-xs font-bold text-gray-500">Pincode</label>
                <input name="pincode" maxlength="6" inputmode="numeric" value="${esc(Store.state.pincode)}" class="field mt-1" placeholder="e.g. 380061"></div>
            <div><label class="text-xs font-bold text-gray-500">City</label>
                <input name="city" value="${esc(Store.state.city)}" class="field mt-1"></div>
            <button class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold py-3 rounded-2xl">Update Location</button></form>`
    });
    qs("#loc-form", m).addEventListener("submit", e => {
        e.preventDefault();
        const f = new FormData(e.target);
        if (!/^\d{6}$/.test(f.get("pincode"))) return toast("Enter a valid 6-digit pincode", "error");
        Store.state.pincode = f.get("pincode");
        Store.state.city = f.get("city").trim() || Store.state.city;
        Store.save();
        Modal.close();
        toast("📍 Delivery location updated");
    });
}

// Add / edit address. Calls onSave(address) once saved.
function openAddressModal(existing, onSave) {
    const a = existing || { label: "Home", name: Store.state.profile.name, phone: Store.state.profile.phone, line: "", city: Store.state.city, pincode: Store.state.pincode };
    const m = Modal.open({
        title: existing ? "Edit address" : "Add new address",
        body: `<form id="addr-form" class="grid grid-cols-2 gap-4 text-xs">
            <div class="col-span-2"><label class="font-bold text-gray-500">Save as</label>
                <div class="flex gap-2 mt-1">${["Home", "Work", "Other"].map(l => `<label class="cursor-pointer"><input type="radio" name="label" value="${l}" class="peer sr-only" ${a.label === l ? "checked" : ""}><span class="block px-4 py-2 rounded-xl border border-gray-200 font-bold text-gray-600 peer-checked:bg-brand-500 peer-checked:text-white peer-checked:border-brand-500">${l}</span></label>`).join("")}</div></div>
            <div><label class="font-bold text-gray-500">Full name</label><input name="name" value="${esc(a.name)}" class="field mt-1" required></div>
            <div><label class="font-bold text-gray-500">Phone</label><input name="phone" value="${esc(a.phone)}" class="field mt-1" required></div>
            <div class="col-span-2"><label class="font-bold text-gray-500">Address</label><input name="line" value="${esc(a.line)}" class="field mt-1" placeholder="House no., building, area" required></div>
            <div><label class="font-bold text-gray-500">City</label><input name="city" value="${esc(a.city)}" class="field mt-1" required></div>
            <div><label class="font-bold text-gray-500">Pincode</label><input name="pincode" maxlength="6" value="${esc(a.pincode)}" class="field mt-1" required></div>
            <button class="col-span-2 bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm py-3 rounded-2xl">Save Address</button></form>`
    });
    qs("#addr-form", m).addEventListener("submit", e => {
        e.preventDefault();
        const f = Object.fromEntries(new FormData(e.target));
        if (!/^\d{6}$/.test(f.pincode)) return toast("Enter a valid 6-digit pincode", "error");
        if (!/^[+\d][\d\s-]{7,14}$/.test(f.phone.trim())) return toast("Enter a valid phone number", "error");
        const list = Store.state.addresses;
        let saved;
        if (existing) { saved = Object.assign(existing, f); }
        else { saved = Object.assign({ id: Store.uid("a"), isDefault: list.length === 0 }, f); list.push(saved); }
        Store.save();
        Modal.close();
        toast("✅ Address saved");
        if (onSave) onSave(saved);
    });
}

// ---------- Global event delegation ----------
document.addEventListener("click", e => {
    const t = e.target.closest("[data-action], [data-open-location], [data-close-banner]");
    if (!t) return;
    if (t.matches("[data-open-location]")) return openLocationModal();
    if (t.matches("[data-close-banner]")) {
        qs("#coming-soon").classList.add("hidden");
        try { sessionStorage.setItem("taksh_banner_closed", "1"); } catch (err) { /* ignore */ }
        return;
    }
    const id = Number(t.dataset.id);
    const p = Store.product(id);
    switch (t.dataset.action) {
        case "add": Cart.add(id); toast(`🛒 ${p.name} added to cart`); break;
        case "inc": Cart.setQty(id, Cart.qty(id) + 1); break;
        case "dec": Cart.setQty(id, Cart.qty(id) - 1); break;
        case "remove": Cart.remove(id); toast("Item removed from cart"); break;
        case "wish":
            e.preventDefault();
            toast(Wishlist.toggle(id) ? "❤️ Added to wishlist" : "Removed from wishlist");
            break;
    }
});

ready(() => {
    renderHeader();
    renderFooter();
    initSearch();
    updateHeader();
    Store.subscribe(() => { updateHeader(); refreshCardActions(); });
    refreshIcons();
});
