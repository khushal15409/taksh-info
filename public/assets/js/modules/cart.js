/* Cart page (server-rendered): every button is a real form, so the cart works without JavaScript. With it, a change is sent in the
   background and only the cart area (#cart-fragment) is swapped for the server's new HTML - no full page reload - and the header's
   cart count and total follow the server's numbers. Anything unexpected falls back to the normal form submit. */
ready(() => {
    const frag = () => qs("#cart-fragment");
    if (!frag()) return;

    document.addEventListener("submit", async e => {
        const form = e.target.closest("[data-cart-form]");
        if (!form || e.defaultPrevented) return;
        e.preventDefault();
        const btn = form.querySelector("button");
        if (btn) btn.disabled = true;
        try {
            const res = await fetch(form.action, { method: "POST", body: new FormData(form), credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest", "Accept": "text/html" } });
            const doc = new DOMParser().parseFromString(await res.text(), "text/html");
            const next = doc.querySelector("#cart-fragment");
            if (!res.ok || !next) throw new Error("unexpected answer");

            frag().innerHTML = next.innerHTML;
            history.replaceState(null, "", res.url);
            const b = doc.body.dataset;
            if (typeof Cart !== "undefined") Cart.seed(Number(b.cartCount) || 0, Number(b.cartTotal) || 0);   // header numbers come from the server
            if (typeof updateHeader === "function") updateHeader();
            const flash = doc.getElementById("flash-data");
            if (flash) toast(flash.dataset.message, flash.dataset.type === "error" ? "error" : undefined);
            refreshIcons();
        } catch (err) {
            form.submit();   // let the browser do it the ordinary way
        }
    });
});
