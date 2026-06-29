import { Chart, registerables } from 'chart.js';
import { applyChartTheme } from './chart-theme.js';

Chart.register(...registerables);
applyChartTheme();

// Stránka Dlouhodobé trendy: 100% skládaný plošný graf podílu pásem napříč
// všemi vyhodnocenými koly. Pro každé kolo počet stanic na pásmu (z band_id
// kategorie) přepočtený na procenta; přepínač 1/2/3/5 let / vše filtruje osu X
// podle roku kola. Data dodává inline config window.__trendyConfig.

const cfg = window.__trendyConfig || {};
const t = cfg.t || {};
const pasma = cfg.pasmaTrend || { rounds: [], bands: [], stanice: [] };

const PALETTE = ['#6366f1', '#8b5cf6', '#a78bfa', '#c4b5fd', '#f472b6', '#fb7185', '#fbbf24', '#34d399', '#22d3ee', '#60a5fa', '#a3e635', '#f87171'];

const pasmaEl = document.getElementById('chartPasma');
if (pasmaEl && Array.isArray(pasma.rounds) && pasma.rounds.length > 0 && Array.isArray(pasma.bands) && pasma.bands.length > 0) {
    const maxYear = Math.max(...pasma.rounds.map((r) => r.year));

    // Datasety (procentní podíl); years = 0 znamená celou historii.
    const build = (years) => {
        const idx = pasma.rounds.reduce((acc, r, i) => {
            if (years === 0 || r.year > maxYear - years) acc.push(i);
            return acc;
        }, []);
        const labels = idx.map((i) => pasma.rounds[i].name);
        // Základ 100 % = součet stanic přes pásma v daném kole.
        const totals = idx.map((i) => pasma.bands.reduce((s, _b, bi) => s + (pasma.stanice[bi][i] || 0), 0));
        const datasets = pasma.bands.map((b, bi) => {
            const color = PALETTE[bi % PALETTE.length];
            return {
                label: b.name,
                data: idx.map((i, k) => (totals[k] > 0 ? (100 * (pasma.stanice[bi][i] || 0)) / totals[k] : 0)),
                _counts: idx.map((i) => pasma.stanice[bi][i] || 0),
                backgroundColor: color + 'cc',
                borderColor: color,
                borderWidth: 1,
                fill: true,
                pointRadius: 0,
                tension: 0.25,
            };
        });
        return { labels, datasets };
    };

    const chart = new Chart(pasmaEl, {
        type: 'line',
        data: build(0),
        options: {
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const cnt = ctx.dataset._counts ? ctx.dataset._counts[ctx.dataIndex] : 0;
                            return `${ctx.dataset.label}: ${ctx.parsed.y.toFixed(1)} % (${cnt} ${t.stations || 'stanic'})`;
                        },
                    },
                },
            },
            scales: {
                x: { stacked: true, ticks: { maxRotation: 45, autoSkip: true } },
                y: { stacked: true, beginAtZero: true, max: 100, ticks: { callback: (v) => `${v} %` } },
            },
        },
    });

    const tabs = document.querySelectorAll('[data-pasma-years]');
    tabs.forEach((btn) => {
        btn.addEventListener('click', () => {
            const years = parseInt(btn.getAttribute('data-pasma-years'), 10) || 0;
            const next = build(years);
            chart.data.labels = next.labels;
            chart.data.datasets = next.datasets;
            chart.update();
            tabs.forEach((b) => b.classList.toggle('active', b === btn));
        });
    });
}
