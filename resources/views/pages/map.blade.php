{{--
    Mapové pohledy na spojení – Leaflet 1.9.4 (bundlován přes npm/Vite).

    Jeden šablonový soubor obsluhuje tři režimy ($mode) ze tří akcí MapController:
      jezek     (M) – QTH uprostřed, čáry (paprsky) do protistanic
      spendliky (N) – špendlíky protistanic; popup = značka, lokátor, km, azimut
      lokatory  (S) – velké čtverce s počtem protistanic (popisek = počet)
--}}
@extends('layouts.app')

@section('title', 'Mapa spojení ' . $pcall . ' – VKV PA')

@push('head')
  <link rel="preconnect" href="https://tile.openstreetmap.org">
  @vite('resources/js/map.js')
  <style>
    #mapa { height: 70vh; width: 100%; border-radius: 0.6rem; }
    /* Popisek s počtem protistanic ve velkém čtverci (režim „lokatory"). */
    .sq-label { background: transparent; border: none; box-shadow: none; color: #fff; font-weight: bold; font-size: 12px; }
  </style>
@endpush

@section('content')
<script>
  window.__mapConfig = {
    mode: @json($mode->value),
    home: @json($home),
    points: @json($points),
    squares: @json($squares),
    pcall: @json($pcall),
    homeLoc: @json($homeLoc),
  };
</script>
<h1>Mapa spojení {{ $pcall }} ({{ $homeLoc }})</h1>
<p class="-mt-2 mb-3 text-sm text-muted">{{ $mode->label() }}</p>
<div class="table-wrap p-1"><div id="mapa"></div></div>
@endsection
