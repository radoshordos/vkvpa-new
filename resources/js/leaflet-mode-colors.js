// Barvy a popisky dle druhu provozu, sdílené napříč vizualizačními mapami:
// 1=SSB (modrá), 2=CW (oranžová), ostatní (šedá).

export function modeColor(mode) {
    if (mode === 1) return { stroke: '#1d4ed8', fill: '#60a5fa' }; // SSB – modrá
    if (mode === 2) return { stroke: '#b45309', fill: '#fbbf24' }; // CW  – oranžová
    return { stroke: '#4b5563', fill: '#9ca3af' };                 // neznámý
}

export function modeLabel(mode) {
    if (mode === 1) return 'SSB';
    if (mode === 2) return 'CW';
    return '?';
}
