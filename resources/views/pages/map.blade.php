{{--
    Mapové pohledy na spojení – Leaflet 1.9.4.

    Jeden šablonový soubor obsluhuje tři režimy ($mode) ze tří akcí MapController:
      jezek     (M) – QTH uprostřed, čáry (paprsky) do protistanic
      spendliky (N) – špendlíky protistanic; popup = značka, lokátor, km, azimut
      lokatory  (S) – velké čtverce s počtem protistanic (popisek = počet)
--}}
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
    #mapa { height: 70vh; width: 100%; border-radius: 0.6rem; }
    /* Popisek s počtem protistanic ve velkém čtverci (režim „lokatory"). */
    .sq-label { background: transparent; border: none; box-shadow: none; color: #fff; font-weight: bold; font-size: 12px; }
  </style>
@endpush

@section('content')
<h1>Mapa spojení {{ $pcall }} ({{ $homeLoc }})</h1>
<p class="-mt-2 mb-3 text-sm text-muted">{{ $mode->label() }}</p>
<div class="table-wrap p-1"><div id="mapa"></div></div>

<script>
  (function () {
    const mode = @json($mode->value);
    const home = @json($home);
    const points = @json($points);
    const squares = @json($squares);

    const map = L.map('mapa');
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    const bounds = [];

    // QTH zkoumané stanice (střed) – ve všech režimech.
    if (home) {
      L.circleMarker([home.lat, home.lon], { radius: 7, color: '#0033cc', fillOpacity: 0.9 })
        .addTo(map)
        .bindPopup('QTH: {{ $pcall }} ({{ $homeLoc }})');
      bounds.push([home.lat, home.lon]);
    }

    if (mode === 'lokatory') {
      // S – velké čtverce: značka uprostřed čtverce s počtem protistanic.
      squares.forEach(function (s) {
        L.circleMarker([s.lat, s.lon], { radius: 12, color: '#cc3300', fillColor: '#ff6633', fillOpacity: 0.75 })
          .addTo(map)
          .bindTooltip(String(s.count), { permanent: true, direction: 'center', className: 'sq-label' })
          .bindPopup(s.square + '<br>' + s.count + ' protistanic');
        bounds.push([s.lat, s.lon]);
      });
    } else {
      // M / N – jednotlivé protistanice.
      points.forEach(function (p) {
        let popup = p.call + '<br>' + p.wwl;
        if (mode === 'spendliky') {
          if (p.dist !== null) { popup += '<br>' + p.dist + ' km'; }
          if (p.azimut !== null) { popup += '<br>azimut ' + p.azimut + '°'; }
        } else {
          popup += '<br>' + p.points + ' b.';
        }

        L.circleMarker([p.lat, p.lon], { radius: 4, color: '#009933', fillOpacity: 0.8 })
          .addTo(map)
          .bindPopup(popup);
        bounds.push([p.lat, p.lon]);

        // M (ježek) – paprsek z QTH do protistanice.
        if (mode === 'jezek' && home) {
          L.polyline([[home.lat, home.lon], [p.lat, p.lon]], { color: '#009933', weight: 1, opacity: 0.5 }).addTo(map);
        }
      });
    }

    if (bounds.length > 0) {
      map.fitBounds(bounds, { padding: [20, 20] });
    } else {
      map.setView([50, 15], 6);
    }
  })();
</script>
@endsection
