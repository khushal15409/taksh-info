/* Courier form on /services (server-rendered): the rate lookup is a small call (POST /service/courier/quote) that lists the live courier options;
   choosing one fills two hidden fields (the courier and the rate the customer saw, so a stale quote is caught). The booking itself is a normal
   multipart post - and without picking a courier the cheapest one is booked. The button locks after one click. */
ready(() => {
    const form = qs("#courier-form");
    if (!form) return;
    const box = qs("#courier-options"), rates = qs("#courier-rates");
    let couriers = [];

    const parcel = () => {
        const f = Object.fromEntries(new FormData(form));
        return { weight_grams: Number(f.weight_grams), dimension_unit: f.dimension_unit, length: Number(f.length), width: Number(f.width), height: Number(f.height), pickup_pincode: String(f.pickup_pincode || "").trim(), delivery_pincode: String(f.delivery_pincode || "").trim() };
    };
    const pick = (c) => { form.shiprocket_courier_company_id.value = c ? c.courier_company_id : ""; form.quoted_rate.value = c ? c.rate : ""; };

    function draw(selected) {
        box.innerHTML = `<div class="font-extrabold text-gray-700 pt-1">Choose a courier</div>` + couriers.map((c, i) => `
            <label class="flex items-center gap-3 p-3 rounded-2xl border ${selected === i ? "border-blue-500 bg-blue-50" : "border-gray-100 bg-white"} cursor-pointer">
                <input type="radio" name="courier_pick" value="${i}" ${selected === i ? "checked" : ""}>
                <div class="flex-1"><div class="font-bold text-gray-800">${esc(c.courier_name || "Courier")}</div><div class="text-gray-400">${c.estimated_delivery_days ? `${c.estimated_delivery_days} day(s)` : "Delivery time varies"}${c.service_type ? ` • ${esc(c.service_type)}` : ""}</div></div>
                <div class="text-right"><div class="font-extrabold text-brand-600">${money(c.total_amount)}</div><div class="text-[10px] text-gray-400">${money(c.rate)} + ${money(c.service_charge)} service</div></div></label>`).join("");
    }

    rates.addEventListener("click", async () => {
        const p = parcel();
        if (!/^\d{6}$/.test(p.pickup_pincode) || !/^\d{6}$/.test(p.delivery_pincode)) return toast("Enter valid 6-digit pickup and delivery pincodes", "error");
        rates.disabled = true; rates.textContent = "Fetching rates…"; box.innerHTML = "";
        try { const d = await Api.courierQuote(p); couriers = d.couriers; pick(couriers[0]); draw(0); }
        catch (e) { couriers = []; pick(null); box.innerHTML = `<p class="text-red-500 font-bold">${esc(e.message)}</p>`; }
        rates.disabled = false; rates.textContent = "Refresh rates";
    });
    box.addEventListener("change", e => { if (e.target.name === "courier_pick") { const i = Number(e.target.value); pick(couriers[i]); draw(i); } });

    // a changed parcel makes the shown rates stale: forget the choice (the server then books the cheapest at today's rates)
    form.addEventListener("input", e => { if (couriers.length && ["weight_grams", "dimension_unit", "length", "width", "height", "pickup_pincode", "delivery_pincode"].includes(e.target.name)) { couriers = []; pick(null); box.innerHTML = `<p class="text-[11px] text-gray-400">The parcel changed - get the rates again, or the cheapest courier will be booked.</p>`; } });

    let sent = false;
    form.addEventListener("submit", e => {
        if (sent) { e.preventDefault(); return; }
        sent = true;
        const b = qs("#courier-book"); b.disabled = true; b.textContent = "Booking…";
    });
    if (location.hash === "#courier") setTimeout(() => form.scrollIntoView({ behavior: "smooth", block: "start" }), 150);
    refreshIcons();
});
