/* The header's live bits, drawn from numbers the server worked out for the page (data-cart-count, data-cart-total, data-wish-ids on <body>):
   the cart count and total, the hearts of saved products, and the notification dot. Also the "Add" button on product cards and the heart
   on product cards and pages. Every cart change returns the changed cart, so the header never needs a separate fetch. */

const Cart = (() => {
    let cart = null;
    let seeded = { count: Number(document.body.dataset.cartCount) || 0, total: Number(document.body.dataset.cartTotal) || 0 };

    const count = () => cart ? cart.items.reduce((n, i) => n + i.qty, 0) : seeded.count;
    const totals = () => ({ count: count(), total: cart ? cart.total_with_delivery : seeded.total });
    const seed = (n, total) => { seeded = { count: n, total }; cart = null; };

    // The header counts both carts (Standard and Quick); an add answers with the cart it went into, so the header is moved by what was added
    async function add(variantId, qty = 1, deliveryType = "normal") {
        const d = await Api.cartAdd(variantId, qty, deliveryType);
        const line = d.cart && d.cart.items.find(i => i.product_variant_id === variantId);
        seeded = { count: seeded.count + qty, total: seeded.total + (line ? Number(line.price) * qty : 0) };
        cart = null;
        updateHeader();
        return d.cart;
    }

    // "Add" on a product card (a product with a single option)
    document.addEventListener("click", async e => {
        const b = e.target.closest("[data-add]");
        if (!b) return;
        e.preventDefault();
        b.disabled = true;
        try { await add(Number(b.dataset.add), 1, b.dataset.delivery || "normal"); toast("🛒 Added to cart"); }
        catch (err) { toast(err.message, "error"); }
        b.disabled = false;
    });

    // "Buy Now": straight to a checkout of THIS product only - the cart is not touched and its items are not part of the order
    function buyNow(variantId, qty) {
        location.href = url("checkout", { buy: variantId, qty });
        return Promise.resolve();
    }

    return { count, totals, seed, add, buyNow };
})();

const Wishlist = (() => {
    let saved = new Set(JSON.parse(document.body.dataset.wishIds || "[]").map(Number));

    const has = (id) => saved.has(Number(id));
    const count = () => saved.size;

    function paint() {
        qsa("[data-wish]").forEach(b => b.classList.toggle("is-on", saved.has(Number(b.dataset.wish))));
        updateHeader();
    }

    function needLogin() {
        toast("Please log in to save products to your wishlist", "error");
        setTimeout(() => { location.href = url("login") + "?next=" + encodeURIComponent(location.pathname + location.search); }, 900);
    }

    async function toggle(productId) {
        if (!Auth.loggedIn) return needLogin();
        productId = Number(productId);
        const wasOn = has(productId);
        try {
            if (wasOn) { await Api.wishlistRemove(productId); saved.delete(productId); }
            else { await Api.wishlistAdd(productId); saved.add(productId); }
            toast(wasOn ? "Removed from wishlist" : "❤️ Added to wishlist");
        } catch (e) { toast(e.message, "error"); }
        paint();
    }

    document.addEventListener("click", e => {
        const b = e.target.closest("[data-wish]");
        if (!b) return;
        e.preventDefault();
        toggle(b.dataset.wish);
    });

    return { has, count, toggle, paint };
})();

const NotifBadge = (() => {
    let unread = 0;
    const count = () => unread;

    async function refresh() {
        if (!Auth.loggedIn) return;
        try { unread = (await Api.notifUnread()).unread_count; } catch (e) { return; }
        updateHeader();
    }
    return { count, refresh };
})();
