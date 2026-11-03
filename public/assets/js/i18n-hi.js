/* Hindi for content a page script draws in the browser (the server already translated the page itself, see App\Services\HindiPage). The texts
   come from lang/hi.json via window.TAKSH_I18N; only a whole text (or placeholder) that matches an entry exactly is replaced. */

const I18N_HI_PAGE = window.TAKSH_I18N || {};
const I18N_HI_ATTR = I18N_HI_PAGE;

(function () {
    const SKIP = new Set(["SCRIPT", "STYLE", "TEXTAREA", "INPUT", "NOSCRIPT"]);
    let queued = false;

    function walk(root) {
        const dict = I18N_HI_PAGE;
        const w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, { acceptNode: n => (n.parentElement && !SKIP.has(n.parentElement.tagName) && !n.parentElement.closest("[data-no-i18n]")) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT });
        const hits = [];
        for (let n = w.nextNode(); n; n = w.nextNode()) {
            const t = n.nodeValue.trim();
            if (t && dict[t]) hits.push([n, t]);
        }
        hits.forEach(([n, t]) => { n.nodeValue = n.nodeValue.replace(t, dict[t]); });
        root.querySelectorAll("[placeholder]").forEach(el => { const p = I18N_HI_ATTR[el.getAttribute("placeholder")]; if (p) el.setAttribute("placeholder", p); });
    }

    function run() { queued = false; if (document.body) walk(document.body); }
    function queue() { if (!queued) { queued = true; requestAnimationFrame(run); } }

    /** Called once by ui.js: translates now and again whenever a page script draws new content. */
    window.startHindiPage = function () {
        if (document.documentElement.lang !== "hi") return;
        run();
        new MutationObserver(queue).observe(document.body, { childList: true, subtree: true, characterData: false });
    };
})();
