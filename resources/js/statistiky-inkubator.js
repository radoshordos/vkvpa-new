import { Chart, registerables } from 'chart.js';
import { applyChartTheme } from './chart-theme.js';

Chart.register(...registerables);

const cfg = window.__inkubatorConfig;
const hhmm = (m) => String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');

applyChartTheme();
const charts = [];

// ── 1) Heatmapa směr × čas (CSS grid, přepínání počet / km) ────────────────

const hm = cfg.heatmap;
const hmWrap = document.getElementById('hm-wrap');
let hmMetric = 'pocet';

function hmMax(metric) {
    let max = 0;
    hm[metric].forEach((row) => row.forEach((v) => { if (v > max) max = v; }));
    return max;
}

function hmColor(v, max) {
    if (v <= 0) return 'transparent';
    const r = max > 0 ? v / max : 0;
    // Světlá → tmavě modrá podle intenzity.
    return `hsl(210, 85%, ${Math.round(88 - 58 * r)}%)`;
}

function renderHeatmap() {
    const cols = hm.casy.length;
    const max = hmMax(hmMetric);
    const grid = document.createElement('div');
    grid.className = 'hm-grid';
    grid.style.gridTemplateColumns = `2.6rem repeat(${cols}, minmax(1.4rem, 1fr))`;
    grid.style.minWidth = `${2.6 + cols * 1.4}rem`;

    // Záhlaví: rohová buňka + popisky časů (svisle).
    grid.appendChild(document.createElement('div'));
    hm.casy.forEach((c) => {
        const el = document.createElement('div');
        el.className = 'hm-axis col';
        el.textContent = c;
        grid.appendChild(el);
    });

    // 16 řádků (sektory), S nahoře.
    hm.smery.forEach((dir, s) => {
        const lab = document.createElement('div');
        lab.className = 'hm-axis';
        lab.textContent = dir;
        grid.appendChild(lab);

        for (let col = 0; col < cols; col++) {
            const v = hm[hmMetric][s][col];
            const cell = document.createElement('div');
            cell.className = 'hm-cell';
            cell.style.background = hmColor(v, max);
            cell.style.color = (max > 0 && v / max > 0.5) ? '#fff' : 'var(--color-heading, #0f172a)';
            cell.title = `${dir} · ${hm.casy[col]} · ${v} ${hmMetric === 'km' ? 'km' : 'QSO'}`;
            cell.textContent = v > 0 ? String(v) : '';
            grid.appendChild(cell);
        }
    });

    hmWrap.replaceChildren(grid);
}

if (hmWrap) {
    renderHeatmap();
    document.querySelectorAll('[data-hm-metric]').forEach((btn) => {
        btn.addEventListener('click', () => {
            hmMetric = btn.dataset.hmMetric;
            document.querySelectorAll('[data-hm-metric]').forEach((b) => b.classList.toggle('active', b === btn));
            renderHeatmap();
        });
    });
}

// ── Grafy závislé na poli kategorie ────────────────────────────────────────

const pole = cfg.pole;

