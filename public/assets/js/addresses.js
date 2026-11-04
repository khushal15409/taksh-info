/* The customer's saved delivery addresses (GET /address/addresses, POST /address/add, PUT /address/{id} ...) with a map picker (Leaflet + OpenStreetMap,
   place search and reverse lookup through our own /location endpoints). Used by the address form on the account, checkout and booking pages. */

const Addresses = (() => {
    let data = { default_address: null, addresses: [] };
    const LEAFLET = "https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/";
    const TYPES = [["home", "Home"], ["office", "Office"], ["other", "Other"]];
    const DEFAULT_CENTER = [22.9734, 78.6569];      // India

    const all = () => [data.default_address, ...data.addresses].filter(Boolean);
    const line = (a) => [a.address_line_1, a.address_line_2, a.landmark && `Near ${a.landmark}`, a.area, a.city, a.state].filter(Boolean).join(", ") + (a.pincode ? ` - ${a.pincode}` : "");

    // The default address drives the "Deliver to" chip and the pincode the product pages start from.
    function applyDefault() {
        const d = data.default_address;
        if (d) { Prefs.state.pincode = d.pincode; Prefs.state.city = d.city || Prefs.state.city; Prefs.save(); }
    }

    async function refresh() {
        if (!Auth.loggedIn) return;
        try { data = await Api.addresses(); applyDefault(); } catch (e) { /* keep what we have */ }
    }

    // ------------------------------------------------------------ list (profile > Addresses)

    async function mount(host) {
        if (!host) return;
        host.innerHTML = `<div class="text-sm text-gray-400">Loading your addresses…</div>`;
        await refresh();
        draw(host);
        host.onclick = async e => {
            const b = e.target.closest("[data-la]");
            if (!b) return;
            const [act, id] = b.dataset.la.split(":");
            const a = all().find(x => String(x.id) === id);
            try {
                if (act === "new") openForm(null, () => { mount(host); });
                if (act === "edit") openForm(a, () => { mount(host); });
                if (act === "default") { await Api.addressDefault(Number(id)); toast("Default address updated"); document.cookie = "taksh_loc=; max-age=0; path=/"; location.reload(); return; }   // the saved default is the delivery place again
                if (act === "delete" && confirm("Delete this address?")) { await Api.addressDelete(Number(id)); toast("Address removed"); await mount(host); updateHeader(); }
            } catch (err) { toast(err.message, "error"); }
        };
    }

    function card(a) {
        return `<div class="p-4 rounded-2xl border-2 ${a.is_default ? "border-brand-500 bg-orange-50/40" : "border-gray-200"} text-xs space-y-1">
            <div class="flex items-center gap-2"><span class="bg-brand-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">${esc(a.address_type)}</span>${a.is_default ? `<span class="text-emerald-600 font-bold">Default</span>` : ""}</div>
            <h4 class="font-bold text-sm text-gray-800 pt-1">${esc(a.name)}</h4>
            <p class="text-gray-500">${esc(line(a))}</p><p class="text-gray-500">Phone: ${esc(a.mobile)}</p>
            <div class="flex gap-3 pt-2 font-bold"><button data-la="edit:${a.id}" class="text-brand-600 hover:underline">Edit</button>
                ${a.is_default ? "" : `<button data-la="default:${a.id}" class="text-gray-500 hover:underline">Set default</button>`}
                ${all().length > 1 ? `<button data-la="delete:${a.id}" class="text-red-500 hover:underline">Delete</button>` : ""}</div></div>`;
    }

    function draw(host) {
        const list = all();
        host.innerHTML = `<div class="flex items-center justify-between"><h2 class="text-xl font-extrabold text-gray-900">Saved Addresses</h2>
            <button data-la="new:0" class="bg-brand-500 text-white font-bold text-xs px-4 py-2 rounded-xl hover:bg-brand-600">+ Add New</button></div>
            <div class="grid md:grid-cols-2 gap-4 mt-6">${list.map(card).join("") || `<p class="text-sm text-gray-400">No saved addresses yet.</p>`}</div>`;
    }

    // ------------------------------------------------------------ add / edit form with the map

    let leafletReady = null;
    function loadLeaflet() {
        if (window.L) return Promise.resolve();
        return leafletReady ||= new Promise((resolve, reject) => {
            const css = document.createElement("link"); css.rel = "stylesheet"; css.href = LEAFLET + "leaflet.min.css"; document.head.appendChild(css);
            const js = document.createElement("script"); js.src = LEAFLET + "leaflet.min.js"; js.onload = resolve; js.onerror = () => { leafletReady = null; reject(new Error("Map could not be loaded")); };
            document.head.appendChild(js);
        });
    }

    function openForm(existing, onSaved) {
        const a = existing || { name: Auth.user ? Auth.user.name : "", mobile: Auth.user ? Auth.user.mobile : "", address_type: "home", is_default: !all().length };
        const m = Modal.open({
            title: existing ? "Edit address" : "Add new address", wide: true,
            body: `<form id="la-form" class="grid grid-cols-2 gap-4 text-xs" novalidate>
                <div class="col-span-2"><div class="flex gap-2 mb-2"><input id="la-q" class="field" placeholder="Search your area, street or landmark…" autocomplete="off">
                        <button type="button" id="la-search" class="bg-brand-500 text-white font-bold px-4 rounded-xl shrink-0">Search</button>
                        <button type="button" id="la-locate" class="border-2 border-brand-500 text-brand-600 font-bold px-3 rounded-xl shrink-0" title="Use my current location">📍</button></div>
                    <div id="la-results" class="hidden bg-white border border-gray-100 rounded-xl shadow mb-2 max-h-40 overflow-y-auto"></div>
                    <div id="la-map" class="w-full h-52 rounded-2xl border border-orange-100 bg-orange-50/40 flex items-center justify-center text-gray-400">Loading map…</div>
                    <p class="text-[11px] text-gray-400 mt-1">Tap the map or drag the pin to the exact spot; the address below is filled in for you.</p></div>
                <div class="col-span-2"><label class="font-bold text-gray-500">Save as</label>
                    <div class="flex gap-2 mt-1">${TYPES.map(([k, l]) => `<label class="cursor-pointer"><input type="radio" name="address_type" value="${k}" class="peer sr-only" ${a.address_type === k ? "checked" : ""}><span class="block px-4 py-2 rounded-xl border border-gray-200 font-bold text-gray-600 peer-checked:bg-brand-500 peer-checked:text-white peer-checked:border-brand-500">${l}</span></label>`).join("")}</div></div>
                <div><label class="font-bold text-gray-500">Full name</label><input name="name" value="${esc(a.name || "")}" class="field mt-1" required></div>
                <div><label class="font-bold text-gray-500">Mobile</label><input name="mobile" value="${esc(a.mobile || "")}" inputmode="numeric" maxlength="14" class="field mt-1" required></div>
                <div class="col-span-2"><label class="font-bold text-gray-500">House / flat, street</label><input name="address_line_1" value="${esc(a.address_line_1 || "")}" class="field mt-1" placeholder="House no., building, street" required></div>
                <div><label class="font-bold text-gray-500">Area (optional)</label><input name="address_line_2" value="${esc(a.address_line_2 || "")}" class="field mt-1"></div>
                <div><label class="font-bold text-gray-500">Landmark (optional)</label><input name="landmark" value="${esc(a.landmark || "")}" class="field mt-1"></div>
                <div><label class="font-bold text-gray-500">Pincode</label><input name="pincode" value="${esc(a.pincode || "")}" inputmode="numeric" maxlength="6" class="field mt-1" required></div>
                <div><label class="font-bold text-gray-500">City</label><input name="city_name" value="${esc(a.city || "")}" class="field mt-1"></div>
                <div><label class="font-bold text-gray-500">State</label><input name="state_name" value="${esc(a.state || "")}" class="field mt-1"></div>
                <label class="flex items-center gap-2 mt-5 font-semibold text-gray-600 cursor-pointer"><input type="checkbox" name="is_default" class="accent-brand-500" ${a.is_default ? "checked" : ""}>Make this my default address</label>
                <input type="hidden" name="latitude" value="${a.latitude ?? ""}"><input type="hidden" name="longitude" value="${a.longitude ?? ""}">
                <button class="col-span-2 bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm py-3 rounded-2xl">Save Address</button></form>`
        });
        const form = qs("#la-form", m);
        initMap(m, form, a);

        form.addEventListener("submit", async e => {
            e.preventDefault();
            const f = Object.fromEntries(new FormData(form));
            const mobile = String(f.mobile).replace(/\D/g, "").slice(-10);
            if (f.name.trim().length < 2) return toast("Enter the full name", "error");
            if (!/^\d{10}$/.test(mobile)) return toast("Enter a valid 10-digit mobile number", "error");
            if (!f.address_line_1.trim()) return toast("Enter the house / street", "error");
            if (!/^\d{6}$/.test(f.pincode)) return toast("Enter a valid 6-digit pincode", "error");
            const body = {
                name: f.name.trim(), mobile, address_line_1: f.address_line_1.trim(), address_line_2: f.address_line_2.trim() || null, landmark: f.landmark.trim() || null,
                pincode: f.pincode, address_type: f.address_type, is_default: !!f.is_default,
                latitude: f.latitude === "" ? null : Number(f.latitude), longitude: f.longitude === "" ? null : Number(f.longitude),
                city_name: f.city_name.trim() || null, state_name: f.state_name.trim() || null
            };
            const btn = form.querySelector("button:last-of-type"); btn.disabled = true;
            try { existing ? await Api.addressUpdate(existing.id, body) : await Api.addressAdd(body); Modal.close(); toast("✅ Address saved"); await refresh(); updateHeader(); if (onSaved) onSaved(); }
            catch (err) { toast(err.message, "error"); btn.disabled = false; }
        });
    }

    async function initMap(modal, form, a) {
        const box = qs("#la-map", modal);
        try { await loadLeaflet(); } catch (e) { box.textContent = "Map unavailable - type your address below."; return; }
        box.textContent = "";
        const start = a.latitude != null && a.longitude != null ? [a.latitude, a.longitude] : null;
        const map = L.map(box).setView(start || DEFAULT_CENTER, start ? 16 : 5);
        L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 19, attribution: "© OpenStreetMap" }).addTo(map);
        const pin = L.marker(start || DEFAULT_CENTER, { draggable: true });
        if (start) pin.addTo(map);

        const setPoint = async (lat, lng, fill = true) => {
            pin.setLatLng([lat, lng]).addTo(map);
            form.elements.latitude.value = lat.toFixed(7); form.elements.longitude.value = lng.toFixed(7);
            if (!fill) return;
            try { fillFrom((await Api.reverseGeocode(lat, lng)).place); } catch (e) { toast(e.message, "error"); }
        };
        const fillFrom = (p) => {
            const ad = p.address || {};
            const set = (n, v) => { if (v) form.elements[n].value = v; };
            set("address_line_1", ad.line); set("address_line_2", ad.area); set("pincode", ad.pincode); set("city_name", ad.city); set("state_name", ad.state);
        };

        map.on("click", e => setPoint(e.latlng.lat, e.latlng.lng));
        pin.on("dragend", () => { const p = pin.getLatLng(); setPoint(p.lat, p.lng); });
        setTimeout(() => map.invalidateSize(), 200);

        const results = qs("#la-results", modal);
        const search = async () => {
            const q = qs("#la-q", modal).value.trim();
            if (q.length < 3) return toast("Type at least 3 letters to search", "error");
            try {
                const { places } = await Api.placeSearch(q);
                results.classList.remove("hidden");
                results.innerHTML = places.length ? places.map((p, i) => `<button type="button" data-pick="${i}" class="block w-full text-left px-3 py-2 text-xs hover:bg-orange-50 border-b border-gray-50">${esc(p.display_name)}</button>`).join("") : `<p class="p-3 text-xs text-gray-400">No places found.</p>`;
                results.onclick = ev => {
                    const b = ev.target.closest("[data-pick]"); if (!b) return;
                    const p = places[Number(b.dataset.pick)];
                    results.classList.add("hidden"); map.setView([p.latitude, p.longitude], 17); setPoint(p.latitude, p.longitude, false); fillFrom(p);
                };
            } catch (err) { toast(err.message, "error"); }
        };
        qs("#la-search", modal).onclick = search;
        qs("#la-q", modal).addEventListener("keydown", e => { if (e.key === "Enter") { e.preventDefault(); search(); } });
        qs("#la-locate", modal).onclick = () => {
            if (!navigator.geolocation) return toast("Location is not supported on this browser", "error");
            navigator.geolocation.getCurrentPosition(pos => { map.setView([pos.coords.latitude, pos.coords.longitude], 17); setPoint(pos.coords.latitude, pos.coords.longitude); },
                err => toast(err.code === 1 ? "Location permission denied. Search or tap the map instead." : "Couldn't detect your location", "error"), { timeout: 10000 });
        };
    }

    return { refresh, mount, openForm, all, loadLeaflet, get default() { return data.default_address; } };
})();
