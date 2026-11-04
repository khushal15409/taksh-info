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

    // only the chosen address is shown; "Change address" opens the others, and picking one makes it the shown one
    const list = qs("#address-list"), toggle = qs("#address-toggle");
    const cards = () => qsa("[data-address-card]", list || form);
    const collapse = () => {
        cards().forEach(c => { c.hidden = !c.querySelector("[name=address_id]").checked; });
        if (toggle) { toggle.textContent = toggle.dataset.more; toggle.dataset.open = ""; }
    };
    if (toggle) toggle.addEventListener("click", () => {
        const open = !toggle.dataset.open;
        cards().forEach(c => { c.hidden = open ? false : !c.querySelector("[name=address_id]").checked; });
        toggle.textContent = open ? toggle.dataset.less : toggle.dataset.more;
        toggle.dataset.open = open ? "1" : "";
    });

    // keep the highlighted card in step with the chosen radio
    form.addEventListener("change", e => {
        if (e.target.name !== "address_id" && e.target.name !== "payment_method") return;
        qsa(`[name=${e.target.name}]`, form).forEach(r => {
            const card = r.closest("label");
            card.classList.toggle("border-brand-500", r.checked); card.classList.toggle("bg-orange-50/40", r.checked);
            card.classList.toggle("border-gray-200", !r.checked);
        });
        if (e.target.name === "address_id" && list) {
            list.prepend(e.target.closest("label"));
            collapse();
        }
    });

    // adding / editing an address reloads the page on the address that was just worked on
    const back = id => { const u = new URL(location.href); if (id) u.searchParams.set("address", id); location.href = u.toString(); };
    document.addEventListener("click", e => {
        const add = e.target.closest("[data-address-new]"), edit = e.target.closest("[data-address-edit]");
        if (add) {
            const before = new Set((Addresses.all() || []).map(a => a.id));
            Addresses.openForm(null, () => { const fresh = (Addresses.all() || []).find(a => !before.has(a.id)); back(fresh ? fresh.id : (form.querySelector("[name=address_id]:checked") || {}).value); });
        }
        if (edit) { e.preventDefault(); const a = JSON.parse(edit.dataset.addressEdit); Addresses.openForm(a, () => back(a.id)); }
    });
    refreshIcons();
});
