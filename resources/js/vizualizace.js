import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { Chart, registerables } from 'chart.js';
import { addFullscreenControl } from './leaflet-fullscreen.js';

Chart.register(...registerables);

const cfg = window.__vizConfig;

// ── Leaflet mapa s přepínatelnými vrstvami ─────────────────────────────────

const map = L.map('viz-mapa');
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap',
}).addTo(map);
addFullscreenControl(map);

const bounds = [];

// Domácí stanoviště
let homeMarker = null;
if (cfg.home) {
    homeMarker = L.circleMarker([cfg.home.lat, cfg.home.lon], {
        radius: 8, color: '#1d4ed8', fillColor: '#3b82f6', fillOpacity: 0.9, weight: 2,
    }).addTo(map).bindPopup(`<strong>${cfg.pcall}</strong><br>${cfg.homeLoc}`);
    bounds.push([cfg.home.lat, cfg.home.lon]);
}

// Vrstva: ježek (čáry + body)
const jezekLayer = L.layerGroup();
// Vrstva: špendlíky
const spendlikyLayer = L.layerGroup();
// Vrstva: lokátory (velké čtverce)
const lokatoryLayer = L.layerGroup();
// Vrstva: CRK – kombinovaná mapa (paprsky + provoz + kružnice + mřížka + stanice z kola)
const crkLayer = L.layerGroup();

// Barvy dle druhu provozu: 1=SSB (modrá), 2=CW (oranžová), ostatní (šedá)
function modeColor(mode) {
    if (mode === 1) return { stroke: '#1d4ed8', fill: '#60a5fa' }; // SSB – modrá
    if (mode === 2) return { stroke: '#b45309', fill: '#fbbf24' }; // CW  – oranžová
    return { stroke: '#4b5563', fill: '#9ca3af' };                 // neznámý
}
function modeLabel(mode) {
    if (mode === 1) return 'SSB';
    if (mode === 2) return 'CW';
    return '?';
}

cfg.points.forEach(function (p) {
    bounds.push([p.lat, p.lon]);
    const mc = modeColor(p.mode);

    // ježek: čára + barevný bod dle modu
    if (cfg.home) {
        L.polyline([[cfg.home.lat, cfg.home.lon], [p.lat, p.lon]], {
            color: mc.fill, weight: 1.2, opacity: 0.55,
        }).addTo(jezekLayer);
    }
    L.circleMarker([p.lat, p.lon], {
        radius: 5, color: mc.stroke, fillColor: mc.fill, fillOpacity: 0.9, weight: 1.5,
    }).addTo(jezekLayer)
        .bindPopup(`<strong>${p.call}</strong> <span style="font-size:.8em;opacity:.7">${modeLabel(p.mode)}</span><br>${p.wwl}<br>${p.points} b.`);

    // špendlíky – taktéž rozlišené barevně
    const popupSpend = `<strong>${p.call}</strong> <span style="font-size:.8em;opacity:.7">${modeLabel(p.mode)}</span><br>${p.wwl}`
        + (p.dist !== null ? `<br>${p.dist} km` : '')
        + (p.azimut !== null ? `<br>azimut ${p.azimut}°` : '');
    L.circleMarker([p.lat, p.lon], {
        radius: 5, color: mc.stroke, fillColor: mc.fill, fillOpacity: 0.9, weight: 1.5,
    }).addTo(spendlikyLayer)
        .bindPopup(popupSpend);
});

// Legenda módů v ježek/špendlíky vrstvě
const modeLegend = L.control({ position: 'bottomright' });
modeLegend.onAdd = function () {
    const div = L.DomUtil.create('div');
    div.style.cssText = 'background:rgba(255,255,255,.9);padding:6px 10px;border-radius:6px;font-size:12px;line-height:1.7;box-shadow:0 1px 4px rgba(0,0,0,.2)';
    div.innerHTML = '<strong style="display:block;margin-bottom:2px">Druh provozu</strong>'
        + '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#60a5fa;margin-right:5px;vertical-align:middle"></span>SSB<br>'
        + '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#fbbf24;margin-right:5px;vertical-align:middle"></span>CW';
    return div;
};
modeLegend.addTo(map);

