/* Enquiry forms and the help page (server-rendered): a form's button locks after one click so an enquiry is not sent twice. */
ready(() => {
    document.addEventListener("submit", e => {
        const f = e.target.closest("form[data-lock]");
        if (!f) return;
        if (f.dataset.sent) { e.preventDefault(); return; }
        f.dataset.sent = "1";
        const b = f.querySelector("button:last-of-type"); if (b) b.disabled = true;
    });
    refreshIcons();
});
