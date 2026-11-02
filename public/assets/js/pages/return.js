/* Return request: choose items, reason, photos/videos (UI demo - files stay in the browser) */
const REASONS = ["Damaged / broken item", "Wrong item delivered", "Item expired / not fresh", "Quality not as expected", "Item missing in package", "Changed my mind"];
const MAX_FILES = 4;
let order = null, files = [];

ready(() => {
    const root = qs("#return-root");
    order = Store.Orders.get(new URLSearchParams(location.search).get("id"));
    if (!order) { root.innerHTML = emptyState({ emoji: "📦", title: "Order not found", text: "Open a delivered order from My Orders to request a return.", ctaLabel: "My Orders", ctaHref: url("profile", null, "#orders") }); return; }
    if (order.cancelled) { root.innerHTML = emptyState({ emoji: "🚫", title: "Order was cancelled", text: "Cancelled orders can't be returned.", ctaLabel: "My Orders", ctaHref: url("profile", null, "#orders") }); return; }
    if (order.returnRequest) { done(root, order.returnRequest); return; }
    if ((Date.now() - order.createdAt) / 1000 < 180) {
        root.innerHTML = emptyState({ emoji: "⏳", title: "Not delivered yet", text: "You can request a return once your order has been delivered.", ctaLabel: "Track Order", ctaHref: url("tracking", { id: order.id }) });
        return;
    }
    render(root);
});

function render(root) {
    root.innerHTML = `
    <form id="return-form" class="max-w-3xl mx-auto space-y-6" novalidate>
        <div><a href="${url("tracking", { id: order.id })}" class="text-xs font-bold text-gray-500 hover:text-brand-600">← Back to order</a>
            <h1 class="text-2xl font-extrabold text-gray-900 mt-1">Return Items</h1><p class="text-xs text-gray-400">Order #${order.id} • 7-day return window • <a class="underline" href="${url("refund")}">Refund policy</a></p></div>
        <section class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-3"><h2 class="font-extrabold text-sm text-gray-900">1. Select items to return</h2>
            ${order.items.map((i, k) => `<label class="flex items-center gap-4 bg-orange-50/40 p-3 rounded-2xl border border-orange-100 cursor-pointer">
                <input type="checkbox" name="item" value="${k}" class="accent-brand-500 w-4 h-4">
                <img src="${ROOT + i.img}" alt="" class="w-14 h-14 rounded-xl object-contain bg-white"><div class="flex-1 min-w-0"><h4 class="text-xs font-bold text-gray-800">${esc(i.name)}</h4><p class="text-[11px] text-gray-400">Qty ${i.qty} × ${money2(i.price)}</p></div>
                <select name="qty-${k}" class="field !w-20 !py-1.5 text-xs" aria-label="Quantity to return">${Array.from({ length: i.qty }, (_, n) => `<option>${n + 1}</option>`).join("")}</select></label>`).join("")}
        </section>
        <section class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-3 text-xs"><h2 class="font-extrabold text-sm text-gray-900">2. Reason</h2>
            <select name="reason" class="field">${REASONS.map(r => `<option>${r}</option>`).join("")}</select>
            <textarea name="comment" rows="3" class="field" placeholder="Describe the issue (optional)"></textarea></section>
        <section class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-3 text-xs"><h2 class="font-extrabold text-sm text-gray-900">3. Add photos / video <span class="font-medium text-gray-400">(up to ${MAX_FILES})</span></h2>
            <label class="block border-2 border-dashed border-orange-200 rounded-2xl p-6 text-center cursor-pointer hover:border-brand-500 transition">
                <i data-lucide="upload-cloud" class="w-7 h-7 mx-auto text-brand-500"></i><span class="block font-bold text-gray-700 mt-1">Click to upload</span><span class="text-gray-400">JPG, PNG or MP4 • max 10 MB each</span>
                <input id="media" type="file" accept="image/*,video/mp4" multiple class="sr-only"></label>
            <div id="previews" class="grid grid-cols-4 gap-3"></div></section>
        <section class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-3 text-xs"><h2 class="font-extrabold text-sm text-gray-900">4. Refund method</h2>
            ${[["wallet", "Taksh Wallet", "Instant credit as points"], ["source", "Original payment method", "5-7 working days"]].map(([v, t, d], i) => `<label class="flex items-center gap-3 p-4 rounded-2xl border-2 border-gray-200 has-[:checked]:border-brand-500 has-[:checked]:bg-orange-50/40 cursor-pointer"><input type="radio" name="refund" value="${v}" class="accent-brand-500" ${i === 0 ? "checked" : ""}><div><div class="text-sm font-bold text-gray-800">${t}</div><div class="text-gray-500">${d}</div></div></label>`).join("")}</section>
        <button class="w-full bg-brand-500 hover:bg-brand-600 text-white font-extrabold py-3.5 rounded-2xl shadow-lg transition">Submit Return Request</button>
    </form>`;
    refreshIcons();
    qs("#media").addEventListener("change", e => {
        for (const f of e.target.files) {
            if (files.length >= MAX_FILES) { toast(`Only ${MAX_FILES} files allowed`, "error"); break; }
            if (f.size > 10 * 1024 * 1024) { toast(`${f.name} is larger than 10 MB`, "error"); continue; }
            files.push(f);
        }
        e.target.value = "";
        previews();
    });
    qs("#previews").addEventListener("click", e => { const b = e.target.closest("[data-rm]"); if (b) { files.splice(Number(b.dataset.rm), 1); previews(); } });
    qs("#return-form").addEventListener("submit", submit);
}

