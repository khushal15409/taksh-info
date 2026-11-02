/* Product catalogue + page routes (paths are relative to the site root) */
const products = [
    { id: 1, name: "Aashirvaad Shudh Chakki Atta 5 kg", price: 364, mrp: 425, sku: "RLS-AR-AASHATTA5", img: "assets/img/products/aashirvaad-atta.svg", tag: "14% OFF" },
    { id: 2, name: "India Gate Classic Basmati Rice 1 kg", price: 218, mrp: 260, sku: "RLS-AR-RICE1KG", img: "assets/img/products/india-gate-rice.svg", tag: "16% OFF" },
    { id: 3, name: "Fortune Sunlite Refined Sunflower Oil 1 L", price: 201, mrp: 240, sku: "RLS-AR-FORTUNEOIL", img: "assets/img/products/fortune-oil.svg", tag: "15% OFF" },
    { id: 4, name: "MDH Deggi Mirch Masala 100 g", price: 133, mrp: 150, sku: "RLS-AR-MDHMIRCH", img: "assets/img/products/mdh-deggi-mirch.svg", tag: "11% OFF" }
];

const ROUTES = {
    "home": "index.html",
    "listing": "pages/listing.html",
    "product-details": "pages/product-details.html",
    "cart": "pages/cart.html",
    "checkout": "pages/checkout.html",
    "tracking": "pages/tracking.html",
    "profile": "pages/profile.html",
    "notifications": "pages/notifications.html",
    "services": "pages/services.html",
    "empty-cart": "pages/empty-cart.html",
    // sections that had no screen in the original design point to the closest page
    "quick-home": "pages/listing.html",
    "offers": "pages/notifications.html",
    "courier": "pages/services.html",
    "help": "pages/services.html",
    "wishlist": "pages/listing.html"
};
