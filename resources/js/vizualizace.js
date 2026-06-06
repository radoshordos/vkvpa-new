import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const cfg = window.__vizConfig;

// ── Leaflet mapa s přepínatelnými vrstvami ─────────────────────────────────

const map = L.map('viz-mapa');
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap',
}).addTo(map);

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

cfg.points.forEach(function (p) {
    bounds.push([p.lat, p.lon]);

    // ježek: čára + bod
    if (cfg.home) {
        L.polyline([[cfg.home.lat, cfg.home.lon], [p.lat, p.lon]], {
            color: '#22c55e', weight: 1.2, opacity: 0.55,
        }).addTo(jezekLayer);
    }
    L.circleMarker([p.lat, p.lon], { radius: 4, color: '#16a34a', fillOpacity: 0.85 })
        .addTo(jezekLayer)
        .bindPopup(`<strong>${p.call}</strong><br>${p.wwl}<br>${p.points} b.`);

    // špendlíky
    const popupSpend = `<strong>${p.call}</strong><br>${p.wwl}`
        + (p.dist !== null ? `<br>${p.dist} km` : '')
        + (p.azimut !== null ? `<br>azimut ${p.azimut}°` : '');
    L.circleMarker([p.lat, p.lon], { radius: 5, color: '#ea580c', fillColor: '#f97316', fillOpacity: 0.85 })
        .addTo(spendlikyLayer)
        .bindPopup(popupSpend);
});

cfg.squares.forEach(function (s) {
    bounds.push([s.lat, s.lon]);
    L.circleMarker([s.lat, s.lon], {
        radius: 14, color: '#7c3aed', fillColor: '#a855f7', fillOpacity: 0.65, weight: 2,
    }).addTo(lokatoryLayer)
        .bindTooltip(String(s.count), { permanent: true, direction: 'center', className: 'sq-label' })
        .bindPopup(`<strong>${s.square}</strong><br>${s.count} protistanic`);
});

// Výchozí vrstva: ježek
jezekLayer.addTo(map);

if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [24, 24] });
} else {
    map.setView([50, 15], 6);
}

// Přepínání vrstev přes tlačítka
const layers = { jezek: jezekLayer, spendliky: spendlikyLayer, lokatory: lokatoryLayer };
document.querySelectorAll('[data-map-layer]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const key = btn.dataset.mapLayer;
        Object.values(layers).forEach((l) => map.removeLayer(l));
        if (homeMarker) map.removeLayer(homeMarker);
        layers[key].addTo(map);
        if (homeMarker) homeMarker.addTo(map);
        document.querySelectorAll('[data-map-layer]').forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
    });
});

// ── Chart.js: Časová osa QSO (bar) ────────────────────────────────────────

new Chart(document.getElementById('chartTimeline'), {
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
});

// ── Chart.js: Azimutová růžice (polar area) ───────────────────────────────

const azColors = [
    'rgba(239,68,68,.75)', 'rgba(251,146,60,.75)', 'rgba(250,204,21,.75)', 'rgba(74,222,128,.75)',
    'rgba(34,211,238,.75)', 'rgba(96,165,250,.75)', 'rgba(167,139,250,.75)', 'rgba(236,72,153,.75)',
];

new Chart(document.getElementById('chartAzimuth'), {
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
                ticks: { stepSize: 1, font: { size: 10 } },
            },
        },
        startAngle: -Math.PI / 8,
    },
});

// ── Chart.js: Histogram vzdáleností (bar) ─────────────────────────────────

new Chart(document.getElementById('chartDist'), {
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
});
