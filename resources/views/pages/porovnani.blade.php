{{--
    Porovnání dvou deníků (hráč vs. hráč) z téhož kola a téže kategorie:
    mapa rozdílů v protistanicích (jen já / jen soupeř / oba) + překryvný
    graf průběhu skóre. Přesunuto ze stránek Vizualizace a Vizuální inkubátor.
--}}
@extends('layouts.app')

@section('title', 'Porovnání deníků – ' . $pcall . ' – VKV PA')

@push('head')
  <link rel="preconnect" href="https://tile.openstreetmap.org">
  @vite('resources/js/porovnani.js')
  <style>
    #por-mapa { height: 52vh; width: 100%; border-radius: .5rem; isolation: isolate; }
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
    cumulative: @json($cumulative),
    rivalCumulative: @json($rivalCumulative),
};
</script>

{{-- ── Hlavička ────────────────────────────────────────────────────────── --}}
<h1 class="text-xl font-bold text-heading">⚔️ Porovnání deníků – {{ $pcall }}</h1>
<p class="text-sm text-muted mb-4">
  {{ $homeLoc }} · porovnání s deníkem soupeře z téhož kola a kategorie ·
  <a href="{{ route('edi.vizualizace', ['head' => $head]) }}" class="underline hover:text-heading">vizualizace</a> ·
  <a href="{{ route('edi.inkubator', ['head' => $head]) }}" class="underline hover:text-heading">🧪 vizuální inkubátor</a>
</p>

@if ($roundDataPending)
  <p class="text-sm text-muted mb-4 -mt-3">ℹ️ Porovnání deníků bude dostupné po uzávěrce, resp. vyhodnocení kola – do té doby se deníky soupeřů nezveřejňují.</p>
@elseif ($rivals->isEmpty())
  <p class="text-sm text-muted mb-4 -mt-3">V téže kategorii tohoto kola není žádný další deník, se kterým by šlo porovnávat.</p>
@else
  {{-- ── Výběr soupeře ──────────────────────────────────────────────────── --}}
  <form method="get" class="flex items-center gap-2 mb-4 flex-wrap">
    <label for="porovnat" class="text-sm text-muted">Porovnat {{ $pcall }} s:</label>
    <select name="porovnat" id="porovnat" data-autosubmit
            class="text-sm rounded border border-line bg-surface text-heading px-2 py-1">
      <option value="">— vyberte soupeře —</option>
      @foreach ($rivals as $r)
        <option value="{{ $r->id }}" @selected($compare !== null && $compare['rivalId'] === $r->id)>{{ $r->p_call }}</option>
      @endforeach
    </select>
  </form>

  @if ($compare === null)
    <p class="text-sm text-muted mb-4">Vyberte soupeře – zobrazí se mapa rozdílů v protistanicích a porovnání průběhu skóre.</p>
  @else
    {{-- ── Souhrnné karty obou stanic ──────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 mb-3">
      @foreach ([$souhrn['mine'], $souhrn['rival']] as $i => $s)
      <div class="rounded-lg border border-line bg-surface p-3">
        <div class="text-sm font-semibold {{ $i === 0 ? 'text-brand' : 'text-heading' }} mb-1">{{ $s['call'] }}</div>
        <div class="text-xs text-muted">
          {{ $s['qso'] }} QSO · {{ $s['nasobice'] }} násobičů · <span class="font-bold">{{ $s['body'] }} bodů</span>
        </div>
      </div>
      @endforeach
    </div>

    {{-- ── Rozdíly v protistanicích ────────────────────────────────────── --}}
    <div class="grid grid-cols-3 gap-3 mb-5">
      @foreach ([
        ['Jen ' . $pcall,             count($compare['onlyMine']),  'text-green-600 dark:text-green-400'],
        ['Jen ' . $compare['rival'],  count($compare['onlyRival']), 'text-red-600 dark:text-red-400'],
        ['Udělali oba',               count($compare['both']),     'text-muted'],
      ] as [$label, $value, $color])
      <div class="rounded-lg border border-line bg-surface p-3 text-center">
        <div class="text-2xl font-bold {{ $color }}">{{ $value }}</div>
        <div class="text-xs text-muted mt-0.5">{{ $label }}</div>
      </div>
      @endforeach
    </div>

    {{-- ── Mapa rozdílů ────────────────────────────────────────────────── --}}
    <div class="rounded-lg border border-line bg-surface p-3 mb-5">
      <div class="text-sm font-semibold text-heading mb-2">Mapa protistanic: {{ $pcall }} vs. {{ $compare['rival'] }}</div>
      <div id="por-mapa"></div>
      <p class="text-xs text-muted mt-2">Zelené body udělal jen {{ $pcall }}, červené jen {{ $compare['rival'] }}, šedé oba. Vzájemné spojení obou stanic se nepočítá.</p>
    </div>

    {{-- ── Průběh skóre obou deníků ────────────────────────────────────── --}}
    <div class="rounded-lg border border-line bg-surface p-3 mb-4">
      <canvas id="chartPrubeh"></canvas>
      <p class="text-xs text-muted mt-2">Orientační průběh: kumulativní body za spojení × průběžný počet násobičů (vlastní čtverec se počítá od začátku). Počítá se jen z QSO s platným lokátorem.</p>
    </div>
  @endif
@endif

@endsection
