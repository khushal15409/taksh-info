/* Home page (server-rendered): the category strip arrows and gentle auto-slide, the "Google Play soon" badge, and the newsletter form. */
ready(() => {
    const track = qs("#categories-grid");
    if (track) {
        const step = () => Math.max(160, track.clientWidth * 0.8);
        const atEnd = () => track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
        const next = () => track.scrollTo({ left: atEnd() ? 0 : track.scrollLeft + step(), behavior: "smooth" });
        qsa("[data-cat-nav]").forEach(b => b.addEventListener("click", () => track.scrollBy({ left: b.dataset.catNav === "next" ? step() : -step(), behavior: "smooth" })));
        if (!window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            let timer = setInterval(next, 3500);
            const pause = () => clearInterval(timer), resume = () => { clearInterval(timer); timer = setInterval(next, 3500); };
            ["mouseenter", "touchstart", "focusin"].forEach(ev => track.addEventListener(ev, pause, { passive: true }));
            ["mouseleave", "touchend", "focusout"].forEach(ev => track.addEventListener(ev, resume, { passive: true }));
        }
    }
    qsa("[data-play-badge]").forEach(el => { el.innerHTML = playBadge(); });
    const form = qs("#newsletter-form");
    if (form) form.addEventListener("submit", e => {
        e.preventDefault();
        const email = e.target.email.value.trim();
        if (!/^\S+@\S+\.\S+$/.test(email)) return toast("Enter a valid email address", "error");
        e.target.reset();
        toast("🎉 Subscribed! Festive offers are on the way.");
    });
    refreshIcons();
});
