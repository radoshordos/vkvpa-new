// Samostatný EDI Visualizer – mapa spojení jednoho nahraného deníku.
// Inspirováno původním opencontest.org/edi (UT4UKW): domácí QTH, paprsky ke
// všem protistanicím, barevné špendlíky podle druhu provozu, popup se
// vzdáleností/azimutem a odkazem na QRZ. Data dodává show.blade.php přes
// window.__vizMap; geometrie je předpočítaná v PHP (Maidenhead).

import L from 'leaflet';
import { createOsmMap } from './leaflet-osm-map.js';
import {
    createHomeMarker,
    createQsoMarker,
    createQsoRay,
    fitMapToBounds,
    pushPointBounds,
    qsoPopupHtml,
} from './leaflet-qso-map.js';
import { redrawMaidenheadGrid } from './maidenhead-grid.js';

const cfg = window.__vizMap || {};
const t = cfg.t || {};

const mapEl = document.getElementById('viz-map');
if (mapEl) {
    const map = createOsmMap('viz-map');

    const bounds = [];

    // Domácí stanoviště – výrazný modrý bod.
    if (cfg.home) {
        createHomeMarker(
            cfg.home,
            `<strong>${cfg.pcall || ''}</strong><br>${cfg.homeLoc || ''}` +
                (cfg.band ? `<br>${cfg.band}` : '') +
                `<br><span style="opacity:.6">${t.home || ''}</span>`,
        ).addTo(map);
        pushPointBounds(bounds, cfg.home);
    }

    // Paprsky + špendlíky ke všem protistanicím.
    (cfg.points || []).forEach(function (p) {
        pushPointBounds(bounds, p);

        if (cfg.home) {
            createQsoRay(cfg.home, p).addTo(map);
        }

        createQsoMarker(p).addTo(map).bindPopup(qsoPopupHtml(p, {
            linkCall: true,
            includeDistance: true,
            includeAzimuth: true,
            azimuthLabel: t.azimuth || 'azimut',
            pointsLabel: t.pts || '',
        }));
    });

    // ── Rastr velkých čtverců Maidenhead (2° délky × 1° šířky) ─────────────
    // Překresluje se podle výřezu mapy; názvy čtverců jen při rozumném zoomu.
    const gridLayer = L.layerGroup().addTo(map);

    function redrawGrid() {
        redrawMaidenheadGrid(map, gridLayer);
    }

    map.on('moveend zoomend', redrawGrid);

    fitMapToBounds(map, bounds, { padding: [30, 30], fallbackZoom: 5 });

    redrawGrid();
}

// Kopírování sdílecího odkazu (CSP blokuje onclick → delegovaný posluchač).
document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-copy-share]');
    if (!btn) return;

    const input = document.querySelector('[data-share-url]');
    if (!input) return;

    const done = () => {
        const orig = btn.textContent;
        btn.textContent = btn.dataset.copied || orig;
        setTimeout(() => { btn.textContent = orig; }, 1500);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value).then(done, () => { input.select(); document.execCommand('copy'); done(); });
    } else {
        input.select();
        document.execCommand('copy');
        done();
    }
});

// Tap/klik do pole s odkazem označí celý text (na mobilu snadné zkopírování ručně).
document.addEventListener('focusin', function (e) {
    if (e.target.matches && e.target.matches('[data-share-url]')) e.target.select();
});
