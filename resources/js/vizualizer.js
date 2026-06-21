// Samostatný EDI Visualizer – mapa spojení jednoho nahraného deníku.
// Inspirováno původním opencontest.org/edi (UT4UKW): domácí QTH, paprsky ke
// všem protistanicím, barevné špendlíky podle druhu provozu, popup se
// vzdáleností/azimutem a odkazem na QRZ. Data dodává show.blade.php přes
// window.__vizMap; geometrie je předpočítaná v PHP (Maidenhead).

import L from 'leaflet';
import { createOsmMap } from './leaflet-osm-map.js';
import { modeColor, modeLabel } from './leaflet-mode-colors.js';
import { redrawMaidenheadGrid } from './maidenhead-grid.js';

const cfg = window.__vizMap || {};
const t = cfg.t || {};

const mapEl = document.getElementById('viz-map');
if (mapEl) {
    const map = createOsmMap('viz-map');

    const bounds = [];

    // Domácí stanoviště – výrazný modrý bod.
    if (cfg.home) {
        L.circleMarker([cfg.home.lat, cfg.home.lon], {
            radius: 8, color: '#1d4ed8', fillColor: '#3b82f6', fillOpacity: 0.9, weight: 2,
        }).addTo(map).bindPopup(
            `<strong>${cfg.pcall || ''}</strong><br>${cfg.homeLoc || ''}` +
            (cfg.band ? `<br>${cfg.band}` : '') +
            `<br><span style="opacity:.6">${t.home || ''}</span>`,
        );
        bounds.push([cfg.home.lat, cfg.home.lon]);
    }

    // Paprsky + špendlíky ke všem protistanicím.
    (cfg.points || []).forEach(function (p) {
        bounds.push([p.lat, p.lon]);
        const mc = modeColor(p.mode);

        if (cfg.home) {
            L.polyline([[cfg.home.lat, cfg.home.lon], [p.lat, p.lon]], {
                color: mc.fill, weight: 1.2, opacity: 0.55,
            }).addTo(map);
        }

        const call = p.call || '';
        const popup =
            `<strong><a href="https://www.qrz.com/db/${encodeURIComponent(call)}" target="_blank" rel="noopener">${call}</a></strong>` +
            ` <span style="font-size:.8em;opacity:.7">${modeLabel(p.mode)}</span><br>${p.wwl}` +
            (p.dist !== null ? `<br>${p.dist} km` : '') +
            (p.azimut !== null ? `<br>${t.azimuth || 'azimut'} ${p.azimut}°` : '') +
            `<br>${p.points} ${t.pts || ''}`;

        L.circleMarker([p.lat, p.lon], {
            radius: 5, color: mc.stroke, fillColor: mc.fill, fillOpacity: 0.9, weight: 1.5,
        }).addTo(map).bindPopup(popup);
    });

    // ── Rastr velkých čtverců Maidenhead (2° délky × 1° šířky) ─────────────
    // Překresluje se podle výřezu mapy; názvy čtverců jen při rozumném zoomu.
    const gridLayer = L.layerGroup().addTo(map);

    function redrawGrid() {
        redrawMaidenheadGrid(map, gridLayer);
    }

    map.on('moveend zoomend', redrawGrid);

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [30, 30] });
    } else {
        map.setView([50, 15], 5);
    }

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
