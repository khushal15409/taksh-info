/* Checkout */
let addressId = null, payment = "cod";

ready(() => {
    if (!Cart.items().length) { location.replace(url("cart")); return; }
    const def = Store.state.addresses.find(a => a.isDefault) || Store.state.addresses[0];
    addressId = def ? def.id : null;
    render();
    Store.subscribe(() => { if (Cart.items().length) renderSummary(); });
});

function render() {
    renderAddresses();
    renderPayment();
    renderSummary();
    refreshIcons();
}

function renderAddresses() {
    const list = Store.state.addresses;
    qs("#addr-list").innerHTML = list.length ? list.map(a => `
        <label class="block p-4 rounded-2xl border-2 cursor-pointer transition ${a.id === addressId ? "border-brand-500 bg-orange-50/40" : "border-gray-200 hover:border-brand-500/50"}">
            <div class="flex justify-between items-start gap-3">
                <div class="flex gap-3">
                    <input type="radio" name="addr" value="${a.id}" class="accent-brand-500 mt-1" ${a.id === addressId ? "checked" : ""}>
                    <div>
                        <span class="bg-brand-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">${esc(a.label)}</span>
                        <h4 class="font-bold text-sm text-gray-800 mt-2">${esc(a.name)}</h4>
                        <p class="text-xs text-gray-500 mt-1">${esc(a.line)}, ${esc(a.city)} - ${esc(a.pincode)}</p>
                        <p class="text-xs text-gray-500 mt-0.5">Phone: ${esc(a.phone)}</p>
                    </div>
                </div>
                <button type="button" data-edit="${a.id}" class="text-xs font-bold text-brand-600 hover:underline">Edit</button>
            </div>
        </label>`).join("") : `<p class="text-sm text-gray-500">No saved addresses yet. Add one to continue.</p>`;
}

function renderPayment() {
    const opts = [
        ["cod", "Cash on Delivery (COD)", "Pay with cash upon delivery", "FREE", "text-emerald-600"],
        ["upi", "UPI (Google Pay / PhonePe / Paytm)", "Instant refund & quick processing", "Instant", "text-brand-600"],
        ["card", "Credit / Debit Card", "Visa, MasterCard, RuPay", "Secure", "text-brand-600"],
        ["razorpay", "Pay Online (Razorpay)", "Cards, UPI, Netbanking & Wallets via secure gateway", "Secure", "text-blue-600"]
    ];
    qs("#pay-list").innerHTML = opts.map(([k, t, d, tag, tc]) => `
        <label class="flex items-center justify-between p-4 rounded-2xl border-2 cursor-pointer transition ${payment === k ? "border-brand-500 bg-orange-50/40" : "border-gray-200 hover:border-brand-500/50"}">
            <div class="flex items-center gap-3"><input type="radio" name="pay" value="${k}" class="accent-brand-500" ${payment === k ? "checked" : ""}>
                <div><div class="text-sm font-bold text-gray-800">${t}</div><div class="text-xs text-gray-500">${d}</div></div></div>
            <span class="text-xs font-bold ${tc}">${tag}</span></label>`).join("") + `
        <div id="pay-extra" class="${payment === "cod" ? "hidden" : ""} p-4 rounded-2xl bg-gray-50 border border-gray-200 text-xs">
            ${payment === "upi" ? `<label class="font-bold text-gray-500">UPI ID</label><input id="upi-id" class="field mt-1" placeholder="name@upi">` : ""}
            ${payment === "card" ? `<div class="grid grid-cols-2 gap-3"><div class="col-span-2"><label class="font-bold text-gray-500">Card number</label><input id="card-no" maxlength="19" inputmode="numeric" class="field mt-1" placeholder="1234 5678 9012 3456"></div>
                <div><label class="font-bold text-gray-500">Expiry (MM/YY)</label><input id="card-exp" maxlength="5" class="field mt-1" placeholder="08/28"></div>
                <div><label class="font-bold text-gray-500">CVV</label><input id="card-cvv" maxlength="3" type="password" inputmode="numeric" class="field mt-1" placeholder="•••"></div></div>` : ""}
        </div>`;
}

