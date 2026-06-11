import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { Chart, registerables } from 'chart.js';
import { addFullscreenControl } from './leaflet-fullscreen.js';

Chart.register(...registerables);

const cfg = window.__inkubatorConfig;

const hhmm = (m) => String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');

// Barvy dle druhu provozu: 1=SSB (modrá), 2=CW (oranžová), ostatní (šedá) – shodné s vizualizace.js
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

// ── Leaflet mapa s přehráváním deníku ──────────────────────────────────────

const map = L.map('ink-mapa');
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

// Každé QSO = paprsek + špendlík; přehrávání je řídí podle času na slideru.
const replayLayer = L.layerGroup().addTo(map);

const items = cfg.points.map(function (p) {
    bounds.push([p.lat, p.lon]);
    const mc = modeColor(p.mode);
    const group = [];

    if (cfg.home) {
        group.push(L.polyline([[cfg.home.lat, cfg.home.lon], [p.lat, p.lon]], {
            color: mc.fill, weight: 1.2, opacity: 0.55,
        }));
    }
    group.push(L.circleMarker([p.lat, p.lon], {
        radius: 5, color: mc.stroke, fillColor: mc.fill, fillOpacity: 0.9, weight: 1.5,
    }).bindPopup(`<strong>${p.call}</strong> <span style="font-size:.8em;opacity:.7">${modeLabel(p.mode)}</span><br>${p.wwl}<br>${hhmm(p.time)} UTC`
        + (p.dist !== null ? `<br>${p.dist} km` : '')
        + (p.azimut !== null ? `<br>azimut ${p.azimut}°` : '')
        + `<br>${p.points} b.`));

    return { t: p.time, group, shown: false };
});

if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [24, 24] });
} else {
    map.setView([50, 15], 6);
}

const slider = document.getElementById('ink-cas');
const casLabel = document.getElementById('ink-cas-label');
const qsoCount = document.getElementById('ink-qso-count');
const playBtn = document.getElementById('ink-play');

function applyTime(t) {
    let shown = 0;
    items.forEach(function (it) {
        const show = it.t <= t;
        if (show) shown++;
        if (show && !it.shown) { it.group.forEach((g) => g.addTo(replayLayer)); it.shown = true; }
        if (!show && it.shown) { it.group.forEach((g) => replayLayer.removeLayer(g)); it.shown = false; }
    });
    casLabel.textContent = hhmm(t);
    qsoCount.textContent = String(shown);
}

let timer = null;
function stopReplay() {
    if (timer !== null) { clearInterval(timer); timer = null; }
    playBtn.textContent = '▶ Přehrát';
}

slider.addEventListener('input', function () {
    stopReplay();
    applyTime(Number(slider.value));
});

playBtn.addEventListener('click', function () {
    if (timer !== null) { stopReplay(); return; }
    let t = Number(slider.value);
    if (t >= cfg.window.to) t = cfg.window.from;
    playBtn.textContent = '⏸ Pauza';
    slider.value = String(t);
    applyTime(t);
    timer = setInterval(function () {
        t++;
        slider.value = String(t);
        applyTime(t);
        if (t >= cfg.window.to) stopReplay();
    }, 50);
});

// Výchozí stav: celý deník zobrazen (slider na konci okna).
applyTime(cfg.window.to);

// ── Chart.js: barvy podle motivu (denní/noční) – shodné s vizualizace.js ──
function applyChartTheme() {
    const css = getComputedStyle(document.documentElement);
    Chart.defaults.color = css.getPropertyValue('--muted').trim() || '#666';
    Chart.defaults.borderColor = css.getPropertyValue('--line').trim() || 'rgba(0,0,0,.1)';
}
applyChartTheme();

const charts = [];

// ── Průběh skóre (schodová čára; porovnání se soupeřem je na stránce
//    Porovnání deníků – porovnani.js) ───────────────────────────────────────

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

