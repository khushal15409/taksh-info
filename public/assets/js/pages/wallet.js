/* Taksh Wallet: points balance, bank accounts, withdrawal request, history (local demo) */
const MIN_WITHDRAW = 100;
const wl = () => Store.state.wallet;

ready(() => {
    qs("#wallet-root").addEventListener("submit", e => {
        e.preventDefault();
        const f = Object.fromEntries(new FormData(e.target));
        if (e.target.id === "bank-form") {
            if (f.holder.trim().length < 3) return toast("Enter the account holder name", "error");
            if (!/^\d{9,18}$/.test(f.number)) return toast("Enter a valid account number (9-18 digits)", "error");
            if (f.number !== f.number2) return toast("Account numbers do not match", "error");
            if (!/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/.test(f.ifsc)) return toast("Enter a valid IFSC code", "error");
            wl().banks.push({ id: Store.uid("b"), holder: f.holder.trim(), number: f.number, ifsc: f.ifsc.toUpperCase(), bank: f.bank.trim() });
            Store.save(); render(); toast("🏦 Bank account added");
        }
        if (e.target.id === "withdraw-form") {
            const amt = Math.floor(Number(f.amount));
            if (!wl().banks.length) return toast("Add a bank account first", "error");
            if (!amt || amt < MIN_WITHDRAW) return toast(`Minimum withdrawal is ${MIN_WITHDRAW} points`, "error");
            if (amt > wl().points) return toast("Amount exceeds your available balance", "error");
            wl().points -= amt;
            wl().txns.unshift({ id: Store.uid("t"), type: "debit", title: "Withdrawal request (pending)", points: amt, at: Date.now() });
            Store.save(); render(); toast("💸 Withdrawal request submitted");
        }
    });
    qs("#wallet-root").addEventListener("click", e => {
        const b = e.target.closest("[data-del-bank]");
        if (!b) return;
        wl().banks = wl().banks.filter(x => x.id !== b.dataset.delBank);
        Store.save(); render(); toast("Bank account removed");
    });
    render();
});

function render() {
    const w = wl();
    const mask = n => "•••• " + n.slice(-4);
    qs("#wallet-root").innerHTML = `
    <div class="max-w-5xl mx-auto space-y-6">
        <h1 class="text-2xl font-extrabold text-gray-900">My Wallet</h1>
        <section class="rounded-3xl bg-gradient-to-r from-brand-500 via-brand-600 to-red-700 text-white p-8 flex flex-wrap items-center justify-between gap-4 relative overflow-hidden">
            <div class="absolute -right-4 -bottom-8 text-[9rem] opacity-15 select-none">👛</div>
            <div class="relative"><p class="text-xs uppercase tracking-widest text-orange-100 font-bold">Available balance</p>
                <p class="text-4xl font-extrabold font-festive mt-1">${w.points.toLocaleString("en-IN")} <span class="text-base font-bold text-orange-100">points</span></p>
                <p class="text-xs text-orange-100 mt-1">1 point = ₹1 • Minimum withdrawal ${MIN_WITHDRAW} points</p></div>
        </section>
        <div class="grid lg:grid-cols-2 gap-6">
            <section class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-4">
                <h2 class="font-extrabold text-base text-gray-900">Withdraw to bank</h2>
                <form id="withdraw-form" class="space-y-3 text-xs">
                    <div><label class="font-bold text-gray-500">Bank account</label>
                        <select name="bank" class="field mt-1">${w.banks.map(b => `<option value="${b.id}">${esc(b.bank || "Bank")} ${mask(b.number)}</option>`).join("") || "<option>No bank account added</option>"}</select></div>
                    <div><label class="font-bold text-gray-500">Points to withdraw</label><input name="amount" type="number" min="${MIN_WITHDRAW}" max="${w.points}" inputmode="numeric" class="field mt-1" placeholder="e.g. 100"></div>
                    <button class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold py-3 rounded-2xl transition">Request Withdrawal</button>
                </form>
            </section>
            <section class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-4">
                <h2 class="font-extrabold text-base text-gray-900">Bank accounts</h2>
                ${w.banks.map(b => `<div class="flex items-center gap-3 p-3 rounded-2xl border border-gray-200 text-xs"><div class="w-9 h-9 rounded-xl bg-sky-100 text-sky-600 flex items-center justify-center"><i data-lucide="landmark" class="w-4 h-4"></i></div>
                    <div class="flex-1 min-w-0"><div class="font-bold text-gray-800">${esc(b.holder)}</div><div class="text-gray-400">${esc(b.bank || "Bank")} • ${mask(b.number)} • ${esc(b.ifsc)}</div></div>
                    <button data-del-bank="${b.id}" class="text-red-500 font-bold hover:underline">Remove</button></div>`).join("")}
                <form id="bank-form" class="grid grid-cols-2 gap-3 text-xs">
                    <div class="col-span-2"><label class="font-bold text-gray-500">Account holder name</label><input name="holder" class="field mt-1" required></div>
                    <div><label class="font-bold text-gray-500">Account number</label><input name="number" inputmode="numeric" maxlength="18" class="field mt-1" required></div>
                    <div><label class="font-bold text-gray-500">Confirm number</label><input name="number2" inputmode="numeric" maxlength="18" class="field mt-1" required></div>
                    <div><label class="font-bold text-gray-500">IFSC code</label><input name="ifsc" maxlength="11" class="field mt-1 uppercase" placeholder="SBIN0001234" required></div>
                    <div><label class="font-bold text-gray-500">Bank name</label><input name="bank" class="field mt-1" placeholder="e.g. SBI"></div>
                    <button class="col-span-2 border-2 border-brand-500 text-brand-600 hover:bg-orange-50 font-bold py-2.5 rounded-2xl transition">+ Add bank account</button>
                </form>
            </section>
        </div>
        <section class="bg-white p-6 rounded-3xl border border-orange-100 shadow-sm space-y-3">
            <h2 class="font-extrabold text-base text-gray-900">Transaction history</h2>
            ${w.txns.map(t => `<div class="flex items-center gap-3 text-xs p-3 rounded-2xl bg-orange-50/40 border border-orange-100">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center ${t.type === "credit" ? "bg-emerald-100 text-emerald-600" : "bg-red-100 text-red-500"}"><i data-lucide="${t.type === "credit" ? "arrow-down-left" : "arrow-up-right"}" class="w-4 h-4"></i></span>
                <div class="flex-1"><div class="font-bold text-gray-800">${esc(t.title)}</div><div class="text-gray-400">${new Date(t.at).toLocaleString("en-IN", { day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" })}</div></div>
                <div class="font-extrabold ${t.type === "credit" ? "text-emerald-600" : "text-red-500"}">${t.type === "credit" ? "+" : "−"}${t.points}</div></div>`).join("")}
        </section>
    </div>`;
    refreshIcons();
}
