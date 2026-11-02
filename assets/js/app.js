/* TakshAllInOne - shared app logic (loaded on every page) */

// "" on index.html, "../" on pages/*.html (set via <body data-root>)
const ROOT = document.body.dataset.root || "";
const PAGE = document.body.dataset.page || "home";

// ---------- State (persisted across pages) ----------
const STORE_KEY = "taksh_state_v1";
let cart = [{ id: 1, qty: 1 }];
let activeDiscount = 0;

function loadState() {
    try {
        const saved = JSON.parse(localStorage.getItem(STORE_KEY));
        if (saved) {
            cart = saved.cart || [];
            activeDiscount = saved.activeDiscount || 0;
        }
    } catch (e) { /* storage unavailable - keep defaults */ }
}

function saveState() {
    try { localStorage.setItem(STORE_KEY, JSON.stringify({ cart, activeDiscount })); } catch (e) { /* ignore */ }
}

function productById(id) { return products.find(p => p.id === id); }
function imgSrc(p) { return ROOT + p.img; }

// ---------- Navigation ----------
function switchView(viewId) {
    const target = ROUTES[viewId];
    if (target) window.location.href = ROOT + target;
}

// ---------- Rendering ----------
function renderFeaturedProducts() {
    const container = document.getElementById('home-featured-grid');
    if (!container) return;
    container.innerHTML = products.map(p => `
        <div class="bg-white rounded-3xl p-4 border border-orange-100 hover:shadow-card-hover transition space-y-3 relative group">
            <span class="absolute top-3 left-3 bg-brand-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">${p.tag}</span>
            <div class="w-full h-40 bg-orange-50/50 rounded-2xl p-2 flex items-center justify-center cursor-pointer" onclick="switchView('product-details')">
                <img src="${imgSrc(p)}" alt="${p.name}" class="h-32 object-contain group-hover:scale-105 transition">
            </div>
            <div>
                <h4 class="font-bold text-xs text-gray-800 line-clamp-1">${p.name}</h4>
                <p class="text-[10px] text-gray-400 mt-0.5">${p.sku}</p>
            </div>
            <div class="flex items-center justify-between pt-1">
                <div>
                    <span class="font-extrabold text-sm text-brand-600">₹${p.price}</span>
                    <span class="text-[10px] text-gray-400 line-through ml-1">₹${p.mrp}</span>
                </div>
                <button onclick="addToCart(${p.id})" class="bg-orange-100 hover:bg-brand-500 hover:text-white text-brand-600 p-2 rounded-xl transition">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
    `).join('');
    lucide.createIcons();
}

function renderProductListing() {
    const container = document.getElementById('product-listing-grid');
    if (!container) return;
    container.innerHTML = products.map(p => `
        <div class="bg-white rounded-3xl p-4 border border-orange-100 hover:shadow-md transition space-y-3 relative">
            <div class="w-full h-44 bg-orange-50/30 rounded-2xl flex items-center justify-center cursor-pointer" onclick="switchView('product-details')">
                <img src="${imgSrc(p)}" alt="${p.name}" class="h-36 object-contain">
            </div>
            <h4 class="font-bold text-xs text-gray-800">${p.name}</h4>
            <div class="flex justify-between items-center">
                <span class="font-extrabold text-base text-brand-600">₹${p.price}</span>
                <button onclick="addToCart(${p.id})" class="bg-brand-500 text-white px-3 py-1.5 rounded-xl text-xs font-bold hover:bg-brand-600">Add +</button>
            </div>
        </div>
    `).join('');
}

function setText(id, text) {
    const el = document.getElementById(id);
    if (el) el.innerText = text;
}

function renderCart() {
    if (cart.length === 0 && (PAGE === 'cart' || PAGE === 'checkout')) {
        switchView('empty-cart');
        return;
    }

    let subtotal = 0;
    const lineItems = cart.map(item => {
        const product = productById(item.id);
        subtotal += product.price * item.qty;
        return { item, product };
    });

    const container = document.getElementById('cart-items-container');
    if (container) {
        container.innerHTML = lineItems.map(({ item, product }) => `
            <div class="bg-white p-4 rounded-3xl border border-orange-100 flex items-center space-x-4">
                <img src="${imgSrc(product)}" alt="${product.name}" class="w-16 h-16 rounded-2xl object-contain bg-orange-50/50 p-1">
                <div class="flex-1">
                    <h4 class="font-bold text-xs text-gray-800">${product.name}</h4>
                    <p class="text-[10px] text-gray-400">SKU: ${product.sku}</p>
                    <span class="text-xs font-extrabold text-brand-600 mt-1 inline-block">₹${product.price}</span>
                </div>
                <div class="flex items-center space-x-2 border rounded-xl px-2 py-1">
                    <button onclick="updateCartQty(${item.id}, -1)" class="text-brand-600 font-bold">-</button>
                    <span class="text-xs font-bold w-4 text-center">${item.qty}</span>
                    <button onclick="updateCartQty(${item.id}, 1)" class="text-brand-600 font-bold">+</button>
                </div>
            </div>
        `).join('');
    }

    const grandTotal = Math.max(0, subtotal - activeDiscount);
    setText('cart-subtotal', `₹${subtotal.toFixed(2)}`);
    setText('cart-total', `₹${grandTotal.toFixed(2)}`);
    setText('checkout-subtotal', `₹${subtotal.toFixed(2)}`);
    setText('checkout-total', `₹${grandTotal.toFixed(2)}`);
    setText('header-cart-total', `₹${grandTotal.toFixed(2)}`);
    setText('header-cart-count', cart.length);
    setText('cart-item-count', cart.length);

    const discountRow = document.getElementById('discount-row');
    if (discountRow) discountRow.classList.toggle('hidden', activeDiscount === 0);
}

