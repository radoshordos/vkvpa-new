import L from 'leaflet';
import { Chart, registerables } from 'chart.js';
import { createOsmMap } from './leaflet-osm-map.js';
import {
    createHomeMarker,
    createQsoMarker,
    createQsoRay,
    fitMapToBounds,
    pushPointBounds,
    qsoPopupHtml,
} from './leaflet-qso-map.js';
import { modeColor, modeLabel } from './leaflet-mode-colors.js';
import { applyChartTheme } from './chart-theme.js';
import { redrawMaidenheadGrid } from './maidenhead-grid.js';

Chart.register(...registerables);

const cfg = window.__vizConfig;
// Lokalizované popisky (z lang/*/pages.php → viz.js), předané přes config.
const t = cfg.t || {};

const hhmm = (m) => String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');

// ── Leaflet mapa s přepínatelnými vrstvami ─────────────────────────────────

const map = createOsmMap('viz-mapa');

const bounds = [];

// Domácí stanoviště
let homeMarker = null;
if (cfg.home) {
    homeMarker = createHomeMarker(cfg.home, `<strong>${cfg.pcall}</strong><br>${cfg.homeLoc}`).addTo(map);
    pushPointBounds(bounds, cfg.home);
}

// Vrstva: ježek (čáry + body)
const jezekLayer = L.layerGroup();
// Vrstva: špendlíky
const spendlikyLayer = L.layerGroup();
// Vrstva: lokátory (velké čtverce – číslované značky se počtem QSO)
const lokatoryLayer = L.layerGroup();
// Vrstva: obsazené čtverce (velké čtverce vykreslené jako vyplněné obdélníky,
// barva podle počtu QSO – teplotní škála; přehled pokrytí mapy)
const ctverceLayer = L.layerGroup();
// Vrstva: CRK – kombinovaná mapa (paprsky + provoz + kružnice + mřížka + stanice z kola)
const crkLayer = L.layerGroup();
// Vrstva: přehrávání deníku (paprsky + špendlíky řízené časem na slideru)
const playbackLayer = L.layerGroup();

// ── Filtr druhu provozu napříč vrstvami ────────────────────────────────────
// Klíč skupiny = kód módu: 1=SSB, 2=CW, 3=SSB/CW, 4=CW/SSB, 5=AM, 6=FM,
// 0=Ostatní (shodně s tlačítky data-mode-filter a PHP enumem QsoMode).

const modeGroup = (m) => (m >= 1 && m <= 6 ? m : 0);
const modeFilter = { 0: true, 1: true, 2: true, 3: true, 4: true, 5: true, 6: true };

// Per-QSO prvky vrstev (mimo přehrávání – to filtruje applyTime): při změně
// filtru se přidávají/odebírají z mateřské skupiny.
const modeEntries = [];
function addModeEntry(group, mode, members) {
    members.forEach((m) => m.addTo(group));
    modeEntries.push({ mode: modeGroup(mode), members, group });
}

function applyModeFilter() {
    modeEntries.forEach(function (e) {
        e.members.forEach(function (m) {
            if (modeFilter[e.mode]) {
                if (!e.group.hasLayer(m)) e.group.addLayer(m);
            } else {
                e.group.removeLayer(m);
            }
        });
    });
    // Přehrávání respektuje filtr přes applyTime (viditelnost = čas × provoz).
    applyTime(Number(slider.value));
}

// QSO, která přinesla nový násobič – zvýraznění při přehrávání (idx = pořadí
// QSO v cfg.points).
const nasobicByIdx = new Map((cfg.multiplier || []).map((n) => [n.idx, n]));

// Položky přehrávání: každé QSO = paprsek + špendlík, řízené časem.
const playbackItems = [];
// Špendlíky podle pořadí QSO – klik na řádek TOP ODX je otevírá na mapě.
const spendlikMarkers = [];

