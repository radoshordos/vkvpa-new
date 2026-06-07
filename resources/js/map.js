import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const cfg = window.__mapConfig;
const HOME = cfg.home ? [cfg.home.lat, cfg.home.lon] : null;
const isCrk = cfg.mode === 'crk';

const map = L.map('mapa');
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap',
}).addTo(map);

const bounds = [];

// Barva špendlíku podle druhu provozu (QsoMode: 1 = SSB, 2 = CW, jiné = ostatní).
function modeColor(mode) {
    if (mode === 2) return '#0066cc'; // CW
    if (mode === 1) return '#cc3300'; // SSB
    return '#777777';                 // ostatní / neznámý
}

// Domácí QTH.
if (HOME) {
    L.circleMarker(HOME, { radius: 7, color: '#0033cc', fillColor: '#3366ff', fillOpacity: 0.9 })
        .addTo(map)
        .bindPopup('QTH: ' + cfg.pcall + ' (' + cfg.homeLoc + ')');
    bounds.push(HOME);
}

if (cfg.mode === 'lokatory') {
    cfg.squares.forEach(function (s) {
        L.circleMarker([s.lat, s.lon], { radius: 12, color: '#cc3300', fillColor: '#ff6633', fillOpacity: 0.75 })
            .addTo(map)
            .bindTooltip(String(s.count), { permanent: true, direction: 'center', className: 'sq-label' })
            .bindPopup(s.square + '<br>' + s.count + ' protistanic');
        bounds.push([s.lat, s.lon]);
    });
} else {
    cfg.points.forEach(function (p) {
        const latlng = [p.lat, p.lon];
        let popup = '<b>' + p.call + '</b><br>' + p.wwl;
        if (cfg.mode === 'spendliky' || isCrk) {
            if (p.dist !== null) { popup += '<br>' + p.dist + ' km'; }
            if (p.azimut !== null) { popup += '<br>azimut ' + p.azimut + '°'; }
        } else {
            popup += '<br>' + p.points + ' b.';
        }

        const color = isCrk ? modeColor(p.mode) : '#009933';
        L.circleMarker(latlng, { radius: 4, color: color, fillOpacity: 0.85 })
            .addTo(map)
            .bindPopup(popup);
        bounds.push(latlng);

        if (cfg.mode === 'jezek' && HOME) {
            L.polyline([HOME, latlng], { color: '#009933', weight: 1, opacity: 0.5 }).addTo(map);
        } else if (isCrk && HOME) {
            L.polyline([HOME, latlng], { color: '#cc0000', weight: 1, opacity: 0.35 }).addTo(map);
        }
    });
}

// --- Kombinovaná mapa „crk": přepínatelné vrstvy ---
if (isCrk) {
    const overlays = {};

    // Kružnice vzdáleností po 200 km (až 1200 km) s popiskem na severu.
    if (HOME) {
        const circles = L.layerGroup();
        for (let r = 200000; r <= 1200000; r += 200000) {
            L.circle(HOME, { radius: r, color: 'red', weight: 1, fill: false, opacity: 0.5 }).addTo(circles);
            const latOffset = r / 111320; // ~ stupně zem. šířky na metr
            L.marker([HOME[0] + latOffset, HOME[1]], {
                icon: L.divIcon({ className: 'km-label', html: r / 1000 + ' km', iconSize: null }),
                interactive: false,
            }).addTo(circles);
        }
        overlays['Kružnice po 200 km'] = circles;
    }

    // Mřížka velkých čtverců (2° délky × 1° šířky) s popisky – překresluje se
    // podle aktuálního výřezu, dokud je vrstva zapnutá.
    const grid = L.layerGroup();

    function bigSquareName(lng, lat) {
        const a = 'A'.charCodeAt(0);
        const fieldLng = Math.floor((lng + 180) / 20);
        const fieldLat = Math.floor((lat + 90) / 10);
        const sqLng = Math.floor(((lng + 180) % 20) / 2);
        const sqLat = Math.floor((lat + 90) % 10);
        return String.fromCharCode(a + fieldLng) + String.fromCharCode(a + fieldLat) + sqLng + sqLat;
    }

    function redrawGrid() {
        grid.clearLayers();
        const b = map.getBounds();
        const zoom = map.getZoom();
        const west = Math.floor(b.getWest() / 2) * 2;
        const east = Math.ceil(b.getEast() / 2) * 2;
        const south = Math.floor(b.getSouth());
        const north = Math.ceil(b.getNorth());

        for (let lat = south; lat <= north; lat++) {
            L.polyline([[lat, west], [lat, east]], { color: '#000', weight: 1, opacity: 0.3 }).addTo(grid);
        }
        for (let lng = west; lng <= east; lng += 2) {
            L.polyline([[south, lng], [north, lng]], { color: '#000', weight: 1, opacity: 0.3 }).addTo(grid);
        }

        // Názvy velkých čtverců jen při rozumném přiblížení (jinak by se slily).
        if (zoom >= 5 && zoom <= 9) {
            for (let lng = west; lng < east; lng += 2) {
                for (let lat = south; lat < north; lat++) {
                    L.marker([lat + 0.5, lng + 1], {
                        icon: L.divIcon({ className: 'loc-label', html: bigSquareName(lng, lat), iconSize: null }),
                        interactive: false,
                    }).addTo(grid);
                }
            }
        }
    }

    map.on('overlayadd', function (e) {
        if (e.layer === grid) { redrawGrid(); map.on('moveend', redrawGrid); }
    });
    map.on('overlayremove', function (e) {
        if (e.layer === grid) { map.off('moveend', redrawGrid); grid.clearLayers(); }
    });
    overlays['Mřížka lokátorů'] = grid;

    // Všechny stanice z kola s ≥ 5 QSO.
    const round = L.layerGroup();
    (cfg.roundStations || []).forEach(function (s) {
        L.circleMarker([s.lat, s.lon], { radius: 3, color: '#9933cc', fillColor: '#cc66ff', fillOpacity: 0.7 })
            .addTo(round)
            .bindPopup('<b>' + s.call + '</b><br>' + s.wwl + '<br>' + s.count + ' QSO');
    });
    overlays['Všechny stanice z kola (≥5 QSO)'] = round;

    L.control.layers(null, overlays, { collapsed: false }).addTo(map);
}

if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [20, 20] });
} else {
    map.setView([50, 15], 6);
}
