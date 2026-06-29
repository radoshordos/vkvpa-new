{{-- Samostatná stránka dlouhodobých trendů: 100% skládaný plošný graf podílu
     pásem napříč všemi vyhodnocenými koly, s přepínačem rozsahu let.
     Chart.js řídí trendy.js. --}}
@extends('layouts.app')

@section('title', __('pages.trendy.title'))
@section('meta_description', __('pages.trendy.meta'))

@push('head')
  @vite('resources/js/trendy.js')
  <style>
    .yr-tab { padding: .25rem .75rem; border-radius: .375rem; font-size: .8rem; font-weight: 600; cursor: pointer;
              border: 1px solid var(--color-line, #e2e8f0); background: var(--color-surface, #fff);
              color: var(--color-muted, #64748b); transition: background .15s, color .15s; }
    .yr-tab.active, .yr-tab:hover { background: var(--color-brand, #3b82f6); color: #fff; border-color: transparent; }
  </style>
@endpush

@section('content')

<script @cspNonce>
window.__trendyConfig = {
    pasmaTrend: @json($pasmaTrend),
    t: { stations: @json(__('pages.stat.js_stations')) },
};
</script>

<header class="mb-4">
  <div class="text-xs font-semibold uppercase tracking-wide text-muted">
    <a href="{{ route('statistiky.index') }}" class="underline hover:text-heading">{{ __('pages.stat.index_heading') }}</a>
  </div>
  <h1 class="text-xl font-bold text-heading sm:text-2xl">{{ __('pages.trendy.heading') }}</h1>
  <p class="mt-1 text-sm text-muted">{{ __('pages.trendy.subtitle') }}</p>
</header>

@if ($pasmaTrend['rounds'] !== [])
<div class="rounded-lg border border-line bg-surface p-3 mb-5">
  <div class="flex items-center gap-2 mb-2 flex-wrap">
    <span class="text-sm font-semibold text-heading">{{ __('pages.stat.chart_pasma') }}</span>
    <span class="ml-auto flex gap-1 flex-wrap">
      <button class="yr-tab" data-pasma-years="1">{{ trans_choice('pages.stat.pasma_year', 1) }}</button>
      <button class="yr-tab" data-pasma-years="2">{{ trans_choice('pages.stat.pasma_year', 2) }}</button>
      <button class="yr-tab" data-pasma-years="3">{{ trans_choice('pages.stat.pasma_year', 3) }}</button>
      <button class="yr-tab" data-pasma-years="5">{{ trans_choice('pages.stat.pasma_year', 5) }}</button>
      <button class="yr-tab active" data-pasma-years="0">{{ __('pages.stat.pasma_all') }}</button>
    </span>
  </div>
  <div class="h-80"><canvas id="chartPasma"></canvas></div>
  <p class="mt-2 text-xs text-muted">{{ __('pages.trendy.pasma_note') }}</p>
</div>
@else
<p class="text-sm text-muted">{{ __('pages.trendy.empty') }}</p>
@endif

@endsection
