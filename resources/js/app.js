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

// Výkonové checkboxy QRP/LP: zakliknutím jednoho se druhý ve stejné skupině
// odznačí (vzájemně se vylučují). Skupina = nejbližší [data-power-group],
// členové = [data-power-exclusive]. Po odznačení vyšleme „input", aby se
// případný Livewire wire:model synchronizoval.
document.addEventListener('change', (e) => {
    const el = e.target instanceof HTMLInputElement ? e.target.closest('[data-power-exclusive]') : null;
    if (!el || !el.checked) return;
    const group = el.closest('[data-power-group]');
    if (!group) return;
    group.querySelectorAll('[data-power-exclusive]').forEach((other) => {
        if (other !== el && other.checked) {
            other.checked = false;
            other.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });
});

// <input type=file data-file-zone="id" data-file-name="id"> – zvýrazní
// drop-zónu a ukáže jméno vybraného souboru.
function refreshFileZone(input) {
    if (!(input instanceof HTMLInputElement) || input.type !== 'file' || !input.dataset.fileZone) return;
    const zone = document.getElementById(input.dataset.fileZone);
    const name = document.getElementById(input.dataset.fileName || '');
    const file = input.files && input.files[0];
    if (zone) zone.classList.toggle('has-file', !!file);
    if (name) name.textContent = file ? file.name : '';
}

document.addEventListener('change', (e) => {
    refreshFileZone(e.target);
});

// Drag & drop: skrytý <input> uvnitř .upload-zone nedostane „drop“ sám,
// proto soubor zachytíme na zóně a vložíme ho do inputu ručně.
function fileInputForZone(zone) {
    return zone ? zone.querySelector('input[type=file][data-file-zone]') : null;
}

['dragenter', 'dragover'].forEach((type) => {
    document.addEventListener(type, (e) => {
        const zone = e.target.closest && e.target.closest('.upload-zone');
        if (!zone || !fileInputForZone(zone)) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        zone.classList.add('dragover');
    });
});

document.addEventListener('dragleave', (e) => {
    const zone = e.target.closest && e.target.closest('.upload-zone');
    // dragleave se ozve i při přechodu mezi vnořenými prvky – reagujeme
    // jen když kurzor opustí zónu úplně.
    if (!zone || (e.relatedTarget && zone.contains(e.relatedTarget))) return;
    zone.classList.remove('dragover');
});

document.addEventListener('drop', (e) => {
    const zone = e.target.closest && e.target.closest('.upload-zone');
    const input = fileInputForZone(zone);
    if (!input) return;
    e.preventDefault();
    zone.classList.remove('dragover');
    const files = e.dataTransfer && e.dataTransfer.files;
    if (!files || !files.length) return;
    input.files = files;
    refreshFileZone(input);
    input.dispatchEvent(new Event('change', { bubbles: true }));
});
