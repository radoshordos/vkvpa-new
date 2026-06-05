// Interaktivita aplikačního layoutu: přepínač tmavého režimu + mobilní menu.

// ── Tmavý režim ────────────────────────────────────────────────────────────
// Volba se ukládá do localStorage; výchozí stav respektuje předvolbu systému.
// (Počáteční třída .dark se nastavuje inline skriptem v <head>, aby web neblikal.)
function applyTheme(dark) {
    document.documentElement.classList.toggle('dark', dark);
    try {
        localStorage.setItem('theme', dark ? 'dark' : 'light');
    } catch (e) {
        /* localStorage nemusí být dostupný */
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.querySelector('[data-theme-toggle]');
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            applyTheme(!document.documentElement.classList.contains('dark'));
        });
    }

    // ── Mobilní navigace (off-canvas drawer) ──────────────────────────────
    const drawer = document.querySelector('[data-drawer]');
    const backdrop = document.querySelector('[data-drawer-backdrop]');
    const openBtn = document.querySelector('[data-drawer-open]');
    const closeEls = document.querySelectorAll('[data-drawer-close]');

    const setDrawer = (open) => {
        if (!drawer || !backdrop) return;
        drawer.classList.toggle('-translate-x-full', !open);
        backdrop.classList.toggle('hidden', !open);
        document.body.classList.toggle('overflow-hidden', open);
    };

    if (openBtn) openBtn.addEventListener('click', () => setDrawer(true));
    closeEls.forEach((el) => el.addEventListener('click', () => setDrawer(false)));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') setDrawer(false);
    });
});
