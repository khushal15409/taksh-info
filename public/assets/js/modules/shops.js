/* Shops pages.
   - Search types as you go: after a short pause the search form is POSTed (the words never go into a URL), and the page's results
     (#live-area) and count (#live-count) are swapped for the answer; the address bar gets the clean ?f=<token> URL, so paging, filters
     and a refresh keep working (the button / Enter is the no-JS fallback).
   - Share on a shop's page uses the phone's share sheet, or copies the link (the link opens that shop's products page). */
ready(() => {
    const filters = qs("#filters");
    if (filters) {
        const toggle = qs("#filter-toggle");
        if (toggle) toggle.addEventListener("click", () => filters.classList.toggle("hidden"));
        const price = qs("[data-price]", filters);
        if (!price) {
            filters.addEventListener("change", () => filters.submit());   // /shops has no price filter
        } else {
            const top = Number(price.dataset.max), rMin = qs("[data-r=min]", price), rMax = qs("[data-r=max]", price), nMin = qs("[data-n=min]", price), nMax = qs("[data-n=max]", price), fill = qs(".dual-fill", price);
            const paint = () => { fill.style.left = (rMin.value / top * 100) + "%"; fill.style.right = (100 - rMax.value / top * 100) + "%"; };
            const clamp = v => Math.min(top, Math.max(0, Number(v) || 0));
            let timer = null;
            const apply = () => { clearTimeout(timer); timer = setTimeout(() => filters.requestSubmit(), 600); };
            const sync = (from) => {
                let lo = clamp(from === "n" ? nMin.value : rMin.value), hi = clamp(from === "n" ? nMax.value : rMax.value);
                if (lo > hi) { if (price.dataset.last === "min") lo = hi; else hi = lo; }
                rMin.value = nMin.value = lo; rMax.value = nMax.value = hi; paint();
            };
            rMin.addEventListener("input", () => { price.dataset.last = "min"; sync("r"); });
            rMax.addEventListener("input", () => { price.dataset.last = "max"; sync("r"); });
            [rMin, rMax].forEach(r => r.addEventListener("change", apply));
            [nMin, nMax].forEach(n => { n.addEventListener("input", () => sync("n")); n.addEventListener("change", apply); });
            paint();
            // the default range ("any price") is simply not in the URL
            filters.addEventListener("submit", () => { if (Number(nMin.value) <= 0) nMin.disabled = true; if (Number(nMax.value) >= top) nMax.disabled = true; });
            filters.addEventListener("change", e => {
                if (e.target.name === "category[]") filters.requestSubmit();
                if (e.target.hasAttribute("data-all-products") && e.target.checked) qsa("[name='category[]']", filters).forEach(c => { c.checked = false; }), filters.requestSubmit();
                if (e.target.hasAttribute("data-all-products") && !e.target.checked) e.target.checked = true;
            });
        }
    }

    const form = qs("[data-live-search]");
    if (form) {
        const input = qs("input[name=q]", form);
        let timer = null, seq = 0, ctrl = null;
        const run = async () => {
            const mine = ++seq;
            if (ctrl) ctrl.abort();   // an older, slower answer must never overwrite a newer one
            ctrl = new AbortController();
            const area0 = qs("#live-area");
            if (area0) { area0.style.opacity = ".45"; area0.style.transition = "opacity .15s"; area0.setAttribute("aria-busy", "true"); }
            try {
                // POST: the words are not put in a URL. The server answers with a redirect to the page's ?f=<token> URL, which fetch follows.
                const res = await fetch(form.action, { method: "POST", body: new FormData(form), headers: { "X-Requested-With": "XMLHttpRequest" }, credentials: "same-origin", signal: ctrl.signal });
                if (!res.ok || mine !== seq) return;
                const doc = new DOMParser().parseFromString(await res.text(), "text/html");
                const area = qs("#live-area"), fresh = doc.querySelector("#live-area");
                if (!area || !fresh) return;
                area.innerHTML = fresh.innerHTML;
                const count = qs("#live-count"), freshCount = doc.querySelector("#live-count");
                if (count && freshCount) count.textContent = freshCount.textContent;
                if (filters) { const hidden = qs("input[name=q]", filters); if (hidden) hidden.value = input.value; }
                history.replaceState(null, "", res.url);   // the clean ?f=<token> address (or none when the box is empty)
                if (window.lucide && lucide.createIcons) lucide.createIcons();
            } catch (e) {
                if (e && e.name !== "AbortError") console.error("shop search failed", e);   // the typed text stays, Enter still submits the form
            } finally {
                if (mine === seq && area0) { area0.style.opacity = ""; area0.removeAttribute("aria-busy"); }
            }
        };
        input.addEventListener("input", () => { clearTimeout(timer); timer = setTimeout(run, 300); });
        form.addEventListener("submit", e => { e.preventDefault(); clearTimeout(timer); run(); });
    }

    // grid / list view of the products (kept in the browser; the results area survives live search because only its contents are swapped)
    const area = qs("#live-area.shop-products");
    if (area) {
        const btns = qsa("[data-view]");
        const setView = v => {
            area.classList.toggle("is-list", v === "list");
            btns.forEach(b => b.setAttribute("aria-pressed", String(b.dataset.view === v)));
            try { localStorage.setItem("taksh_shop_view", v); } catch (e) { /* private mode: not remembered */ }
        };
        let saved = "grid";
        try { saved = localStorage.getItem("taksh_shop_view") === "list" ? "list" : "grid"; } catch (e) { /* default */ }
        setView(saved);
        btns.forEach(b => b.addEventListener("click", () => setView(b.dataset.view)));
    }

    const share = qs("#shop-share");
    if (!share) return;
    const label = qs("[data-label]", share);
    share.addEventListener("click", async () => {
        const url = share.dataset.url, title = share.dataset.title;
        if (navigator.share) {
            try { await navigator.share({ title, text: "Shop " + title + " on Taksh", url }); return; } catch (e) { if (e && e.name === "AbortError") return; }
        }
        try { await navigator.clipboard.writeText(url); }
        catch (e) { const t = document.createElement("textarea"); t.value = url; document.body.appendChild(t); t.select(); document.execCommand("copy"); t.remove(); }
        if (label) { label.textContent = "Link copied"; setTimeout(() => { label.textContent = "Share"; }, 2000); }
        if (typeof toast === "function") toast("Shop link copied", "success");
    });
});
