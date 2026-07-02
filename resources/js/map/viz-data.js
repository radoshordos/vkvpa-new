import { qsoPopupHtml } from '../leaflet-qso-map.js';
import { modeColor, modeLabel } from '../leaflet-mode-colors.js';

// Odvozená data vizualizace sdílená mapovými enginy (Leaflet i MapLibre):
// skupiny druhů provozu, rozpad velkých čtverců s popupem, teplotní škála
// a násobiče podle pořadí QSO. Enginy z nich jen kreslí – výpočty a HTML
// popupů žijí tady, aby oba enginy ukazovaly totéž.

// Klíč skupiny = kód módu: 1=SSB, 2=CW, 3=SSB/CW, 4=CW/SSB, 5=AM, 6=FM,
// 0=Ostatní (shodně s tlačítky data-mode-filter a PHP enumem QsoMode).
export const modeGroup = (m) => (m >= 1 && m <= 6 ? m : 0);

export const hhmm = (m) => String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');

// Pořadí druhů provozu v rozpadu (Ostatní na konec), shodně s legendou.
const modeOrder = (g) => g || 99;

export function buildVizData(cfg) {
    const t = cfg.t || {};

    // QSO, která přinesla nový násobič – zvýraznění při přehrávání (idx =
    // pořadí QSO v cfg.points).
    const nasobicByIdx = new Map((cfg.multiplier || []).map((n) => [n.idx, n]));

    // Střed každého velkého čtverce (ze serverem předpočítaných souřadnic).
    const squareCenter = {};
    cfg.squares.forEach(function (s) {
        squareCenter[s.square] = { lat: s.lat, lon: s.lon };
    });

    // Body za spojení získané ve velkém čtverci (ze serverem počítané statistiky).
    const squareBody = {};
    cfg.squarePoints.forEach(function (s) { squareBody[s.square] = s.body; });

    // Detailní rozpad každého velkého čtverce z QSO deníku: počty podle druhu
    // provozu, různé stanice a ODX (nejvzdálenější QSO). Slouží jednak filtru
    // (počet ve čtverci = součet zapnutých druhů provozu), jednak popupu se
    // statistikou čtverce. Popup ukazuje vždy úplný rozpad – nezávisle na filtru.
    const squareDetail = new Map();
    cfg.points.forEach(function (p) {
        const sq = (p.wwl || '').slice(0, 4).toUpperCase();
        if (!squareCenter[sq]) return;
        let d = squareDetail.get(sq);
        if (!d) { d = { modes: {}, total: 0, calls: new Map(), odx: null }; squareDetail.set(sq, d); }
        d.modes[modeGroup(p.mode)] = (d.modes[modeGroup(p.mode)] || 0) + 1;
        d.total += 1;
        if (!d.calls.has(p.call)) d.calls.set(p.call, p.wwl);
        if (p.dist !== null && (d.odx === null || p.dist > d.odx.dist)) {
            d.odx = { call: p.call, wwl: p.wwl, dist: p.dist };
        }
    });

    // Popup se statistikou čtverce – plný (nefiltrovaný) rozpad, počítá se jednou.
    function buildSquarePopup(sq, d) {
        const modeRows = Object.keys(d.modes).map(Number).sort((a, b) => modeOrder(a) - modeOrder(b))
            .map((g) => `<span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:${modeColor(g).fill};margin-right:5px;vertical-align:middle"></span>${g === 0 ? t.other : modeLabel(g)}: <strong>${d.modes[g]}</strong>`)
            .join('<br>');

        let html = `<strong style="font-size:1.05em">${sq}</strong><br>`
            + `${d.total} ${t.qso_suffix} · ${d.calls.size} ${t.sq_stations} · ${squareBody[sq] || 0} ${t.pts}`
            + `<br><span style="opacity:.7">${t.sq_modes}:</span><br>${modeRows}`;

        if (d.odx) {
            html += `<br><span style="opacity:.7">${t.sq_odx}:</span> ${d.odx.call} (${d.odx.wwl}) ${d.odx.dist} km`;
        }

        html += `<br><span style="opacity:.7">${t.sq_calls}:</span><br>`
            + `<span style="display:inline-block;max-height:7em;overflow:auto">${[...d.calls.keys()].sort().join(', ')}</span>`;

        return html;
    }
    squareDetail.forEach((d, sq) => { d.popup = buildSquarePopup(sq, d); });

    // Maximální počet QSO ve čtverci – pro normalizaci teplotní škály obsazených
    // čtverců. Z plného (nefiltrovaného) součtu, aby škála zůstala při filtrování
    // provozu stabilní (barva čtverce neskáče jen kvůli vypnutí jiného čtverce).
    const maxSquareCount = cfg.squares.reduce((m, s) => Math.max(m, s.count), 1);
    // Teplotní škála žlutá → červená (víc QSO = červenější), odmocnina kvůli rozprostření.
    const squareHeatColor = (count) => `hsl(${Math.round(60 - 60 * Math.sqrt(count / maxSquareCount))}, 90%, 50%)`;

    // Druhy provozu skutečně přítomné v deníku, v kanonickém pořadí (Ostatní na
    // konec) – legenda i obarvení teček se řídí jen jimi.
    const presentModes = [...new Set(cfg.points.map((p) => modeGroup(p.mode)))]
        .sort((a, b) => (a || 99) - (b || 99));

    // Výřez mapy: domácí QTH + všechna QSO + středy čtverců.
    const boundsPoints = [];
    if (cfg.home) boundsPoints.push({ lat: cfg.home.lat, lon: cfg.home.lon });
    cfg.points.forEach((p) => boundsPoints.push({ lat: p.lat, lon: p.lon }));
    cfg.squares.forEach((s) => boundsPoints.push({ lat: s.lat, lon: s.lon }));

    // ── Popupy QSO (HTML) – jednotné pro oba enginy ────────────────────────
    const popupHome = cfg.home ? `<strong>${cfg.pcall}</strong><br>${cfg.homeLoc}` : '';
    const popupJezek = (p) => qsoPopupHtml(p, { pointsLabel: t.pts });
    const popupSpendlik = (p) => qsoPopupHtml(p, {
        includeDistance: true,
        includeAzimuth: true,
        azimuthLabel: t.azimuth,
    });
    const popupPlayback = (p, idx) => {
        const nn = nasobicByIdx.get(idx);
        return qsoPopupHtml(p, {
            afterLocator: [`${hhmm(p.time)} UTC`],
            includeDistance: true,
            includeAzimuth: true,
            azimuthLabel: t.azimuth,
            pointsLabel: t.pts,
            extraLines: nn ? [`🆕 ${t.new_mult} ${nn.square} (${nn.poradi}.)`] : [],
        });
    };

    return {
        t,
        nasobicByIdx,
        squareCenter,
        squareBody,
        squareDetail,
        maxSquareCount,
        squareHeatColor,
        presentModes,
        boundsPoints,
        popupHome,
        popupJezek,
        popupSpendlik,
        popupPlayback,
    };
}
