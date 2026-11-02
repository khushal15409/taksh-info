/* Account: profile, addresses, orders (tabs via URL hash) */
const TABS = [["profile", "My Profile", "user"], ["addresses", "Addresses", "map-pin"], ["orders", "My Orders", "package"]];
let tab = "profile";

ready(() => {
    const h = location.hash.replace("#", "");
    if (TABS.some(t => t[0] === h)) tab = h;
    qs("#profile-root").addEventListener("click", e => {
        const t = e.target.closest("[data-tab], [data-addr], [data-new-addr]");
        if (!t) return;
        if (t.dataset.tab) { tab = t.dataset.tab; history.replaceState(null, "", "#" + tab); render(); }
        if (t.dataset.newAddr !== undefined) openAddressModal(null, render);
        if (t.dataset.addr) {
            const [act, id] = t.dataset.addr.split(":");
            const list = Store.state.addresses, a = list.find(x => x.id === id);
            if (act === "edit") openAddressModal(a, render);
            if (act === "default") { list.forEach(x => x.isDefault = x.id === id); Store.save(); render(); toast("Default address updated"); }
            if (act === "delete") { Store.state.addresses = list.filter(x => x.id !== id); Store.save(); render(); toast("Address removed"); }
        }
    });
    qs("#profile-root").addEventListener("submit", e => {
        if (e.target.id !== "profile-form") return;
        e.preventDefault();
        const f = Object.fromEntries(new FormData(e.target));
        if (f.name.trim().length < 2) return toast("Enter your full name", "error");
        if (!/^\S+@\S+\.\S+$/.test(f.email)) return toast("Enter a valid email", "error");
        Object.assign(Store.state.profile, { name: f.name.trim(), phone: f.phone.trim(), email: f.email.trim() });
        Store.save();
        toast("✅ Profile Updated Successfully");
        render();
        renderHeader(); updateHeader();
    });
    render();
});

function render() {
    const u = Store.state.profile;
    const panels = {
        profile: `<h2 class="text-xl font-extrabold text-gray-900">Account Details</h2>
            <form id="profile-form" class="space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    <div><label class="text-gray-400 font-bold">Full Name</label><input name="name" value="${esc(u.name)}" class="field mt-1" required></div>
                    <div><label class="text-gray-400 font-bold">Phone Number</label><input name="phone" value="${esc(u.phone)}" class="field mt-1" required></div>
                    <div class="md:col-span-2"><label class="text-gray-400 font-bold">Email</label><input name="email" type="email" value="${esc(u.email)}" class="field mt-1" required></div>
                </div>
                <button class="bg-brand-500 text-white font-bold text-xs px-6 py-3 rounded-xl hover:bg-brand-600 transition">Save Changes</button>
            </form>`,
        addresses: `<div class="flex items-center justify-between"><h2 class="text-xl font-extrabold text-gray-900">Saved Addresses</h2>
                <button data-new-addr class="bg-brand-500 text-white font-bold text-xs px-4 py-2 rounded-xl hover:bg-brand-600">+ Add New</button></div>
            <div class="grid md:grid-cols-2 gap-4">${Store.state.addresses.map(a => `
                <div class="p-4 rounded-2xl border-2 ${a.isDefault ? "border-brand-500 bg-orange-50/40" : "border-gray-200"} text-xs space-y-1">
                    <div class="flex items-center gap-2"><span class="bg-brand-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">${esc(a.label)}</span>${a.isDefault ? `<span class="text-emerald-600 font-bold">Default</span>` : ""}</div>
                    <h4 class="font-bold text-sm text-gray-800 pt-1">${esc(a.name)}</h4>
                    <p class="text-gray-500">${esc(a.line)}, ${esc(a.city)} - ${esc(a.pincode)}</p><p class="text-gray-500">Phone: ${esc(a.phone)}</p>
                    <div class="flex gap-3 pt-2 font-bold"><button data-addr="edit:${a.id}" class="text-brand-600 hover:underline">Edit</button>
                        ${a.isDefault ? "" : `<button data-addr="default:${a.id}" class="text-gray-500 hover:underline">Set default</button><button data-addr="delete:${a.id}" class="text-red-500 hover:underline">Delete</button>`}</div></div>`).join("") || `<p class="text-sm text-gray-400">No saved addresses.</p>`}</div>`,
        orders: `<h2 class="text-xl font-extrabold text-gray-900">My Orders</h2>` + (Store.state.orders.length ? Store.state.orders.map(o => `
            <a href="${url("tracking", { id: o.id })}" class="flex flex-wrap items-center gap-4 p-4 rounded-2xl border border-gray-200 hover:border-brand-500 transition">
                <div class="flex -space-x-3">${o.items.slice(0, 3).map(i => `<img src="${ROOT + i.img}" alt="" class="w-12 h-12 rounded-xl bg-orange-50 border-2 border-white object-contain">`).join("")}</div>
                <div class="flex-1 min-w-[160px]"><div class="font-bold text-sm text-gray-800">#${o.id}</div><div class="text-[11px] text-gray-400">${new Date(o.createdAt).toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" })} • ${o.items.length} item(s)</div></div>
                <div class="font-extrabold text-brand-600">${money(o.totals.total)}</div><span class="text-xs font-bold text-brand-600">Track →</span></a>`).join("")
            : emptyState({ emoji: "📦", title: "No orders yet", text: "Your placed orders will show up here.", ctaLabel: "Start Shopping", ctaHref: url("listing") }))
    };
    qs("#profile-root").innerHTML = `
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <div class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-4 h-fit">
            <div class="flex items-center gap-3 border-b pb-4 border-gray-100">
                <div class="w-12 h-12 rounded-2xl bg-brand-500 text-white font-extrabold text-xl flex items-center justify-center">${esc(u.name.charAt(0).toUpperCase())}</div>
                <div class="min-w-0"><h3 class="font-extrabold text-sm text-gray-900 truncate">${esc(u.name)}</h3><p class="text-[11px] text-gray-400 truncate">${esc(u.email)}</p></div>
            </div>
            <div class="space-y-1 text-xs font-semibold">
                ${TABS.map(([k, l, ic]) => `<button data-tab="${k}" class="w-full text-left px-3 py-2 rounded-xl flex items-center gap-2 ${tab === k ? "bg-orange-50 text-brand-600 font-bold" : "text-gray-600 hover:bg-gray-50"}"><i data-lucide="${ic}" class="w-4 h-4"></i>${l}</button>`).join("")}
                <a href="${url("wishlist")}" class="w-full px-3 py-2 rounded-xl flex items-center gap-2 text-gray-600 hover:bg-gray-50"><i data-lucide="heart" class="w-4 h-4"></i>Wishlist</a>
                <a href="${url("notifications")}" class="w-full px-3 py-2 rounded-xl flex items-center gap-2 text-gray-600 hover:bg-gray-50"><i data-lucide="bell" class="w-4 h-4"></i>Notifications</a>
            </div>
        </div>
        <div class="lg:col-span-3 bg-white p-6 lg:p-8 rounded-3xl border border-orange-100 shadow-sm space-y-6">${panels[tab]}</div>
    </div>`;
    refreshIcons();
}