function previews() {
    qs("#previews").innerHTML = files.map((f, i) => {
        const u = URL.createObjectURL(f);
        return `<div class="relative aspect-square rounded-xl overflow-hidden border border-gray-200 bg-gray-50">${f.type.startsWith("video") ? `<video src="${u}" class="w-full h-full object-cover" muted></video>` : `<img src="${u}" alt="" class="w-full h-full object-cover">`}
            <button type="button" data-rm="${i}" class="absolute top-1 right-1 w-6 h-6 rounded-full bg-black/60 text-white text-xs" aria-label="Remove">✕</button></div>`;
    }).join("");
}

function submit(e) {
    e.preventDefault();
    const f = new FormData(e.target);
    const picked = f.getAll("item").map(Number);
    if (!picked.length) return toast("Select at least one item to return", "error");
    const req = {
        id: "RET" + Date.now().toString(36).toUpperCase().slice(-6), at: Date.now(), reason: f.get("reason"), comment: (f.get("comment") || "").trim(), refund: f.get("refund"),
        items: picked.map(k => ({ name: order.items[k].name, qty: Number(f.get("qty-" + k)), price: order.items[k].price })), media: files.length
    };
    req.amount = req.items.reduce((s, i) => s + i.qty * i.price, 0);
    order.returnRequest = req;
    Store.save();
    toast("✅ Return request submitted");
    done(qs("#return-root"), req);
}

function done(root, r) {
    root.innerHTML = `<div class="max-w-md mx-auto bg-white rounded-3xl border border-orange-100 shadow-sm p-8 text-center space-y-4 fade-up">
        <div class="w-20 h-20 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center"><i data-lucide="check" class="w-10 h-10"></i></div>
        <h1 class="text-xl font-extrabold text-gray-900">Return request submitted</h1>
        <p class="text-sm text-gray-500">Our team will review it and arrange a pickup within 24-48 hours. Estimated refund: <b class="text-gray-800">${money2(r.amount)}</b> to ${r.refund === "wallet" ? "your Taksh Wallet" : "your original payment method"}.</p>
        <p class="text-xs text-gray-400">Reference: <b class="text-gray-700">${r.id}</b> • ${r.items.length} item(s) • ${r.media} attachment(s)</p>
        <a href="${url("tracking", { id: order.id })}" class="inline-block bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm px-6 py-3 rounded-2xl">Back to order</a></div>`;
    refreshIcons();
}
