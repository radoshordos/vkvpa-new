import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { Chart, registerables } from 'chart.js';
import { addFullscreenControl } from './leaflet-fullscreen.js';

Chart.register(...registerables);

const cfg = window.__porovnaniConfig;

const hhmm = (m) => String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');

// ── Mapa rozdílů v protistanicích (po vzoru vushf.dk) ──────────────────────
// Zelené body udělal jen tento deník, červené jen soupeř, šedé oba.
// Server data vydá jen po uzávěrce kola (cfg.compare === null jinak) –
// bez zvoleného soupeře se mapa vůbec nerenderuje (element neexistuje).

const mapEl = document.getElementById('por-mapa');

if (mapEl && cfg.compare) {
    const cmp = cfg.compare;
    const mineCol = { stroke: '#15803d', fill: '#4ade80' };  // zelená – jen můj deník
    const rivalCol = { stroke: '#b91c1c', fill: '#f87171' }; // červená – jen soupeř
    const bothCol = { stroke: '#4b5563', fill: '#9ca3af' };  // šedá – oba

    const map = L.map(mapEl);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);
    addFullscreenControl(map);

    const bounds = [];

    if (cfg.home) {
        L.circleMarker([cfg.home.lat, cfg.home.lon], {
            radius: 8, color: '#1d4ed8', fillColor: '#3b82f6', fillOpacity: 0.9, weight: 2,
        }).addTo(map).bindPopup(`<strong>${cfg.pcall}</strong><br>${cfg.homeLoc}`);
        bounds.push([cfg.home.lat, cfg.home.lon]);
    }

    if (cmp.rivalHome) {
        L.circleMarker([cmp.rivalHome.lat, cmp.rivalHome.lon], {
            radius: 8, color: '#b45309', fillColor: '#f59e0b', fillOpacity: 0.9, weight: 2,
        }).addTo(map).bindPopup(`<strong>${cmp.rival}</strong><br>${cmp.rivalLoc}`);
        bounds.push([cmp.rivalHome.lat, cmp.rivalHome.lon]);
    }

    function comparePin(s, color, owner) {
        bounds.push([s.lat, s.lon]);
        return L.circleMarker([s.lat, s.lon], {
            radius: 5, color: color.stroke, fillColor: color.fill, fillOpacity: 0.9, weight: 1.5,
        }).bindPopup(`<strong>${s.call}</strong><br>${s.wwl}`
            + (s.dist !== null ? `<br>${s.dist} km` : '')
            + `<br><em>${owner}</em>`);
    }

    cmp.both.forEach((s) => comparePin(s, bothCol, 'udělali oba').addTo(map));
    cmp.onlyMine.forEach((s) => comparePin(s, mineCol, `jen ${cfg.pcall}`).addTo(map));
    cmp.onlyRival.forEach((s) => comparePin(s, rivalCol, `jen ${cmp.rival}`).addTo(map));

    const legend = L.control({ position: 'bottomright' });
    legend.onAdd = function () {
        const div = L.DomUtil.create('div');
        div.style.cssText = 'background:rgba(255,255,255,.9);padding:6px 10px;border-radius:6px;font-size:12px;line-height:1.7;box-shadow:0 1px 4px rgba(0,0,0,.2)';
        const dot = (c) => `<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${c};margin-right:5px;vertical-align:middle"></span>`;
        div.innerHTML = `<strong style="display:block;margin-bottom:2px">${cfg.pcall} vs. ${cmp.rival}</strong>`
            + dot(mineCol.fill) + `jen ${cfg.pcall} (${cmp.onlyMine.length})<br>`
            + dot(rivalCol.fill) + `jen ${cmp.rival} (${cmp.onlyRival.length})<br>`
            + dot(bothCol.fill) + `oba (${cmp.both.length})`;
        return div;
    };
    legend.addTo(map);

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [24, 24] });
    } else {
        map.setView([50, 15], 6);
    }
}

// ── Chart.js: barvy podle motivu (denní/noční) – shodné s vizualizace.js ──
function applyChartTheme() {
    const css = getComputedStyle(document.documentElement);
    Chart.defaults.color = css.getPropertyValue('--muted').trim() || '#666';
    Chart.defaults.borderColor = css.getPropertyValue('--line').trim() || 'rgba(0,0,0,.1)';
}
applyChartTheme();

const charts = [];

// ── Průběh skóre obou deníků (schodové čáry přes sebe) ─────────────────────

const prubehEl = document.getElementById('chartPrubeh');

if (prubehEl && cfg.compare && cfg.rivalCumulative) {
    function cumulativeDataset(label, series, stroke, fill) {
        return {
            label,
            data: [{ x: cfg.window.from, y: 0 }].concat(series.map((c) => ({ x: c.t, y: c.body }))),
            stepped: true,
            borderColor: stroke,
            backgroundColor: fill,
            pointRadius: 2,
            borderWidth: 2,
        };
    }

    charts.push(new Chart(prubehEl, {
        type: 'line',
        data: {
            datasets: [
                cumulativeDataset(cfg.pcall, cfg.cumulative, 'rgba(59,130,246,1)', 'rgba(59,130,246,.4)'),
                cumulativeDataset(cfg.compare.rival, cfg.rivalCumulative, 'rgba(239,68,68,1)', 'rgba(239,68,68,.4)'),
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 3,
            plugins: {
                legend: { display: true },
                title: { display: true, text: 'Průběh skóre (body za spojení × násobiče)', font: { size: 13 } },
                tooltip: { callbacks: { title: (its) => its.length ? hhmm(its[0].parsed.x) + ' UTC' : '' } },
            },
            scales: {
                x: {
                    type: 'linear', min: cfg.window.from, max: cfg.window.to,
                    ticks: { stepSize: 15, callback: (v) => hhmm(v) },
                    grid: { display: false },
                },
                y: { beginAtZero: true },
            },
        },
    }));
}

// Živé přebarvení grafů při přepnutí denního/nočního režimu (třída .dark na <html>).
new MutationObserver(() => {
    applyChartTheme();
    charts.forEach((ch) => ch.update());
}).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
