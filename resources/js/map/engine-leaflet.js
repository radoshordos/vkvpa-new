import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { addFullscreenControl } from '../leaflet-fullscreen.js';
import {
    createHomeMarker,
    createQsoMarker,
    createQsoRay,
    fitMapToBounds,
} from '../leaflet-qso-map.js';
import { redrawMaidenheadGrid } from '../maidenhead-grid.js';
import { modeGroup } from './viz-data.js';

// Leaflet engine vizualizace deníku: rastrové dlaždice (OSM den / CARTO noc)
// a 6 přepínatelných vrstev (přehrávání, CRK, ježek, špendlíky, lokátory,
// obsazené čtverce). Implementuje společné rozhraní mapových enginů – DOM
// ovládání (slider, filtr, legenda) drží orchestrátor ve vizualizace.js.

const TILES = {
    light: {
        url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        options: { maxZoom: 19, attribution: '&copy; OpenStreetMap' },
    },
    // Noční režim: CARTO dark_matter – tmavý rastr nad OSM daty.
    dark: {
        url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png',
        options: { maxZoom: 19, subdomains: 'abcd', attribution: '&copy; OpenStreetMap &copy; CARTO' },
    },
};

// Vrstvy, pod kterými se vykresluje mřížka velkých čtverců (Maidenhead) jako podklad.
const LAYERS_WITH_GRID = { crk: true, lokatory: true, ctverce: true };