cfg.points.forEach(function (p, idx) {
    pushPointBounds(bounds, p);

    // ježek: čára + barevný bod dle modu
    const jezekMembers = [];
    if (cfg.home) {
        jezekMembers.push(createQsoRay(cfg.home, p));
    }
    jezekMembers.push(createQsoMarker(p).bindPopup(qsoPopupHtml(p, { pointsLabel: t.pts })));
    addModeEntry(jezekLayer, p.mode, jezekMembers);

    // špendlíky – taktéž rozlišené barevně
    const spendlik = createQsoMarker(p).bindPopup(qsoPopupHtml(p, {
        includeDistance: true,
        includeAzimuth: true,
        azimuthLabel: t.azimuth,
    }));
    spendlikMarkers.push(spendlik);
    addModeEntry(spendlikyLayer, p.mode, [spendlik]);

    // přehrávání: paprsek + špendlík s časem QSO; nový násobič jen větší (barva dle provozu)
    const nn = nasobicByIdx.get(idx);
    const group = [];
    if (cfg.home) {
        group.push(createQsoRay(cfg.home, p));
    }
    group.push(createQsoMarker(p, nn ? { radius: 7, weight: 2 } : {}).bindPopup(qsoPopupHtml(p, {
        afterLocator: [`${hhmm(p.time)} UTC`],
        includeDistance: true,
        includeAzimuth: true,
        azimuthLabel: t.azimuth,
        pointsLabel: t.pts,
        extraLines: nn ? [`🆕 ${t.new_mult} ${nn.square} (${nn.poradi}.)`] : [],
    })));
    playbackItems.push({ t: p.time, mode: modeGroup(p.mode), group, shown: false });
});

// Maximální počet QSO ve čtverci – pro normalizaci teplotní škály obsazených čtverců.
const maxSquareCount = cfg.squares.reduce((m, s) => Math.max(m, s.count), 1);
// Teplotní škála žlutá → červená (víc QSO = červenější), odmocnina kvůli rozprostření.
const squareHeatColor = (count) => `hsl(${Math.round(60 - 60 * Math.sqrt(count / maxSquareCount))}, 90%, 50%)`;

cfg.squares.forEach(function (s) {
    bounds.push([s.lat, s.lon]);
    L.circleMarker([s.lat, s.lon], {
        radius: 14, color: '#7c3aed', fillColor: '#a855f7', fillOpacity: 0.65, weight: 2,
    }).addTo(lokatoryLayer)
        .bindTooltip(String(s.count), { permanent: true, direction: 'center', className: 'sq-label' })
        .bindPopup(`<strong>${s.square}</strong><br>${s.count} ${t.stations}`);

    // Obsazené čtverce: velký čtverec = 2° délky × 1° šířky kolem středu.
    L.rectangle([[s.lat - 0.5, s.lon - 1], [s.lat + 0.5, s.lon + 1]], {
        color: '#b45309', weight: 1, fillColor: squareHeatColor(s.count), fillOpacity: 0.45,
    }).addTo(ctverceLayer)
        .bindPopup(`<strong>${s.square}</strong><br>${s.count} ${t.stations}`);
});

// ── Legenda – obsah podle aktivní vrstvy ───────────────────────────────────

// Druhy provozu skutečně přítomné v deníku, v kanonickém pořadí (Ostatní na
// konec) – legenda i obarvení teček se řídí jen jimi.
const presentModes = [...new Set(cfg.points.map((p) => modeGroup(p.mode)))]
    .sort((a, b) => (a || 99) - (b || 99));

// Obarvení teček druhu provozu v legendě souhrnu i ve filtru (z jediné palety).
document.querySelectorAll('[data-mode-dot]').forEach(function (el) {
    el.style.background = modeColor(Number(el.dataset.modeDot)).fill;
});

let legendCtl = null;

function updateLegend(key) {
    if (legendCtl) { map.removeControl(legendCtl); legendCtl = null; }
    if (aggregateLayer(key)) return; // vrstva nemá barvy podle provozu

    const rows = presentModes.map((m) => [modeColor(m).fill, m === 0 ? t.other : modeLabel(m)]);

    legendCtl = L.control({ position: 'bottomright' });
    legendCtl.onAdd = function () {
        const div = L.DomUtil.create('div');
        div.style.cssText = 'background:rgba(255,255,255,.9);padding:6px 10px;border-radius:6px;font-size:12px;line-height:1.7;box-shadow:0 1px 4px rgba(0,0,0,.2);color:#333';
        div.innerHTML = `<strong style="display:block;margin-bottom:2px">${t.legend}</strong>`
            + rows.map(([c, l]) => `<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${c};margin-right:5px;vertical-align:middle"></span>${l}`).join('<br>');
        return div;
    };
    legendCtl.addTo(map);
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
    const members = [];
    if (cfg.home) {
        members.push(createQsoRay(cfg.home, p, { color: '#cc0000', weight: 1, opacity: 0.35 }));
    }
    members.push(createQsoMarker(p, { radius: 4, fillOpacity: 0.85 }).bindPopup(qsoPopupHtml(p, {
        includeDistance: true,
        includeAzimuth: true,
        azimuthLabel: t.azimuth,
    })));
    addModeEntry(crkLayer, p.mode, members);
});

