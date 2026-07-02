import L from 'leaflet';
import { Chart, registerables } from 'chart.js';
import { createOsmMap } from './leaflet-osm-map.js';
import { applyChartTheme } from './chart-theme.js';
import { fitMapToBounds, pushPointBounds } from './leaflet-qso-map.js';
import { modeColor, modeLabel } from './leaflet-mode-colors.js';

Chart.register(...registerables);
applyChartTheme();

// Veřejná statistika kola: mapa (stanice kola / obsazené čtverce / účastníci)
// + grafy (časová osa, druhy provozu, země, prefixy, kategorie, trend).
// Data dodává inline config window.__statConfig ze šablony statistiky/kolo.

const cfg = window.__statConfig || {};
const t = cfg.t || {};
const arr = (v) => (Array.isArray(v) ? v : []);

const stanice = arr(cfg.stanice);
const ctverce = arr(cfg.ctverce);
const ucastnici = arr(cfg.ucastnici);

const BRAND = '#6366f1';
const PALETTE = ['#6366f1', '#8b5cf6', '#a78bfa', '#c4b5fd', '#f472b6', '#fb7185', '#fbbf24', '#34d399', '#22d3ee', '#60a5fa', '#a3e635', '#f87171'];

// ── Mapa (jen na detailu kola, kde existuje #stat-mapa) ─────────────────────
if (document.getElementById('stat-mapa')) {
    const map = createOsmMap('stat-mapa');
    const bounds = [];

    const staniceLayer = L.layerGroup();
    const maxCount = stanice.reduce((m, s) => Math.max(m, s.count), 1);
    stanice.forEach((s) => {
        const radius = 3 + 7 * Math.sqrt(s.count / maxCount);
        L.circleMarker([s.lat, s.lon], { radius, color: '#1d4ed8', weight: 1, fillColor: '#3b82f6', fillOpacity: 0.6 })
            .addTo(staniceLayer)
            .bindPopup(`<strong>${s.call}</strong><br>${s.wwl} · ${s.count}×`);
        pushPointBounds(bounds, s);
    });

    const ctverceLayer = L.layerGroup();
    const maxSq = ctverce.reduce((m, c) => Math.max(m, c.count), 1);
    const heatColor = (x) => `hsl(${60 - 60 * x}, 90%, 50%)`;
    ctverce.forEach((c) => {
        const x = Math.sqrt(c.count / maxSq);
        L.rectangle([[c.lat - 0.5, c.lon - 1], [c.lat + 0.5, c.lon + 1]], { color: '#b45309', weight: 1, fillColor: heatColor(x), fillOpacity: 0.45 })
            .addTo(ctverceLayer)
            .bindPopup(`<strong>${c.square}</strong><br>${c.count}×`);
        pushPointBounds(bounds, c);
    });

    const ucastniciLayer = L.layerGroup();
    ucastnici.forEach((u) => {
        const place = u.poradi > 0 ? `${u.poradi}.` : '';
        L.circleMarker([u.lat, u.lon], { radius: 5, color: '#7c3aed', weight: 1, fillColor: '#a78bfa', fillOpacity: 0.7 })
            .addTo(ucastniciLayer)
            .bindPopup(`<strong>${u.call}</strong> ${place}<br>${u.loc} · ${u.kat} · ${u.body} ${t.points || 'b.'}`);
    });

    staniceLayer.addTo(map);
    fitMapToBounds(map, bounds, { padding: [20, 20], fallbackCenter: [49.8, 15.5] });

    const layers = { stanice: staniceLayer, ctverce: ctverceLayer, ucastnici: ucastniciLayer };
    const tabs = document.querySelectorAll('[data-stat-layer]');
    tabs.forEach((btn) => {
        btn.addEventListener('click', () => {
            const key = btn.getAttribute('data-stat-layer');
            Object.values(layers).forEach((l) => map.removeLayer(l));
            (layers[key] || staniceLayer).addTo(map);
            tabs.forEach((b) => b.classList.toggle('active', b === btn));
        });
    });
}

