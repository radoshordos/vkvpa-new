import { Chart, registerables } from 'chart.js';
import { modeColor, modeLabel } from './leaflet-mode-colors.js';
import { applyChartTheme } from './chart-theme.js';
import { buildVizData, hhmm, modeGroup } from './map/viz-data.js';
import { createLeafletEngine } from './map/engine-leaflet.js';
import { createMapLibreEngine } from './map/engine-maplibre.js';

Chart.register(...registerables);

const cfg = window.__vizConfig;
// Lokalizované popisky (z lang/*/pages.php → viz.js), předané přes config.
const t = cfg.t || {};

const isDark = () => document.documentElement.classList.contains('dark');

// ── Mapa: vyměnitelný engine (Leaflet / MapLibre GL + deck.gl) ─────────────
// Orchestrátor drží DOM ovládání (výběr vrstvy, filtr provozu, přehrávání,
// legendu) a stav; samotné kreslení deleguje na engine se společným rozhraním
// init/setLayer/setModeFilter/applyTime/focusQso/setTheme/fit/destroy.

// Filtr druhu provozu napříč vrstvami (klíče shodné s data-mode-filter).
const modeFilter = { 0: true, 1: true, 2: true, 3: true, 4: true, 5: true, 6: true };

const data = buildVizData(cfg);
const mapEl = document.getElementById('viz-mapa');

const ENGINES = { leaflet: createLeafletEngine, maplibre: createMapLibreEngine };

// MapLibre GL vyžaduje WebGL – bez něj se tiše padá na Leaflet (s hláškou).
function webglSupported() {
    try {
        const c = document.createElement('canvas');
        return !!(c.getContext('webgl2') || c.getContext('webgl'));
    } catch (e) {
        return false;
    }
}

const engineNote = document.getElementById('viz-engine-note');

function showEngineNote() {
    if (engineNote) engineNote.classList.remove('hidden');
}

// Volba enginu se pamatuje; výchozí je lehčí Leaflet.
let engineKey = 'leaflet';
try {
    if (localStorage.getItem('viz-map-engine') === 'maplibre') engineKey = 'maplibre';
} catch (e) { /* localStorage nemusí být dostupný */ }
if (engineKey === 'maplibre' && !webglSupported()) {
    engineKey = 'leaflet';
    showEngineNote();
}

// ── Legenda druhů provozu – HTML overlay nezávislý na enginu ───────────────

// Obarvení teček druhu provozu v legendě souhrnu i ve filtru (z jediné palety).
document.querySelectorAll('[data-mode-dot]').forEach(function (el) {
    el.style.background = modeColor(Number(el.dataset.modeDot)).fill;
});

const legendEl = document.createElement('div');
legendEl.className = 'viz-legend';
legendEl.innerHTML = `<strong style="display:block;margin-bottom:2px">${t.legend}</strong>`
    + data.presentModes
        .map((m) => `<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${modeColor(m).fill};margin-right:5px;vertical-align:middle"></span>${m === 0 ? t.other : modeLabel(m)}`)
        .join('<br>');

let engine = null;

function initEngine(key) {
    engine = ENGINES[key]();
    engine.init({ container: 'viz-mapa', cfg, data, modeFilter, dark: isDark() });
    // Legenda je dítě kontejneru mapy (aby zůstala vidět i na celé obrazovce)
    // – po (re)initu enginu ji vrátíme na konec kontejneru.
    mapEl.appendChild(legendEl);
}

try {
    initEngine(engineKey);
} catch (e) {
    // Inicializace WebGL může selhat i po pozitivní detekci – tiše na Leaflet.
    if (engineKey !== 'leaflet') {
        engineKey = 'leaflet';
        showEngineNote();
        initEngine('leaflet');
    } else {
        throw e;
    }
}

// Vrstvy agregující čtverce nemají barvy podle druhu provozu – skrývá se u nich
// barevná legenda (filtr provozu na ně ale platí: přepočítává počty QSO).
const aggregateLayer = (key) => key === 'lokatory' || key === 'ctverce';

// ── Přehrávání deníku (vrstva „Přehrávání") ────────────────────────────────

const playbackControls = document.getElementById('viz-playback-controls');

