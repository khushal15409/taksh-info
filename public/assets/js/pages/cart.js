/* Cart page */
let view = "all";   // all | standard | quick

ready(() => {
    qs("#cart-tabs").addEventListener("click", e => { const b = e.target.closest("[data-view]"); if (b) { view = b.dataset.view; render(); } });
    qs("#cart-root").addEventListener("click", e => {
        const t = e.target.closest("[data-cart]");
        if (!t) return;
        const id = Number(t.dataset.id);
        if (t.dataset.cart === "save") { if (!Wishlist.has(id)) Wishlist.toggle(id); Cart.remove(id); toast("Moved to wishlist"); }
        if (t.dataset.cart === "coupon-remove") Cart.removeCoupon();
        if (t.dataset.cart === "coupon-pick") qs("#coupon-input").value = t.dataset.code;
    });
    qs("#cart-root").addEventListener("submit", e => {
        if (e.target.id !== "coupon-form") return;
        e.preventDefault();
        const r = Cart.applyCoupon(qs("#coupon-input").value);
        toast((r.ok ? "🎉 " : "❌ ") + r.msg, r.ok ? "success" : "error");
    });
    Store.subscribe(render);
    render();
});

function render() {
    const items = Cart.items();
    const root = qs("#cart-root");
    qs("#cart-tabs").classList.toggle("hidden", items.length === 0);
    if (!items.length) {
        root.innerHTML = emptyState({ emoji: "🛒", title: "Your Cart is Currently Empty", text: "Looks like you haven't added anything yet. Explore top festive deals!", ctaLabel: "Start Shopping", ctaHref: url("listing") }) +
            `<h2 class="text-xl font-extrabold text-gray-900 mb-4">Popular right now</h2>` + productGrid(PRODUCTS.filter(p => p.tags.includes("bestseller")).slice(0, 4));
        refreshIcons();
        return;
    }
    const std = items.filter(i => !i.product.quick), quick = items.filter(i => i.product.quick);
    qs("#cart-tabs").innerHTML = [["all", "All Items", items.length, "shopping-cart"], ["standard", "Standard Delivery", std.length, "truck"], ["quick", "Quick Delivery", quick.length, "zap"]]
        .map(([k, l, n, ic]) => `<button data-view="${k}" class="pb-3 border-b-2 flex items-center gap-2 ${view === k ? "border-brand-500 text-brand-600" : "border-transparent text-gray-400 hover:text-gray-600"}"><i data-lucide="${ic}" class="w-4 h-4"></i>${l} (${n})</button>`).join("");
    const shown = view === "quick" ? quick : view === "standard" ? std : items;
    const t = Cart.totals();
    const toFree = Math.max(0, SITE.freeDeliveryMin - t.subtotal);
    const coupon = COUPONS.find(c => c.code === Store.state.coupon);

    root.innerHTML = `
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-2xl border border-orange-100 p-4 space-y-2">
                <p class="text-xs font-bold ${toFree ? "text-gray-700" : "text-emerald-700"}">${toFree ? `Add items worth <span class="text-brand-600">${money(toFree)}</span> more for FREE delivery` : "🎉 You've unlocked FREE delivery!"}</p>
                <div class="h-2 bg-orange-100 rounded-full overflow-hidden"><div class="h-full bg-gradient-to-r from-brand-500 to-brand-gold transition-all" style="width:${Math.min(100, t.subtotal / SITE.freeDeliveryMin * 100)}%"></div></div>
            </div>
            ${shown.length ? shown.map(({ product: p, qty, line }) => `
                <div class="bg-white p-4 rounded-3xl border border-orange-100 flex items-center gap-4">
                    <a href="${url("product", { id: p.id })}">${productImg(p, "w-20 h-20 rounded-2xl object-contain bg-orange-50/50 p-1")}</a>
                    <div class="flex-1 min-w-0">
                        <a href="${url("product", { id: p.id })}" class="font-bold text-sm text-gray-800 hover:text-brand-600 line-clamp-2">${esc(p.name)}</a>
                        <p class="text-[11px] text-gray-400">${esc(p.unit)} • SKU: ${p.sku}</p>
                        <div class="flex items-center gap-3 mt-1.5 text-[11px] font-bold">
                            <button data-cart="save" data-id="${p.id}" class="text-gray-400 hover:text-brand-600">Save for later</button>
                            <button data-action="remove" data-id="${p.id}" class="text-gray-400 hover:text-red-500">Remove</button>
                            ${p.quick ? `<span class="text-emerald-600">⚡ 15 min</span>` : ""}
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <div class="font-extrabold text-brand-600">${money(line)}</div>
                        <div class="flex items-center gap-2 border rounded-xl px-1 py-0.5">
                            <button data-action="dec" data-id="${p.id}" class="w-7 h-7 text-brand-600 font-bold">−</button>
                            <span class="text-xs font-bold w-5 text-center">${qty}</span>
                            <button data-action="inc" data-id="${p.id}" class="w-7 h-7 text-brand-600 font-bold">+</button>
                        </div>
                    </div>
                </div>`).join("") : `<div class="bg-white rounded-3xl border border-dashed border-orange-200 p-10 text-center text-sm text-gray-400">No items in this delivery type.</div>`}
            <div class="bg-emerald-50/60 rounded-2xl p-4 border border-emerald-100 grid grid-cols-2 md:grid-cols-4 gap-4 text-center text-xs font-semibold text-emerald-900">
                ${[["shield-check", "100% Genuine"], ["truck", "Fast Delivery"], ["refresh-cw", "7 Day Returns"], ["lock", "Secure Payment"]].map(([i, l]) => `<div class="flex items-center justify-center gap-2"><i data-lucide="${i}" class="w-4 h-4 text-emerald-600"></i>${l}</div>`).join("")}
            </div>
        </div>

        <aside class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-5 h-fit lg:sticky lg:top-40">
            <h3 class="font-extrabold text-base text-gray-900 border-b pb-3 border-gray-100">Order Summary</h3>
            ${coupon && t.discount ? `<div class="flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-xl px-3 py-2 text-xs"><span class="font-bold text-emerald-700">🎟️ ${coupon.code} applied</span><button data-cart="coupon-remove" class="text-gray-400 hover:text-red-500 font-bold">Remove</button></div>` : `
            <form id="coupon-form" class="flex gap-2"><input id="coupon-input" placeholder="Have a coupon code?" class="field" autocomplete="off">
                <button class="bg-brand-500 hover:bg-brand-600 text-white text-xs font-bold px-4 rounded-xl">Apply</button></form>
            <div class="flex flex-wrap gap-2">${COUPONS.map(c => `<button data-cart="coupon-pick" data-code="${c.code}" title="${c.desc}" class="text-[10px] font-bold border border-dashed border-brand-500 text-brand-600 px-2 py-1 rounded-lg hover:bg-orange-50">${c.code}</button>`).join("")}</div>`}
            <div class="space-y-3 text-xs">
                <div class="flex justify-between text-gray-600"><span>Subtotal (${t.count} items)</span><span class="font-bold text-gray-900">${money2(t.subtotal)}</span></div>
                <div class="flex justify-between text-gray-600"><span>Delivery Charges</span><span class="font-bold ${t.delivery ? "text-gray-900" : "text-emerald-600 uppercase"}">${t.delivery ? money2(t.delivery) : "FREE"}</span></div>
                ${t.discount ? `<div class="flex justify-between text-emerald-600 font-bold"><span>Coupon Discount</span><span>−${money2(t.discount)}</span></div>` : ""}
                <div class="border-t pt-3 flex justify-between text-base font-extrabold text-gray-900"><span>Total Amount</span><span class="text-brand-600">${money2(t.total)}</span></div>
                ${t.savings > 0 ? `<p class="bg-emerald-50 text-emerald-700 font-bold text-center rounded-xl py-2">🎉 You're saving ${money(t.savings)} on this order</p>` : ""}
            </div>
            <a href="${url("checkout")}" class="w-full bg-gradient-to-r from-brand-500 to-brand-600 hover:brightness-110 text-white font-extrabold py-3.5 rounded-2xl shadow-md transition flex items-center justify-center gap-2"><i data-lucide="lock" class="w-4 h-4"></i>Proceed to Checkout</a>
        </aside>
    </div>`;
    refreshIcons();
    const rec = qs("#cart-recs");
    const ids = new Set(items.map(i => i.product.id));
    rec.innerHTML = `<h2 class="text-xl font-extrabold text-gray-900 mb-4">You might also like</h2><div class="rail">${PRODUCTS.filter(p => !ids.has(p.id)).slice(0, 8).map(productCard).join("")}</div>`;
    refreshIcons();
}
