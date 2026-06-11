import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { Chart, registerables } from 'chart.js';
import { addFullscreenControl } from './leaflet-fullscreen.js';

Chart.register(...registerables);

const cfg = window.__vizConfig;

const hhmm = (m) => String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');

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
// Vrstva: přehrávání deníku (paprsky + špendlíky řízené časem na slideru)
const playbackLayer = L.layerGroup();

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

// Položky přehrávání: každé QSO = paprsek + špendlík, řízené časem.
const playbackItems = [];

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

    // přehrávání: paprsek + špendlík s časem QSO
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
    playbackItems.push({ t: p.time, group, shown: false });
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

// ── Přehrávání deníku (vrstva „Přehrávání") ────────────────────────────────

const playbackControls = document.getElementById('viz-playback-controls');
const slider = document.getElementById('viz-cas');
const casLabel = document.getElementById('viz-cas-label');
const qsoCount = document.getElementById('viz-qso-count');
const playBtn = document.getElementById('viz-play');

function applyTime(t) {
    let shown = 0;
    playbackItems.forEach(function (it) {
        const show = it.t <= t;
        if (show) shown++;
        if (show && !it.shown) { it.group.forEach((g) => g.addTo(playbackLayer)); it.shown = true; }
        if (!show && it.shown) { it.group.forEach((g) => playbackLayer.removeLayer(g)); it.shown = false; }
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

// Výřez nastavíme dřív, než zapneme vrstvu – mřížka CRK čte map.getBounds().
if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [24, 24] });
} else {
    map.setView([50, 15], 6);
}

// Přepínání vrstev přes tlačítka.
const layers = { jezek: jezekLayer, spendliky: spendlikyLayer, lokatory: lokatoryLayer, crk: crkLayer, playback: playbackLayer };

function showLayer(key) {
    Object.values(layers).forEach((l) => map.removeLayer(l));
    if (homeMarker) map.removeLayer(homeMarker);
    map.off('moveend', redrawCrkGrid);
    stopReplay();
    layers[key].addTo(map);
    if (key === 'crk') { redrawCrkGrid(); map.on('moveend', redrawCrkGrid); }
    if (key === 'playback') {
        // Výchozí stav: celý deník zobrazen (slider na konci okna).
        slider.value = String(cfg.window.to);
        applyTime(cfg.window.to);
    }
    playbackControls.classList.toggle('hidden', key !== 'playback');
    playbackControls.classList.toggle('flex', key === 'playback');
    if (homeMarker) homeMarker.addTo(map);
    document.querySelectorAll('[data-map-layer]').forEach((b) => b.classList.toggle('active', b.dataset.mapLayer === key));
}

document.querySelectorAll('[data-map-layer]').forEach(function (btn) {
    btn.addEventListener('click', () => showLayer(btn.dataset.mapLayer));
});

// Výchozí vrstva: přehrávání deníku (slider na konci okna = celý deník).
showLayer('playback');

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

// ── Chart.js: Průběh skóre (schodová čára) ─────────────────────────────────

charts.push(new Chart(document.getElementById('chartPrubeh'), {
    type: 'line',
    data: {
        datasets: [{
            label: cfg.pcall,
            data: [{ x: cfg.window.from, y: 0 }].concat(cfg.cumulative.map((c) => ({ x: c.t, y: c.body }))),
            stepped: true,
            borderColor: 'rgba(59,130,246,1)',
            backgroundColor: 'rgba(59,130,246,.4)',
            pointRadius: 2,
            borderWidth: 2,
        }],
    },
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

// ── Chart.js: Timeline – QSO po 15 minutách, zvýrazněná QSO s novým násobičem

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

// ── Chart.js: Azimutová růžice s přepínáním vážení (počet / km / body) ─────

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
            r: {
                beginAtZero: true,
                // Průhledné pozadí popisků os – bílý backdrop by v nočním režimu rušil.
                ticks: { font: { size: 10 }, backdropColor: 'transparent' },
            },
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

// ── Chart.js: Body podle velkých čtverců (vodorovné sloupce) ───────────────

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

// ── Chart.js: Celoroční trend stanice (body + pořadí po kolech) ────────────

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
