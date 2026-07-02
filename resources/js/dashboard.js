import { Chart, registerables } from 'chart.js';
import { applyChartTheme } from './chart-theme';

Chart.register(...registerables);
applyChartTheme();

const cfg = window.__dashboardConfig;

const dashboard = document.querySelector('[data-dashboard]') ?? document.documentElement;
const css = getComputedStyle(dashboard);
const rootCss = getComputedStyle(document.documentElement);

const cssColor = (name, fallback) => css.getPropertyValue(name).trim() || rootCss.getPropertyValue(name).trim() || fallback;
const withAlpha = (color, alpha) => {
    const value = color.trim();
    const hex = value.match(/^#([0-9a-f]{6})$/i);

    if (!hex) {
        return value;
    }

    const int = Number.parseInt(hex[1], 16);
    const red = (int >> 16) & 255;
    const green = (int >> 8) & 255;
    const blue = int & 255;

    return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
};

const colors = {
    primary: cssColor('--dashboard-chart-primary', '#9c2a35'),
    secondary: cssColor('--dashboard-chart-secondary', '#0f766e'),
    tertiary: cssColor('--dashboard-chart-tertiary', '#b45309'),
    plum: cssColor('--dashboard-chart-plum', '#6d28d9'),
    slate: cssColor('--dashboard-chart-slate', '#475569'),
    soft: cssColor('--dashboard-chart-soft', '#c2410c'),
    surface: rootCss.getPropertyValue('--surface').trim() || '#ffffff',
};

const isDark = document.documentElement.classList.contains('dark');
const gridColor = withAlpha(colors.slate, 0.18);
const tooltipBackground = isDark ? 'rgba(15, 23, 42, 0.94)' : withAlpha(colors.slate, 0.92);
const axisOptions = {
    grid: { color: gridColor },
    ticks: { color: Chart.defaults.color },
};

const barFill = (start, end) => (context) => {
    const { chart } = context;
    const { chartArea, ctx } = chart;

    if (!chartArea) {
        return withAlpha(start, 0.8);
    }

    const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
    gradient.addColorStop(0, withAlpha(start, 0.68));
    gradient.addColorStop(1, withAlpha(end, 0.9));

    return gradient;
};

// Graf: účastníci per kolo
new Chart(document.getElementById('chartKola'), {
    type: 'bar',
    data: {
        labels: cfg.trendKolaLabels,
        datasets: [{
            label: 'Účastníci',
            data: cfg.trendKolaData,
            backgroundColor: barFill(colors.secondary, colors.primary),
            borderColor: withAlpha(colors.primary, 0.75),
            borderRadius: 6,
            borderWidth: 1,
        }],
    },
    options: {
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: tooltipBackground,
                displayColors: false,
            },
        },
        scales: {
            y: { ...axisOptions, beginAtZero: true, ticks: { ...axisOptions.ticks, stepSize: 1 } },
            x: { ...axisOptions, grid: { display: false }, ticks: { ...axisOptions.ticks, maxRotation: 45 } },
        },
    },
});

// Graf: distribuce pásem
const palette = [
    colors.primary,
    colors.secondary,
    colors.tertiary,
    colors.plum,
    colors.soft,
    colors.slate,
    withAlpha(colors.primary, 0.68),
    withAlpha(colors.secondary, 0.68),
];

new Chart(document.getElementById('chartKategorie'), {
    type: 'doughnut',
    data: {
        labels: cfg.katLabels,
        datasets: [{
            data: cfg.katData,
            backgroundColor: palette,
            borderWidth: 2,
            borderColor: colors.surface,
            hoverOffset: 5,
        }],
    },
    options: {
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    boxWidth: 12,
                    boxHeight: 12,
                    padding: 14,
                },
            },
            tooltip: {
                backgroundColor: tooltipBackground,
            },
        },
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
                backgroundColor: withAlpha(colors.slate, 0.45),
                borderColor: withAlpha(colors.slate, 0.72),
                borderRadius: 5,
                borderWidth: 1,
            },
            {
                label: String(cfg.rok),
                data: cfg.aktData,
                backgroundColor: barFill(colors.tertiary, colors.primary),
                borderColor: withAlpha(colors.primary, 0.72),
                borderRadius: 5,
                borderWidth: 1,
            },
        ],
    },
    options: {
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'rectRounded',
                },
            },
            tooltip: {
                backgroundColor: tooltipBackground,
            },
        },
        scales: {
            y: { ...axisOptions, beginAtZero: true, ticks: { ...axisOptions.ticks, stepSize: 1 } },
            x: { ...axisOptions, grid: { display: false }, ticks: { ...axisOptions.ticks, maxRotation: 0 } },
        },
    },
});
