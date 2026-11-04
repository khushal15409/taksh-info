/* TakshAllInOne - this browser's preferences: language, theme, the delivery pincode / city shown in the header, and recent searches.
   Nothing else lives in the browser: the cart, wishlist, orders and profile are the server's. */

const Prefs = (() => {
    const KEY = "taksh_prefs";
    const listeners = [];
    const defaults = () => ({ lang: "en", theme: "light", pincode: "", city: "", searches: [], notify: [] });

    let state = load();

    function load() {
        try {
            const saved = JSON.parse(localStorage.getItem(KEY));
            if (saved && typeof saved === "object") return Object.assign(defaults(), saved);
        } catch (e) { /* storage unavailable */ }
        return defaults();
    }

    function save() {
        try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) { /* ignore */ }
        listeners.forEach(fn => fn(state));
    }

    return { get state() { return state; }, save, subscribe: (fn) => listeners.push(fn) };
})();

// dark mode as early as possible (the page head also applies it before first paint)
document.documentElement.classList.remove("dark");   // one (light) theme
