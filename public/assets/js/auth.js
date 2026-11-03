/* TakshAllInOne - customer auth client. Talks to the same /auth/* endpoints as the mobile app (session + CSRF on web).
   The signed-in user is rendered into <body data-user> by the server, so the first paint already knows who is logged in. */

const Auth = (() => {
    let user = null;
    try { user = JSON.parse(document.body.dataset.user || "null"); } catch (e) { /* guest */ }

    const csrf = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || "";
    const setCsrf = (t) => { const m = document.querySelector('meta[name="csrf-token"]'); if (m && t) m.content = t; };
    const base = () => document.body.dataset.root || "/";

    async function call(method, path, body) {
        const res = await fetch(base() + "auth/" + path, {
            method,
            credentials: "same-origin",
            headers: { "Accept": "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrf(), "X-Requested-With": "XMLHttpRequest" },
            body: body ? JSON.stringify(body) : undefined
        });
        let json = null;
        try { json = await res.json(); } catch (e) { /* non-JSON error page */ }
        if (res.status === 419) throw Object.assign(new Error("Your session expired. Please refresh the page and try again."), { status: 419 });
        if (!json) throw Object.assign(new Error("Something went wrong. Please try again."), { status: res.status });
        if (!json.success) {
            const errs = json.data && json.data.errors;
            const first = errs && Object.values(errs)[0];
            throw Object.assign(new Error((first && first[0]) || json.message), { status: res.status, errors: errs || null });
        }
        return json;
    }

    return {
        get user() { return user; },
        get loggedIn() { return !!user; },
        csrf,

        sendOtp: (mobile) => call("POST", "send-otp", { mobile }),
        async verifyOtp(mobile, otp) {
            const json = await call("POST", "verify-otp", { mobile, otp });   // the server merges this browser's guest cart into the customer's
            setCsrf(json.data.csrf_token);
            user = json.data.user;
            return json;
        },
        async refresh() {          // session restore / re-check (e.g. coming back with the browser's back button)
            const json = await call("GET", "session");
            user = json.data.user;
            return user;
        }
    };
})();

// Back/forward cache can show a page rendered for a previous login state - re-check with the server.
window.addEventListener("pageshow", (e) => {
    if (e.persisted) Auth.refresh().then(() => location.reload()).catch(() => { /* offline: keep the page */ });
});
