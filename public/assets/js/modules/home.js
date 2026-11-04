/* Home page (server-rendered): the hero slider (auto-slides, dots), the category strip auto-slide, the "Google Play soon" badge, and the newsletter form. */
ready(() => {
    const slider = qs("#home-slider");
    if (slider) {
        const slides = qsa(".hero-slide", slider), dots = qsa("[data-slide]", slider);
        let at = 0, timer = null;
        const show = i => {
            at = (i + slides.length) % slides.length;
            slides.forEach((s, n) => s.classList.toggle("is-active", n === at));
            dots.forEach((d, n) => d.classList.toggle("is-active", n === at));
        };
        const start = () => { stop(); if (slides.length > 1 && !window.matchMedia("(prefers-reduced-motion: reduce)").matches) timer = setInterval(() => show(at + 1), 4500); };
        const stop = () => { if (timer) clearInterval(timer); timer = null; };
        dots.forEach(d => d.addEventListener("click", () => { show(Number(d.dataset.slide)); start(); }));
        ["mouseenter", "touchstart", "focusin"].forEach(ev => slider.addEventListener(ev, stop, { passive: true }));
        ["mouseleave", "touchend", "focusout"].forEach(ev => slider.addEventListener(ev, start, { passive: true }));
        start();
    }
    const track = qs("#categories-grid");
    if (track) {
        const step = () => Math.max(160, track.clientWidth * 0.8);
        const atEnd = () => track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
        const next = () => track.scrollTo({ left: atEnd() ? 0 : track.scrollLeft + step(), behavior: "smooth" });
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
