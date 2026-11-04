/* Product page (server-rendered): what needs the browser - gallery thumbnails, the tabs, quantity and "Add to Cart", the delivery-pincode
   check and posting a review. Everything else on the page is already in the HTML. */
ready(() => {
    const page = qs("#product-page");
    if (!page) return;
    const productId = Number(page.dataset.productId), variantId = Number(page.dataset.variantId), maxQty = Number(page.dataset.maxQty) || 1;
    let qty = 1;

    page.addEventListener("click", e => {
        const th = e.target.closest("[data-lp-thumb]");
        if (th) { const m = qs("#lp-main"); if (m) m.src = th.dataset.lpThumb; return; }

        const tb = e.target.closest("[data-lp-tab]");
        if (tb) {
            const i = tb.dataset.lpTab;
            qsa("[data-lp-tab]", page).forEach(b => { const on = b.dataset.lpTab === i; b.classList.toggle("border-brand-500", on); b.classList.toggle("text-brand-600", on); b.classList.toggle("border-transparent", !on); b.classList.toggle("text-gray-400", !on); });
            qsa("[data-lp-panel]", page).forEach(p => { p.hidden = p.dataset.lpPanel !== i; });
            return;
        }

        const qb = e.target.closest("[data-lp-q]");
        if (qb) { qty = Math.min(Math.max(1, qty + Number(qb.dataset.lpQ)), maxQty); qs("#lp-qty").textContent = qty; return; }

        const ab = e.target.closest("[data-lp-add]");
        if (ab) {
            ab.disabled = true;
            Cart.add(variantId, qty, page.dataset.delivery || "normal")
                .then(c => { const line = c.items.find(i => i.product_variant_id === variantId); toast(`🛒 Added ${qty} item(s) to cart`); qs("#lp-in-cart").innerHTML = `${line ? line.qty : qty} in your cart • <a class="underline" href="${url("cart")}">View cart</a>`; })
                .catch(err => toast(err.message, "error"))
                .finally(() => { ab.disabled = false; });
        }
    });

    // delivery check: starts from the pincode of the customer's default address (or the one they chose before)
    const pin = qs("#lp-pin-input"), out = qs("#lp-pin-result");
    pin.value = (qs("#hdr-location") || { dataset: {} }).dataset.pincode || Prefs.state.pincode || "";   // the delivery place shown in the header
    async function check(silent) {
        const value = pin.value.trim();
        if (!/^\d{4,10}$/.test(value)) { out.className = "text-[11px] font-medium text-red-600"; out.textContent = "Enter a valid pincode."; return; }
        try {
            const d = await Api.checkDelivery(productId, { pincode: value, product_variant_id: variantId });
            out.className = `text-[11px] font-medium ${d.is_deliverable ? "text-emerald-700" : "text-red-600"}`;
            out.textContent = (d.is_deliverable ? "✅ " : "⚠️ ") + d.message;
            if (!silent) toast(d.is_deliverable ? "✅ Delivery available at " + value : "Not deliverable at " + value, d.is_deliverable ? "success" : "error");
        } catch (err) { out.className = "text-[11px] font-medium text-red-600"; out.textContent = err.message; }
    }
    qs("#lp-pin").addEventListener("submit", e => { e.preventDefault(); check(false); });
    if (pin.value) check(true);

    // review: posted from the page, then the page reloads to show the new rating
    const rf = qs("#lp-rf");
    if (rf) rf.addEventListener("submit", async e => {
        e.preventDefault();
        const f = Object.fromEntries(new FormData(rf)), btn = rf.querySelector("button");
        btn.disabled = true;
        try { await Api.submitReview(productId, { rating: Number(f.rating), review_title: f.review_title, review_text: f.review_text }); toast("✅ Thank you for your rating"); setTimeout(() => location.reload(), 500); }
        catch (err) { toast(err.message, "error"); btn.disabled = false; }
    });
    refreshIcons();
});