export function createLeafletEngine() {
    let map = null;
    let tileLayer = null;
    let homeMarker = null;
    let layers = {};
    let gridLayer = null;
    let currentLayer = null;
    let modeFilter = {};
    let cfg = null;
    let data = null;

    // Per-QSO prvky vrstev (mimo přehrávání – to filtruje applyTime): při změně
    // filtru se přidávají/odebírají z mateřské skupiny.
    let modeEntries = [];
    // Položky přehrávání: každé QSO = paprsek + špendlík, řízené časem.
    let playbackItems = [];
    // Špendlíky podle pořadí QSO – klik na řádek TOP ODX je otevírá na mapě.
    let spendlikMarkers = [];

    function addModeEntry(group, mode, members) {
        members.forEach((m) => m.addTo(group));
        modeEntries.push({ mode: modeGroup(mode), members, group });
    }

    function redrawGrid() {
        redrawMaidenheadGrid(map, gridLayer);
    }

    // (Pře)kreslí vrstvy Lokátory + Obsazené čtverce podle aktuálního filtru provozu.
    function rebuildSquareLayers() {
        layers.lokatory.clearLayers();
        layers.ctverce.clearLayers();
        data.squareDetail.forEach(function (d, sq) {
            const c = data.squareCenter[sq];
            let count = 0;
            Object.keys(d.modes).forEach(function (g) { if (modeFilter[g]) count += d.modes[g]; });
            if (count === 0) return;

            L.circleMarker([c.lat, c.lon], {
                radius: 14, color: '#7c3aed', fillColor: '#a855f7', fillOpacity: 0.65, weight: 2,
            }).addTo(layers.lokatory)
                .bindTooltip(String(count), { permanent: true, direction: 'center', className: 'sq-label' })
                .bindPopup(d.popup);

            // Obsazené čtverce: velký čtverec = 2° délky × 1° šířky kolem středu.
            L.rectangle([[c.lat - 0.5, c.lon - 1], [c.lat + 0.5, c.lon + 1]], {
                color: '#b45309', weight: 1, fillColor: data.squareHeatColor(count), fillOpacity: 0.45,
            }).addTo(layers.ctverce)
                .bindPopup(d.popup);
        });
    }

    return {
        init(options) {
            cfg = options.cfg;
            data = options.data;
            modeFilter = { ...options.modeFilter };

            map = L.map(options.container);
            addFullscreenControl(map);
            this.setTheme(options.dark);

            layers = {
                jezek: L.layerGroup(),
                spendliky: L.layerGroup(),
                lokatory: L.layerGroup(),
                ctverce: L.layerGroup(),
                crk: L.layerGroup(),
                playback: L.layerGroup(),
            };
            gridLayer = L.layerGroup();
            modeEntries = [];
            playbackItems = [];
            spendlikMarkers = [];

            // Domácí stanoviště
            if (cfg.home) {
                homeMarker = createHomeMarker(cfg.home, data.popupHome);
            }

            cfg.points.forEach(function (p, idx) {
                // ježek: čára + barevný bod dle modu
                const jezekMembers = [];
                if (cfg.home) {
                    jezekMembers.push(createQsoRay(cfg.home, p));
                }
                jezekMembers.push(createQsoMarker(p).bindPopup(data.popupJezek(p)));
                addModeEntry(layers.jezek, p.mode, jezekMembers);

                // špendlíky – taktéž rozlišené barevně
                const spendlik = createQsoMarker(p).bindPopup(data.popupSpendlik(p));
                spendlikMarkers.push(spendlik);
                addModeEntry(layers.spendliky, p.mode, [spendlik]);

                // přehrávání: paprsek + špendlík s časem QSO; nový násobič jen
                // větší (barva dle provozu)
                const nn = data.nasobicByIdx.get(idx);
                const group = [];
                if (cfg.home) {
                    group.push(createQsoRay(cfg.home, p));
                }
                group.push(createQsoMarker(p, nn ? { radius: 7, weight: 2 } : {}).bindPopup(data.popupPlayback(p, idx)));
                playbackItems.push({ t: p.time, mode: modeGroup(p.mode), group, shown: false });
            });

            // ── Vrstva CRK: kombinovaná mapa ve stylu vkvzavody.crk.cz ────────
            // Kružnice vzdáleností po 200 km (až 1200 km) s popiskem na severu.
            if (cfg.home) {
                for (let r = 200000; r <= 1200000; r += 200000) {
                    L.circle([cfg.home.lat, cfg.home.lon], {
                        radius: r, color: 'red', weight: 1, fill: false, opacity: 0.5,
                    }).addTo(layers.crk);
                    const latOffset = r / 111320; // ~ stupně zem. šířky na metr
                    L.marker([cfg.home.lat + latOffset, cfg.home.lon], {
                        icon: L.divIcon({ className: 'km-label', html: r / 1000 + ' km', iconSize: null }),
                        interactive: false,
                    }).addTo(layers.crk);
                }
            }

            // Paprsky z QTH do protistanic + špendlíky barevně dle druhu provozu.
            cfg.points.forEach(function (p) {
                const members = [];
                if (cfg.home) {
                    members.push(createQsoRay(cfg.home, p, { color: '#cc0000', weight: 1, opacity: 0.35 }));
                }
                members.push(createQsoMarker(p, { radius: 4, fillOpacity: 0.85 }).bindPopup(data.popupSpendlik(p)));
                addModeEntry(layers.crk, p.mode, members);
            });

            rebuildSquareLayers();

            // Výřez nastavíme dřív, než se zapne vrstva – mřížka CRK čte map.getBounds().
            this.fit();
        },

        setLayer(key) {
            Object.values(layers).forEach((l) => map.removeLayer(l));
            if (homeMarker) map.removeLayer(homeMarker);
            map.off('moveend', redrawGrid);
            map.removeLayer(gridLayer);
            // Mřížku lokátorů přidáme jako první, aby ležela pod značkami vrstvy.
            if (LAYERS_WITH_GRID[key]) { redrawGrid(); gridLayer.addTo(map); map.on('moveend', redrawGrid); }
            layers[key].addTo(map);
            if (homeMarker) homeMarker.addTo(map);
            currentLayer = key;
        },

        setModeFilter(filter) {
            modeFilter = { ...filter };
            modeEntries.forEach(function (e) {
                e.members.forEach(function (m) {
                    if (modeFilter[e.mode]) {
                        if (!e.group.hasLayer(m)) e.group.addLayer(m);
                    } else {
                        e.group.removeLayer(m);
                    }
                });
            });
            // Vrstvy agregující čtverce (Lokátory, Obsazené čtverce) se přepočítají z QSO.
            rebuildSquareLayers();
        },

        // Viditelnost přehrávaných QSO = čas × filtr provozu; vrací počet zobrazených.
        applyTime(time) {
            let shown = 0;
            playbackItems.forEach(function (it) {
                const show = it.t <= time && modeFilter[it.mode];
                if (show) shown++;
                if (show && !it.shown) { it.group.forEach((g) => g.addTo(layers.playback)); it.shown = true; }
                if (!show && it.shown) { it.group.forEach((g) => layers.playback.removeLayer(g)); it.shown = false; }
            });
            return shown;
        },

        // Špendlík daného QSO na mapě (orchestrátor předem zapne vrstvu i filtr).
        focusQso(idx) {
            const marker = spendlikMarkers[idx];
            if (!marker) return;
            map.setView(marker.getLatLng(), Math.max(map.getZoom(), 8));
            marker.openPopup();
        },

        setTheme(dark) {
            if (tileLayer) map.removeLayer(tileLayer);
            const t = dark ? TILES.dark : TILES.light;
            tileLayer = L.tileLayer(t.url, t.options).addTo(map);
        },

        fit() {
            fitMapToBounds(map, data.boundsPoints.map((p) => [p.lat, p.lon]));
        },

        destroy() {
            if (map) { map.remove(); map = null; }
            layers = {};
            gridLayer = null;
            homeMarker = null;
            tileLayer = null;
            modeEntries = [];
            playbackItems = [];
            spendlikMarkers = [];
            currentLayer = null;
        },

        get layer() { return currentLayer; },
    };
}
