/* Wishlist page (server-rendered): un-hearting a card removes it from the page and the header count follows. (The Remove link under each
   card is the same thing as a normal form, for browsers without JavaScript.) */
ready(() => {
    const grid = qs("#wish-grid");
    if (!grid) return;
    grid.addEventListener("click", e => {
        const heart = e.target.closest("[data-wish]");
        if (!heart) return;
        const card = heart.closest("[data-wish-card]");
        setTimeout(() => {
            if (!Wishlist.has(heart.dataset.liveWish)) {
                card.remove();
                if (!qs("[data-wish-card]")) location.reload();   // the last one: show the empty-wishlist message
            }
        }, 600);
    });
});
