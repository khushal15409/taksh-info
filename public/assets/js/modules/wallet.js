/* Wallet (server-rendered): both forms are real posts; this only locks a form's button after one click so money is not requested twice. */
ready(() => {
    document.addEventListener("submit", e => {
        const f = e.target.closest("form[data-lock]");
        if (!f) return;
        if (f.dataset.sent) { e.preventDefault(); return; }
        f.dataset.sent = "1";
        const b = f.querySelector("button:last-of-type"); if (b) { b.disabled = true; b.textContent = "Please wait…"; }
    });
    refreshIcons();
});
