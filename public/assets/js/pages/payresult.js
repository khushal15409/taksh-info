/* Payment success / failed result page */
ready(() => {
    const p = new URLSearchParams(location.search);
    const ok = p.get("status") === "success";
    const order = p.get("order");
    qs("#payresult-root").innerHTML = `
    <div class="max-w-md mx-auto bg-white rounded-3xl border border-orange-100 shadow-sm p-8 text-center space-y-4 fade-up">
        <div class="w-24 h-24 mx-auto rounded-full ${ok ? "bg-emerald-100 text-emerald-600" : "bg-red-100 text-red-500"} flex items-center justify-center"><i data-lucide="${ok ? "check" : "x"}" class="w-12 h-12"></i></div>
        <h1 class="text-2xl font-extrabold text-gray-900">${ok ? "Payment Successful" : "Payment Failed"}</h1>
        <p class="text-sm text-gray-500">${ok ? "Thank you! Your payment was received and your order is confirmed." : esc(p.get("reason") || "We couldn't process your payment. No money was deducted. Please try again or choose another method.")}</p>
        ${ok && order ? `<p class="text-xs text-gray-400">Order ID: <b class="text-gray-700">#${esc(order)}</b></p>` : ""}
        <div class="flex flex-col gap-2 pt-2">
            ${ok ? `<a href="${url("tracking", { id: order })}" class="bg-brand-500 hover:bg-brand-600 text-white font-bold py-3 rounded-2xl">Track Order</a><a href="${url("listing")}" class="text-xs font-bold text-gray-500 hover:text-brand-600">Continue shopping</a>`
                : `<a href="${url("checkout")}" class="bg-brand-500 hover:bg-brand-600 text-white font-bold py-3 rounded-2xl">Retry Payment</a><a href="${url("cart")}" class="text-xs font-bold text-gray-500 hover:text-brand-600">← Back to cart</a>`}
        </div>
    </div>`;
    refreshIcons();
});
