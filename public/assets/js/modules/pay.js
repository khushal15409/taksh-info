/* Pay page (server-rendered): the Razorpay window is the only thing here that needs a browser. The server creates the Razorpay order
   (/payments/create) and decides whether the order is paid (/payments/verify) - closing the window or a failed card changes nothing and the
   page offers "Pay now" again. ?start=1 opens the window once, straight after checkout. */
ready(() => {
    const btn = qs("#pay-now");
    if (!btn) return;
    const orderId = Number(btn.dataset.order);
    let busy = false;

    async function pay() {
        if (busy) return;
        busy = true; btn.disabled = true; btn.textContent = "Opening payment…";
        try {
            const r = await Pay.pay(orderId);
            if (r.paid) { location.href = btn.dataset.placedUrl; return; }
            toast(r.reason, "error");
            // a payment that failed inside Razorpay's window is reported on the result page; a closed window just stays here
            if (r.reason && !/closed the payment window/i.test(r.reason)) { location.href = `${btn.dataset.resultUrl}?status=failed&order=${orderId}&reason=${encodeURIComponent(r.reason)}`; return; }
        } catch (err) { toast(err.message, "error"); }
        busy = false; btn.disabled = false; btn.textContent = "Pay now";
    }

    btn.addEventListener("click", pay);
    if (btn.dataset.start === "1") { history.replaceState(null, "", location.pathname + "?order_id=" + orderId); pay(); }
});
