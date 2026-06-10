{{--
    Vizualizace EDI deníku: mapa, grafy, statistiky na jedné stránce.
    Leaflet (mapa, 4 přepínatelné vrstvy vč. kombinované CRK) + Chart.js (timeline, azimutová růžice, histogram vzdáleností).
--}}
@extends('layouts.app')

@section('title', 'Vizualizace – ' . $pcall . ' – VKV PA')

@push('head')
  <link rel="preconnect" href="https://tile.openstreetmap.org">
  @vite('resources/js/vizualizace.js')
  <style>
    #viz-mapa { height: 52vh; width: 100%; border-radius: .5rem; isolation: isolate; }
    .sq-label { background: transparent; border: none; box-shadow: none; color: #fff; font-weight: 700; font-size: 11px; }
    /* Popisky kružnic vzdáleností a mřížky lokátorů (vrstva „CRK"). */
    .km-label { background: transparent; border: none; box-shadow: none; color: #c00; font-weight: bold; font-size: 11px; white-space: nowrap; text-shadow: 0 0 2px #fff, 0 0 2px #fff; }
    .loc-label { background: transparent; border: none; box-shadow: none; color: #333; font-weight: bold; font-size: 11px; white-space: nowrap; text-shadow: 0 0 2px #fff, 0 0 2px #fff; }
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
    roundStations: @json($roundStations),
    compare: @json($compare),
    timeline: @json($timeline),
    azimuth: @json($azimuth),
    distHistogram: @json($distHistogram),
};
</script>

{{-- ── Hlavička ────────────────────────────────────────────────────────── --}}
<h1 class="text-xl font-bold text-heading">Vizualizace deníku {{ $pcall }}</h1>
<p class="text-sm text-muted mb-4">
  {{ $homeLoc }} · mapa a grafy (Leaflet + Chart.js) ·
  <a href="{{ route('edi.inkubator', ['head' => $head]) }}" class="underline hover:text-heading">🧪 Vizuální inkubátor</a>
</p>
@if ($roundDataPending)
  <p class="text-sm text-muted mb-4 -mt-3">ℹ️ Po vyhodnocení kola budou mapy obsahovat více dat – do vrstvy CRK přibudou všechny stanice z kola a půjde porovnat deníky účastníků.</p>
@endif

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
    <button class="map-tab active" data-map-layer="crk">CRK</button>
    <button class="map-tab" data-map-layer="jezek">Ježek</button>
    <button class="map-tab" data-map-layer="spendliky">Špendlíky</button>
    <button class="map-tab" data-map-layer="lokatory">Lokátory</button>
    @if ($compare !== null)
      <button class="map-tab" data-map-layer="porovnani">Porovnání</button>
    @endif
    {{-- Porovnání s deníkem soupeře z téhož kola (jen po uzávěrce/vyhodnocení). --}}
    @if ($rivals->isNotEmpty())
      <form method="get" class="ml-auto flex items-center gap-2">
        <label for="porovnat" class="text-xs text-muted">Porovnat s:</label>
        <select name="porovnat" id="porovnat" onchange="this.form.submit()"
                class="text-xs rounded border border-line bg-surface text-heading px-2 py-1">
          <option value="">— bez porovnání —</option>
          @foreach ($rivals as $r)
            <option value="{{ $r->id }}" @selected($compare !== null && $compare['rivalId'] === $r->id)>{{ $r->p_call }}</option>
          @endforeach
        </select>
      </form>
    @endif
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
