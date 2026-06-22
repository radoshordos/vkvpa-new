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
@if ($rekordy['ucast'] || $rekordy['skore'] || $rekordy['qso'] || $rekordy['nasobice'] || $odxAllTime)
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
  @foreach (['skore' => 'rec_skore', 'qso' => 'rec_qso', 'nasobice' => 'rec_nasobice'] as $k => $label)
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

<div class="section-head">{{ __('pages.stat.index_heading') }}</div>

@if ($kola->isEmpty())
  <p class="text-muted">{{ __('pages.stat.empty') }}</p>
@else
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
  @foreach ($kola as $k)
  <a href="{{ route('statistiky.kolo', ['kolo' => $k->id]) }}"
     class="card block p-4 transition-colors hover:border-brand hover:bg-surface-2">
    <div class="flex items-baseline justify-between gap-2">
      <span class="font-bold text-heading">{{ $k->nazev }}</span>
      <span class="text-xs text-muted">{{ $k->datum_konani->locale(app()->getLocale())->isoFormat('D. M. YYYY') }}</span>
    </div>
    <div class="mt-2 text-sm text-muted">
      {{ trans_choice('pages.stat.card_participants', (int) $k->ucastniku, ['count' => (int) $k->ucastniku]) }}
    </div>
  </a>
  @endforeach
</div>
@endif

@endsection
