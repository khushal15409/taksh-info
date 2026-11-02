/* Product listing: filters, sorting, pagination (state synced to URL) */
const PER_PAGE = 12;
const MAX_PRICE = Math.ceil(Math.max(...PRODUCTS.map(p => p.price)) / 50) * 50;
const f = { q: "", cats: new Set(), brands: new Set(), maxPrice: MAX_PRICE, minRating: 0, quick: false, deals: false, sort: "relevance", page: 1 };

ready(() => {
    const p = new URLSearchParams(location.search);
    f.q = p.get("q") || "";
    (p.get("cat") || "").split(",").filter(Boolean).forEach(c => f.cats.add(c));
    (p.get("brand") || "").split(",").filter(Boolean).forEach(b => f.brands.add(b));
    f.quick = p.get("quick") === "1";
    f.deals = p.get("deals") === "1";
    f.sort = p.get("sort") || "relevance";
    f.maxPrice = Number(p.get("max")) || MAX_PRICE;
    f.minRating = Number(p.get("rating")) || 0;
    f.page = Number(p.get("page")) || 1;

    renderSidebar();
    qs("#sort-select").value = f.sort;
    qs("#sort-select").addEventListener("change", e => { f.sort = e.target.value; f.page = 1; apply(); });
    qs("#filter-toggle").addEventListener("click", () => qs("#filters").classList.toggle("hidden"));
    qs("#filters").addEventListener("change", onFilterChange);
    qs("#filters").addEventListener("input", e => { if (e.target.id === "price-range") qs("#price-val").textContent = money(e.target.value); });
    qs("#reset-filters").addEventListener("click", resetAll);
    qs("#results").addEventListener("click", e => {
        const pg = e.target.closest("[data-page]");
        if (pg) { f.page = Number(pg.dataset.page); apply(); window.scrollTo({ top: 0, behavior: "smooth" }); }
        if (e.target.closest("[data-reset]")) resetAll();
    });
    qs("#chips").addEventListener("click", e => {
        const c = e.target.closest("[data-chip]");
        if (!c) return;
        const [k, v] = c.dataset.chip.split(":");
        if (k === "cat") f.cats.delete(v);
        if (k === "brand") f.brands.delete(v);
        if (k === "quick") f.quick = false;
        if (k === "deals") f.deals = false;
        if (k === "q") { f.q = ""; qs("#search-input").value = ""; }
        if (k === "rating") f.minRating = 0;
        if (k === "max") f.maxPrice = MAX_PRICE;
        f.page = 1; syncSidebar(); apply();
    });
    apply();
});

function renderSidebar() {
    const brands = [...new Set(PRODUCTS.map(p => p.brand))].sort();
    qs("#filter-cats").innerHTML = CATEGORIES.map(c => `
        <label class="flex items-center gap-2 cursor-pointer text-gray-600 hover:text-gray-900">
            <input type="checkbox" data-f="cat" value="${c.id}" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" ${f.cats.has(c.id) ? "checked" : ""}>
            <span>${c.emoji} ${c.name}</span><span class="ml-auto text-gray-400">${PRODUCTS.filter(p => p.cat === c.id).length}</span></label>`).join("");
    qs("#filter-brands").innerHTML = brands.map(b => `
        <label class="flex items-center gap-2 cursor-pointer text-gray-600 hover:text-gray-900">
            <input type="checkbox" data-f="brand" value="${esc(b)}" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" ${f.brands.has(b) ? "checked" : ""}>
            <span>${esc(b)}</span><span class="ml-auto text-gray-400">${PRODUCTS.filter(p => p.brand === b).length}</span></label>`).join("");
    const r = qs("#price-range");
    r.max = MAX_PRICE; r.value = f.maxPrice;
    qs("#price-val").textContent = money(f.maxPrice);
    qs("#price-max-label").textContent = money(MAX_PRICE);
    syncSidebar();
}

function syncSidebar() {
    qsa("[data-f=cat]").forEach(i => i.checked = f.cats.has(i.value));
    qsa("[data-f=brand]").forEach(i => i.checked = f.brands.has(i.value));
    qs("#f-quick").checked = f.quick;
    qs("#f-deals").checked = f.deals;
    qsa("[name=rating]").forEach(i => i.checked = Number(i.value) === f.minRating);
    qs("#price-range").value = f.maxPrice;
    qs("#price-val").textContent = money(f.maxPrice);
}

function onFilterChange(e) {
    const t = e.target;
    if (t.dataset.f === "cat") t.checked ? f.cats.add(t.value) : f.cats.delete(t.value);
    else if (t.dataset.f === "brand") t.checked ? f.brands.add(t.value) : f.brands.delete(t.value);
    else if (t.id === "f-quick") f.quick = t.checked;
    else if (t.id === "f-deals") f.deals = t.checked;
    else if (t.name === "rating") f.minRating = Number(t.value);
    else if (t.id === "price-range") f.maxPrice = Number(t.value);
    f.page = 1;
    apply();
}

