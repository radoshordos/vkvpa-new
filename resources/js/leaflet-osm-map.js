import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { addFullscreenControl } from './leaflet-fullscreen.js';

// Leaflet mapa s OSM dlaždicemi a tlačítkem celé obrazovky, společné pro
// všechny vizualizační stránky.
export function createOsmMap(elementId) {
    const map = L.map(elementId);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);
    addFullscreenControl(map);
    return map;
}
