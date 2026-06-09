import L from 'leaflet';
import './leaflet-fullscreen.css';

// Vlastní Leaflet ovládací prvek: přepnutí mapy z okna na celou obrazovku a
// zpět. Staví na nativním Fullscreen API nad kontejnerem mapy – bez další
// závislosti. Po každé změně velikosti se volá map.invalidateSize(), aby
// Leaflet přepočítal rozměry a dotáhl dlaždice.

// Feather „maximize" / „minimize".
const EXPAND_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m13-5h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3m13 5h3a2 2 0 0 0 2-2v-3"/></svg>';
const COLLAPSE_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3M3 16h3a2 2 0 0 1 2 2v3m13-5h-3a2 2 0 0 0-2 2v3"/></svg>';

function fullscreenElement() {
    return document.fullscreenElement || document.webkitFullscreenElement || null;
}

function requestFullscreen(el) {
    const fn = el.requestFullscreen || el.webkitRequestFullscreen;
    if (fn) fn.call(el);
}

function exitFullscreen() {
    const fn = document.exitFullscreen || document.webkitExitFullscreen;
    if (fn) fn.call(document);
}

export function addFullscreenControl(map) {
    const container = map.getContainer();

    const FullscreenControl = L.Control.extend({
        options: { position: 'topleft' },
        onAdd() {
            const wrap = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
            const btn = L.DomUtil.create('a', 'leaflet-control-fullscreen-btn', wrap);
            btn.href = '#';
            btn.role = 'button';
            btn.title = 'Celá obrazovka';
            btn.setAttribute('aria-label', 'Celá obrazovka');
            btn.innerHTML = EXPAND_ICON;

            L.DomEvent.disableClickPropagation(wrap);
            L.DomEvent.on(btn, 'click', function (e) {
                L.DomEvent.preventDefault(e);
                if (fullscreenElement()) {
                    exitFullscreen();
                } else {
                    requestFullscreen(container);
                }
            });

            this._btn = btn;
            return wrap;
        },
    });

    const control = new FullscreenControl();
    map.addControl(control);

    function sync() {
        const active = fullscreenElement() === container;
        if (control._btn) {
            control._btn.innerHTML = active ? COLLAPSE_ICON : EXPAND_ICON;
            const label = active ? 'Ukončit celou obrazovku' : 'Celá obrazovka';
            control._btn.title = label;
            control._btn.setAttribute('aria-label', label);
        }
        // Kontejner změnil velikost → Leaflet musí přepočítat rozměry.
        setTimeout(() => map.invalidateSize(), 120);
    }

    document.addEventListener('fullscreenchange', sync);
    document.addEventListener('webkitfullscreenchange', sync);
}
