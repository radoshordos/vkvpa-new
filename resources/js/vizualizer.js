// Samostatný EDI Visualizer – mapa spojení jednoho nahraného deníku.
// Inspirováno původním opencontest.org/edi (UT4UKW): domácí QTH, paprsky ke
// všem protistanicím, barevné špendlíky podle druhu provozu, popup se
// vzdáleností/azimutem a odkazem na QRZ. Data dodává show.blade.php přes
// window.__vizMap; geometrie je předpočítaná v PHP (Maidenhead).

import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { addFullscreenControl } from './leaflet-fullscreen.js';

const cfg = window.__vizMap || {};
const t = cfg.t || {};

// Barvy dle druhu provozu (shodně s vizualizace.js): 1=SSB modrá, 2=CW oranžová.
function modeColor(mode) {
    if (mode === 1) return { stroke: '#1d4ed8', fill: '#60a5fa' };
    if (mode === 2) return { stroke: '#b45309', fill: '#fbbf24' };
    return { stroke: '#4b5563', fill: '#9ca3af' };
}
function modeLabel(mode) {
    if (mode === 1) return 'SSB';
    if (mode === 2) return 'CW';
    return '?';
}

const mapEl = document.getElementById('viz-map');
if (mapEl) {
    const map = L.map('viz-map');
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);
    addFullscreenControl(map);

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

    function bigSquareName(lng, lat) {
        const a = 'A'.charCodeAt(0);
        const fieldLng = Math.floor((lng + 180) / 20);
        const fieldLat = Math.floor((lat + 90) / 10);
        const sqLng = Math.floor(((lng + 180) % 20) / 2);
        const sqLat = Math.floor((lat + 90) % 10);
        return String.fromCharCode(a + fieldLng) + String.fromCharCode(a + fieldLat) + sqLng + sqLat;
    }

    function redrawGrid() {
        gridLayer.clearLayers();
        const b = map.getBounds();
        const zoom = map.getZoom();
        const west = Math.floor(b.getWest() / 2) * 2;
        const east = Math.ceil(b.getEast() / 2) * 2;
        const south = Math.floor(b.getSouth());
        const north = Math.ceil(b.getNorth());

        for (let lat = south; lat <= north; lat++) {
            L.polyline([[lat, west], [lat, east]], { color: '#000', weight: 1, opacity: 0.3 }).addTo(gridLayer);
        }
        for (let lng = west; lng <= east; lng += 2) {
            L.polyline([[south, lng], [north, lng]], { color: '#000', weight: 1, opacity: 0.3 }).addTo(gridLayer);
        }

        if (zoom >= 5 && zoom <= 9) {
            for (let lng = west; lng < east; lng += 2) {
                for (let lat = south; lat < north; lat++) {
                    L.marker([lat + 0.5, lng + 1], {
                        icon: L.divIcon({ className: 'loc-label', html: bigSquareName(lng, lat), iconSize: null }),
                        interactive: false,
                    }).addTo(gridLayer);
                }
            }
        }
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
