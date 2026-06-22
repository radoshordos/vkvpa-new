{{-- Detail statistik jednoho vyhodnoceného kola: souhrn, odznaky, zajímavosti,
     mapa (stanice / čtverce / účastníci), grafy a TOP žebříčky.
     Leaflet vrstvy + Chart.js řídí statistiky.js. --}}
@extends('layouts.app')

@section('title', __('pages.stat.kolo_title', ['kolo' => $kolo->nazev]))
@section('meta_description', __('pages.stat.kolo_meta', ['kolo' => $kolo->nazev]))
@section('og_image', route('statistiky.kolo.og', ['kolo' => $kolo->id]))

@push('head')
  <link rel="preconnect" href="https://tile.openstreetmap.org">
  @vite('resources/js/statistiky.js')
  <style>
    #stat-mapa { height: 52vh; width: 100%; border-radius: .5rem; isolation: isolate; }
    .map-tab { padding: .25rem .75rem; border-radius: .375rem; font-size: .8rem; font-weight: 600; cursor: pointer;
               border: 1px solid var(--color-line, #e2e8f0); background: var(--color-surface, #fff);
               color: var(--color-muted, #64748b); transition: background .15s, color .15s; }
    .map-tab.active, .map-tab:hover { background: var(--color-brand, #3b82f6); color: #fff; border-color: transparent; }
  </style>
@endpush

@section('content')

{{-- Inline JS config pro statistiky.js --}}
<script @cspNonce>
window.__statConfig = {
    stanice: @json($prehled['stanice']),
    ctverce: @json($prehled['ctverce']),
    ucastnici: @json($prehled['ucastnici']),
    tok: @json($prehled['tok']),
    timeline: @json($prehled['timeline']),
    mody: @json($prehled['mody']),
    zeme: @json($prehled['zeme']),
    prefixy: @json($prehled['prefixy']),
    kategorie: @json($prehled['kategorie']),
    trend: @json($prehled['trend']),
    t: {
        ssb: @json(__('pages.stat.mode_ssb')),
        cw: @json(__('pages.stat.mode_cw')),
        other: @json(__('pages.stat.country_other')),
        qsoCount: 'QSO',
        stations: @json(__('pages.stat.js_stations')),
        points: @json(__('pages.stat.unit_points')),
    },
};
</script>

<header class="mb-4">
  <div class="text-xs font-semibold uppercase tracking-wide text-muted">
    <a href="{{ route('statistiky.index') }}" class="underline hover:text-heading">{{ __('pages.stat.index_heading') }}</a>
  </div>
  <div class="flex flex-wrap items-center gap-2">
    <h1 class="text-xl font-bold text-heading sm:text-2xl">{{ __('pages.stat.kolo_heading', ['kolo' => $kolo->nazev]) }}</h1>
    @foreach (['ucast' => 'badge_ucast', 'skore' => 'badge_skore', 'qso' => 'badge_qso', 'nasobice' => 'badge_nasobice'] as $flag => $key)
      @if ($prehled['odznaky'][$flag])
        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">🏅 {{ __('pages.stat.'.$key) }}</span>
      @endif
    @endforeach
  </div>
</header>

{{-- ── Souhrnné karty ──────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6 mb-4">
  @foreach ([
    [__('pages.stat.stat_stations'), $prehled['pocetStanic'], ''],
    [__('pages.stat.stat_entries'),  $prehled['pocetZaznamu'], ''],
    [__('pages.stat.stat_qso'),      $prehled['pocetQso'], ''],
    [__('pages.stat.stat_points'),   $prehled['bodyCelkem'], ''],
    [__('pages.stat.stat_squares'),  $prehled['pocetCtvercu'], ''],
    [__('pages.stat.stat_odx'),      $prehled['odx']['dist'] ?? 0, 'km'],
  ] as [$label, $value, $unit])
  <div class="rounded-lg border border-line bg-surface p-3 text-center">
    <div class="text-2xl font-bold text-heading">{{ $value }}<span class="text-sm font-normal text-muted ml-1">{{ $unit }}</span></div>
    <div class="text-xs text-muted mt-0.5">{{ $label }}</div>
  </div>
  @endforeach
</div>

{{-- ── Zajímavosti ─────────────────────────────────────────────────────── --}}
@if ($prehled['zajimavosti'] !== [])
<div class="flex flex-wrap gap-2 mb-5">
  @foreach ($prehled['zajimavosti'] as $f)
    <span class="rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm text-muted">{{ __('pages.stat.'.$f['key'], $f['params']) }}</span>
  @endforeach
</div>
@endif

{{-- ── Mapa s přepínatelnými vrstvami ──────────────────────────────────── --}}
<div class="rounded-lg border border-line bg-surface p-3 mb-5">
  <div class="flex items-center gap-2 mb-2 flex-wrap">
    <span class="text-sm font-semibold text-heading">{{ __('pages.stat.map') }}</span>
    <button class="map-tab active" data-stat-layer="stanice">{{ __('pages.stat.layer_stations') }}</button>
    <button class="map-tab" data-stat-layer="ctverce">{{ __('pages.stat.layer_squares') }}</button>
    <button class="map-tab" data-stat-layer="ucastnici">{{ __('pages.stat.layer_participants') }}</button>
    <button class="map-tab" data-stat-layer="tok">{{ __('pages.stat.layer_tok') }}</button>
  </div>
  <div id="stat-mapa"></div>
</div>

{{-- ── ODX kola ────────────────────────────────────────────────────────── --}}
@if ($prehled['odx'])
<div class="rounded-lg border border-line bg-surface p-3 mb-5">
  <div class="text-sm font-semibold text-heading mb-1">{{ __('pages.stat.odx_heading') }}</div>
  <p class="text-sm text-muted">
    {{ __('pages.stat.odx_line', [
        'home'    => $prehled['odx']['homeCall'],
        'homeloc' => $prehled['odx']['home'],
        'call'    => $prehled['odx']['call'],
        'wwl'     => $prehled['odx']['wwl'],
        'dist'    => $prehled['odx']['dist'],
    ]) }}
  </p>
</div>
@endif

{{-- ── Časová osa aktivity ─────────────────────────────────────────────── --}}
<div class="rounded-lg border border-line bg-surface p-3 mb-4">
  <div class="text-sm font-semibold text-heading mb-2">{{ __('pages.stat.chart_timeline') }}</div>
  <div class="h-56"><canvas id="chartTimeline"></canvas></div>
</div>

{{-- ── Druhy provozu + kategorie ───────────────────────────────────────── --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-4">
  <div class="rounded-lg border border-line bg-surface p-3">
    <div class="text-sm font-semibold text-heading mb-2">{{ __('pages.stat.chart_mody') }}</div>
    <div class="h-64"><canvas id="chartMody"></canvas></div>
  </div>
  <div class="rounded-lg border border-line bg-surface p-3">
    <div class="text-sm font-semibold text-heading mb-2">{{ __('pages.stat.chart_kategorie') }}</div>
    <div class="h-64"><canvas id="chartKategorie"></canvas></div>
  </div>
</div>

{{-- ── Země + prefixy ──────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-4">
  <div class="rounded-lg border border-line bg-surface p-3">
    <div class="text-sm font-semibold text-heading mb-2">{{ __('pages.stat.chart_zeme') }}</div>
    <div class="h-72"><canvas id="chartZeme"></canvas></div>
  </div>
  <div class="rounded-lg border border-line bg-surface p-3">
    <div class="text-sm font-semibold text-heading mb-2">{{ __('pages.stat.chart_prefix') }}</div>
    <div class="h-72"><canvas id="chartPrefix"></canvas></div>
  </div>
</div>

{{-- ── Trend posledních kol ────────────────────────────────────────────── --}}
<div class="rounded-lg border border-line bg-surface p-3 mb-5">
  <div class="text-sm font-semibold text-heading mb-2">{{ __('pages.stat.chart_trend') }}</div>
  <div class="h-64"><canvas id="chartTrend"></canvas></div>
</div>

{{-- ── TOP žebříčky kola ───────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 gap-4 lg:grid-cols-3 mb-5">
  @foreach ([
    ['title' => __('pages.stat.top_points'), 'rows' => $prehled['topBody'],     'col' => 'body',     'unit' => __('pages.stat.unit_points')],
    ['title' => __('pages.stat.top_qso'),    'rows' => $prehled['topQso'],      'col' => 'pocet',    'unit' => 'QSO'],
    ['title' => __('pages.stat.top_mult'),   'rows' => $prehled['topNasobice'], 'col' => 'nasobice', 'unit' => __('pages.stat.unit_mult')],
  ] as $tbl)
  <div class="rounded-lg border border-line bg-surface p-3">
    <div class="text-sm font-semibold text-heading mb-2">{{ $tbl['title'] }}</div>
    @if ($tbl['rows'] === [])
      <p class="text-sm text-muted">{{ __('pages.stat.empty') }}</p>
    @else
    <div class="table-wrap">
      <table class="data-table">
        <tbody>
        @foreach ($tbl['rows'] as $i => $row)
          <tr>
            <td class="num font-bold">{{ $i + 1 }}.</td>
            <td class="mono font-bold">
              @if (preg_match('/^[A-Za-z0-9]+$/', $row['znacka']))
                <a class="hover:underline" href="{{ route('statistiky.stanice', ['znacka' => $row['znacka']]) }}">{{ $row['znacka'] }}</a>
              @else
                {{ $row['znacka'] }}
              @endif
            </td>
            <td class="text-xs text-muted">{{ $row['kategorie'] }}</td>
            <td class="num font-bold">{{ $row[$tbl['col']] }} <span class="font-normal text-muted text-xs">{{ $tbl['unit'] }}</span></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>
  @endforeach
</div>

@endsection
