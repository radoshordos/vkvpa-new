{{-- Rozcestník veřejných statistik: dlaždice vyhodnocených kol. --}}
@extends('layouts.app')

@section('title', __('pages.stat.index_title'))
@section('meta_description', __('pages.stat.index_meta'))

@section('content')

<header class="mb-5">
  <h1 class="text-xl font-bold text-heading sm:text-2xl">{{ __('pages.stat.index_heading') }}</h1>
  <p class="mt-1 text-sm text-muted">{{ __('pages.stat.index_subtitle') }}</p>
</header>

{{-- ── Síň slávy (all-time rekordy) ─────────────────────────────────────── --}}
@if ($rekordy['ucast'] || $rekordy['skore'] || $rekordy['qso'] || $rekordy['multiplier'] || $odxAllTime)
<div class="section-head">{{ __('pages.stat.hall_heading') }}</div>
<div class="grid gap-3 grid-cols-2 lg:grid-cols-4 mb-6">
  @if ($odxAllTime)
  <a href="{{ route('statistiky.kolo', ['kolo' => $odxAllTime['koloId']]) }}" class="card block p-4 hover:border-brand hover:bg-surface-2 transition-colors">
    <div class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('pages.stat.rec_odx') }}</div>
    <div class="mt-1 text-2xl font-bold text-heading">{{ $odxAllTime['dist'] }} <span class="text-sm font-normal text-muted">km</span></div>
    <div class="text-xs text-muted"><span class="mono font-semibold">{{ $odxAllTime['homeCall'] }}</span> → <span class="mono font-semibold">{{ $odxAllTime['call'] }}</span> · {{ __('pages.stat.rec_in_round', ['kolo' => $odxAllTime['kolo']]) }}</div>
  </a>
  @endif
  @if ($rekordy['ucast'])
  <a href="{{ route('statistiky.kolo', ['kolo' => $rekordy['ucast']['koloId']]) }}" class="card block p-4 hover:border-brand hover:bg-surface-2 transition-colors">
    <div class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('pages.stat.rec_ucast') }}</div>
    <div class="mt-1 text-2xl font-bold text-heading">{{ $rekordy['ucast']['value'] }} <span class="text-sm font-normal text-muted">{{ __('pages.stat.rec_stations_unit') }}</span></div>
    <div class="text-xs text-muted">{{ __('pages.stat.rec_in_round', ['kolo' => $rekordy['ucast']['kolo']]) }}</div>
  </a>
  @endif
  @foreach (['skore' => 'rec_skore', 'qso' => 'rec_qso', 'multiplier' => 'rec_multiplier'] as $k => $label)
    @if ($rekordy[$k])
    <a href="{{ route('statistiky.kolo', ['kolo' => $rekordy[$k]['koloId']]) }}" class="card block p-4 hover:border-brand hover:bg-surface-2 transition-colors">
      <div class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('pages.stat.'.$label) }}</div>
      <div class="mt-1 text-2xl font-bold text-heading">{{ $rekordy[$k]['value'] }}</div>
      <div class="text-xs text-muted"><span class="mono font-semibold">{{ $rekordy[$k]['znacka'] }}</span> · {{ __('pages.stat.rec_in_round', ['kolo' => $rekordy[$k]['kolo']]) }}</div>
    </a>
    @endif
  @endforeach
</div>
@endif

<div class="section-head">{{ __('pages.stat.archive_heading') }}</div>

@if ($kolaPodleRoku->isEmpty())
  <p class="text-muted">{{ __('pages.stat.empty') }}</p>
@else
@if ($kolaPodleRoku->count() > 1)
<nav class="mb-5 flex flex-wrap gap-2" aria-label="{{ __('pages.stat.year_nav') }}">
  @foreach ($kolaPodleRoku->keys() as $rok)
    <a href="#rok-{{ $rok }}" class="rounded-lg border border-line bg-surface px-3 py-1.5 text-sm font-semibold text-heading transition-colors hover:border-brand hover:bg-surface-2">{{ $rok }}</a>
  @endforeach
</nav>
@endif

<div class="space-y-6">
  @foreach ($kolaPodleRoku as $rok => $rocniKola)
  @php($souhrn = $rocniSouhrny[$rok] ?? ['pocetKol' => $rocniKola->count(), 'prumerUcast' => 0, 'maxUcast' => 0, 'minUcast' => 0, 'zaznamu' => 0])
  <section id="rok-{{ $rok }}" class="scroll-mt-6" aria-labelledby="stat-year-{{ $rok }}">
    <div class="mb-3 border-b border-line pb-3">
      <h2 id="stat-year-{{ $rok }}" class="text-lg font-bold text-heading">{{ $rok }}</h2>
      <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted">
        <span class="inline-flex items-center rounded-lg border border-line bg-surface px-2 py-1">{{ trans_choice('pages.stat.year_rounds', $souhrn['pocetKol'], ['count' => $souhrn['pocetKol']]) }}</span>
        <span class="inline-flex items-center rounded-lg border border-line bg-surface px-2 py-1">{{ trans_choice('pages.stat.year_entries', $souhrn['zaznamu'], ['count' => $souhrn['zaznamu']]) }}</span>
        <span class="inline-flex items-center rounded-lg border border-line bg-surface px-2 py-1">{{ __('pages.stat.year_avg_participants', ['count' => $souhrn['prumerUcast']]) }}</span>
      </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      @foreach ($rocniKola as $k)
      @php($nejvyssiUcast = (int) $k->ucastniku === $souhrn['maxUcast'])
      <a href="{{ route('statistiky.kolo', ['kolo' => $k->id]) }}"
         class="card flex gap-3 p-4 transition-colors hover:border-brand hover:bg-surface-2">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center text-2xl leading-none" aria-hidden="true">📅</span>
        <span class="min-w-0 flex-1">
          <span class="flex items-start justify-between gap-2">
            <span class="font-bold text-heading">{{ $k->name }}</span>
            @if ($nejvyssiUcast)
              <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[0.7rem] font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">{{ __('pages.stat.best_attendance_year') }}</span>
            @endif
          </span>
          <span class="mt-1 block text-xs text-muted">{{ $k->starts_at->locale(app()->getLocale())->isoFormat('D. M. YYYY') }}</span>
          <span class="mt-3 grid grid-cols-2 gap-2">
            <span>
              <span class="block text-lg font-bold leading-tight text-heading">{{ (int) $k->ucastniku }}</span>
              <span class="text-xs text-muted">{{ __('pages.stat.card_participants_label') }}</span>
            </span>
            <span>
              <span class="block text-lg font-bold leading-tight text-heading">{{ (int) $k->zaznamu }}</span>
              <span class="text-xs text-muted">{{ __('pages.stat.card_entries_label') }}</span>
            </span>
          </span>
        </span>
      </a>
      @endforeach
    </div>
  </section>
  @endforeach
</div>
@endif

@endsection