charts.push(new Chart(document.getElementById('chartPrubeh'), {
    type: 'line',
    data: { datasets: [cumulativeDataset(cfg.pcall, cfg.cumulative, 'rgba(59,130,246,1)', 'rgba(59,130,246,.4)')] },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 3,
        plugins: {
            legend: { display: false },
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

// ── Timeline: QSO po 15 minutách, zvýrazněná QSO s novým násobičem ─────────

charts.push(new Chart(document.getElementById('chartTimeline'), {
    type: 'bar',
    data: {
        labels: cfg.timeline.labels,
        datasets: [
            {
                label: 'QSO s novým násobičem',
                data: cfg.timeline.nove,
                backgroundColor: 'rgba(168, 85, 247, 0.8)',
                borderColor: 'rgba(168, 85, 247, 1)',
                borderWidth: 1,
                borderRadius: 3,
            },
            {
                label: 'Ostatní QSO',
                data: cfg.timeline.celkem.map((c, i) => c - cfg.timeline.nove[i]),
                backgroundColor: 'rgba(59, 130, 246, 0.6)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1,
                borderRadius: 3,
            },
        ],
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
            title: { display: true, text: 'QSO v čase a nové násobiče (15min intervaly)', font: { size: 13 } },
        },
        scales: {
            y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } },
            x: { stacked: true, grid: { display: false } },
        },
    },
}));

// ── Azimutová růžice s přepínáním vážení (počet / km / body) ───────────────

const azColors = [
    'rgba(239,68,68,.75)', 'rgba(251,146,60,.75)', 'rgba(250,204,21,.75)', 'rgba(74,222,128,.75)',
    'rgba(34,211,238,.75)', 'rgba(96,165,250,.75)', 'rgba(167,139,250,.75)', 'rgba(236,72,153,.75)',
];
const azTitles = { pocet: 'Směry QSO (počet)', km: 'Směry QSO (součet km)', body: 'Směry QSO (součet bodů)' };

const azChart = new Chart(document.getElementById('chartAzimuth'), {
    type: 'polarArea',
    data: {
        labels: cfg.azimuth.labels,
        datasets: [{
            data: cfg.azimuth.pocet,
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
            title: { display: true, text: azTitles.pocet, font: { size: 13 } },
        },
        scales: {
            r: { beginAtZero: true, ticks: { font: { size: 10 }, backdropColor: 'transparent' } },
        },
        startAngle: -Math.PI / 8,
    },
});
charts.push(azChart);

document.querySelectorAll('[data-az-metric]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const metric = btn.dataset.azMetric;
        azChart.data.datasets[0].data = cfg.azimuth[metric];
        azChart.options.plugins.title.text = azTitles[metric];
        azChart.update();
        document.querySelectorAll('[data-az-metric]').forEach((b) => b.classList.toggle('active', b === btn));
    });
});

// ── Body podle velkých čtverců (vodorovné sloupce) ─────────────────────────

charts.push(new Chart(document.getElementById('chartCtverce'), {
    type: 'bar',
    data: {
        labels: cfg.squarePoints.map((s) => s.square),
        datasets: [{
            label: 'Body',
            data: cfg.squarePoints.map((s) => s.body),
            backgroundColor: 'rgba(16, 185, 129, 0.75)',
            borderColor: 'rgba(16, 185, 129, 1)',
            borderWidth: 1,
            borderRadius: 3,
        }],
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            title: { display: true, text: 'Body za spojení podle velkých čtverců', font: { size: 13 } },
            tooltip: { callbacks: { afterLabel: (it) => cfg.squarePoints[it.dataIndex].pocet + ' QSO' } },
        },
        scales: {
            x: { beginAtZero: true, ticks: { stepSize: 1 } },
            y: { grid: { display: false } },
        },
    },
}));

// ── Celoroční trend stanice (body + pořadí po kolech) ──────────────────────

if (cfg.sezona) {
    charts.push(new Chart(document.getElementById('chartSezona'), {
        type: 'line',
        data: {
            labels: cfg.sezona.labels,
            datasets: [
                {
                    label: 'Body',
                    data: cfg.sezona.body,
                    borderColor: 'rgba(59,130,246,1)',
                    backgroundColor: 'rgba(59,130,246,.4)',
                    borderWidth: 2,
                    spanGaps: false,
                    yAxisID: 'y',
                },
                {
                    label: 'Pořadí v kategorii',
                    data: cfg.sezona.poradi,
                    borderColor: 'rgba(245,158,11,1)',
                    backgroundColor: 'rgba(245,158,11,.4)',
                    borderWidth: 2,
                    borderDash: [5, 4],
                    spanGaps: false,
                    yAxisID: 'y1',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                title: { display: true, text: 'Sezóna ' + cfg.pcall + ' po kolech', font: { size: 13 } },
            },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Body' } },
                // Pořadí: 1. místo nahoře.
                y1: { position: 'right', reverse: true, suggestedMin: 1, ticks: { stepSize: 1 }, grid: { display: false }, title: { display: true, text: 'Pořadí' } },
            },
        },
    }));
}

// Živé přebarvení grafů při přepnutí denního/nočního režimu (třída .dark na <html>).
new MutationObserver(() => {
    applyChartTheme();
    charts.forEach((ch) => ch.update());
}).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
