/* Online payment of an order with Razorpay: create the Razorpay order on the server, open Razorpay's checkout in the browser,
   then send what it returns to the server, which verifies the signature and asks Razorpay before marking the order paid. */

const Pay = (() => {
    let sdk = null;
    const loadSdk = () => window.Razorpay ? Promise.resolve() : (sdk ||= new Promise((resolve, reject) => {
        const s = document.createElement("script");
        s.src = "https://checkout.razorpay.com/v1/checkout.js";
        s.onload = resolve;
        s.onerror = () => { sdk = null; reject(new Error("Couldn't load the payment window. Check your connection and try again.")); };
        document.head.appendChild(s);
    }));

    /**
     * Pay an order. Resolves {paid: true, data} when verified, {paid: false, reason} when the customer closed the window or
     * the payment failed (nothing is charged without the server's verification), and rejects on a server/network error.
     */
    async function pay(orderId) {
        const order = await Api.paymentCreate(orderId);          // {razorpay_order_id, amount, currency, razorpay_key}
        return checkout({ key: order.razorpay_key, amount: order.amount, currency: order.currency, orderId: order.razorpay_order_id }, "Order payment", Api.paymentVerify);
    }

    /**
     * Open Razorpay's checkout for any server-created order ({key, amount, currency, orderId}); `verify` gets Razorpay's answer
     * and must ask the server to verify it. Same resolve/reject contract as pay().
     */
    async function checkout(order, description, verify) {
        await loadSdk();
        const u = Auth.user || {};

        return new Promise((resolve, reject) => {
            const rzp = new Razorpay({
                key: order.key, amount: order.amount, currency: order.currency, order_id: order.orderId,
                name: "TakshAllInOne", description,
                prefill: { name: u.name || "", contact: u.mobile || "", email: u.email || "" },
                theme: { color: "#FF7A00" },
                handler: async (r) => {
                    try {
                        const data = await verify({ razorpay_order_id: r.razorpay_order_id, razorpay_payment_id: r.razorpay_payment_id, razorpay_signature: r.razorpay_signature });
                        resolve({ paid: true, data });
                    } catch (e) { reject(e); }
                },
                modal: { ondismiss: () => resolve({ paid: false, reason: "You closed the payment window before paying." }) }
            });
            rzp.on("payment.failed", (resp) => resolve({ paid: false, reason: (resp.error && resp.error.description) || "The payment failed." }));
            rzp.open();
        });
    }

    return { pay, checkout };
})();