// Překreslení svislé časové linky v grafech (průběh + timeline) podle času na
// slideru; přiřazuje se až po vytvoření grafů níže (do té doby no-op).
let chartTimeMarker = null;
const slider = document.getElementById('viz-cas');
const casLabel = document.getElementById('viz-cas-label');
const qsoCount = document.getElementById('viz-qso-count');
const skoreLabel = document.getElementById('viz-skore');
const playBtn = document.getElementById('viz-play');

function applyTime(time) {
    const shown = engine.applyTime(time);
    casLabel.textContent = hhmm(time);
    qsoCount.textContent = String(shown);

    // Časová linka v grafech sleduje slider – jen na vrstvě Přehrávání a jen
    // dokud slider není na konci okna (plný deník = linka u kraje by rušila).
    if (chartTimeMarker) {
        const active = !playbackControls.classList.contains('hidden');
        chartTimeMarker(active && time < cfg.window.to ? time : null);
    }

    // Průběžné skóre k času (poslední záznam cfg.cumulative; je řazeno časem).
    let last = null;
    for (const c of cfg.cumulative) {
        if (c.t > time) break;
        last = c;
    }
    skoreLabel.textContent = last ? `${last.body} ${t.score_pts} · ${last.multiplier} ${t.score_mult}` : `0 ${t.score_pts}`;
}

function applyModeFilter() {
    engine.setModeFilter(modeFilter);
    // Přehrávání respektuje filtr přes applyTime (viditelnost = čas × provoz).
    applyTime(Number(slider.value));
}

let timer = null;

// Ladění rychlosti přehrávání (vše v ms na minutu závodu): malý deník běží
// základním tempem, u početnějších se přehrávání úměrně zpomaluje, aby
// neprobleskl příliš rychle na sledování.
const playbackBaseMs = 50; // základní tempo (deníky do ~100 QSO)
const playbackMaxMs = 150; // strop zpomalení pro velmi početné deníky
const playbackMsPerQso = 0.5; // zpomalení za každé QSO deníku

const speedMs = Math.min(playbackMaxMs, Math.max(playbackBaseMs, Math.round(cfg.points.length * playbackMsPerQso)));

function stopReplay() {
    if (timer !== null) { clearInterval(timer); timer = null; }
    playBtn.textContent = t.play;
}

function tick() {
    const time = Number(slider.value) + 1;
    slider.value = String(time);
    applyTime(time);
    if (time >= cfg.window.to) stopReplay();
}

slider.addEventListener('input', function () {
    stopReplay();
    applyTime(Number(slider.value));
});

playBtn.addEventListener('click', function () {
    if (timer !== null) { stopReplay(); return; }
    let time = Number(slider.value);
    if (time >= cfg.window.to) time = cfg.window.from;
    playBtn.textContent = t.pause;
    slider.value = String(time);
    applyTime(time);
    timer = setInterval(tick, speedMs);
});

// ── Přepínání vrstev ───────────────────────────────────────────────────────

const layerKeys = ['jezek', 'spendliky', 'lokatory', 'ctverce', 'crk', 'playback'];
const layerSelect = document.getElementById('viz-layer-select');

function showLayer(key) {
    stopReplay();
    engine.setLayer(key);
    if (key === 'playback') {
        // Výchozí stav: celý deník zobrazen (slider na konci okna).
        slider.value = String(cfg.window.to);
        applyTime(cfg.window.to);
    }
    playbackControls.classList.toggle('hidden', key !== 'playback');
    playbackControls.classList.toggle('flex', key === 'playback');
    // Mimo Přehrávání časová linka v grafech nemá co sledovat – zhasnout.
    if (key !== 'playback' && chartTimeMarker) chartTimeMarker(null);
    legendEl.style.display = aggregateLayer(key) ? 'none' : '';
    layerSelect.value = key;
    // Vrstva do URL (#mapa-…) – odkaz na konkrétní vrstvu jde sdílet.
    history.replaceState(null, '', '#mapa-' + key);
}

layerSelect.addEventListener('change', () => showLayer(layerSelect.value));

// Filtr druhu provozu – přepínací tlačítka SSB / CW / Ostatní.
document.querySelectorAll('[data-mode-filter]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const m = Number(btn.dataset.modeFilter);
        modeFilter[m] = !modeFilter[m];
        btn.classList.toggle('active', modeFilter[m]);
        applyModeFilter();
    });
});

