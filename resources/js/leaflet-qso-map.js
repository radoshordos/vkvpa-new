import L from 'leaflet';
import { modeColor, modeLabel } from './leaflet-mode-colors.js';

const HOME_MARKER_STYLE = {
    radius: 8,
    color: '#1d4ed8',
    fillColor: '#3b82f6',
    fillOpacity: 0.9,
    weight: 2,
};

export function pointLatLng(point) {
    return [point.lat, point.lon];
}

export function pushPointBounds(bounds, point) {
    if (point) bounds.push(pointLatLng(point));
}

export function fitMapToBounds(map, bounds, options = {}) {
    const {
        padding = [24, 24],
        fallbackCenter = [50, 15],
        fallbackZoom = 6,
        maxZoom,
        singleZoom = null,
    } = options;

    if (bounds.length === 0) {
        if (fallbackCenter) map.setView(fallbackCenter, fallbackZoom);
        return;
    }

    if (bounds.length === 1 && singleZoom !== null) {
        map.setView(bounds[0], singleZoom);
        return;
    }

    const fitOptions = { padding };
    if (maxZoom !== undefined) fitOptions.maxZoom = maxZoom;

    map.fitBounds(bounds, fitOptions);
}

export function createHomeMarker(point, popup = '', style = {}) {
    const marker = L.circleMarker(pointLatLng(point), { ...HOME_MARKER_STYLE, ...style });

    return popup ? marker.bindPopup(popup) : marker;
}

export function createQsoRay(home, point, options = {}) {
    const { color = modeColor(point.mode).fill, weight = 1.2, opacity = 0.55 } = options;

    return L.polyline([pointLatLng(home), pointLatLng(point)], { color, weight, opacity });
}

export function createQsoMarker(point, options = {}) {
    const color = options.color || modeColor(point.mode);

    return L.circleMarker(pointLatLng(point), {
        radius: options.radius ?? 5,
        color: color.stroke,
        fillColor: color.fill,
        fillOpacity: options.fillOpacity ?? 0.9,
        weight: options.weight ?? 1.5,
    });
}

export function qsoPopupHtml(point, options = {}) {
    const {
        linkCall = false,
        afterLocator = [],
        includeDistance = false,
        includeAzimuth = false,
        azimuthLabel = '',
        pointsLabel = null,
        extraLines = [],
    } = options;

    const call = point.call || '';
    const callHtml = linkCall
        ? `<a href="https://www.qrz.com/db/${encodeURIComponent(call)}" target="_blank" rel="noopener">${call}</a>`
        : call;
    const lines = [
        `<strong>${callHtml}</strong> <span style="font-size:.8em;opacity:.7">${modeLabel(point.mode)}</span>`,
        point.wwl,
        ...afterLocator,
    ];

    if (includeDistance && point.dist !== null && point.dist !== undefined) {
        lines.push(`${point.dist} km`);
    }

    if (includeAzimuth && point.azimut !== null && point.azimut !== undefined) {
        lines.push(`${azimuthLabel ? `${azimuthLabel} ` : ''}${point.azimut}°`);
    }

    if (pointsLabel !== null && point.points !== null && point.points !== undefined) {
        lines.push(`${point.points} ${pointsLabel}`.trim());
    }

    lines.push(...extraLines);

    return lines.filter((line) => line !== '').join('<br>');
}
