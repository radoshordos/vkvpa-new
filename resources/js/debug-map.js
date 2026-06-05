import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const instances = {};

function buildMap(containerId, mode) {
    const cfg = window.__debugMapCfg;
    if (!cfg) return;

    if (instances[mode]) {
        instances[mode].invalidateSize();
        return;
    }

    const map = L.map(containerId);
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

    if (mode === 'lokatory') {
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
            if (mode === 'spendliky') {
                if (p.dist !== null) popup += '<br>' + p.dist + ' km';
                if (p.azimut !== null) popup += '<br>azimut ' + p.azimut + '°';
            } else {
                popup += '<br>' + p.points + ' b.';
            }
            L.circleMarker([p.lat, p.lon], { radius: 4, color: '#009933', fillOpacity: 0.8 })
                .addTo(map)
                .bindPopup(popup);
            bounds.push([p.lat, p.lon]);

            if (mode === 'jezek' && cfg.home) {
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

    instances[mode] = map;
}

document.addEventListener('DOMContentLoaded', function () {
    if (!window.__debugMapCfg) return;

    const tabs = [
        { key: 'm', mode: 'jezek' },
        { key: 'n', mode: 'spendliky' },
        { key: 's', mode: 'lokatory' },
    ];

    function activate(activeKey) {
        tabs.forEach(function (t) {
            const btn = document.getElementById('dbg-tab-' + t.key);
            const panel = document.getElementById('dbg-panel-' + t.key);
            if (!btn || !panel) return;

            const isActive = t.key === activeKey;
            panel.classList.toggle('hidden', !isActive);
            btn.classList.toggle('btn-primary', isActive);
            btn.classList.toggle('btn-ghost', !isActive);

            if (isActive) {
                requestAnimationFrame(function () {
                    buildMap('dbg-mapa-' + t.key, t.mode);
                });
            }
        });
    }

    tabs.forEach(function (t) {
        const btn = document.getElementById('dbg-tab-' + t.key);
        if (btn) btn.addEventListener('click', function () { activate(t.key); });
    });

    activate('m');
});