// ── Přepínač mapového enginu (Leaflet ⇄ MapLibre GL + deck.gl) ─────────────

function syncEngineButtons() {
    document.querySelectorAll('[data-map-engine]').forEach(function (b) {
        b.classList.toggle('active', b.dataset.mapEngine === engineKey);
    });
}

function switchEngine(key) {
    if (!ENGINES[key] || key === engineKey) return;
    if (key === 'maplibre' && !webglSupported()) {
        showEngineNote();
        syncEngineButtons();
        return;
    }
    stopReplay();
    engine.destroy();
    engineKey = key;
    try { localStorage.setItem('viz-map-engine', key); } catch (e) { /* noop */ }
    try {
        initEngine(key);
    } catch (e) {
        // Inicializace WebGL může selhat i po pozitivní detekci (vyčerpané
        // kontexty, ovladač) – tiše zpět na Leaflet.
        if (key !== 'leaflet') {
            engineKey = 'leaflet';
            showEngineNote();
            try { localStorage.setItem('viz-map-engine', 'leaflet'); } catch (e2) { /* noop */ }
            initEngine('leaflet');
        }
    }
    // Obnova stavu mapy: aktuální vrstva + filtr (přehrávání začíná s celým
    // deníkem – slider na konci okna, viz showLayer).
    showLayer(layerSelect.value);
    syncEngineButtons();
}

document.querySelectorAll('[data-map-engine]').forEach(function (btn) {
    btn.addEventListener('click', function () { switchEngine(btn.dataset.mapEngine); });
});
syncEngineButtons();

// Klik na řádek TOP ODX → špendlík daného QSO na mapě.
document.querySelectorAll('[data-odx-idx]').forEach(function (row) {
    row.addEventListener('click', function () {
        const idx = Number(row.dataset.odxIdx);
        // Kdyby byl provoz QSO odfiltrovaný, špendlík by na mapě nebyl – zapni ho.
        const m = modeGroup(cfg.points[idx].mode);
        if (!modeFilter[m]) {
            modeFilter[m] = true;
            document.querySelectorAll('[data-mode-filter]').forEach(function (b) {
                if (Number(b.dataset.modeFilter) === m) b.classList.add('active');
            });
            applyModeFilter();
        }
        showLayer('spendliky');
        mapEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        engine.focusQso(idx);
    });
});

