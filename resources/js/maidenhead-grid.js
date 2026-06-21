import L from 'leaflet';

// Mřížka velkých čtverců Maidenhead (2° délky × 1° šířky); překresluje se
// podle výřezu mapy, názvy čtverců jen při rozumném přiblížení (jinak by se slily).

export function bigSquareName(lng, lat) {
    const a = 'A'.charCodeAt(0);
    const fieldLng = Math.floor((lng + 180) / 20);
    const fieldLat = Math.floor((lat + 90) / 10);
    const sqLng = Math.floor(((lng + 180) % 20) / 2);
    const sqLat = Math.floor((lat + 90) % 10);
    return String.fromCharCode(a + fieldLng) + String.fromCharCode(a + fieldLat) + sqLng + sqLat;
}

export function redrawMaidenheadGrid(map, gridLayer) {
    gridLayer.clearLayers();
    const b = map.getBounds();
    const zoom = map.getZoom();
    const west = Math.floor(b.getWest() / 2) * 2;
    const east = Math.ceil(b.getEast() / 2) * 2;
    const south = Math.floor(b.getSouth());
    const north = Math.ceil(b.getNorth());

    for (let lat = south; lat <= north; lat++) {
        L.polyline([[lat, west], [lat, east]], { color: '#000', weight: 1, opacity: 0.3 }).addTo(gridLayer);
    }
    for (let lng = west; lng <= east; lng += 2) {
        L.polyline([[south, lng], [north, lng]], { color: '#000', weight: 1, opacity: 0.3 }).addTo(gridLayer);
    }

    if (zoom >= 5 && zoom <= 9) {
        for (let lng = west; lng < east; lng += 2) {
            for (let lat = south; lat < north; lat++) {
                L.marker([lat + 0.5, lng + 1], {
                    icon: L.divIcon({ className: 'loc-label', html: bigSquareName(lng, lat), iconSize: null }),
                    interactive: false,
                }).addTo(gridLayer);
            }
        }
    }
}
