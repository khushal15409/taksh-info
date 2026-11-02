/* Partner / request forms: delivery partner, vendor, development request, callback (saved locally until a backend is connected) */
const F = (name, label, o = {}) => Object.assign({ name, label, type: "text" }, o);

const FORMS = {
    delivery: {
        title: "Join as Delivery Partner", emoji: "🛵", tint: "from-emerald-700 via-emerald-600 to-lime-500",
        intro: "Earn on your own schedule delivering Taksh orders in your city. Weekly payouts, fuel support and insurance cover.",
        perks: [["wallet", "Weekly payouts"], ["clock", "Flexible hours"], ["shield-check", "Accident cover"]],
        fields: [
            F("name", "Full name", { required: 1, profile: "name" }), F("phone", "Mobile number", { required: 1, type: "tel", profile: "phone", pattern: /^[+\d][\d\s-]{9,14}$/, msg: "Enter a valid mobile number" }),
            F("city", "City", { required: 1, value: "Ahmedabad" }), F("pincode", "Pincode", { required: 1, maxlength: 6, pattern: /^\d{6}$/, msg: "Enter a valid 6-digit pincode" }),
            F("vehicle", "Vehicle", { type: "select", options: ["Motorbike", "Scooter", "Electric scooter", "Bicycle"] }),
            F("licence", "Driving licence number", { required: 1, hint: "Not needed for bicycles" }),
            F("shift", "Availability", { type: "select", options: ["Full time", "Part time (evenings)", "Weekends only"] }),
            F("agree", "I confirm the details are correct and agree to a background verification.", { type: "checkbox", required: 1, wide: 1 })
        ],
        submit: "Submit Application", success: "Application received! Our onboarding team will call you within 2 working days."
    },
    vendor: {
        title: "Become a Vendor", emoji: "🏪", tint: "from-purple-700 via-purple-600 to-fuchsia-500",
        intro: "Sell your products to thousands of Taksh customers. List items, manage orders and get paid directly to your bank.",
        perks: [["store", "Free store setup"], ["trending-up", "More customers"], ["landmark", "Direct payouts"]],
        fields: [
            F("business", "Business / shop name", { required: 1 }), F("owner", "Owner name", { required: 1, profile: "name" }),
            F("phone", "Mobile number", { required: 1, type: "tel", profile: "phone", pattern: /^[+\d][\d\s-]{9,14}$/, msg: "Enter a valid mobile number" }),
            F("email", "Email", { required: 1, type: "email", profile: "email", pattern: /^\S+@\S+\.\S+$/, msg: "Enter a valid email" }),
            F("category", "Main category", { type: "select", options: () => CATEGORIES.map(c => c.name) }),
            F("gstin", "GSTIN (optional)", { maxlength: 15, pattern: /^$|^\d{2}[A-Z]{5}\d{4}[A-Z][A-Z\d]Z[A-Z\d]$/i, msg: "Enter a valid 15-character GSTIN or leave blank" }),
            F("city", "City", { required: 1, value: "Ahmedabad" }), F("pincode", "Pincode", { required: 1, maxlength: 6, pattern: /^\d{6}$/, msg: "Enter a valid 6-digit pincode" }),
            F("about", "Tell us about your products", { type: "textarea", wide: 1 }),
            F("agree", "I agree to the Taksh vendor terms.", { type: "checkbox", required: 1, wide: 1 })
        ],
        submit: "Register as Vendor", success: "Vendor request submitted! Our partner team will verify your details and reach out shortly."
    },
    devrequest: {
        title: "Development Request", emoji: "💻", tint: "from-sky-700 via-sky-600 to-cyan-500",
        intro: "Need a mobile app or website for your business? Share your idea and the Taksh tech team will get back with a plan and quote.",
        perks: [["smartphone", "Mobile apps"], ["globe", "Websites"], ["headphones", "Ongoing support"]],
        fields: [
            F("name", "Your name", { required: 1, profile: "name" }), F("phone", "Mobile number", { required: 1, type: "tel", profile: "phone", pattern: /^[+\d][\d\s-]{9,14}$/, msg: "Enter a valid mobile number" }),
            F("email", "Email", { required: 1, type: "email", profile: "email", pattern: /^\S+@\S+\.\S+$/, msg: "Enter a valid email" }),
            F("kind", "What do you need?", { type: "select", options: ["Create Application", "Create Website", "Both"] }),
            F("budget", "Budget (approx.)", { type: "select", options: ["Under ₹50,000", "₹50,000 - ₹2,00,000", "₹2,00,000 - ₹5,00,000", "Above ₹5,00,000"] }),
            F("timeline", "Timeline", { type: "select", options: ["Within 1 month", "1-3 months", "3-6 months", "Flexible"] }),
            F("details", "Describe your requirement", { type: "textarea", required: 1, wide: 1 })
        ],
        submit: "Send Request", success: "Request sent! Our tech team will contact you within 2 working days."
    },
    callback: {
        title: "Request a Callback", fields: [
            F("name", "Your name", { required: 1, profile: "name" }),
            F("phone", "Mobile number", { required: 1, type: "tel", profile: "phone", pattern: /^[+\d][\d\s-]{9,14}$/, msg: "Enter a valid mobile number" }),
            F("topic", "Topic", { type: "select", options: ["Order / delivery", "Returns & refunds", "Payments", "Home services", "Courier", "Other"] }),
            F("slot", "Preferred time", { type: "select", options: ["Anytime", "9 AM - 12 PM", "12 PM - 3 PM", "3 PM - 6 PM", "6 PM - 9 PM"] })
        ], submit: "Request Callback", success: "Callback requested! We'll call you at your preferred time."
    }
};