// Výchozí vrstva: z URL hashe (#mapa-…), jinak přehrávání deníku.
const hashLayer = (location.hash.match(/^#mapa-([a-z]+)$/) || [])[1];
showLayer(layerKeys.includes(hashLayer) ? hashLayer : 'playback');

// ── Chart.js: barvy podle motivu (denní/noční) ─────────────────────────────
applyChartTheme();

const charts = [];

// ── Synchronizovaný hover: průběh skóre ↔ timeline (sdílená časová osa) ────
// Najetí na jeden graf kreslí svislou linku v odpovídajícím čase na obou.

// Minuty od půlnoci, null = nic; `t` = najetí myší, `playback` = čas na
// slideru přehrávání (myš má přednost, po opuštění grafu se linka vrátí
// na čas přehrávání).
const hoverSync = { t: null, playback: null };

const syncPlugin = {
    id: 'vizHoverSync',
    afterDraw(chart) {
        const toX = chart.$vizSyncToX;
        const tm = hoverSync.t ?? hoverSync.playback;
        if (tm === null || !toX) return;
        const x = toX(tm);
        if (x === null || x < chart.chartArea.left || x > chart.chartArea.right) return;
        const ctx = chart.ctx;
        ctx.save();
        ctx.strokeStyle = 'rgba(148,163,184,.9)';
        ctx.setLineDash([4, 3]);
        ctx.beginPath();
        ctx.moveTo(x, chart.chartArea.top);
        ctx.lineTo(x, chart.chartArea.bottom);
        ctx.stroke();
        ctx.restore();
    },
};

// ── Chart.js: Průběh skóre (schodová čára) ─────────────────────────────────

const prubehChart = new Chart(document.getElementById('chartPrubeh'), {
    type: 'line',
    plugins: [syncPlugin],
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
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            title: { display: true, text: t.title_prubeh, font: { size: 13 } },
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
});
charts.push(prubehChart);

// ── Chart.js: Timeline – QSO po 15 minutách, zvýrazněná QSO s novým násobičem

const timelineChart = new Chart(document.getElementById('chartTimeline'), {
    type: 'bar',
    plugins: [syncPlugin],
    data: {
        labels: cfg.timeline.labels,
        datasets: [
            {
                label: t.ds_new_mult,
                data: cfg.timeline.nove,
                backgroundColor: 'rgba(236, 72, 153, 0.85)',
                borderColor: 'rgba(236, 72, 153, 1)',
                borderWidth: 1,
                borderRadius: 3,
            },
            {
                label: t.ds_other_qso,
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
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
            title: { display: true, text: t.title_timeline, font: { size: 13 } },
        },
        scales: {
            y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } },
            x: { stacked: true, grid: { display: false } },
        },
    },
});
charts.push(timelineChart);

// Mapování čas (minuty) ↔ pixel: průběh má lineární osu, timeline kategorie
// po 15 minutách (index i pokrývá interval od from+15·i, střed na from+15·i+7,5).
prubehChart.$vizSyncToX = (t) => prubehChart.scales.x.getPixelForValue(t);
timelineChart.$vizSyncToX = (t) => timelineChart.scales.x.getPixelForValue((t - cfg.window.from) / 15 - 0.5);

function syncHover(chart, pixelToMinutes) {
    chart.options.onHover = function (evt) {
        const { left, right } = chart.chartArea;
        hoverSync.t = (evt.x !== null && evt.x >= left && evt.x <= right) ? pixelToMinutes(evt.x) : null;
        prubehChart.draw();
        timelineChart.draw();
    };
    chart.canvas.addEventListener('mouseleave', function () {
        hoverSync.t = null;
        prubehChart.draw();
        timelineChart.draw();
    });
}

syncHover(prubehChart, (px) => prubehChart.scales.x.getValueForPixel(px));
// Kategorie → index intervalu (getValueForPixel zaokrouhluje na nejbližší) → střed intervalu.
syncHover(timelineChart, (px) => cfg.window.from + (timelineChart.scales.x.getValueForPixel(px) + 0.5) * 15);

// Napojení přehrávání na časovou linku grafů (viz applyTime výše).
chartTimeMarker = function (t) {
    hoverSync.playback = t;
    prubehChart.draw();
    timelineChart.draw();
};

// ── Chart.js: Azimutová růžice s přepínáním vážení (počet / km / body) ─────
// 16 sektorů po 22,5°; popisky směrů přímo u výsečí (legenda by byla nečitelná).

const azColors = Array.from({ length: 16 }, (_, i) => `hsla(${Math.round(i * 22.5)}, 72%, 55%, .75)`);
const azTitles = { pocet: t.az_count, km: t.az_km, body: t.az_points };

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
            legend: { display: false },
            title: { display: true, text: azTitles.pocet, font: { size: 13 } },
        },
        scales: {
            r: {
                beginAtZero: true,
                // Průhledné pozadí popisků os – bílý backdrop by v nočním režimu rušil.
                ticks: { font: { size: 10 }, backdropColor: 'transparent' },
                pointLabels: { display: true, centerPointLabels: true, font: { size: 10 } },
            },
        },
        // Posun o půl sektoru, aby S mířil přesně na sever (hodnota ve stupních).
        startAngle: -11.25,
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
            label: t.ds_points,
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
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            title: { display: true, text: t.title_squares, font: { size: 13 } },
            tooltip: { callbacks: { afterLabel: (it) => cfg.squarePoints[it.dataIndex].pocet + ' ' + t.qso_suffix } },
        },
        scales: {
            x: { beginAtZero: true, ticks: { stepSize: 1 } },
            y: { grid: { display: false } },
        },
    },
}));

// ── Chart.js: QSO podle zemí (DXCC) a podle prefixů (vodorovné sloupce) ─────
// Náhrada za dva 3D koláče z legacy verze – seřazené vodorovné sloupce jsou
// čitelnější pro dlouhý chvost (řada zemí/prefixů po 1 QSO).

