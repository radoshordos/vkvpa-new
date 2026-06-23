// Živá mapa + persistence pro ruční generátor EDI deníku
// (App\Livewire\EdiGenerator / resources/views/livewire/edi-generator.blade.php).
//
// Mapa je v `wire:ignore` kontejneru, takže ji Livewire při překreslení nesahá;
// data o spojeních a stav formuláře čteme z data-* atributů skrytého
// `#edi-gen-data`, který se naopak při každé změně překresluje. Po každém
// Livewire commitu mapu překreslíme a stav uložíme do localStorage; na čisté
// stránce stav z localStorage obnovíme zpět do komponenty.

import L from 'leaflet';
import { createOsmMap } from './leaflet-osm-map.js';
import { modeColor, modeLabel } from './leaflet-mode-colors.js';

const KEY = 'vkvpa:edi-generator';

let map = null;
let homeMarker = null;
let qsoLayer = null;
let restored = false;

function dataEl() {
    return document.getElementById('edi-gen-data');
}

function parse(json, fallback) {
    try {
        return JSON.parse(json);
    } catch {
        return fallback;
    }
}

function ensureMap() {
    if (map || !document.getElementById('edi-gen-mapa')) return;
    map = createOsmMap('edi-gen-mapa');
    map.setView([49.8, 15.5], 6); // střed ČR jako výchozí pohled
    qsoLayer = L.layerGroup().addTo(map);
}

function redraw() {
    const el = dataEl();
    if (!el) return;
    ensureMap();
    if (!map) return;

    const home = parse(el.dataset.home, null);
    const points = parse(el.dataset.points, []);

    qsoLayer.clearLayers();
    if (homeMarker) {
        map.removeLayer(homeMarker);
        homeMarker = null;
    }

    const bounds = [];

    if (home) {
        homeMarker = L.circleMarker([home.lat, home.lon], {
            radius: 8, color: '#1d4ed8', fillColor: '#3b82f6', fillOpacity: 0.9, weight: 2,
        }).addTo(map);
        bounds.push([home.lat, home.lon]);
    }

    points.forEach(function (p) {
        const mc = modeColor(p.mode);
        if (home) {
            L.polyline([[home.lat, home.lon], [p.lat, p.lon]], {
                color: mc.fill, weight: 1.2, opacity: 0.55,
            }).addTo(qsoLayer);
        }
        const popup = `<strong>${p.call}</strong> <span style="font-size:.8em;opacity:.7">${modeLabel(p.mode)}</span>`
            + `<br>${p.wwl}`
            + (p.dist !== null ? `<br>${p.dist} km` : '')
            + (p.azimut !== null ? `<br>${p.azimut}°` : '')
            + `<br>${p.points} b.`;
        L.circleMarker([p.lat, p.lon], {
            radius: 5, color: mc.stroke, fillColor: mc.fill, fillOpacity: 0.9, weight: 1.5,
        }).bindPopup(popup).addTo(qsoLayer);
        bounds.push([p.lat, p.lon]);
    });

    if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [30, 30], maxZoom: 9 });
    } else if (bounds.length === 1) {
        map.setView(bounds[0], 8);
    }

    // Leaflet po vložení do dříve skrytého kontejneru potřebuje přeměřit.
    setTimeout(() => map.invalidateSize(), 0);
}

function persist() {
    const el = dataEl();
    if (el && el.dataset.state) {
        localStorage.setItem(KEY, el.dataset.state);
    }
}

function componentFor(el) {
    const root = el.closest('[wire\\:id]');
    if (!root || !window.Livewire) return null;
    return window.Livewire.find(root.getAttribute('wire:id'));
}

// Stav je „prázdný", když uživatel ještě nic nezadal – jen tehdy přepíšeme
// formulář uloženým konceptem (jinak bychom přemazali rozdělanou práci).
function isEmptyState(state) {
    if (!state) return true;
    if (state.pcall) return false;
    const qsos = Array.isArray(state.qsos) ? state.qsos : [];
    return qsos.every((q) => !q.call && !q.wwl && !q.time);
}

function maybeRestore() {
    if (restored) return;
    const el = dataEl();
    if (!el) return;
    restored = true;

    const current = parse(el.dataset.state, {});
    if (!isEmptyState(current)) return;

    const saved = parse(localStorage.getItem(KEY), null);
    if (!saved || isEmptyState(saved)) return;

    const cmp = componentFor(el);
    if (cmp) cmp.call('restoreState', saved);
}

function boot() {
    if (!dataEl()) return; // nejsme na stránce generátoru
    redraw();
    persist();
    maybeRestore();
}

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('commit', ({ succeed }) => {
        succeed(() => queueMicrotask(() => {
            redraw();
            persist();
        }));
    });
});

document.addEventListener('livewire:initialized', boot);
document.addEventListener('livewire:navigated', boot);