if (pole) {
    const tickMins = pole.ticks.map((_, i) => cfg.window.from + i * 15);

    // ── 2) Vs. pole kategorie (moje skóre + medián + kvartilové pásmo) ─────
    const poleEl = document.getElementById('chartPole');
    if (poleEl) {
        charts.push(new Chart(poleEl, {
            type: 'line',
            data: {
                labels: pole.ticks,
                datasets: [
                    { label: '75. perc.', data: pole.p75, borderColor: 'transparent', backgroundColor: 'rgba(148,163,184,.25)', fill: '+1', pointRadius: 0, stepped: true },
                    { label: '25. perc.', data: pole.p25, borderColor: 'transparent', backgroundColor: 'rgba(148,163,184,.25)', fill: false, pointRadius: 0, stepped: true },
                    { label: 'medián pole', data: pole.median, borderColor: 'rgba(100,116,139,1)', borderDash: [5, 4], borderWidth: 2, pointRadius: 0, stepped: true },
                    { label: cfg.pcall, data: pole.mojeBody, borderColor: 'rgba(59,130,246,1)', backgroundColor: 'rgba(59,130,246,.15)', borderWidth: 2, pointRadius: 2, stepped: true },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 }, filter: (it) => !it.text.includes('perc.') } },
                    title: { display: true, text: 'Průběžné skóre vs. pole kategorie', font: { size: 13 } },
                },
                scales: { y: { beginAtZero: true }, x: { grid: { display: false } } },
            },
        }));
    }

    // ── 3) Závod skóre (animace) ──────────────────────────────────────────
    const palette = ['#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
    const raceSeries = [{ call: cfg.pcall, body: pole.mojeBody, color: '#3b82f6', mine: true }]
        .concat(pole.race.map((r, i) => ({ call: r.call, body: r.body, color: palette[i % palette.length], mine: false })));

    const zavodEl = document.getElementById('chartZavod');
    let raceChart = null;
    if (zavodEl) {
        raceChart = new Chart(zavodEl, {
            type: 'line',
            data: {
                labels: pole.ticks,
                datasets: raceSeries.map((s) => ({
                    label: s.call,
                    data: s.body.slice(),
                    borderColor: s.color,
                    backgroundColor: s.color,
                    borderWidth: s.mine ? 3 : 1.5,
                    pointRadius: 0,
                    stepped: true,
                })),
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    title: { display: true, text: 'Závod skóre v čase', font: { size: 13 } },
                },
                scales: { y: { beginAtZero: true }, x: { grid: { display: false } } },
            },
        });
        charts.push(raceChart);

        const slider = document.getElementById('race-cas');
        const label = document.getElementById('race-cas-label');
        const playBtn = document.getElementById('race-play');

        function applyRace(time) {
            const k = tickMins.filter((m) => m <= time).length;
            raceChart.data.datasets.forEach((ds, di) => {
                ds.data = raceSeries[di].body.map((v, i) => (i < k ? v : null));
            });
            raceChart.update('none');
            label.textContent = hhmm(time) + ' UTC';
        }

        let timer = null;
        function stop() { if (timer) { clearInterval(timer); timer = null; } playBtn.textContent = '▶ Přehrát'; }
        function tick() {
            const t = Number(slider.value) + 1;
            slider.value = String(t);
            applyRace(t);
            if (t >= cfg.window.to) stop();
        }

        slider.addEventListener('input', () => { stop(); applyRace(Number(slider.value)); });
        playBtn.addEventListener('click', () => {
            if (timer) { stop(); return; }
            let t = Number(slider.value);
            if (t >= cfg.window.to) t = cfg.window.from;
            slider.value = String(t);
            applyRace(t);
            playBtn.textContent = '⏸ Pauza';
            timer = setInterval(tick, 60);
        });

        applyRace(cfg.window.to);
    }

    // ── 4) Rate sheet (moje vs. medián pole) ──────────────────────────────
    const rateEl = document.getElementById('chartRate');
    if (rateEl) {
        charts.push(new Chart(rateEl, {
            type: 'bar',
            data: {
                labels: pole.rateLabels,
                datasets: [
                    { label: cfg.pcall, data: pole.rateMoje, backgroundColor: 'rgba(59,130,246,.75)', borderColor: 'rgba(59,130,246,1)', borderWidth: 1, borderRadius: 3 },
                    { label: 'medián pole', data: pole.rateMedian, backgroundColor: 'rgba(148,163,184,.6)', borderColor: 'rgba(100,116,139,1)', borderWidth: 1, borderRadius: 3 },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    title: { display: true, text: 'QSO v 15min intervalech', font: { size: 13 } },
                },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } }, x: { grid: { display: false } } },
            },
        }));
    }
}

// ── 6) Export QSO do CSV ───────────────────────────────────────────────────

const csvBtn = document.getElementById('export-csv');
if (csvBtn) {
    csvBtn.addEventListener('click', () => {
        const head = ['cas', 'znacka', 'lokator', 'km', 'azimut', 'body', 'provoz'];
        const rows = cfg.qso.map((q) => [
            hhmm(q.time), q.call, q.wwl, q.dist ?? '', q.azimut ?? '', q.points, q.mode,
        ].join(';'));
        const csv = '﻿' + head.join(';') + '\n' + rows.join('\n');
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        a.download = `deník-${cfg.pcall.replace(/[^\w-]+/g, '-')}.csv`;
        a.click();
        URL.revokeObjectURL(a.href);
    });
}

// ── Export grafů do PNG (tlačítka ⤓) ───────────────────────────────────────

document.querySelectorAll('[data-ink-png]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const canvas = document.getElementById(btn.dataset.inkPng);
        if (!canvas) return;
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

// Živé přebarvení při přepnutí denního/nočního režimu.
new MutationObserver(() => { applyChartTheme(); charts.forEach((ch) => ch.update()); })
    .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