cfg.squares.forEach(function (s) {
    bounds.push([s.lat, s.lon]);
    L.circleMarker([s.lat, s.lon], {
        radius: 14, color: '#7c3aed', fillColor: '#a855f7', fillOpacity: 0.65, weight: 2,
    }).addTo(lokatoryLayer)
        .bindTooltip(String(s.count), { permanent: true, direction: 'center', className: 'sq-label' })
        .bindPopup(`<strong>${s.square}</strong><br>${s.count} protistanic`);
});

// ── Vrstva: porovnání s deníkem soupeře (po vzoru vushf.dk) ────────────────
// Zelené body udělal jen tento deník, červené jen soupeř, šedé oba.
// Server data vydá jen po uzávěrce kola (cfg.compare === null jinak).

const porovnaniLayer = L.layerGroup();
const compareLegend = L.control({ position: 'bottomright' });

function comparePin(s, color, owner) {
    return L.circleMarker([s.lat, s.lon], {
        radius: 5, color: color.stroke, fillColor: color.fill, fillOpacity: 0.9, weight: 1.5,
    }).bindPopup(`<strong>${s.call}</strong><br>${s.wwl}`
        + (s.dist !== null ? `<br>${s.dist} km` : '')
        + `<br><em>${owner}</em>`);
}

if (cfg.compare) {
    const cmp = cfg.compare;
    const mineCol = { stroke: '#15803d', fill: '#4ade80' };  // zelená – jen můj deník
    const rivalCol = { stroke: '#b91c1c', fill: '#f87171' }; // červená – jen soupeř
    const bothCol = { stroke: '#4b5563', fill: '#9ca3af' };  // šedá – oba

    if (cmp.rivalHome) {
        L.circleMarker([cmp.rivalHome.lat, cmp.rivalHome.lon], {
            radius: 8, color: '#b45309', fillColor: '#f59e0b', fillOpacity: 0.9, weight: 2,
        }).addTo(porovnaniLayer).bindPopup(`<strong>${cmp.rival}</strong><br>${cmp.rivalLoc}`);
        bounds.push([cmp.rivalHome.lat, cmp.rivalHome.lon]);
    }

    cmp.both.forEach((s) => comparePin(s, bothCol, 'udělali oba').addTo(porovnaniLayer));
    cmp.onlyMine.forEach((s) => comparePin(s, mineCol, `jen ${cfg.pcall}`).addTo(porovnaniLayer));
    cmp.onlyRival.forEach((s) => {
        comparePin(s, rivalCol, `jen ${cmp.rival}`).addTo(porovnaniLayer);
        bounds.push([s.lat, s.lon]); // body z mého deníku už v bounds jsou
    });

    compareLegend.onAdd = function () {
        const div = L.DomUtil.create('div');
        div.style.cssText = 'background:rgba(255,255,255,.9);padding:6px 10px;border-radius:6px;font-size:12px;line-height:1.7;box-shadow:0 1px 4px rgba(0,0,0,.2)';
        const dot = (c) => `<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${c};margin-right:5px;vertical-align:middle"></span>`;
        div.innerHTML = `<strong style="display:block;margin-bottom:2px">${cfg.pcall} vs. ${cmp.rival}</strong>`
            + dot(mineCol.fill) + `jen ${cfg.pcall} (${cmp.onlyMine.length})<br>`
            + dot(rivalCol.fill) + `jen ${cmp.rival} (${cmp.onlyRival.length})<br>`
            + dot(bothCol.fill) + `oba (${cmp.both.length})`;
        return div;
    };
}

// ── Vrstva CRK: kombinovaná mapa ve stylu vkvzavody.crk.cz ─────────────────

// Kružnice vzdáleností po 200 km (až 1200 km) s popiskem na severu.
if (cfg.home) {
    for (let r = 200000; r <= 1200000; r += 200000) {
        L.circle([cfg.home.lat, cfg.home.lon], {
            radius: r, color: 'red', weight: 1, fill: false, opacity: 0.5,
        }).addTo(crkLayer);
        const latOffset = r / 111320; // ~ stupně zem. šířky na metr
        L.marker([cfg.home.lat + latOffset, cfg.home.lon], {
            icon: L.divIcon({ className: 'km-label', html: r / 1000 + ' km', iconSize: null }),
            interactive: false,
        }).addTo(crkLayer);
    }
}

