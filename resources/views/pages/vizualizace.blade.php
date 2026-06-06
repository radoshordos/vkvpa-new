{{--
    Vizualizace EDI deníku: mapa, grafy, statistiky na jedné stránce.
    Leaflet (mapa, 3 přepínatelné vrstvy) + Chart.js (timeline, azimutová růžice, histogram vzdáleností).
--}}
@extends('layouts.app')

@section('title', 'Vizualizace – ' . $pcall . ' – VKV PA')

@push('head')
  <link rel="preconnect" href="https://tile.openstreetmap.org">
  @vite('resources/js/vizualizace.js')
  <style>
    #viz-mapa { height: 52vh; width: 100%; border-radius: .5rem; }
    .sq-label { background: transparent; border: none; box-shadow: none; color: #fff; font-weight: 700; font-size: 11px; }
    .map-tab { padding: .25rem .75rem; border-radius: .375rem; font-size: .8rem; font-weight: 600; cursor: pointer;
               border: 1px solid var(--color-line, #e2e8f0); background: var(--color-surface, #fff);
               color: var(--color-muted, #64748b); transition: background .15s, color .15s; }
    .map-tab.active, .map-tab:hover { background: var(--color-brand, #3b82f6); color: #fff; border-color: transparent; }
  </style>
@endpush

@section('content')

{{-- Inline JS config pro vizualizace.js --}}
<script>
window.__vizConfig = {
    pcall: @json($pcall),
    homeLoc: @json($homeLoc),
    home: @json($home),
    points: @json($mapPoints),
    squares: @json($squares),
    timeline: @json($timeline),
    azimuth: @json($azimuth),
    distHistogram: @json($distHistogram),
};
</script>

{{-- ── Hlavička ────────────────────────────────────────────────────────── --}}
<h1 class="text-xl font-bold text-heading">Vizualizace deníku {{ $pcall }}</h1>
<p class="text-sm text-muted mb-4">{{ $homeLoc }} · mapa a grafy (Leaflet + Chart.js)</p>

{{-- ── Statistické karty ───────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-5">
  @foreach ([
    ['Počet QSO',       $stats['pocet'],    'b.'],
    ['Unique lokátory', $stats['uniqueSq'], ''],
    ['Max. vzdálenost', $stats['maxDist'],  'km'],
    ['Průměr vzdálenost', $stats['avgDist'],'km'],
  ] as [$label, $value, $unit])
  <div class="rounded-lg border border-line bg-surface p-3 text-center">
    <div class="text-2xl font-bold text-heading">{{ $value }}<span class="text-sm font-normal text-muted ml-1">{{ $unit }}</span></div>
    <div class="text-xs text-muted mt-0.5">{{ $label }}</div>
  </div>
  @endforeach
</div>

{{-- ── Mapa s přepínatelnými vrstvami ─────────────────────────────────── --}}
<div class="rounded-lg border border-line bg-surface p-3 mb-5">
  <div class="flex items-center gap-2 mb-2 flex-wrap">
    <span class="text-sm font-semibold text-heading">Mapa</span>
    <button class="map-tab active" data-map-layer="jezek">Ježek</button>
    <button class="map-tab" data-map-layer="spendliky">Špendlíky</button>
    <button class="map-tab" data-map-layer="lokatory">Lokátory</button>
  </div>
  <div id="viz-mapa"></div>
</div>

{{-- ── Grafy: azimutová růžice + časová osa ───────────────────────────── --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-4">
  <div class="rounded-lg border border-line bg-surface p-3">
    <canvas id="chartAzimuth"></canvas>
  </div>
  <div class="rounded-lg border border-line bg-surface p-3">
    <canvas id="chartTimeline"></canvas>
  </div>
</div>

{{-- ── Graf: histogram vzdáleností ──────────────────────────────────────── --}}
<div class="rounded-lg border border-line bg-surface p-3">
  <canvas id="chartDist"></canvas>
</div>

@endsection