// ── Grafy ─────────────────────────────────────────────────────────────────
function chart(id, config) {
    const el = document.getElementById(id);
    if (el) {
        new Chart(el, config);
    }
}

// Časová osa aktivity (počet QSO v 15min intervalech).
const timeline = cfg.timeline || { labels: [], counts: [] };
chart('chartTimeline', {
    type: 'bar',
    data: { labels: timeline.labels, datasets: [{ label: t.qsoCount || 'QSO', data: timeline.counts, backgroundColor: BRAND + 'cc', borderRadius: 3 }] },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
});

// Druhy provozu: oficiální módy 1–6 + „Ostatní", barvy a popisky ze sdílené
// palety (leaflet-mode-colors) – shodně s vizualizací deníku.
const mody = arr(cfg.mody);
chart('chartMody', {
    type: 'doughnut',
    data: {
        labels: mody.map((m) => (m.mode === 0 ? (t.other || 'Ostatní') : modeLabel(m.mode))),
        datasets: [{ data: mody.map((m) => m.pocet), backgroundColor: mody.map((m) => modeColor(m.mode).fill), borderWidth: 0 }],
    },
    options: { plugins: { legend: { position: 'bottom' } }, cutout: '60%' },
});

// Kategorie.
const kategorie = arr(cfg.kategorie);
chart('chartKategorie', {
    type: 'doughnut',
    data: {
        labels: kategorie.map((k) => k.zkratka),
        datasets: [{ data: kategorie.map((k) => k.pocet), backgroundColor: PALETTE, borderWidth: 0 }],
    },
    options: { plugins: { legend: { position: 'right' } }, cutout: '55%' },
});

// Země (TOP) – vodorovný sloupcový.
const zeme = arr(cfg.zeme);
chart('chartZeme', {
    type: 'bar',
    data: {
        labels: zeme.map((z) => z.nazev || (t.other || 'Ostatní')),
        datasets: [{ label: t.qsoCount || 'QSO', data: zeme.map((z) => z.pocet), backgroundColor: '#8b5cf6cc', borderRadius: 3 }],
    },
    options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } },
});

// Prefixy (TOP) – vodorovný sloupcový.
const prefixy = arr(cfg.prefixy);
chart('chartPrefix', {
    type: 'bar',
    data: {
        labels: prefixy.map((p) => p.nazev || (t.other || 'Ostatní')),
        datasets: [{ label: t.qsoCount || 'QSO', data: prefixy.map((p) => p.pocet), backgroundColor: '#22d3eecc', borderRadius: 3 }],
    },
    options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } },
});

// Trend posledních kol – účast (sloupce) + QSO (čára na druhé ose).
const trend = cfg.trend || { labels: [], stanic: [], qso: [], body: [] };
chart('chartTrend', {
    data: {
        labels: trend.labels,
        datasets: [
            { type: 'bar', label: t.stations || 'Stanice', data: trend.stanic, backgroundColor: BRAND + 'cc', borderRadius: 3, yAxisID: 'y' },
            { type: 'line', label: t.qsoCount || 'QSO', data: trend.qso, borderColor: '#f472b6', backgroundColor: '#f472b6', tension: 0.3, yAxisID: 'y1' },
        ],
    },
    options: {
        plugins: { legend: { position: 'top' } },
        scales: {
            y: { beginAtZero: true, position: 'left' },
            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } },
            x: { ticks: { maxRotation: 45 } },
        },
    },
});

// Profil stanice: trend bodů v čase (jen na stránce profilu).
const staniceTrend = cfg.staniceTrend || { labels: [], body: [] };
chart('chartStaniceTrend', {
    type: 'line',
    data: { labels: staniceTrend.labels, datasets: [{ label: t.points || 'b.', data: staniceTrend.body, borderColor: BRAND, backgroundColor: BRAND + '33', fill: true, tension: 0.3 }] },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
});
