/* Checkout (server-rendered): "Confirm Order" is a real form post, so what this adds is protection and the address form with its map.
   The button locks the moment the form is submitted (a second click does nothing - and the server would answer a repeat with the same order
   anyway, see CheckoutIdempotency), and adding or editing an address opens the map form and reloads the page. */
ready(() => {
    const form = qs("#checkout-form");
    if (!form) return;

    let sent = false;
    form.addEventListener("submit", e => {
        if (sent) { e.preventDefault(); return; }
        if (!form.querySelector("[name=address_id]:checked")) { e.preventDefault(); toast("Please select or add a delivery address", "error"); return; }
        if (!form.querySelector("[name=payment_method]:checked")) { e.preventDefault(); toast("Choose a payment method", "error"); return; }
        sent = true;
        const btn = qs("#place-order");
        btn.disabled = true;
        btn.querySelector("span").textContent = "Placing your order…";
    });

    // keep the highlighted card in step with the chosen radio
    form.addEventListener("change", e => {
        if (e.target.name !== "address_id" && e.target.name !== "payment_method") return;
        qsa(`[name=${e.target.name}]`, form).forEach(r => {
            const card = r.closest("label");
            card.classList.toggle("border-brand-500", r.checked); card.classList.toggle("bg-orange-50/40", r.checked);
            card.classList.toggle("border-gray-200", !r.checked);
        });
    });

    document.addEventListener("click", e => {
        const add = e.target.closest("[data-address-new]"), edit = e.target.closest("[data-address-edit]");
        if (add) Addresses.openForm(null, () => location.reload());
        if (edit) { e.preventDefault(); Addresses.openForm(JSON.parse(edit.dataset.addressEdit), () => location.reload()); }
    });
    refreshIcons();
});
