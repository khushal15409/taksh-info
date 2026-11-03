/* Return form (server-rendered): the quantity list follows the chosen item, photos get a preview (up to 5, 5 MB each), and the button locks
   after one click so a request is not sent twice. The form itself is a normal multipart post. */
ready(() => {
    const form = qs("#return-form");
    if (!form) return;
    const qty = qs("#return-qty"), photos = qs("#return-photos"), previews = qs("#return-previews");
    const MAX = 5, MAX_BYTES = 5 * 1024 * 1024;
    let files = [];

    const fillQty = (keep) => {
        const max = Number(form.querySelector("[name=order_item_id]:checked").dataset.qty) || 1;
        qty.innerHTML = Array.from({ length: max }, (_, n) => `<option ${keep && Number(keep) === n + 1 ? "selected" : ""}>${n + 1}</option>`).join("");
    };
    fillQty(qty.dataset.old);
    form.addEventListener("change", e => { if (e.target.name === "order_item_id") fillQty(); });

    // the file input holds exactly the photos that are previewed (so removing one really removes it from the upload)
    const sync = () => {
        const dt = new DataTransfer();
        files.forEach(f => dt.items.add(f));
        photos.files = dt.files;
        previews.innerHTML = files.map((f, i) => `<div class="relative aspect-square rounded-xl overflow-hidden border border-gray-200"><img src="${URL.createObjectURL(f)}" alt="" class="w-full h-full object-cover"><button type="button" data-rm="${i}" class="absolute top-1 right-1 w-6 h-6 rounded-full bg-black/60 text-white text-xs" aria-label="Remove">✕</button></div>`).join("");
    };
    photos.addEventListener("change", () => {
        for (const f of photos.files) {
            if (files.length >= MAX) { toast(`Only ${MAX} photos allowed`, "error"); break; }
            if (f.size > MAX_BYTES) { toast(`${f.name} is larger than 5 MB`, "error"); continue; }
            if (!files.some(x => x.name === f.name && x.size === f.size)) files.push(f);
        }
        sync();
    });
    previews.addEventListener("click", e => { const b = e.target.closest("[data-rm]"); if (b) { files.splice(Number(b.dataset.rm), 1); sync(); } });

    let sent = false;
    form.addEventListener("submit", e => {
        if (sent) { e.preventDefault(); return; }
        if (form.reason.value.trim().length < 10) { e.preventDefault(); toast("Please describe the problem (at least 10 characters)", "error"); return; }
        sent = true;
        const b = qs("#return-submit"); b.disabled = true; b.textContent = "Submitting…";
    });
    refreshIcons();
});
