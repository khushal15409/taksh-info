/* Order tracking + invoice */
const STEPS = [
    ["Order Placed", "Your order has been received by Taksh Seller", 0],
    ["Order Confirmed", "Seller has packed your items", 20],
    ["Shipped", "Courier partner assigned and picked up your order", 60],
    ["Out for Delivery", "Your delivery partner is on the way", 120],
    ["Delivered", "Order delivered. Enjoy!", 180]
];   // third value: demo seconds after which the step completes
let current = null;

ready(() => {
    const orders = Store.state.orders;
    const root = qs("#tracking-root");
    if (!orders.length) {
        root.innerHTML = emptyState({ emoji: "📦", title: "No orders yet", text: "Once you place an order you can track it here.", ctaLabel: "Start Shopping", ctaHref: url("listing") });
        return;
    }
    const id = new URLSearchParams(location.search).get("id");
    current = Store.Orders.get(id) || orders[0];
    render(orders);
    setInterval(() => renderTimeline(), 5000);
});

function stepIndex(o) {
    const sec = (Date.now() - o.createdAt) / 1000;
    let idx = 0;
    STEPS.forEach(([, , t], i) => { if (sec >= t) idx = i; });
    return idx;
}

function render(orders) {
    const o = current;
    qs("#tracking-root").innerHTML = `
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <aside class="no-print bg-white p-4 rounded-3xl border border-orange-100 h-fit space-y-2">
            <h3 class="font-extrabold text-sm text-gray-900 px-2 pb-2">My Orders</h3>
            ${orders.map(x => `<a href="${url("tracking", { id: x.id })}" class="block px-3 py-2.5 rounded-xl text-xs ${x.id === o.id ? "bg-orange-50 text-brand-600 font-bold" : "text-gray-600 hover:bg-gray-50"}">
                <div class="font-bold">#${x.id}</div><div class="text-[11px] text-gray-400">${x.items.length} item(s) • ${money(x.totals.total)}</div></a>`).join("")}
        </aside>
        <section class="lg:col-span-3 bg-white p-6 lg:p-8 rounded-3xl border border-orange-100 shadow-sm space-y-6">
            <div class="flex flex-wrap items-center justify-between border-b pb-6 border-gray-100 gap-4">
                <div>
                    <span id="status-pill" class="bg-emerald-100 text-emerald-800 text-xs font-extrabold px-3 py-1 rounded-full uppercase"></span>
                    <h1 class="text-2xl font-extrabold text-gray-900 mt-2">Order #${o.id}</h1>
                    <p class="text-xs text-gray-400">Placed on ${new Date(o.createdAt).toLocaleString("en-IN", { day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" })} • Paid via ${esc(o.payment)}</p>
                </div>
                <button onclick="window.print()" class="no-print border border-emerald-600 text-emerald-700 font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-2 hover:bg-emerald-50"><i data-lucide="download" class="w-4 h-4"></i>Download Invoice</button>
            </div>
            <div class="space-y-4"><h3 class="font-extrabold text-sm text-gray-900">Order Delivery Status</h3><div id="timeline" class="relative pl-10 space-y-6"></div></div>
            <div class="grid md:grid-cols-2 gap-6 border-t pt-6 border-gray-100 text-xs">
                <div><h3 class="font-extrabold text-sm text-gray-900 mb-2">Delivery Address</h3>
                    <p class="font-bold text-gray-800">${esc(o.address.name)} <span class="bg-brand-500 text-white text-[10px] px-2 py-0.5 rounded-full uppercase ml-1">${esc(o.address.label)}</span></p>
                    <p class="text-gray-500 mt-1">${esc(o.address.line)}, ${esc(o.address.city)} - ${esc(o.address.pincode)}</p><p class="text-gray-500">Phone: ${esc(o.address.phone)}</p>
                    ${o.note ? `<p class="mt-2 text-gray-500"><b>Note:</b> ${esc(o.note)}</p>` : ""}</div>
                <div class="space-y-2"><h3 class="font-extrabold text-sm text-gray-900 mb-2">Payment Summary</h3>
                    <div class="flex justify-between text-gray-600"><span>Subtotal</span><span>${money2(o.totals.subtotal)}</span></div>
                    <div class="flex justify-between text-gray-600"><span>Delivery</span><span>${o.totals.delivery ? money2(o.totals.delivery) : "FREE"}</span></div>
                    ${o.totals.discount ? `<div class="flex justify-between text-emerald-600 font-bold"><span>Discount (${o.coupon})</span><span>−${money2(o.totals.discount)}</span></div>` : ""}
                    <div class="flex justify-between font-extrabold text-sm text-gray-900 border-t pt-2"><span>Total</span><span class="text-brand-600">${money2(o.totals.total)}</span></div></div>
            </div>
            <div class="border-t pt-6 border-gray-100 space-y-3"><h3 class="font-extrabold text-sm text-gray-900">Ordered Items</h3>
                ${o.items.map(i => `<div class="flex items-center gap-4 bg-orange-50/40 p-3 rounded-2xl border border-orange-100">
                    <img src="${ROOT + i.img}" alt="" class="w-14 h-14 rounded-xl object-contain bg-white">
                    <div class="flex-1 min-w-0"><h4 class="text-xs font-bold text-gray-800">${esc(i.name)}</h4><p class="text-[11px] text-gray-400">Qty: ${i.qty} × ${money2(i.price)}</p></div>
                    <div class="text-sm font-extrabold text-brand-600">${money2(i.price * i.qty)}</div></div>`).join("")}</div>
            <p class="no-print text-[11px] text-gray-400">Live tracking shown here is a demo simulation of order progress.</p>
        </section>
    </div>`;
    renderTimeline();
    refreshIcons();
}

function renderTimeline() {
    const el = qs("#timeline");
    if (!el || !current) return;
    const idx = stepIndex(current);
    qs("#status-pill").textContent = STEPS[idx][0];
    el.innerHTML = `<div class="timeline-line" style="left:15px"></div>` + STEPS.map(([t, d], i) => {
        const done = i <= idx;
        return `<div class="relative"><span class="absolute -left-[33px] top-0 w-4 h-4 rounded-full ${done ? "bg-emerald-500 ring-4 ring-emerald-100" : "bg-gray-300"}"></span>
            <h4 class="text-xs font-bold ${done ? "text-gray-900" : "text-gray-400"}">${t}${i === idx && idx < STEPS.length - 1 ? ` <span class="text-[10px] text-brand-600 ml-1 animate-pulse">● in progress</span>` : ""}</h4>
            <p class="text-[11px] ${done ? "text-gray-500" : "text-gray-400"}">${d}</p></div>`;
    }).join("");
}