// ---------- Cart actions ----------
function addToCart(productId) {
    const existing = cart.find(item => item.id === productId);
    if (existing) existing.qty++;
    else cart.push({ id: productId, qty: 1 });
    saveState();
    renderCart();
    showToast("🛒 Item added to your cart!");
}

function updateCartQty(productId, delta) {
    const item = cart.find(i => i.id === productId);
    if (item) {
        item.qty += delta;
        if (item.qty <= 0) cart = cart.filter(i => i.id !== productId);
    }
    saveState();
    renderCart();
}

function applyCoupon() {
    const input = document.getElementById('coupon-code-input').value.trim();
    if (input === 'TAKSHDIWALI') {
        activeDiscount = 50;
        saveState();
        renderCart();
        showToast("🎉 Festive Coupon TAKSHDIWALI Applied (-₹50)");
    } else {
        showToast("❌ Invalid Coupon Code");
    }
}

// ---------- Product details ----------
function updateQty(delta) {
    const el = document.getElementById('detail-qty');
    el.innerText = Math.max(1, parseInt(el.innerText) + delta);
}

function addToCartDetailed() {
    const qty = parseInt(document.getElementById('detail-qty').innerText);
    const item = cart.find(i => i.id === 1);
    if (item) item.qty += qty;
    else cart.push({ id: 1, qty });
    saveState();
    renderCart();
    showToast(`🛒 Added ${qty} item(s) to Cart!`);
}

function buyNow() {
    addToCartDetailed();
    switchView('checkout');
}

// ---------- Checkout ----------
function placeOrder() {
    showToast("🎉 Order Confirmed Successfully!");
    setTimeout(() => switchView('tracking'), 800);
}

// ---------- Search autocomplete ----------
function handleSearch(val) {
    const popup = document.getElementById('search-results-popup');
    const list = document.getElementById('search-results-list');
    if (val.trim().length === 0) {
        popup.classList.add('hidden');
        return;
    }

    const matches = products.filter(p => p.name.toLowerCase().includes(val.toLowerCase()));
    if (matches.length > 0) {
        list.innerHTML = matches.map(m => `
            <div onclick="switchView('product-details')" class="p-2.5 hover:bg-orange-50 rounded-xl cursor-pointer flex items-center space-x-3">
                <img src="${imgSrc(m)}" alt="${m.name}" class="w-8 h-8 rounded-lg object-contain">
                <div>
                    <div class="text-xs font-bold text-gray-800">${m.name}</div>
                    <div class="text-[10px] text-brand-600 font-semibold">₹${m.price}</div>
                </div>
            </div>
        `).join('');
    } else {
        const msg = document.createElement('div');
        msg.className = 'p-3 text-xs text-gray-400';
        msg.textContent = `No products found matching "${val}"`;
        list.replaceChildren(msg);
    }
    popup.classList.remove('hidden');
}

// ---------- UI helpers ----------
function showToast(message) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = "bg-gray-900 text-yellow-400 text-xs font-bold px-4 py-3 rounded-2xl shadow-2xl flex items-center space-x-2 border border-yellow-500/30 animate-bounce pointer-events-auto";
    toast.innerHTML = `<span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('hidden');
    else showToast("ℹ️ Coming soon");
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('hidden');
}

function resetFilters() {
    document.querySelectorAll('aside input[type="checkbox"]').forEach((cb, i) => cb.checked = i === 0);
    const range = document.querySelector('aside input[type="range"]');
    if (range) { range.value = 600; setText('price-val', 600); }
}

// ---------- Init ----------
document.addEventListener('DOMContentLoaded', () => {
    loadState();
    renderFeaturedProducts();
    renderProductListing();
    renderCart();
    lucide.createIcons();
});
