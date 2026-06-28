{{--
    Porovnání dvou deníků (hráč vs. hráč) z téhož kola a téže kategorie:
    mapa rozdílů v protistanicích (jen já / jen soupeř / oba), překryvný
    graf průběhu skóre, tempo obou stanic po 15 minutách a směrová růžice.
    Přesunuto ze stránek Vizualizace a Vizuální inkubátor.
--}}
@extends('layouts.app')

@section('title', __('pages.porovnani.title', ['call' => $pcall]))
@section('meta_description', __('pages.porovnani.meta', ['call' => $pcall]))

@push('head')
  <link rel="preconnect" href="https://tile.openstreetmap.org">
  @vite('resources/js/porovnani.js')
  <style>
    #por-mapa { height: 52vh; width: 100%; border-radius: .5rem; isolation: isolate; }
    #race-cas { accent-color: var(--color-brand, #3b82f6); }
  </style>
@endpush

@section('content')

{{-- Inline JS config pro porovnani.js --}}
<script @cspNonce>
window.__porovnaniConfig = {
    pcall: @json($pcall),
    homeLoc: @json($homeLoc),
    home: @json($home),
    window: @json($window),
    compare: @json($compare),
    zavod: @json($zavod),
    cumulative: @json($cumulative),
    rivalCumulative: @json($rivalCumulative),
    timeline: @json($timeline),
    azimuth: @json($azimuth),
    t: @json(__('pages.porovnani.js')),
};
</script>

{{-- ── Hlavička ────────────────────────────────────────────────────────── --}}
<h1 class="text-xl font-bold text-heading">{{ __('pages.porovnani.heading', ['call' => $pcall]) }}</h1>
<p class="text-sm text-muted mb-4">
  {{ $homeLoc }} · {{ __('pages.porovnani.subtitle') }} ·
  <a href="{{ route('edi.vizualizace', ['head' => $head]) }}" class="underline hover:text-heading">{{ __('pages.porovnani.viz_link') }}</a>
</p>

@if ($roundDataPending)
  <p class="text-sm text-muted mb-4 -mt-3">{{ __('pages.porovnani.round_pending') }}</p>
@elseif ($rivals->isEmpty())
  <p class="text-sm text-muted mb-4 -mt-3">{{ __('pages.porovnani.no_rivals') }}</p>
@else
  {{-- ── Výběr soupeře ──────────────────────────────────────────────────── --}}
  <form method="get" class="flex items-center gap-2 mb-4 flex-wrap">
    <label for="porovnat" class="text-sm text-muted">{{ __('pages.porovnani.compare_with', ['call' => $pcall]) }}</label>
    <select name="porovnat" id="porovnat" data-autosubmit
            class="text-sm rounded border border-line bg-surface text-heading px-2 py-1">
      <option value="">{{ __('pages.porovnani.pick_placeholder') }}</option>
      @foreach ($rivals as $r)
        <option value="{{ $r->id }}" @selected($compare !== null && $compare['rivalId'] === $r->id)>{{ $r->p_call }}</option>
      @endforeach
    </select>
  </form>

  {{-- ── Závod skóre (animace po minutách) – já + TOP 5 pole kategorie ──── --}}
  @if ($zavod !== null)
    <div class="rounded-lg border border-line bg-surface p-3 mb-5">
      <div class="text-sm font-semibold text-heading mb-2">Závod skóre — {{ $pcall }} vs. TOP pole kategorie</div>
      <div class="flex items-center gap-3 mb-2 flex-wrap">
        <button type="button" id="race-play" class="text-sm rounded border border-line bg-surface text-heading px-3 py-1 hover:bg-brand hover:text-white">▶ Přehrát</button>
        <input type="range" id="race-cas" class="flex-1 min-w-40" min="{{ $window['from'] }}" max="{{ $window['to'] }}" value="{{ $window['to'] }}" step="1">
        <span class="text-sm font-mono font-semibold text-heading" id="race-cas-label"></span>
      </div>
      <div class="h-72"><canvas id="chartZavod"></canvas></div>
      <p class="text-xs text-muted mt-2">Kumulativní skóre rostoucí po minutách — kdo vedl kdy a kde tě soupeř předjel. Posuvníkem zastavíš čas.</p>
    </div>
  @endif

  @if ($compare === null)
    <p class="text-sm text-muted mb-4">{{ __('pages.porovnani.pick_hint') }}</p>
  @else
    {{-- ── Souhrnné karty obou stanic ──────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 mb-3">
      @foreach ([$souhrn['mine'], $souhrn['rival']] as $i => $s)
      <div class="rounded-lg border border-line bg-surface p-3">
        <div class="text-sm font-semibold {{ $i === 0 ? 'text-brand' : 'text-heading' }} mb-1">{{ $s['call'] }}</div>
        <div class="text-xs text-muted">
          {{ $s['qso'] }} {{ __('pages.porovnani.sum_qso') }} · {{ $s['multiplier'] }} {{ __('pages.porovnani.sum_mult') }} · <span class="font-bold">{{ $s['body'] }} {{ __('pages.porovnani.sum_points') }}</span>
        </div>
      </div>
      @endforeach
    </div>

    {{-- ── Rozdíly v protistanicích ────────────────────────────────────── --}}
    <div class="grid grid-cols-3 gap-3 mb-5">
      @foreach ([
        [__('pages.porovnani.only', ['call' => $pcall]),            count($compare['onlyMine']),  'text-green-600 dark:text-green-400'],
        [__('pages.porovnani.only', ['call' => $compare['rival']]), count($compare['onlyRival']), 'text-red-600 dark:text-red-400'],
        [__('pages.porovnani.both'),                                count($compare['both']),      'text-muted'],
      ] as [$label, $value, $color])
      <div class="rounded-lg border border-line bg-surface p-3 text-center">
        <div class="text-2xl font-bold {{ $color }}">{{ $value }}</div>
        <div class="text-xs text-muted mt-0.5">{{ $label }}</div>
      </div>
      @endforeach
    </div>

    {{-- ── Mapa rozdílů ────────────────────────────────────────────────── --}}
    <div class="rounded-lg border border-line bg-surface p-3 mb-5">
      <div class="text-sm font-semibold text-heading mb-2">{{ __('pages.porovnani.map_heading', ['a' => $pcall, 'b' => $compare['rival']]) }}</div>
      <div id="por-mapa"></div>
      <p class="text-xs text-muted mt-2">{{ __('pages.porovnani.map_caption', ['a' => $pcall, 'b' => $compare['rival']]) }}</p>
    </div>

    {{-- ── Průběh skóre obou deníků ────────────────────────────────────── --}}
    <div class="rounded-lg border border-line bg-surface p-3 mb-4">
      <div class="h-60 sm:h-72"><canvas id="chartPrubeh"></canvas></div>
      <p class="text-xs text-muted mt-2">{{ __('pages.porovnani.prubeh_caption') }}</p>
    </div>

    {{-- ── Grafy: tempo obou stanic + směrová růžice ───────────────────── --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-4">
      <div class="rounded-lg border border-line bg-surface p-3">
        <div class="h-72 sm:h-72"><canvas id="chartTimeline"></canvas></div>
      </div>
      <div class="rounded-lg border border-line bg-surface p-3">
        <canvas id="chartAzimuth"></canvas>
      </div>
    </div>
  @endif
@endif

@endsection
