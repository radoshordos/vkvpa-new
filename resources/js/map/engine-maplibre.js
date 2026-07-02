import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import { MapboxOverlay } from '@deck.gl/mapbox';
import {
    ArcLayer,
    LineLayer,
    PathLayer,
    PolygonLayer,
    ScatterplotLayer,
    TextLayer,
} from '@deck.gl/layers';
import { modeGroup } from './viz-data.js';
import { modeColor } from '../leaflet-mode-colors.js';
import { bigSquareName } from '../maidenhead-grid.js';

// MapLibre GL + deck.gl engine vizualizace deníku: vektorový podklad
// OpenFreeMap (positron den / dark noc) a geometrie QSO kreslené na GPU
// (oblouky paprsků, špendlíky, čtverce, mřížka). Stejné rozhraní a stejná
// odvozená data (viz-data.js) jako Leaflet engine – liší se jen vykreslením.

const STYLES = {
    light: 'https://tiles.openfreemap.org/styles/positron',
    dark: 'https://tiles.openfreemap.org/styles/dark',
};

// Vrstvy, pod kterými se vykresluje mřížka velkých čtverců (Maidenhead).
const LAYERS_WITH_GRID = { crk: true, lokatory: true, ctverce: true };

// '#rrggbb' → [r, g, b, a] pro deck.gl.
function rgb(hex, a = 255) {
    const n = parseInt(hex.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255, a];
}

// hsl(h, s%, l%) → [r, g, b, a]; teplotní škála čtverců sdílí vzorec
// s Leaflet enginem (viz-data.squareHeatColor), tady potřebujeme čísla.
function hsl(h, s, l, a = 255) {
    s /= 100; l /= 100;
    const k = (n) => (n + h / 30) % 12;
    const f = (n) => l - s * Math.min(l, 1 - l) * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)));
    return [Math.round(255 * f(0)), Math.round(255 * f(8)), Math.round(255 * f(4)), a];
}

// Cílový bod z výchozího bodu, azimutu a vzdálenosti (sférická Země) –
// pro geodetické kružnice vzdáleností vrstvy CRK.
function destPoint(lat, lon, bearingDeg, distKm) {
    const R = 6371;
    const br = (bearingDeg * Math.PI) / 180;
    const f1 = (lat * Math.PI) / 180;
    const l1 = (lon * Math.PI) / 180;
    const d = distKm / R;
    const f2 = Math.asin(Math.sin(f1) * Math.cos(d) + Math.cos(f1) * Math.sin(d) * Math.cos(br));
    const l2 = l1 + Math.atan2(Math.sin(br) * Math.sin(d) * Math.cos(f1), Math.cos(d) - Math.sin(f1) * Math.sin(f2));
    return [(l2 * 180) / Math.PI, (f2 * 180) / Math.PI];
}