function resetAll() {
    f.q = ""; f.cats.clear(); f.brands.clear(); f.maxPrice = MAX_PRICE; f.minRating = 0; f.quick = false; f.deals = false; f.page = 1;
    qs("#search-input").value = "";
    syncSidebar(); apply();
}

function filtered() {
    const q = f.q.trim().toLowerCase();
    let list = PRODUCTS.filter(p =>
        (!q || (p.name + " " + p.brand + " " + (catById(p.cat) || {}).name).toLowerCase().includes(q)) &&
        (!f.cats.size || f.cats.has(p.cat)) &&
        (!f.brands.size || f.brands.has(p.brand)) &&
        p.price <= f.maxPrice && p.rating >= f.minRating &&
        (!f.quick || p.quick) && (!f.deals || discountPct(p) >= 15));
    const sorts = {
        "price-asc": (a, b) => a.price - b.price,
        "price-desc": (a, b) => b.price - a.price,
        "rating": (a, b) => b.rating - a.rating,
        "discount": (a, b) => discountPct(b) - discountPct(a),
        "popular": (a, b) => b.reviews - a.reviews,
        "name": (a, b) => a.name.localeCompare(b.name)
    };
    if (sorts[f.sort]) list = list.slice().sort(sorts[f.sort]);
    return list;
}

function title() {
    if (f.q) return `Results for “${esc(f.q)}”`;
    if (f.quick) return "⚡ Quick 15-Min Delivery";
    if (f.deals) return "🪔 Diwali Offers";
    if (f.cats.size === 1) { const c = catById([...f.cats][0]); return c ? `${c.emoji} ${c.name}` : "All Products"; }
    return "All Products";
}

function apply() {
    const list = filtered();
    const pages = Math.max(1, Math.ceil(list.length / PER_PAGE));
    f.page = Math.min(f.page, pages);
    const slice = list.slice((f.page - 1) * PER_PAGE, f.page * PER_PAGE);

    qs("#page-title").innerHTML = title();
    qs("#crumb-current").innerHTML = title().replace(/^[^\w“]+/, "");
    qs("#count-label").textContent = list.length;

    const chips = [];
    f.cats.forEach(c => chips.push(["cat:" + c, (catById(c) || {}).name || c]));
    f.brands.forEach(b => chips.push(["brand:" + b, b]));
    if (f.q) chips.push(["q:q", `“${f.q}”`]);
    if (f.quick) chips.push(["quick:1", "Quick delivery"]);
    if (f.deals) chips.push(["deals:1", "15%+ off"]);
    if (f.minRating) chips.push(["rating:1", `${f.minRating}★ & up`]);
    if (f.maxPrice < MAX_PRICE) chips.push(["max:1", `Under ${money(f.maxPrice)}`]);
    qs("#chips").innerHTML = chips.map(([k, l]) => `<button data-chip="${esc(k)}" class="flex items-center gap-1.5 bg-orange-100 text-brand-700 text-[11px] font-bold px-3 py-1 rounded-full hover:bg-orange-200">${esc(l)} <span>✕</span></button>`).join("");

    qs("#results").innerHTML = list.length
        ? productGrid(slice, "grid-cols-2 md:grid-cols-3") + pagination(pages)
        : emptyState({ emoji: "🔍", title: "No products found", text: "Try changing or clearing some filters.", ctaLabel: "" }) +
          `<div class="text-center"><button data-reset class="bg-brand-500 text-white font-bold text-sm px-6 py-3 rounded-2xl">Clear all filters</button></div>`;

    // keep URL shareable
    const u = new URLSearchParams();
    if (f.q) u.set("q", f.q);
    if (f.cats.size) u.set("cat", [...f.cats].join(","));
    if (f.brands.size) u.set("brand", [...f.brands].join(","));
    if (f.quick) u.set("quick", "1");
    if (f.deals) u.set("deals", "1");
    if (f.sort !== "relevance") u.set("sort", f.sort);
    if (f.maxPrice < MAX_PRICE) u.set("max", f.maxPrice);
    if (f.minRating) u.set("rating", f.minRating);
    if (f.page > 1) u.set("page", f.page);
    history.replaceState(null, "", location.pathname + (u.toString() ? "?" + u : ""));
    refreshIcons();
}

function pagination(pages) {
    if (pages <= 1) return "";
    const btn = (n, label, on, dis) => `<button data-page="${n}" ${dis ? "disabled" : ""} class="min-w-[38px] h-9 px-3 rounded-xl text-xs font-bold border transition ${on ? "bg-brand-500 text-white border-brand-500" : "bg-white text-gray-600 border-gray-200 hover:border-brand-500"} ${dis ? "opacity-40 pointer-events-none" : ""}">${label}</button>`;
    let out = btn(f.page - 1, "‹ Prev", false, f.page === 1);
    for (let i = 1; i <= pages; i++) out += btn(i, i, i === f.page, false);
    out += btn(f.page + 1, "Next ›", false, f.page === pages);
    return `<div class="flex items-center justify-center gap-2 pt-8 flex-wrap">${out}</div>`;
}