// Paprsky z QTH do protistanic + špendlíky barevně dle druhu provozu.
cfg.points.forEach(function (p) {
    const mc = modeColor(p.mode);
    if (cfg.home) {
        L.polyline([[cfg.home.lat, cfg.home.lon], [p.lat, p.lon]], {
            color: '#cc0000', weight: 1, opacity: 0.35,
        }).addTo(crkLayer);
    }
    const popup = `<strong>${p.call}</strong> <span style="font-size:.8em;opacity:.7">${modeLabel(p.mode)}</span><br>${p.wwl}`
        + (p.dist !== null ? `<br>${p.dist} km` : '')
        + (p.azimut !== null ? `<br>azimut ${p.azimut}°` : '');
    L.circleMarker([p.lat, p.lon], {
        radius: 4, color: mc.stroke, fillColor: mc.fill, fillOpacity: 0.85, weight: 1.5,
    }).addTo(crkLayer).bindPopup(popup);
});

// Všechny stanice z kola s ≥ 5 QSO.
(cfg.roundStations || []).forEach(function (s) {
    L.circleMarker([s.lat, s.lon], {
        radius: 3, color: '#9933cc', fillColor: '#cc66ff', fillOpacity: 0.7,
    }).addTo(crkLayer).bindPopup(`<strong>${s.call}</strong><br>${s.wwl}<br>${s.count} QSO`);
});

// Mřížka velkých čtverců (2° délky × 1° šířky) – překresluje se podle výřezu,
// dokud je vrstva CRK aktivní (viz přepínání vrstev níže).
const crkGrid = L.layerGroup().addTo(crkLayer);

function bigSquareName(lng, lat) {
    const a = 'A'.charCodeAt(0);
    const fieldLng = Math.floor((lng + 180) / 20);
    const fieldLat = Math.floor((lat + 90) / 10);
    const sqLng = Math.floor(((lng + 180) % 20) / 2);
    const sqLat = Math.floor((lat + 90) % 10);
    return String.fromCharCode(a + fieldLng) + String.fromCharCode(a + fieldLat) + sqLng + sqLat;
}

function redrawCrkGrid() {
    crkGrid.clearLayers();
    const b = map.getBounds();
    const zoom = map.getZoom();
    const west = Math.floor(b.getWest() / 2) * 2;
    const east = Math.ceil(b.getEast() / 2) * 2;
    const south = Math.floor(b.getSouth());
    const north = Math.ceil(b.getNorth());

    for (let lat = south; lat <= north; lat++) {
        L.polyline([[lat, west], [lat, east]], { color: '#000', weight: 1, opacity: 0.3 }).addTo(crkGrid);
    }
    for (let lng = west; lng <= east; lng += 2) {
        L.polyline([[south, lng], [north, lng]], { color: '#000', weight: 1, opacity: 0.3 }).addTo(crkGrid);
    }

    // Názvy velkých čtverců jen při rozumném přiblížení (jinak by se slily).
    if (zoom >= 5 && zoom <= 9) {
        for (let lng = west; lng < east; lng += 2) {
            for (let lat = south; lat < north; lat++) {
                L.marker([lat + 0.5, lng + 1], {
                    icon: L.divIcon({ className: 'loc-label', html: bigSquareName(lng, lat), iconSize: null }),
                    interactive: false,
                }).addTo(crkGrid);
            }
        }
    }
}

// Výřez nastavíme dřív, než zapneme vrstvu – mřížka CRK čte map.getBounds().
if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [24, 24] });
} else {
    map.setView([50, 15], 6);
}

// Přepínání vrstev přes tlačítka
const layers = { jezek: jezekLayer, spendliky: spendlikyLayer, lokatory: lokatoryLayer, crk: crkLayer };
if (cfg.compare) layers.porovnani = porovnaniLayer;

function showLayer(key) {
    Object.values(layers).forEach((l) => map.removeLayer(l));
    if (homeMarker) map.removeLayer(homeMarker);
    map.off('moveend', redrawCrkGrid);
    layers[key].addTo(map);
    if (key === 'crk') { redrawCrkGrid(); map.on('moveend', redrawCrkGrid); }
    if (homeMarker) homeMarker.addTo(map);
    // Legenda druhů provozu nedává ve vrstvě porovnání smysl – prohodí se s legendou porovnání.
    if (cfg.compare) {
        if (key === 'porovnani') { modeLegend.remove(); compareLegend.addTo(map); }
        else { compareLegend.remove(); modeLegend.addTo(map); }
    }
    document.querySelectorAll('[data-map-layer]').forEach((b) => b.classList.toggle('active', b.dataset.mapLayer === key));
}