function renderSummary() {
    const items = Cart.items(), t = Cart.totals();
    qs("#co-items").innerHTML = items.map(({ product: p, qty, line }) => `
        <div class="flex items-center gap-3">${productImg(p, "w-12 h-12 rounded-xl object-contain bg-orange-50/60")}
            <div class="flex-1 min-w-0"><div class="text-xs font-bold text-gray-800 truncate">${esc(p.name)}</div><div class="text-[11px] text-gray-400">Qty ${qty} × ${money(p.price)}</div></div>
            <div class="text-xs font-extrabold text-gray-900">${money(line)}</div></div>`).join("");
    qs("#co-totals").innerHTML = `
        <div class="flex justify-between text-gray-600"><span>Item Total</span><span class="font-bold text-gray-900">${money2(t.subtotal)}</span></div>
        <div class="flex justify-between text-gray-600"><span>Delivery Fee</span><span class="font-bold ${t.delivery ? "text-gray-900" : "text-emerald-600"}">${t.delivery ? money2(t.delivery) : "FREE"}</span></div>
        ${t.discount ? `<div class="flex justify-between text-emerald-600 font-bold"><span>Coupon (${Store.state.coupon})</span><span>−${money2(t.discount)}</span></div>` : ""}
        <div class="border-t pt-3 flex justify-between text-base font-extrabold text-gray-900"><span>Grand Total</span><span class="text-brand-600">${money2(t.total)}</span></div>`;
}

document.addEventListener("change", e => {
    if (e.target.name === "addr") { addressId = e.target.value; renderAddresses(); }
    if (e.target.name === "pay") { payment = e.target.value; renderPayment(); }
});
document.addEventListener("click", e => {
    const edit = e.target.closest("[data-edit]");
    if (edit) { e.preventDefault(); openAddressModal(Store.state.addresses.find(a => a.id === edit.dataset.edit), () => { renderAddresses(); refreshIcons(); }); }
    if (e.target.closest("#add-address")) openAddressModal(null, a => { addressId = a.id; renderAddresses(); refreshIcons(); });
    if (e.target.closest("#place-order")) placeOrder();
});

function placeOrder() {
    const address = Store.state.addresses.find(a => a.id === addressId);
    if (!address) return toast("Please select or add a delivery address", "error");
    if (payment === "upi" && !/^[\w.\-]{2,}@[\w]{2,}$/.test(qs("#upi-id").value.trim())) return toast("Enter a valid UPI ID (e.g. name@upi)", "error");
    if (payment === "card") {
        if (!/^\d{16}$/.test(qs("#card-no").value.replace(/\s/g, ""))) return toast("Enter a valid 16-digit card number", "error");
        if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(qs("#card-exp").value)) return toast("Enter expiry as MM/YY", "error");
        if (!/^\d{3}$/.test(qs("#card-cvv").value)) return toast("Enter a valid 3-digit CVV", "error");
    }
    if (payment === "razorpay") return openGateway(address);
    const label = { cod: "Cash on Delivery", upi: "UPI", card: "Card" }[payment];
    const order = Store.Orders.place({ address, payment: label, note: qs("#order-note").value.trim() });
    toast("🎉 Order Confirmed Successfully!");
    setTimeout(() => { location.href = url("tracking", { id: order.id }); }, 700);
}

// Online gateway (demo): simulates the pay-now step. Replace with the real Razorpay Checkout once the backend creates orders.
function openGateway(address) {
    const total = Cart.totals().total;
    const m = Modal.open({
        title: "Secure Online Payment",
        body: `<div class="space-y-4 text-xs">
            <div class="flex items-center justify-between bg-orange-50/60 border border-orange-100 rounded-2xl p-4"><span class="font-bold text-gray-600">Amount payable</span><span class="text-xl font-extrabold text-brand-600">${money2(total)}</span></div>
            <p class="text-gray-500">Choose a method to complete the payment. <span class="text-gray-400">(Demo gateway - no real money is charged.)</span></p>
            <div class="grid grid-cols-2 gap-3">${[["upi", "📱", "UPI"], ["card", "💳", "Card"], ["netbanking", "🏦", "Netbanking"], ["wallet", "👛", "Wallet"]].map(([k, e, l], i) => `<label class="flex items-center gap-2 p-3 rounded-xl border-2 border-gray-200 cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:bg-orange-50/40"><input type="radio" name="gw" value="${k}" class="accent-brand-500" ${i === 0 ? "checked" : ""}><span>${e}</span><span class="font-bold text-gray-700">${l}</span></label>`).join("")}</div>
            <button id="gw-pay" class="w-full bg-brand-500 hover:bg-brand-600 text-white font-extrabold py-3 rounded-2xl transition">Pay ${money2(total)}</button>
            <button id="gw-fail" class="w-full text-gray-400 hover:text-red-500 font-bold">Simulate a failed payment</button></div>`
    });
    qs("#gw-pay", m).onclick = ev => {
        ev.target.disabled = true; ev.target.textContent = "Processing payment…";
        setTimeout(() => {
            const order = Store.Orders.place({ address, payment: "Razorpay (Online)", note: qs("#order-note").value.trim() });
            Modal.close();
            location.href = url("payresult", { status: "success", order: order.id });
        }, 1400);
    };
    qs("#gw-fail", m).onclick = () => { Modal.close(); location.href = url("payresult", { status: "failed" }); };
}
