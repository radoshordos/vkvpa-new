{{--
    Vizualizace EDI deníku: mapa, grafy, statistiky na jedné stránce.
    Leaflet (mapa, 5 přepínatelných vrstev vč. kombinované CRK a přehrávání)
    + Chart.js (průběh skóre, timeline s násobiči, vážená azimutová růžice,
    body podle čtverců, celoroční trend, histogram vzdáleností).
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
    #viz-cas { accent-color: var(--color-brand, #3b82f6); }
  </style>
@endpush

@section('content')

{{-- Inline JS config pro vizualizace.js --}}
<script @cspNonce>
window.__vizConfig = {
    pcall: @json($pcall),
    homeLoc: @json($homeLoc),
    home: @json($home),
    window: @json($window),
    points: @json($mapPoints),
    squares: @json($squares),
    roundStations: @json($roundStations),
    cumulative: @json($cumulative),
    timeline: @json($timeline),
    azimuth: @json($azimuth),
    squarePoints: @json($squarePoints),
    sezona: @json($sezona),
    distHistogram: @json($distHistogram),
};
</script>

{{-- ── Hlavička ────────────────────────────────────────────────────────── --}}
<h1 class="text-xl font-bold text-heading">Vizualizace deníku {{ $pcall }}</h1>
<p class="text-sm text-muted mb-4">
  {{ $homeLoc }} · mapa a grafy (Leaflet + Chart.js) ·
  {{-- Odkaz na porovnání jen když existuje aspoň jeden soupeř z téhož kola
       a kategorie (a kolo už je uzavřené/vyhodnocené) – jinak by stránka
       porovnání neměla co nabídnout. --}}
  @if ($porovnaniDostupne)
    <a href="{{ route('edi.porovnani', ['head' => $head]) }}" class="underline hover:text-heading">⚔️ Porovnání deníků</a> ·
  @endif
  <a href="{{ route('edi.inkubator', ['head' => $head]) }}" class="underline hover:text-heading">🧪 Vizuální inkubátor</a>
</p>
@if ($roundDataPending)
  <p class="text-sm text-muted mb-4 -mt-3">ℹ️ Po vyhodnocení kola budou mapy obsahovat více dat – do vrstvy CRK přibudou všechny stanice z kola a na stránce Porovnání deníků půjde srovnat deníky účastníků.</p>
@endif

{{-- ── Statistické karty ───────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-3">
  @foreach ([
    ['Počet QSO',       $stats['pocet'],    ''],
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

{{-- ── Tempo závodu + nezapočítaná QSO ─────────────────────────────────── --}}
<div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-3">
  <div class="rounded-lg border border-line bg-surface p-3 text-center">
    <div class="text-2xl font-bold text-heading">{{ $tempo['spickaQso'] }}<span class="text-sm font-normal text-muted ml-1">QSO/hod</span></div>
    <div class="text-xs text-muted mt-0.5">Špička {{ $tempo['spicka'] ?? '—' }}</div>
  </div>
  <div class="rounded-lg border border-line bg-surface p-3 text-center">
    <div class="text-2xl font-bold text-heading">{{ $tempo['pauza'] ?? '—' }}<span class="text-sm font-normal text-muted ml-1">min</span></div>
    <div class="text-xs text-muted mt-0.5">Nejdelší pauza {{ $tempo['pauzaKdy'] ? '(' . $tempo['pauzaKdy'] . ')' : '' }}</div>
  </div>
  <div class="rounded-lg border border-line bg-surface p-3 text-center">
    <div class="text-2xl font-bold text-heading">{{ $tempo['prumer'] }}<span class="text-sm font-normal text-muted ml-1">QSO/hod</span></div>
    <div class="text-xs text-muted mt-0.5">Průměrné tempo</div>
  </div>
  <div class="rounded-lg border border-line bg-surface p-3 text-center">
    <div class="text-2xl font-bold text-heading">{{ $nezapocitanaCelkem }}</div>
    <div class="text-xs text-muted mt-0.5">Nezapočítaná / označená QSO</div>
  </div>
</div>

{{-- ── Souhrn po druzích provozu ───────────────────────────────────────── --}}
@if ($modeStats !== [])
<div class="grid grid-cols-1 gap-3 sm:grid-cols-{{ min(3, count($modeStats)) }} mb-5">
  @foreach ($modeStats as $m)
  <div class="rounded-lg border border-line bg-surface p-3">
    <div class="text-sm font-semibold text-heading mb-1">{{ $m['label'] === '?' ? 'Ostatní druhy provozu' : $m['label'] }}</div>
    <div class="text-xs text-muted">
      {{ $m['pocet'] }} QSO · {{ $m['body'] }} b. za spojení · Ø {{ $m['avgDist'] }} km · max {{ $m['maxDist'] }} km
    </div>
  </div>
  @endforeach
</div>
@endif

{{-- ── Mapa s přepínatelnými vrstvami (vč. přehrávání deníku) ──────────── --}}
<div class="rounded-lg border border-line bg-surface p-3 mb-5">
  <div class="flex items-center gap-2 mb-2 flex-wrap">
    <span class="text-sm font-semibold text-heading">Mapa</span>
    <button class="map-tab active" data-map-layer="crk">CRK</button>
    <button class="map-tab" data-map-layer="jezek">Ježek</button>
    <button class="map-tab" data-map-layer="spendliky">Špendlíky</button>
    <button class="map-tab" data-map-layer="lokatory">Lokátory</button>
    <button class="map-tab" data-map-layer="playback">Přehrávání</button>
  </div>
  {{-- Ovládání přehrávání – viditelné jen v režimu „Přehrávání" (řídí JS). --}}
  <div id="viz-playback-controls" class="hidden items-center gap-3 mb-2 flex-wrap">
    <button type="button" id="viz-play" class="map-tab">▶ Přehrát</button>
    <input type="range" id="viz-cas" class="flex-1 min-w-40"
           min="{{ $window['from'] }}" max="{{ $window['to'] }}" value="{{ $window['to'] }}" step="1">
    <span class="text-sm font-mono font-semibold text-heading" id="viz-cas-label"></span>
    <span class="text-xs text-muted"><span id="viz-qso-count">0</span> QSO</span>
  </div>
  <div id="viz-mapa"></div>
</div>

{{-- ── Průběh skóre ────────────────────────────────────────────────────── --}}
<div class="rounded-lg border border-line bg-surface p-3 mb-4">
  <canvas id="chartPrubeh"></canvas>
  <p class="text-xs text-muted mt-2">Orientační průběh: kumulativní body za spojení × průběžný počet násobičů (vlastní čtverec {{ $homeSq }} se počítá od začátku). Počítá se jen z QSO s platným lokátorem.</p>
</div>

{{-- ── Grafy: timeline s násobiči + vážená azimutová růžice ───────────── --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-4">
  <div class="rounded-lg border border-line bg-surface p-3">
    <canvas id="chartTimeline"></canvas>
  </div>
  <div class="rounded-lg border border-line bg-surface p-3">
    <div class="flex items-center gap-2 mb-1 flex-wrap">
      <span class="text-xs text-muted">Vážit podle:</span>
      <button type="button" class="map-tab active" data-az-metric="pocet">Počet QSO</button>
      <button type="button" class="map-tab" data-az-metric="km">Kilometry</button>
      <button type="button" class="map-tab" data-az-metric="body">Body</button>
    </div>
    <canvas id="chartAzimuth"></canvas>
  </div>
</div>

{{-- ── Grafy: body podle čtverců + celoroční trend ─────────────────────── --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-4">
  <div class="rounded-lg border border-line bg-surface p-3">
    <canvas id="chartCtverce"></canvas>
  </div>
  <div class="rounded-lg border border-line bg-surface p-3">
    @if ($sezona !== null)
      <canvas id="chartSezona"></canvas>
      <p class="text-xs text-muted mt-2">Body a pořadí stanice {{ $pcall }} v kolech roku (z veřejné výsledkové listiny).</p>
    @else
      <p class="text-sm text-muted">Celoroční trend zatím není k dispozici – deník nemá přiřazené kolo nebo stanice nemá schválené záznamy.</p>
    @endif
  </div>
</div>

{{-- ── Graf: histogram vzdáleností ──────────────────────────────────────── --}}
<div class="rounded-lg border border-line bg-surface p-3">
  <canvas id="chartDist"></canvas>
</div>

@endsection
