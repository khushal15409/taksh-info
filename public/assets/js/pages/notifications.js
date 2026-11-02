/* Notifications hub */
let filter = "all";

ready(() => {
    qs("#notif-tabs").addEventListener("click", e => { const b = e.target.closest("[data-f]"); if (b) { filter = b.dataset.f; render(); } });
    qs("#mark-read").addEventListener("click", () => { Store.state.readNotifs = allNotifications().map(n => n.id); Store.save(); render(); toast("All notifications marked as read"); });
    render();
});

function render() {
    const read = Store.state.readNotifs;
    const list = allNotifications().filter(n => filter === "all" || n.kind === filter);
    qs("#notif-tabs").innerHTML = [["all", "All Notifications"], ["offer", "Offers & Diwali Sales"], ["order", "Order Updates"]]
        .map(([k, l]) => `<button data-f="${k}" class="pb-1 border-b-2 ${filter === k ? "text-brand-600 border-brand-500" : "text-gray-400 border-transparent hover:text-gray-600"}">${l}</button>`).join("");
    qs("#notif-list").innerHTML = list.length ? list.map(n => {
        const unread = !read.includes(n.id);
        const tag = n.href ? "a" : "div";
        return `<${tag} ${n.href ? `href="${n.href}"` : ""} class="p-4 rounded-2xl border flex items-start gap-4 ${unread ? "bg-orange-50/60 border-orange-100" : "bg-white border-gray-100"}">
            <div class="w-10 h-10 rounded-xl bg-brand-500 text-white flex items-center justify-center text-lg shrink-0">${n.icon}</div>
            <div class="flex-1"><h4 class="text-xs font-extrabold text-gray-900">${esc(n.title)}</h4><p class="text-xs text-gray-500">${esc(n.text)}</p><span class="text-[10px] text-gray-400">${esc(n.time)}</span></div>
            ${unread ? `<span class="w-2 h-2 rounded-full bg-brand-500 mt-1.5"></span>` : ""}</${tag}>`;
    }).join("") : `<p class="text-center text-sm text-gray-400 py-10">No notifications here yet.</p>`;
    updateHeader();
}
