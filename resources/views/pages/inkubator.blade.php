{{--
    Vizuální inkubátor – experimentální vizualizace EDI deníku:
    přehrávání deníku na mapě, průběh skóre, nové násobiče, TOP ODX,
    vážená azimutová růžice, body podle čtverců, tempo závodu,
    nezapočítaná QSO a celoroční trend stanice. Porovnání se soupeřem
    je na samostatné stránce Porovnání deníků (route edi.porovnani).
--}}
@extends('layouts.app')

@section('title', 'Vizuální inkubátor – ' . $pcall . ' – VKV PA')

@push('head')
  <link rel="preconnect" href="https://tile.openstreetmap.org">
  @vite('resources/js/inkubator.js')
  <style>
    #ink-mapa { height: 52vh; width: 100%; border-radius: .5rem; isolation: isolate; }
    .map-tab { padding: .25rem .75rem; border-radius: .375rem; font-size: .8rem; font-weight: 600; cursor: pointer;
               border: 1px solid var(--color-line, #e2e8f0); background: var(--color-surface, #fff);
               color: var(--color-muted, #64748b); transition: background .15s, color .15s; }
    .map-tab.active, .map-tab:hover { background: var(--color-brand, #3b82f6); color: #fff; border-color: transparent; }
    #ink-cas { accent-color: var(--color-brand, #3b82f6); }
  </style>
@endpush

@section('content')

{{-- Inline JS config pro inkubator.js --}}
<script @cspNonce>
window.__inkubatorConfig = {
    pcall: @json($pcall),
    homeLoc: @json($homeLoc),
    home: @json($home),
    window: @json($window),
    points: @json($mapPoints),
    cumulative: @json($cumulative),
    timeline: @json($timeline),
    azimuth: @json($azimuth),
    squarePoints: @json($squarePoints),
    sezona: @json($sezona),
};
</script>

{{-- ── Hlavička ────────────────────────────────────────────────────────── --}}
<h1 class="text-xl font-bold text-heading">🧪 Vizuální inkubátor – {{ $pcall }}</h1>
<p class="text-sm text-muted mb-4">
  {{ $homeLoc }} · experimentální vizualizace deníku ·
  <a href="{{ route('edi.vizualizace', ['head' => $head]) }}" class="underline hover:text-heading">zpět na vizualizaci</a> ·
  <a href="{{ route('edi.porovnani', ['head' => $head]) }}" class="underline hover:text-heading">⚔️ Porovnání deníků</a>
</p>

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
    <div class="text-2xl font-bold text-heading">{{ $nezapocitana['celkem'] }}</div>
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

{{-- ── Mapa s přehráváním deníku ───────────────────────────────────────── --}}
<div class="rounded-lg border border-line bg-surface p-3 mb-5">
  <div class="flex items-center gap-3 mb-2 flex-wrap">
    <span class="text-sm font-semibold text-heading">Přehrávání deníku</span>
    <button type="button" id="ink-play" class="map-tab">▶ Přehrát</button>
    <input type="range" id="ink-cas" class="flex-1 min-w-40"
           min="{{ $window['from'] }}" max="{{ $window['to'] }}" value="{{ $window['to'] }}" step="1">
    <span class="text-sm font-mono font-semibold text-heading" id="ink-cas-label"></span>
    <span class="text-xs text-muted"><span id="ink-qso-count">0</span> QSO</span>
  </div>
  <div id="ink-mapa"></div>
</div>

{{-- ── Průběh skóre (porovnání se soupeřem je na stránce Porovnání deníků) ── --}}
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
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-5">
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

{{-- ── TOP ODX ─────────────────────────────────────────────────────────── --}}
<div class="section-head">TOP ODX – nejvzdálenější spojení</div>
@if ($odx === [])
  <p class="text-muted mb-4">Žádná spojení se spočítanou vzdáleností.</p>
@else
<div class="table-wrap mb-5">
  <table class="data-table">
    <thead>
      <tr>
        <th class="num">#</th>
        <th>Značka</th>
        <th>Lokátor</th>
        <th class="num">km</th>
        <th class="num">Azimut</th>
        <th>Čas</th>
        <th>Mód</th>
        <th class="num">Body</th>
      </tr>
    </thead>
    <tbody>
    @foreach ($odx as $i => $o)
      <tr>
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

{{-- ── Nové násobiče ───────────────────────────────────────────────────── --}}
<div class="section-head">Nové násobiče (velké čtverce)</div>
@if ($nasobice === [])
  <p class="text-muted mb-4">Žádný nový násobič nad rámec vlastního čtverce {{ $homeSq }}.</p>
@else
<div class="table-wrap mb-2">
  <table class="data-table">
    <thead>
      <tr>
        <th class="num">Násobič č.</th>
        <th>Čtverec</th>
        <th>Čas</th>
        <th>První QSO</th>
      </tr>
    </thead>
    <tbody>
    @foreach ($nasobice as $n)
      <tr>
        <td class="num font-bold">{{ $n['poradi'] }}</td>
        <td class="mono font-bold">{{ $n['square'] }}</td>
        <td class="mono">{{ $n['cas'] }}</td>
        <td class="mono">{{ $n['call'] }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
<p class="text-xs text-muted mb-5">Vlastní čtverec {{ $homeSq }} je násobič č. 1 automaticky (počítá se vždy, i bez QSO).</p>
@endif

{{-- ── Nezapočítaná QSO ────────────────────────────────────────────────── --}}
@if ($nezapocitana['celkem'] > 0)
<div class="section-head">Nezapočítaná a označená QSO</div>
<div class="table-wrap mb-2">
  <table class="data-table">
    <thead>
      <tr>
        <th>Značka</th>
        <th>Čas</th>
        <th>Důvod</th>
      </tr>
    </thead>
    <tbody>
    @foreach ($nezapocitana['radky'] as $r)
      <tr>
        <td class="mono font-bold">{{ $r['call'] }}</td>
        <td class="mono">{{ $r['cas'] }}</td>
        <td class="text-muted">{{ $r['duvod'] }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
<p class="text-xs text-muted mb-5">
  QSO mimo závodní okno či mimo den závodu se do skóre nepočítají. Duplicity označené v deníku se ve skóre počítají, uvádíme je jen pro kontrolu.
  @if ($nezapocitana['celkem'] > count($nezapocitana['radky']))
    Zobrazeno prvních {{ count($nezapocitana['radky']) }} z {{ $nezapocitana['celkem'] }} řádků.
  @endif
</p>
@endif

@endsection
