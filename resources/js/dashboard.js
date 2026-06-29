import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const cfg = window.__dashboardConfig;

const isDark = document.documentElement.classList.contains('dark');
const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
const textColor = isDark ? '#a1a1aa' : '#71717a';
const brandColor = '#6366f1';
const prevColor = '#a78bfa';

Chart.defaults.color = textColor;
Chart.defaults.borderColor = gridColor;

// Graf: účastníci per kolo
new Chart(document.getElementById('chartKola'), {
    type: 'bar',
    data: {
        labels: cfg.trendKolaLabels,
        datasets: [{
            label: 'Účastníci',
            data: cfg.trendKolaData,
            backgroundColor: brandColor + 'cc',
            borderRadius: 4,
        }],
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } },
            x: { ticks: { maxRotation: 45 } },
        },
    },
});

// Graf: distribuce pásem
const palette = ['#6366f1', '#8b5cf6', '#a78bfa', '#c4b5fd', '#ddd6fe', '#ede9fe', '#4f46e5', '#7c3aed'];

new Chart(document.getElementById('chartKategorie'), {
    type: 'doughnut',
    data: {
        labels: cfg.katLabels,
        datasets: [{
            data: cfg.katData,
            backgroundColor: palette,
            borderWidth: 2,
            borderColor: isDark ? '#18181b' : '#ffffff',
        }],
    },
    options: {
        plugins: { legend: { position: 'right' } },
        cutout: '65%',
    },
});

// Graf: rok vs. rok
const maxLen = Math.max(cfg.aktData.length, cfg.prevData.length);
const koloLabels = Array.from({ length: maxLen }, (_, i) => `${i + 1}. kolo`);

new Chart(document.getElementById('chartRokVsRok'), {
    type: 'bar',
    data: {
        labels: koloLabels,
        datasets: [
            {
                label: String(cfg.rokPredchozi),
                data: cfg.prevData,
                backgroundColor: prevColor + 'aa',
                borderRadius: 3,
            },
            {
                label: String(cfg.rok),
                data: cfg.aktData,
                backgroundColor: brandColor + 'cc',
                borderRadius: 3,
            },
        ],
    },
    options: {
        plugins: { legend: { position: 'top' } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } },
            x: { ticks: { maxRotation: 0 } },
        },
    },
});