export function createMapLibreEngine() {
    let map = null;
    let overlay = null;
    let popup = null;
    let cfg = null;
    let data = null;
    let pts = [];
    let homePos = null;
    let crkCircles = [];
    let crkLabels = [];
    let grid = { lines: [], labels: [] };

    const state = { layer: null, filter: {}, time: 0 };

    function openPopup(html, lngLat) {
        popup.setLngLat(lngLat).setHTML(html).addTo(map);
    }

    // Klik na QSO špendlík → popup (varianta HTML podle vrstvy).
    const qsoClick = (htmlFor) => (info) => {
        if (info.object) openPopup(htmlFor(info.object), [info.object.lon, info.object.lat]);
    };

    // Mřížka velkých čtverců podle aktuálního výřezu (obdoba redrawMaidenheadGrid);
    // názvy čtverců jen při rozumném přiblížení, jinak by se slily.
    function rebuildGrid() {
        const b = map.getBounds();
        const zoom = map.getZoom();
        const west = Math.floor(b.getWest() / 2) * 2;
        const east = Math.ceil(b.getEast() / 2) * 2;
        const south = Math.floor(b.getSouth());
        const north = Math.ceil(b.getNorth());

        const lines = [];
        for (let lat = south; lat <= north; lat++) {
            lines.push([[west, lat], [east, lat]]);
        }
        for (let lng = west; lng <= east; lng += 2) {
            lines.push([[lng, south], [lng, north]]);
        }

        const labels = [];
        if (zoom >= 4 && zoom <= 8) {
            for (let lng = west; lng < east; lng += 2) {
                for (let lat = south; lat < north; lat++) {
                    labels.push({ pos: [lng + 1, lat + 0.5], name: bigSquareName(lng, lat) });
                }
            }
        }

        grid = { lines, labels };
    }

    // Rozpad čtverců podle zapnutých druhů provozu (Lokátory / Obsazené čtverce).
    function filteredSquares() {
        const rows = [];
        data.squareDetail.forEach(function (d, sq) {
            const c = data.squareCenter[sq];
            let count = 0;
            Object.keys(d.modes).forEach(function (g) { if (state.filter[g]) count += d.modes[g]; });
            if (count === 0) return;
            rows.push({ sq, count, lat: c.lat, lon: c.lon, popup: d.popup });
        });
        return rows;
    }

    function gridLayers() {
        return [
            new PathLayer({
                id: 'grid-lines',
                data: grid.lines,
                getPath: (d) => d,
                getColor: [0, 0, 0, 77],
                widthUnits: 'pixels',
                getWidth: 1,
            }),
            new TextLayer({
                id: 'grid-labels',
                data: grid.labels,
                getPosition: (d) => d.pos,
                getText: (d) => d.name,
                getSize: 11,
                getColor: [51, 51, 51, 255],
                outlineColor: [255, 255, 255, 220],
                outlineWidth: 2,
                fontSettings: { sdf: true },
                fontWeight: 700,
            }),
        ];
    }

    // Špendlíky QSO (barva dle provozu) – sdílené vrstvami; radius/popup dle užití.
    function pinsLayer(id, rows, { radius = 5, htmlFor, playback = false } = {}) {
        return new ScatterplotLayer({
            id,
            data: rows,
            getPosition: (p) => [p.lon, p.lat],
            radiusUnits: 'pixels',
            getRadius: playback ? (p) => (data.nasobicByIdx.has(p.idx) ? 7 : 5) : radius,
            getFillColor: (p) => rgb(modeColor(p.group).fill, 230),
            getLineColor: (p) => rgb(modeColor(p.group).stroke),
            stroked: true,
            lineWidthUnits: 'pixels',
            getLineWidth: playback ? (p) => (data.nasobicByIdx.has(p.idx) ? 2 : 1.5) : 1.5,
            pickable: true,
            onClick: qsoClick(htmlFor),
        });
    }

    // Paprsky z QTH jako GPU oblouky (efektnější než rovné čáry Leafletu).
    function arcsLayer(id, rows) {
        return new ArcLayer({
            id,
            data: rows,
            getSourcePosition: () => homePos,
            getTargetPosition: (p) => [p.lon, p.lat],
            getSourceColor: (p) => rgb(modeColor(p.group).fill, 150),
            getTargetColor: (p) => rgb(modeColor(p.group).fill, 220),
            widthUnits: 'pixels',
            getWidth: 1.5,
            getHeight: 0.18,
        });
    }

    function buildLayers() {
        const filtered = pts.filter((p) => state.filter[p.group]);
        const layers = [];

        if (LAYERS_WITH_GRID[state.layer]) layers.push(...gridLayers());

        if (state.layer === 'jezek') {
            if (homePos) layers.push(arcsLayer('jezek-arcs', filtered));
            layers.push(pinsLayer('jezek-pins', filtered, { htmlFor: data.popupJezek }));
        }

        if (state.layer === 'spendliky') {
            layers.push(pinsLayer('spendliky-pins', filtered, { htmlFor: data.popupSpendlik }));
        }

        if (state.layer === 'playback') {
            const shown = filtered.filter((p) => p.time <= state.time);
            if (homePos) layers.push(arcsLayer('playback-arcs', shown));
            layers.push(pinsLayer('playback-pins', shown, {
                htmlFor: (p) => data.popupPlayback(p, p.idx),
                playback: true,
            }));
        }

        if (state.layer === 'crk') {
            // Kružnice vzdáleností po 200 km s popiskem na severu.
            layers.push(new PathLayer({
                id: 'crk-circles',
                data: crkCircles,
                getPath: (d) => d.path,
                getColor: [255, 0, 0, 128],
                widthUnits: 'pixels',
                getWidth: 1,
            }));
            layers.push(new TextLayer({
                id: 'crk-circle-labels',
                data: crkLabels,
                getPosition: (d) => d.pos,
                getText: (d) => d.text,
                getSize: 11,
                getColor: [204, 0, 0, 255],
                outlineColor: [255, 255, 255, 220],
                outlineWidth: 2,
                fontSettings: { sdf: true },
                fontWeight: 700,
            }));
            if (homePos) {
                layers.push(new LineLayer({
                    id: 'crk-rays',
                    data: filtered,
                    getSourcePosition: () => homePos,
                    getTargetPosition: (p) => [p.lon, p.lat],
                    getColor: rgb('#cc0000', 90),
                    widthUnits: 'pixels',
                    getWidth: 1,
                }));
            }
            layers.push(pinsLayer('crk-pins', filtered, { radius: 4, htmlFor: data.popupSpendlik }));
        }

        if (state.layer === 'lokatory') {
            const rows = filteredSquares();
            layers.push(new ScatterplotLayer({
                id: 'lokatory-circles',
                data: rows,
                getPosition: (d) => [d.lon, d.lat],
                radiusUnits: 'pixels',
                getRadius: 14,
                getFillColor: rgb('#a855f7', 166),
                getLineColor: rgb('#7c3aed'),
                stroked: true,
                lineWidthUnits: 'pixels',
                getLineWidth: 2,
                pickable: true,
                onClick: (info) => { if (info.object) openPopup(info.object.popup, [info.object.lon, info.object.lat]); },
            }));
            layers.push(new TextLayer({
                id: 'lokatory-counts',
                data: rows,
                getPosition: (d) => [d.lon, d.lat],
                getText: (d) => String(d.count),
                getSize: 11,
                getColor: [255, 255, 255, 255],
                fontWeight: 700,
            }));
        }

        if (state.layer === 'ctverce') {
            // Obsazené čtverce: velký čtverec = 2° délky × 1° šířky kolem středu,
            // výplň teplotní škálou (víc QSO = červenější).
            const heatHue = (count) => 60 - 60 * Math.sqrt(count / data.maxSquareCount);
            layers.push(new PolygonLayer({
                id: 'ctverce-fill',
                data: filteredSquares(),
                getPolygon: (d) => [
                    [d.lon - 1, d.lat - 0.5], [d.lon + 1, d.lat - 0.5],
                    [d.lon + 1, d.lat + 0.5], [d.lon - 1, d.lat + 0.5],
                ],
                getFillColor: (d) => hsl(Math.round(heatHue(d.count)), 90, 50, 115),
                getLineColor: rgb('#b45309'),
                lineWidthUnits: 'pixels',
                getLineWidth: 1,
                stroked: true,
                pickable: true,
                onClick: (info) => { if (info.object) openPopup(info.object.popup, info.coordinate); },
            }));
        }

        // Domácí stanoviště vždy nahoře.
        if (homePos) {
            layers.push(new ScatterplotLayer({
                id: 'home',
                data: [cfg.home],
                getPosition: () => homePos,
                radiusUnits: 'pixels',
                getRadius: 8,
                getFillColor: rgb('#3b82f6', 230),
                getLineColor: rgb('#1d4ed8'),
                stroked: true,
                lineWidthUnits: 'pixels',
                getLineWidth: 2,
                pickable: true,
                onClick: (info) => { if (info.object) openPopup(data.popupHome, homePos); },
            }));
        }

        return layers;
    }

    function refresh() {
        overlay.setProps({ layers: buildLayers() });
    }

    function onMoveEnd() {
        if (!LAYERS_WITH_GRID[state.layer]) return;
        rebuildGrid();
        refresh();
    }

    return {
        init(options) {
            cfg = options.cfg;
            data = options.data;
            state.filter = { ...options.modeFilter };
            state.time = cfg.window.to;

            pts = cfg.points.map((p, idx) => ({ ...p, idx, group: modeGroup(p.mode) }));
            homePos = cfg.home ? [cfg.home.lon, cfg.home.lat] : null;

            // Kružnice vzdáleností CRK (geodetické, po 200 km do 1200 km).
            crkCircles = [];
            crkLabels = [];
            if (cfg.home) {
                for (let r = 200; r <= 1200; r += 200) {
                    const path = [];
                    for (let a = 0; a <= 360; a += 5) {
                        path.push(destPoint(cfg.home.lat, cfg.home.lon, a, r));
                    }
                    crkCircles.push({ path });
                    crkLabels.push({ pos: destPoint(cfg.home.lat, cfg.home.lon, 0, r), text: r + ' km' });
                }
            }

            const mapOptions = {
                container: options.container,
                style: options.dark ? STYLES.dark : STYLES.light,
            };

            // Výřez: všechna QSO + čtverce + QTH (obdoba fitMapToBounds).
            const bp = data.boundsPoints;
            if (bp.length >= 2) {
                const lons = bp.map((p) => p.lon);
                const lats = bp.map((p) => p.lat);
                mapOptions.bounds = [[Math.min(...lons), Math.min(...lats)], [Math.max(...lons), Math.max(...lats)]];
                mapOptions.fitBoundsOptions = { padding: 24 };
            } else if (bp.length === 1) {
                mapOptions.center = [bp[0].lon, bp[0].lat];
                mapOptions.zoom = 7;
            } else {
                mapOptions.center = [15, 50];
                mapOptions.zoom = 5;
            }

            map = new maplibregl.Map(mapOptions);
            map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-left');
            map.addControl(new maplibregl.FullscreenControl(), 'top-left');

            // Styl dark OpenFreeMap odkazuje na ikony chybějící ve sprite
            // (circle-11) – prázdný pixel warning utiší, POI značky podkladu
            // pro vizualizaci nejsou potřeba.
            map.on('styleimagemissing', (e) => {
                if (!map.hasImage(e.id)) {
                    map.addImage(e.id, { width: 1, height: 1, data: new Uint8Array(4) });
                }
            });

            popup = new maplibregl.Popup({ maxWidth: '320px' });

            // deck.gl overlay nad podkladem (overlaid – přežije setStyle při
            // přepnutí den/noc beze změny).
            overlay = new MapboxOverlay({
                layers: [],
                getCursor: ({ isHovering }) => (isHovering ? 'pointer' : 'grab'),
            });
            map.addControl(overlay);

            map.on('moveend', onMoveEnd);
        },

        setLayer(key) {
            state.layer = key;
            popup.remove();
            if (LAYERS_WITH_GRID[key]) rebuildGrid();
            refresh();
        },

        setModeFilter(filter) {
            state.filter = { ...filter };
            refresh();
        },

        // Viditelnost přehrávaných QSO = čas × filtr provozu; vrací počet zobrazených.
        applyTime(time) {
            state.time = time;
            if (state.layer === 'playback') refresh();
            return pts.filter((p) => state.filter[p.group] && p.time <= time).length;
        },

        // Špendlík daného QSO na mapě (orchestrátor předem zapne vrstvu i filtr).
        focusQso(idx) {
            const p = pts[idx];
            if (!p) return;
            map.flyTo({ center: [p.lon, p.lat], zoom: Math.max(map.getZoom(), 7) });
            openPopup(data.popupSpendlik(p), [p.lon, p.lat]);
        },

        setTheme(dark) {
            map.setStyle(dark ? STYLES.dark : STYLES.light);
        },

        fit() {
            const bp = data.boundsPoints;
            if (bp.length < 2) return;
            const lons = bp.map((p) => p.lon);
            const lats = bp.map((p) => p.lat);
            map.fitBounds([[Math.min(...lons), Math.min(...lats)], [Math.max(...lons), Math.max(...lats)]], { padding: 24 });
        },

        destroy() {
            if (popup) { popup.remove(); popup = null; }
            if (map) { map.remove(); map = null; }
            overlay = null;
            pts = [];
            crkCircles = [];
            crkLabels = [];
            grid = { lines: [], labels: [] };
            state.layer = null;
        },

        get layer() { return state.layer; },
    };
}
