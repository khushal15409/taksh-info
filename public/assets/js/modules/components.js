/* Behaviour for the server-rendered components: <x-modal> (data-modal-open / data-modal-close, Esc). Nothing else is needed on the page. */
(function () {
    const byId = (id) => document.getElementById(id);
    const open = (m) => { if (m) { m.hidden = false; document.body.classList.add("overflow-hidden"); const f = m.querySelector("input,select,textarea,button:not([data-modal-close])"); if (f) f.focus(); } };
    const close = (m) => { if (m) { m.hidden = true; if (!document.querySelector("[data-modal]:not([hidden])")) document.body.classList.remove("overflow-hidden"); } };

    document.addEventListener("click", function (e) {
        const o = e.target.closest("[data-modal-open]");
        if (o) { e.preventDefault(); open(byId(o.dataset.modalOpen)); return; }
        const c = e.target.closest("[data-modal-close]");
        if (c) close(c.closest("[data-modal]"));
    });
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") document.querySelectorAll("[data-modal]:not([hidden])").forEach(close);
    });
})();