// Mřížka velkých čtverců (2° délky × 1° šířky) – překresluje se podle výřezu,
// dokud je vrstva CRK aktivní (viz přepínání vrstev níže).
const crkGrid = L.layerGroup().addTo(crkLayer);

function redrawCrkGrid() {
    redrawMaidenheadGrid(map, crkGrid);
}

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
    let shown = 0;
    playbackItems.forEach(function (it) {
        const show = it.t <= time && modeFilter[it.mode];
        if (show) shown++;
        if (show && !it.shown) { it.group.forEach((g) => g.addTo(playbackLayer)); it.shown = true; }
        if (!show && it.shown) { it.group.forEach((g) => playbackLayer.removeLayer(g)); it.shown = false; }
    });
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

let timer = null;
// Rychlost přehrávání: základ 50 ms na minutu závodu, u početnějších deníků
// úměrně pomaleji (0,5 ms na QSO, strop 150 ms) – velký deník by při plné
// rychlosti probleskl příliš rychle na sledování.
const speedMs = Math.min(150, Math.max(50, Math.round(cfg.points.length / 2)));

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

// Výřez nastavíme dřív, než zapneme vrstvu – mřížka CRK čte map.getBounds().
fitMapToBounds(map, bounds);

// Přepínání vrstev přes tlačítka.
const layers = { jezek: jezekLayer, spendliky: spendlikyLayer, lokatory: lokatoryLayer, ctverce: ctverceLayer, crk: crkLayer, playback: playbackLayer };

// Vrstvy agregující čtverce nemají barvy podle druhu provozu – skrývá se u nich
// legenda i filtr provozu.
const aggregateLayer = (key) => key === 'lokatory' || key === 'ctverce';

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
    // Mimo Přehrávání časová linka v grafech nemá co sledovat – zhasnout.
    if (key !== 'playback' && chartTimeMarker) chartTimeMarker(null);
    // Filtr provozu nedává smysl na vrstvách agregujících čtverce (Lokátory, Obsazené čtverce).
    document.getElementById('viz-mode-filter').classList.toggle('hidden', aggregateLayer(key));
    updateLegend(key);
    if (homeMarker) homeMarker.addTo(map);
    layerSelect.value = key;
    // Vrstva do URL (#mapa-…) – odkaz na konkrétní vrstvu jde sdílet.
    history.replaceState(null, '', '#mapa-' + key);
}

const layerSelect = document.getElementById('viz-layer-select');
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

// Klik na řádek TOP ODX → špendlík daného QSO na mapě.
document.querySelectorAll('[data-odx-idx]').forEach(function (row) {
    row.addEventListener('click', function () {
        const idx = Number(row.dataset.odxIdx);
        const marker = spendlikMarkers[idx];
        if (!marker) return;
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
        document.getElementById('viz-mapa').scrollIntoView({ behavior: 'smooth', block: 'center' });
        map.setView(marker.getLatLng(), Math.max(map.getZoom(), 8));
        marker.openPopup();
    });
});

// Výchozí vrstva: z URL hashe (#mapa-…), jinak přehrávání deníku.
const hashLayer = (location.hash.match(/^#mapa-([a-z]+)$/) || [])[1];
showLayer(Object.hasOwn(layers, hashLayer) ? hashLayer : 'playback');

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

// Živé přebarvení grafů při přepnutí denního/nočního režimu (třída .dark na <html>).
new MutationObserver(() => {
    applyChartTheme();
    charts.forEach((ch) => ch.update());
}).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
