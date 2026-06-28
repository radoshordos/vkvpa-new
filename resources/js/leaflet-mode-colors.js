// Barvy a popisky dle druhu provozu, sdílené napříč vizualizačními mapami,
// legendou souhrnu i filtrem provozu. Oficiálně povolené módy 1–6 mají každý
// vlastní kontrastní barvu, cokoli jiného (kód 0 / neznámý) padá do „Ostatní"
// (šedá). Drží se 1:1 s PHP enumem App\Enums\QsoMode.

const MODE_COLORS = {
    1: { stroke: '#1e40af', fill: '#3b82f6' }, // SSB    – modrá
    2: { stroke: '#b45309', fill: '#f59e0b' }, // CW     – jantarová
    3: { stroke: '#15803d', fill: '#22c55e' }, // SSB/CW – zelená
    4: { stroke: '#6b21a8', fill: '#a855f7' }, // CW/SSB – fialová
    5: { stroke: '#b91c1c', fill: '#ef4444' }, // AM     – červená
    6: { stroke: '#0e7490', fill: '#06b6d4' }, // FM     – tyrkysová
};

const MODE_OTHER = { stroke: '#374151', fill: '#9ca3af' }; // Ostatní – šedá

const MODE_LABELS = {
    1: 'SSB',
    2: 'CW',
    3: 'SSB/CW',
    4: 'CW/SSB',
    5: 'AM',
    6: 'FM',
};

export function modeColor(mode) {
    return MODE_COLORS[mode] ?? MODE_OTHER;
}

export function modeLabel(mode) {
    return MODE_LABELS[mode] ?? '?';
}
