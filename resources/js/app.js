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

// ── Místní čas vedle UTC ───────────────────────────────────────────────────
// <span data-local-time="unix" data-local-suffix="místního času"> – doplní
// čas v časové zóně prohlížeče. Vynechá se, když je místní čas shodný s UTC,
// a bez JS zůstane stránka beze změny (server renderuje jen UTC).
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-local-time]').forEach((el) => {
        const ts = parseInt(el.dataset.localTime, 10);
        if (!Number.isFinite(ts)) return;
        const date = new Date(ts * 1000);
        if (date.getTimezoneOffset() === 0) return;
        const time = new Intl.DateTimeFormat(document.documentElement.lang || 'cs', {
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
        el.textContent = `(${time} ${el.dataset.localSuffix || ''})`.trim();
    });
});

// ── Globální delegované handlery ───────────────────────────────────────────
// Náhrada za inline onchange/onclick atributy – ty CSP s nonce blokuje
// (event handler atribut nonce nést nemůže). Delegace na document funguje
// i pro DOM přerenderovaný Livewirem.

// <select data-autosubmit> – změna hodnoty odešle nejbližší formulář.
document.addEventListener('change', (e) => {
    const el = e.target instanceof Element ? e.target.closest('[data-autosubmit]') : null;
    if (el && el.form) el.form.submit();
});

// <input type=file data-file-zone="id" data-file-name="id"> – zvýrazní
// drop-zónu a ukáže jméno vybraného souboru.
document.addEventListener('change', (e) => {
    const input = e.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'file' || !input.dataset.fileZone) return;
    const zone = document.getElementById(input.dataset.fileZone);
    const name = document.getElementById(input.dataset.fileName || '');
    const file = input.files && input.files[0];
    if (zone) zone.classList.toggle('has-file', !!file);
    if (name) name.textContent = file ? file.name : '';
});
