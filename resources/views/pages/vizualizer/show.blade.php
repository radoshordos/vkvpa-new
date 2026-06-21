{{--
    Samostatný EDI Visualizer – mapa spojení jednoho nahraného deníku.
    Leaflet: domácí QTH, paprsky ke všem protistanicím, barevné špendlíky
    podle druhu provozu, popup se vzdáleností/azimutem a odkazem na QRZ.
    Data + souhrn počítá VizualizerController z lokátorů (bez databáze).
--}}
@extends('layouts.app')

@section('title', __('pages.vizualizer.title'))
@section('meta_description', __('pages.vizualizer.meta'))

@push('head')
  <link rel="preconnect" href="https://tile.openstreetmap.org">
  @vite('resources/js/vizualizer.js')
  <style>
    #viz-map { height: 60vh; width: 100%; border-radius: .5rem; isolation: isolate; }
    /* Popisky velkých čtverců v rastru lokátorů. */
    .loc-label { background: transparent; border: none; box-shadow: none; color: #333; font-weight: bold;
                 font-size: 11px; white-space: nowrap; text-shadow: 0 0 2px #fff, 0 0 2px #fff; }
  </style>
@endpush

@section('content')

{{-- Inline JS config pro vizualizer.js --}}
<script @cspNonce>
window.__vizMap = {
    pcall: @json($pcall),
    homeLoc: @json($homeLoc),
    band: @json($band),
    home: @json($home),
    points: @json($points),
    t: @json(__('pages.vizualizer.js')),
};
</script>

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <h1 class="mb-0">{{ __('pages.vizualizer.heading') }} — {{ $pcall }}</h1>
    <a href="{{ route('vizualizer.create') }}" class="btn">{{ __('pages.vizualizer.new') }}</a>
</div>

<div id="viz-map"></div>

{{-- Souhrn deníku --}}
@php
    $cards = [
        [__('pages.vizualizer.sum_call'), $pcall !== '' ? $pcall : '—'],
        [__('pages.vizualizer.sum_loc'), $homeLoc !== '' ? $homeLoc : '—'],
        [__('pages.vizualizer.sum_band'), $band !== '' ? $band : '—'],
        [__('pages.vizualizer.sum_qso'), number_format($summary['qso'], 0, ',', ' ')],
        [__('pages.vizualizer.sum_avg'), $summary['avgDist'].' km'],
        [__('pages.vizualizer.sum_max'), $summary['maxDist'].' km'],
        [__('pages.vizualizer.sum_loc_unique'), $summary['uniqueLoc']],
        [__('pages.vizualizer.sum_sq_unique'), $summary['uniqueSq']],
    ];
@endphp
<section class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
    @foreach ($cards as [$label, $value])
        <div class="card p-3">
            <div class="text-xs text-muted">{{ $label }}</div>
            <div class="text-lg font-semibold text-heading">{{ $value }}</div>
        </div>
    @endforeach
</section>

{{-- Trvalý sdílecí odkaz – mobile-first: popisek nad polem, vstup + tlačítko
     vždy v jednom řádku (vstup se zužuje, tlačítko se nezalomí). --}}
<div class="card mt-4 p-3">
    <label for="viz-share" class="mb-1.5 block text-sm text-muted">{{ __('pages.vizualizer.share') }}</label>
    <div class="flex items-center gap-2">
        <input id="viz-share" type="text" readonly value="{{ route('vizualizer.show', ['token' => $token]) }}"
               class="min-w-0 flex-1 rounded border border-line bg-surface px-2 py-1.5 text-sm" data-share-url>
        <button type="button" class="btn shrink-0 whitespace-nowrap" data-copy-share
                data-copied="{{ __('pages.vizualizer.copied') }}">{{ __('pages.vizualizer.copy') }}</button>
    </div>
</div>

@endsection
