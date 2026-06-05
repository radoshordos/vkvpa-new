import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const cfg = window.__mapConfig;

const map = L.map('mapa');
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap',
}).addTo(map);

const bounds = [];

if (cfg.home) {
    L.circleMarker([cfg.home.lat, cfg.home.lon], { radius: 7, color: '#0033cc', fillOpacity: 0.9 })
        .addTo(map)
        .bindPopup('QTH: ' + cfg.pcall + ' (' + cfg.homeLoc + ')');
    bounds.push([cfg.home.lat, cfg.home.lon]);
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
        let popup = p.call + '<br>' + p.wwl;
        if (cfg.mode === 'spendliky') {
            if (p.dist !== null) { popup += '<br>' + p.dist + ' km'; }
            if (p.azimut !== null) { popup += '<br>azimut ' + p.azimut + '°'; }
        } else {
            popup += '<br>' + p.points + ' b.';
        }

        L.circleMarker([p.lat, p.lon], { radius: 4, color: '#009933', fillOpacity: 0.8 })
            .addTo(map)
            .bindPopup(popup);
        bounds.push([p.lat, p.lon]);

        if (cfg.mode === 'jezek' && cfg.home) {
            L.polyline(
                [[cfg.home.lat, cfg.home.lon], [p.lat, p.lon]],
                { color: '#009933', weight: 1, opacity: 0.5 },
            ).addTo(map);
        }
    });
}

if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [20, 20] });
} else {
    map.setView([50, 15], 6);
}
