/* Shop page (server-rendered): the filter form submits itself when a filter changes (the Apply button is the no-JS fallback),
   the price label follows the slider, and the Filters button opens the form on phones. */
ready(() => {
    const form = qs("#filters");
    if (!form) return;
    const range = qs("#price-range", form), label = qs("#price-val", form), max = Number(range && range.dataset.max);

    form.addEventListener("change", e => {
        // ticking a category ticks its sub-categories (and the other way round), so one click filters the whole group
        const t = e.target;
        if (t.dataset && t.dataset.parent) qsa(`[data-child-of="${t.dataset.parent}"]`, form).forEach(c => { c.checked = t.checked; });
        if (t.dataset && t.dataset.childOf) {
            const kids = qsa(`[data-child-of="${t.dataset.childOf}"]`, form), parent = qs(`[data-parent="${t.dataset.childOf}"]`, form);
            if (parent && kids.every(c => c.checked)) parent.checked = true;
        }
        if (e.target.name === "category[]" || e.target.name === "max") {
            if (range && Number(range.value) >= max) range.disabled = true;   // "any price" is simply not in the URL
            form.submit();
        }
    });
    if (range) range.addEventListener("input", () => { label.textContent = Number(range.value) >= max || Number(range.value) === 0 ? "Any" : money(range.value); });
    if (range && Number(range.value) === 0) range.disabled = false;
    const toggle = qs("#filter-toggle");
    if (toggle) toggle.addEventListener("click", () => form.classList.toggle("hidden"));
    form.addEventListener("submit", () => { if (range && (Number(range.value) >= max || Number(range.value) === 0)) range.disabled = true; });
});
