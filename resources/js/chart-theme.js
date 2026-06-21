import { Chart } from 'chart.js';

// Text i mřížku grafů bereme z CSS proměnných motivu (--muted, --line), takže
// ladí s paletou a s přepnutím třídy .dark. Výchozí šedá Chart.js by byla
// v nočním režimu nečitelná a mřížka neviditelná.
export function applyChartTheme() {
    const css = getComputedStyle(document.documentElement);
    Chart.defaults.color = css.getPropertyValue('--muted').trim() || '#666';
    Chart.defaults.borderColor = css.getPropertyValue('--line').trim() || 'rgba(0,0,0,.1)';
}
