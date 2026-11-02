/* Phone OTP login (UI demo: OTP is always 1234 until a backend is connected) */
let phone = "", timer = null;
const DEMO_OTP = "1234";

ready(() => {
    if (Store.state.auth.loggedIn) { location.replace(next()); return; }
    stepPhone();
});

function next() {
    const n = new URLSearchParams(location.search).get("next");
    return n && n.startsWith("/") && !n.startsWith("//") ? location.origin + n : url("profile");
}

function shell(inner) {
    qs("#login-root").innerHTML = `
    <div class="max-w-md mx-auto bg-white rounded-3xl border border-orange-100 shadow-sm p-8 space-y-6 fade-up">
        <div class="text-center space-y-2">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-brand-500 to-brand-600 text-white flex items-center justify-center shadow-lg"><i data-lucide="smartphone" class="w-8 h-8"></i></div>
            ${inner}
        </div>
    </div>`;
    refreshIcons();
}

function stepPhone() {
    clearInterval(timer);
    shell(`<h1 class="text-xl font-extrabold text-gray-900">Login or Sign up</h1>
        <p class="text-xs text-gray-500">Enter your mobile number to get a one-time password.</p>
        <form id="phone-form" class="text-left space-y-4 pt-2">
            <div><label class="text-xs font-bold text-gray-500">Mobile number</label>
                <div class="flex mt-1"><span class="px-3 flex items-center bg-gray-100 border border-gray-200 border-r-0 rounded-l-xl text-sm font-bold text-gray-600">+91</span>
                <input id="phone-input" inputmode="numeric" maxlength="10" placeholder="98765 43210" class="field !rounded-l-none" autofocus></div></div>
            <button class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold py-3 rounded-2xl transition">Send OTP</button>
        </form>
        <a href="${url("home")}" class="block text-xs font-bold text-gray-500 hover:text-brand-600">Continue as guest →</a>
        <p class="text-[11px] text-gray-400">By continuing you agree to our <a class="underline" href="${url("terms")}">Terms</a> &amp; <a class="underline" href="${url("privacy")}">Privacy Policy</a>.</p>`);
    qs("#phone-form").addEventListener("submit", e => {
        e.preventDefault();
        const v = qs("#phone-input").value.replace(/\D/g, "");
        if (!/^[6-9]\d{9}$/.test(v)) return toast("Enter a valid 10-digit mobile number", "error");
        phone = v; stepOtp();
        toast(`OTP sent to +91 ${v} (demo OTP: ${DEMO_OTP})`);
    });
}

function stepOtp() {
    let left = 30;
    shell(`<h1 class="text-xl font-extrabold text-gray-900">Verify OTP</h1>
        <p class="text-xs text-gray-500">We sent a 4-digit code to <b class="text-gray-800">+91 ${phone}</b> <button id="change" class="text-brand-600 font-bold hover:underline">Change</button></p>
        <form id="otp-form" class="space-y-4 pt-2">
            <div class="flex justify-center gap-3">${[0, 1, 2, 3].map(i => `<input data-otp="${i}" inputmode="numeric" maxlength="1" aria-label="Digit ${i + 1}" class="w-14 h-14 text-center text-2xl font-extrabold rounded-2xl border-2 border-orange-200 bg-gray-50 focus:bg-white focus:border-brand-500 focus:outline-none">`).join("")}</div>
            <button class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold py-3 rounded-2xl transition">Verify &amp; Continue</button>
        </form>
        <p class="text-xs text-gray-400">Demo mode: use OTP <b class="text-gray-700">${DEMO_OTP}</b></p>
        <p class="text-xs text-gray-500" id="resend"></p>`);
    const boxes = qsa("[data-otp]");
    boxes[0].focus();
    boxes.forEach((b, i) => {
        b.addEventListener("input", () => { b.value = b.value.replace(/\D/g, ""); if (b.value && boxes[i + 1]) boxes[i + 1].focus(); });
        b.addEventListener("keydown", e => { if (e.key === "Backspace" && !b.value && boxes[i - 1]) boxes[i - 1].focus(); });
        b.addEventListener("paste", e => {
            const t = (e.clipboardData.getData("text") || "").replace(/\D/g, "").slice(0, 4);
            if (!t) return;
            e.preventDefault(); t.split("").forEach((c, k) => { boxes[k].value = c; }); boxes[Math.min(t.length, 3)].focus();
        });
    });
    qs("#change").onclick = e => { e.preventDefault(); stepPhone(); };
    const tick = () => {
        qs("#resend").innerHTML = left > 0 ? `Resend OTP in <b>${left}s</b>` : `<button id="resend-btn" class="text-brand-600 font-bold hover:underline">Resend OTP</button>`;
        const rb = qs("#resend-btn");
        if (rb) rb.onclick = () => { toast("OTP resent (demo OTP: " + DEMO_OTP + ")"); left = 30; tick(); };
    };
    tick();
    timer = setInterval(() => { if (left > 0) { left--; tick(); } }, 1000);
    qs("#otp-form").addEventListener("submit", e => {
        e.preventDefault();
        const code = boxes.map(b => b.value).join("");
        if (code.length < 4) return toast("Enter the 4-digit OTP", "error");
        if (code !== DEMO_OTP) { boxes.forEach(b => { b.value = ""; }); boxes[0].focus(); return toast("Incorrect OTP. Please try again.", "error"); }
        Store.state.auth = { loggedIn: true, phone, at: Date.now() };
        Store.state.profile.phone = "+91 " + phone.replace(/(\d{5})(\d{5})/, "$1 $2");
        Store.save();
        toast("✅ Logged in successfully");
        setTimeout(() => { location.href = next(); }, 600);
    });
}
