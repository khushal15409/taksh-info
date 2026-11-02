/* Help & Support: callback request, email, WhatsApp / live chat, FAQs */
ready(() => {
    const wa = "https://wa.me/" + SITE.supportPhone.replace(/\D/g, "") + "?text=" + encodeURIComponent("Hi Taksh, I need help with my order.");
    const mail = `mailto:${SITE.supportEmail}?subject=${encodeURIComponent("Taksh support request")}`;
    const card = (href, ic, tint, t, d, extra = "") => `<a href="${href}" ${extra} class="bg-white p-5 rounded-3xl border border-orange-100 hover:border-brand-500/50 hover:shadow-card-hover transition flex items-start gap-4">
        <div class="w-11 h-11 rounded-2xl ${tint} flex items-center justify-center shrink-0"><i data-lucide="${ic}" class="w-5 h-5"></i></div>
        <div><h3 class="font-extrabold text-sm text-gray-900">${t}</h3><p class="text-xs text-gray-500 mt-0.5">${d}</p></div></a>`;
    qs("#help-root").innerHTML = `
    <div class="max-w-5xl mx-auto space-y-8">
        <div><h1 class="text-2xl font-extrabold text-gray-900">Help &amp; Support</h1><p class="text-sm text-gray-500 mt-1">Choose how you'd like to reach us. We're here 24x7.</p></div>
        <div class="grid md:grid-cols-3 gap-4">
            ${card(wa, "message-circle", "bg-emerald-100 text-emerald-600", "Live chat on WhatsApp", "Chat with a Taksh care executive", 'target="_blank" rel="noopener"')}
            ${card(mail, "mail", "bg-sky-100 text-sky-600", "Email support", SITE.supportEmail)}
            ${card("tel:" + SITE.supportPhone.replace(/\s/g, ""), "phone", "bg-orange-100 text-brand-600", "Call us", SITE.supportPhone)}
        </div>
        <div class="grid lg:grid-cols-5 gap-6">
            <section data-form-host class="lg:col-span-2 bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-4 h-fit">
                <div><h2 class="font-extrabold text-base text-gray-900">Request a callback</h2><p class="text-xs text-gray-500">Tell us when to call and we'll get back to you.</p></div>
                ${formHtml("callback")}
            </section>
            <section class="lg:col-span-3 space-y-3"><h2 class="font-extrabold text-base text-gray-900">Frequently asked questions</h2>
                ${FAQS.map(f => `<details class="bg-white rounded-2xl border border-gray-100 p-4"><summary class="flex items-center justify-between font-bold text-sm text-gray-800 cursor-pointer">${f.q}<i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i></summary><p class="text-xs text-gray-500 mt-3 leading-relaxed">${f.a}</p></details>`).join("")}
            </section>
        </div>
        <div class="grid sm:grid-cols-3 gap-4 text-xs font-bold">
            <a href="${url("delivery")}" class="bg-emerald-50 text-emerald-800 rounded-2xl p-4 hover:shadow">🛵 Join as Delivery Partner →</a>
            <a href="${url("vendor")}" class="bg-purple-50 text-purple-800 rounded-2xl p-4 hover:shadow">🏪 Become a Vendor →</a>
            <a href="${url("devrequest")}" class="bg-sky-50 text-sky-800 rounded-2xl p-4 hover:shadow">💻 Development Request →</a>
        </div>
    </div>`;
    refreshIcons();
});
