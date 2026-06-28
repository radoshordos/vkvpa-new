{{--
    INKUBÁTOR statistik – hřiště s prototypy nových grafů a analýz nad jedním
    deníkem. Texty jsou zatím natvrdo česky (prototyp); při slučování do
    produkční Vizualizace se převedou na __() jako zbytek aplikace.

    Prototypy:
      • Heatmapa směr × čas (kdy se kam pásmo otevřelo)
      • Vs. pole kategorie (moje skóre vs. medián + kvartilové pásmo)
      • Rate sheet (moje QSO/15 min vs. medián pole)
      • Promarněné příležitosti (čtverce a stanice, které pracovala většina pole)
      • Export QSO do CSV

    Animovaný „závod skóre" se přesunul na stránku Porovnání deníků.
--}}
@extends('layouts.app')

@section('title', 'Statistiky-inkubátor · ' . $pcall)
@section('meta_description', 'Prototypy nových statistik deníku ' . $pcall)

@push('head')
  @vite('resources/js/statistiky-inkubator.js')
  <style>
    .map-tab { padding: .25rem .75rem; border-radius: .375rem; font-size: .8rem; font-weight: 600; cursor: pointer;
               border: 1px solid var(--color-line, #e2e8f0); background: var(--color-surface, #fff);
               color: var(--color-muted, #64748b); transition: background .15s, color .15s; }
    .map-tab.active, .map-tab:hover { background: var(--color-brand, #3b82f6); color: #fff; border-color: transparent; }
    /* Heatmapa směr × čas */
    .hm-grid { display: grid; gap: 1px; font-size: 10px; }
    .hm-cell { aspect-ratio: 1; border-radius: 2px; display: flex; align-items: center; justify-content: center;
               color: #fff; font-weight: 600; min-width: 0; }
    .hm-axis { color: var(--color-muted, #64748b); font-weight: 600; display: flex; align-items: center; }
    .hm-axis.col { justify-content: center; writing-mode: vertical-rl; transform: rotate(180deg); height: 2.2rem; }
    .ink-png { position: absolute; top: .4rem; right: .5rem; z-index: 1; padding: .15rem .35rem; border: none;
               border-radius: .25rem; background: transparent; color: var(--color-muted, #64748b);
               font-size: .9rem; line-height: 1; cursor: pointer; }
    .ink-png:hover { color: #fff; background: var(--color-brand, #3b82f6); }
  </style>
@endpush

@section('content')

<script @cspNonce>
window.__inkubatorConfig = {
    pcall: @json($pcall),
    window: @json($window),
    heatmap: @json($heatmap),
    pole: @json($pole),
    qso: @json($qso),
};
</script>

{{-- ── Hlavička ────────────────────────────────────────────────────────── --}}
<div class="flex items-center gap-2 mb-1 flex-wrap">
  <h1 class="text-xl font-bold text-heading">Statistiky-inkubátor — {{ $pcall }}</h1>
  <span class="text-xs font-semibold uppercase tracking-wide rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 px-2 py-0.5">prototyp</span>
</div>
<p class="text-sm text-muted mb-4">
  {{ $homeLoc }} · Hřiště s návrhy nových funkcí — vyber, co se ti líbí, a sloučí se do statistik. ·
  <a href="{{ route('edi.vizualizace', ['head' => $head]) }}" class="underline hover:text-heading">zpět na vizualizaci</a>
  · <a href="{{ route('edi.porovnani', ['head' => $head]) }}" class="underline hover:text-heading">souboj soupeřů (závod skóre)</a>
  @php $znacka = preg_replace('/[^A-Za-z0-9]/', '', $pcall); @endphp
  @if ($znacka !== '')
    · <a href="{{ route('statistiky.stanice', ['znacka' => $znacka]) }}" class="underline hover:text-heading">profil stanice</a>
  @endif
</p>

@if ($roundDataPending)
  <p class="text-sm text-muted mb-4 -mt-2">
    Porovnání s polem kategorie (vs. pole, závod, promarněné příležitosti) se zobrazí až po uzávěrce kola — deníky soupeřů se během příjmu hlášení neodhalují.
  </p>
@endif

{{-- ── 1) Heatmapa směr × čas ─────────────────────────────────────────── --}}
<div class="section-head">1 · Heatmapa směr × čas <span class="text-xs font-normal text-muted">— kdy se kam pásmo otevřelo</span></div>
<div class="rounded-lg border border-line bg-surface p-3 mb-3">
  <div class="flex items-center gap-2 mb-2 flex-wrap">
    <span class="text-xs text-muted">Barva podle:</span>
    <button type="button" class="map-tab active" data-hm-metric="pocet">počtu QSO</button>
    <button type="button" class="map-tab" data-hm-metric="km">km</button>
  </div>
  <div id="hm-wrap" class="overflow-x-auto"></div>
  <p class="text-xs text-muted mt-2">Řádky = 16 azimutových sektorů (S nahoře), sloupce = 15min intervaly okna. Tmavší = víc QSO / km. Shluk v jednom směru a čase = otevření pásma.</p>
</div>

@if ($pole === null)
  <p class="text-sm text-muted mb-6">Další prototypy (vs. pole kategorie, závod skóre, rate sheet, promarněné příležitosti) potřebují deníky soupeřů z téhož kola a kategorie — budou dostupné po uzávěrce kola, jakmile bude s kým porovnávat.</p>
@else
  <p class="text-xs text-muted mb-4">Pole kategorie: {{ $pole['stanic'] }} deníků (já + soupeři z téhož kola a kategorie).</p>

  {{-- ── 2) Vs. pole kategorie ────────────────────────────────────────── --}}
  <div class="section-head">2 · Vs. pole kategorie <span class="text-xs font-normal text-muted">— moje skóre proti mediánu a kvartilovému pásmu</span></div>
  <div class="relative rounded-lg border border-line bg-surface p-3 mb-5">
    <button type="button" class="ink-png" data-ink-png="chartPole" data-nazev="vs-pole" title="Stáhnout jako PNG">⤓</button>
    <div class="h-72"><canvas id="chartPole"></canvas></div>
    <p class="text-xs text-muted mt-2">Modře moje průběžné skóre, šedá čára = medián pole, šedé pásmo = 25.–75. percentil. Nad pásmem = patřím ke špičce kategorie.</p>
  </div>

  {{-- ── 3) Rate sheet ────────────────────────────────────────────────── --}}
  <div class="section-head">3 · Rate sheet <span class="text-xs font-normal text-muted">— moje tempo proti mediánu pole</span></div>
  <div class="relative rounded-lg border border-line bg-surface p-3 mb-5">
    <button type="button" class="ink-png" data-ink-png="chartRate" data-nazev="rate-sheet" title="Stáhnout jako PNG">⤓</button>
    <div class="h-72"><canvas id="chartRate"></canvas></div>
    <p class="text-xs text-muted mt-2">QSO v 15min intervalech: tvoje (modře) vs. medián kategorie (šedě). Kde jsi pod medián, tam ti tempo stojí.</p>
  </div>

  {{-- ── 4) Promarněné příležitosti ───────────────────────────────────── --}}
  <div class="section-head">4 · Promarněné příležitosti <span class="text-xs font-normal text-muted">— co pracovalo pole, ale ty ne</span></div>
  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-6">
    <div class="rounded-lg border border-line bg-surface p-3">
      <div class="text-sm font-semibold text-heading mb-2">Čtverce, které ti chybí</div>
      @if ($pole['missedSquares'] === [])
        <p class="text-sm text-muted">Nic — pracoval jsi všechny čtverce, co byly v poli běžné. 👏</p>
      @else
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Čtverec</th><th class="num">Pracovalo soupeřů</th><th class="num">Body/QSO</th></tr></thead>
          <tbody>
          @foreach ($pole['missedSquares'] as $s)
            <tr><td class="mono font-bold">{{ $s['square'] }}</td><td class="num">{{ $s['kolik'] }}×</td><td class="num">{{ $s['body'] }} <span class="text-xs text-muted">+ nový násobič</span></td></tr>
          @endforeach
          </tbody>
        </table>
      </div>
      @endif
    </div>
    <div class="rounded-lg border border-line bg-surface p-3">
      <div class="text-sm font-semibold text-heading mb-2">Stanice, které ti chybí</div>
      @if ($pole['missedStations'] === [])
        <p class="text-sm text-muted">Nic — slyšel jsi v podstatě všechno, co bylo k práci. 👏</p>
      @else
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Značka</th><th>Lokátor</th><th class="num">km</th><th class="num">Pracovalo soupeřů</th></tr></thead>
          <tbody>
          @foreach ($pole['missedStations'] as $s)
            <tr><td class="mono font-bold">{{ $s['call'] }}</td><td class="mono">{{ $s['wwl'] }}</td><td class="num">{{ $s['dist'] ?? '—' }}</td><td class="num">{{ $s['kolik'] }}×</td></tr>
          @endforeach
          </tbody>
        </table>
      </div>
      @endif
    </div>
  </div>
@endif

{{-- ── 5) Export ────────────────────────────────────────────────────────── --}}
<div class="section-head">5 · Export deníku</div>
<div class="rounded-lg border border-line bg-surface p-3 mb-4">
  <button type="button" id="export-csv" class="map-tab">⤓ Stáhnout QSO jako CSV</button>
  <span class="text-xs text-muted ml-2">{{ count($qso) }} spojení · sloupce: čas, značka, lokátor, km, azimut, body, druh provozu. (ADIF/EDI export jako další krok.)</span>
</div>

@endsection
