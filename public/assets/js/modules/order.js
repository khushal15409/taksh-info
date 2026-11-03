/* Order page (server-rendered): what needs the browser - locking a button after one click ("Cancel order" is a real form post), and for
   a Quick order on its way, the delivery person on a map (OpenStreetMap), refreshed every 15 seconds from /order/orders/{id}/delivery-location.
   When the order stops being "out for delivery" the page reloads to show its new status. */
ready(() => {
    document.addEventListener("submit", e => {
        const b = e.target.querySelector("[data-once]");
        if (b) { b.disabled = true; b.textContent = "Please wait…"; }
    });

    const box = qs("#order-map");
    if (!box) return;
    const orderId = Number(box.dataset.order), lat = box.dataset.lat, lng = box.dataset.lng;

    (async () => {
        try { await Addresses.loadLeaflet(); } catch (e) { box.textContent = "Map unavailable"; return; }
        box.textContent = "";
        const map = L.map(box).setView([22.9734, 78.6569], 5);
        L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 19, attribution: "© OpenStreetMap" }).addTo(map);
        let rider = null, home = null, timer = null;
        const tick = async () => {
            let d; try { d = await Api.deliveryLocation(orderId); } catch (e) { return; }
            if (d.order_status !== "out_for_delivery") { clearInterval(timer); location.reload(); return; }
            const note = qs("#order-map-note");
            if (!d.location) { note.textContent = "The delivery partner's location isn't available yet."; return; }
            const p = [d.location.latitude, d.location.longitude];
            rider ? rider.setLatLng(p) : (rider = L.marker(p).addTo(map).bindPopup(d.delivery_boy ? d.delivery_boy.name : "Delivery partner"));
            if (lat && !home) home = L.circleMarker([Number(lat), Number(lng)], { radius: 8, color: "#E65100" }).addTo(map).bindPopup("Your address");
            map.setView(p, 15);
            note.textContent = d.reached_nearby ? "Your delivery partner has reached your location! 🎉" : d.distance_to_destination != null ? `About ${d.distance_to_destination >= 1000 ? (d.distance_to_destination / 1000).toFixed(1) + " km" : Math.round(d.distance_to_destination) + " m"} away` : "On the way";
        };
        tick(); timer = setInterval(tick, 15000);
    })();
});
