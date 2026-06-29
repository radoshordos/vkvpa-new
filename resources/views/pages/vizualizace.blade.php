{{--
    Vizualizace EDI deníku: mapa, grafy, statistiky na jedné stránce.
    Leaflet (mapa, 5 přepínatelných vrstev vč. kombinované CRK a přehrávání)
    + Chart.js (průběh skóre, timeline s násobiči, vážená azimutová růžice,
    body podle čtverců, celoroční trend, histogram vzdáleností).
--}}
@extends('layouts.app')

@section('title', __('pages.viz.title', ['call' => $pcall]))
@section('meta_description', __('pages.viz.meta', ['call' => $pcall]))

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
    /* Barevná tečka druhu provozu (barvu nastavuje JS z palety leaflet-mode-colors). */
    .mode-dot { display: inline-block; width: .7rem; height: .7rem; border-radius: 9999px; flex: 0 0 auto;
                background: var(--color-muted, #9ca3af); box-shadow: 0 0 0 1px rgba(0,0,0,.15) inset; }
    /* Na neaktivním filtru tečka vybledne, aby bylo poznat vypnutí. */
    .map-tab:not(.active) .mode-dot { opacity: .35; }
    .map-select { padding: .25rem 1.75rem .25rem .75rem; border-radius: .375rem; font-size: .8rem; font-weight: 600; cursor: pointer;
                  border: 1px solid var(--color-line, #e2e8f0); background: var(--color-surface, #fff); color: var(--color-heading, #0f172a);
                  appearance: none;
                  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%2364748b'%3E%3Cpath d='M4 6l4 4 4-4'/%3E%3C/svg%3E");
                  background-repeat: no-repeat; background-position: right .4rem center; background-size: 1rem; }
    .map-select:focus-visible { outline: 2px solid var(--color-brand, #3b82f6); outline-offset: 1px; }
    #viz-cas { accent-color: var(--color-brand, #3b82f6); }
    /* Tlačítko „stáhnout graf jako PNG" v rohu karty grafu. */
    .chart-png { position: absolute; top: .4rem; right: .5rem; z-index: 1; padding: .15rem .35rem; border: none;
                 border-radius: .25rem; background: transparent; color: var(--color-muted, #64748b);
                 font-size: .9rem; line-height: 1; cursor: pointer; }
    .chart-png:hover { color: #fff; background: var(--color-brand, #3b82f6); }
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
    multiplier: @json($multiplier),
    squares: @json($squares),
    roundStations: @json($roundStations),
    cumulative: @json($cumulative),
    timeline: @json($timeline),
    azimuth: @json($azimuth),
    squarePoints: @json($squarePoints),
    podleZemi: @json($podleZemi),
    podlePrefixu: @json($podlePrefixu),
    sezona: @json($sezona),
    distHistogram: @json($distHistogram),
    t: @json(__('pages.viz.js')),
};
</script>

{{-- ── Hlavička ────────────────────────────────────────────────────────── --}}
<h1 class="text-xl font-bold text-heading">{{ __('pages.viz.heading', ['call' => $pcall]) }}</h1>
<p class="text-sm text-muted mb-4">
  {{ $homeLoc }} · {{ __('pages.viz.subtitle_charts') }} ·
  @if ($ediSouborDostupny)
    <a href="{{ route('edi.soubor', ['head' => $head]) }}" class="underline hover:text-heading" title="{{ __('app.edi_link_original') }}">EDI</a> ·
  @endif
  {{-- Odkaz na porovnání jen když existuje aspoň jeden soupeř z téhož kola
       a kategorie (a kolo už je uzavřené/vyhodnocené) – jinak by stránka
       porovnání neměla co nabídnout. --}}
  @if ($porovnaniDostupne)
    <a href="{{ route('edi.porovnani', ['head' => $head]) }}" class="underline hover:text-heading">{{ __('pages.viz.compare_link') }}</a> ·
  @endif
</p>
@if ($roundDataPending)
  <p class="text-sm text-muted mb-4 -mt-3">{{ __('pages.viz.round_pending') }}</p>
@endif

{{-- ── Údaje o stanici z hlavičky EDI ──────────────────────────────────── --}}
@php
  // Výkon, anténa, TRX a operátor z hlavičky deníku; nevyplněná pole se
  // zobrazí s popiskem „nevyplněno", aby řádek zůstal jednotný.
  $stationInfo = [
    ['label' => __('pages.viz.station_operator'), 'value' => trim((string) $head->r_name)],
    ['label' => __('pages.viz.station_power'),    'value' => $head->s_powe > 0 ? $head->s_powe . ' W' : ''],
    ['label' => __('pages.viz.station_antenna'),  'value' => trim((string) $head->s_ante)],
    ['label' => __('pages.viz.station_trx'),      'value' => trim((string) $head->s_tx_eq)],
  ];
@endphp
<div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-3">
  @foreach ($stationInfo as $info)
  <div class="rounded-lg border border-line bg-surface p-3 text-center">
    @if ($info['value'] !== '')
      <div class="text-2xl font-bold text-heading break-words">{{ $info['value'] }}</div>
    @else
      <div class="text-2xl font-normal italic text-muted">{{ __('pages.viz.station_empty') }}</div>
    @endif
    <div class="text-xs text-muted mt-0.5">{{ $info['label'] }}</div>
  </div>
  @endforeach
</div>

{{-- ── Statistické karty ───────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-3">
  @foreach ([
    [__('pages.viz.stat_qso'),     $stats['pocet'],    '',   __('pages.viz.stat_qso_hint')],
    [__('pages.viz.stat_unique'),  $stats['uniqueSq'], '',   __('pages.viz.stat_unique_hint')],
    [__('pages.viz.stat_maxdist'), $stats['maxDist'],  'km', __('pages.viz.stat_maxdist_hint')],
    [__('pages.viz.stat_avgdist'), $stats['avgDist'],  'km', __('pages.viz.stat_avgdist_hint')],
  ] as [$label, $value, $unit, $hint])
  <div class="rounded-lg border border-line bg-surface p-3 text-center cursor-help" title="{{ $hint }}">
    <div class="text-2xl font-bold text-heading">{{ $value }}<span class="text-sm font-normal text-muted ml-1">{{ $unit }}</span></div>
    <div class="text-xs text-muted mt-0.5">{{ $label }}</div>
  </div>
  @endforeach
</div>

{{-- ── Tempo závodu + nezapočítaná QSO ─────────────────────────────────── --}}
<div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-3">
  <div class="rounded-lg border border-line bg-surface p-3 text-center cursor-help" title="{{ __('pages.viz.tempo_peak_hint') }}">
    <div class="text-2xl font-bold text-heading">{{ $tempo['spickaQso'] }}<span class="text-sm font-normal text-muted ml-1">{{ __('pages.viz.tempo_qso_per_hour') }}</span></div>
    <div class="text-xs text-muted mt-0.5">{{ __('pages.viz.tempo_peak', ['when' => $tempo['spicka'] ?? '—']) }}</div>
  </div>
  <div class="rounded-lg border border-line bg-surface p-3 text-center cursor-help" title="{{ __('pages.viz.tempo_pause_hint') }}">
    <div class="text-2xl font-bold text-heading">{{ $tempo['pauza'] ?? '—' }}<span class="text-sm font-normal text-muted ml-1">{{ __('pages.viz.tempo_min') }}</span></div>
    <div class="text-xs text-muted mt-0.5">{{ __('pages.viz.tempo_longest_pause', ['when' => $tempo['pauzaKdy'] ? '(' . $tempo['pauzaKdy'] . ')' : '']) }}</div>
  </div>
  <div class="rounded-lg border border-line bg-surface p-3 text-center cursor-help" title="{{ __('pages.viz.tempo_avg_hint') }}">
    <div class="text-2xl font-bold text-heading">{{ $tempo['prumer'] }}<span class="text-sm font-normal text-muted ml-1">{{ __('pages.viz.tempo_qso_per_hour') }}</span></div>
    <div class="text-xs text-muted mt-0.5">{{ __('pages.viz.tempo_avg') }}</div>
  </div>
  <div class="rounded-lg border border-line bg-surface p-3 text-center cursor-help" title="{{ __('pages.viz.tempo_uncounted_hint') }}">
    <div class="text-2xl font-bold text-heading">{{ $nezapocitanaCelkem }}</div>
    <div class="text-xs text-muted mt-0.5">{{ __('pages.viz.tempo_uncounted') }}</div>
  </div>
</div>

{{-- ── Souhrn po druzích provozu ───────────────────────────────────────── --}}
@if ($modeStats !== [])
<div class="section-head">{{ __('pages.viz.mode_heading') }}</div>
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 mb-5">
  @foreach ($modeStats as $m)
  <div class="rounded-lg border border-line bg-surface p-3">
    <div class="flex items-center gap-2 text-sm font-semibold text-heading mb-1">
      <span class="mode-dot" data-mode-dot="{{ $m['mode'] }}"></span>
      {{ $m['mode'] === 0 ? __('pages.viz.mode_other') : $m['label'] }}
    </div>
    <div class="text-xs text-muted">
      {{ $m['pocet'] }} QSO · {{ $m['body'] }} {{ __('pages.viz.mode_pts_per_qso') }} · Ø {{ $m['avgDist'] }} km · max {{ $m['maxDist'] }} km
    </div>
  </div>
  @endforeach
</div>
@endif

{{-- ── Soapbox a poznámka účastníka (dvojnásobně široké dlaždice) ───────── --}}
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 mb-5">
  @foreach ([
    [__('pages.viz.station_soapbox'), $soapbox],
    [__('pages.viz.station_note'),    $poznamka],
  ] as [$label, $value])
  <div class="rounded-lg border border-line bg-surface p-3 text-center">
    @if (trim((string) $value) !== '')
      <div class="text-base font-semibold text-heading break-words">{{ $value }}</div>
    @else
      <div class="text-base font-normal italic text-muted">{{ __('pages.viz.station_empty') }}</div>
    @endif
    <div class="text-xs text-muted mt-0.5">{{ $label }}</div>
  </div>
  @endforeach
</div>

{{-- ── Mapa s přepínatelnými vrstvami (vč. přehrávání deníku) ──────────── --}}
<div class="rounded-lg border border-line bg-surface p-3 mb-5">
  <div class="flex items-center gap-2 mb-2 flex-wrap">
    <label class="text-sm font-semibold text-heading" for="viz-layer-select">{{ __('pages.viz.map') }}</label>
    <select id="viz-layer-select" class="map-select">
      <option value="playback" data-map-layer="playback">{{ __('pages.viz.layer_playback') }}</option>
      <option value="crk" data-map-layer="crk">{{ __('pages.viz.layer_crk') }}</option>
      <option value="jezek" data-map-layer="jezek">{{ __('pages.viz.layer_jezek') }}</option>
      <option value="spendliky" data-map-layer="spendliky">{{ __('pages.viz.layer_spendliky') }}</option>
      <option value="lokatory" data-map-layer="lokatory">{{ __('pages.viz.layer_lokatory') }}</option>
      <option value="ctverce" data-map-layer="ctverce">{{ __('pages.viz.layer_ctverce') }}</option>
    </select>
    {{-- Filtr druhu provozu – platí pro vrstvy s QSO (skrývá ho JS na vrstvě Lokátory).
         Tlačítka se generují jen pro druhy provozu, které se v deníku vyskytují. --}}
    <span id="viz-mode-filter" class="inline-flex items-center gap-2 flex-wrap sm:ml-auto">
      <span class="text-xs text-muted">{{ __('pages.viz.mode_filter') }}</span>
      @foreach ($modeStats as $m)
      <button type="button" class="map-tab active inline-flex items-center gap-1.5" data-mode-filter="{{ $m['mode'] }}">
        <span class="mode-dot" data-mode-dot="{{ $m['mode'] }}"></span>
        {{ $m['mode'] === 0 ? __('pages.viz.mode_other_short') : $m['label'] }}
      </button>
      @endforeach
    </span>
  </div>
  {{-- Ovládání přehrávání – viditelné jen v režimu „Přehrávání" (řídí JS). --}}
  <div id="viz-playback-controls" class="hidden items-center gap-3 mb-2 flex-wrap">
    <button type="button" id="viz-play" class="map-tab">{{ __('pages.viz.play') }}</button>
    <input type="range" id="viz-cas" class="flex-1 min-w-40"
           min="{{ $window['from'] }}" max="{{ $window['to'] }}" value="{{ $window['to'] }}" step="1">
    <span class="text-sm font-mono font-semibold text-heading" id="viz-cas-label"></span>
    <span class="text-xs text-muted"><span id="viz-qso-count">0</span> {{ __('pages.viz.qso') }}</span>
    <span class="text-xs font-semibold text-heading" id="viz-skore"
          title="{{ __('pages.viz.score_title') }}"></span>
  </div>
  <div id="viz-mapa"></div>
</div>

{{-- ── Průběh skóre ────────────────────────────────────────────────────── --}}
<div class="relative rounded-lg border border-line bg-surface p-3 mb-4">
  <button type="button" class="chart-png" data-chart-png="chartPrubeh" data-nazev="prubeh-skore" title="{{ __('pages.viz.chart_png_title') }}">⤓</button>
  <div class="h-60 sm:h-72"><canvas id="chartPrubeh"></canvas></div>
  <p class="text-xs text-muted mt-2">{{ __('pages.viz.prubeh_caption', ['sq' => $homeSq]) }}</p>
</div>

{{-- ── Grafy: timeline s násobiči + vážená azimutová růžice ───────────── --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-4">
  <div class="relative rounded-lg border border-line bg-surface p-3">
    <button type="button" class="chart-png" data-chart-png="chartTimeline" data-nazev="timeline-multiplier" title="{{ __('pages.viz.chart_png_title') }}">⤓</button>
    <div class="h-72 sm:h-72"><canvas id="chartTimeline"></canvas></div>
  </div>
  <div class="relative rounded-lg border border-line bg-surface p-3">
    <button type="button" class="chart-png" data-chart-png="chartAzimuth" data-nazev="smery-qso" title="{{ __('pages.viz.chart_png_title') }}">⤓</button>
    <div class="flex items-center gap-2 mb-1 flex-wrap">
      <span class="text-xs text-muted">{{ __('pages.viz.az_weight_by') }}</span>
      <button type="button" class="map-tab active" data-az-metric="pocet">{{ __('pages.viz.az_count') }}</button>
      <button type="button" class="map-tab" data-az-metric="km">{{ __('pages.viz.az_km') }}</button>
      <button type="button" class="map-tab" data-az-metric="body">{{ __('pages.viz.az_points') }}</button>
    </div>
    <canvas id="chartAzimuth"></canvas>
  </div>
</div>

{{-- ── Grafy: body podle čtverců + celoroční trend ─────────────────────── --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-4">
  <div class="relative rounded-lg border border-line bg-surface p-3">
    <button type="button" class="chart-png" data-chart-png="chartCtverce" data-nazev="body-ctverce" title="{{ __('pages.viz.chart_png_title') }}">⤓</button>
    <div class="h-72 sm:h-72"><canvas id="chartCtverce"></canvas></div>
  </div>
  <div class="relative rounded-lg border border-line bg-surface p-3">
    @if ($sezona !== null)
      <button type="button" class="chart-png" data-chart-png="chartSezona" data-nazev="sezona" title="{{ __('pages.viz.chart_png_title') }}">⤓</button>
      <div class="h-72 sm:h-72"><canvas id="chartSezona"></canvas></div>
      <p class="text-xs text-muted mt-2">{{ __('pages.viz.sezona_caption', ['call' => $pcall]) }}</p>
    @else
      <p class="text-sm text-muted">{{ __('pages.viz.sezona_unavailable') }}</p>
    @endif
  </div>
</div>

{{-- ── Grafy: QSO podle zemí (DXCC) + podle prefixů ────────────────────── --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-4">
  <div class="relative rounded-lg border border-line bg-surface p-3">
    <button type="button" class="chart-png" data-chart-png="chartZeme" data-nazev="qso-zeme" title="{{ __('pages.viz.chart_png_title') }}">⤓</button>
    <div class="h-72 sm:h-72"><canvas id="chartZeme"></canvas></div>
  </div>
  <div class="relative rounded-lg border border-line bg-surface p-3">
    <button type="button" class="chart-png" data-chart-png="chartPrefix" data-nazev="qso-prefixy" title="{{ __('pages.viz.chart_png_title') }}">⤓</button>
    <div class="h-72 sm:h-72"><canvas id="chartPrefix"></canvas></div>
  </div>
</div>

{{-- ── Graf: histogram vzdáleností (skládaný podle druhu provozu) ───────── --}}
<div class="relative rounded-lg border border-line bg-surface p-3 mb-5">
  <button type="button" class="chart-png" data-chart-png="chartDist" data-nazev="histogram-vzdalenosti" title="{{ __('pages.viz.chart_png_title') }}">⤓</button>
  <div class="h-72 sm:h-72"><canvas id="chartDist"></canvas></div>
</div>

{{-- ── TOP ODX ─────────────────────────────────────────────────────────── --}}
<div class="section-head">{{ __('pages.viz.odx_heading') }}</div>
@if ($odx === [])
  <p class="text-muted mb-4">{{ __('pages.viz.odx_empty') }}</p>
@else
<p class="text-xs text-muted mb-2">{{ __('pages.viz.odx_hint') }}</p>
<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr>
        <th class="num">#</th>
        <th>{{ __('pages.viz.col_callsign') }}</th>
        <th>{{ __('pages.viz.col_locator') }}</th>
        <th class="num">km</th>
        <th class="num">{{ __('pages.viz.col_azimuth') }}</th>
        <th>{{ __('pages.viz.col_time') }}</th>
        <th>{{ __('pages.viz.col_mode') }}</th>
        <th class="num">{{ __('pages.viz.col_points') }}</th>
      </tr>
    </thead>
    <tbody>
    @foreach ($odx as $i => $o)
      <tr class="cursor-pointer" data-odx-idx="{{ $o['idx'] }}" title="{{ __('pages.viz.odx_show_on_map') }}">
        <td class="num font-bold">{{ $i + 1 }}.</td>
        <td class="mono font-bold">{{ $o['call'] }}</td>
        <td class="mono">{{ $o['wwl'] }}</td>
        <td class="num font-bold">{{ $o['dist'] }}</td>
        <td class="num">{{ $o['azimut'] !== null ? $o['azimut'] . '°' : '—' }}</td>
        <td class="mono">{{ $o['cas'] }}</td>
        <td>{{ $o['mode'] }}</td>
        <td class="num">{{ $o['points'] }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@endif

@endsection