function fieldHtml(f) {
    const profile = f.profile ? (Store.state.profile[f.profile] || "") : "";
    const val = f.value || profile;
    const base = `name="${f.name}" id="f-${f.name}"`;
    if (f.type === "checkbox")
        return `<label class="${f.wide ? "col-span-2" : ""} flex items-start gap-2 text-xs text-gray-600 cursor-pointer"><input type="checkbox" ${base} class="accent-brand-500 mt-0.5" ${f.required ? "required" : ""}><span>${esc(f.label)}</span></label>`;
    let input;
    if (f.type === "select") {
        const opts = typeof f.options === "function" ? f.options() : f.options;
        input = `<select ${base} class="field mt-1">${opts.map(o => `<option>${esc(o)}</option>`).join("")}</select>`;
    } else if (f.type === "textarea") input = `<textarea ${base} rows="3" class="field mt-1" ${f.required ? "required" : ""}></textarea>`;
    else input = `<input ${base} type="${f.type}" value="${esc(val)}" class="field mt-1" ${f.maxlength ? `maxlength="${f.maxlength}"` : ""} ${f.required ? "required" : ""}>`;
    return `<div class="${f.wide ? "col-span-2" : "col-span-2 sm:col-span-1"}"><label for="f-${f.name}" class="font-bold text-gray-500">${esc(f.label)}</label>${input}${f.hint ? `<p class="text-[11px] text-gray-400 mt-1">${esc(f.hint)}</p>` : ""}</div>`;
}

// Renders <form> markup for a FORMS entry; call bindForm() after inserting it.
function formHtml(key) {
    const c = FORMS[key];
    return `<form data-form="${key}" class="grid grid-cols-2 gap-4 text-xs" novalidate>${c.fields.map(fieldHtml).join("")}
        <button class="col-span-2 bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm py-3 rounded-2xl transition">${c.submit}</button></form>`;
}

function submitForm(key, form, onDone) {
    const c = FORMS[key];
    const data = {};
    for (const f of c.fields) {
        const el = form.elements[f.name];
        const v = f.type === "checkbox" ? el.checked : el.value.trim();
        if (f.required && !v) return toast(`Please fill: ${f.label.replace(/\.$/, "")}`, "error");
        if (f.pattern && !f.pattern.test(v)) return toast(f.msg, "error");
        data[f.name] = v;
    }
    const rec = { id: "REQ" + Date.now().toString(36).toUpperCase().slice(-6), kind: key, data, at: Date.now() };
    (Store.state.requests = Store.state.requests || []).unshift(rec);
    Store.save();
    onDone(rec);
}

document.addEventListener("submit", e => {
    const form = e.target.closest("[data-form]");
    if (!form) return;
    e.preventDefault();
    const key = form.dataset.form;
    submitForm(key, form, rec => {
        toast("✅ " + FORMS[key].success.split("!")[0] + "!");
        const host = form.closest("[data-form-host]") || form.parentElement;
        host.innerHTML = `<div class="text-center py-8 space-y-3">
            <div class="w-20 h-20 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center"><i data-lucide="check" class="w-10 h-10"></i></div>
            <h3 class="text-lg font-extrabold text-gray-900">Thank you!</h3>
            <p class="text-sm text-gray-500 max-w-sm mx-auto">${esc(FORMS[key].success)}</p>
            <p class="text-xs text-gray-400">Reference ID: <b class="text-gray-700">${rec.id}</b></p>
            <a href="${url("home")}" class="inline-block bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm px-6 py-3 rounded-2xl">Back to Home</a></div>`;
        refreshIcons();
    });
});

// Full-page mount (delivery / vendor / development request)
ready(() => {
    const root = qs("#form-root");
    const c = root && FORMS[PAGE];
    if (!c) return;
    document.title = `${c.title} | TakshAllInOne`;
    root.innerHTML = `
    <div class="max-w-4xl mx-auto space-y-6">
        <section class="rounded-3xl bg-gradient-to-r ${c.tint} text-white p-8 lg:p-10 relative overflow-hidden">
            <div class="absolute -right-4 -bottom-6 text-[8rem] opacity-20 select-none">${c.emoji}</div>
            <h1 class="text-2xl lg:text-3xl font-extrabold font-festive relative">${c.title}</h1>
            <p class="text-sm text-white/90 max-w-xl mt-2 relative">${c.intro}</p>
            <div class="flex flex-wrap gap-3 mt-5 relative">${c.perks.map(([ic, l]) => `<span class="bg-white/20 backdrop-blur px-3 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5"><i data-lucide="${ic}" class="w-3.5 h-3.5"></i>${l}</span>`).join("")}</div>
        </section>
        <section data-form-host class="bg-white p-6 lg:p-8 rounded-3xl border border-orange-100 shadow-sm">${formHtml(PAGE)}</section>
    </div>`;
    refreshIcons();
});
