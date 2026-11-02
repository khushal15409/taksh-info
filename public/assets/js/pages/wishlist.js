/* Wishlist */
ready(() => {
    qs("#add-all").addEventListener("click", () => {
        const items = Wishlist.items();
        items.forEach(p => Cart.add(p.id));
        toast(`🛒 ${items.length} item(s) added to cart`);
    });
    Store.subscribe(render);
    render();
});

function render() {
    const items = Wishlist.items();
    qs("#add-all").classList.toggle("hidden", !items.length);
    qs("#wish-count").textContent = `${items.length} saved item(s)`;
    qs("#wish-root").innerHTML = items.length
        ? productGrid(items, "grid-cols-2 md:grid-cols-3 lg:grid-cols-4")
        : emptyState({ emoji: "💝", title: "Your wishlist is empty", text: "Tap the heart on any product to save it here for later.", ctaLabel: "Explore Products", ctaHref: url("listing") });
    refreshIcons();
}
