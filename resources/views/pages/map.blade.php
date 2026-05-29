{{-- Mapa spojení (Fáze 9) – Leaflet 1.9.4, nahrazuje všechny map*.php. --}}
@extends('layouts.app')

@section('title', 'Mapa spojení ' . $pcall . ' – VKV PA')

@push('head')
  <link rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin="">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
          integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
          crossorigin=""></script>
  <style>
    #mapa { height: 70vh; width: 100%; max-width: 900px; }
  </style>
@endpush

@section('content')
<h1>Mapa spojení {{ $pcall }} ({{ $homeLoc }})</h1>
<div id="mapa"></div>

<script>
  (function () {
    const home = @json($home);
    const points = @json($points);

    const map = L.map('mapa');
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    const bounds = [];

    if (home) {
      L.circleMarker([home.lat, home.lon], { radius: 7, color: '#0033cc', fillOpacity: 0.9 })
        .addTo(map)
        .bindPopup('QTH: {{ $pcall }} ({{ $homeLoc }})');
      bounds.push([home.lat, home.lon]);
    }

    points.forEach(function (p) {
      L.circleMarker([p.lat, p.lon], { radius: 4, color: '#009933', fillOpacity: 0.8 })
        .addTo(map)
        .bindPopup(p.call + '<br>' + p.wwl + '<br>' + p.points + ' b.');
      bounds.push([p.lat, p.lon]);
      if (home) {
        L.polyline([[home.lat, home.lon], [p.lat, p.lon]], { color: '#009933', weight: 1, opacity: 0.5 }).addTo(map);
      }
    });

    if (bounds.length > 0) {
      map.fitBounds(bounds, { padding: [20, 20] });
    } else {
      map.setView([50, 15], 6);
    }
  })();
</script>
@endsection