function countBarChart(canvasId, rows, labelKey, title) {
    return new Chart(document.getElementById(canvasId), {
        type: 'bar',
        data: {
            labels: rows.map((r) => r[labelKey]),
            datasets: [{
                label: t.qso_suffix,
                data: rows.map((r) => r.pocet),
                backgroundColor: 'rgba(139, 92, 246, 0.75)',
                borderColor: 'rgba(139, 92, 246, 1)',
                borderWidth: 1,
                borderRadius: 3,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                title: { display: true, text: title, font: { size: 13 } },
            },
            scales: {
                x: { beginAtZero: true, ticks: { stepSize: 1 } },
                y: { grid: { display: false } },
            },
        },
    });
}

charts.push(countBarChart('chartZeme', cfg.podleZemi, 'country', t.title_country));
charts.push(countBarChart('chartPrefix', cfg.podlePrefixu, 'prefix', t.title_prefix));

// ── Chart.js: Celoroční trend stanice (body + pořadí po kolech) ────────────

if (cfg.sezona) {
    charts.push(new Chart(document.getElementById('chartSezona'), {
        type: 'line',
        data: {
            labels: cfg.sezona.labels,
            datasets: [
                {
                    label: t.ds_points,
                    data: cfg.sezona.body,
                    borderColor: 'rgba(59,130,246,1)',
                    backgroundColor: 'rgba(59,130,246,.4)',
                    borderWidth: 2,
                    spanGaps: false,
                    yAxisID: 'y',
                },
                {
                    label: t.ds_rank,
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
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                title: { display: true, text: (t.title_season || ':call').replace(':call', cfg.pcall), font: { size: 13 } },
            },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: t.axis_points } },
                // Pořadí: 1. místo nahoře.
                y1: { position: 'right', reverse: true, suggestedMin: 1, ticks: { stepSize: 1 }, grid: { display: false }, title: { display: true, text: t.axis_rank } },
            },
        },
    }));
}

// ── Chart.js: Histogram vzdáleností skládaný podle druhu provozu ───────────

const distDatasets = [
    {
        label: 'SSB',
        data: cfg.distHistogram.ssb,
        backgroundColor: 'rgba(96, 165, 250, 0.75)',
        borderColor: 'rgba(29, 78, 216, 1)',
        borderWidth: 1,
        borderRadius: 3,
    },
    {
        label: 'CW',
        data: cfg.distHistogram.cw,
        backgroundColor: 'rgba(251, 191, 36, 0.75)',
        borderColor: 'rgba(180, 83, 9, 1)',
        borderWidth: 1,
        borderRadius: 3,
    },
];
if (cfg.distHistogram.ostatni.some((v) => v > 0)) {
    distDatasets.push({
        label: t.ds_other,
        data: cfg.distHistogram.ostatni,
        backgroundColor: 'rgba(156, 163, 175, 0.75)',
        borderColor: 'rgba(75, 85, 99, 1)',
        borderWidth: 1,
        borderRadius: 3,
    });
}

charts.push(new Chart(document.getElementById('chartDist'), {
    type: 'bar',
    data: {
        labels: cfg.distHistogram.labels.map((k) => k + ' km'),
        datasets: distDatasets,
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
            title: { display: true, text: t.title_dist, font: { size: 13 } },
        },
        scales: {
            y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } },
            x: { stacked: true, grid: { display: false } },
        },
    },
}));

// ── Export grafu do PNG (tlačítka ⤓ na kartách grafů) ──────────────────────

document.querySelectorAll('[data-chart-png]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const canvas = document.getElementById(btn.dataset.chartPng);
        if (!canvas) return;
        // Podklad barvou povrchu – průhledné PNG by mimo stránku nebylo čitelné.
        const out = document.createElement('canvas');
        out.width = canvas.width;
        out.height = canvas.height;
        const ctx = out.getContext('2d');
        ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--surface').trim() || '#fff';
        ctx.fillRect(0, 0, out.width, out.height);
        ctx.drawImage(canvas, 0, 0);
        const a = document.createElement('a');
        a.href = out.toDataURL('image/png');
        a.download = `${btn.dataset.nazev}-${cfg.pcall.replace(/[^\w-]+/g, '-')}.png`;
        a.click();
    });
});

// Živé přebarvení grafů i mapy při přepnutí denního/nočního režimu
// (třída .dark na <html>).
new MutationObserver(() => {
    applyChartTheme();
    charts.forEach((ch) => ch.update());
    engine.setTheme(isDark());
}).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
