/* Account hub (server-rendered): the parts that need a browser - the address form with its map. The order and return lists are rendered
   by the server. Old deep links like /profile#orders are sent to /profile?tab=orders. */
ready(() => {
    const tabs = ["profile", "addresses", "orders", "returns"], h = location.hash.replace("#", "");
    if (!new URLSearchParams(location.search).has("tab") && tabs.includes(h)) { location.replace(url("profile", { tab: h })); return; }

    document.addEventListener("click", e => {
        const add = e.target.closest("[data-address-new]"), edit = e.target.closest("[data-address-edit]");
        if (add) Addresses.openForm(null, () => location.reload());
        if (edit) Addresses.openForm(JSON.parse(edit.dataset.addressEdit), () => location.reload());
    });
});

/* The order filters apply as soon as a select or date changes (the Filter button is the no-JS fallback). */
ready(() => {
    const f = qs("form[data-auto-submit]");
    if (f) f.addEventListener("change", () => f.submit());
});
