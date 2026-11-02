/* Product details */
let qty = 1;

ready(() => {
    const id = Number(new URLSearchParams(location.search).get("id")) || 1;
    const p = Store.product(id);
    const root = qs("#product-root");
    if (!p) {
        root.innerHTML = emptyState({ emoji: "😕", title: "Product not found", text: "This product may have been removed.", ctaLabel: "Browse products", ctaHref: url("listing") });
        return;
    }
    document.title = `${p.name} | TakshAllInOne`;
    Store.trackRecent(p.id);
    render(p);
});

function render(p) {
    const cat = catById(p.cat), off = discountPct(p);
    qs("#crumbs").innerHTML = `
        <a href="${url("home")}" class="hover:text-brand-500">Home</a><span>/</span>
        <a href="${url("listing", { cat: p.cat })}" class="hover:text-brand-500">${cat.name}</a><span>/</span>
        <span class="text-gray-800 font-bold truncate">${esc(p.name)}</span>`;

    qs("#product-root").innerHTML = `
    <div class="bg-white rounded-3xl p-6 lg:p-10 border border-orange-100 shadow-sm grid grid-cols-1 lg:grid-cols-2 gap-10">
        <div class="space-y-4">
            <div class="relative bg-gradient-to-b from-orange-50/70 to-white rounded-2xl p-6 border border-orange-100 flex items-center justify-center overflow-hidden group">
                ${productImg(p, "w-full max-w-md aspect-square object-contain group-hover:scale-110 transition duration-500")}
                <span class="absolute top-4 left-4 bg-emerald-600 text-white text-xs font-bold px-3 py-1 rounded-full shadow">IN STOCK</span>
                ${off > 0 ? `<span class="absolute top-4 right-4 bg-brand-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow">${off}% OFF</span>` : ""}
            </div>
            <div class="grid grid-cols-3 gap-3 text-center text-[11px] font-semibold text-gray-600">
                <div class="bg-emerald-50 rounded-xl p-3"><i data-lucide="shield-check" class="w-5 h-5 mx-auto text-emerald-600 mb-1"></i>100% Genuine</div>
                <div class="bg-orange-50 rounded-xl p-3"><i data-lucide="refresh-cw" class="w-5 h-5 mx-auto text-brand-500 mb-1"></i>7 Day Returns</div>
                <div class="bg-sky-50 rounded-xl p-3"><i data-lucide="truck" class="w-5 h-5 mx-auto text-sky-600 mb-1"></i>Fast Delivery</div>
            </div>
        </div>

        <div class="space-y-5">
            <div>
                <div class="flex items-center justify-between">
                    <a href="${url("listing", { brand: p.brand })}" class="text-xs font-bold text-brand-600 uppercase tracking-widest hover:underline">Brand: ${esc(p.brand)}</a>
                    ${wishBtn(p.id, "border border-gray-200")}
                </div>
                <h1 class="text-2xl lg:text-3xl font-extrabold text-gray-900 mt-1">${esc(p.name)}</h1>
                <p class="text-xs text-gray-400 mt-1">SKU: ${p.sku} | ${esc(p.unit)}</p>
            </div>

            <div class="flex flex-wrap items-center gap-4">
                <div class="text-3xl font-extrabold text-brand-600">${money2(p.price)}</div>
                ${off > 0 ? `<div class="text-sm text-gray-400 line-through">${money2(p.mrp)}</div>
                <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2.5 py-1 rounded-lg">You save ${money(p.mrp - p.price)}</span>` : ""}
                <div class="flex items-center gap-1 bg-amber-100 text-amber-800 text-xs font-bold px-2.5 py-1 rounded-lg">
                    <i data-lucide="star" class="w-3.5 h-3.5 fill-current text-amber-500"></i>${p.rating} (${p.reviews.toLocaleString("en-IN")} reviews)</div>
            </div>
            <p class="text-xs text-gray-500">Inclusive of all taxes. ${p.price >= SITE.freeDeliveryMin ? "<b class='text-emerald-600'>Free delivery</b> on this item." : `Free delivery on orders above ${money(SITE.freeDeliveryMin)}.`}</p>

            <div class="bg-emerald-50/60 p-4 rounded-2xl border border-emerald-200 space-y-2">
                <div class="text-xs font-bold text-emerald-900 flex items-center gap-2"><i data-lucide="map-pin" class="w-4 h-4 text-emerald-600"></i>Check Delivery Availability</div>
                <form id="pin-form" class="flex gap-2">
                    <input id="pin-input" maxlength="6" inputmode="numeric" value="${esc(Store.state.pincode)}" class="bg-white border border-emerald-300 rounded-xl px-3 py-1.5 text-xs font-bold text-gray-800 w-36 focus:outline-none">
                    <button class="bg-emerald-600 text-white px-4 py-1.5 rounded-xl text-xs font-bold hover:bg-emerald-700 transition">Check</button>
                </form>
                <p id="pin-result" class="text-[11px] text-emerald-700 font-medium"></p>
            </div>

            <div class="pt-2 flex flex-wrap items-center gap-3">
                <div class="flex items-center border-2 border-orange-200 rounded-2xl bg-white p-1">
                    <button id="q-dec" class="w-9 h-9 rounded-xl hover:bg-orange-100 font-bold text-brand-600">−</button>
                    <span id="q-val" class="w-10 text-center text-sm font-extrabold text-gray-800">1</span>
                    <button id="q-inc" class="w-9 h-9 rounded-xl hover:bg-orange-100 font-bold text-brand-600">+</button>
                </div>
                <button id="add-btn" class="flex-1 min-w-[150px] bg-white border-2 border-brand-500 text-brand-600 hover:bg-orange-50 font-extrabold py-3 px-6 rounded-2xl transition flex items-center justify-center gap-2"><i data-lucide="shopping-cart" class="w-5 h-5"></i>Add To Cart</button>
                <button id="buy-btn" class="flex-1 min-w-[150px] bg-gradient-to-r from-brand-500 to-brand-600 text-white font-extrabold py-3 px-6 rounded-2xl shadow-md hover:brightness-110 transition">Buy Now</button>
            </div>

            <div class="border-t border-gray-100 pt-4 text-xs text-gray-500 space-y-1">
                <p><strong>Seller:</strong> RELISH Logistics Pvt Ltd (Ghatlodiya, 380061)</p>
                <p><strong>Returns:</strong> 7 Days Easy Return Policy</p>
                ${p.quick ? `<p class="text-emerald-700 font-semibold">⚡ Eligible for 15-minute quick delivery</p>` : ""}
            </div>
        </div>
    </div>

    <div class="bg-white rounded-3xl border border-orange-100 shadow-sm mt-8">
        <div class="flex gap-6 px-6 pt-4 border-b border-gray-100 text-sm font-bold" id="pd-tabs">
            ${["Description", "Details", "Reviews", "Q&A"].map((t, i) => `<button data-t="${i}" class="pb-3 border-b-2 ${i === 0 ? "border-brand-500 text-brand-600" : "border-transparent text-gray-400 hover:text-gray-700"}">${t}${t === "Reviews" ? ` (${p.reviews.toLocaleString("en-IN")})` : ""}</button>`).join("")}
        </div>
        <div class="p-6 text-sm text-gray-600 leading-relaxed" id="pd-panel"></div>
    </div>`;

    const panels = [
        `<p>${esc(p.desc)}</p><ul class="mt-4 space-y-2">${p.highlights.map(h => `<li class="flex items-center gap-2"><i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>${esc(h)}</li>`).join("")}</ul>`,
        `<dl class="grid grid-cols-2 gap-y-2 max-w-md text-xs"><dt class="text-gray-400 font-bold">Brand</dt><dd class="font-semibold">${esc(p.brand)}</dd>
            <dt class="text-gray-400 font-bold">Category</dt><dd class="font-semibold">${cat.name}</dd>
            <dt class="text-gray-400 font-bold">Pack size</dt><dd class="font-semibold">${esc(p.unit)}</dd>
            <dt class="text-gray-400 font-bold">SKU</dt><dd class="font-semibold">${p.sku}</dd>
            <dt class="text-gray-400 font-bold">Country of origin</dt><dd class="font-semibold">India</dd></dl>`,
        reviewsHtml(p),
        qaHtml(p)
    ];
    const panel = qs("#pd-panel");
    const showTab = (i) => {
        panel.innerHTML = i === 3 ? qaHtml(p) : panels[i];
        if (i === 3) bindQa(p);
        qsa("#pd-tabs [data-t]").forEach(b => { const on = Number(b.dataset.t) === i; b.classList.toggle("border-brand-500", on); b.classList.toggle("text-brand-600", on); b.classList.toggle("border-transparent", !on); b.classList.toggle("text-gray-400", !on); });
        refreshIcons();
    };
    qs("#pd-tabs").addEventListener("click", e => { const b = e.target.closest("[data-t]"); if (b) showTab(Number(b.dataset.t)); });
    showTab(0);

    qs("#q-dec").onclick = () => { qty = Math.max(1, qty - 1); qs("#q-val").textContent = qty; };
    qs("#q-inc").onclick = () => { qty = Math.min(20, qty + 1); qs("#q-val").textContent = qty; };
    qs("#add-btn").onclick = () => { Cart.add(p.id, qty); toast(`🛒 Added ${qty} item(s) to cart`); };
    qs("#buy-btn").onclick = () => { Cart.add(p.id, qty); location.href = url("checkout"); };
    qs("#pin-form").addEventListener("submit", e => { e.preventDefault(); checkPin(p); });
    checkPin(p, true);

    // related + recently viewed
    const related = PRODUCTS.filter(x => x.cat === p.cat && x.id !== p.id).slice(0, 5);
    qs("#related").innerHTML = related.length ? `<h2 class="text-xl font-extrabold text-gray-900 mb-4">Similar Products</h2><div class="rail">${related.map(productCard).join("")}</div>` : "";
    const recent = Store.state.recent.filter(r => r !== p.id).map(Store.product).filter(Boolean).slice(0, 5);
    qs("#recent").innerHTML = recent.length ? `<h2 class="text-xl font-extrabold text-gray-900 mb-4">Recently Viewed</h2><div class="rail">${recent.map(productCard).join("")}</div>` : "";
    refreshIcons();
}

function checkPin(p, silent) {
    const pin = qs("#pin-input").value.trim();
    const out = qs("#pin-result");
    if (!/^\d{6}$/.test(pin)) { out.textContent = "Enter a valid 6-digit pincode."; out.className = "text-[11px] text-red-600 font-medium"; return; }
    out.className = "text-[11px] text-emerald-700 font-medium";
    if (/^3[89]/.test(pin)) out.textContent = p.quick ? "⚡ Standard & 15-minute Quick Delivery available at this pincode." : "✅ Standard delivery available - arrives in 1-2 days.";
    else out.textContent = "✅ Standard delivery available - arrives in 3-5 days.";
    if (!silent) toast("✅ Delivery available at " + pin);
}

function reviewsHtml(p) {
    const names = ["Anita S.", "Vikram P.", "Meera D.", "Karan J."];
    const texts = ["Great quality and exactly as described. Delivery was quick.", "Value for money, will order again.", "Fresh and well packed. Happy with the purchase.", "Good product, slightly late delivery but fine."];
    const bars = [5, 4, 3, 2, 1].map((s, i) => { const w = [68, 22, 7, 2, 1][i]; return `<div class="flex items-center gap-2 text-xs"><span class="w-6">${s}★</span><div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden"><div class="h-full bg-amber-400" style="width:${w}%"></div></div><span class="w-8 text-gray-400">${w}%</span></div>`; }).join("");
    return `<div class="grid md:grid-cols-3 gap-8"><div class="space-y-3"><div class="text-5xl font-extrabold text-gray-900">${p.rating}</div><div class="flex gap-0.5">${stars(p.rating, "w-5 h-5")}</div><p class="text-xs text-gray-400">Based on ${p.reviews.toLocaleString("en-IN")} ratings</p>${bars}</div>
        <div class="md:col-span-2 space-y-4">${names.map((n, i) => `<div class="border-b border-gray-100 pb-4"><div class="flex items-center gap-2"><span class="font-bold text-gray-800 text-xs">${n}</span><span class="flex gap-0.5">${stars(i === 3 ? 4 : 5, "w-3 h-3")}</span><span class="text-[10px] text-emerald-600 font-bold">Verified Buyer</span></div><p class="text-xs mt-1">${texts[i]}</p></div>`).join("")}</div></div>`;
}

