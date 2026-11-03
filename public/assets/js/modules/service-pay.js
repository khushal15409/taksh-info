/* Pay button of a service or courier booking (server-rendered page): only the Razorpay window is here. The server made the Razorpay order and
   decides that the booking is paid (/service/book/verify, /service/courier/payment/verify); closing the window changes nothing and the button stays.
   For a courier booking the Razorpay order is asked for first (/service/courier/payment/create). ?pay=1 opens the window once after booking. */
ready(() => {
    const btn = qs("#pay-now");
    if (!btn) return;
    let busy = false;

    async function pay() {
        if (busy) return;
        busy = true; btn.disabled = true; btn.textContent = "Opening payment…";
        try {
            let order, verify;
            if (btn.dataset.kind === "courier") {
                const id = Number(btn.dataset.booking), o = await Api.courierPaymentCreate(id);
                order = { key: o.gateway_key, amount: o.amount, currency: o.currency, orderId: o.razorpay_order_id };
                verify = (x) => Api.courierPaymentVerify({ courier_booking_id: id, ...x });
            } else {
                order = { key: btn.dataset.key, amount: Number(btn.dataset.amount), currency: btn.dataset.currency, orderId: btn.dataset.order };
                verify = Api.serviceVerify;
            }
            const r = await Pay.checkout(order, btn.dataset.description, verify);
            if (r.paid) { toast("✅ Payment received"); setTimeout(() => { history.replaceState(null, "", location.pathname); location.reload(); }, 600); return; }
            toast(r.reason, "error");
        } catch (err) { toast(err.message, "error"); }
        busy = false; btn.disabled = false; btn.textContent = "Pay now";
    }

    btn.addEventListener("click", pay);
    if (btn.dataset.start === "1") { history.replaceState(null, "", location.pathname); pay(); }
});
