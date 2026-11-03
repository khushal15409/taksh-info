/* Phone OTP login - same flow as the mobile app: send OTP -> verify OTP -> signed in (guest cart merged by the server) */
let phone = "", timer = null;
const OTP_LENGTH = Number(document.body.dataset.otpLength) || 4;

ready(() => {
    if (Auth.loggedIn) { location.replace(next()); return; }
    stepPhone();
});

// Where to go after login: only same-site paths (the guard sends ?next=/checkout etc.)
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

function busy(btn, on, label) { btn.disabled = on; btn.textContent = on ? "Please wait…" : label; }

function stepPhone() {
    clearInterval(timer);
    shell(`<h1 class="text-xl font-extrabold text-gray-900">Login or Sign up</h1>
        <p class="text-xs text-gray-500">Enter your mobile number to get a one-time password.</p>
        <form id="phone-form" class="text-left space-y-4 pt-2" novalidate>
            <div><label class="text-xs font-bold text-gray-500">Mobile number</label>
                <div class="flex mt-1"><span class="px-3 flex items-center bg-gray-100 border border-gray-200 border-r-0 rounded-l-xl text-sm font-bold text-gray-600">+91</span>
                <input id="phone-input" inputmode="numeric" maxlength="10" autocomplete="tel-national" placeholder="98765 43210" value="${esc(phone)}" class="field !rounded-l-none" autofocus></div></div>
            <button class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold py-3 rounded-2xl transition">Send OTP</button>
        </form>
        <a href="${url("home")}" class="block text-xs font-bold text-gray-500 hover:text-brand-600">Continue as guest →</a>
        <p class="text-[11px] text-gray-400">By continuing you agree to our <a class="underline" href="${url("terms")}">Terms</a> &amp; <a class="underline" href="${url("privacy")}">Privacy Policy</a>.</p>`);
    qs("#phone-form").addEventListener("submit", async e => {
        e.preventDefault();
        const v = qs("#phone-input").value.replace(/\D/g, "");
        if (!/^\d{10}$/.test(v)) return toast("Enter a valid 10-digit mobile number", "error");
        const btn = e.target.querySelector("button");
        busy(btn, true);
        try { await Auth.sendOtp(v); phone = v; stepOtp(); toast(`OTP sent to +91 ${v}`); }
        catch (err) { toast(err.message, "error"); busy(btn, false, "Send OTP"); }
    });
}

function stepOtp() {
    let left = 30;
    shell(`<h1 class="text-xl font-extrabold text-gray-900">Verify OTP</h1>
        <p class="text-xs text-gray-500">We sent a ${OTP_LENGTH}-digit code to <b class="text-gray-800">+91 ${esc(phone)}</b> <button id="change" class="text-brand-600 font-bold hover:underline">Change</button></p>
        <form id="otp-form" class="space-y-4 pt-2" novalidate>
            <div class="flex justify-center gap-3">${Array.from({ length: OTP_LENGTH }, (_, i) => `<input data-otp="${i}" inputmode="numeric" maxlength="1" autocomplete="one-time-code" aria-label="Digit ${i + 1}" class="w-14 h-14 text-center text-2xl font-extrabold rounded-2xl border-2 border-orange-200 bg-gray-50 focus:bg-white focus:border-brand-500 focus:outline-none">`).join("")}</div>
            <button class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold py-3 rounded-2xl transition">Verify &amp; Continue</button>
        </form>
        <p class="text-xs text-gray-500" id="resend"></p>`);
    const boxes = qsa("[data-otp]");
    boxes[0].focus();
    boxes.forEach((b, i) => {
        b.addEventListener("input", () => { b.value = b.value.replace(/\D/g, ""); if (b.value && boxes[i + 1]) boxes[i + 1].focus(); });
        b.addEventListener("keydown", e => { if (e.key === "Backspace" && !b.value && boxes[i - 1]) boxes[i - 1].focus(); });
        b.addEventListener("paste", e => {
            const t = (e.clipboardData.getData("text") || "").replace(/\D/g, "").slice(0, OTP_LENGTH);
            if (!t) return;
            e.preventDefault(); t.split("").forEach((c, k) => { boxes[k].value = c; }); boxes[Math.min(t.length, OTP_LENGTH - 1)].focus();
        });
    });
    qs("#change").onclick = e => { e.preventDefault(); stepPhone(); };
    const tick = () => {
        qs("#resend").innerHTML = left > 0 ? `Resend OTP in <b>${left}s</b>` : `<button id="resend-btn" class="text-brand-600 font-bold hover:underline">Resend OTP</button>`;
        const rb = qs("#resend-btn");
        if (rb) rb.onclick = async () => {
            try { await Auth.sendOtp(phone); toast("OTP resent"); left = 30; tick(); }
            catch (err) { toast(err.message, "error"); }
        };
    };
    tick();
    timer = setInterval(() => { if (left > 0) { left--; tick(); } }, 1000);
    qs("#otp-form").addEventListener("submit", async e => {
        e.preventDefault();
        const code = boxes.map(b => b.value).join("");
        if (code.length < OTP_LENGTH) return toast(`Enter the ${OTP_LENGTH}-digit OTP`, "error");
        const btn = e.target.querySelector("button");
        busy(btn, true);
        try {
            await Auth.verifyOtp(phone, code);
            toast("✅ Logged in successfully");
            setTimeout(() => { location.href = next(); }, 500);
        } catch (err) {
            boxes.forEach(b => { b.value = ""; }); boxes[0].focus();
            toast(err.message, "error"); busy(btn, false, "Verify & Continue");
        }
    });
}
