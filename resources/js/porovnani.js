import L from 'leaflet';
import { Chart, registerables } from 'chart.js';
import { createOsmMap } from './leaflet-osm-map.js';
import { applyChartTheme } from './chart-theme.js';

Chart.register(...registerables);

const cfg = window.__porovnaniConfig;
// Lokalizované popisky (z lang/*/pages.php → porovnani.js), předané přes config.
const t = cfg.t || {};

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

    const map = createOsmMap(mapEl);

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

    cmp.both.forEach((s) => comparePin(s, bothCol, t.both).addTo(map));
    cmp.onlyMine.forEach((s) => comparePin(s, mineCol, `${t.only} ${cfg.pcall}`).addTo(map));
    cmp.onlyRival.forEach((s) => comparePin(s, rivalCol, `${t.only} ${cmp.rival}`).addTo(map));

    const legend = L.control({ position: 'bottomright' });
    legend.onAdd = function () {
        const div = L.DomUtil.create('div');
        div.style.cssText = 'background:rgba(255,255,255,.9);padding:6px 10px;border-radius:6px;font-size:12px;line-height:1.7;box-shadow:0 1px 4px rgba(0,0,0,.2)';
        const dot = (c) => `<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${c};margin-right:5px;vertical-align:middle"></span>`;
        div.innerHTML = `<strong style="display:block;margin-bottom:2px">${cfg.pcall} vs. ${cmp.rival}</strong>`
            + dot(mineCol.fill) + `${t.only} ${cfg.pcall} (${cmp.onlyMine.length})<br>`
            + dot(rivalCol.fill) + `${t.only} ${cmp.rival} (${cmp.onlyRival.length})<br>`
            + dot(bothCol.fill) + `${t.legend_both} (${cmp.both.length})`;
        return div;
    };
    legend.addTo(map);

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [24, 24] });
    } else {
        map.setView([50, 15], 6);
    }
}

// ── Chart.js: barvy podle motivu (denní/noční) ─────────────────────────────
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
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true },
                title: { display: true, text: t.title_score, font: { size: 13 } },
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

// ── Tempo obou stanic: QSO po 15 minutách vedle sebe ───────────────────────

const timelineEl = document.getElementById('chartTimeline');

if (timelineEl && cfg.compare && cfg.timeline) {
    charts.push(new Chart(timelineEl, {
        type: 'bar',
        data: {
            labels: cfg.timeline.labels,
            datasets: [
                {
                    label: cfg.pcall,
                    data: cfg.timeline.mine,
                    backgroundColor: 'rgba(59, 130, 246, 0.75)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1,
                    borderRadius: 3,
                },
                {
                    label: cfg.compare.rival,
                    data: cfg.timeline.rival,
                    backgroundColor: 'rgba(239, 68, 68, 0.75)',
                    borderColor: 'rgba(239, 68, 68, 1)',
                    borderWidth: 1,
                    borderRadius: 3,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                title: { display: true, text: t.title_timeline, font: { size: 13 } },
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } },
                x: { grid: { display: false } },
            },
        },
    }));
}

// ── Směrová růžice obou stanic (radar – dvě průhledné plochy přes sebe) ────

const azimuthEl = document.getElementById('chartAzimuth');

if (azimuthEl && cfg.compare && cfg.azimuth) {
    charts.push(new Chart(azimuthEl, {
        type: 'radar',
        data: {
            labels: cfg.azimuth.labels,
            datasets: [
                {
                    label: cfg.pcall,
                    data: cfg.azimuth.mine,
                    borderColor: 'rgba(59, 130, 246, 1)',
                    backgroundColor: 'rgba(59, 130, 246, 0.25)',
                    borderWidth: 2,
                    pointRadius: 2,
                },
                {
                    label: cfg.compare.rival,
                    data: cfg.azimuth.rival,
                    borderColor: 'rgba(239, 68, 68, 1)',
                    backgroundColor: 'rgba(239, 68, 68, 0.25)',
                    borderWidth: 2,
                    pointRadius: 2,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                title: { display: true, text: t.title_az, font: { size: 13 } },
            },
            scales: {
                r: {
                    beginAtZero: true,
                    // Průhledné pozadí popisků os – bílý backdrop by v nočním režimu rušil.
                    ticks: { stepSize: 1, font: { size: 10 }, backdropColor: 'transparent' },
                },
            },
        },
    }));
}

// Živé přebarvení grafů při přepnutí denního/nočního režimu (třída .dark na <html>).
new MutationObserver(() => {
    applyChartTheme();
    charts.forEach((ch) => ch.update());
}).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