document.querySelectorAll('[data-map-layer]').forEach(function (btn) {
    btn.addEventListener('click', () => showLayer(btn.dataset.mapLayer));
});

// Výchozí vrstva: porovnání (když je zvolen soupeř), jinak CRK (kombinovaná mapa).
showLayer(cfg.compare ? 'porovnani' : 'crk');

// ── Chart.js: barvy podle motivu (denní/noční) ─────────────────────────────
// Text i mřížku grafů bereme z CSS proměnných motivu (--muted, --line), takže
// ladí s paletou a s přepnutím třídy .dark. Výchozí šedá Chart.js by byla
// v nočním režimu nečitelná a mřížka neviditelná.
function applyChartTheme() {
    const css = getComputedStyle(document.documentElement);
    Chart.defaults.color = css.getPropertyValue('--muted').trim() || '#666';
    Chart.defaults.borderColor = css.getPropertyValue('--line').trim() || 'rgba(0,0,0,.1)';
}
applyChartTheme();

const charts = [];

// ── Chart.js: Časová osa QSO (bar) ────────────────────────────────────────

charts.push(new Chart(document.getElementById('chartTimeline'), {
    type: 'bar',
    data: {
        labels: Object.keys(cfg.timeline),
        datasets: [{
            label: 'QSO',
            data: Object.values(cfg.timeline),
            backgroundColor: 'rgba(59, 130, 246, 0.75)',
            borderColor: 'rgba(59, 130, 246, 1)',
            borderWidth: 1,
            borderRadius: 3,
        }],
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            title: { display: true, text: 'QSO v čase (15min intervaly)', font: { size: 13 } },
        },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } },
            x: { grid: { display: false } },
        },
    },
}));

// ── Chart.js: Azimutová růžice (polar area) ───────────────────────────────

const azColors = [
    'rgba(239,68,68,.75)', 'rgba(251,146,60,.75)', 'rgba(250,204,21,.75)', 'rgba(74,222,128,.75)',
    'rgba(34,211,238,.75)', 'rgba(96,165,250,.75)', 'rgba(167,139,250,.75)', 'rgba(236,72,153,.75)',
];

charts.push(new Chart(document.getElementById('chartAzimuth'), {
    type: 'polarArea',
    data: {
        labels: cfg.azimuth.labels,
        datasets: [{
            data: cfg.azimuth.data,
            backgroundColor: azColors,
            borderColor: azColors.map((c) => c.replace('.75)', '1)')),
            borderWidth: 1,
        }],
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } },
            title: { display: true, text: 'Směry QSO (azimutová růžice)', font: { size: 13 } },
        },
        scales: {
            r: {
                beginAtZero: true,
                // Průhledné pozadí popisků os – bílý backdrop by v nočním režimu rušil.
                ticks: { stepSize: 1, font: { size: 10 }, backdropColor: 'transparent' },
            },
        },
        startAngle: -Math.PI / 8,
    },
}));

// ── Chart.js: Histogram vzdáleností (bar) ─────────────────────────────────

charts.push(new Chart(document.getElementById('chartDist'), {
    type: 'bar',
    data: {
        labels: Object.keys(cfg.distHistogram).map((k) => k + ' km'),
        datasets: [{
            label: 'QSO',
            data: Object.values(cfg.distHistogram),
            backgroundColor: 'rgba(16, 185, 129, 0.75)',
            borderColor: 'rgba(16, 185, 129, 1)',
            borderWidth: 1,
            borderRadius: 3,
        }],
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            title: { display: true, text: 'Rozložení vzdáleností QSO', font: { size: 13 } },
        },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } },
            x: { grid: { display: false } },
        },
    },
}));

// Živé přebarvení grafů při přepnutí denního/nočního režimu (třída .dark na <html>).
new MutationObserver(() => {
    applyChartTheme();
    charts.forEach((ch) => ch.update());
}).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