// Questions & Answers (seeded examples + your own questions, kept in this browser)
function qaHtml(p) {
    const own = (Store.state.qa || {})[p.id] || [];
    const seed = [
        ["Is this product genuine and sealed?", "Yes, Taksh sells 100% genuine, factory-sealed products straight from the brand or authorised distributors."],
        ["What is the return policy?", "This item has a 7 day easy return policy from the date of delivery."],
        ["Is cash on delivery available?", "Yes, COD is available on most pincodes, along with UPI and card payments."]
    ];
    const row = ([q, a], mine) => `<div class="border-b border-gray-100 pb-4"><p class="text-xs font-extrabold text-gray-900">Q: ${esc(q)}</p>
        <p class="text-xs text-gray-600 mt-1">${a ? "A: " + esc(a) : `<span class="text-gray-400 italic">Awaiting an answer from the seller</span>`}</p>${mine ? `<span class="text-[10px] text-brand-600 font-bold">Your question</span>` : ""}</div>`;
    return `<div class="max-w-2xl space-y-5"><form id="qa-form" class="flex gap-2"><input name="q" maxlength="160" class="field" placeholder="Ask a question about this product…" required>
        <button class="bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs px-5 rounded-xl shrink-0">Ask</button></form>
        <div class="space-y-4">${own.map(q => row([q, ""], true)).join("")}${seed.map(x => row(x)).join("")}</div></div>`;
}

function bindQa(p) {
    qs("#qa-form").addEventListener("submit", e => {
        e.preventDefault();
        const q = e.target.elements.q.value.trim();
        if (q.length < 5) return toast("Please enter a longer question", "error");
        const qa = Store.state.qa = Store.state.qa || {};
        qa[p.id] = [q, ...(qa[p.id] || [])];
        Store.save();
        qs("#pd-panel").innerHTML = qaHtml(p); bindQa(p);
        toast("✅ Question posted");
    });
}
