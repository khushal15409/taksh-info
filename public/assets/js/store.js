/* TakshAllInOne - client-side store (cart, wishlist, orders, profile) persisted in localStorage */

const Store = (() => {
    const KEY = "taksh_v2";
    const listeners = [];

    const defaults = () => ({
        cart: [],                 // [{ id, qty }]
        wishlist: [],             // [productId]
        coupon: null,             // coupon code
        orders: [],
        bookings: [],
        recent: [],               // recently viewed product ids
        readNotifs: [],
        auth: { loggedIn: false },
        lang: "en",
        theme: "light",
        requests: [],             // partner / callback / development form submissions
        wallet: { points: 100, banks: [], txns: [{ id: "t0", type: "credit", title: "Welcome bonus", points: 100, at: Date.now() }] },
        pincode: "380061",
        city: "Ahmedabad",
        profile: { name: "Taksh User", phone: "+91 98765 43210", email: "taksh@allinone.com" },
        addresses: [{
            id: "a1", label: "Home", name: "Taksh User", phone: "+91 98765 43210",
            line: "I-201, Shivalik Residency, Ghatlodiya", city: "Ahmedabad", pincode: "380061", isDefault: true
        }]
    });

    let state = load();

    function load() {
        try {
            const saved = JSON.parse(localStorage.getItem(KEY));
            if (saved && typeof saved === "object") return Object.assign(defaults(), saved);
        } catch (e) { /* storage unavailable */ }
        return defaults();
    }

    function save() {
        try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) { /* ignore */ }
        listeners.forEach(fn => fn(state));
    }

    function subscribe(fn) { listeners.push(fn); }
    function product(id) { return PRODUCTS.find(p => p.id === Number(id)); }
    const uid = (prefix) => prefix + Math.random().toString(36).slice(2, 8);

    // ---------- Cart ----------
    const Cart = {
        qty: (id) => (state.cart.find(i => i.id === id) || {}).qty || 0,
        add(id, qty = 1) {
            const item = state.cart.find(i => i.id === id);
            if (item) item.qty = Math.min(20, item.qty + qty);
            else state.cart.push({ id, qty: Math.min(20, qty) });
            save();
        },
        setQty(id, qty) {
            if (qty <= 0) return this.remove(id);
            const item = state.cart.find(i => i.id === id);
            if (item) { item.qty = Math.min(20, qty); save(); }
        },
        remove(id) { state.cart = state.cart.filter(i => i.id !== id); save(); },
        clear() { state.cart = []; state.coupon = null; save(); },
        items() {
            return state.cart
                .map(i => ({ product: product(i.id), qty: i.qty }))
                .filter(i => i.product)
                .map(i => Object.assign(i, { line: i.product.price * i.qty }));
        },
        count() { return state.cart.reduce((n, i) => n + i.qty, 0); },
        couponDiscount(subtotal) {
            const c = COUPONS.find(c => c.code === state.coupon);
            if (!c || subtotal < c.min) return 0;
            if (c.type === "flat") return c.value;
            return Math.min(c.max || Infinity, Math.round(subtotal * c.value / 100));
        },
        totals() {
            const items = this.items();
            const subtotal = items.reduce((s, i) => s + i.line, 0);
            const mrpTotal = items.reduce((s, i) => s + i.product.mrp * i.qty, 0);
            const discount = this.couponDiscount(subtotal);
            const delivery = items.length === 0 || subtotal >= SITE.freeDeliveryMin ? 0 : SITE.deliveryFee;
            return {
                subtotal, mrpTotal, discount, delivery,
                savings: mrpTotal - subtotal + discount,
                total: Math.max(0, subtotal - discount + delivery),
                count: this.count()
            };
        },
        applyCoupon(code) {
            const c = COUPONS.find(c => c.code === String(code).trim().toUpperCase());
            if (!c) return { ok: false, msg: "Invalid coupon code" };
            const subtotal = this.totals().subtotal;
            if (subtotal < c.min) return { ok: false, msg: `Add items worth ₹${c.min - subtotal} more to use ${c.code}` };
            state.coupon = c.code;
            save();
            return { ok: true, msg: `Coupon ${c.code} applied` };
        },
        removeCoupon() { state.coupon = null; save(); }
    };

    // ---------- Wishlist ----------
    const Wishlist = {
        has: (id) => state.wishlist.includes(id),
        toggle(id) {
            const on = !this.has(id);
            state.wishlist = on ? [...state.wishlist, id] : state.wishlist.filter(w => w !== id);
            save();
            return on;
        },
        items: () => state.wishlist.map(product).filter(Boolean)
    };

    // ---------- Orders ----------
    const Orders = {
        place({ address, payment, note }) {
            const items = Cart.items().map(i => ({
                id: i.product.id, name: i.product.name, unit: i.product.unit, img: i.product.img,
                price: i.product.price, qty: i.qty
            }));
            const order = {
                id: "ORD" + Date.now().toString(36).toUpperCase().slice(-7),
                createdAt: Date.now(),
                items, address, payment, note: note || "",
                totals: Cart.totals(), coupon: state.coupon
            };
            state.orders.unshift(order);
            state.cart = [];
            state.coupon = null;
            save();
            return order;
        },
        get: (id) => state.orders.find(o => o.id === id)
    };

    // ---------- Misc ----------
    function trackRecent(id) {
        state.recent = [id, ...state.recent.filter(r => r !== id)].slice(0, 8);
        save();
    }

    return {
        get state() { return state; },
        save, subscribe, product, uid, trackRecent,
        Cart, Wishlist, Orders
    };
})();

const Cart = Store.Cart;
const Wishlist = Store.Wishlist;
