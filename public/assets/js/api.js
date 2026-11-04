/* TakshAllInOne - read-only data client for the shared Taksh endpoints (same paths and {success, message, data} payloads as the app's API). */

const Api = (() => {
    const base = () => document.body.dataset.root || "/";

    async function get(path, params) {
        const q = params ? "?" + new URLSearchParams(Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== "")).toString() : "";
        const res = await fetch(base() + path + q, { credentials: "same-origin", headers: { "Accept": "application/json", "X-Requested-With": "XMLHttpRequest" } });
        let json = null;
        try { json = await res.json(); } catch (e) { /* not JSON */ }
        if (!json || json.success === false || json.status === false) throw Object.assign(new Error((json && json.message) || "Something went wrong. Please try again."), { status: res.status });
        return json.data;
    }

    async function post(path, body, method = "POST") {
        const res = await fetch(base() + path, {
            method, credentials: "same-origin",
            headers: { "Accept": "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": Auth.csrf(), "X-Requested-With": "XMLHttpRequest" },
            body: JSON.stringify(body || {})
        });
        let json = null;
        try { json = await res.json(); } catch (e) { /* not JSON */ }
        if (res.status === 419) throw Object.assign(new Error("Your session expired. Please refresh the page and try again."), { status: 419 });
        if (!json || json.success === false || json.status === false) {
            const errs = json && json.data && json.data.errors, first = errs && Object.values(errs)[0];
            throw Object.assign(new Error((first && first[0]) || (json && json.message) || "Something went wrong. Please try again."), { status: res.status });
        }
        return json.data;
    }


    async function upload(path, formData) {
        const res = await fetch(base() + path, { method: "POST", credentials: "same-origin", headers: { "Accept": "application/json", "X-CSRF-TOKEN": Auth.csrf(), "X-Requested-With": "XMLHttpRequest" }, body: formData });
        let json = null;
        try { json = await res.json(); } catch (e) { /* not JSON */ }
        if (res.status === 419) throw Object.assign(new Error("Your session expired. Please refresh the page and try again."), { status: 419 });
        if (!json || json.success === false || json.status === false) {
            const errs = json && json.data && json.data.errors, first = errs && Object.values(errs)[0];
            throw Object.assign(new Error((first && first[0]) || (json && json.message) || "Upload failed. Please try again."), { status: res.status });
        }
        return json.data;
    }

    return {
        get, post, upload,
        cartAdd: (variantId, qty = 1, deliveryType = "normal") => post("cart/add", { product_variant_id: variantId, qty, delivery_type: deliveryType }),   // D2
        wishlistAdd: (productId) => post("wishlist/add", { product_id: productId }),
        wishlistRemove: (productId) => post(`wishlist/remove/${productId}`, {}),
        addresses: () => get("address/addresses"),                          // F1 F2 (login required)
        addressAdd: (body) => post("address/add", body),
        addressUpdate: (id, body) => post(`address/${id}`, body, "PUT"),
        addressDefault: (id) => post("address/user/addresses/set-default", { address_id: id }),
        addressDelete: (id) => post("address/user/addresses/delete", { address_id: id }),
        placeSearch: (q) => get("location/search", { q }),                  // F3
        reverseGeocode: (latitude, longitude) => get("location/reverse-geocode", { latitude, longitude }),
        paymentCreate: (orderId) => post("payments/create", { order_id: orderId }),   // G6 G8
        paymentVerify: (body) => post("payments/verify", body),
        deliveryLocation: (id) => get(`order/orders/${id}/delivery-location`),  // H7
        notifUnread: () => get("notifications/unread-count"),
        pushRegister: (token) => post("notifications/device-token", { token, platform: "web" }),   // J4 (needs a push provider on the page)
        serviceVerify: (body) => post("service/book/verify", body),             // K3
        courierQuote: (body) => post("service/courier/quote", body),            // K6
        courierPaymentCreate: (id) => post("service/courier/payment/create", { courier_booking_id: id }),
        courierPaymentVerify: (body) => post("service/courier/payment/verify", body),
        checkDelivery: (id, body) => post(`ecommerce/products/${id}/check-delivery`, body),   // C7
        submitReview: (id, body) => post(`product/${id}/review`, body),
        search: async (keyword, limit = 6) => { const r = await fetch(base() + "products/search?" + new URLSearchParams({ keyword, limit }), { credentials: "same-origin", headers: { "Accept": "application/json" } }); const j = await r.json(); return j.status ? j.data : []; },  // C11 (plain {status,data})
        recentSearches: () => get("product/recent-searches")                // C12 (login required)
    };
})();
